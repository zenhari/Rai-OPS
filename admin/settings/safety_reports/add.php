<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/settings/safety_reports/add.php');


$user = getCurrentUser();
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'report_type' => trim($_POST['report_type'] ?? ''),
        'report_no' => trim($_POST['report_no'] ?? ''),
        'submit_on_behalf_of' => trim($_POST['submit_on_behalf_of'] ?? ''),
        'reporter_name' => trim($_POST['reporter_name'] ?? ''),
        'reporter_address_line_1' => trim($_POST['reporter_address_line_1'] ?? ''),
        'reporter_address_line_2' => trim($_POST['reporter_address_line_2'] ?? ''),
        'reporter_suburb' => trim($_POST['reporter_suburb'] ?? ''),
        'reporter_state' => trim($_POST['reporter_state'] ?? ''),
        'reporter_postcode' => trim($_POST['reporter_postcode'] ?? ''),
        'reporter_country' => trim($_POST['reporter_country'] ?? ''),
        'reporter_telephone' => trim($_POST['reporter_telephone'] ?? ''),
        'reporter_fax' => trim($_POST['reporter_fax'] ?? ''),
        'reporter_email' => trim($_POST['reporter_email'] ?? ''),
        'confidential' => isset($_POST['confidential']) ? 1 : 0,
        'report_title' => trim($_POST['report_title'] ?? ''),
        'event_date_time' => !empty($_POST['event_date_time']) ? $_POST['event_date_time'] : null,
        'event_base' => trim($_POST['event_base'] ?? ''),
        'event_department' => trim($_POST['event_department'] ?? ''),
        'report_description' => trim($_POST['report_description'] ?? ''),
        'status' => $_POST['status'] ?? 'draft',
        'created_by' => $user['id']
    ];

    // Validation
    if (empty($data['report_type'])) {
        $error_message = "Report type is required.";
    } elseif (empty($data['reporter_name'])) {
        $error_message = "Reporter name is required.";
    } elseif (empty($data['report_title'])) {
        $error_message = "Report title is required.";
    } else {
        if (createSafetyReport($data)) {
            $success_message = "Safety report created successfully.";
            // Redirect to list page after 2 seconds
            header("refresh:2;url=index.php");
        } else {
            $error_message = "Failed to create safety report. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Safety Report - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="flex flex-col min-h-screen">
        <!-- Include Sidebar -->
        <?php include '../../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="lg:ml-64 flex-1">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                New Safety Report
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Create a new safety incident report
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Permission Banner -->
                <?php include '../../../includes/permission_banner.php'; ?>

                <!-- Messages -->
                <?php if ($success_message): ?>
                    <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800 dark:text-green-200">
                                    <?php echo htmlspecialchars($success_message); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                    <?php echo htmlspecialchars($error_message); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <form method="POST" class="space-y-6 p-6">
                        <!-- Report Information -->
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Report Information</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Report Type <span class="text-red-500">*</span>
                                    </label>
                                    <select name="report_type" required 
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="">Select Report Type</option>
                                        <option value="Incident Report" <?php echo (($_POST['report_type'] ?? '') === 'Incident Report') ? 'selected' : ''; ?>>Incident Report</option>
                                        <option value="Near Miss" <?php echo (($_POST['report_type'] ?? '') === 'Near Miss') ? 'selected' : ''; ?>>Near Miss</option>
                                        <option value="Hazard Report" <?php echo (($_POST['report_type'] ?? '') === 'Hazard Report') ? 'selected' : ''; ?>>Hazard Report</option>
                                        <option value="Safety Concern" <?php echo (($_POST['report_type'] ?? '') === 'Safety Concern') ? 'selected' : ''; ?>>Safety Concern</option>
                                        <option value="Equipment Failure" <?php echo (($_POST['report_type'] ?? '') === 'Equipment Failure') ? 'selected' : ''; ?>>Equipment Failure</option>
                                        <option value="Environmental" <?php echo (($_POST['report_type'] ?? '') === 'Environmental') ? 'selected' : ''; ?>>Environmental</option>
                                        <option value="Other" <?php echo (($_POST['report_type'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Report No</label>
                                    <input type="text" name="report_no" 
                                           value="<?php echo htmlspecialchars($_POST['report_no'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Submit on Behalf Of</label>
                                    <input type="text" name="submit_on_behalf_of" 
                                           value="<?php echo htmlspecialchars($_POST['submit_on_behalf_of'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Report Title <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="report_title" required 
                                           value="<?php echo htmlspecialchars($_POST['report_title'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Event Date/Time</label>
                                    <input type="datetime-local" name="event_date_time" 
                                           value="<?php echo htmlspecialchars($_POST['event_date_time'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Event Base</label>
                                    <input type="text" name="event_base" 
                                           value="<?php echo htmlspecialchars($_POST['event_base'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Event Department</label>
                                    <input type="text" name="event_department" 
                                           value="<?php echo htmlspecialchars($_POST['event_department'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                                    <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="draft" <?php echo (($_POST['status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                        <option value="submitted" <?php echo (($_POST['status'] ?? '') === 'submitted') ? 'selected' : ''; ?>>Submitted</option>
                                    </select>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Report Description</label>
                                    <textarea name="report_description" rows="4" 
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars($_POST['report_description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Reporter Details -->
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Reporter Details</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="reporter_name" required 
                                           value="<?php echo htmlspecialchars($_POST['reporter_name'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Address Line 1</label>
                                    <input type="text" name="reporter_address_line_1" 
                                           value="<?php echo htmlspecialchars($_POST['reporter_address_line_1'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Address Line 2</label>
                                    <input type="text" name="reporter_address_line_2" 
                                           value="<?php echo htmlspecialchars($_POST['reporter_address_line_2'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Suburb</label>
                                    <input type="text" name="reporter_suburb" 
                                           value="<?php echo htmlspecialchars($_POST['reporter_suburb'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">State</label>
                                    <input type="text" name="reporter_state" 
                                           value="<?php echo htmlspecialchars($_POST['reporter_state'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Postcode</label>
                                    <input type="text" name="reporter_postcode" 
                                           value="<?php echo htmlspecialchars($_POST['reporter_postcode'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Country</label>
                                    <input type="text" name="reporter_country" 
                                           value="<?php echo htmlspecialchars($_POST['reporter_country'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Telephone</label>
                                    <input type="tel" name="reporter_telephone" 
                                           value="<?php echo htmlspecialchars($_POST['reporter_telephone'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fax</label>
                                    <input type="tel" name="reporter_fax" 
                                           value="<?php echo htmlspecialchars($_POST['reporter_fax'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                                    <input type="email" name="reporter_email" 
                                           value="<?php echo htmlspecialchars($_POST['reporter_email'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                
                                <div class="md:col-span-2">
                                    <div class="flex items-center">
                                        <input type="checkbox" name="confidential" id="confidential" 
                                               <?php echo isset($_POST['confidential']) ? 'checked' : ''; ?>
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                        <label for="confidential" class="ml-2 block text-sm text-gray-900 dark:text-white">
                                            Confidential Report
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-save mr-2"></i>
                                Create Safety Report
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>

