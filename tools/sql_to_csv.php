<?php
// tools/sql_to_csv.php
// Parses the SQL dump (attendiq.sql) and generates CSVs for Supabase import

$sqlFile = __DIR__ . '/../attendiq.sql';
if (!file_exists($sqlFile)) {
    die("Error: $sqlFile not found.\n");
}

$outDir = __DIR__ . '/exports';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);

$sql = file_get_contents($sqlFile);

// Parse and generate CSVs per table
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

function parse_insert_statement($sql, $table) {
    // Match: INSERT INTO `table_name` (`col1`,`col2`,...) VALUES ('val1','val2',...), (...), ...;
    $pattern = '/INSERT INTO `' . preg_quote($table) . '`\s*\(([^)]+)\)\s*VALUES\s*(.+?)(?=;)/is';
    if (!preg_match($pattern, $sql, $m)) {
        return ['columns' => [], 'rows' => []];
    }

    $colStr = $m[1];
    $valStr = $m[2];

    // Parse columns
    $columns = array_map(fn($c) => trim($c, '` \'"'), preg_split('/,/', $colStr));

    // Parse VALUES — handle multiple rows and quoted/escaped values
    $rows = [];
    $valStr = trim($valStr, '(),\' ');
    
    // Split by "), (" to separate rows
    $rowStrings = preg_split('/\)\s*,\s*\(/', $valStr);
    
    foreach ($rowStrings as $rowStr) {
        $rowStr = trim($rowStr, '()');
        $values = [];
        
        // Simple parser: split by comma but respect quotes
        $current = '';
        $inQuote = false;
        $quoteChar = null;
        $escaped = false;
        
        for ($i = 0; $i < strlen($rowStr); $i++) {
            $c = $rowStr[$i];
            
            if ($escaped) {
                $current .= $c;
                $escaped = false;
                continue;
            }
            
            if ($c === '\\') {
                $current .= $c;
                $escaped = true;
                continue;
            }
            
            if (!$inQuote && ($c === '"' || $c === "'")) {
                $inQuote = true;
                $quoteChar = $c;
                continue;
            }
            
            if ($inQuote && $c === $quoteChar) {
                $inQuote = false;
                $quoteChar = null;
                continue;
            }
            
            if (!$inQuote && $c === ',') {
                $values[] = trim($current);
                $current = '';
                continue;
            }
            
            $current .= $c;
        }
        
        if ($current !== '') {
            $values[] = trim($current);
        }
        
        if (count($values) > 0) {
            $rows[] = $values;
        }
    }
    
    return ['columns' => $columns, 'rows' => $rows];
}

function write_csv_file($path, $columns, $rows) {
    $fh = fopen($path, 'w');
    if (!$fh) throw new Exception("Cannot open $path");
    
    fputcsv($fh, $columns);
    foreach ($rows as $row) {
        fputcsv($fh, $row);
    }
    fclose($fh);
}

$report = [];
foreach ($tables as $t) {
    try {
        $parsed = parse_insert_statement($sql, $t);
        $columns = $parsed['columns'];
        $rows = $parsed['rows'];
        
        if (count($columns) === 0) {
            $report[] = ['table' => $t, 'status' => 'empty', 'rows' => 0];
            continue;
        }
        
        $path = $outDir . '/' . $t . '.csv';
        write_csv_file($path, $columns, $rows);
        $report[] = ['table' => $t, 'status' => 'ok', 'rows' => count($rows)];
    } catch (Exception $e) {
        $report[] = ['table' => $t, 'status' => 'error', 'error' => $e->getMessage()];
    }
}

echo "CSV Export from SQL Dump Complete\n";
echo "==================================\n\n";
foreach ($report as $r) {
    $status = strtoupper($r['status']);
    if ($r['status'] === 'ok') {
        echo "✓ {$r['table']}: {$r['rows']} rows\n";
    } elseif ($r['status'] === 'empty') {
        echo "○ {$r['table']}: empty (no data)\n";
    } else {
        echo "✗ {$r['table']}: ERROR - {$r['error']}\n";
    }
}
echo "\n→ Files saved to: $outDir\n";
echo "\nNext: Import these CSVs into Supabase using the Table Editor\n";
