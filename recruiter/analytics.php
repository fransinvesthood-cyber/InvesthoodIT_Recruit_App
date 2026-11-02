<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user = current_user();

/*
|--------------------------------------------------------------------------
| Export request (CSV is streamed before any layout output)
|--------------------------------------------------------------------------
*/

$allowedExports = [
    'all',
    'candidates',
    'skills',
    'qualifications',
    'shortlists',
    'followups',
];

$exportKey = trim((string)($_GET['export'] ?? ''));
$isExport  = in_array($exportKey, $allowedExports, true);

$exportFormat = strtolower(trim((string)($_GET['format'] ?? 'csv')));
$exportFormat = $exportFormat === 'pdf' ? 'pdf' : 'csv';

$flashes     = $isExport ? '' : render_flashes();
$currentPage = 'analytics';
$pageTitle   = 'Analytics & Reporting';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$recruiterId = (int)(
    $user['id']
    ?? $user['user_id']
    ?? 0
);

if ($recruiterId <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

/*
|--------------------------------------------------------------------------
| Page-local helpers (prefixed rc_an_ so nothing in _helpers.php is touched)
|--------------------------------------------------------------------------
*/

function rc_an_parse_date(string $value): ?DateTimeImmutable
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $date;
}

function rc_an_fetch(
    mysqli $conn,
    string $sql,
    string $types,
    array $params,
    string $label
): array {

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            $label . ' query failed: ' . $conn->error
        );
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    $rows = $result
        ? $result->fetch_all(MYSQLI_ASSOC)
        : [];

    $stmt->close();

    return $rows;
}

function rc_an_label(?string $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'Unknown';
    }

    return ucwords(str_replace(['_', '-'], ' ', strtolower($value)));
}

function rc_an_province_key(?string $value): string
{
    return strtolower(trim(str_replace(['-', '_'], ' ', (string)$value)));
}

function rc_an_province_label(?string $value): string
{
    $raw = trim((string)$value);

    if ($raw === '') {
        return 'Unknown';
    }

    static $map = null;

    if ($map === null) {

        $map = [];

        foreach ([
            'Gauteng',
            'Western Cape',
            'KwaZulu-Natal',
            'Eastern Cape',
            'Free State',
            'Limpopo',
            'Mpumalanga',
            'North West',
            'Northern Cape',
        ] as $name) {
            $map[rc_an_province_key($name)] = $name;
        }
    }

    $key = rc_an_province_key($raw);

    return $map[$key] ?? rc_an_label($raw);
}

function rc_an_link(
    array $filters,
    array $override = []
): string {

    $query = array_merge($filters, $override);

    $clean = [];

    foreach ($query as $key => $value) {

        if ($value === '' || $value === null || $value === 0) {
            continue;
        }

        $clean[$key] = $value;
    }

    $qs = http_build_query($clean);

    return url('recruiter/analytics.php')
        . ($qs !== '' ? '?' . $qs : '');
}

function rc_an_csv_cell($value)
{
    if ($value === null) {
        return '';
    }

    if (is_int($value) || is_float($value)) {
        return $value;
    }

    $text = (string)$value;

    // Neutralise spreadsheet formula injection.
    if (
        $text !== ''
        && strpbrk($text[0], "=+-@\t\r") !== false
    ) {
        return "'" . $text;
    }

    return $text;
}

function rc_an_stream_csv(
    string $filename,
    array $sections,
    bool $withTitles
): void {

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename="'
        . $filename . '"'
    );
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');

    // UTF-8 BOM so Excel reads accents correctly.
    fwrite($out, "\xEF\xBB\xBF");

    // South African/European Excel uses ";" as its list separator, so use it
    // here and also declare it, so every value lands in its own column.
    fwrite($out, "sep=;\r\n");

    $first = true;

    foreach ($sections as $section) {

        if (!$first && $withTitles) {
            fwrite($out, "\n");
        }

        $first = false;

        if ($withTitles) {
            fputcsv(
                $out,
                [rc_an_csv_cell((string)$section['title'])],
                ';',
                '"',
                '\\'
            );
        }

        fputcsv($out, $section['headers'], ';', '"', '\\');

        foreach ($section['rows'] as $row) {

            $cells = [];

            foreach ($row as $cell) {
                $cells[] = rc_an_csv_cell($cell);
            }

            fputcsv($out, $cells, ';', '"', '\\');
        }
    }

    fclose($out);

    exit;
}

/*
|--------------------------------------------------------------------------
| PDF export (self-contained - no Composer package required)
|--------------------------------------------------------------------------
*/

function rc_an_pdf_encode($value): string
{
    $text = preg_replace('/\s+/u', ' ', (string)$value);
    $text = $text === null ? '' : trim($text);

    if ($text === '') {
        return '';
    }

    $out = function_exists('iconv')
        ? @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text)
        : false;

    if ($out === false || $out === null) {
        $out = preg_replace('/[^\x20-\x7E]/', '?', $text);
    }

    return (string)$out;
}

function rc_an_pdf_string_width(string $text, float $size, bool $bold = false): float
{
    static $w = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556,
        278, 278, 584, 584, 584, 556, 1015,
        667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833,
        722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611,
        278, 278, 278, 469, 556, 333,
        556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833,
        556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500,
        334, 260, 334, 584,
    ];

    $total = 0;
    $len   = strlen($text);

    for ($i = 0; $i < $len; $i++) {
        $c      = ord($text[$i]);
        $total += ($c >= 32 && $c <= 126) ? $w[$c - 32] : 556;
    }

    return ($total / 1000) * $size * ($bold ? 1.06 : 1.0);
}

function rc_an_pdf_wrap(string $text, float $maxWidth, float $size, bool $bold = false, int $maxLines = 12): array
{
    if ($text === '') {
        return [''];
    }

    $lines = [];
    $line  = '';

    foreach (explode(' ', $text) as $word) {

        if ($word === '') {
            continue;
        }

        $try = $line === '' ? $word : $line . ' ' . $word;

        if (rc_an_pdf_string_width($try, $size, $bold) <= $maxWidth) {
            $line = $try;
            continue;
        }

        if ($line !== '') {
            $lines[] = $line;
            $line    = '';
        }

        // Word longer than the column: break it by character.
        while (rc_an_pdf_string_width($word, $size, $bold) > $maxWidth && strlen($word) > 1) {

            $cut = strlen($word) - 1;

            while ($cut > 1 && rc_an_pdf_string_width(substr($word, 0, $cut), $size, $bold) > $maxWidth) {
                $cut--;
            }

            $lines[] = substr($word, 0, $cut);
            $word    = substr($word, $cut);
        }

        $line = $word;
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    if (count($lines) > $maxLines) {
        $lines            = array_slice($lines, 0, $maxLines);
        $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], '. ') . '...';
    }

    return $lines ?: [''];
}

final class RcAnPdfReport
{
    private float $pageW  = 841.89; // A4 landscape
    private float $pageH  = 595.28;
    private float $margin = 36.0;

    private array $pages = [];
    private string $cur  = '';
    private float $y     = 0.0;

    public function __construct()
    {
        $this->newPage();
    }

    private function num(float $v): string
    {
        return number_format($v, 2, '.', '');
    }

    private function esc(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    private function bottom(): float
    {
        return $this->margin + 14;
    }

    private function newPage(): void
    {
        if ($this->cur !== '' || $this->pages) {
            $this->pages[] = $this->cur;
        }

        $this->cur = '';
        $this->y   = $this->pageH - $this->margin;
    }

    private function text(float $x, float $y, string $s, float $size, bool $bold = false, array $rgb = [0, 0, 0]): void
    {
        if ($s === '') {
            return;
        }

        $this->cur .= sprintf(
            "BT %s %s %s rg /%s %s Tf %s %s Td (%s) Tj ET\n",
            $this->num($rgb[0]), $this->num($rgb[1]), $this->num($rgb[2]),
            $bold ? 'F2' : 'F1',
            $this->num($size),
            $this->num($x), $this->num($y),
            $this->esc($s)
        );
    }

    private function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->cur .= sprintf(
            "%s %s %s rg %s %s %s %s re f\n",
            $this->num($rgb[0]), $this->num($rgb[1]), $this->num($rgb[2]),
            $this->num($x), $this->num($y), $this->num($w), $this->num($h)
        );
    }

    public function heading(string $title, array $meta): void
    {
        $this->text($this->margin, $this->y - 16, rc_an_pdf_encode($title), 16, true, [0.10, 0.18, 0.32]);
        $this->y -= 24;

        foreach ($meta as $label => $value) {
            $this->text(
                $this->margin,
                $this->y - 9,
                rc_an_pdf_encode($label . ': ' . $value),
                8,
                false,
                [0.35, 0.38, 0.42]
            );
            $this->y -= 11;
        }

        $this->y -= 8;
    }

    public function table(string $title, array $headers, array $rows): void
    {
        // Keep a section title together with at least a header + one row.
        if ($this->y - 70 < $this->bottom()) {
            $this->newPage();
        }

        $this->text($this->margin, $this->y - 12, rc_an_pdf_encode($title), 11, true, [0.10, 0.18, 0.32]);
        $this->y -= 20;

        $n = count($headers);

        if ($rows === []) {
            $this->text($this->margin, $this->y - 9, 'No data for the selected filters.', 8.5, false, [0.4, 0.4, 0.4]);
            $this->y -= 22;
            return;
        }

        $size  = $n >= 14 ? 6.2 : ($n >= 9 ? 7.0 : 8.0);
        $lead  = $size + 2.6;
        $padX  = 3.0;
        $padY  = 2.6;
        $avail = $this->pageW - 2 * $this->margin;

        // Encode once.
        $encHeaders = array_map('rc_an_pdf_encode', $headers);
        $encRows    = [];

        foreach ($rows as $row) {
            $r = [];
            for ($i = 0; $i < $n; $i++) {
                $r[] = rc_an_pdf_encode($row[$i] ?? '');
            }
            $encRows[] = $r;
        }

        // Column widths from content.
        $desired = [];
        $floor   = [];

        for ($i = 0; $i < $n; $i++) {

            $max     = 0;
            $longest = 0;

            foreach (explode(' ', $encHeaders[$i]) as $word) {
                $max     = max($max, rc_an_pdf_string_width($word, $size, true));
                $longest = max($longest, rc_an_pdf_string_width($word, $size, true));
            }

            $natural = rc_an_pdf_string_width($encHeaders[$i], $size, true);
            $max     = max($max, min($natural, 70));

            foreach (array_slice($encRows, 0, 300) as $r) {
                $max = max($max, min(rc_an_pdf_string_width($r[$i], $size), 170));

                foreach (explode(' ', $r[$i]) as $word) {
                    $longest = max($longest, min(rc_an_pdf_string_width($word, $size), 80));
                }
            }

            $pad         = 2 * $padX + 2;
            $desired[$i] = max(26.0, $max + $pad);
            $floor[$i]   = max(22.0, min($longest + $pad, $desired[$i]));
        }

        // Shrink columns toward their own floor (longest single word) so
        // words are not split mid-word wherever it can be avoided.
        $w = $desired;

        for ($pass = 0; $pass < 8; $pass++) {

            $sum = array_sum($w);

            if ($sum <= $avail + 0.5) {
                break;
            }

            $fixed = 0.0;
            $flex  = 0.0;

            foreach ($w as $i => $v) {
                if ($v <= $floor[$i] + 0.01) {
                    $fixed += $v;
                } else {
                    $flex += $v - $floor[$i];
                }
            }

            if ($flex <= 0) {
                break;
            }

            $f = max(0.0, min(1.0, ($avail - $fixed - array_sum(array_map(
                fn ($i) => $w[$i] > $floor[$i] + 0.01 ? $floor[$i] : 0,
                array_keys($w)
            ))) / $flex));

            foreach ($w as $i => $v) {
                if ($v > $floor[$i] + 0.01) {
                    $w[$i] = $floor[$i] + ($v - $floor[$i]) * $f;
                }
            }
        }

        $sum = array_sum($w);

        if ($sum > 0 && abs($sum - $avail) > 0.5) {
            $f = $avail / $sum;
            foreach ($w as $i => $v) {
                $w[$i] = $v * $f;
            }
        }

        $drawHeader = function () use ($encHeaders, $w, $n, $size, $lead, $padX, $padY): void {

            $cells  = [];
            $maxLn  = 1;

            for ($i = 0; $i < $n; $i++) {
                $cells[$i] = rc_an_pdf_wrap($encHeaders[$i], $w[$i] - 2 * $padX, $size, true, 4);
                $maxLn     = max($maxLn, count($cells[$i]));
            }

            $h = $maxLn * $lead + 2 * $padY;

            $this->rect($this->margin, $this->y - $h, array_sum($w), $h, [0.10, 0.18, 0.32]);

            $x = $this->margin;

            for ($i = 0; $i < $n; $i++) {
                foreach ($cells[$i] as $ln => $line) {
                    $this->text(
                        $x + $padX,
                        $this->y - $padY - $size - $ln * $lead,
                        $line,
                        $size,
                        true,
                        [1, 1, 1]
                    );
                }
                $x += $w[$i];
            }

            $this->y -= $h;
        };

        $drawHeader();

        foreach ($encRows as $rIndex => $r) {

            $cells = [];
            $maxLn = 1;

            for ($i = 0; $i < $n; $i++) {
                $cells[$i] = rc_an_pdf_wrap($r[$i], $w[$i] - 2 * $padX, $size);
                $maxLn     = max($maxLn, count($cells[$i]));
            }

            $h = $maxLn * $lead + 2 * $padY;

            if ($this->y - $h < $this->bottom()) {
                $this->newPage();
                $drawHeader();
            }

            if ($rIndex % 2 === 1) {
                $this->rect($this->margin, $this->y - $h, array_sum($w), $h, [0.95, 0.96, 0.98]);
            }

            $x = $this->margin;

            for ($i = 0; $i < $n; $i++) {
                foreach ($cells[$i] as $ln => $line) {
                    $this->text(
                        $x + $padX,
                        $this->y - $padY - $size - $ln * $lead,
                        $line,
                        $size
                    );
                }
                $x += $w[$i];
            }

            $this->y -= $h;
        }

        $this->y -= 18;
    }

    public function output(string $filename, string $footerLeft): void
    {
        $this->pages[] = $this->cur;
        $this->cur     = '';

        $total = count($this->pages);

        foreach ($this->pages as $i => $content) {

            $label = 'Page ' . ($i + 1) . ' of ' . $total;
            $fw    = rc_an_pdf_string_width($label, 7);

            $this->cur = '';
            $this->text($this->margin, $this->margin - 6, rc_an_pdf_encode($footerLeft), 7, false, [0.45, 0.45, 0.5]);
            $this->text($this->pageW - $this->margin - $fw, $this->margin - 6, $label, 7, false, [0.45, 0.45, 0.5]);

            $this->pages[$i] = $content . $this->cur;
        }

        $objs   = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $kids = [];

        foreach ($this->pages as $i => $_) {
            $kids[] = (5 + 2 * $i) . ' 0 R';
        }

        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $compress = function_exists('gzcompress');

        foreach ($this->pages as $i => $content) {

            $pageId = 5 + 2 * $i;
            $contId = $pageId + 1;

            $objs[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . $this->num($this->pageW) . ' ' . $this->num($this->pageH) . '] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> '
                . '/Contents ' . $contId . ' 0 R >>';

            $stream = $compress ? gzcompress($content, 6) : $content;

            $objs[$contId] = '<< /Length ' . strlen($stream)
                . ($compress ? ' /Filter /FlateDecode' : '')
                . " >>\nstream\n" . $stream . "\nendstream";
        }

        $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        ksort($objs);

        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $max  = max(array_keys($objs));

        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";

        for ($id = 1; $id <= $max; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo $pdf;

        exit;
    }
}

function rc_an_stream_pdf(
    string $filename,
    array $sections,
    array $meta
): void {

    $pdf = new RcAnPdfReport();

    $pdf->heading('Recruiter Analytics & Reporting', $meta);

    foreach ($sections as $section) {
        $pdf->table(
            (string)$section['title'],
            $section['headers'],
            $section['rows']
        );
    }

    $pdf->output($filename, 'Recruiter Analytics & Reporting');
}

function rc_an_distribution(array $counts, int $total): array
{
    uksort(
        $counts,
        function ($a, $b) use ($counts) {

            if ($counts[$a] === $counts[$b]) {
                return strcmp((string)$a, (string)$b);
            }

            return $counts[$b] <=> $counts[$a];
        }
    );

    $rows = [];

    foreach ($counts as $label => $count) {

        $rows[] = [
            'label' => (string)$label,
            'count' => (int)$count,
            'pct'   => $total > 0
                ? round(($count / $total) * 100, 1)
                : 0.0,
        ];
    }

    return $rows;
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
|
| date_from / date_to : inclusive range (defaults to the last 90 days)
| province            : one of the nine provinces
| skill               : skills.id
| shortlist           : recruiter_shortlists.id (must belong to recruiter)
|
*/

$provinces = [
    'Gauteng',
    'Western Cape',
    'KwaZulu-Natal',
    'Eastern Cape',
    'Free State',
    'Limpopo',
    'Mpumalanga',
    'North West',
    'Northern Cape',
];

$today = new DateTimeImmutable('today');

$to   = rc_an_parse_date((string)($_GET['date_to'] ?? ''));
$from = rc_an_parse_date((string)($_GET['date_from'] ?? ''));

if (!$to) {
    $to = $today;
}

if (!$from) {
    $from = $to->modify('-89 days');
}

if ($from > $to) {
    $swap = $from;
    $from = $to;
    $to   = $swap;
}

$fromStart = $from->format('Y-m-d') . ' 00:00:00';
$toEnd     = $to->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

$province = '';
$provinceInput = trim((string)($_GET['province'] ?? ''));

foreach ($provinces as $value) {
    if ($provinceInput !== '' && strcasecmp($provinceInput, $value) === 0) {
        $province = $value;
        break;
    }
}

/*
|--------------------------------------------------------------------------
| Table availability
|--------------------------------------------------------------------------
*/

$hasShortlists          = rc_has_table($conn, 'recruiter_shortlists');
$hasShortlistCandidates = rc_has_table($conn, 'recruiter_shortlist_candidates');
$hasProfiles            = rc_has_table($conn, 'candidate_profiles');
$hasConsents            = rc_has_table($conn, 'candidate_consents');
$hasSkills              = rc_has_table($conn, 'candidate_skills')
                          && rc_has_table($conn, 'skills');
$hasActivity            = rc_has_table($conn, 'recruiter_activity_log');
$hasQuals               = rc_has_table($conn, 'qualifications');
$hasAvailability        = rc_has_table($conn, 'availability_statuses');
$hasWorkflow            = $hasShortlists && $hasShortlistCandidates;

/*
|--------------------------------------------------------------------------
| Filter option lists (own shortlists, active skills)
|--------------------------------------------------------------------------
*/

$shortlistOptions = [];

if ($hasShortlists) {

    $shortlistOptions = rc_an_fetch(
        $conn,
        "SELECT id, name
         FROM recruiter_shortlists
         WHERE recruiter_id = ?
         ORDER BY name ASC, id ASC",
        'i',
        [$recruiterId],
        'Shortlist filter'
    );
}

$skillOptions = [];

if ($hasSkills) {

    $skillOptions = rc_an_fetch(
        $conn,
        "SELECT id, name
         FROM skills
         WHERE is_active = 1
         ORDER BY name ASC, id ASC",
        '',
        [],
        'Skill filter'
    );
}

$shortlistId   = (int)($_GET['shortlist'] ?? 0);
$shortlistName = '';

foreach ($shortlistOptions as $option) {
    if ((int)$option['id'] === $shortlistId) {
        $shortlistName = (string)$option['name'];
    }
}

if ($shortlistName === '') {
    $shortlistId = 0;
}

$skillId   = (int)($_GET['skill'] ?? 0);
$skillName = '';

foreach ($skillOptions as $option) {
    if ((int)$option['id'] === $skillId) {
        $skillName = (string)$option['name'];
    }
}

if ($skillName === '') {
    $skillId = 0;
}

$filters = [
    'date_from' => $from->format('Y-m-d'),
    'date_to'   => $to->format('Y-m-d'),
    'province'  => $province,
    'skill'     => $skillId,
    'shortlist' => $shortlistId,
];

/*
|--------------------------------------------------------------------------
| Core dataset: filtered shortlist entries
|--------------------------------------------------------------------------
|
| One row per candidate-in-shortlist (recruiter_shortlist_candidates).
| The date range applies to the date the candidate was added.
|
*/

$pool = [];

if ($hasWorkflow) {

    $rid = (int)$recruiterId;

    $profileSelect = $hasProfiles
        ? "cp.professional_title AS profile_title,
           cp.city AS city,
           cp.completion_percent AS completion_percent,
           cp.availability_date AS availability_date"
        : "NULL AS profile_title,
           NULL AS city,
           NULL AS completion_percent,
           NULL AS availability_date";

    $profileJoin = $hasProfiles
        ? "LEFT JOIN candidate_profiles cp ON cp.user_id = u.id"
        : "";

    $consentSelect = $hasConsents
        ? "(
                SELECT MAX(COALESCE(cc.expires_at, '9999-12-31 23:59:59'))
                FROM candidate_consents cc
                WHERE cc.candidate_id = u.id
                  AND cc.consent_type = 'recruiter_discovery'
                  AND cc.status = 'consented'
                  AND (cc.expires_at IS NULL OR cc.expires_at > NOW())
           ) AS consent_valid_until,
           (
                SELECT cc2.status
                FROM candidate_consents cc2
                WHERE cc2.candidate_id = u.id
                  AND cc2.consent_type = 'recruiter_discovery'
                ORDER BY cc2.id DESC
                LIMIT 1
           ) AS consent_last_status"
        : "NULL AS consent_valid_until,
           NULL AS consent_last_status";

    $skillSelect = $hasSkills
        ? "(
                SELECT COUNT(DISTINCT cs_t.skill_id)
                FROM candidate_skills cs_t
                INNER JOIN skills s_t ON s_t.id = cs_t.skill_id
                WHERE cs_t.user_id = u.id
                  AND s_t.is_active = 1
           ) AS total_skills,
           (
                SELECT COUNT(DISTINCT cs_v.skill_id)
                FROM candidate_skills cs_v
                INNER JOIN skills s_v ON s_v.id = cs_v.skill_id
                WHERE cs_v.user_id = u.id
                  AND s_v.is_active = 1
                  AND cs_v.verification_status = 'verified'
           ) AS verified_skills,
           (
                SELECT COUNT(DISTINCT cs_p.skill_id)
                FROM candidate_skills cs_p
                INNER JOIN skills s_p ON s_p.id = cs_p.skill_id
                WHERE cs_p.user_id = u.id
                  AND s_p.is_active = 1
                  AND cs_p.verification_status = 'pending'
           ) AS pending_skills"
        : "0 AS total_skills,
           0 AS verified_skills,
           0 AS pending_skills";

    $viewSelect = $hasActivity
        ? "(
                SELECT MAX(al.created_at)
                FROM recruiter_activity_log al
                WHERE al.recruiter_id = {$rid}
                  AND al.action = 'candidate_profile_viewed'
                  AND al.entity_type = 'candidate'
                  AND al.entity_id = u.id
           ) AS last_viewed_at"
        : "NULL AS last_viewed_at";

    $availSelect = ($hasProfiles && $hasAvailability)
        ? "(
                SELECT av.slug
                FROM availability_statuses av
                WHERE av.id = cp.availability_status_id
                LIMIT 1
           ) AS availability_slug"
        : "NULL AS availability_slug";

    $qualSelect = $hasQuals
        ? "(
                SELECT COUNT(*)
                FROM qualifications q_t
                WHERE q_t.user_id = u.id
           ) AS total_quals,
           (
                SELECT COUNT(*)
                FROM qualifications q_v
                WHERE q_v.user_id = u.id
                  AND q_v.verification_status = 'verified'
           ) AS verified_quals,
           (
                SELECT COUNT(*)
                FROM qualifications q_p
                WHERE q_p.user_id = u.id
                  AND q_p.verification_status = 'pending'
           ) AS pending_quals"
        : "0 AS total_quals,
           0 AS verified_quals,
           0 AS pending_quals";

    $where  = [
        'rs.recruiter_id = ?',
        "r.slug = 'candidate'",
        'rsc.added_at >= ?',
        'rsc.added_at < ?',
    ];
    $types  = 'iss';
    $params = [$recruiterId, $fromStart, $toEnd];

    if ($province !== '') {
        // users.province is stored as a slug (e.g. "western-cape"), so compare normalised.
        $where[]  = "LOWER(REPLACE(REPLACE(TRIM(u.province), '-', ' '), '_', ' ')) = ?";
        $types   .= 's';
        $params[] = rc_an_province_key($province);
    }

    if ($shortlistId > 0) {
        $where[]  = 'rs.id = ?';
        $types   .= 'i';
        $params[] = $shortlistId;
    }

    if ($skillId > 0 && $hasSkills) {
        $where[] = "EXISTS (
            SELECT 1
            FROM candidate_skills cs_f
            INNER JOIN skills s_f ON s_f.id = cs_f.skill_id
            WHERE cs_f.user_id = u.id
              AND s_f.is_active = 1
              AND s_f.id = ?
        )";
        $types   .= 'i';
        $params[] = $skillId;
    }

    $sql = "
        SELECT
            rsc.id AS rsc_id,
            rsc.shortlist_id,
            rsc.candidate_id,
            rsc.added_at,

            rs.name AS shortlist_name,
            rs.client_name,
            rs.status AS shortlist_status,

            u.first_name,
            u.last_name,
            u.province,
            u.qualification_level,
            u.employment_status,
            u.professional_title,

            {$profileSelect},
            {$consentSelect},
            {$skillSelect},
            {$availSelect},
            {$qualSelect},
            {$viewSelect}

        FROM recruiter_shortlist_candidates rsc

        INNER JOIN recruiter_shortlists rs
            ON rs.id = rsc.shortlist_id

        INNER JOIN users u
            ON u.id = rsc.candidate_id

        INNER JOIN roles r
            ON r.id = u.role_id

        {$profileJoin}

        WHERE " . implode(' AND ', $where) . "

        ORDER BY rsc.added_at DESC, rsc.id DESC
    ";

    $rows = rc_an_fetch($conn, $sql, $types, $params, 'Analytics pool');

    // Keyed by rsc_id so an accidental duplicate profile join can never double count.
    foreach ($rows as $row) {
        $pool[(int)$row['rsc_id']] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Unique candidates in the filtered pool
|--------------------------------------------------------------------------
*/

$candidates = [];

foreach ($pool as $row) {

    $cid = (int)$row['candidate_id'];

    if (!isset($candidates[$cid])) {
        $candidates[$cid] = $row;
    }
}

$entryCount  = count($pool);
$uniqueCount = count($candidates);

$shortlistIdsInPool = [];

foreach ($pool as $row) {
    $shortlistIdsInPool[(int)$row['shortlist_id']] = true;
}

$shortlistCount = count($shortlistIdsInPool);

/*
|--------------------------------------------------------------------------
| Qualifications / employment / province distributions
|--------------------------------------------------------------------------
*/

$qualCounts = [];
$empCounts  = [];
$provCounts = [];

$completionSum   = 0;
$validConsentCnt = 0;
$verifiedCnt     = 0;
$noSkillsCnt     = 0;
$noQualCnt       = 0;
$verifiedQualCnt = 0;

foreach ($candidates as $row) {

    $qual = rc_an_label((string)($row['qualification_level'] ?? ''));
    $emp  = rc_an_label((string)($row['employment_status'] ?? ''));
    $prov = rc_an_province_label((string)($row['province'] ?? ''));

    $qualCounts[$qual] = ($qualCounts[$qual] ?? 0) + 1;
    $empCounts[$emp]   = ($empCounts[$emp] ?? 0) + 1;
    $provCounts[$prov] = ($provCounts[$prov] ?? 0) + 1;

    $completionSum += (int)($row['completion_percent'] ?? 0);

    if ($hasConsents && $row['consent_valid_until'] !== null) {
        $validConsentCnt++;
    }

    if ((int)($row['verified_skills'] ?? 0) > 0) {
        $verifiedCnt++;
    }

    if ($hasSkills && (int)($row['total_skills'] ?? 0) === 0) {
        $noSkillsCnt++;
    }

    if ($hasQuals && (int)($row['total_quals'] ?? 0) === 0) {
        $noQualCnt++;
    }

    if ($hasQuals && (int)($row['verified_quals'] ?? 0) > 0) {
        $verifiedQualCnt++;
    }
}

$qualDist = rc_an_distribution($qualCounts, $uniqueCount);
$empDist  = rc_an_distribution($empCounts, $uniqueCount);
$provDist = rc_an_distribution($provCounts, $uniqueCount);

$avgCompletion = ($hasProfiles && $uniqueCount > 0)
    ? (int)round($completionSum / $uniqueCount)
    : 0;

/*
|--------------------------------------------------------------------------
| Qualification records (qualifications table, by level)
|--------------------------------------------------------------------------
*/

$qualRecords = [];

if ($hasQuals && $uniqueCount > 0) {

    foreach (array_chunk(array_keys($candidates), 500) as $chunk) {

        $marks = implode(',', array_fill(0, count($chunk), '?'));
        $types = str_repeat('i', count($chunk));

        $rows = rc_an_fetch(
            $conn,
            "SELECT
                COALESCE(TRIM(q.level), '') AS level_key,
                COUNT(DISTINCT q.user_id) AS candidates,
                COUNT(*) AS records,
                SUM(CASE WHEN q.verification_status = 'verified'
                         THEN 1 ELSE 0 END) AS verified,
                SUM(CASE WHEN q.verification_status = 'pending'
                         THEN 1 ELSE 0 END) AS pending
             FROM qualifications q
             WHERE q.user_id IN ({$marks})
             GROUP BY level_key",
            $types,
            $chunk,
            'Qualification records'
        );

        foreach ($rows as $row) {

            $key = (string)$row['level_key'];

            if (!isset($qualRecords[$key])) {
                $qualRecords[$key] = [
                    'label'      => $key === ''
                        ? 'Not specified'
                        : rc_an_label($key),
                    'candidates' => 0,
                    'records'    => 0,
                    'verified'   => 0,
                    'pending'    => 0,
                ];
            }

            $qualRecords[$key]['candidates'] += (int)$row['candidates'];
            $qualRecords[$key]['records']    += (int)$row['records'];
            $qualRecords[$key]['verified']   += (int)$row['verified'];
            $qualRecords[$key]['pending']    += (int)$row['pending'];
        }
    }

    uasort(
        $qualRecords,
        function ($a, $b) {

            if ($a['candidates'] !== $b['candidates']) {
                return $b['candidates'] <=> $a['candidates'];
            }

            return strcasecmp($a['label'], $b['label']);
        }
    );
}

/*
|--------------------------------------------------------------------------
| Skills insight
|--------------------------------------------------------------------------
|
| Every active skill held by the filtered candidates, with how many of
| those candidates hold it and how many are verified / pending.
|
*/

$skillStats = [];

if ($hasSkills && $uniqueCount > 0) {

    foreach (array_chunk(array_keys($candidates), 500) as $chunk) {

        $marks = implode(',', array_fill(0, count($chunk), '?'));
        $types = str_repeat('i', count($chunk));

        $rows = rc_an_fetch(
            $conn,
            "SELECT
                s.id,
                s.name,
                s.category,
                COUNT(DISTINCT cs.user_id) AS candidates,
                COUNT(DISTINCT CASE
                    WHEN cs.verification_status = 'verified'
                    THEN cs.user_id END) AS verified,
                COUNT(DISTINCT CASE
                    WHEN cs.verification_status = 'pending'
                    THEN cs.user_id END) AS pending
             FROM candidate_skills cs
             INNER JOIN skills s ON s.id = cs.skill_id
             WHERE s.is_active = 1
               AND cs.user_id IN ({$marks})
             GROUP BY s.id, s.name, s.category",
            $types,
            $chunk,
            'Skill insight'
        );

        foreach ($rows as $row) {

            $sid = (int)$row['id'];

            if (!isset($skillStats[$sid])) {
                $skillStats[$sid] = [
                    'id'         => $sid,
                    'name'       => (string)$row['name'],
                    'category'   => (string)($row['category'] ?? ''),
                    'candidates' => 0,
                    'verified'   => 0,
                    'pending'    => 0,
                ];
            }

            $skillStats[$sid]['candidates'] += (int)$row['candidates'];
            $skillStats[$sid]['verified']   += (int)$row['verified'];
            $skillStats[$sid]['pending']    += (int)$row['pending'];
        }
    }

    usort(
        $skillStats,
        function ($a, $b) {

            if ($a['candidates'] !== $b['candidates']) {
                return $b['candidates'] <=> $a['candidates'];
            }

            if ($a['verified'] !== $b['verified']) {
                return $b['verified'] <=> $a['verified'];
            }

            return strcasecmp($a['name'], $b['name']);
        }
    );
}

/*
|--------------------------------------------------------------------------
| Recruiter productivity: shortlist output (filtered pool)
|--------------------------------------------------------------------------
*/

$shortlistOutput = [];

foreach ($pool as $row) {

    $sid = (int)$row['shortlist_id'];

    if (!isset($shortlistOutput[$sid])) {
        $shortlistOutput[$sid] = [
            'id'          => $sid,
            'name'        => (string)$row['shortlist_name'],
            'client'      => (string)($row['client_name'] ?? ''),
            'status'      => (string)($row['shortlist_status'] ?? ''),
            'candidates'  => 0,
            'verified'    => 0,
            'completion'  => 0,
            'last_added'  => (string)$row['added_at'], // pool is ordered newest first
        ];
    }

    $shortlistOutput[$sid]['candidates']++;
    $shortlistOutput[$sid]['completion'] += (int)($row['completion_percent'] ?? 0);

    if ((int)($row['verified_skills'] ?? 0) > 0) {
        $shortlistOutput[$sid]['verified']++;
    }
}

foreach ($shortlistOutput as $sid => $item) {

    $shortlistOutput[$sid]['avg_completion'] = $item['candidates'] > 0
        ? (int)round($item['completion'] / $item['candidates'])
        : 0;
}

usort(
    $shortlistOutput,
    function ($a, $b) {

        if ($a['candidates'] !== $b['candidates']) {
            return $b['candidates'] <=> $a['candidates'];
        }

        return strcasecmp($a['name'], $b['name']);
    }
);

$avgPerShortlist = $shortlistCount > 0
    ? round($entryCount / $shortlistCount, 1)
    : 0.0;

/*
|--------------------------------------------------------------------------
| Recruiter productivity: shortlists created in range
|--------------------------------------------------------------------------
*/

$shortlistsCreated = 0;

if ($hasShortlists) {

    $sqlCreated = "SELECT COUNT(*) AS n
                   FROM recruiter_shortlists rs
                   WHERE rs.recruiter_id = ?
                     AND rs.created_at >= ?
                     AND rs.created_at < ?";
    $typesCreated  = 'iss';
    $paramsCreated = [$recruiterId, $fromStart, $toEnd];

    if ($shortlistId > 0) {
        $sqlCreated    .= ' AND rs.id = ?';
        $typesCreated  .= 'i';
        $paramsCreated[] = $shortlistId;
    }

    $created = rc_an_fetch(
        $conn,
        $sqlCreated,
        $typesCreated,
        $paramsCreated,
        'Shortlists created'
    );

    $shortlistsCreated = (int)($created[0]['n'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Recruiter productivity: activity log (date range only)
|--------------------------------------------------------------------------
*/

$actionLabels = [
    'candidate_search'         => 'Candidate searches',
    'candidate_profile_viewed' => 'Profiles viewed',
    'candidate_shortlisted'    => 'Candidates shortlisted',
    'shortlist_created'        => 'Shortlists created',
    'shortlist_view'           => 'Shortlists viewed',
    'talent_pool_viewed'       => 'Talent pool views',
    'recruiter_reports_viewed' => 'Report views',
];

$actionRows   = [];
$actionCounts = [];
$activityTotal = 0;
$activeDays    = 0;
$trend         = [];
$trendMonthly  = false;

if ($hasActivity) {

    $excluded = "'analytics_viewed','analytics_exported'";

    $actionRows = rc_an_fetch(
        $conn,
        "SELECT action, COUNT(*) AS total, MAX(created_at) AS last_at
         FROM recruiter_activity_log
         WHERE recruiter_id = ?
           AND created_at >= ?
           AND created_at < ?
           AND action NOT IN ({$excluded})
         GROUP BY action
         ORDER BY total DESC, action ASC",
        'iss',
        [$recruiterId, $fromStart, $toEnd],
        'Activity actions'
    );

    foreach ($actionRows as $row) {
        $actionCounts[(string)$row['action']] = (int)$row['total'];
        $activityTotal += (int)$row['total'];
    }

    $daysRow = rc_an_fetch(
        $conn,
        "SELECT COUNT(DISTINCT DATE(created_at)) AS n
         FROM recruiter_activity_log
         WHERE recruiter_id = ?
           AND created_at >= ?
           AND created_at < ?
           AND action NOT IN ({$excluded})",
        'iss',
        [$recruiterId, $fromStart, $toEnd],
        'Activity days'
    );

    $activeDays = (int)($daysRow[0]['n'] ?? 0);

    $spanDays     = (int)$from->diff($to)->days + 1;
    $trendMonthly = $spanDays > 92;

    $bucketSql = $trendMonthly
        ? "DATE_FORMAT(created_at, '%Y-%m')"
        : "DATE(created_at)";

    $trendRows = rc_an_fetch(
        $conn,
        "SELECT {$bucketSql} AS bucket, COUNT(*) AS total
         FROM recruiter_activity_log
         WHERE recruiter_id = ?
           AND created_at >= ?
           AND created_at < ?
           AND action NOT IN ({$excluded})
         GROUP BY bucket",
        'iss',
        [$recruiterId, $fromStart, $toEnd],
        'Activity trend'
    );

    $trendMap = [];

    foreach ($trendRows as $row) {
        $trendMap[(string)$row['bucket']] = (int)$row['total'];
    }

    if ($trendMonthly) {

        $cursor = $from->modify('first day of this month');
        $last   = $to->modify('first day of this month');

        while ($cursor <= $last) {

            $key = $cursor->format('Y-m');

            $trend[] = [
                'key'   => $key,
                'label' => $cursor->format('M Y'),
                'count' => $trendMap[$key] ?? 0,
            ];

            $cursor = $cursor->modify('+1 month');
        }

    } else {

        $cursor = $from;

        while ($cursor <= $to) {

            $key = $cursor->format('Y-m-d');

            $trend[] = [
                'key'   => $key,
                'label' => $cursor->format('d M Y'),
                'count' => $trendMap[$key] ?? 0,
            ];

            $cursor = $cursor->modify('+1 day');
        }
    }
}

$avgActionsPerDay = $activeDays > 0
    ? round($activityTotal / $activeDays, 1)
    : 0.0;

$trendMax = 0;

foreach ($trend as $point) {
    if ($point['count'] > $trendMax) {
        $trendMax = $point['count'];
    }
}

/*
|--------------------------------------------------------------------------
| Outstanding follow-ups (derived)
|--------------------------------------------------------------------------
|
| No follow-up task table exists, so follow-ups are derived from live
| shortlist, consent, skill, profile and activity records. Shortlists
| with a closed-type status are ignored.
|
*/

$FU_STALE_DAYS     = 7;
$FU_EXPIRY_DAYS    = 14;
$FU_AVAILABLE_DAYS = 14;
$FU_PROFILE_MIN    = 60;
$FU_EMPTY_DAYS     = 3;
$FU_DISPLAY_LIMIT  = 50;

$closedStatuses = [
    'archived',
    'closed',
    'completed',
    'cancelled',
    'canceled',
    'inactive',
];

$fuTypes = [
    'consent_lost'         => ['label' => 'Consent not valid',           'priority' => 1],
    'consent_expiring'     => ['label' => 'Consent expiring',            'priority' => 2],
    'not_reviewed'         => ['label' => 'Profile not reviewed',        'priority' => 2],
    'available_soon'       => ['label' => 'Becoming available',          'priority' => 2],
    'verification_pending' => ['label' => 'Skill verification pending',  'priority' => 3],
    'qualification_pending' => ['label' => 'Qualification verification pending', 'priority' => 3],
    'profile_incomplete'   => ['label' => 'Profile incomplete',          'priority' => 3],
    'empty_shortlist'      => ['label' => 'Empty shortlist',             'priority' => 3],
];

$priorityLabels = [1 => 'High', 2 => 'Medium', 3 => 'Low'];

$followups = [];

$now     = time();
$todayTs = strtotime('today');

foreach ($pool as $row) {

    $status = strtolower(trim((string)($row['shortlist_status'] ?? '')));

    if (in_array($status, $closedStatuses, true)) {
        continue;
    }

    $cid  = (int)$row['candidate_id'];
    $name = trim(
        (string)$row['first_name'] . ' ' . (string)$row['last_name']
    );

    if ($name === '') {
        $name = 'Candidate';
    }

    $title = trim((string)($row['profile_title'] ?: $row['professional_title'] ?: ''));

    $validUntil = $row['consent_valid_until'];
    $consentOk  = !$hasConsents || $validUntil !== null;

    $base = [
        'candidate_id' => $cid,
        'candidate'    => $name,
        'title'        => $title,
        'shortlist_id' => (int)$row['shortlist_id'],
        'shortlist'    => (string)$row['shortlist_name'],
        'linkable'     => $consentOk,
    ];

    // 1. Consent no longer valid
    if ($hasConsents && $validUntil === null) {

        $last = strtolower(trim((string)($row['consent_last_status'] ?? '')));

        if ($last === '') {
            $detail = 'No recruiter-discovery consent on record.';
        } elseif ($last === 'revoked') {
            $detail = 'Candidate revoked recruiter-discovery consent.';
        } elseif ($last === 'consented' || $last === 'expired') {
            $detail = 'Recruiter-discovery consent has expired.';
        } else {
            $detail = 'Consent status: ' . rc_label($last) . '.';
        }

        $followups[] = $base + [
            'type'   => 'consent_lost',
            'detail' => $detail,
            'ref'    => null,
        ];
    }

    // 2. Consent expiring soon
    if (
        $hasConsents
        && $validUntil !== null
        && strpos((string)$validUntil, '9999') !== 0
    ) {

        $expTs = strtotime((string)$validUntil);

        if ($expTs && $expTs <= $now + ($FU_EXPIRY_DAYS * 86400)) {

            $daysLeft = max(0, (int)ceil(($expTs - $now) / 86400));

            $followups[] = $base + [
                'type'   => 'consent_expiring',
                'detail' => 'Consent expires in ' . $daysLeft
                    . ($daysLeft === 1 ? ' day.' : ' days.'),
                'ref'    => (string)$validUntil,
            ];
        }
    }

    // 3. Shortlisted but profile never reviewed since being added
    if ($hasActivity && $consentOk) {

        $addedTs = strtotime((string)$row['added_at']);

        if ($addedTs && $addedTs <= $now - ($FU_STALE_DAYS * 86400)) {

            $viewedTs = $row['last_viewed_at']
                ? strtotime((string)$row['last_viewed_at'])
                : 0;

            if (!$viewedTs || $viewedTs < $addedTs) {

                $waiting = (int)floor(($now - $addedTs) / 86400);

                $followups[] = $base + [
                    'type'   => 'not_reviewed',
                    'detail' => 'Shortlisted ' . $waiting
                        . ' days ago; profile not viewed since.',
                    'ref'    => (string)$row['added_at'],
                ];
            }
        }
    }

    // 4. Candidate becoming available soon
    if (!empty($row['availability_date'])) {

        $availTs = strtotime((string)$row['availability_date']);

        $availSlug = strtolower(trim((string)($row['availability_slug'] ?? '')));

        if (
            $availTs
            && !in_array($availSlug, ['do_not_contact', 'unavailable'], true)
            && $availTs >= $todayTs
            && $availTs <= $todayTs + ($FU_AVAILABLE_DAYS * 86400)
        ) {

            $followups[] = $base + [
                'type'   => 'available_soon',
                'detail' => $availTs === $todayTs
                    ? 'Available from today.'
                    : 'Available from '
                        . rc_date((string)$row['availability_date']) . '.',
                'ref'    => (string)$row['availability_date'],
            ];
        }
    }

    // 5. Skills awaiting verification
    if ($hasSkills && (int)($row['pending_skills'] ?? 0) > 0) {

        $pendingN = (int)$row['pending_skills'];

        $followups[] = $base + [
            'type'   => 'verification_pending',
            'detail' => $pendingN . ($pendingN === 1 ? ' skill' : ' skills')
                . ' awaiting verification.',
            'ref'    => null,
        ];
    }

    // 5b. Qualifications awaiting verification
    if ($hasQuals && (int)($row['pending_quals'] ?? 0) > 0) {

        $pendingQ = (int)$row['pending_quals'];

        $followups[] = $base + [
            'type'   => 'qualification_pending',
            'detail' => $pendingQ
                . ($pendingQ === 1 ? ' qualification' : ' qualifications')
                . ' awaiting verification.',
            'ref'    => null,
        ];
    }

    // 6. Incomplete profile
    if ($hasProfiles) {

        $completion = (int)($row['completion_percent'] ?? 0);

        if ($completion < $FU_PROFILE_MIN) {

            $followups[] = $base + [
                'type'   => 'profile_incomplete',
                'detail' => 'Profile is ' . $completion . '% complete.',
                'ref'    => null,
            ];
        }
    }
}

// 7. Empty shortlists (only meaningful when no candidate-level filter is active)
if ($hasShortlists && $province === '' && $skillId === 0) {

    $closedList = "'" . implode("','", $closedStatuses) . "'";

    $sqlEmpty = "SELECT rs.id, rs.name, rs.created_at
                 FROM recruiter_shortlists rs
                 WHERE rs.recruiter_id = ?
                   AND rs.created_at >= ?
                   AND rs.created_at < ?
                   AND rs.created_at < DATE_SUB(NOW(), INTERVAL {$FU_EMPTY_DAYS} DAY)
                   AND LOWER(COALESCE(rs.status, '')) NOT IN ({$closedList})";
    $typesEmpty  = 'iss';
    $paramsEmpty = [$recruiterId, $fromStart, $toEnd];

    if ($shortlistId > 0) {
        $sqlEmpty    .= ' AND rs.id = ?';
        $typesEmpty  .= 'i';
        $paramsEmpty[] = $shortlistId;
    }

    if ($hasShortlistCandidates) {
        $sqlEmpty .= " AND NOT EXISTS (
            SELECT 1
            FROM recruiter_shortlist_candidates x
            WHERE x.shortlist_id = rs.id
        )";
    }

    $sqlEmpty .= ' ORDER BY rs.created_at ASC, rs.id ASC';

    $emptyRows = rc_an_fetch(
        $conn,
        $sqlEmpty,
        $typesEmpty,
        $paramsEmpty,
        'Empty shortlists'
    );

    foreach ($emptyRows as $row) {

        $followups[] = [
            'candidate_id' => 0,
            'candidate'    => '',
            'title'        => '',
            'shortlist_id' => (int)$row['id'],
            'shortlist'    => (string)$row['name'],
            'linkable'     => false,
            'type'         => 'empty_shortlist',
            'detail'       => 'No candidates added since the shortlist was created.',
            'ref'          => (string)$row['created_at'],
        ];
    }
}

foreach ($followups as $index => $item) {
    $followups[$index]['priority'] = $fuTypes[$item['type']]['priority'];
}

usort(
    $followups,
    function ($a, $b) {

        $left  = [$a['priority'], strtolower($a['candidate']), $a['type']];
        $right = [$b['priority'], strtolower($b['candidate']), $b['type']];

        return $left <=> $right;
    }
);

$fuByType     = [];
$fuByPriority = [1 => 0, 2 => 0, 3 => 0];
$fuCandidates = [];

foreach ($followups as $item) {

    $fuByType[$item['type']] = ($fuByType[$item['type']] ?? 0) + 1;
    $fuByPriority[$item['priority']]++;

    if ($item['candidate_id'] > 0) {
        $fuCandidates[$item['candidate_id']] = true;
    }
}

$fuTotal = count($followups);

/*
|--------------------------------------------------------------------------
| CSV datasets
|--------------------------------------------------------------------------
*/

$filterSummary = [
    'Date range' => $from->format('Y-m-d') . ' to ' . $to->format('Y-m-d'),
    'Province'   => $province !== '' ? $province : 'All provinces',
    'Skill'      => $skillName !== '' ? $skillName : 'All skills',
    'Shortlist'  => $shortlistName !== '' ? $shortlistName : 'All shortlists',
];

$datasets = [];

// Candidates
$candidateRowsCsv = [];

foreach ($pool as $row) {

    $consentText = 'Unknown';

    if ($hasConsents) {
        $consentText = $row['consent_valid_until'] !== null ? 'Yes' : 'No';
    }

    $candidateRowsCsv[] = [
        trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
        (string)($row['profile_title'] ?: $row['professional_title'] ?: ''),
        rc_an_province_label((string)($row['province'] ?? '')),
        (string)($row['city'] ?? ''),
        rc_an_label((string)($row['qualification_level'] ?? '')),
        rc_an_label((string)($row['employment_status'] ?? '')),
        (int)($row['completion_percent'] ?? 0),
        $consentText,
        (int)($row['total_skills'] ?? 0),
        (int)($row['verified_skills'] ?? 0),
        (int)($row['pending_skills'] ?? 0),
        (int)($row['total_quals'] ?? 0),
        (int)($row['verified_quals'] ?? 0),
        (string)$row['shortlist_name'],
        (string)($row['client_name'] ?? ''),
        (string)$row['added_at'],
    ];
}

$datasets['candidates'] = [
    'title'   => 'Shortlisted candidates',
    'headers' => [
        'Candidate', 'Title', 'Province', 'City', 'Qualification',
        'Employment status', 'Profile completion %', 'Recruiter consent valid',
        'Total skills', 'Verified skills', 'Pending skills',
        'Qualification records', 'Verified qualifications',
        'Shortlist', 'Client', 'Added at',
    ],
    'rows'    => $candidateRowsCsv,
];

// Skills
$skillRowsCsv = [];

foreach ($skillStats as $item) {

    $other = max(0, $item['candidates'] - $item['verified'] - $item['pending']);

    $skillRowsCsv[] = [
        $item['name'],
        $item['category'],
        $item['candidates'],
        $uniqueCount > 0
            ? round(($item['candidates'] / $uniqueCount) * 100, 1)
            : 0,
        $item['verified'],
        $item['pending'],
        $other,
    ];
}

$datasets['skills'] = [
    'title'   => 'Candidate skills',
    'headers' => [
        'Skill', 'Category', 'Candidates', 'Coverage %',
        'Verified', 'Pending', 'Other',
    ],
    'rows'    => $skillRowsCsv,
];

// Qualifications / demographics
$qualRowsCsv = [];

foreach ($qualDist as $item) {
    $qualRowsCsv[] = ['Qualification level', $item['label'], $item['count'], $item['pct']];
}

foreach ($empDist as $item) {
    $qualRowsCsv[] = ['Employment status', $item['label'], $item['count'], $item['pct']];
}

foreach ($provDist as $item) {
    $qualRowsCsv[] = ['Province', $item['label'], $item['count'], $item['pct']];
}

foreach ($qualRecords as $item) {
    $qualRowsCsv[] = [
        'Qualification records (profile)',
        $item['label'],
        $item['candidates'],
        $uniqueCount > 0
            ? round(($item['candidates'] / $uniqueCount) * 100, 1)
            : 0,
    ];
}

$datasets['qualifications'] = [
    'title'   => 'Qualifications and demographics (unique candidates)',
    'headers' => ['Dimension', 'Value', 'Candidates', 'Share %'],
    'rows'    => $qualRowsCsv,
];

// Activity / productivity
$activityRowsCsv = [
    ['Summary', 'Shortlists created', $shortlistsCreated],
    ['Summary', 'Candidates shortlisted (filtered)', $entryCount],
    ['Summary', 'Unique candidates (filtered)', $uniqueCount],
    ['Summary', 'Average candidates per shortlist', $avgPerShortlist],
    ['Summary', 'Logged actions (date range)', $activityTotal],
    ['Summary', 'Active days', $activeDays],
    ['Summary', 'Average actions per active day', $avgActionsPerDay],
];

foreach ($actionRows as $row) {

    $action = (string)$row['action'];

    $activityRowsCsv[] = [
        'Action',
        $actionLabels[$action] ?? rc_label($action),
        (int)$row['total'],
    ];
}

foreach ($trend as $point) {
    $activityRowsCsv[] = [
        $trendMonthly ? 'Monthly activity' : 'Daily activity',
        $point['key'],
        $point['count'],
    ];
}

$datasets['activity'] = [
    'title'   => 'Recruiter productivity',
    'headers' => ['Section', 'Metric', 'Value'],
    'rows'    => $activityRowsCsv,
];

// Shortlist output
$shortlistRowsCsv = [];

foreach ($shortlistOutput as $item) {
    $shortlistRowsCsv[] = [
        $item['name'],
        $item['client'],
        rc_label($item['status']),
        $item['candidates'],
        $item['verified'],
        $item['avg_completion'],
        $item['last_added'],
    ];
}

$datasets['shortlists'] = [
    'title'   => 'Shortlist output',
    'headers' => [
        'Shortlist', 'Client', 'Status', 'Candidates',
        'With verified skills', 'Avg profile completion %', 'Last candidate added',
    ],
    'rows'    => $shortlistRowsCsv,
];

// Follow-ups
$followupRowsCsv = [];

foreach ($followups as $item) {
    $followupRowsCsv[] = [
        $priorityLabels[$item['priority']],
        $fuTypes[$item['type']]['label'],
        $item['candidate'],
        $item['shortlist'],
        $item['detail'],
        $item['ref'] ? rc_date((string)$item['ref'], '') : '',
    ];
}

$datasets['followups'] = [
    'title'   => 'Outstanding follow-ups',
    'headers' => ['Priority', 'Follow-up', 'Candidate', 'Shortlist', 'Detail', 'Key date'],
    'rows'    => $followupRowsCsv,
];

/*
|--------------------------------------------------------------------------
| Stream CSV and stop
|--------------------------------------------------------------------------
*/

if ($isExport) {

    rc_activity(
        $conn,
        $recruiterId,
        'analytics_exported',
        'Exported analytics ' . strtoupper($exportFormat) . ': ' . $exportKey,
        'report',
        null
    );

    $suffix = $from->format('Y-m-d') . '_to_' . $to->format('Y-m-d');
    $ext    = $exportFormat === 'pdf' ? 'pdf' : 'csv';

    $metaRows = [];

    foreach ($filterSummary as $label => $value) {
        $metaRows[] = [$label, $value];
    }

    $generatedAt = date('Y-m-d H:i:s');

    $metaRows[] = ['Generated', $generatedAt];

    if ($exportKey === 'all') {

        // Full report: candidates, candidate skills, qualifications
        // and outstanding follow-up activities.
        $sections = [
            $datasets['candidates'],
            $datasets['skills'],
            $datasets['qualifications'],
            $datasets['followups'],
        ];

        if ($exportFormat === 'pdf') {

            rc_an_stream_pdf(
                'recruiter-analytics-all_' . $suffix . '.pdf',
                $sections,
                $filterSummary + ['Generated' => $generatedAt]
            );
        }

        array_unshift($sections, [
            'title'   => 'Recruiter Analytics & Reporting - applied filters',
            'headers' => ['Filter', 'Value'],
            'rows'    => $metaRows,
        ]);

        rc_an_stream_csv(
            'recruiter-analytics-all_' . $suffix . '.csv',
            $sections,
            true
        );
    }

    if ($exportFormat === 'pdf') {

        rc_an_stream_pdf(
            'recruiter-analytics-' . $exportKey . '_' . $suffix . '.pdf',
            [$datasets[$exportKey]],
            $filterSummary + ['Generated' => $generatedAt]
        );
    }

    rc_an_stream_csv(
        'recruiter-analytics-' . $exportKey . '_' . $suffix . '.csv',
        [$datasets[$exportKey]],
        false
    );
}

/*
|--------------------------------------------------------------------------
| Audit
|--------------------------------------------------------------------------
*/

rc_activity(
    $conn,
    $recruiterId,
    'analytics_viewed',
    'Viewed recruiter analytics and reporting.',
    'report',
    null
);

/*
|--------------------------------------------------------------------------
| View helpers
|--------------------------------------------------------------------------
*/

$selected = function ($a, $b): string {
    return (string)$a === (string)$b ? 'selected' : '';
};

$presetLinks = [
    'Last 7 days'   => 6,
    'Last 30 days'  => 29,
    'Last 90 days'  => 89,
    'Last 12 months' => 364,
];

$hasActiveFilters = $province !== '' || $skillId > 0 || $shortlistId > 0;

$skillDisplayLimit = 15;

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>

<style>

.rc-an-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.rc-an-presets {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 12px 0 0;
}

.rc-an-presets a {
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    text-decoration: none;
    color: inherit;
    border: 1px solid rgba(127, 127, 127, .25);
}

.rc-an-presets a:hover {
    background: rgba(127, 127, 127, .10);
}

.rc-an-scope {
    margin: 14px 0 22px;
    font-size: 13px;
    opacity: .75;
}

.rc-an-scope b {
    display: inline-block;
    margin: 0 4px 4px 0;
    padding: 3px 10px;
    border-radius: 999px;
    font-weight: 600;
    background: rgba(127, 127, 127, .12);
}

.rc-an-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.rc-an-stat {
    padding: 20px;
    border-radius: 18px;
    background: rgba(127, 127, 127, .08);
    color: inherit;
    border: 1px solid rgba(127, 127, 127, .18);
}

.rc-an-stat i {
    display: block;
    margin-bottom: 12px;
    font-size: 20px;
}

.rc-an-stat strong {
    display: block;
    font-size: 26px;
    line-height: 1;
}

.rc-an-stat span {
    display: block;
    margin-top: 8px;
    font-size: 13px;
    opacity: .7;
}

.rc-an-stat small {
    display: block;
    margin-top: 5px;
    font-size: 11px;
    opacity: .55;
}

.rc-an-section-note {
    margin: 5px 0 0;
    font-size: 13px;
    opacity: .7;
}

.rc-an-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.rc-an-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}

.rc-an-grid--3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.rc-an-bar-row {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 2fr) auto;
    align-items: center;
    gap: 12px;
    padding: 9px 0;
    font-size: 13px;
}

.rc-an-bar-row + .rc-an-bar-row {
    border-top: 1px solid rgba(127, 127, 127, .10);
}

.rc-an-bar-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rc-an-track {
    height: 8px;
    overflow: hidden;
    border-radius: 99px;
    background: rgba(127, 127, 127, .15);
}

.rc-an-fill {
    display: block;
    height: 100%;
    border-radius: 99px;
    background: var(--rc-primary, #1a56db);
}

.rc-an-bar-value {
    min-width: 70px;
    text-align: right;
    font-weight: 600;
}

.rc-an-bar-value small {
    font-weight: 400;
    opacity: .6;
}

.rc-an-trend {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    height: 150px;
    padding-top: 6px;
}

.rc-an-trend__col {
    flex: 1 1 0;
    min-width: 2px;
    height: 100%;
    display: flex;
    align-items: flex-end;
}

.rc-an-trend__col span {
    display: block;
    width: 100%;
    min-height: 2px;
    border-radius: 3px 3px 0 0;
    background: var(--rc-primary, #1a56db);
}

.rc-an-trend__col span.is-zero {
    opacity: .2;
}

.rc-an-trend__axis {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
    font-size: 11px;
    opacity: .6;
}

.rc-an-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.rc-an-pill--1 {
    color: #dc2626;
    background: rgba(220, 38, 38, .12);
}

.rc-an-pill--2 {
    color: #d97706;
    background: rgba(217, 119, 6, .14);
}

.rc-an-pill--3 {
    color: #2563eb;
    background: rgba(37, 99, 235, .12);
}

.rc-an-fu-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
}

.rc-an-fu-chip {
    padding: 8px 14px;
    border-radius: 12px;
    font-size: 12px;
    background: rgba(127, 127, 127, .10);
}

.rc-an-fu-chip strong {
    margin-right: 5px;
    font-size: 15px;
}

.rc-an-cell-sub {
    display: block;
    margin-top: 2px;
    font-size: 12px;
    opacity: .65;
}

.rc-an-muted {
    opacity: .6;
}

.rc-an-empty {
    padding: 28px 12px;
    text-align: center;
    font-size: 13px;
    opacity: .65;
}

.rc-an-empty i {
    display: block;
    margin-bottom: 10px;
    font-size: 26px;
    opacity: .6;
}

.rc-an-note {
    margin-top: 14px;
    font-size: 12px;
    opacity: .65;
}

@media (max-width: 1100px) {

    .rc-an-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .rc-an-grid--3 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {

    .rc-an-grid,
    .rc-an-grid--3 {
        grid-template-columns: 1fr;
    }

    .rc-an-bar-row {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .rc-an-bar-row .rc-an-track {
        grid-column: 1 / -1;
        order: 3;
    }
}

@media (max-width: 480px) {

    .rc-an-stats {
        grid-template-columns: 1fr;
    }
}


/* Dark-mode safety: never paint solid white behind page-local blocks */
.rc-an-fu-chip,
.rc-an-scope b {
    color: inherit;
}

.rc-an-presets a:hover {
    background: rgba(127, 127, 127, .18);
}

.rc-an-bar-row,
.rc-an-bar-label,
.rc-an-bar-value,
.rc-an-cell-sub {
    color: inherit;
}

</style>


<!-- ================================================================
     HEADER
================================================================ -->

<div class="rc-page-header">

    <div class="rc-an-head">

        <div>

            <span class="rc-eyebrow">
                <i class="fas fa-chart-line"></i>
                Recruitment Insights
            </span>

            <h2>
                Analytics &amp; Reporting
            </h2>

            <p>
                Candidate skills, qualifications, your recruiter
                productivity and the follow-ups still waiting on you.
            </p>

        </div>

        <?php if ($hasWorkflow): ?>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">

                <a
                    class="rc-btn rc-btn--primary"
                    href="<?= e(rc_an_link($filters, ['export' => 'all'])) ?>"
                >
                    <i class="fas fa-file-csv"></i>
                    Export Report (CSV)
                </a>

                <a
                    class="rc-btn rc-btn--secondary"
                    href="<?= e(rc_an_link($filters, ['export' => 'all', 'format' => 'pdf'])) ?>"
                >
                    <i class="fas fa-file-pdf"></i>
                    Export Report (PDF)
                </a>

            </div>

        <?php endif; ?>

    </div>

</div>


<?php if (!$hasWorkflow): ?>

    <div class="rc-card">

        <div class="rc-empty">

            <i class="fas fa-chart-line"></i>

            <strong>
                Analytics unavailable
            </strong>

            <span>
                The recruiter shortlist tables are not available, so
                there is no recruitment data to report on yet.
            </span>

        </div>

    </div>

<?php else: ?>


<!-- ================================================================
     FILTERS
================================================================ -->

<form
    class="rc-search"
    method="get"
    action="<?= e(url('recruiter/analytics.php')) ?>"
>

    <div class="rc-field">

        <label for="rcAnFrom">
            From
        </label>

        <input
            type="date"
            id="rcAnFrom"
            name="date_from"
            value="<?= e($from->format('Y-m-d')) ?>"
            max="<?= e($today->format('Y-m-d')) ?>"
        >

    </div>


    <div class="rc-field">

        <label for="rcAnTo">
            To
        </label>

        <input
            type="date"
            id="rcAnTo"
            name="date_to"
            value="<?= e($to->format('Y-m-d')) ?>"
            max="<?= e($today->format('Y-m-d')) ?>"
        >

    </div>


    <div class="rc-field">

        <label for="rcAnProvince">
            Province
        </label>

        <select id="rcAnProvince" name="province">

            <option value="">
                All provinces
            </option>

            <?php foreach ($provinces as $value): ?>

                <option
                    value="<?= e($value) ?>"
                    <?= $selected($province, $value) ?>
                >
                    <?= e($value) ?>
                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <?php if ($hasSkills): ?>

        <div class="rc-field">

            <label for="rcAnSkill">
                Skill
            </label>

            <select id="rcAnSkill" name="skill">

                <option value="">
                    All skills
                </option>

                <?php foreach ($skillOptions as $option): ?>

                    <option
                        value="<?= (int)$option['id'] ?>"
                        <?= $selected($skillId, (int)$option['id']) ?>
                    >
                        <?= e((string)$option['name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

    <?php endif; ?>


    <div class="rc-field">

        <label for="rcAnShortlist">
            Shortlist
        </label>

        <select id="rcAnShortlist" name="shortlist">

            <option value="">
                All shortlists
            </option>

            <?php foreach ($shortlistOptions as $option): ?>

                <option
                    value="<?= (int)$option['id'] ?>"
                    <?= $selected($shortlistId, (int)$option['id']) ?>
                >
                    <?= e((string)$option['name']) ?>
                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <button
        type="submit"
        class="rc-btn rc-btn--primary"
    >
        <i class="fas fa-filter"></i>
        Apply
    </button>

    <a
        class="rc-btn rc-btn--secondary"
        href="<?= e(url('recruiter/analytics.php')) ?>"
    >
        <i class="fas fa-rotate-left"></i>
        Reset
    </a>

</form>


<div class="rc-an-presets">

    <?php foreach ($presetLinks as $label => $offset): ?>

        <a
            href="<?= e(rc_an_link($filters, [
                'date_from' => $today->modify('-' . $offset . ' days')->format('Y-m-d'),
                'date_to'   => $today->format('Y-m-d'),
            ])) ?>"
        >
            <?= e($label) ?>
        </a>

    <?php endforeach; ?>

</div>


<div class="rc-an-scope">

    <b>
        <?= e($from->format('d M Y')) ?>
        –
        <?= e($to->format('d M Y')) ?>
    </b>

    <b><?= e($filterSummary['Province']) ?></b>

    <b><?= e($filterSummary['Skill']) ?></b>

    <b><?= e($filterSummary['Shortlist']) ?></b>

    <br>

    Date range applies to when candidates were added to a shortlist
    and to your logged activity. Province, skill and shortlist apply to
    candidate, skill, qualification and follow-up data. Activity-log
    metrics use the date range only.

</div>


<!-- ================================================================
     SUMMARY
================================================================ -->

<section class="rc-an-stats">

    <article class="rc-an-stat">
        <i class="fas fa-users"></i>
        <strong><?= number_format($uniqueCount) ?></strong>
        <span>Unique candidates</span>
        <small><?= number_format($entryCount) ?> shortlist entries</small>
    </article>

    <article class="rc-an-stat">
        <i class="fas fa-certificate"></i>
        <strong><?= number_format($verifiedCnt) ?></strong>
        <span>With verified skills</span>
        <small>
            <?= $uniqueCount > 0
                ? number_format(round(($verifiedCnt / $uniqueCount) * 100))
                : 0 ?>% of candidates
        </small>
    </article>

    <article class="rc-an-stat">
        <i class="fas fa-user-check"></i>
        <strong><?= number_format($avgCompletion) ?>%</strong>
        <span>Avg profile completion</span>
        <small>
            <?= $hasConsents
                ? number_format($validConsentCnt) . ' with valid consent'
                : 'Consent data unavailable' ?>
        </small>
    </article>

    <article class="rc-an-stat">
        <i class="fas fa-bell"></i>
        <strong><?= number_format($fuTotal) ?></strong>
        <span>Outstanding follow-ups</span>
        <small>
            <?= number_format($fuByPriority[1]) ?> high priority
        </small>
    </article>

</section>


<!-- ================================================================
     CANDIDATE SKILLS
================================================================ -->

<div class="rc-card rc-space">

    <div class="rc-card__header rc-an-card-head">

        <div>

            <h3>Candidate Skills</h3>

            <p class="rc-an-section-note">
                Skills held by the filtered candidates, with verification status.
                <?php if ($hasSkills && $noSkillsCnt > 0): ?>
                    <?= number_format($noSkillsCnt) ?>
                    <?= $noSkillsCnt === 1 ? 'candidate has' : 'candidates have' ?>
                    no skills recorded.
                <?php endif; ?>
            </p>

        </div>

        <div>


        </div>

    </div>

    <div class="rc-card__body">

        <?php if (!$hasSkills): ?>

            <div class="rc-an-empty">
                <i class="fas fa-certificate"></i>
                Skill tables are not available.
            </div>

        <?php elseif (!$skillStats): ?>

            <div class="rc-an-empty">
                <i class="fas fa-certificate"></i>
                No skills found for the candidates in this selection.
            </div>

        <?php else: ?>

            <div class="rc-table-wrap">

                <table class="rc-table">

                    <thead>
                        <tr>
                            <th>Skill</th>
                            <th>Category</th>
                            <th>Candidates</th>
                            <th>Coverage</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Other</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach (
                            array_slice($skillStats, 0, $skillDisplayLimit)
                            as $item
                        ): ?>

                            <?php

                            $coverage = $uniqueCount > 0
                                ? ($item['candidates'] / $uniqueCount) * 100
                                : 0;

                            $other = max(
                                0,
                                $item['candidates']
                                - $item['verified']
                                - $item['pending']
                            );

                            ?>

                            <tr>

                                <td>
                                    <strong><?= e($item['name']) ?></strong>
                                </td>

                                <td>
                                    <?= e($item['category'] !== '' ? $item['category'] : '—') ?>
                                </td>

                                <td>
                                    <strong><?= number_format($item['candidates']) ?></strong>
                                </td>

                                <td style="min-width:140px;">
                                    <div class="rc-an-track">
                                        <span
                                            class="rc-an-fill"
                                            style="width:<?= (int)round($coverage) ?>%"
                                        ></span>
                                    </div>
                                    <span class="rc-an-cell-sub">
                                        <?= number_format($coverage, 1) ?>%
                                    </span>
                                </td>

                                <td><?= number_format($item['verified']) ?></td>

                                <td><?= number_format($item['pending']) ?></td>

                                <td><?= number_format($other) ?></td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <?php if (count($skillStats) > $skillDisplayLimit): ?>

                <p class="rc-an-note">
                    Showing the top <?= (int)$skillDisplayLimit ?> of
                    <?= number_format(count($skillStats)) ?> skills.
                    Export the CSV or PDF for the full list.
                </p>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>


<!-- ================================================================
     QUALIFICATIONS
================================================================ -->

<div class="rc-card rc-space">

    <div class="rc-card__header rc-an-card-head">

        <div>

            <h3>Qualifications &amp; Profile</h3>

            <p class="rc-an-section-note">
                Unique candidates by qualification level, employment status
                and province.
            </p>

        </div>

        <div>


        </div>

    </div>

    <div class="rc-card__body">

        <?php if ($uniqueCount === 0): ?>

            <div class="rc-an-empty">
                <i class="fas fa-graduation-cap"></i>
                No candidates match the selected filters.
            </div>

        <?php else: ?>

            <div class="rc-an-grid rc-an-grid--3">

                <?php

                $distBlocks = [
                    'Qualification level' => $qualDist,
                    'Employment status'   => $empDist,
                    'Province'            => $provDist,
                ];

                ?>

                <?php foreach ($distBlocks as $blockTitle => $dist): ?>

                    <div>

                        <strong><?= e($blockTitle) ?></strong>

                        <?php foreach ($dist as $item): ?>

                            <div class="rc-an-bar-row">

                                <span
                                    class="rc-an-bar-label"
                                    title="<?= e($item['label']) ?>"
                                >
                                    <?= e($item['label']) ?>
                                </span>

                                <div class="rc-an-track">
                                    <span
                                        class="rc-an-fill"
                                        style="width:<?= (int)round($item['pct']) ?>%"
                                    ></span>
                                </div>

                                <span class="rc-an-bar-value">
                                    <?= number_format($item['count']) ?>
                                    <small>(<?= number_format($item['pct'], 1) ?>%)</small>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endforeach; ?>

            </div>

            <?php if ($hasQuals): ?>

                <div style="margin-top:26px;">

                    <strong>Qualification records on candidate profiles</strong>

                    <p class="rc-an-section-note">
                        <?= number_format($verifiedQualCnt) ?>
                        <?= $verifiedQualCnt === 1 ? 'candidate has' : 'candidates have' ?>
                        at least one verified qualification;
                        <?= number_format($noQualCnt) ?>
                        <?= $noQualCnt === 1 ? 'has' : 'have' ?>
                        none recorded.
                    </p>

                    <?php if (!$qualRecords): ?>

                        <div class="rc-an-empty">
                            No qualification records for these candidates.
                        </div>

                    <?php else: ?>

                        <div class="rc-table-wrap">

                            <table class="rc-table">

                                <thead>
                                    <tr>
                                        <th>Level</th>
                                        <th>Candidates</th>
                                        <th>Records</th>
                                        <th>Verified</th>
                                        <th>Pending</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($qualRecords as $item): ?>

                                        <tr>
                                            <td><strong><?= e($item['label']) ?></strong></td>
                                            <td><?= number_format($item['candidates']) ?></td>
                                            <td><?= number_format($item['records']) ?></td>
                                            <td><?= number_format($item['verified']) ?></td>
                                            <td><?= number_format($item['pending']) ?></td>
                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>


<!-- ================================================================
     RECRUITER PRODUCTIVITY
================================================================ -->

<div class="rc-card rc-space">

    <div class="rc-card__header rc-an-card-head">

        <div>

            <h3>Recruiter Productivity</h3>

            <p class="rc-an-section-note">
                Your shortlisting output and logged activity for the selected period.
            </p>

        </div>

        <div>


        </div>

    </div>

    <div class="rc-card__body">

        <section class="rc-an-stats">

            <article class="rc-an-stat">
                <i class="fas fa-list-check"></i>
                <strong><?= number_format($shortlistsCreated) ?></strong>
                <span>Shortlists created</span>
            </article>

            <article class="rc-an-stat">
                <i class="fas fa-user-plus"></i>
                <strong><?= number_format($entryCount) ?></strong>
                <span>Candidates shortlisted</span>
                <small><?= number_format($avgPerShortlist, 1) ?> avg per shortlist</small>
            </article>

            <article class="rc-an-stat">
                <i class="fas fa-magnifying-glass"></i>
                <strong>
                    <?= number_format($actionCounts['candidate_search'] ?? 0) ?>
                </strong>
                <span>Candidate searches</span>
                <small>
                    <?= number_format($actionCounts['candidate_profile_viewed'] ?? 0) ?>
                    profiles viewed
                </small>
            </article>

            <article class="rc-an-stat">
                <i class="fas fa-bolt"></i>
                <strong><?= number_format($activeDays) ?></strong>
                <span>Active days</span>
                <small><?= number_format($avgActionsPerDay, 1) ?> actions per active day</small>
            </article>

        </section>


        <?php if (!$hasActivity): ?>

            <div class="rc-an-empty">
                <i class="fas fa-clock-rotate-left"></i>
                The activity log is not available, so activity trends cannot be shown.
            </div>

        <?php else: ?>

            <div class="rc-an-grid">

                <div>

                    <strong>
                        <?= $trendMonthly ? 'Monthly' : 'Daily' ?> activity
                    </strong>

                    <?php if ($trendMax === 0): ?>

                        <div class="rc-an-empty">
                            No logged activity in this period.
                        </div>

                    <?php else: ?>

                        <div class="rc-an-trend">

                            <?php foreach ($trend as $point): ?>

                                <?php

                                $height = (int)round(($point['count'] / $trendMax) * 100);

                                ?>

                                <div
                                    class="rc-an-trend__col"
                                    title="<?= e($point['label'] . ': ' . $point['count']) ?>"
                                >
                                    <span
                                        class="<?= $point['count'] === 0 ? 'is-zero' : '' ?>"
                                        style="<?= $point['count'] === 0
                                            ? ''
                                            : 'height:' . max(3, $height) . '%' ?>"
                                    ></span>
                                </div>

                            <?php endforeach; ?>

                        </div>

                        <div class="rc-an-trend__axis">
                            <span><?= e($trend[0]['label']) ?></span>
                            <span><?= e($trend[count($trend) - 1]['label']) ?></span>
                        </div>

                    <?php endif; ?>

                </div>


                <div>

                    <strong>Activity breakdown</strong>

                    <?php if (!$actionRows): ?>

                        <div class="rc-an-empty">
                            No logged activity in this period.
                        </div>

                    <?php else: ?>

                        <?php foreach ($actionRows as $row): ?>

                            <?php

                            $action = (string)$row['action'];

                            $share = $activityTotal > 0
                                ? ((int)$row['total'] / $activityTotal) * 100
                                : 0;

                            ?>

                            <div class="rc-an-bar-row">

                                <span class="rc-an-bar-label">
                                    <?= e($actionLabels[$action] ?? rc_label($action)) ?>
                                </span>

                                <div class="rc-an-track">
                                    <span
                                        class="rc-an-fill"
                                        style="width:<?= (int)round($share) ?>%"
                                    ></span>
                                </div>

                                <span class="rc-an-bar-value">
                                    <?= number_format((int)$row['total']) ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>


        <div class="rc-an-card-head" style="margin-top:26px;">

            <div>
                <strong>Shortlist output</strong>
            </div>

            <div>


            </div>

        </div>

        <?php if (!$shortlistOutput): ?>

            <div class="rc-an-empty">
                <i class="fas fa-list-check"></i>
                No shortlist activity matches the selected filters.
            </div>

        <?php else: ?>

            <div class="rc-table-wrap">

                <table class="rc-table">

                    <thead>
                        <tr>
                            <th>Shortlist</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Candidates</th>
                            <th>Verified skills</th>
                            <th>Avg profile</th>
                            <th>Last added</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($shortlistOutput as $item): ?>

                            <tr>

                                <td><strong><?= e($item['name']) ?></strong></td>

                                <td>
                                    <?= e($item['client'] !== '' ? $item['client'] : '—') ?>
                                </td>

                                <td>
                                    <span class="rc-status">
                                        <?= e(rc_label($item['status'])) ?>
                                    </span>
                                </td>

                                <td><strong><?= number_format($item['candidates']) ?></strong></td>

                                <td><?= number_format($item['verified']) ?></td>

                                <td><?= number_format($item['avg_completion']) ?>%</td>

                                <td><?= e(rc_date($item['last_added'])) ?></td>

                                <td>
                                    <a
                                        class="rc-btn rc-btn--secondary rc-btn--small"
                                        href="<?= e(url('recruiter/shortlist_view.php?id=' . (int)$item['id'])) ?>"
                                    >
                                        <i class="fas fa-eye"></i>
                                        View
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>


<!-- ================================================================
     OUTSTANDING FOLLOW-UPS
================================================================ -->

<div class="rc-card rc-space">

    <div class="rc-card__header rc-an-card-head">

        <div>

            <h3>Outstanding Follow-ups</h3>

            <p class="rc-an-section-note">
                Items needing your attention across
                <?= number_format(count($fuCandidates)) ?>
                <?= count($fuCandidates) === 1 ? 'candidate' : 'candidates' ?>,
                derived from your shortlists, consent, skill and profile records.
            </p>

        </div>

        <div>


        </div>

    </div>

    <div class="rc-card__body">

        <?php if (!$followups): ?>

            <div class="rc-an-empty">
                <i class="fas fa-circle-check"></i>
                Nothing outstanding for the selected filters.
            </div>

        <?php else: ?>

            <div class="rc-an-fu-summary">

                <?php foreach ($fuTypes as $typeKey => $meta): ?>

                    <?php if (!empty($fuByType[$typeKey])): ?>

                        <span class="rc-an-fu-chip">
                            <strong><?= number_format($fuByType[$typeKey]) ?></strong>
                            <?= e($meta['label']) ?>
                        </span>

                    <?php endif; ?>

                <?php endforeach; ?>

            </div>

            <div class="rc-table-wrap">

                <table class="rc-table">

                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Follow-up</th>
                            <th>Candidate</th>
                            <th>Shortlist</th>
                            <th>Detail</th>
                            <th>Key date</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach (
                            array_slice($followups, 0, $FU_DISPLAY_LIMIT)
                            as $item
                        ): ?>

                            <tr>

                                <td>
                                    <span class="rc-an-pill rc-an-pill--<?= (int)$item['priority'] ?>">
                                        <?= e($priorityLabels[$item['priority']]) ?>
                                    </span>
                                </td>

                                <td>
                                    <strong><?= e($fuTypes[$item['type']]['label']) ?></strong>
                                </td>

                                <td>

                                    <?php if ($item['candidate'] === ''): ?>

                                        <span class="rc-an-muted">—</span>

                                    <?php elseif ($item['linkable']): ?>

                                        <a href="<?= e(url('recruiter/candidate_view.php?id=' . (int)$item['candidate_id'])) ?>">
                                            <strong><?= e($item['candidate']) ?></strong>
                                        </a>

                                    <?php else: ?>

                                        <strong><?= e($item['candidate']) ?></strong>

                                    <?php endif; ?>

                                    <?php if ($item['title'] !== ''): ?>
                                        <span class="rc-an-cell-sub"><?= e($item['title']) ?></span>
                                    <?php endif; ?>

                                </td>

                                <td>
                                    <a href="<?= e(url('recruiter/shortlist_view.php?id=' . (int)$item['shortlist_id'])) ?>">
                                        <?= e($item['shortlist']) ?>
                                    </a>
                                </td>

                                <td><?= e($item['detail']) ?></td>

                                <td>
                                    <?= e($item['ref'] ? rc_date((string)$item['ref'], '—') : '—') ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <?php if ($fuTotal > $FU_DISPLAY_LIMIT): ?>

                <p class="rc-an-note">
                    Showing the first <?= (int)$FU_DISPLAY_LIMIT ?> of
                    <?= number_format($fuTotal) ?> follow-ups, highest priority first.
                    Export the CSV or PDF for the full list.
                </p>

            <?php endif; ?>

        <?php endif; ?>

        <p class="rc-an-note">
            Rules: consent no longer valid or expiring within
            <?= (int)$FU_EXPIRY_DAYS ?> days; shortlisted over
            <?= (int)$FU_STALE_DAYS ?> days ago with no profile view since;
            available within <?= (int)$FU_AVAILABLE_DAYS ?> days; skills pending
            verification; profile under <?= (int)$FU_PROFILE_MIN ?>% complete;
            empty shortlists older than <?= (int)$FU_EMPTY_DAYS ?> days.
            Shortlists with an archived, closed, completed, cancelled or
            inactive status are excluded.
        </p>

    </div>

</div>


<?php endif; ?>


<?php

require __DIR__ . '/_layout_end.php';

?>
