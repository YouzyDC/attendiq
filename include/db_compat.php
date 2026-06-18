<?php
// include/db_compat.php — Database compatibility helpers for MySQL ↔ Postgres

function is_postgres(): bool {
    static $isPostgres = null;
    if ($isPostgres !== null) return $isPostgres;
    
    $dbUrl = getenv('DATABASE_URL');
    $isPostgres = !empty($dbUrl);
    return $isPostgres;
}

/**
 * Safely insert a row, ignoring duplicates (MySQL: INSERT IGNORE, Postgres: ON CONFLICT DO NOTHING)
 */
function insert_ignore(PDO $db, string $table, array $cols, array $values): bool {
    if (is_postgres()) {
        $colList = implode(',', $cols);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO $table ($colList) VALUES ($placeholders) ON CONFLICT DO NOTHING";
    } else {
        $colList = implode(',', $cols);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT IGNORE INTO $table ($colList) VALUES ($placeholders)";
    }
    
    try {
        return $db->prepare($sql)->execute($values);
    } catch (Exception $e) {
        // Silently fail on duplicate (same behavior as INSERT IGNORE)
        return false;
    }
}

/**
 * Safely insert a row, ignoring duplicates, with named columns (for convenience)
 */
function insert_ignore_assoc(PDO $db, string $table, array $data): bool {
    return insert_ignore($db, $table, array_keys($data), array_values($data));
}
