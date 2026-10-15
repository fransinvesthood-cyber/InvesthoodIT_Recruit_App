<?php
// ============================================================================
// Exportable Supervisor Reports - helpers.
// Include from a supervisor page with:
//     require_once __DIR__ . '/_report_export.php';
// Requires _helpers.php (sv_status_label, sv_completion_rate) to be loaded first.
// Every function is guarded. No third-party libraries are required:
//   CSV  - native PHP
//   XLSX - native PHP + ZipArchive (falls back to Excel XML .xls without it)
//   PDF  - built-in minimal PDF writer
// ============================================================================

if (!function_exists('sv_rx_formats')) {
    /** Supported formats: key => [label, extension, mime type]. */
    function sv_rx_formats(): array {
        return [
            'csv'  => ['CSV',   'csv',  'text/csv; charset=UTF-8'],
            'xlsx' => ['Excel', 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'pdf'  => ['PDF',   'pdf',  'application/pdf'],
        ];
    }
}

if (!function_exists('sv_rx_fetch')) {
    /**
     * Same query and scope rules as the Cohort Progress report in reports.php,
     * so the export always matches what the supervisor is viewing.
     */
    function sv_rx_fetch(int $supervisorId, int $programmeId, int $cohortId, string $status): array {
        $where  = ['c.supervisor_id = ?'];
        $types  = 'i';
        $params = [$supervisorId];
        if ($programmeId > 0) { $where[] = 'p.id = ?'; $types .= 'i'; $params[] = $programmeId; }
        if ($cohortId > 0)    { $where[] = 'c.id = ?'; $types .= 'i'; $params[] = $cohortId; }
        if ($status !== '')   { $where[] = 'cp.status = ?'; $types .= 's'; $params[] = $status; }

        $sql = "
            SELECT
                p.name AS programme_name,
                c.id AS cohort_id,
                c.name AS cohort_name,
                c.status AS cohort_status,
                c.start_date,
                c.end_date,
                COUNT(DISTINCT CASE WHEN cp.status <> 'withdrawn' THEN cp.user_id END) AS total_candidates,
                COUNT(DISTINCT CASE WHEN cp.status = 'active' THEN cp.user_id END) AS active_candidates,
                COUNT(DISTINCT CASE WHEN cp.status = 'completed' THEN cp.user_id END) AS completed_candidates,
                COUNT(DISTINCT CASE WHEN cp.status = 'withdrawn' THEN cp.user_id END) AS withdrawn_candidates
            FROM cohorts c
            INNER JOIN programmes p ON p.id = c.programme_id
            LEFT JOIN cohort_participants cp ON cp.cohort_id = c.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY p.name, c.id, c.name, c.status, c.start_date, c.end_date
            ORDER BY p.name, c.name
        ";
        $stmt = Database::prepare($sql, $types, $params);
        $rows = [];
        $res  = $stmt->get_result();
        while ($res && $row = $res->fetch_assoc()) {
            $row['rate'] = sv_completion_rate((int)$row['total_candidates'], (int)$row['completed_candidates']);
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('sv_rx_filter_labels')) {
    /** Human-readable names of the applied filters (scoped to this supervisor). */
    function sv_rx_filter_labels(int $supervisorId, int $programmeId, int $cohortId, string $status): array {
        $prog = 'All programmes';
        $coh  = 'All cohorts';
        if ($programmeId > 0) {
            $prog = 'Programme #' . $programmeId;
            $st = Database::prepare("SELECT DISTINCT p.name FROM programmes p INNER JOIN cohorts c ON c.programme_id=p.id WHERE p.id=? AND c.supervisor_id=? LIMIT 1", 'ii', [$programmeId, $supervisorId]);
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if ($r) $prog = (string)$r['name'];
        }
        if ($cohortId > 0) {
            $coh = 'Cohort #' . $cohortId;
            $st = Database::prepare("SELECT name FROM cohorts WHERE id=? AND supervisor_id=? LIMIT 1", 'ii', [$cohortId, $supervisorId]);
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if ($r) $coh = (string)$r['name'];
        }
        return [
            'programme' => $prog,
            'cohort'    => $coh,
            'status'    => $status !== '' ? sv_status_label($status) : 'All statuses',
        ];
    }
}

if (!function_exists('sv_rx_dataset')) {
    /**
     * Builds one neutral dataset used by every format writer.
     * Columns mirror the on-screen "Progress by Cohort" table; the totals row
     * mirrors the summary cards (sums, and overall completion rate).
     */
    function sv_rx_dataset(array $rows, array $labels, string $generatedBy): array {
        $total = $active = $completed = $withdrawn = 0;
        $body  = [];
        foreach ($rows as $r) {
            $total     += (int)$r['total_candidates'];
            $active    += (int)$r['active_candidates'];
            $completed += (int)$r['completed_candidates'];
            $withdrawn += (int)$r['withdrawn_candidates'];
            $body[] = [
                (string)$r['programme_name'],
                (string)$r['cohort_name'],
                sv_status_label((string)$r['cohort_status']),
                (int)$r['total_candidates'],
                (int)$r['active_candidates'],
                (int)$r['completed_candidates'],
                (int)$r['withdrawn_candidates'],
                (int)$r['rate'],
            ];
        }
        $overall = sv_completion_rate($total, $completed);
        return [
            'title'   => 'Cohort Progress Report',
            'sheet'   => 'Cohort Progress',
            'meta'    => [
                ['Generated',        date('d M Y, H:i')],
                ['Generated by',     $generatedBy],
                ['Programme',        $labels['programme']],
                ['Cohort',           $labels['cohort']],
                ['Candidate status', $labels['status']],
                ['Cohorts included', (string)count($rows)],
            ],
            'headers' => ['Programme', 'Cohort', 'Cohort Status', 'Current', 'Active', 'Completed', 'Withdrawn', 'Completion Rate'],
            'types'   => ['text', 'text', 'text', 'int', 'int', 'int', 'int', 'pct'],
            'rows'    => $body,
            'totals'  => ['Total', '', '', $total, $active, $completed, $withdrawn, $overall],
            'empty'   => 'No cohorts match the selected filters.',
            'totals_summary' => ['cohorts' => count($rows), 'candidates' => $total],
        ];
    }
}

/* ------------------------------------------------------------------ CSV -- */

if (!function_exists('sv_rx_csv_safe')) {
    /** Neutralises spreadsheet formula injection in text cells. */
    function sv_rx_csv_safe(string $v): string {
        return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
    }
}

if (!function_exists('sv_rx_csv')) {
    function sv_rx_csv(array $ds): string {
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly
        $put = static function (array $f) use ($h): void { fputcsv($h, $f, ',', '"', ''); };
        $fmt = static function (array $row, array $types): array {
            $o = [];
            foreach ($row as $i => $v) {
                $t = $types[$i] ?? 'text';
                if ($t === 'pct') $o[] = $v . '%';
                elseif ($t === 'int') $o[] = (int)$v;
                else $o[] = sv_rx_csv_safe((string)$v);
            }
            return $o;
        };
        $put([$ds['title']]);
        foreach ($ds['meta'] as $m) $put([$m[0], sv_rx_csv_safe((string)$m[1])]);
        $put([]);
        $put($ds['headers']);
        foreach ($ds['rows'] as $r) $put($fmt($r, $ds['types']));
        if (!$ds['rows']) $put([$ds['empty']]);
        $put($fmt($ds['totals'], $ds['types']));
        rewind($h);
        $out = stream_get_contents($h);
        fclose($h);
        return (string)$out;
    }
}

/* ----------------------------------------------------------------- XLSX -- */

if (!function_exists('sv_rx_xml')) {
    function sv_rx_xml(string $v): string {
        $v = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $v) ?? '';
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sv_rx_col')) {
    function sv_rx_col(int $i): string {
        $s = '';
        $i++;
        while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); }
        return $s;
    }
}

if (!function_exists('sv_rx_xlsx_available')) {
    function sv_rx_xlsx_available(): bool { return class_exists('ZipArchive'); }
}

if (!function_exists('sv_rx_xlsx')) {
    /** Real .xlsx (Office Open XML), built without libraries. Returns binary string or '' on failure. */
    function sv_rx_xlsx(array $ds): string {
        if (!sv_rx_xlsx_available()) return '';
        $r = 0;
        $sheet = '';
        $str = static function (int $c, int $row, string $v, int $s): string {
            return '<c r="' . sv_rx_col($c) . $row . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . sv_rx_xml($v) . '</t></is></c>';
        };
        $num = static function (int $c, int $row, $v, int $s): string {
            return '<c r="' . sv_rx_col($c) . $row . '" s="' . $s . '"><v>' . $v . '</v></c>';
        };
        // Title + metadata
        $r++; $sheet .= '<row r="' . $r . '">' . $str(0, $r, $ds['title'], 1) . '</row>';
        foreach ($ds['meta'] as $m) {
            $r++;
            $sheet .= '<row r="' . $r . '">' . $str(0, $r, (string)$m[0], 9) . $str(1, $r, (string)$m[1], 0) . '</row>';
        }
        $r++; // blank spacer
        // Header
        $r++;
        $headerRow = $r;
        $cells = '';
        foreach ($ds['headers'] as $i => $h) $cells .= $str($i, $r, $h, 2);
        $sheet .= '<row r="' . $r . '" ht="22" customHeight="1">' . $cells . '</row>';
        // Body
        $emit = static function (array $row, array $types, int $rowNo, bool $total) use ($str, $num): string {
            $cells = '';
            foreach ($row as $i => $v) {
                $t = $types[$i] ?? 'text';
                if ($t === 'int')      $cells .= $num($i, $rowNo, (int)$v, $total ? 7 : 4);
                elseif ($t === 'pct')  $cells .= $num($i, $rowNo, ((int)$v) / 100, $total ? 8 : 5);
                else                   $cells .= $str($i, $rowNo, (string)$v, $total ? 6 : 3);
            }
            return '<row r="' . $rowNo . '">' . $cells . '</row>';
        };
        foreach ($ds['rows'] as $row) { $r++; $sheet .= $emit($row, $ds['types'], $r, false); }
        if (!$ds['rows']) { $r++; $sheet .= '<row r="' . $r . '">' . $str(0, $r, $ds['empty'], 10) . '</row>'; }
        $r++; $sheet .= $emit($ds['totals'], $ds['types'], $r, true);

        $widths = [34, 30, 16, 12, 12, 14, 14, 18];
        $cols = '';
        foreach ($widths as $i => $w) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $cols . '</cols><sheetData>' . $sheet . '</sheetData>'
            . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            . '<pageSetup orientation="landscape"/></worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="5">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><i/><sz val="11"/><color rgb="FF667085"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD0D5DD"/></left><right style="thin"><color rgb="FFD0D5DD"/></right><top style="thin"><color rgb="FFD0D5DD"/></top><bottom style="thin"><color rgb="FFD0D5DD"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="11">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '<xf numFmtId="9" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="3" fontId="3" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="9" fontId="3" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
            . '</cellXfs></styleSheet>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . sv_rx_xml($ds['sheet']) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => $styles,
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'svrx');
        if ($tmp === false) return '';
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { @unlink($tmp); return ''; }
        foreach ($files as $name => $content) $zip->addFromString($name, $content);
        $ok = $zip->close();
        $bin = $ok ? (string)file_get_contents($tmp) : '';
        @unlink($tmp);
        return $bin;
    }
}

if (!function_exists('sv_rx_xls')) {
    /** Fallback when ZipArchive is missing: Excel XML Spreadsheet 2003 (.xls) that Excel opens natively. */
    function sv_rx_xls(array $ds): string {
        $cell = static function ($v, string $type, string $style): string {
            if ($type === 'int')  return '<Cell ss:StyleID="' . $style . 'N"><Data ss:Type="Number">' . (int)$v . '</Data></Cell>';
            if ($type === 'pct')  return '<Cell ss:StyleID="' . $style . 'P"><Data ss:Type="Number">' . ((int)$v / 100) . '</Data></Cell>';
            return '<Cell ss:StyleID="' . $style . '"><Data ss:Type="String">' . sv_rx_xml((string)$v) . '</Data></Cell>';
        };
        $x = '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>'
           . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
           . '<Styles>'
           . '<Style ss:ID="T"><Font ss:Bold="1" ss:Size="14"/></Style>'
           . '<Style ss:ID="L"><Font ss:Bold="1"/></Style>'
           . '<Style ss:ID="H"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#2563EB" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>'
           . '<Style ss:ID="D"/><Style ss:ID="DN"><NumberFormat ss:Format="#,##0"/></Style><Style ss:ID="DP"><NumberFormat ss:Format="0%"/></Style>'
           . '<Style ss:ID="B"><Font ss:Bold="1"/><Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/></Style>'
           . '<Style ss:ID="BN"><Font ss:Bold="1"/><Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/><NumberFormat ss:Format="#,##0"/></Style>'
           . '<Style ss:ID="BP"><Font ss:Bold="1"/><Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/><NumberFormat ss:Format="0%"/></Style>'
           . '</Styles><Worksheet ss:Name="' . sv_rx_xml($ds['sheet']) . '"><Table>'
           . '<Column ss:Width="190"/><Column ss:Width="170"/><Column ss:Width="95"/><Column ss:Width="70"/><Column ss:Width="70"/><Column ss:Width="80"/><Column ss:Width="80"/><Column ss:Width="100"/>';
        $x .= '<Row>' . $cell($ds['title'], 'text', 'T') . '</Row>';
        foreach ($ds['meta'] as $m) $x .= '<Row>' . $cell($m[0], 'text', 'L') . $cell($m[1], 'text', 'D') . '</Row>';
        $x .= '<Row/><Row>';
        foreach ($ds['headers'] as $h) $x .= $cell($h, 'text', 'H');
        $x .= '</Row>';
        foreach ($ds['rows'] as $row) {
            $x .= '<Row>';
            foreach ($row as $i => $v) $x .= $cell($v, $ds['types'][$i], 'D');
            $x .= '</Row>';
        }
        if (!$ds['rows']) $x .= '<Row>' . $cell($ds['empty'], 'text', 'D') . '</Row>';
        $x .= '<Row>';
        foreach ($ds['totals'] as $i => $v) $x .= $cell($v, $ds['types'][$i], 'B');
        $x .= '</Row></Table></Worksheet></Workbook>';
        return $x;
    }
}

/* ------------------------------------------------------------------ PDF -- */

if (!class_exists('SvRxPdf')) {
    /** Minimal dependency-free PDF writer: landscape A4, Helvetica, wrapped table with repeating header. */
    final class SvRxPdf {
        private const W = 841.89;
        private const H = 595.28;
        private const M = 36.0;
        private const SIZE = 8.5;
        private const LH = 11.0;
        private const PAD = 4.0;

        private array $pages = [];
        private string $cur = '';
        private float $y = 0.0;
        private array $colW = [190, 170, 85, 60, 55, 70, 70, 70];
        private array $ds;

        public function __construct(array $dataset) { $this->ds = $dataset; }

        private static function f(float $n): string { return rtrim(rtrim(sprintf('%.2F', $n), '0'), '.') ?: '0'; }

        private function enc(string $s): string {
            $s = str_replace(["\r", "\n", "\t"], ' ', $s);
            $out = null;
            if (function_exists('iconv')) {
                $c = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
                if ($c !== false) $out = $c;
            }
            return $out ?? (string)preg_replace('/[^\x20-\x7E]/', '?', $s);
        }

        private static function esc(string $s): string { return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s); }

        private function width(string $s, float $size, bool $bold): float {
            $w = 0.0;
            $n = strlen($s);
            for ($i = 0; $i < $n; $i++) {
                $c = $s[$i];
                if (strpos("ijl.,:;'|!()[]/ ft", $c) !== false) $u = 0.28;
                elseif (strpos('mwMW@%', $c) !== false)        $u = 0.85;
                elseif (ctype_upper($c))                         $u = 0.68;
                elseif (ctype_digit($c))                         $u = 0.556;
                else                                             $u = 0.53;
                $w += $u;
            }
            return $w * $size * ($bold ? 1.07 : 1.0);
        }

        /** Word-wraps (already encoded) text into lines no wider than $max. */
        private function wrap(string $s, float $size, bool $bold, float $max): array {
            if ($s === '') return [''];
            $lines = [];
            $line = '';
            foreach (explode(' ', $s) as $word) {
                $try = $line === '' ? $word : $line . ' ' . $word;
                if ($this->width($try, $size, $bold) <= $max) { $line = $try; continue; }
                if ($line !== '') { $lines[] = $line; $line = ''; }
                // very long word: split by characters
                while ($this->width($word, $size, $bold) > $max && strlen($word) > 1) {
                    $cut = strlen($word) - 1;
                    while ($cut > 1 && $this->width(substr($word, 0, $cut), $size, $bold) > $max) $cut--;
                    $lines[] = substr($word, 0, $cut);
                    $word = substr($word, $cut);
                }
                $line = $word;
            }
            if ($line !== '') $lines[] = $line;
            return $lines ?: [''];
        }

        private function newPage(): void {
            if ($this->cur !== '') $this->pages[] = $this->cur;
            $this->cur = '';
            $this->y = self::H - self::M;
        }

        private function text(float $x, float $y, string $s, float $size, bool $bold, string $rgb = '0.09 0.11 0.16'): void {
            $this->cur .= "BT {$rgb} rg /" . ($bold ? 'F2' : 'F1') . ' ' . self::f($size) . ' Tf ' . self::f($x) . ' ' . self::f($y) . ' Td (' . self::esc($s) . ") Tj ET\n";
        }

        private function rect(float $x, float $y, float $w, float $h, string $rgb): void {
            $this->cur .= "{$rgb} rg " . self::f($x) . ' ' . self::f($y) . ' ' . self::f($w) . ' ' . self::f($h) . " re f\n";
        }

        private function hline(float $x1, float $x2, float $y): void {
            $this->cur .= '0.82 0.84 0.88 RG 0.5 w ' . self::f($x1) . ' ' . self::f($y) . ' m ' . self::f($x2) . ' ' . self::f($y) . " l S\n";
        }

        private function row(array $cells, bool $header, bool $total): void {
            $types = $this->ds['types'];
            $bold  = $header || $total;
            $size  = self::SIZE;
            $wrapped = [];
            $maxLines = 1;
            foreach ($cells as $i => $v) {
                $t = $types[$i] ?? 'text';
                $str = $t === 'pct' && !$header ? $v . '%' : (string)$v;
                $lines = $this->wrap($this->enc($str), $size, $bold, $this->colW[$i] - 2 * self::PAD);
                $wrapped[$i] = $lines;
                $maxLines = max($maxLines, count($lines));
            }
            $h = $maxLines * self::LH + 2 * self::PAD - 2;
            if ($this->y - $h < self::M + 18) {
                $this->newPage();
                $this->row($this->ds['headers'], true, false);
            }
            $x0 = self::M;
            $tw = array_sum($this->colW);
            $bottom = $this->y - $h;
            if ($header)     $this->rect($x0, $bottom, $tw, $h, '0.145 0.388 0.922');
            elseif ($total)  $this->rect($x0, $bottom, $tw, $h, '0.945 0.961 0.976');
            $x = $x0;
            foreach ($cells as $i => $_) {
                $t = $types[$i] ?? 'text';
                foreach ($wrapped[$i] as $li => $ln) {
                    $ty = $this->y - self::PAD - $size * 0.85 - $li * self::LH;
                    $tx = $x + self::PAD;
                    $right = ($t !== 'text');
                    if ($right && !$header) $tx = $x + $this->colW[$i] - self::PAD - $this->width($ln, $size, $bold);
                    $this->text($tx, $ty, $ln, $size, $bold, $header ? '1 1 1' : '0.09 0.11 0.16');
                }
                $x += $this->colW[$i];
            }
            $this->hline($x0, $x0 + $tw, $bottom);
            $this->y = $bottom;
        }

        public function render(): string {
            $this->newPage();
            // Title + meta
            $this->text(self::M, $this->y - 16, $this->enc($this->ds['title']), 16, true);
            $this->y -= 26;
            foreach ($this->ds['meta'] as $m) {
                $line = $this->enc($m[0] . ': ' . $m[1]);
                $this->text(self::M, $this->y - 9, $line, 9, false, '0.4 0.44 0.52');
                $this->y -= 13;
            }
            $this->y -= 8;
            $this->row($this->ds['headers'], true, false);
            foreach ($this->ds['rows'] as $r) $this->row($r, false, false);
            if (!$this->ds['rows']) {
                $this->text(self::M + self::PAD, $this->y - 14, $this->enc($this->ds['empty']), self::SIZE, false, '0.4 0.44 0.52');
                $this->y -= 22;
            }
            $this->row($this->ds['totals'], false, true);
            if ($this->cur !== '') $this->pages[] = $this->cur;

            // Footers
            $count = count($this->pages);
            foreach ($this->pages as $i => $content) {
                $label = 'Page ' . ($i + 1) . ' of ' . $count;
                $foot  = 'BT 0.4 0.44 0.52 rg /F1 8 Tf ' . self::f(self::W - self::M - 50) . ' 22 Td (' . self::esc($label) . ") Tj ET\n"
                       . 'BT 0.4 0.44 0.52 rg /F1 8 Tf ' . self::f(self::M) . ' 22 Td (' . self::esc($this->enc($this->ds['title'])) . ") Tj ET\n";
                $this->pages[$i] = $content . $foot;
            }

            // Objects: 1 catalog, 2 pages, 3 Helvetica, 4 Helvetica-Bold, then (content, page) pairs
            $objs = [];
            $kids = [];
            foreach ($this->pages as $i => $content) {
                $cid = 5 + 2 * $i;
                $pid = 6 + 2 * $i;
                $kids[] = $pid . ' 0 R';
                $objs[$cid] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';
                $objs[$pid] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::f(self::W) . ' ' . self::f(self::H) . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $cid . ' 0 R >>';
            }
            $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
            $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $count . ' >>';
            $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
            $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
            ksort($objs);

            $pdf  = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
            $offs = [];
            foreach ($objs as $id => $body) {
                $offs[$id] = strlen($pdf);
                $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
            }
            $xref = strlen($pdf);
            $n = max(array_keys($objs)) + 1;
            $pdf .= "xref\n0 {$n}\n0000000000 65535 f \n";
            for ($i = 1; $i < $n; $i++) $pdf .= sprintf("%010d 00000 n \n", $offs[$i]);
            $pdf .= "trailer\n<< /Size {$n} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
            return $pdf;
        }
    }
}

if (!function_exists('sv_rx_pdf')) {
    function sv_rx_pdf(array $ds): string { return (new SvRxPdf($ds))->render(); }
}
