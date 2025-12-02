<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/full_log/flight_log.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get log file path
$logFile = __DIR__ . '/flight_log.json';

// Read logs from file
$logs = [];
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    if (!empty($content)) {
        $logs = json_decode($content, true);
        if (!is_array($logs)) {
            $logs = [];
        }
    }
}

// Filter logs if search is provided
$searchTerm = $_GET['search'] ?? '';
$filteredLogs = $logs;

if (!empty($searchTerm)) {
    $searchTerm = strtolower(trim($searchTerm));
    $filteredLogs = array_filter($logs, function($log) use ($searchTerm) {
        // Search in flight ID, user name, username, changed fields
        $flightId = strval($log['flight_id'] ?? '');
        $userName = strtolower($log['user']['name'] ?? '');
        $username = strtolower($log['user']['username'] ?? '');
        $changedFields = implode(' ', $log['changed_fields'] ?? []);
        
        return (
            strpos($flightId, $searchTerm) !== false ||
            strpos($userName, $searchTerm) !== false ||
            strpos($username, $searchTerm) !== false ||
            strpos(strtolower($changedFields), $searchTerm) !== false
        );
    });
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$totalLogs = count($filteredLogs);
$totalPages = ceil($totalLogs / $perPage);
$offset = ($page - 1) * $perPage;
$paginatedLogs = array_slice($filteredLogs, $offset, $perPage);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Log - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Flight Log</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View all flight change logs and history</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-history text-blue-500 text-xl"></i>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Total Logs: <?php echo number_format($totalLogs); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Search Box -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 mb-6">
                    <form method="GET" class="flex items-center space-x-4">
                        <div class="flex-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                   placeholder="Search by Flight ID, User Name, Username, or Changed Fields..." 
                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-search mr-2"></i>
                            Search
                        </button>
                        <?php if (!empty($searchTerm)): ?>
                        <a href="flight_log.php" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <i class="fas fa-times mr-2"></i>
                            Clear
                        </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Logs Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <?php if (empty($paginatedLogs)): ?>
                            <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                                <p>No log entries found</p>
                            </div>
                        <?php else: ?>
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date & Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Changed Fields</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Changes</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php foreach ($paginatedLogs as $log): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($log['timestamp'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <a href="<?php echo getAbsolutePath('admin/flights/edit.php?id=' . $log['flight_id']); ?>" 
                                                   class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                    <?php echo htmlspecialchars($log['flight_id'] ?? 'N/A'); ?>
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($log['user']['name'] ?? 'N/A'); ?>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($log['user']['username'] ?? ''); ?>
                                                    <?php if (!empty($log['user']['role'])): ?>
                                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                            <?php echo htmlspecialchars(ucfirst($log['user']['role'])); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex flex-wrap gap-1">
                                                    <?php foreach ($log['changed_fields'] ?? [] as $field): ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                            <?php echo htmlspecialchars($field); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <button onclick="showChangesModal(<?php echo htmlspecialchars(json_encode($log)); ?>)" 
                                                        class="text-sm text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                    <i class="fas fa-eye mr-1"></i>
                                                    View Changes
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalLogs); ?> of <?php echo number_format($totalLogs); ?> entries
                            </div>
                            <div class="flex space-x-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" 
                                       class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" 
                                       class="px-3 py-2 text-sm font-medium <?php echo $i == $page ? 'text-white bg-blue-600' : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600'; ?> rounded-md">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>" 
                                       class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Changes Modal -->
    <div id="changesModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Flight Changes Details</h3>
                    <button onclick="closeChangesModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div id="changesContent" class="space-y-4">
                    <!-- Content will be populated by JavaScript -->
                </div>
                
                <div class="flex justify-end mt-6">
                    <button onclick="closeChangesModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showChangesModal(log) {
            const modal = document.getElementById('changesModal');
            const content = document.getElementById('changesContent');
            
            let html = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Flight ID</label>
                            <div class="text-sm text-gray-900 dark:text-white">
                                <a href="/admin/flights/edit.php?id=${log.flight_id}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">
                                    ${log.flight_id}
                                </a>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date & Time</label>
                            <div class="text-sm text-gray-900 dark:text-white">${log.timestamp}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">User</label>
                            <div class="text-sm text-gray-900 dark:text-white">${log.user.name || 'N/A'}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">${log.user.username || ''} (${log.user.role || ''})</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Changed Fields</label>
                            <div class="text-sm text-gray-900 dark:text-white">${log.changed_fields.length} field(s)</div>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Field Changes:</h4>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
            `;
            
            for (const [field, change] of Object.entries(log.changes)) {
                const oldValue = change.old === null || change.old === '' ? '<span class="text-gray-400 italic">(empty)</span>' : escapeHtml(String(change.old));
                const newValue = change.new === null || change.new === '' ? '<span class="text-gray-400 italic">(empty)</span>' : escapeHtml(String(change.new));
                
                html += `
                    <div class="border border-gray-200 dark:border-gray-700 rounded-md p-3">
                        <div class="font-medium text-sm text-gray-900 dark:text-white mb-2">${escapeHtml(field)}</div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Old Value:</div>
                                <div class="text-sm bg-red-50 dark:bg-red-900/20 p-2 rounded border border-red-200 dark:border-red-800 text-red-900 dark:text-red-200">${oldValue}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">New Value:</div>
                                <div class="text-sm bg-green-50 dark:bg-green-900/20 p-2 rounded border border-green-200 dark:border-green-800 text-green-900 dark:text-green-200">${newValue}</div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            html += `
                        </div>
                    </div>
                </div>
            `;
            
            content.innerHTML = html;
            modal.classList.remove('hidden');
        }
        
        function closeChangesModal() {
            document.getElementById('changesModal').classList.add('hidden');
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('changesModal');
            if (event.target === modal) {
                closeChangesModal();
            }
        }
    </script>
</body>
</html>

