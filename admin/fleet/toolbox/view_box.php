<?php
require_once '../../../config.php';

// Check access - allow access via QR code for all logged-in users
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}

// Check if user has access to toolbox or view_box page
if (!checkPageAccessEnhanced('admin/fleet/toolbox/index.php') && !checkPageAccessEnhanced('admin/fleet/toolbox/view_box.php')) {
    // If no specific access, still allow if logged in (for QR code access)
    $current_user = getCurrentUser();
    if (!$current_user) {
        header('Location: /login.php');
        exit();
    }
}

$box_id = intval($_GET['id'] ?? 0);

if ($box_id <= 0) {
    header('Location: index.php');
    exit();
}

$db = getDBConnection();

// Fetch box details
$stmt = $db->prepare("SELECT * FROM boxes WHERE id = ?");
$stmt->execute([$box_id]);
$box = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$box) {
    header('Location: index.php');
    exit();
}

// Fetch tools for this box
$stmt = $db->prepare("SELECT * FROM tools WHERE box_id = ? ORDER BY name ASC");
$stmt->execute([$box_id]);
$tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Contents - <?php echo htmlspecialchars($box['name']); ?> - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="min-h-screen p-6">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6">
                <div class="flex items-center justify-between no-print">
                    <a href="index.php" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Toolbox
                    </a>
                    <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                </div>
                
                <div class="mt-4">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        <i class="fas fa-toolbox mr-2"></i><?php echo htmlspecialchars($box['name']); ?>
                    </h1>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                        <div class="flex items-center">
                            <i class="fas fa-hashtag mr-2"></i>
                            <span>Box #<?php echo htmlspecialchars($box['box_number']); ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-user mr-2"></i>
                            <span>Owner: <?php echo htmlspecialchars($box['owner']); ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-tools mr-2"></i>
                            <span><?php echo count($tools); ?> tool(s)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tools Table -->
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-wrench mr-2"></i>Tools in Box
                    </h2>
                </div>
                
                <?php if (empty($tools)): ?>
                    <div class="p-12 text-center">
                        <i class="fas fa-toolbox text-gray-300 dark:text-gray-600 text-5xl mb-4"></i>
                        <p class="text-gray-500 dark:text-gray-400">No tools in this box</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Image</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Unique Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tool Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Brand</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Model</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($tools as $index => $tool): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo $index + 1; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if (!empty($tool['image_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($tool['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($tool['name']); ?>"
                                                     class="w-16 h-16 object-cover rounded border border-gray-200 dark:border-gray-600"
                                                     onerror="this.style.display='none'">
                                            <?php else: ?>
                                                <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600 flex items-center justify-center">
                                                    <i class="fas fa-tool text-gray-400"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($tool['unique_number']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($tool['name']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                                <?php echo htmlspecialchars($tool['brand'] ?? '-'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                                <?php echo htmlspecialchars($tool['model'] ?? '-'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                <?php echo $tool['quantity'] ?? 1; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                                <?php echo htmlspecialchars($tool['description'] ?? '-'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

