<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/fleet/toolbox/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

$db = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_box') {
        $name = trim($_POST['name'] ?? '');
        $owner = trim($_POST['owner'] ?? '');
        $box_number = intval($_POST['box_number'] ?? 0);
        
        if (empty($name) || empty($owner) || $box_number <= 0) {
            $error = 'All fields are required.';
        } else {
            // Check if box_number already exists
            $stmt = $db->prepare("SELECT id FROM boxes WHERE box_number = ?");
            $stmt->execute([$box_number]);
            if ($stmt->fetch()) {
                $error = 'Box number already exists.';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO boxes (name, owner, box_number) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $owner, $box_number]);
                    $message = 'Box added successfully.';
                } catch (PDOException $e) {
                    error_log("Error adding box: " . $e->getMessage());
                    $error = 'Failed to add box.';
                }
            }
        }
    } elseif ($action === 'edit_box') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $owner = trim($_POST['owner'] ?? '');
        $box_number = intval($_POST['box_number'] ?? 0);
        
        if (empty($name) || empty($owner) || $box_number <= 0) {
            $error = 'All fields are required.';
        } else {
            try {
                // Check if box_number already exists for another box
                $stmt = $db->prepare("SELECT id FROM boxes WHERE box_number = ? AND id != ?");
                $stmt->execute([$box_number, $id]);
                if ($stmt->fetch()) {
                    $error = 'Box number already exists.';
                } else {
                    $stmt = $db->prepare("UPDATE boxes SET name = ?, owner = ?, box_number = ? WHERE id = ?");
                    $stmt->execute([$name, $owner, $box_number, $id]);
                    $message = 'Box updated successfully.';
                }
            } catch (PDOException $e) {
                error_log("Error updating box: " . $e->getMessage());
                $error = 'Failed to update box.';
            }
        }
    } elseif ($action === 'delete_box') {
        $id = intval($_POST['id'] ?? 0);
        
        try {
            // Delete all tools in this box first
            $stmt = $db->prepare("DELETE FROM tools WHERE box_id = ?");
            $stmt->execute([$id]);
            
            // Delete the box
            $stmt = $db->prepare("DELETE FROM boxes WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Box deleted successfully.';
        } catch (PDOException $e) {
            error_log("Error deleting box: " . $e->getMessage());
            $error = 'Failed to delete box.';
        }
    } elseif ($action === 'add_tool') {
        $box_id = intval($_POST['box_id'] ?? 0);
        $unique_number = trim($_POST['unique_number'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 1);
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        
        if (empty($unique_number) || empty($name) || $box_id <= 0) {
            $error = 'Unique number, name, and box are required.';
        } else {
            // Check if unique_number already exists
            $stmt = $db->prepare("SELECT id FROM tools WHERE unique_number = ?");
            $stmt->execute([$unique_number]);
            if ($stmt->fetch()) {
                $error = 'Unique number already exists.';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO tools (unique_number, name, description, quantity, brand, model, image_url, box_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$unique_number, $name, $description, $quantity, $brand, $model, $image_url, $box_id]);
                    $message = 'Tool added successfully.';
                } catch (PDOException $e) {
                    error_log("Error adding tool: " . $e->getMessage());
                    $error = 'Failed to add tool.';
                }
            }
        }
    } elseif ($action === 'edit_tool') {
        $id = intval($_POST['id'] ?? 0);
        $unique_number = trim($_POST['unique_number'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 1);
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        
        if (empty($unique_number) || empty($name)) {
            $error = 'Unique number and name are required.';
        } else {
            try {
                // Check if unique_number already exists for another tool
                $stmt = $db->prepare("SELECT id FROM tools WHERE unique_number = ? AND id != ?");
                $stmt->execute([$unique_number, $id]);
                if ($stmt->fetch()) {
                    $error = 'Unique number already exists.';
                } else {
                    $stmt = $db->prepare("UPDATE tools SET unique_number = ?, name = ?, description = ?, quantity = ?, brand = ?, model = ?, image_url = ? WHERE id = ?");
                    $stmt->execute([$unique_number, $name, $description, $quantity, $brand, $model, $image_url, $id]);
                    $message = 'Tool updated successfully.';
                }
            } catch (PDOException $e) {
                error_log("Error updating tool: " . $e->getMessage());
                $error = 'Failed to update tool.';
            }
        }
    } elseif ($action === 'delete_tool') {
        $id = intval($_POST['id'] ?? 0);
        
        try {
            $stmt = $db->prepare("DELETE FROM tools WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Tool deleted successfully.';
        } catch (PDOException $e) {
            error_log("Error deleting tool: " . $e->getMessage());
            $error = 'Failed to delete tool.';
        }
    } elseif ($action === 'move_tool') {
        $tool_id = intval($_POST['tool_id'] ?? 0);
        $new_box_id = intval($_POST['new_box_id'] ?? 0);
        
        if ($tool_id <= 0 || $new_box_id <= 0) {
            $error = 'Invalid tool or box selection.';
        } else {
            try {
                $stmt = $db->prepare("UPDATE tools SET box_id = ? WHERE id = ?");
                $stmt->execute([$new_box_id, $tool_id]);
                $message = 'Tool moved successfully.';
            } catch (PDOException $e) {
                error_log("Error moving tool: " . $e->getMessage());
                $error = 'Failed to move tool.';
            }
        }
    }
}

// Fetch all boxes with tool counts
$stmt = $db->query("
    SELECT b.*, 
           COUNT(t.id) as tool_count
    FROM boxes b
    LEFT JOIN tools t ON b.id = t.box_id
    GROUP BY b.id
    ORDER BY b.box_number ASC
");
$boxes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all boxes for dropdowns
$stmt = $db->query("SELECT id, name, box_number FROM boxes ORDER BY box_number ASC");
$allBoxes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get dynamic base URL for QR codes (using base_url() function)
$dynamicBaseUrl = base_url();
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toolbox - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        
        /* Custom Scrollbar */
        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }
        
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f7fafc;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 3px;
            transition: background-color 0.2s ease;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }
        
        /* Dark mode scrollbar */
        .dark .custom-scrollbar {
            scrollbar-color: #4a5568 #2d3748;
        }
        
        .dark .custom-scrollbar::-webkit-scrollbar-track {
            background: #2d3748;
        }
        
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #4a5568;
        }
        
        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #718096;
        }
        
        /* Smooth scrolling */
        .custom-scrollbar {
            scroll-behavior: smooth;
        }
        
        /* Table row hover effect */
        .tool-row {
            transition: all 0.2s ease;
        }
        
        .tool-row:hover {
            transform: translateX(2px);
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <i class="fas fa-toolbox mr-2"></i>Toolbox
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Manage toolboxes and tools
                            </p>
                        </div>
                        <div>
                            <button onclick="openAddBoxModal()" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>Add Box
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <div class="w-full">
                    <?php if ($message): ?>
                        <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-md">
                            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-md">
                            <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Boxes Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($boxes as $box): ?>
                            <?php
                            // Fetch tools for this box
                            $stmt = $db->prepare("SELECT * FROM tools WHERE box_id = ? ORDER BY name ASC");
                            $stmt->execute([$box['id']]);
                            $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden flex flex-col h-full">
                                <!-- Box Header -->
                                <div class="bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 px-4 py-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1 min-w-0">
                                            <h3 class="text-lg font-semibold text-white truncate">
                                                <?php echo htmlspecialchars($box['name']); ?>
                                            </h3>
                                            <div class="flex items-center space-x-2 mt-1">
                                                <span class="text-xs text-blue-100 bg-blue-500/30 px-2 py-0.5 rounded">
                                                    #<?php echo htmlspecialchars($box['box_number']); ?>
                                                </span>
                                                <span class="text-xs text-blue-100">
                                                    <i class="fas fa-tools mr-1"></i><?php echo count($tools); ?> tool(s)
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2 ml-2">
                                            <!-- QR Code -->
                                            <div class="relative group">
                                                <button onclick="openQRCodeModal(<?php echo $box['id']; ?>)" 
                                                        class="text-white hover:text-blue-200 transition-colors p-1 rounded hover:bg-blue-500/30"
                                                        title="View QR Code">
                                                    <i class="fas fa-qrcode text-lg"></i>
                                                </button>
                                                <div class="absolute right-0 top-full mt-2 bg-white dark:bg-gray-800 rounded-lg shadow-xl p-3 hidden group-hover:block z-50 border border-gray-200 dark:border-gray-700">
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1 text-center">Scan to view contents</div>
                                                    <img src="<?php 
                                                        $qrUrl = $dynamicBaseUrl . 'admin/fleet/toolbox/view_box.php?id=' . $box['id'];
                                                        $qrApiBase = 'http://portal.raimonairways.net/raimon-cer/qr_api/qrapi.php';
                                                        echo $qrApiBase . '?' . http_build_query(['size' => 150, 'text' => $qrUrl]);
                                                    ?>" 
                                                         alt="QR Code" 
                                                         class="w-32 h-32 border border-gray-200 dark:border-gray-600 rounded">
                                                </div>
                                            </div>
                                            <button onclick="openEditBoxModal(<?php echo $box['id']; ?>, '<?php echo htmlspecialchars(addslashes($box['name'])); ?>', '<?php echo htmlspecialchars(addslashes($box['owner'])); ?>', <?php echo $box['box_number']; ?>)" 
                                                    class="text-white hover:text-blue-200 transition-colors p-1 rounded hover:bg-blue-500/30"
                                                    title="Edit Box">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="if(confirm('Are you sure you want to delete this box and all its tools?')) { deleteBox(<?php echo $box['id']; ?>); }" 
                                                    class="text-white hover:text-red-200 transition-colors p-1 rounded hover:bg-red-500/30"
                                                    title="Delete Box">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-xs text-blue-100 mt-2 flex items-center">
                                        <i class="fas fa-user mr-1"></i>
                                        <span class="truncate"><?php echo htmlspecialchars($box['owner']); ?></span>
                                    </p>
                                </div>

                                <!-- Tools Section -->
                                <div class="flex-1 flex flex-col p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center">
                                            <i class="fas fa-wrench mr-2 text-blue-600 dark:text-blue-400"></i>Tools
                                        </h4>
                                        <button onclick="openAddToolModal(<?php echo $box['id']; ?>)" 
                                                class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 rounded-md transition-colors">
                                            <i class="fas fa-plus mr-1"></i>Add
                                        </button>
                                    </div>
                                    
                                    <?php if (empty($tools)): ?>
                                        <div class="flex-1 flex items-center justify-center py-8">
                                            <div class="text-center">
                                                <i class="fas fa-toolbox text-gray-300 dark:text-gray-600 text-4xl mb-2"></i>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">No tools in this box</p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Search Box for Tools -->
                                        <div class="mb-3">
                                            <div class="relative">
                                                <input type="text" 
                                                       id="search_tools_<?php echo $box['id']; ?>" 
                                                       placeholder="Search tools..." 
                                                       onkeyup="filterTools(<?php echo $box['id']; ?>, this.value)"
                                                       class="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                <i class="fas fa-search absolute left-2.5 top-2.5 text-gray-400 text-sm"></i>
                                            </div>
                                        </div>
                                        
                                        <!-- Tools Table -->
                                        <div class="flex-1 overflow-hidden">
                                            <div class="overflow-y-auto max-h-96 custom-scrollbar" style="max-height: 400px;">
                                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                    <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                                        <tr>
                                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tool</th>
                                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Details</th>
                                                            <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-20">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="tools_list_<?php echo $box['id']; ?>" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                        <?php foreach ($tools as $tool): ?>
                                                            <tr class="tool-row hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" 
                                                                data-tool-name="<?php echo strtolower(htmlspecialchars($tool['name'])); ?>"
                                                                data-tool-number="<?php echo strtolower(htmlspecialchars($tool['unique_number'])); ?>"
                                                                data-tool-brand="<?php echo strtolower(htmlspecialchars($tool['brand'] ?? '')); ?>"
                                                                data-tool-model="<?php echo strtolower(htmlspecialchars($tool['model'] ?? '')); ?>">
                                                                <td class="px-2 py-2 whitespace-nowrap">
                                                                    <div class="flex items-center space-x-2">
                                                                        <?php if (!empty($tool['image_url'])): ?>
                                                                            <img src="<?php echo htmlspecialchars($tool['image_url']); ?>" 
                                                                                 alt="<?php echo htmlspecialchars($tool['name']); ?>"
                                                                                 class="w-10 h-10 object-cover rounded border border-gray-200 dark:border-gray-600"
                                                                                 onerror="this.style.display='none'">
                                                                        <?php else: ?>
                                                                            <div class="w-10 h-10 bg-gray-100 dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600 flex items-center justify-center">
                                                                                <i class="fas fa-tool text-gray-400 text-sm"></i>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <div class="min-w-0">
                                                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-[120px]" title="<?php echo htmlspecialchars($tool['name']); ?>">
                                                                                <?php echo htmlspecialchars($tool['name']); ?>
                                                                            </p>
                                                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[120px]">
                                                                                #<?php echo htmlspecialchars($tool['unique_number']); ?>
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="px-2 py-2">
                                                                    <div class="text-xs text-gray-600 dark:text-gray-400">
                                                                        <?php if ($tool['quantity'] > 1): ?>
                                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 mr-1">
                                                                                Qty: <?php echo $tool['quantity']; ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($tool['brand']) || !empty($tool['model'])): ?>
                                                                            <p class="truncate max-w-[150px]" title="<?php echo htmlspecialchars(trim(($tool['brand'] ?? '') . ' ' . ($tool['model'] ?? ''))); ?>">
                                                                                <?php echo htmlspecialchars(trim(($tool['brand'] ?? '') . ' ' . ($tool['model'] ?? ''))); ?>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($tool['description'])): ?>
                                                                            <p class="text-gray-500 dark:text-gray-500 truncate max-w-[150px] mt-0.5" title="<?php echo htmlspecialchars($tool['description']); ?>">
                                                                                <?php echo htmlspecialchars($tool['description']); ?>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                                <td class="px-2 py-2 whitespace-nowrap text-center">
                                                                    <div class="flex items-center justify-center space-x-1">
                                                                        <button onclick="openToolDetailsModal(<?php echo $tool['id']; ?>, '<?php echo htmlspecialchars(addslashes($tool['unique_number'])); ?>', '<?php echo htmlspecialchars(addslashes($tool['name'])); ?>', '<?php echo htmlspecialchars(addslashes($tool['description'] ?? '')); ?>', <?php echo $tool['quantity']; ?>, '<?php echo htmlspecialchars(addslashes($tool['brand'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($tool['model'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($tool['image_url'] ?? '')); ?>')" 
                                                                                class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 p-1 rounded hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors"
                                                                                title="View Details">
                                                                            <i class="fas fa-eye text-xs"></i>
                                                                        </button>
                                                                        <button onclick="openEditToolModal(<?php echo $tool['id']; ?>, '<?php echo htmlspecialchars(addslashes($tool['unique_number'])); ?>', '<?php echo htmlspecialchars(addslashes($tool['name'])); ?>', '<?php echo htmlspecialchars(addslashes($tool['description'] ?? '')); ?>', <?php echo $tool['quantity']; ?>, '<?php echo htmlspecialchars(addslashes($tool['brand'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($tool['model'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($tool['image_url'] ?? '')); ?>', <?php echo $tool['box_id']; ?>)" 
                                                                                class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 p-1 rounded hover:bg-green-50 dark:hover:bg-green-900/30 transition-colors"
                                                                                title="Edit">
                                                                            <i class="fas fa-edit text-xs"></i>
                                                                        </button>
                                                                        <button onclick="openMoveToolModal(<?php echo $tool['id']; ?>, '<?php echo htmlspecialchars(addslashes($tool['name'])); ?>', <?php echo $tool['box_id']; ?>)" 
                                                                                class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-300 p-1 rounded hover:bg-yellow-50 dark:hover:bg-yellow-900/30 transition-colors"
                                                                                title="Move">
                                                                            <i class="fas fa-exchange-alt text-xs"></i>
                                                                        </button>
                                                                        <button onclick="if(confirm('Are you sure you want to delete this tool?')) { deleteTool(<?php echo $tool['id']; ?>); }" 
                                                                                class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors"
                                                                                title="Delete">
                                                                            <i class="fas fa-trash text-xs"></i>
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 text-center">
                                            <span id="tools_count_<?php echo $box['id']; ?>"><?php echo count($tools); ?></span> tool(s) shown
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($boxes)): ?>
                            <div class="col-span-full text-center py-12">
                                <i class="fas fa-toolbox text-gray-400 text-5xl mb-4"></i>
                                <p class="text-gray-500 dark:text-gray-400">No boxes found. Create your first box to get started.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Box Modal -->
    <div id="addBoxModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add New Box</h3>
                    <button onclick="closeAddBoxModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_box">
                    
                    <div>
                        <label for="add_box_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Box Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="add_box_name" name="name" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="add_box_owner" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Owner <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="add_box_owner" name="owner" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="add_box_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Box Number <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="add_box_number" name="box_number" required min="1"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddBoxModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Add Box
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Box Modal -->
    <div id="editBoxModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Box</h3>
                    <button onclick="closeEditBoxModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="edit_box">
                    <input type="hidden" id="edit_box_id" name="id">
                    
                    <div>
                        <label for="edit_box_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Box Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_box_name" name="name" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_box_owner" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Owner <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_box_owner" name="owner" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_box_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Box Number <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="edit_box_number" name="box_number" required min="1"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditBoxModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Update Box
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Tool Modal -->
    <div id="addToolModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add New Tool</h3>
                    <button onclick="closeAddToolModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_tool">
                    <input type="hidden" id="add_tool_box_id" name="box_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="add_tool_unique_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Unique Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="add_tool_unique_number" name="unique_number" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="add_tool_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Tool Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="add_tool_name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="add_tool_quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Quantity
                            </label>
                            <input type="number" id="add_tool_quantity" name="quantity" min="1" value="1"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="add_tool_brand" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Brand
                            </label>
                            <input type="text" id="add_tool_brand" name="brand"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="add_tool_model" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Model
                            </label>
                            <input type="text" id="add_tool_model" name="model"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="add_tool_image_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Image URL
                            </label>
                            <input type="url" id="add_tool_image_url" name="image_url"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    
                    <div>
                        <label for="add_tool_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Description
                        </label>
                        <textarea id="add_tool_description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddToolModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Add Tool
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Tool Modal -->
    <div id="editToolModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Tool</h3>
                    <button onclick="closeEditToolModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="edit_tool">
                    <input type="hidden" id="edit_tool_id" name="id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_tool_unique_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Unique Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="edit_tool_unique_number" name="unique_number" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="edit_tool_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Tool Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="edit_tool_name" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="edit_tool_quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Quantity
                            </label>
                            <input type="number" id="edit_tool_quantity" name="quantity" min="1"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="edit_tool_brand" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Brand
                            </label>
                            <input type="text" id="edit_tool_brand" name="brand"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="edit_tool_model" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Model
                            </label>
                            <input type="text" id="edit_tool_model" name="model"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="edit_tool_image_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Image URL
                            </label>
                            <input type="url" id="edit_tool_image_url" name="image_url"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    
                    <div>
                        <label for="edit_tool_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Description
                        </label>
                        <textarea id="edit_tool_description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditToolModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Update Tool
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Move Tool Modal -->
    <div id="moveToolModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Move Tool</h3>
                    <button onclick="closeMoveToolModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="move_tool">
                    <input type="hidden" id="move_tool_id" name="tool_id">
                    
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                            Tool: <span id="move_tool_name" class="font-medium text-gray-900 dark:text-white"></span>
                        </p>
                    </div>
                    
                    <div>
                        <label for="move_new_box_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Move to Box <span class="text-red-500">*</span>
                        </label>
                        <select id="move_new_box_id" name="new_box_id" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">-- Select Box --</option>
                            <?php foreach ($allBoxes as $box): ?>
                                <option value="<?php echo $box['id']; ?>">
                                    <?php echo htmlspecialchars($box['name']); ?> (#<?php echo $box['box_number']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeMoveToolModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200">
                            Move Tool
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Box Modals
        function openAddBoxModal() {
            document.getElementById('addBoxModal').classList.remove('hidden');
        }

        function closeAddBoxModal() {
            document.getElementById('addBoxModal').classList.add('hidden');
            document.getElementById('addBoxModal').querySelector('form').reset();
        }

        function openEditBoxModal(id, name, owner, boxNumber) {
            document.getElementById('edit_box_id').value = id;
            document.getElementById('edit_box_name').value = name;
            document.getElementById('edit_box_owner').value = owner;
            document.getElementById('edit_box_number').value = boxNumber;
            document.getElementById('editBoxModal').classList.remove('hidden');
        }

        function closeEditBoxModal() {
            document.getElementById('editBoxModal').classList.add('hidden');
        }

        function deleteBox(id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_box">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Tool Modals
        function openAddToolModal(boxId) {
            document.getElementById('add_tool_box_id').value = boxId;
            document.getElementById('addToolModal').classList.remove('hidden');
        }

        function closeAddToolModal() {
            document.getElementById('addToolModal').classList.add('hidden');
            document.getElementById('addToolModal').querySelector('form').reset();
        }

        function openEditToolModal(id, uniqueNumber, name, description, quantity, brand, model, imageUrl, boxId) {
            document.getElementById('edit_tool_id').value = id;
            document.getElementById('edit_tool_unique_number').value = uniqueNumber;
            document.getElementById('edit_tool_name').value = name;
            document.getElementById('edit_tool_description').value = description;
            document.getElementById('edit_tool_quantity').value = quantity;
            document.getElementById('edit_tool_brand').value = brand;
            document.getElementById('edit_tool_model').value = model;
            document.getElementById('edit_tool_image_url').value = imageUrl;
            document.getElementById('editToolModal').classList.remove('hidden');
        }

        function closeEditToolModal() {
            document.getElementById('editToolModal').classList.add('hidden');
        }

        function deleteTool(id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_tool">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function openMoveToolModal(toolId, toolName, currentBoxId) {
            document.getElementById('move_tool_id').value = toolId;
            document.getElementById('move_tool_name').textContent = toolName;
            const select = document.getElementById('move_new_box_id');
            // Remove current box from options
            Array.from(select.options).forEach(option => {
                if (option.value == currentBoxId) {
                    option.disabled = true;
                } else {
                    option.disabled = false;
                }
            });
            select.value = '';
            document.getElementById('moveToolModal').classList.remove('hidden');
        }

        function closeMoveToolModal() {
            document.getElementById('moveToolModal').classList.add('hidden');
        }

        // Filter Tools Function
        function filterTools(boxId, searchTerm) {
            const searchLower = searchTerm.toLowerCase().trim();
            const rows = document.querySelectorAll(`#tools_list_${boxId} .tool-row`);
            let visibleCount = 0;
            
            rows.forEach(row => {
                const toolName = row.getAttribute('data-tool-name') || '';
                const toolNumber = row.getAttribute('data-tool-number') || '';
                const toolBrand = row.getAttribute('data-tool-brand') || '';
                const toolModel = row.getAttribute('data-tool-model') || '';
                
                if (searchTerm === '' || 
                    toolName.includes(searchLower) || 
                    toolNumber.includes(searchLower) ||
                    toolBrand.includes(searchLower) ||
                    toolModel.includes(searchLower)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update count
            const countElement = document.getElementById(`tools_count_${boxId}`);
            if (countElement) {
                countElement.textContent = `${visibleCount} tool(s) shown`;
            }
        }
        
        // Tool Details Modal
        function openToolDetailsModal(id, uniqueNumber, name, description, quantity, brand, model, imageUrl) {
            document.getElementById('detail_tool_unique_number').textContent = uniqueNumber;
            document.getElementById('detail_tool_name').textContent = name;
            document.getElementById('detail_tool_quantity').textContent = quantity || '1';
            document.getElementById('detail_tool_brand').textContent = brand || '-';
            document.getElementById('detail_tool_model').textContent = model || '-';
            document.getElementById('detail_tool_description').textContent = description || '-';
            document.getElementById('detail_tool_image_url').textContent = imageUrl || '-';
            
            const imageElement = document.getElementById('detail_tool_image');
            const placeholderElement = document.getElementById('detail_tool_image_placeholder');
            
            if (imageUrl && imageUrl.trim() !== '') {
                imageElement.src = imageUrl;
                imageElement.classList.remove('hidden');
                placeholderElement.classList.add('hidden');
                imageElement.onerror = function() {
                    this.classList.add('hidden');
                    placeholderElement.classList.remove('hidden');
                };
            } else {
                imageElement.classList.add('hidden');
                placeholderElement.classList.remove('hidden');
            }
            
            document.getElementById('toolDetailsModal').classList.remove('hidden');
        }
        
        function closeToolDetailsModal() {
            document.getElementById('toolDetailsModal').classList.add('hidden');
        }
        
        // QR Code Modal
        function openQRCodeModal(boxId) {
            const qrUrl = '<?php echo $dynamicBaseUrl; ?>admin/fleet/toolbox/view_box.php?id=' + boxId;
            const qrApiBase = 'http://portal.raimonairways.net/raimon-cer/qr_api/qrapi.php';
            const qrImageUrl = qrApiBase + '?' + new URLSearchParams({
                size: 300,
                text: qrUrl
            });
            
            document.getElementById('qr_code_image').src = qrImageUrl;
            document.getElementById('qr_code_url').textContent = qrUrl;
            document.getElementById('qr_code_url_link').href = qrUrl;
            document.getElementById('qrCodeModal').classList.remove('hidden');
        }
        
        function closeQRCodeModal() {
            document.getElementById('qrCodeModal').classList.add('hidden');
        }
        
        function downloadQRCode() {
            const img = document.getElementById('qr_code_image');
            const link = document.createElement('a');
            link.href = img.src;
            link.download = 'box_qr_code.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['addBoxModal', 'editBoxModal', 'addToolModal', 'editToolModal', 'moveToolModal', 'toolDetailsModal', 'qrCodeModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    if (modalId === 'addBoxModal') closeAddBoxModal();
                    else if (modalId === 'editBoxModal') closeEditBoxModal();
                    else if (modalId === 'addToolModal') closeAddToolModal();
                    else if (modalId === 'editToolModal') closeEditToolModal();
                    else if (modalId === 'moveToolModal') closeMoveToolModal();
                    else if (modalId === 'toolDetailsModal') closeToolDetailsModal();
                    else if (modalId === 'qrCodeModal') closeQRCodeModal();
                }
            });
        }
    </script>
    
    <!-- QR Code Modal -->
    <div id="qrCodeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Box QR Code</h3>
                    <button onclick="closeQRCodeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-center bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <img id="qr_code_image" src="" alt="QR Code" 
                             class="w-64 h-64 border-2 border-gray-200 dark:border-gray-600 rounded">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">URL</label>
                        <div class="flex items-center space-x-2">
                            <p id="qr_code_url" class="text-xs text-gray-600 dark:text-gray-400 break-all flex-1"></p>
                            <a id="qr_code_url_link" href="#" target="_blank" 
                               class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                               title="Open in new tab">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button onclick="downloadQRCode()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            <i class="fas fa-download mr-2"></i>Download
                        </button>
                        <button onclick="closeQRCodeModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tool Details Modal -->
    <div id="toolDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Tool Details</h3>
                    <button onclick="closeToolDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-center">
                        <img id="detail_tool_image" src="" alt="Tool Image" 
                             class="w-32 h-32 object-cover rounded-lg border-2 border-gray-200 dark:border-gray-700 hidden">
                        <div id="detail_tool_image_placeholder" class="w-32 h-32 bg-gray-100 dark:bg-gray-700 rounded-lg border-2 border-gray-200 dark:border-gray-700 flex items-center justify-center">
                            <i class="fas fa-tool text-gray-400 text-3xl"></i>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Unique Number</label>
                            <p id="detail_tool_unique_number" class="text-sm font-semibold text-gray-900 dark:text-white"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Tool Name</label>
                            <p id="detail_tool_name" class="text-sm font-semibold text-gray-900 dark:text-white"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Quantity</label>
                            <p id="detail_tool_quantity" class="text-sm font-semibold text-gray-900 dark:text-white"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Brand</label>
                            <p id="detail_tool_brand" class="text-sm text-gray-900 dark:text-white">-</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Model</label>
                            <p id="detail_tool_model" class="text-sm text-gray-900 dark:text-white">-</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Image URL</label>
                            <p id="detail_tool_image_url" class="text-sm text-gray-600 dark:text-gray-400 break-all">-</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Description</label>
                        <p id="detail_tool_description" class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap">-</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button onclick="closeToolDetailsModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

