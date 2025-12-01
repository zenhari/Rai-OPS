<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/settings/backup_db.php');

$current_user = getCurrentUser();
$message = '';
$error = '';
$backup_file = '';
$backup_size = '';

// Create backup directory if it doesn't exist
$backup_dir = __DIR__ . '/../../backup';
if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0755, true)) {
        $error = 'Failed to create backup directory.';
    }
}

// Handle delete backup request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_backup') {
    $filename = $_POST['filename'] ?? '';
    if (!empty($filename) && preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename)) {
        $file_path = $backup_dir . '/' . $filename;
        if (file_exists($file_path) && unlink($file_path)) {
            $message = "Backup file '{$filename}' deleted successfully.";
        } else {
            $error = "Failed to delete backup file '{$filename}'.";
        }
    } else {
        $error = "Invalid backup filename.";
    }
}

// Handle backup request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_backup') {
    try {
        // Generate backup filename with date and time
        $timestamp = date('Y-m-d_H-i-s');
        $backup_filename = "backup_{$timestamp}.sql";
        $backup_path = $backup_dir . '/' . $backup_filename;
        
        // Try to find mysqldump
        function find_mysqldump() {
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
        
        function run_mysqldump($mysqldump, $host, $db, $user, $pass, $charset, $outFile) {
            $tmpCnf = tempnam(sys_get_temp_dir(), 'mycnf_');
            $errFile = tempnam(sys_get_temp_dir(), 'mdump_err_');
            $ok = false;
            $err = '';
            $cnf = "[client]\nhost={$host}\nuser={$user}\npassword={$pass}\n";
            file_put_contents($tmpCnf, $cnf);
            
            $cmd = sprintf('"%s" --defaults-extra-file="%s" --single-transaction --skip-lock-tables --routines --events --triggers --set-gtid-purged=OFF --default-character-set=%s --no-tablespaces --databases "%s" > "%s" 2> "%s"',
                $mysqldump, $tmpCnf, escapeshellarg($charset), $db, $outFile, $errFile
            );
            
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
        
        function backtick($name) {
            return '`'.str_replace('`','``',$name).'`';
        }
        
        function php_dump_all($outFile) {
            $pdo = getDBConnection();
            $fh = fopen($outFile, 'wb');
            if (!$fh) throw new Exception("Cannot write to $outFile");
            
            // Header
            $header = "-- ================================================\n".
                      "-- Raimon Airways MySQL Backup\n".
                      "-- Generated: ".date('Y-m-d H:i:s')."\n".
                      "-- Host    : ".DB_HOST."\n".
                      "-- Database: ".DB_NAME."\n".
                      "-- User    : ".DB_USER."\n".
                      "-- Charset : ".DB_CHARSET."\n".
                      "-- ================================================\n\n".
                      "SET NAMES ".DB_CHARSET.";\n".
                      "SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n".
                      "SET @OLD_FK=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;\n\n";
            
            fwrite($fh, $header);
            
            // Tables
            $tables = [];
            $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
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
                    while ($c = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
                        $cols[] = $c['Field'];
                    }
                    $colList = implode(',', array_map('backtick', $cols));
                    
                    $batchSize = 500;
                    $batch = [];
                    $offset = 0;
                    
                    while (true) {
                        $stmt = $pdo->query("SELECT * FROM ".backtick($t)." LIMIT {$batchSize} OFFSET {$offset}");
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($rows)) break;
                        
                        foreach ($rows as $row) {
                            $vals = [];
                            foreach ($cols as $col) {
                                $val = $row[$col];
                                if ($val === null) {
                                    $vals[] = 'NULL';
                                } else {
                                    $val = str_replace(['\\', "\n", "\r", "\t", "\x00", "'"], ['\\\\', '\\n', '\\r', '\\t', '\\0', "\\'"], $val);
                                    $vals[] = "'".$val."'";
                                }
                            }
                            $batch[] = '('.implode(',', $vals).')';
                        }
                        
                        if (!empty($batch)) {
                            fwrite($fh, "INSERT INTO ".backtick($t)." (".$colList.") VALUES\n");
                            fwrite($fh, implode(",\n", $batch).";\n\n");
                            $batch = [];
                        }
                        
                        $offset += $batchSize;
                        if (count($rows) < $batchSize) break;
                    }
                }
            }
            
            // Routines
            $routines = $pdo->query("SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '".DB_NAME."'")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($routines)) {
                fwrite($fh, "--\n-- Routines\n--\n");
                foreach ($routines as $r) {
                    $type = $r['ROUTINE_TYPE'];
                    $name = $r['ROUTINE_NAME'];
                    $row = $pdo->query("SHOW CREATE {$type} ".backtick($name))->fetch(PDO::FETCH_ASSOC);
                    fwrite($fh, "DROP {$type} IF EXISTS ".backtick($name).";\n");
                    fwrite($fh, $row["Create {$type}"].';'."\n\n");
                }
            }
            
            // Triggers
            $triggers = $pdo->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = '".DB_NAME."'")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($triggers)) {
                fwrite($fh, "--\n-- Triggers\n--\n");
                foreach ($triggers as $t) {
                    $name = $t['TRIGGER_NAME'];
                    $row = $pdo->query("SHOW CREATE TRIGGER ".backtick($name))->fetch(PDO::FETCH_ASSOC);
                    fwrite($fh, "DROP TRIGGER IF EXISTS ".backtick($name).";\n");
                    fwrite($fh, $row['SQL Original Statement'].';'."\n\n");
                }
            }
            
            // Restore FK
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=@OLD_FK;\n");
            fwrite($fh, "SET SQL_MODE=@OLD_SQL_MODE;\n");
            
            fclose($fh);
        }
        
        $mysqldump = find_mysqldump();
        $usedFallback = false;
        $tmpDump = tempnam(sys_get_temp_dir(), 'dump_');
        
        if ($mysqldump) {
            [$ok, $err] = run_mysqldump($mysqldump, DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, $tmpDump);
            if (!$ok) {
                $usedFallback = true;
            }
        } else {
            $usedFallback = true;
        }
        
        if ($usedFallback) {
            php_dump_all($tmpDump);
            $final = file_get_contents($tmpDump);
            @unlink($tmpDump);
        } else {
            $header = "-- ================================================\n".
                      "-- Raimon Airways MySQL Backup\n".
                      "-- Generated: ".date('Y-m-d H:i:s')."\n".
                      "-- Host    : ".DB_HOST."\n".
                      "-- Database: ".DB_NAME."\n".
                      "-- User    : ".DB_USER."\n".
                      "-- Charset : ".DB_CHARSET."\n".
                      "-- ================================================\n\n";
            $body = file_get_contents($tmpDump);
            @unlink($tmpDump);
            $final = $header . $body;
        }
        
        if (file_put_contents($backup_path, $final) === false) {
            throw new Exception('Failed to write backup file.');
        }
        
        $backup_file = $backup_filename;
        $backup_size = number_format(filesize($backup_path));
        $message = "Database backup created successfully! File: {$backup_file} (Size: {$backup_size} bytes)";
        
    } catch (Exception $e) {
        $error = 'Failed to create backup: ' . $e->getMessage();
    }
}

// Get list of existing backups
$backups = [];
if (is_dir($backup_dir)) {
    $files = glob($backup_dir . '/backup_*.sql');
    if ($files) {
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Database - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Backup Database</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Create full database backup of all tables and data</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-database text-blue-500 text-xl"></i>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Database Backup</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800 dark:text-green-200"><?php echo htmlspecialchars($message); ?></p>
                                <?php if ($backup_file): ?>
                                    <div class="mt-2">
                                        <a href="<?php echo htmlspecialchars('/backup/' . $backup_file); ?>" 
                                           download
                                           class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200">
                                            <i class="fas fa-download mr-2"></i>
                                            Download Backup
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800 dark:text-red-200"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Create Backup Section -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                                <i class="fas fa-plus-circle mr-2 text-blue-500"></i>
                                Create New Backup
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Creates a complete backup of all tables, data, routines, triggers, and events
                            </p>
                        </div>
                    </div>
                    
                    <form method="POST" class="mt-4">
                        <input type="hidden" name="action" value="create_backup">
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            <i class="fas fa-database mr-2"></i>
                            Create Backup Now
                        </button>
                    </form>
                    
                    <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-md">
                        <h3 class="text-sm font-medium text-blue-900 dark:text-blue-200 mb-2">
                            <i class="fas fa-info-circle mr-2"></i>
                            Backup Information
                        </h3>
                        <ul class="text-xs text-blue-800 dark:text-blue-300 space-y-1">
                            <li>• Database: <strong><?php echo htmlspecialchars(DB_NAME); ?></strong></li>
                            <li>• Host: <strong><?php echo htmlspecialchars(DB_HOST); ?></strong></li>
                            <li>• Backup Format: SQL file (.sql)</li>
                            <li>• Filename Format: backup_YYYY-MM-DD_HH-MM-SS.sql</li>
                            <li>• Backup Location: /backup/</li>
                            <li>• Includes: All tables, data, routines, triggers, and events</li>
                        </ul>
                    </div>
                </div>

                <!-- Existing Backups List -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                            <i class="fas fa-list mr-2"></i>
                            Existing Backups
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            List of all database backup files (sorted by date, newest first)
                        </p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <?php if (empty($backups)): ?>
                            <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                                <p>No backup files found</p>
                            </div>
                        <?php else: ?>
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Filename</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Size</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php foreach ($backups as $backup): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <i class="fas fa-file-archive text-blue-500 mr-2"></i>
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($backup['filename']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo number_format($backup['size']); ?> bytes
                                                    (<?php echo number_format($backup['size'] / 1024, 2); ?> KB)
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($backup['date']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="<?php echo htmlspecialchars('/backup/' . $backup['filename']); ?>" 
                                                   download
                                                   class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-4">
                                                    <i class="fas fa-download mr-1"></i>
                                                    Download
                                                </a>
                                                <button onclick="deleteBackup('<?php echo htmlspecialchars($backup['filename']); ?>')" 
                                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    <i class="fas fa-trash mr-1"></i>
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete Backup</h3>
                    <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Are you sure you want to delete this backup file? This action cannot be undone.
                </p>
                <div class="flex justify-end space-x-3">
                    <button onclick="closeDeleteModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Cancel
                    </button>
                    <button onclick="confirmDeleteBackup()"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors duration-200">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let backupToDelete = '';
        
        function deleteBackup(filename) {
            backupToDelete = filename;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            backupToDelete = '';
        }
        
        function confirmDeleteBackup() {
            if (backupToDelete) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_backup">
                    <input type="hidden" name="filename" value="${backupToDelete}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>

