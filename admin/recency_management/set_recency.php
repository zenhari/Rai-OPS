<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/recency_management/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle delete action
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $id = intval($_GET['delete']);
    $redirect_url = 'set_recency.php';
    
    // Preserve filter if exists
    $params = [];
    if (isset($_GET['department']) && !empty($_GET['department'])) {
        $params['department'] = $_GET['department'];
    }
    $params['deleted'] = '1';
    
    // Build query string
    if (!empty($params)) {
        $redirect_url .= '?' . http_build_query($params);
    }
    
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("DELETE FROM recency_items WHERE id = ?");
        if ($stmt->execute([$id])) {
            header('Location: ' . $redirect_url);
            exit();
        } else {
            $error = 'Failed to delete recency item.';
        }
    } catch (PDOException $e) {
        error_log("Error deleting recency item: " . $e->getMessage());
        $error = 'An error occurred while deleting.';
    }
}

// Check for success message from redirect
if (isset($_GET['deleted'])) {
    $message = 'Recency item deleted successfully.';
}

// Get filter parameter
$filter_department = isset($_GET['department']) && !empty($_GET['department']) ? $_GET['department'] : '';

// Get all recency items
$recency_items = [];
try {
    $db = getDBConnection();
    $stmt = $db->query("
        SELECT 
            ri.*,
            u.first_name,
            u.last_name
        FROM recency_items ri
        LEFT JOIN users u ON ri.created_by = u.id
        ORDER BY ri.created_at DESC
    ");
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode departments JSON and filter
    foreach ($all_items as &$item) {
        if (!empty($item['departments'])) {
            $item['departments'] = json_decode($item['departments'], true);
        } else {
            $item['departments'] = [];
        }
        
        // Filter by department if specified
        if (!empty($filter_department)) {
            if (!empty($item['departments']) && is_array($item['departments']) && in_array($filter_department, $item['departments'])) {
                $recency_items[] = $item;
            }
        } else {
            $recency_items[] = $item;
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching recency items: " . $e->getMessage());
    $error = 'Failed to load recency items.';
}

// Department list for display
$departments = [
    'General' => 'General',
    'Cabin Crew' => 'Cabin Crew',
    'CAMO' => 'CAMO',
    'Commanders' => 'Commanders',
    'Commercial' => 'Commercial',
    'Flight Operation Officer' => 'Flight Operation Officer',
    'Ground Operation' => 'Ground Operation',
    'IT' => 'IT',
    'Maintenance' => 'Maintenance',
    'Maintenance Logestic' => 'Maintenance Logestic',
    'Maintenance Store' => 'Maintenance Store',
    'Managers' => 'Managers',
    'Pilot' => 'Pilot',
    'SCM' => 'SCM',
    'Security' => 'Security',
    'Senior CCM' => 'Senior CCM',
    'STAFFs' => 'STAFFs'
];
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Recency's - <?php echo PROJECT_NAME; ?></title>
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
                                <i class="fas fa-list-check mr-2"></i>Set Recency's
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                View and manage all recency items
                            </p>
                        </div>
                        <div>
                            <a href="index.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <i class="fas fa-plus mr-2"></i>
                                Add New Recency Item
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <div class="w-full mx-auto">
                    <?php if ($message): ?>
                        <div class="mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-md">
                            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-md">
                            <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Filter Section -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
                        <form method="GET" action="" class="flex flex-wrap items-end gap-4">
                            <div class="flex-1 min-w-[200px]">
                                <label for="department_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fas fa-filter mr-1"></i>Filter by Department
                                </label>
                                <select id="department_filter" name="department" 
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                        onchange="this.form.submit()">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $key => $value): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($filter_department === $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($value); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <?php if (!empty($filter_department)): ?>
                                    <a href="?" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors duration-200">
                                        <i class="fas fa-times mr-2"></i>Clear Filter
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <?php if (!empty($filter_department)): ?>
                            <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                <i class="fas fa-info-circle mr-1"></i>
                                Showing <strong><?php echo count($recency_items); ?></strong> recency item(s) for 
                                <strong><?php echo htmlspecialchars($departments[$filter_department] ?? $filter_department); ?></strong>
                            </div>
                        <?php else: ?>
                            <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                <i class="fas fa-info-circle mr-1"></i>
                                Showing <strong><?php echo count($recency_items); ?></strong> recency item(s)
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recency Items Table -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Department(s)
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Period
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Created By
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Created At
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (empty($recency_items)): ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                <i class="fas fa-inbox text-2xl mb-2 block"></i>
                                                No recency items found. <a href="index.php" class="text-blue-600 dark:text-blue-400 hover:underline">Create one</a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recency_items as $item): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="text-sm text-gray-900 dark:text-white">
                                                        <?php 
                                                        $typeLabels = [
                                                            'HEAD' => 'Heading',
                                                            'TIME' => 'Timesheet Item',
                                                            'COMPOSITE' => 'Composite Item',
                                                            '' => 'Item'
                                                        ];
                                                        echo htmlspecialchars($typeLabels[$item['type'] ?? ''] ?? 'Item');
                                                        ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900 dark:text-white">
                                                        <?php 
                                                        if (!empty($item['departments']) && is_array($item['departments'])) {
                                                            $deptLabels = array_map(function($dept) use ($departments) {
                                                                return $departments[$dept] ?? $dept;
                                                            }, $item['departments']);
                                                            echo htmlspecialchars(implode(', ', $deptLabels));
                                                        } else {
                                                            echo '<span class="text-gray-400">No departments</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900 dark:text-white">
                                                        <?php 
                                                        if ($item['period'] > 0) {
                                                            $periodType = $item['period_type'] === 'M' ? 'months' : ($item['period_type'] === 'NA' ? 'N/A' : 'days');
                                                            echo htmlspecialchars($item['period'] . ' ' . $periodType);
                                                        } else {
                                                            echo '<span class="text-gray-400">-</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php if ($item['disabled']): ?>
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400">
                                                            Disabled
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                                                            Active
                                                        </span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">
                                                        <?php echo htmlspecialchars($item['default_status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900 dark:text-white">
                                                        <?php 
                                                        if ($item['first_name'] && $item['last_name']) {
                                                            echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']);
                                                        } else {
                                                            echo '<span class="text-gray-400">-</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo date('Y-m-d H:i', strtotime($item['created_at'])); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <div class="flex items-center justify-center space-x-2">
                                                        <a href="index.php?edit=<?php echo $item['id']; ?>" 
                                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                                                           title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="?delete=<?php echo $item['id']; ?><?php echo !empty($filter_department) ? '&department=' . urlencode($filter_department) : ''; ?>" 
                                                           class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                                           title="Delete"
                                                           onclick="return confirm('Are you sure you want to delete this recency item?');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

