<?php
// tools/export_csv.php
// Usage (CLI): php export_csv.php
// Outputs CSV files to tools/exports/*.csv

require_once __DIR__ . '/../include/config.php';

$outDir = __DIR__ . '/exports';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);

$tables = [
    'class_reps',
    'courses',
    'students',
    'timetable',
    'att_sessions',
    'attendance',
    'webauthn_credentials',
    'qr_tokens'
];

$db = db();

function write_csv(string $path, array $rows) {
    $fh = fopen($path, 'w');
    if ($fh === false) throw new Exception("Unable to open $path for writing");
    if (count($rows) === 0) {
        // write an empty file with no header
        fclose($fh);
        return;
    }
    // header columns in stable order
    $header = array_keys($rows[0]);
    fputcsv($fh, $header);
    foreach ($rows as $r) {
        // Ensure no binary data breaks CSV: convert resources to strings
        $line = [];
        foreach ($header as $h) {
            $v = $r[$h];
            if (is_null($v)) { $line[] = null; continue; }
            if (is_resource($v)) { $line[] = strval($v); continue; }
            // Convert boolean-like 0/1 to plain integers to be safe
            if ($v === true) $v = 1;
            if ($v === false) $v = 0;
            $line[] = $v;
        }
        fputcsv($fh, $line);
    }
    fclose($fh);
}

$report = [];
foreach ($tables as $t) {
    try {
        $rows = $db->query("SELECT * FROM `" . str_replace('`','', $t) . "`")->fetchAll(PDO::FETCH_ASSOC);
        // Normalize by ordering keys consistently
        $norm = [];
        foreach ($rows as $r) {
            ksort($r);
            $norm[] = $r;
        }
        $path = $outDir . '/' . $t . '.csv';
        write_csv($path, $norm);
        $report[] = [ 'table' => $t, 'rows' => count($norm), 'file' => str_replace(__DIR__ . '/', '', $path) ];
    } catch (Exception $e) {
        $report[] = [ 'table' => $t, 'error' => $e->getMessage() ];
    }
}

if (php_sapi_name() === 'cli') {
    echo "Export complete:\n";
    foreach ($report as $r) {
        if (isset($r['error'])) echo " - {$r['table']}: ERROR: {$r['error']}\n";
        else echo " - {$r['table']}: {$r['rows']} rows -> {$r['file']}\n";
    }
    echo "\nFiles are in: $outDir\n";
} else {
    echo "<h2>Export complete</h2>\n<ul>\n";
    foreach ($report as $r) {
        if (isset($r['error'])) echo "<li><strong>{$r['table']}</strong>: ERROR: " . htmlspecialchars($r['error']) . "</li>\n";
        else echo "<li><strong>{$r['table']}</strong>: {$r['rows']} rows &rarr; <code>tools/exports/{$r['table']}.csv</code></li>\n";
    }
    echo "</ul>\n<p>Download the files from <code>tools/exports/</code> and import them in Supabase.</p>";
}

