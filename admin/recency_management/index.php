<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/recency_management/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Process form data here
    $type = $_POST['type'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $departments = isset($_POST['departments']) && is_array($_POST['departments']) ? $_POST['departments'] : [];
    $pilot_licence = $_POST['pilot_licence'] ?? '';
    $type_specific = isset($_POST['type_specific']) ? 1 : 0;
    $target_count = intval($_POST['target_count'] ?? 0);
    $months_apart = intval($_POST['months_apart'] ?? 0);
    $increment_target = isset($_POST['increment_target']) ? 1 : 0;
    $period = intval($_POST['period'] ?? 0);
    $period_type = $_POST['period_type'] ?? 'D';
    $warning = intval($_POST['warning'] ?? 0);
    $renewal = intval($_POST['renewal'] ?? 0);
    $renewal_type = $_POST['renewal_type'] ?? 'D';
    $renewal_count_type = $_POST['renewal_count_type'] ?? 'D';
    $grace_period = intval($_POST['grace_period'] ?? 0);
    $grace_period_type = $_POST['grace_period_type'] ?? 'D';
    $reval = intval($_POST['reval'] ?? 0);
    $end_period = $_POST['end_period'] ?? '';
    $default_status = $_POST['default_status'] ?? 'Optional';
    $contacts = $_POST['contacts'] ?? '';
    $disabled = isset($_POST['disabled']) ? 1 : 0;
    $documentation_type = $_POST['documentation_type'] ?? 'File Upload';
    $document_required = isset($_POST['document_required']) ? 1 : 0;
    
    // Validation
    if (empty($name)) {
        $error = 'Name is required.';
    } else {
        try {
            $db = getDBConnection();
            $departments_json = !empty($departments) ? json_encode($departments) : null;
            
            $stmt = $db->prepare("INSERT INTO recency_items (
                type, name, departments, pilot_licence, type_specific, target_count, months_apart,
                increment_target, period, period_type, warning, renewal, renewal_type, renewal_count_type,
                grace_period, grace_period_type, reval, end_period, default_status, contacts,
                disabled, documentation_type, document_required, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $type ?: null,
                $name,
                $departments_json,
                $pilot_licence ?: null,
                $type_specific,
                $target_count,
                $months_apart,
                $increment_target,
                $period,
                $period_type,
                $warning,
                $renewal,
                $renewal_type,
                $renewal_count_type,
                $grace_period,
                $grace_period_type,
                $reval,
                $end_period ?: null,
                $default_status,
                $contacts ?: null,
                $disabled,
                $documentation_type,
                $document_required,
                $current_user['id']
            ]);
            
            if ($result) {
                $message = 'Recency item saved successfully.';
                // Clear form by redirecting
                header('Location: index.php?success=1');
                exit();
            } else {
                $error = 'Failed to save recency item. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Error saving recency item: " . $e->getMessage());
            $error = 'An error occurred while saving. Please try again.';
        }
    }
}

// Check for success message
if (isset($_GET['success'])) {
    $message = 'Recency item saved successfully.';
}

// Department list from the HTML
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
    <title>Recency Management - <?php echo PROJECT_NAME; ?></title>
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
                                <i class="fas fa-user-clock mr-2"></i>Recency Management
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Manage recency items and configurations
                            </p>
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

                    <form method="POST" action="" class="space-y-6">
                        <!-- Basic Information -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Basic Information</h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Type
                                    </label>
                                    <div class="flex flex-wrap gap-4">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="type" value="HEAD" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Heading</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="type" value="" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Item</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="type" value="TIME" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Timesheet Item</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="type" value="COMPOSITE" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Composite Item</span>
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="name" name="name" maxlength="200" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>

                                <div>
                                    <label for="departments" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Department(s) <span class="text-xs text-gray-500">(Select multiple)</span>
                                    </label>
                                    <select id="departments" name="departments[]" multiple
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                            style="min-height: 120px;">
                                        <?php foreach ($departments as $key => $value): ?>
                                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($key === 'Cabin Crew') ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($value); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Hold Ctrl (Windows) or Cmd (Mac) to select multiple departments
                                    </p>
                                </div>

                                <div>
                                    <label for="pilot_licence" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Pilot Licence
                                    </label>
                                    <select id="pilot_licence" name="pilot_licence"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="">*Not Selected*</option>
                                        <!-- TODO: Populate from database -->
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="type_specific" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Type Specific</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Period Settings -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Period Settings</h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="target_count" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Target Count
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        <input type="text" id="target_count" name="target_count" maxlength="6"
                                               class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">at least</span>
                                        <input type="text" id="months_apart" name="months_apart" value="0" maxlength="2"
                                               class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">months apart</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        &nbsp;
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="increment_target" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Increment to target count as items are performed</span>
                                    </label>
                                </div>

                                <div>
                                    <label for="period" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Period <span class="text-red-500">*</span>
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        <input type="text" id="period" name="period" maxlength="3"
                                               class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <div class="flex space-x-4">
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="period_type" value="D" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Days</span>
                                            </label>
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="period_type" value="M" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Months</span>
                                            </label>
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="period_type" value="NA" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Not Available</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="warning" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Caution (weeks)
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        <input type="text" id="warning" name="warning" maxlength="3"
                                               class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">weeks</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Renewal & Grace Period -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Renewal & Grace Period</h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="renewal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Renewal Window <span class="text-red-500">*</span>
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        <input type="text" id="renewal" name="renewal" maxlength="3"
                                               class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <div class="flex space-x-4">
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="renewal_type" value="D" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Days</span>
                                            </label>
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="renewal_type" value="M" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Months</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="flex space-x-4 mt-2" id="renewal_count_type_group" style="display: none;">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="renewal_count_type" value="D" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">To The Day</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="renewal_count_type" value="M" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Calendar Month</span>
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label for="grace_period" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Grace Period <span class="text-red-500">*</span>
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        <input type="text" id="grace_period" name="grace_period" maxlength="3"
                                               class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <div class="flex space-x-4">
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="grace_period_type" value="D" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Days</span>
                                            </label>
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="grace_period_type" value="M" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Months</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="reval" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Re-validation required after (months)
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        <input type="text" id="reval" name="reval" value="0" maxlength="3"
                                               class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">months</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        End Period
                                    </label>
                                    <div class="flex flex-wrap gap-4">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="end_period" value="" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">N/A</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="end_period" value="EOM" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">End of Month</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="end_period" value="EOQ" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">End of Quarter</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="end_period" value="EOT" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">End of Third</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="end_period" value="EOY" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">End of Year</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status & Documentation -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Status & Documentation</h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="default_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Default Status
                                    </label>
                                    <select id="default_status" name="default_status"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="Required">Required</option>
                                        <option value="Optional" selected>Optional</option>
                                        <option value="Hidden">Hidden</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="contacts" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Contacts
                                    </label>
                                    <div class="relative">
                                        <input type="text" id="contacts_search" 
                                               placeholder="Begin typing to search users..."
                                               autocomplete="off"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <div id="contacts_search_results" class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-y-auto hidden">
                                            <!-- Search results will appear here -->
                                        </div>
                                    </div>
                                    <div id="selected_contacts" class="mt-2 space-y-2">
                                        <!-- Selected users will appear here -->
                                    </div>
                                    <input type="hidden" id="contacts" name="contacts" value="">
                                </div>

                                <div>
                                    <label for="documentation_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Documentation Type
                                    </label>
                                    <select id="documentation_type" name="documentation_type"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="File Upload" selected>File Upload</option>
                                        <option value="Link to a repository">Link to a repository</option>
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <div class="space-y-2">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="disabled" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Disabled</span>
                                        </label>
                                        <br>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="document_required" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Document required for each item</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-4">
                            <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-save mr-2"></i>
                                Save
                            </button>
                            <a href="index.php" class="inline-flex items-center px-6 py-3 border border-gray-300 dark:border-gray-600 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Show/hide renewal count type based on renewal type
        document.querySelectorAll('input[name="renewal_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const renewalCountTypeGroup = document.getElementById('renewal_count_type_group');
                if (this.value === 'M') {
                    renewalCountTypeGroup.style.display = 'flex';
                } else {
                    renewalCountTypeGroup.style.display = 'none';
                }
            });
        });

        // User search functionality for contacts
        let selectedContacts = [];
        let searchTimeout = null;

        const contactsSearchInput = document.getElementById('contacts_search');
        const contactsSearchResults = document.getElementById('contacts_search_results');
        const selectedContactsContainer = document.getElementById('selected_contacts');
        const contactsHiddenInput = document.getElementById('contacts');

        contactsSearchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                contactsSearchResults.classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(() => {
                searchUsers(query);
            }, 300);
        });

        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!contactsSearchInput.contains(e.target) && !contactsSearchResults.contains(e.target)) {
                contactsSearchResults.classList.add('hidden');
            }
        });

        function searchUsers(query) {
            fetch(`../api/search_users.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.users) {
                        displaySearchResults(data.users);
                    } else {
                        contactsSearchResults.innerHTML = '<div class="p-3 text-sm text-gray-500 dark:text-gray-400">No users found</div>';
                        contactsSearchResults.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error searching users:', error);
                    contactsSearchResults.classList.add('hidden');
                });
        }

        function displaySearchResults(users) {
            if (users.length === 0) {
                contactsSearchResults.innerHTML = '<div class="p-3 text-sm text-gray-500 dark:text-gray-400">No users found</div>';
                contactsSearchResults.classList.remove('hidden');
                return;
            }

            const filteredUsers = users.filter(user => 
                !selectedContacts.some(selected => selected.id === user.id)
            );

            if (filteredUsers.length === 0) {
                contactsSearchResults.innerHTML = '<div class="p-3 text-sm text-gray-500 dark:text-gray-400">All matching users are already selected</div>';
                contactsSearchResults.classList.remove('hidden');
                return;
            }

            contactsSearchResults.innerHTML = filteredUsers.map(user => `
                <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0" 
                     onclick="selectContact(${user.id}, '${escapeHtml(user.first_name)}', '${escapeHtml(user.last_name)}', '${escapeHtml(user.position || 'N/A')}', '${escapeHtml(user.role || 'N/A')}')">
                    <div class="font-medium text-gray-900 dark:text-white">${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">${escapeHtml(user.position || 'N/A')} - ${escapeHtml(user.role || 'N/A')}</div>
                    ${user.email ? `<div class="text-xs text-gray-400 dark:text-gray-500">${escapeHtml(user.email)}</div>` : ''}
                </div>
            `).join('');
            
            contactsSearchResults.classList.remove('hidden');
        }

        function selectContact(userId, firstName, lastName, position, role) {
            // Check if already selected
            if (selectedContacts.some(contact => contact.id === userId)) {
                return;
            }

            selectedContacts.push({
                id: userId,
                first_name: firstName,
                last_name: lastName,
                position: position,
                role: role
            });

            updateSelectedContactsDisplay();
            contactsSearchInput.value = '';
            contactsSearchResults.classList.add('hidden');
        }

        function removeContact(userId) {
            selectedContacts = selectedContacts.filter(contact => contact.id !== userId);
            updateSelectedContactsDisplay();
        }

        function updateSelectedContactsDisplay() {
            if (selectedContacts.length === 0) {
                selectedContactsContainer.innerHTML = '';
                contactsHiddenInput.value = '';
                return;
            }

            selectedContactsContainer.innerHTML = selectedContacts.map(contact => `
                <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 mr-2 mb-2">
                    <span>${escapeHtml(contact.first_name)} ${escapeHtml(contact.last_name)}</span>
                    <button type="button" onclick="removeContact(${contact.id})" 
                            class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
            `).join('');

            // Update hidden input with comma-separated user IDs
            contactsHiddenInput.value = selectedContacts.map(contact => contact.id).join(',');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>

