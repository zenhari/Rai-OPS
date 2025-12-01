<?php
require_once '../../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/users/personnel_recency/index.php');

$current_user = getCurrentUser();

// Handle search and pagination
$search = [
    'first_name' => $_GET['first_name'] ?? '',
    'last_name' => $_GET['last_name'] ?? '',
    'name' => $_GET['name'] ?? '',
    'quick_search' => $_GET['quick_search'] ?? ''
];

$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 30;
$offset = ($page - 1) * $per_page;

$records = getAllPersonnelRecency($per_page, $offset, $search);
$records_count = getPersonnelRecencyCount($search);
$total_pages = ceil($records_count / $per_page);

// Handle actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $record_id = $_POST['record_id'] ?? '';
    
    switch ($action) {
        case 'delete_record':
            if (deletePersonnelRecency($record_id)) {
                $message = 'Personnel recency record deleted successfully.';
                $message_type = 'success';
                $records = getAllPersonnelRecency($per_page, $offset, $search); // Refresh the list with current search
            } else {
                $message = 'Failed to delete personnel recency record.';
                $message_type = 'error';
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnel Recency - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../../../assets/images/favicon.ico">
    
    <!-- Google Fonts - Roboto -->
    
    
    <link rel="stylesheet" href="/assets/css/roboto.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    
    <!-- Tailwind CSS -->
    <script src="/assets/js/tailwind.js"></script>
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- Include Sidebar -->
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-user-clock mr-2"></i>Personnel Recency
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Manage personnel recency records and certifications
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <!-- Add Record Button -->
                            <a href="add.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>
                                Add Record
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Search Form -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-search mr-2"></i>Search Personnel Recency
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="GET" class="space-y-4">
                            <!-- Quick Search -->
                            <div class="mb-4">
                                <label for="quick_search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fas fa-search mr-1"></i>Quick Search (All Fields)
                                </label>
                                <input type="text" id="quick_search" name="quick_search" 
                                       value="<?php echo htmlspecialchars($_GET['quick_search'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Search across all name fields...">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Searches in First Name, Last Name, and Name fields
                                </p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- First Name Search -->
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="fas fa-user mr-1"></i>First Name
                                    </label>
                                    <input type="text" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($search['first_name']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="First name...">
                                </div>
                                
                                <!-- Last Name Search -->
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="fas fa-user mr-1"></i>Last Name
                                    </label>
                                    <input type="text" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($search['last_name']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Last name...">
                                </div>
                                
                                <!-- Name Search -->
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="fas fa-tag mr-1"></i>Name
                                    </label>
                                    <input type="text" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($search['name']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Name field...">
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <button type="submit" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                        <i class="fas fa-search mr-2"></i>Search
                                    </button>
                                    
                                    <a href="index.php" 
                                       class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                        <i class="fas fa-times mr-2"></i>Clear
                                    </a>
                                </div>
                                
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    Showing <?php echo count($records); ?> of <?php echo $records_count; ?> records
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Permission Banner -->
                <?php include '../../../includes/permission_banner.php'; ?>
                
                <!-- Message Display -->
                <?php if (!empty($message)): ?>
                    <div class="mb-6 <?php echo $message_type == 'success' ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400'; ?> px-4 py-3 rounded-md">
                        <div class="flex">
                            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mt-0.5 mr-2"></i>
                            <span class="text-sm"><?php echo htmlspecialchars($message); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Records -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                                <i class="fas fa-list text-blue-600 dark:text-blue-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Records</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $records_count; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Expired Records -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 dark:bg-red-900">
                                <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Expired Records</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo count(array_filter($records, fn($r) => $r['Expires'] && strtotime($r['Expires']) < time())); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Expiring Soon -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900">
                                <i class="fas fa-clock text-yellow-600 dark:text-yellow-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Expiring Soon</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo count(array_filter($records, fn($r) => $r['Expires'] && strtotime($r['Expires']) > time() && strtotime($r['Expires']) < strtotime('+30 days'))); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Active Records -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Records</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo count(array_filter($records, fn($r) => !$r['Expires'] || strtotime($r['Expires']) > time())); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Records Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-list mr-2"></i>Personnel Recency Records
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Personnel
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Type Code
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Last Updated
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Expires
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Value
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Base
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($records as $record): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0">
                                                <div class="h-10 w-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                                    <i class="fas fa-user text-gray-600 dark:text-gray-300"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars(trim(($record['FirstName'] ?? '') . ' ' . ($record['LastName'] ?? ''))); ?>
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($record['Name'] ?? 'N/A'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($record['TypeCode'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo $record['LastUpdated'] ? date('M j, Y g:i A', strtotime($record['LastUpdated'])) : 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($record['Expires']): ?>
                                            <?php 
                                            $expiresTime = strtotime($record['Expires']);
                                            $isExpired = $expiresTime < time();
                                            $isExpiringSoon = $expiresTime > time() && $expiresTime < strtotime('+1 days');
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php 
                                                if ($isExpired) {
                                                    echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                                                } elseif ($isExpiringSoon) {
                                                    echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                                                } else {
                                                    echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                                                }
                                                ?>">
                                                <?php echo date('M j, Y', $expiresTime); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-400 dark:text-gray-500">No expiry</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo $record['Value'] ?? 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($record['BaseName'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <!-- View Button -->
                                            <button onclick="viewRecord(<?php echo $record['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <!-- Edit Button -->
                                            <a href="edit.php?id=<?php echo $record['id']; ?>" 
                                               class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                               title="Edit Record">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <!-- Delete Button -->
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="delete_record">
                                                <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                                <button type="submit" 
                                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                        onclick="return confirm('Are you sure you want to delete this record? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 mt-6">
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                                (<?php echo $records_count; ?> total records)
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <!-- Previous Page -->
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                       class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                        <i class="fas fa-chevron-left mr-1"></i>
                                        Previous
                                    </a>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-400 dark:text-gray-600 bg-gray-100 dark:bg-gray-800 cursor-not-allowed">
                                        <i class="fas fa-chevron-left mr-1"></i>
                                        Previous
                                    </span>
                                <?php endif; ?>
                                
                                <!-- Page Numbers -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                                       class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                        1
                                    </a>
                                    <?php if ($start_page > 2): ?>
                                        <span class="inline-flex items-center px-3 py-2 text-sm text-gray-500 dark:text-gray-400">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="inline-flex items-center px-3 py-2 border border-blue-500 text-sm font-medium rounded-md text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20">
                                            <?php echo $i; ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                           class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <span class="inline-flex items-center px-3 py-2 text-sm text-gray-500 dark:text-gray-400">...</span>
                                    <?php endif; ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" 
                                       class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                        <?php echo $total_pages; ?>
                                    </a>
                                <?php endif; ?>
                                
                                <!-- Next Page -->
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                       class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors duration-200">
                                        Next
                                        <i class="fas fa-chevron-right ml-1"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-400 dark:text-gray-600 bg-gray-100 dark:bg-gray-800 cursor-not-allowed">
                                        Next
                                        <i class="fas fa-chevron-right ml-1"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        // Dark mode functionality
        function toggleDarkMode() {
            const html = document.documentElement;
            const darkModeIcon = document.getElementById('dark-mode-icon');
            
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                darkModeIcon.className = 'fas fa-moon';
                localStorage.setItem('darkMode', 'false');
            } else {
                html.classList.add('dark');
                darkModeIcon.className = 'fas fa-sun';
                localStorage.setItem('darkMode', 'true');
            }
        }

        // Initialize dark mode
        function initDarkMode() {
            const savedDarkMode = localStorage.getItem('darkMode');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const darkModeIcon = document.getElementById('dark-mode-icon');
            
            if (savedDarkMode === 'true' || (savedDarkMode === null && systemPrefersDark)) {
                document.documentElement.classList.add('dark');
                darkModeIcon.className = 'fas fa-sun';
            } else {
                document.documentElement.classList.remove('dark');
                darkModeIcon.className = 'fas fa-moon';
            }
        }

        // Personnel recency functions
        function viewRecord(recordId) {
            console.log('Loading record details for ID:', recordId);
            
            // Show loading state
            const loadingModal = `
                <div id="viewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
                        <div class="text-center py-8">
                            <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
                            <p class="mt-2 text-gray-500">Loading record details...</p>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', loadingModal);
            
            // Fetch record details via AJAX
            fetch(`view_record.php?id=${recordId}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    // Remove loading modal
                    const modal = document.getElementById('viewModal');
                    if (modal) modal.remove();
                    
                    if (data.success) {
                        showViewModal(data.record);
                    } else {
                        alert('Error loading record: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Remove loading modal
                    const modal = document.getElementById('viewModal');
                    if (modal) modal.remove();
                    alert('Error loading record details: ' + error.message);
                });
        }

        function showViewModal(record) {
            // Format dates
            const formatDate = (dateString) => {
                if (!dateString) return 'N/A';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            };

            // Check if expired
            const isExpired = record.Expires ? new Date(record.Expires) < new Date() : false;
            const expiresIn30Days = record.Expires ? new Date(record.Expires) < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000) : false;

            // Create modal HTML
            const modalHtml = `
                <div id="viewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div class="relative top-4 mx-auto p-4 w-11/12 max-w-6xl shadow-2xl rounded-xl bg-white dark:bg-gray-800">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                    <i class="fas fa-user-check text-blue-600 dark:text-blue-400 text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Personnel Recency Details</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">${record.FirstName || ''} ${record.LastName || ''}</p>
                                </div>
                            </div>
                            <button onclick="closeViewModal()" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <!-- Status Banner -->
                        ${isExpired ? `
                            <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 mr-3"></i>
                                    <div>
                                        <h4 class="text-red-800 dark:text-red-200 font-medium">Expired Certificate</h4>
                                        <p class="text-red-600 dark:text-red-300 text-sm">This certificate expired on ${formatDate(record.Expires)}</p>
                                    </div>
                                </div>
                            </div>
                        ` : expiresIn30Days ? `
                            <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-clock text-yellow-600 dark:text-yellow-400 mr-3"></i>
                                    <div>
                                        <h4 class="text-yellow-800 dark:text-yellow-200 font-medium">Expiring Soon</h4>
                                        <p class="text-yellow-600 dark:text-yellow-300 text-sm">This certificate expires on ${formatDate(record.Expires)}</p>
                                    </div>
                                </div>
                            </div>
                        ` : ''}

                        <!-- Main Content -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Personnel Information Card -->
                            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6 border border-blue-200 dark:border-blue-800">
                                <div class="flex items-center mb-4">
                                    <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg mr-3">
                                        <i class="fas fa-user text-blue-600 dark:text-blue-400"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold text-blue-900 dark:text-blue-100">Personnel Information</h4>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center py-2 border-b border-blue-200 dark:border-blue-700">
                                        <span class="text-blue-700 dark:text-blue-300 font-medium">First Name</span>
                                        <span class="text-blue-900 dark:text-blue-100 font-semibold">${record.FirstName || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-blue-200 dark:border-blue-700">
                                        <span class="text-blue-700 dark:text-blue-300 font-medium">Last Name</span>
                                        <span class="text-blue-900 dark:text-blue-100 font-semibold">${record.LastName || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-blue-200 dark:border-blue-700">
                                        <span class="text-blue-700 dark:text-blue-300 font-medium">Full Name</span>
                                        <span class="text-blue-900 dark:text-blue-100 font-semibold">${record.Name || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2">
                                        <span class="text-blue-700 dark:text-blue-300 font-medium">Personnel ID</span>
                                        <span class="text-blue-900 dark:text-blue-100 font-semibold">${record.PersonnelID || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Recency Information Card -->
                            <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl p-6 border border-green-200 dark:border-green-800">
                                <div class="flex items-center mb-4">
                                    <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg mr-3">
                                        <i class="fas fa-certificate text-green-600 dark:text-green-400"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold text-green-900 dark:text-green-100">Recency Information</h4>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center py-2 border-b border-green-200 dark:border-green-700">
                                        <span class="text-green-700 dark:text-green-300 font-medium">Type Code</span>
                                        <span class="px-2 py-1 bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200 rounded-md text-sm font-semibold">${record.TypeCode || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-green-200 dark:border-green-700">
                                        <span class="text-green-700 dark:text-green-300 font-medium">Value</span>
                                        <span class="text-green-900 dark:text-green-100 font-semibold">${record.Value || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-green-200 dark:border-green-700">
                                        <span class="text-green-700 dark:text-green-300 font-medium">Last Updated</span>
                                        <span class="text-green-900 dark:text-green-100 font-semibold">${formatDate(record.LastUpdated)}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2">
                                        <span class="text-green-700 dark:text-green-300 font-medium">Expires</span>
                                        <span class="text-green-900 dark:text-green-100 font-semibold">${formatDate(record.Expires)}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Document Information Card -->
                            <div class="bg-gradient-to-br from-purple-50 to-violet-50 dark:from-purple-900/20 dark:to-violet-900/20 rounded-xl p-6 border border-purple-200 dark:border-purple-800">
                                <div class="flex items-center mb-4">
                                    <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg mr-3">
                                        <i class="fas fa-file-alt text-purple-600 dark:text-purple-400"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold text-purple-900 dark:text-purple-100">Document Information</h4>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center py-2 border-b border-purple-200 dark:border-purple-700">
                                        <span class="text-purple-700 dark:text-purple-300 font-medium">Document ID</span>
                                        <span class="text-purple-900 dark:text-purple-100 font-semibold">${record.DocID || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-purple-200 dark:border-purple-700">
                                        <span class="text-purple-700 dark:text-purple-300 font-medium">Document Name</span>
                                        <span class="text-purple-900 dark:text-purple-100 font-semibold truncate max-w-32">${record.DocName || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2">
                                        <span class="text-purple-700 dark:text-purple-300 font-medium">CF Master</span>
                                        <span class="px-2 py-1 ${record.CFMaster ? 'bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200' : 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200'} rounded-md text-sm font-semibold">${record.CFMaster ? 'Yes' : 'No'}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Base Information Card -->
                            <div class="bg-gradient-to-br from-orange-50 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/20 rounded-xl p-6 border border-orange-200 dark:border-orange-800">
                                <div class="flex items-center mb-4">
                                    <div class="p-2 bg-orange-100 dark:bg-orange-900 rounded-lg mr-3">
                                        <i class="fas fa-building text-orange-600 dark:text-orange-400"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold text-orange-900 dark:text-orange-100">Base Information</h4>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center py-2 border-b border-orange-200 dark:border-orange-700">
                                        <span class="text-orange-700 dark:text-orange-300 font-medium">Base Name</span>
                                        <span class="text-orange-900 dark:text-orange-100 font-semibold">${record.BaseName || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-orange-200 dark:border-orange-700">
                                        <span class="text-orange-700 dark:text-orange-300 font-medium">Base Code</span>
                                        <span class="px-2 py-1 bg-orange-100 dark:bg-orange-800 text-orange-800 dark:text-orange-200 rounded-md text-sm font-semibold">${record.BaseShortName || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b border-orange-200 dark:border-orange-700">
                                        <span class="text-orange-700 dark:text-orange-300 font-medium">Home Base ID</span>
                                        <span class="text-orange-900 dark:text-orange-100 font-semibold">${record.HomeBaseID || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2">
                                        <span class="text-orange-700 dark:text-orange-300 font-medium">Department</span>
                                        <span class="text-orange-900 dark:text-orange-100 font-semibold">${record.PrimaryDepartmentName || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information Card -->
                        <div class="mt-6 bg-gradient-to-br from-gray-50 to-slate-50 dark:from-gray-800 dark:to-slate-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center mb-4">
                                <div class="p-2 bg-gray-100 dark:bg-gray-700 rounded-lg mr-3">
                                    <i class="fas fa-info-circle text-gray-600 dark:text-gray-400"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Additional Information</h4>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-600">
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">Recency Personnel Item ID</span>
                                    <span class="text-gray-900 dark:text-gray-100 font-semibold">${record.RecencyPersonnelItemID || 'N/A'}</span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-600">
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">Recency Item ID</span>
                                    <span class="text-gray-900 dark:text-gray-100 font-semibold">${record.RecencyItemID || 'N/A'}</span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-600">
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">Home Department ID</span>
                                    <span class="text-gray-900 dark:text-gray-100 font-semibold">${record.HomeDepartmentID || 'N/A'}</span>
                                </div>
                                <div class="flex justify-between items-center py-2">
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">Integration Reference Code</span>
                                    <span class="text-gray-900 dark:text-gray-100 font-semibold">${record.IntegrationReferenceCode || 'N/A'}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Actions -->
                        <div class="flex justify-end space-x-3 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <button onclick="closeViewModal()" 
                                    class="px-6 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-lg transition-colors duration-200">
                                <i class="fas fa-times mr-2"></i>Close
                            </button>
                            <a href="edit.php?id=${record.id}" 
                               class="px-6 py-3 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors duration-200">
                                <i class="fas fa-edit mr-2"></i>Edit Record
                            </a>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }

        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            if (modal) {
                modal.remove();
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initDarkMode);
    </script>
</body>
</html>
