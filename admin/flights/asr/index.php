<?php
require_once '../../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/flights/asr/index.php');

$current_user = getCurrentUser();
$message = '';
$message_type = '';

// Handle search and pagination
$search = [
    'report_number' => $_GET['report_number'] ?? '',
    'aircraft_registration' => $_GET['aircraft_registration'] ?? '',
    'flight_number' => $_GET['flight_number'] ?? '',
    'status' => $_GET['status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'quick_search' => $_GET['quick_search'] ?? ''
];

$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get ASR reports
$reports = getASRReports($per_page, $offset, $search);
$reports_count = getASRReportsCount($search);
$total_pages = ceil($reports_count / $per_page);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $report_id = $_POST['report_id'] ?? '';
    
    switch ($action) {
        case 'delete_report':
            if (deleteASRReport($report_id)) {
                $message = 'ASR report deleted successfully.';
                $message_type = 'success';
                $reports = getASRReports($per_page, $offset, $search); // Refresh the list
            } else {
                $message = 'Failed to delete ASR report.';
                $message_type = 'error';
            }
            break;
            
        case 'change_status':
            $status = $_POST['status'] ?? '';
            if (updateASRReportStatus($report_id, $status)) {
                $message = 'ASR report status updated successfully.';
                $message_type = 'success';
                $reports = getASRReports($per_page, $offset, $search); // Refresh the list
            } else {
                $message = 'Failed to update ASR report status.';
                $message_type = 'error';
            }
            break;
    }
}

// Get status counts
$status_counts = [];
foreach ($reports as $report) {
    $status = $report['status'] ?? 'draft';
    $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Air Safety Reports (ASR) - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { 
            font-family: 'Vazirmatn', 'IRANSansX', 'Roboto', sans-serif; 
            line-height: 1.5;
        }
        
        /* Dark Mode Styles */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #111827;
                color: #E5E7EB;
            }
            
            .bg-white {
                background-color: #1F2937 !important;
                border: 1px solid #374151 !important;
            }
            
            .bg-gray-50 {
                background-color: #111827 !important;
            }
            
            .text-gray-900 {
                color: #E5E7EB !important;
            }
            
            .text-gray-700 {
                color: #E5E7EB !important;
            }
            
            .text-gray-600 {
                color: #9CA3AF !important;
            }
            
            .text-gray-500 {
                color: #9CA3AF !important;
            }
            
            .text-gray-400 {
                color: #9CA3AF !important;
            }
            
            .text-gray-300 {
                color: #D1D5DB !important;
            }
            
            .border-gray-200 {
                border-color: #374151 !important;
            }
            
            .border-gray-300 {
                border-color: #374151 !important;
            }
            
            .border-gray-600 {
                border-color: #4B5563 !important;
            }
            
            .shadow-sm {
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.3) !important;
            }
            
            .shadow {
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2) !important;
            }
            
            /* Buttons */
            .btn-primary {
                background-color: #3B82F6 !important;
                border-color: #3B82F6 !important;
                color: white !important;
                border-radius: 10px !important;
                font-weight: 500 !important;
                transition: all 150ms ease !important;
            }
            
            .btn-primary:hover {
                background-color: #2563EB !important;
                border-color: #2563EB !important;
            }
            
            .btn-secondary {
                background-color: transparent !important;
                border: 1px solid #374151 !important;
                color: #E5E7EB !important;
                border-radius: 8px !important;
                font-weight: 500 !important;
                transition: all 150ms ease !important;
            }
            
            .btn-secondary:hover {
                background-color: rgba(55, 65, 81, 0.1) !important;
                border-color: #4B5563 !important;
            }
            
            /* Input Fields */
            input, textarea, select {
                background-color: #1F2937 !important;
                border: 1px solid #374151 !important;
                color: #E5E7EB !important;
                border-radius: 8px !important;
            }
            
            input:focus, textarea:focus, select:focus {
                border-color: #3B82F6 !important;
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2) !important;
                outline: none !important;
            }
            
            input:hover, textarea:hover, select:hover {
                border-color: #4B5563 !important;
            }
            
            input::placeholder, textarea::placeholder {
                color: #9CA3AF !important;
            }
            
            /* Labels */
            label {
                color: #9CA3AF !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                margin-bottom: 8px !important;
            }
            
            /* Status Colors */
            .bg-blue-100 {
                background-color: rgba(59, 130, 246, 0.1) !important;
                color: #60A5FA !important;
            }
            
            .bg-yellow-100 {
                background-color: rgba(245, 158, 11, 0.1) !important;
                color: #FBBF24 !important;
            }
            
            .bg-purple-100 {
                background-color: rgba(147, 51, 234, 0.1) !important;
                color: #A78BFA !important;
            }
            
            .bg-green-100 {
                background-color: rgba(34, 197, 94, 0.1) !important;
                color: #4ADE80 !important;
            }
            
            .bg-red-100 {
                background-color: rgba(239, 68, 68, 0.1) !important;
                color: #F87171 !important;
            }
            
            /* Hover States */
            .hover\:bg-gray-50:hover {
                background-color: rgba(55, 65, 81, 0.1) !important;
            }
            
            .hover\:bg-gray-100:hover {
                background-color: rgba(55, 65, 81, 0.2) !important;
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <i class="fas fa-shield-alt mr-2"></i>Air Safety Reports (ASR)
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Manage air safety incident reports and investigations
                            </p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <a href="add.php" class="btn-primary inline-flex items-center px-4 py-2 text-sm font-medium">
                                <i class="fas fa-plus mr-2"></i>
                                New ASR Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 <?php echo $message_type === 'success' ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400'; ?> px-4 py-3 rounded-md">
                        <div class="flex">
                            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mt-0.5 mr-2"></i>
                            <span class="text-sm"><?php echo htmlspecialchars($message); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-file-alt text-blue-600 dark:text-blue-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Reports</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $reports_count; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-edit text-yellow-600 dark:text-yellow-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Draft</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $status_counts['draft'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-paper-plane text-blue-600 dark:text-blue-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Submitted</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $status_counts['submitted'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-search text-purple-600 dark:text-purple-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Under Review</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $status_counts['under_review'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $status_counts['approved'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6 mb-6">
                    <form method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Report Number
                                </label>
                                <input type="text" name="report_number" value="<?php echo htmlspecialchars($search['report_number']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Aircraft Registration
                                </label>
                                <input type="text" name="aircraft_registration" value="<?php echo htmlspecialchars($search['aircraft_registration']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Flight Number
                                </label>
                                <input type="text" name="flight_number" value="<?php echo htmlspecialchars($search['flight_number']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Status
                                </label>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">All Status</option>
                                    <option value="draft" <?php echo $search['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="submitted" <?php echo $search['status'] === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                    <option value="under_review" <?php echo $search['status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                    <option value="approved" <?php echo $search['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $search['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Date From
                                </label>
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($search['date_from']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Date To
                                </label>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($search['date_to']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <a href="index.php" class="btn-secondary px-4 py-2 text-sm font-medium">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                            <button type="submit" class="btn-primary px-4 py-2 text-sm font-medium">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Reports Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">ASR Reports</h2>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Report #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($reports)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                            <i class="fas fa-file-alt text-4xl mb-4"></i>
                                            <p class="text-lg font-medium">No ASR reports found</p>
                                            <p class="text-sm">Get started by creating your first air safety report.</p>
                                            <a href="add.php" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                                <i class="fas fa-plus mr-2"></i>Create ASR Report
                                            </a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reports as $report): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($report['report_number']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo date('M j, Y', strtotime($report['report_date'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($report['aircraft_type'] ?? 'N/A'); ?>
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($report['aircraft_registration'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($report['flight_number'] ?? 'N/A'); ?>
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($report['departure_airport'] ?? 'N/A'); ?> → <?php echo htmlspecialchars($report['destination_airport'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $status_colors = [
                                                    'draft' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                    'submitted' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                    'under_review' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                                    'approved' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                ];
                                                $status = $report['status'] ?? 'draft';
                                                $color_class = $status_colors[$status] ?? $status_colors['draft'];
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $color_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900 dark:text-white max-w-xs truncate">
                                                    <?php echo htmlspecialchars($report['short_description'] ?? 'No description'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <a href="view.php?id=<?php echo $report['id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $report['id']; ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="deleteReport(<?php echo $report['id']; ?>)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-6 flex items-center justify-between">
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $reports_count); ?> of <?php echo $reports_count; ?> results
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="px-3 py-2 text-sm font-medium <?php echo $i === $page ? 'text-white bg-blue-600 border border-blue-600' : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700'; ?> rounded-md">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900 rounded-full">
                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400"></i>
                </div>
                <div class="mt-2 text-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete ASR Report</h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Are you sure you want to delete this ASR report? This action cannot be undone.
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex justify-center space-x-3">
                    <button onclick="closeDeleteModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Cancel
                    </button>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="delete_report">
                        <input type="hidden" id="delete_report_id" name="report_id">
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors duration-200">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function deleteReport(reportId) {
            document.getElementById('delete_report_id').value = reportId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
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
