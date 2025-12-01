<?php
/**
 * db_backup.php — Smart MySQL backup using config.php
 *
 * Features:
 *  - Reads DB_* constants from a PHP config file (default: ./config.php)
 *  - Tries mysqldump (auto-detect common paths). Uses a secure temp my.cnf
 *  - Falls back to pure-PHP export via PDO (tables + data + views + triggers + events + routines)
 *  - Prepends your DB_* settings as comments at the top of backup.sql
 *  - Outputs progress messages in UTF-8
 *
 * Usage (CLI):
 *   php db_backup.php [--config=/path/to/config.php] [--outdir=/path/to/backups]
 *
 * Usage (Web):
 *   /db_backup.php?config=/path/to/config.php&outdir=/path/to/backups
 */

declare(strict_types=1);
mb_internal_encoding('UTF-8');

//--------------------------------------------------
// Helpers
//--------------------------------------------------
function e(string $s): void { echo $s.(PHP_SAPI === 'cli' ? PHP_EOL : "<br>\n"); }
function fatal(string $s, int $code = 1): never { e("❌ $s"); exit($code); }
function arg(string $name, ?string $default = null): ?string {
    // CLI --key=value or Web ?key=value
    if (PHP_SAPI === 'cli') {
        global $argv; foreach ($argv as $a) { if (str_starts_with($a, "--$name=")) return substr($a, strlen($name)+3); }
    }
    return $_GET[$name] ?? $default;
}

//--------------------------------------------------
// Locate / read config.php (expects DB_HOST/DB_NAME/DB_USER/DB_PASS/DB_CHARSET)
//--------------------------------------------------
$CONFIG_FILE = arg('config', __DIR__ . DIRECTORY_SEPARATOR . 'config.php');
if (!is_file($CONFIG_FILE)) fatal("Config not found: $CONFIG_FILE");

// Load constants safely: prefer include, fall back to parsing if not defined
require_once $CONFIG_FILE;
$consts = ['DB_HOST','DB_NAME','DB_USER','DB_PASS','DB_CHARSET'];
$cfg = [];
foreach ($consts as $c) {
    if (defined($c)) $cfg[$c] = constant($c);
}

if (count($cfg) < 5) {
    // Fallback: parse file for define('X','Y');
    $src = file_get_contents($CONFIG_FILE);
    if ($src !== false) {
        foreach ($consts as $c) {
            if (!isset($cfg[$c]) || $cfg[$c] === '') {
                if (preg_match("/define\\(\\s*'".$c."'\\s*,\\s*'([^']*)'\\s*\\)\\s*;/i", $src, $m)) {
                    $cfg[$c] = $m[1];
                }
            }
        }
    }
}

foreach ($consts as $c) {
    if (!isset($cfg[$c])) fatal("Missing constant $c in config: $CONFIG_FILE");
}

$DB_HOST = (string)$cfg['DB_HOST'];
$DB_NAME = (string)$cfg['DB_NAME'];
$DB_USER = (string)$cfg['DB_USER'];
$DB_PASS = (string)$cfg['DB_PASS'];
$DB_CHARSET = (string)$cfg['DB_CHARSET'];

//--------------------------------------------------
// Output directory
//--------------------------------------------------
$OUT_DIR = rtrim((string)(arg('outdir', __DIR__ . DIRECTORY_SEPARATOR . 'backups')), DIRECTORY_SEPARATOR);
if (!is_dir($OUT_DIR)) {
    if (!mkdir($OUT_DIR, 0775, true)) fatal("Cannot create output dir: $OUT_DIR");
}

$ts = date('Ymd-His');
$outBase = $DB_NAME . "_backup_" . $ts . ".sql";
$outFile = $OUT_DIR . DIRECTORY_SEPARATOR . $outBase;

//--------------------------------------------------
// Header with config constants (commented)
//--------------------------------------------------
$header = "-- ================================================\n".
          "-- Raimon Airways MySQL Backup\n".
          "-- Generated: ".date('c')."\n".
          "-- Host    : {$DB_HOST}\n".
          "-- Database: {$DB_NAME}\n".
          "-- User    : {$DB_USER}\n".
          "-- Charset : {$DB_CHARSET}\n".
          "-- Source  : ".basename($CONFIG_FILE)."\n".
          "-- ================================================\n\n".
          "SET NAMES {$DB_CHARSET};\n".
          "SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n".
          "SET @OLD_FK=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;\n\n";

//--------------------------------------------------
// Try mysqldump first
//--------------------------------------------------
function find_mysqldump(): ?string {
    $candidates = [];
    // 1) which/where
    $which = trim((string)@shell_exec('which mysqldump 2>/dev/null'));
    if ($which !== '') $candidates[] = $which;
    $where = trim((string)@shell_exec('where mysqldump 2>nul'));
    if ($where !== '') {
        foreach (preg_split('/\r?\n/', $where) as $p) if ($p !== '') $candidates[] = $p;
    }
    // 2) common paths (Linux/macOS)
    foreach ([
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
        '/usr/local/mysql/bin/mysqldump',
        '/opt/homebrew/bin/mysqldump',
    ] as $p) $candidates[] = $p;
    // 3) common paths (Windows / XAMPP / MySQL installers)
    foreach ([
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
        'C:\\Program Files (x86)\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
        'C:\\Program Files\\MariaDB 10.4\\bin\\mysqldump.exe',
    ] as $p) $candidates[] = $p;

    foreach ($candidates as $p) {
        if (@is_file($p) && @is_executable($p)) return $p;
    }
    return null;
}

function run_mysqldump(string $mysqldump, string $host, string $db, string $user, string $pass, string $charset, string $outFile): array {
    $tmpCnf = tempnam(sys_get_temp_dir(), 'mycnf_');
    $errFile = tempnam(sys_get_temp_dir(), 'mdump_err_');
    $ok = false; $err = '';
    $cnf = "[client]\nhost={$host}\nuser={$user}\npassword={$pass}\n";
    file_put_contents($tmpCnf, $cnf);

    $cmd = sprintf('"%s" --defaults-extra-file="%s" --single-transaction --skip-lock-tables --routines --events --triggers --set-gtid-purged=OFF --default-character-set=%s --no-tablespaces --databases "%s" > "%s" 2> "%s"',
        $mysqldump, $tmpCnf, escapeshellarg($charset), $db, $outFile, $errFile
    );

    // Note: escapeshellarg($charset) adds quotes; acceptable for this flag context
    @exec($cmd, $void, $rc);

    if ($rc === 0 && is_file($outFile) && filesize($outFile) > 0) {
        $ok = true;
    } else {
        $err = @file_get_contents($errFile) ?: 'Unknown mysqldump error';
    }

    @unlink($tmpCnf);
    @unlink($errFile);
    return [$ok, $err];
}

//--------------------------------------------------
// Fallback: Pure PHP dump via PDO
//--------------------------------------------------
function pdo(): PDO {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET;
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ]);
    return $pdo;
}

function backtick(string $name): string { return '`'.str_replace('`','``',$name).'`'; }

function php_dump_all(string $outFile): void {
    $pdo = pdo();
    $fh = fopen($outFile, 'wb');
    if (!$fh) fatal("Cannot write to $outFile");

    // Disable FK
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n\n");

    // Tables
    $tables = [];
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) { $tables[] = $row[0]; }

    foreach ($tables as $t) {
        // Drop + Create
        fwrite($fh, "--\n-- Table structure for ".backtick($t)."\n--\n");
        fwrite($fh, "DROP TABLE IF EXISTS ".backtick($t).";\n");
        $row = $pdo->query("SHOW CREATE TABLE ".backtick($t))->fetch(PDO::FETCH_ASSOC);
        fwrite($fh, $row['Create Table'].';'."\n\n");

        // Data (batch inserts)
        $count = (int)$pdo->query("SELECT COUNT(*) FROM ".backtick($t))->fetchColumn();
        if ($count > 0) {
            fwrite($fh, "--\n-- Dumping data for table ".backtick($t)." (rows: {$count})\n--\n");
            $colsStmt = $pdo->query("SHOW COLUMNS FROM ".backtick($t));
            $cols = [];
            while ($c = $colsStmt->fetch(PDO::FETCH_ASSOC)) $cols[] = $c['Field'];
            $colList = implode(',', array_map('backtick', $cols));

            $batchSize = 500; $batch = [];
            $q = $pdo->query("SELECT * FROM ".backtick($t));
            while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
                $vals = [];
                foreach ($cols as $c) {
                    $v = $r[$c];
                    if ($v === null) { $vals[] = 'NULL'; }
                    elseif (is_numeric($v) && !preg_match('/^0[0-9]/', (string)$v)) { $vals[] = (string)$v; }
                    else { $vals[] = $pdo->quote((string)$v); }
                }
                $batch[] = '('.implode(',', $vals).')';
                if (count($batch) >= $batchSize) {
                    fwrite($fh, "INSERT INTO ".backtick($t)." ({$colList}) VALUES\n".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($fh, "INSERT INTO ".backtick($t)." ({$colList}) VALUES\n".implode(",\n", $batch).";\n");
            }
            fwrite($fh, "\n");
        }
    }

    // Views
    $views = [];
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type='VIEW'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) { $views[] = $row[0]; }
    if ($views) {
        fwrite($fh, "--\n-- Views\n--\n");
        foreach ($views as $v) {
            $row = $pdo->query("SHOW CREATE VIEW ".backtick($v))->fetch(PDO::FETCH_ASSOC);
            fwrite($fh, "DROP VIEW IF EXISTS ".backtick($v).";\n");
            // Normalize DEFINER away by replacing DEFINER clause if present
            $createView = $row['Create View'];
            $createView = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\\s+/', '', $createView);
            $createView = preg_replace('/ALGORITHM=\\w+\\s*/', '', $createView);
            // Force INVOKER for portability
            $createView = preg_replace('/SQL SECURITY \\w+/', 'SQL SECURITY INVOKER', $createView);
            fwrite($fh, $createView.';'."\n\n");
        }
    }

    // Triggers
    $trigs = $pdo->query("SHOW TRIGGERS WHERE `Table` IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    if ($trigs) {
        fwrite($fh, "--\n-- Triggers\n--\n");
        foreach ($trigs as $tg) {
            $name = $tg['Trigger'];
            $row = $pdo->query("SHOW CREATE TRIGGER ".backtick($name))->fetch(PDO::FETCH_ASSOC);
            fwrite($fh, "DROP TRIGGER IF EXISTS ".backtick($name).";\n".$row['SQL Original Statement'].';'."\n\n");
        }
    }

    // Events
    $events = $pdo->query("SHOW EVENTS WHERE Db = ".$pdo->quote($GLOBALS['DB_NAME']))->fetchAll(PDO::FETCH_ASSOC);
    if ($events) {
        fwrite($fh, "--\n-- Events\n--\n");
        foreach ($events as $ev) {
            $name = $ev['Name'];
            $row = $pdo->query("SHOW CREATE EVENT ".backtick($name))->fetch(PDO::FETCH_ASSOC);
            fwrite($fh, "DROP EVENT IF EXISTS ".backtick($name).";\n".$row['Create Event'].';'."\n\n");
        }
    }

    // Routines (procedures/functions)
    $routines = $pdo->query("SELECT ROUTINE_NAME, ROUTINE_TYPE FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = ".$pdo->quote($GLOBALS['DB_NAME']))->fetchAll(PDO::FETCH_ASSOC);
    if ($routines) {
        fwrite($fh, "--\n-- Routines\n--\n");
        foreach ($routines as $r) {
            $name = $r['ROUTINE_NAME'];
            $type = strtoupper($r['ROUTINE_TYPE']);
            $row = $pdo->query("SHOW CREATE {$type} ".backtick($name))->fetch(PDO::FETCH_ASSOC);
            $col = $type === 'FUNCTION' ? 'Create Function' : 'Create Procedure';
            $drop = $type === 'FUNCTION' ? 'DROP FUNCTION IF EXISTS ' : 'DROP PROCEDURE IF EXISTS ';
            fwrite($fh, $drop.backtick($name).";\n".$row[$col].';' . "\n\n");
        }
    }

    // Re-enable FK
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=@OLD_FK;\nSET SQL_MODE=@OLD_SQL_MODE;\n");
    fclose($fh);
}

//--------------------------------------------------
// Run
//--------------------------------------------------
e("🟢 Starting backup of database '".$DB_NAME."'...");

$mysqldump = find_mysqldump();
$usedFallback = false;
$tmpDump = tempnam(sys_get_temp_dir(), 'dump_');

if ($mysqldump) {
    e("🔎 Found mysqldump at: $mysqldump");
    [$ok, $err] = run_mysqldump($mysqldump, $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET, $tmpDump);
    if (!$ok) {
        e("⚠️ mysqldump failed, falling back to PHP exporter.");
        e("   Details: ".trim($err));
        $usedFallback = true;
    }
} else {
    e("ℹ️ mysqldump not found, using PHP exporter.");
    $usedFallback = true;
}

if ($usedFallback) {
    // Build full dump via PDO into tmp, then write header + dump to final
    php_dump_all($tmpDump);
}

// Combine header + body
$body = file_get_contents($tmpDump);
if ($body === false || $body === '') fatal('Backup body is empty.');
$final = $header . $body;
if (file_put_contents($outFile, $final) === false) fatal('Failed to write final backup file.');
@unlink($tmpDump);

$size = number_format(filesize($outFile));
e("✅ Backup completed: ".$outFile." (".$size." bytes)".
  ($usedFallback ? ' [PHP exporter]' : ' [mysqldump]'));

if (PHP_SAPI !== 'cli') {
    echo '<hr><a href="'.htmlspecialchars('backups/'.basename($outFile)).'" download>Download backup</a>';
}
