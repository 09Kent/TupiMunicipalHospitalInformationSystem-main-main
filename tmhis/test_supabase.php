<?php
/**
 * TMHIS - Supabase PostgreSQL Connection & Health Diagnostics
 * Usage: php test_supabase.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
try {
    $dotenv->load();
} catch (\Exception $e) {
    echo "[!] Warning: Could not load .env directly via Dotenv. Using getenv/$_ENV.\n";
}

$host = env('SUPABASE_DB_HOST', env('DB_HOST', 'aws-0-ap-southeast-1.pooler.supabase.com'));
$port = env('SUPABASE_DB_PORT', env('DB_PORT', '6543'));
$dbname = env('SUPABASE_DB_DATABASE', env('DB_DATABASE', 'postgres'));
$user = env('SUPABASE_DB_USERNAME', env('DB_USERNAME', ''));
$password = env('SUPABASE_DB_PASSWORD', env('DB_PASSWORD', ''));
$sslmode = env('DB_SSLMODE', 'require');

echo "========================================================\n";
echo "TMHIS Supabase PostgreSQL Diagnostics\n";
echo "========================================================\n";
echo "Host:     $host\n";
echo "Port:     $port\n";
echo "Database: $dbname\n";
echo "User:     $user\n";
echo "SSL Mode: $sslmode\n";
echo "--------------------------------------------------------\n";

if (empty($user) || str_contains($user, 'your-project-ref')) {
    echo "[!] Notice: Supabase credentials in .env are currently placeholder values.\n";
    echo "    To connect to live Supabase:\n";
    echo "    1. Open your Supabase Dashboard -> Project Settings -> Database\n";
    echo "    2. Copy the Connection Pooling string (Host, Port, User, Password)\n";
    echo "    3. Put them into tmhis/.env and rerun this diagnostic script.\n";
    echo "========================================================\n";
    exit(0);
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ]);

    echo "[✓] Connection SUCCESSFUL!\n";

    // Check version
    $stmt = $pdo->query("SELECT version();");
    $version = $stmt->fetchColumn();
    echo "[✓] PostgreSQL Version: $version\n\n";

    // Count tables in public schema
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($tables);

    echo "[✓] Tables found in public schema ($tableCount tables):\n";
    if ($tableCount === 0) {
        echo "    [i] No tables found. Please run the SQL migration script:\n";
        echo "        File: tmhis/database/sql/supabase_deploy.sql\n";
        echo "        In your Supabase Dashboard -> SQL Editor -> Run\n";
    } else {
        foreach (array_slice($tables, 0, 15) as $table) {
            echo "    - $table\n";
        }
        if ($tableCount > 15) {
            echo "    ... and " . ($tableCount - 15) . " more tables.\n";
        }
    }

    echo "========================================================\n";
    echo "[✓] Supabase is ready for TMHIS!\n";
} catch (\PDOException $e) {
    echo "[x] Connection FAILED: " . $e->getMessage() . "\n";
    echo "========================================================\n";
    exit(1);
}
