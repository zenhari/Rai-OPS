<?php
/**
 * strip_rai_fleet.php (web-safe)
 * Removes all "" substrings from every *.php file under a path.
 * Works from CLI and from a browser (GET params).
 *
 * CLI usage:
 *   php strip_rai_fleet.php --path="/path/to/project" --dry-run --backup --exclude="vendor,node_modules,.git"
 *
 * Browser usage (examples):
 *   strip_rai_fleet.php?path=/home/raiops/public_html&dry-run=1&exclude=vendor,node_modules,.git
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
@set_time_limit(0);

/* ---------- helpers ---------- */
function is_cli(): bool { return PHP_SAPI === 'cli'; }
function log_info(string $msg): void {
    if (is_cli()) { echo $msg; }
    else { echo htmlspecialchars($msg) . "<br>\n"; flush(); }
}
function log_warn(string $msg): void {
    if (is_cli()) { echo "[WARN] $msg"; }
    else { echo "<span style='color:#b45309'>[WARN] " . htmlspecialchars($msg) . "</span><br>\n"; }
    error_log(trim(strip_tags($msg)));
}
function log_error(string $msg): void {
    if (is_cli()) { echo "[ERROR] $msg"; }
    else { echo "<span style='color:#dc2626'>[ERROR] " . htmlspecialchars($msg) . "</span><br>\n"; }
    error_log(trim(strip_tags($msg)));
}

/* ---------- parse options (CLI or GET) ---------- */
$defaults = [
    'path'    => getcwd(),
    'dry-run' => false,
    'backup'  => false,
    'exclude' => '.git,node_modules,vendor,.idea,.vscode',
];

if (is_cli()) {
    $o = getopt('', ['path::','dry-run','backup','exclude::']);
    $basePath = isset($o['path']) ? rtrim($o['path'], DIRECTORY_SEPARATOR) : $defaults['path'];
    $dryRun   = array_key_exists('dry-run', $o);
    $backup   = array_key_exists('backup', $o);
    $excludeS = $o['exclude'] ?? $defaults['exclude'];
} else {
    $basePath = isset($_GET['path']) ? rtrim($_GET['path'], DIRECTORY_SEPARATOR) : $defaults['path'];
    $dryRun   = isset($_GET['dry-run']) && $_GET['dry-run'] != '0';
    $backup   = isset($_GET['backup']) && $_GET['backup'] != '0';
    $excludeS = $_GET['exclude'] ?? $defaults['exclude'];
}

$exclude = array_values(array_filter(array_map('trim', explode(',', (string)$excludeS)), fn($s) => $s !== ''));

$needle = '';
$needle2 = '/assets/';
$replacement2 = '/assets/';

/* ---------- sanity ---------- */
if (!is_dir($basePath)) {
    log_error("Path not found or not a directory: {$basePath}\n");
    exit(1);
}
$selfPath = realpath(__FILE__);
$start = microtime(true);

$stats = [
    'scanned_files' => 0,
    'matched_files' => 0,
    'replacements'  => 0,
    'written_files' => 0,
    'skipped_files' => 0,
    'errors'        => 0,
];

log_info("[strip] Base path: {$basePath}\n");
log_info("[strip] Options  : dry-run=" . ($dryRun ? 'yes' : 'no') .
         " backup=" . ($backup ? 'yes' : 'no') .
         " exclude=[" . implode(', ', $exclude) . "]\n");
log_info("[strip] Target   : removing \"{$needle}\" and replacing \"{$needle2}\" with \"{$replacement2}\"\n\n");

/* ---------- exclude check ---------- */
function isExcluded(string $path, array $excludeNames): bool {
    $segments = explode(DIRECTORY_SEPARATOR, rtrim($path, DIRECTORY_SEPARATOR));
    foreach ($excludeNames as $name) {
        if (in_array($name, $segments, true)) return true;
    }
    return false;
}

/* ---------- main walk ---------- */
try {
    $directory = new RecursiveDirectoryIterator(
        $basePath,
        FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
    );
    $filter = new RecursiveCallbackFilterIterator(
        $directory,
        function (SplFileInfo $current) use ($exclude) {
            $path = $current->getPathname();
            if ($current->isDir()) return !isExcluded($path, $exclude);
            return true;
        }
    );
    $iterator = new RecursiveIteratorIterator($filter);

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) continue;
        if (strtolower($file->getExtension()) !== 'php') continue;

        $path = $file->getPathname();

        // Skip this script
        if (realpath($path) === $selfPath) { $stats['skipped_files']++; continue; }

        $stats['scanned_files']++;

        $content = @file_get_contents($path);
        if ($content === false) { log_warn("Could not read file: {$path}\n"); $stats['errors']++; continue; }

        $countBefore1 = substr_count($content, $needle);
        $countBefore2 = substr_count($content, $needle2);
        $totalCount = $countBefore1 + $countBefore2;
        
        if ($totalCount === 0) continue;

        $stats['matched_files']++;
        $stats['replacements'] += $totalCount;

        // First replace /assets/ with /assets/
        $newContent = str_replace($needle2, $replacement2, $content);
        // Then remove remaining 
        $newContent = str_replace($needle, '', $newContent);

        if ($dryRun) {
            $details = [];
            if ($countBefore1 > 0) $details[] = ": {$countBefore1}";
            if ($countBefore2 > 0) $details[] = "/assets/: {$countBefore2}";
            log_info("[dry] {$path}  (replacements: " . implode(', ', $details) . ")\n");
            continue;
        }

        if ($backup) {
            $bakPath = $path . '.bak';
            if (@file_put_contents($bakPath, $content) === false) {
                log_warn("Failed to create backup: {$bakPath}\n");
                $stats['errors']++;
            }
        }

        if (@file_put_contents($path, $newContent) === false) {
            log_error("Failed to write file: {$path}\n");
            $stats['errors']++;
            continue;
        }

        // try keep perms
        $perms = @fileperms($path);
        if ($perms !== false) { @chmod($path, $perms & 0777); }

        $details = [];
        if ($countBefore1 > 0) $details[] = ": {$countBefore1}";
        if ($countBefore2 > 0) $details[] = "/assets/: {$countBefore2}";
        log_info("[ok ] {$path}  (replacements: " . implode(', ', $details) . ")\n");
        $stats['written_files']++;
    }

} catch (Throwable $e) {
    log_error("FATAL: {$e->getMessage()}\n");
    exit(1);
}

/* ---------- summary ---------- */
$duration = number_format(microtime(true) - $start, 3);
log_info("\n[strip] Done in {$duration}s\n");
log_info("[strip] Scanned: {$stats['scanned_files']}\n");
log_info("[strip] Matched: {$stats['matched_files']}\n");
log_info("[strip] Replaced occurrences: {$stats['replacements']}\n");
log_info("[strip] Written: {$stats['written_files']}\n");
log_info("[strip] Skipped: {$stats['skipped_files']}\n");
log_info("[strip] Errors : {$stats['errors']}\n");

exit($stats['errors'] > 0 ? 1 : 0);
