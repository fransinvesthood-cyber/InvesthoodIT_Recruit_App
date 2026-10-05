<?php
/**
 * TEMPORARY developer inspection tool - LOCAL DEVELOPMENT ONLY.
 * Deleted before the task is completed.
 */

$_KEY = 'devlocal';
if (($_GET['key'] ?? '') !== $_KEY) {
    http_response_code(403);
    exit('forbidden');
}

header('Content-Type: text/plain; charset=utf-8');

$ROOT = __DIR__;
$mode = $_GET['mode'] ?? 'read';

function out_lines(string $file, int $from, int $to): void
{
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        echo "cannot read\n";
        return;
    }
    $total = count($lines);
    $to = $to > 0 ? min($to, $total) : $total;
    for ($i = max(1, $from); $i <= $to; $i++) {
        echo $i . ': ' . $lines[$i - 1] . "\n";
    }
    echo "\n--- total lines: {$total} ---\n";
}

$rel = str_replace(['..', '\\'], ['', '/'], (string) ($_GET['file'] ?? ''));
$path = $ROOT . '/' . ltrim($rel, '/');

switch ($mode) {
    case 'ls':
        $dir = $_GET['dir'] === '' ? $ROOT : $ROOT . '/' . str_replace(['..', '\\'], ['', '/'], (string) $_GET['dir']);
        $items = @scandir($dir);
        if (!$items) { echo "cannot list {$dir}\n"; break; }
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            echo (is_dir($dir . '/' . $it) ? '[d] ' : '    ') . $it . "\n";
        }
        break;

    case 'map':
        if (!is_file($path)) { echo "missing: {$rel}\n"; break; }
        $pattern = $_GET['pattern'] ?? '^\s*(class|public|private|protected|const|function|/\*\*[A-Za-z ]+)';
        if ($pattern === '') { $pattern = '.+'; }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $n => $line) {
            if (@preg_match('/' . str_replace('/', '\/', $pattern) . '/', $line) === 1) {
                echo ($n + 1) . ': ' . rtrim($line) . "\n";
            }
        }
        echo "\n--- total lines: " . count($lines) . " ---\n";
        break;

    case 'grep':
        $pattern = (string) ($_GET['pattern'] ?? '');
        // recursive grep when a directory is given instead of a file
        $files = [$path];
        if (!isset($_GET['dir']) && !is_file($path)) { echo "missing: {$rel}\n"; break; }
        if (isset($_GET['dir'])) {
            $dir = $ROOT . '/' . str_replace(['..', '\\'], ['', '/'], (string) $_GET['dir']);
            $files = [];
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) { $files[] = (string) $f; }
            }
        }
        $ext = (string) ($_GET['ext'] ?? '');
        foreach ($files as $f) {
            if ($ext !== '' && !preg_match('/\.(' . $ext . ')$/i', $f)) continue;
            $lines = @file($f, FILE_IGNORE_NEW_LINES);
            if ($lines === false) continue;
            foreach ($lines as $n => $line) {
                if (@preg_match('/' . $pattern . '/', $line) === 1) {
                    echo str_replace($ROOT . '/', '', $f) . ':' . ($n + 1) . ': ' . trim($line) . "\n";
                }
            }
        }
        break;

    case 'read':
        if (!is_file($path)) { echo "missing: {$rel}\n"; break; }
        out_lines($path, (int) ($_GET['from'] ?? 1), (int) ($_GET['to'] ?? 0));
        break;

    case 'lint':
        if (!is_file($path)) { echo "missing: {$rel}\n"; break; }
        $php = 'C:\\xampp\\php\\php.exe';
        $cmd = escapeshellarg($php) . ' -l ' . escapeshellarg($path) . ' 2>&1';
        if (function_exists('shell_exec')) {
            echo (string) shell_exec($cmd) . "\n";
        } else {
            echo "shell_exec disabled\n";
        }
        break;

    case 'sql':
        require_once __DIR__ . '/includes/db.php';
        $q = (string) ($_GET['q'] ?? 'SELECT 1');
        try {
            $rows = Database::fetchAll($q);
            echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } catch (Throwable $e) {
            echo 'ERROR: ' . $e->getMessage() . "\n";
        }
        break;

    case 'exec':
        require_once __DIR__ . '/includes/db.php';
        $q = (string) ($_GET['q'] ?? '');
        try {
            $n = Database::execute($q);
            echo "affected: {$n}\n";
        } catch (Throwable $e) {
            echo 'ERROR: ' . $e->getMessage() . "\n";
        }
        break;

    case 'tables':
        require_once __DIR__ . '/includes/db.php';
        $rows = Database::fetchAll('SHOW TABLES');
        foreach ($rows as $r) { echo implode(' | ', array_values($r)) . "\n"; }
        break;

    case 'schema':
        require_once __DIR__ . '/includes/db.php';
        $t = preg_replace('/[^a-z0-9_]/i', '', (string) ($_GET['t'] ?? '')) ?: 'offers';
        $rows = Database::fetchAll("SHOW COLUMNS FROM `{$t}`");
        foreach ($rows as $r) {
            echo str_pad($r['Field'], 20) . ' ' . str_pad($r['Type'], 40) . ' null=' . $r['Null'] . ' def=' . var_export($r['Default'], true) . "\n";
        }
        break;

    case 'php':
        require_once __DIR__ . '/includes/bootstrap.php';
        $f = $ROOT . '/' . $rel;
        if (!is_file($f)) { echo "missing: {$rel}\n"; break; }
        try {
            echo "OK compiled\n";
        } catch (Throwable $e) {
            echo 'ERROR: ' . $e->getMessage() . "\n";
        }
        break;

    case 'env':
        echo 'PHP: ' . PHP_VERSION . "\n";
        echo 'curl: ' . (extension_loaded('curl') ? 'yes' : 'no') . "\n";
        echo 'mbstring: ' . (extension_loaded('mbstring') ? 'yes' : 'no') . "\n";
        echo 'sqlite: ' . (extension_loaded('pdo_sqlite') ? 'yes' : 'no') . "\n";
        echo 'tz: ' . date_default_timezone_get() . "\n";
        echo 'shell_exec: ' . (function_exists('shell_exec') ? 'yes' : 'no') . "\n";
        break;

    default:
        echo "unknown mode\n";
}
