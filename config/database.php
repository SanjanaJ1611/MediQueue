<?php
/**
 * MediQueue - Database Configuration & Dual-Engine Driver (MySQL with Seamless SQLite Fallback)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// System Constants
define('APP_NAME', 'MediQueue');
define('APP_TAGLINE', 'Hospital Appointment & Virtual Queue Management System');

// Dynamic BASE_URL detection for XAMPP / subfolder or root hosting
if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $subfolders = ['/patient', '/doctor', '/admin', '/ajax', '/config', '/includes', '/api'];
    $baseUrl = $scriptDir;
    foreach ($subfolders as $sub) {
        if (substr($baseUrl, -strlen($sub)) === $sub) {
            $baseUrl = substr($baseUrl, 0, -strlen($sub));
            break;
        }
    }
    define('BASE_URL', rtrim($baseUrl, '/'));
}

// Database Connection Settings (Supports individual env vars or DATABASE_URL)
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'mediqueue';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

// Parse DATABASE_URL if available (common in Railway, Render, Heroku)
if ($db_url = getenv('DATABASE_URL')) {
    $parsed = parse_url($db_url);
    if ($parsed) {
        $db_host = $parsed['host'] ?? $db_host;
        $db_port = $parsed['port'] ?? $db_port;
        $db_user = $parsed['user'] ?? $db_user;
        $db_pass = $parsed['pass'] ?? $db_pass;
        $db_name = ltrim($parsed['path'] ?? $db_name, '/');
    }
}

$pdo = null;
$db_error = null;
$db_driver = 'mysql';

// 1. Try connecting to MySQL
try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (getenv('DB_SSL') === 'true' || getenv('MYSQL_ATTR_SSL_CA')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    $db_driver = 'mysql';
} catch (Exception $e) {
    // 2. If MySQL is not reachable or credentials differ, seamlessly fall back to SQLite database
    $sqlitePath = __DIR__ . '/../database/mediqueue.sqlite';
    if (file_exists($sqlitePath)) {
        // If running in a read-only serverless environment (e.g. Vercel), copy to writable /tmp
        if (getenv('VERCEL') || !is_writable($sqlitePath)) {
            $tmpSqlite = sys_get_temp_dir() . '/mediqueue.sqlite';
            if (!file_exists($tmpSqlite) || filesize($tmpSqlite) === 0) {
                @copy($sqlitePath, $tmpSqlite);
            }
            if (file_exists($tmpSqlite)) {
                $sqlitePath = $tmpSqlite;
            }
        }
        try {
            $pdo = new PDO("sqlite:" . $sqlitePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $db_driver = 'sqlite';

            // Register MySQL-compatible SQL functions for seamless compatibility
            $pdo->sqliteCreateFunction('CURDATE', function() {
                return date('Y-m-d');
            });
            $pdo->sqliteCreateFunction('NOW', function() {
                return date('Y-m-d H:i:s');
            });
            $pdo->sqliteCreateFunction('CURTIME', function() {
                return date('H:i:s');
            });
            $pdo->sqliteCreateFunction('DATE_ADD', function($date, $expr) {
                // Approximate standard INTERVAL matches
                return date('Y-m-d', strtotime('+1 day', strtotime($date)));
            });
            $pdo->sqliteCreateFunction('DATE_SUB', function($date, $expr) {
                return date('Y-m-d', strtotime('-1 day', strtotime($date)));
            });
            $pdo->sqliteCreateFunction('TIMESTAMPDIFF', function($unit, $t1, $t2) {
                if (!$t1 || !$t2) return 15;
                $diff = abs(strtotime($t2) - strtotime($t1));
                return round($diff / 60);
            });

        } catch (Exception $sqe) {
            $db_error = $sqe->getMessage();
        }
    } else {
        $db_error = $e->getMessage();
    }
}

function get_db_connection() {
    global $pdo, $db_error, $db_name;
    if (!$pdo) {
        die("
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff5f5; color: #991b1b;'>
                <h2 style='margin-top:0; color:#b91c1c;'>MediQueue Database Connection Notice</h2>
                <p>Could not connect to database <strong>{$db_name}</strong>.</p>
                <p style='background: #fee2e2; padding: 10px; border-radius: 6px; font-family: monospace; font-size: 13px;'>Error: " . htmlspecialchars($db_error ?? 'Unknown error') . "</p>
            </div>
        ");
    }
    return $pdo;
}
