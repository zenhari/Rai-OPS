<?php
require_once '../../config.php';

// Check access
checkPageAccessWithRedirect('admin/full_log/activity_log.php');

$current_user = getCurrentUser();
$db = getDBConnection();

// Filters
$searchTerm = trim($_GET['search'] ?? '');
$filterUser = intval($_GET['user_id'] ?? 0);
$filterAction = $_GET['action_type'] ?? '';
$filterPage = trim($_GET['page_path'] ?? '');
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterRecordType = trim($_GET['record_type'] ?? '');

// Build query
$whereConditions = [];
$params = [];

if (!empty($searchTerm)) {
    $whereConditions[] = "(al.page_name LIKE ? OR al.page_path LIKE ? OR al.section LIKE ? OR al.field_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($filterUser > 0) {
    $whereConditions[] = "al.user_id = ?";
    $params[] = $filterUser;
}

if (!empty($filterAction)) {
    $whereConditions[] = "al.action_type = ?";
    $params[] = $filterAction;
}

if (!empty($filterPage)) {
    $whereConditions[] = "al.page_path LIKE ?";
    $params[] = "%$filterPage%";
}

if (!empty($filterDateFrom)) {
    $whereConditions[] = "DATE(al.created_at) >= ?";
    $params[] = $filterDateFrom;
}

if (!empty($filterDateTo)) {
    $whereConditions[] = "DATE(al.created_at) <= ?";
    $params[] = $filterDateTo;
}

if (!empty($filterRecordType)) {
    $whereConditions[] = "al.record_type = ?";
    $params[] = $filterRecordType;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id $whereClause");
$countStmt->execute($params);
$totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$totalPages = ceil($totalCount / $perPage);
$offset = ($page - 1) * $perPage;

// Get logs
// Note: LIMIT and OFFSET cannot use placeholders in MySQL/MariaDB, so we use intval to ensure safety
$perPage = intval($perPage);
$offset = intval($offset);
$sql = "SELECT al.*, 
        u.first_name, u.last_name, u.username, u.position
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $whereClause
        ORDER BY al.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for filter
$usersStmt = $db->query("SELECT id, first_name, last_name, username FROM users WHERE status = 'active' ORDER BY first_name, last_name");
$allUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique action types
$actionTypesStmt = $db->query("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type");
$actionTypes = $actionTypesStmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique record types
$recordTypesStmt = $db->query("SELECT DISTINCT record_type FROM activity_logs WHERE record_type IS NOT NULL ORDER BY record_type");
$recordTypes = $recordTypesStmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique page paths
$pagePathsStmt = $db->query("SELECT DISTINCT page_path FROM activity_logs ORDER BY page_path LIMIT 100");
$pagePaths = $pagePathsStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <i class="fas fa-history mr-2"></i>Activity Log
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Track all user activities, page views, and data changes
                            </p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                Total: <span class="font-semibold"><?php echo number_format($totalCount); ?></span> logs
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-filter mr-2"></i>Filters
                    </h2>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Search -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Search
                            </label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>"
                                   placeholder="Search in all fields..."
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <!-- User -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                User
                            </label>
                            <select name="user_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Users</option>
                                <?php foreach ($allUsers as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $filterUser == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Action Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Action Type
                            </label>
                            <select name="action_type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Actions</option>
                                <?php foreach ($actionTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filterAction == $type ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(htmlspecialchars($type)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Record Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Record Type
                            </label>
                            <select name="record_type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Types</option>
                                <?php foreach ($recordTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filterRecordType == $type ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(htmlspecialchars($type)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Page Path -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Page Path
                            </label>
                            <input type="text" name="page_path" value="<?php echo htmlspecialchars($filterPage); ?>"
                                   placeholder="e.g., admin/users/edit.php"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <!-- Date From -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Date From
                            </label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <!-- Date To -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Date To
                            </label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <!-- Buttons -->
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                            <a href="activity_log.php" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md transition-colors">
                                <i class="fas fa-redo mr-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Logs Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Page</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Section</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Changes</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IP Address</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No activity logs found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <?php
                                        $changes = null;
                                        if (!empty($log['changes_summary'])) {
                                            $changes = json_decode($log['changes_summary'], true);
                                        }
                                        ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')); ?>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($log['username'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $actionColors = [
                                                    'view' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                    'create' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    'update' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                    'delete' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                    'login' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                                    'logout' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                                    'export' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
                                                    'print' => 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
                                                ];
                                                $color = $actionColors[$log['action_type']] ?? 'bg-gray-100 text-gray-800';
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $color; ?>">
                                                    <?php echo ucfirst($log['action_type']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($log['page_name'] ?? $log['page_path']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs" title="<?php echo htmlspecialchars($log['page_path']); ?>">
                                                    <?php echo htmlspecialchars($log['page_path']); ?>
                                                </div>
                                                <?php if ($log['record_type'] && $log['record_id']): ?>
                                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                                        <?php echo htmlspecialchars($log['record_type']); ?> #<?php echo $log['record_id']; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo htmlspecialchars($log['section'] ?? '-'); ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php 
                                                $changeCount = 0;
                                                if ($changes && is_array($changes)) {
                                                    $changeCount = count($changes);
                                                } elseif ($log['field_name']) {
                                                    $changeCount = 1;
                                                }
                                                ?>
                                                
                                                <?php if ($changeCount > 0): ?>
                                                    <?php if ($changeCount <= 3): ?>
                                                        <!-- Show all changes if 3 or fewer -->
                                                        <?php if ($changes && is_array($changes)): ?>
                                                            <div class="text-xs space-y-1">
                                                                <?php foreach ($changes as $change): ?>
                                                                    <div class="bg-gray-50 dark:bg-gray-700 p-2 rounded">
                                                                        <span class="font-medium text-gray-700 dark:text-gray-300">
                                                                            <?php echo htmlspecialchars($change['field'] ?? 'N/A'); ?>:
                                                                        </span>
                                                                        <?php if (isset($change['old']) && isset($change['new'])): ?>
                                                                            <div class="mt-1">
                                                                                <span class="text-red-600 dark:text-red-400 line-through text-xs">
                                                                                    <?php echo htmlspecialchars($change['old']); ?>
                                                                                </span>
                                                                                <span class="text-green-600 dark:text-green-400 text-xs ml-2">
                                                                                    → <?php echo htmlspecialchars($change['new']); ?>
                                                                                </span>
                                                                            </div>
                                                                        <?php elseif (isset($change['new'])): ?>
                                                                            <span class="text-green-600 dark:text-green-400 text-xs ml-1">
                                                                                <?php echo htmlspecialchars($change['new']); ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php elseif ($log['field_name']): ?>
                                                            <div class="text-xs">
                                                                <span class="font-medium text-gray-700 dark:text-gray-300">
                                                                    <?php echo htmlspecialchars($log['field_name']); ?>:
                                                                </span>
                                                                <?php if ($log['old_value'] && $log['new_value']): ?>
                                                                    <div class="mt-1">
                                                                        <span class="text-red-600 dark:text-red-400 line-through">
                                                                            <?php echo htmlspecialchars($log['old_value']); ?>
                                                                        </span>
                                                                        <span class="text-green-600 dark:text-green-400 ml-2">
                                                                            → <?php echo htmlspecialchars($log['new_value']); ?>
                                                                        </span>
                                                                    </div>
                                                                <?php elseif ($log['new_value']): ?>
                                                                    <span class="text-green-600 dark:text-green-400 ml-1">
                                                                        <?php echo htmlspecialchars($log['new_value']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <!-- Show summary with expand button if more than 3 changes -->
                                                        <div class="text-xs">
                                                            <div class="mb-2">
                                                                <span class="font-medium text-gray-700 dark:text-gray-300">
                                                                    <?php echo $changeCount; ?> field(s) changed
                                                                </span>
                                                            </div>
                                                            <button onclick="toggleChangesDetails(<?php echo $log['id']; ?>)" 
                                                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-xs font-medium">
                                                                <i class="fas fa-chevron-down" id="icon_<?php echo $log['id']; ?>"></i>
                                                                <span id="text_<?php echo $log['id']; ?>">Show Details</span>
                                                            </button>
                                                            <div id="details_<?php echo $log['id']; ?>" class="hidden mt-2 space-y-1 max-h-64 overflow-y-auto">
                                                                <?php if ($changes && is_array($changes)): ?>
                                                                    <?php foreach ($changes as $change): ?>
                                                                        <div class="bg-gray-50 dark:bg-gray-700 p-2 rounded text-xs">
                                                                            <span class="font-medium text-gray-700 dark:text-gray-300">
                                                                                <?php echo htmlspecialchars($change['field'] ?? 'N/A'); ?>:
                                                                            </span>
                                                                            <?php if (isset($change['old']) && isset($change['new'])): ?>
                                                                                <div class="mt-1">
                                                                                    <span class="text-red-600 dark:text-red-400 line-through">
                                                                                        <?php echo htmlspecialchars($change['old']); ?>
                                                                                    </span>
                                                                                    <span class="text-green-600 dark:text-green-400 ml-2">
                                                                                        → <?php echo htmlspecialchars($change['new']); ?>
                                                                                    </span>
                                                                                </div>
                                                                            <?php elseif (isset($change['new'])): ?>
                                                                                <span class="text-green-600 dark:text-green-400 ml-1">
                                                                                    <?php echo htmlspecialchars($change['new']); ?>
                                                                                </span>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                                <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-t border-gray-200 dark:border-gray-600">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalCount); ?> of <?php echo number_format($totalCount); ?> results
                                </div>
                                <div class="flex space-x-2">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md <?php echo $i == $page ? 'bg-blue-600 text-white' : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            Next
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
</body>
</html>

