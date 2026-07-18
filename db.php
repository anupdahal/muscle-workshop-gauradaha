<?php
/**
 * Database connection (PDO) for Muscle Workshop Gauradaha.
 *
 * Uses PDO with:
 *   - ERRMODE_EXCEPTION        -> errors throw exceptions we can catch
 *   - FETCH_ASSOC              -> consistent associative arrays
 *   - EMULATE_PREPARES = false -> REAL server-side prepared statements
 *                                 (strong protection against SQL injection)
 *
 * On first run the schema tables are created automatically from
 * database.sql so a fresh deployment works without manual import.
 */

$host    = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'muscle_workshop_gauradaha';
$user    = getenv('DB_USER') ?: 'root';
$pass    = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';
$port    = getenv('DB_PORT') ?: '3306';

$dsn = "mysql:host={$host};port={$port};dbname={$db_name};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // If the database does not exist yet, create it then reconnect
    // so a brand-new deployment bootstraps itself cleanly.
    try {
        $root = new PDO("mysql:host={$host};port={$port};charset={$charset}", $user, $pass, $options);
        $root->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $root = null;
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e2) {
        http_response_code(500);
        die('Database connection failed. Please check your MySQL credentials in db.php.');
    }
}

/**
 * Auto-bootstrap: create every table from database.sql if missing.
 * CREATE TABLE IF NOT EXISTS is idempotent and cheap, so this is
 * safe to run on every request and keeps deployments self-contained.
 */
try {
    $sqlFile = __DIR__ . '/database.sql';
    if (is_file($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        // Strip line comments (-- ...) and blank lines so statement
        // splitting on ';' is reliable and no real statement is lost.
        $lines = preg_split('/\r\n|\r|\n/', $sql);
        $lines = array_filter($lines, function ($l) {
            $t = ltrim($l);
            return $t !== '' && !str_starts_with($t, '--');
        });
        $sql = implode("\n", $lines);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt === '') {
                continue;
            }
            $pdo->exec($stmt);
        }
    }
} catch (PDOException $e) {
    // Schema bootstrap is best-effort; let the app continue and let
    // individual queries report their own errors where relevant.
}
