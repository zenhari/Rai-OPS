<?php
require_once '../../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/operations/roster/add.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_shift_code') {
        // Validate required fields
        $code = trim($_POST['code'] ?? '');
        if (empty($code)) {
            $error = 'Code is required.';
        } elseif (strlen($code) !== 3) {
            $error = 'Code must be exactly 3 characters.';
        } else {
            // Check if code already exists
            $existing = getAllShiftCodes();
            $codeExists = false;
            foreach ($existing as $shift) {
                if (strtolower($shift['code']) === strtolower($code)) {
                    $codeExists = true;
                    break;
                }
            }
            
            if ($codeExists) {
                $error = 'Shift code already exists.';
            } else {
                // Prepare data
                $data = $_POST;
                $data['text_color'] = $_POST['text_color_hex'] ?? $_POST['text_color'] ?? '#000000';
                
                // Create shift code
                $shiftId = createShiftCode($data, $current_user['id'] ?? null);
                
                if ($shiftId) {
                    $message = 'Shift code saved successfully.';
                    header('Location: index.php?msg=' . urlencode($message));
                    exit();
                } else {
                    $error = 'Failed to save shift code. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Shift Code - <?php echo PROJECT_NAME; ?></title>
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
                                <i class="fas fa-plus-circle mr-2"></i>Add Shift Code
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Create a new shift code configuration
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Error Messages -->
                <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                <?php echo htmlspecialchars($error); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Shift Details Form -->
                <form method="POST" action="" id="shiftCodeForm" class="space-y-6">
                    <input type="hidden" name="action" value="save_shift_code">

                    <!-- Basic Information Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Basic Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Code -->
                            <div>
                                <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Code <span class="text-red-500">*</span> <span class="text-xs text-gray-500">(3 characters)</span>
                                </label>
                                <input type="text" 
                                       id="code" 
                                       name="code" 
                                       required
                                       maxlength="3"
                                       minlength="3"
                                       pattern=".{3}"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white uppercase"
                                       placeholder="ABC"
                                       oninput="this.value = this.value.toUpperCase(); if(this.value.length > 3) this.value = this.value.slice(0,3);">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Must be exactly 3 characters</p>
                            </div>

                            <!-- Description -->
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Description
                                </label>
                                <input type="text" 
                                       id="description" 
                                       name="description" 
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Text Colour -->
                            <div>
                                <label for="text_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Text Colour
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" 
                                           id="text_color" 
                                           name="text_color" 
                                           value="#000000"
                                           class="h-10 w-20 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700">
                                    <input type="text" 
                                           id="text_color_hex" 
                                           value="#000000"
                                           pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$"
                                           class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                            </div>

                            <!-- Background Colour -->
                            <div>
                                <label for="background_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Background Colour
                                </label>
                                <input type="color" 
                                       id="background_color" 
                                       name="background_color" 
                                       value="#ffffff"
                                       class="h-10 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700">
                            </div>

                            <!-- Base -->
                            <div>
                                <label for="base" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Base
                                </label>
                                <select id="base" 
                                        name="base" 
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="Common" selected>Common</option>
                                    <!-- TODO: Populate from database -->
                                </select>
                            </div>

                            <!-- Department -->
                            <div>
                                <label for="department" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Department
                                </label>
                                <select id="department" 
                                        name="department" 
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="Common" selected>Common</option>
                                    <!-- TODO: Populate from database -->
                                </select>
                            </div>
                        </div>

                        <!-- Category -->
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <div class="flex flex-wrap gap-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="category" value="Duty" checked class="form-radio h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Duty</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="category" value="Non-Duty" class="form-radio h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Non-Duty</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="category" value="Standby" class="form-radio h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Standby</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="category" value="Leave" class="form-radio h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Leave</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="category" value="Aircraft" class="form-radio h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Aircraft</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Shift Details Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Shift Details</h3>
                        
                        <!-- Duties -->
                        <div class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                            <h4 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-4">Duties</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                                <div>
                                    <label for="duty_start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Start
                                    </label>
                                    <div class="relative">
                                        <input type="time" 
                                               id="duty_start" 
                                               name="duty_start[]" 
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <i class="fas fa-clock absolute right-3 top-3 text-gray-400"></i>
                                    </div>
                                </div>
                                <div>
                                    <label for="duty_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        End
                                    </label>
                                    <div class="relative">
                                        <input type="time" 
                                               id="duty_end" 
                                               name="duty_end[]" 
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <i class="fas fa-clock absolute right-3 top-3 text-gray-400"></i>
                                    </div>
                                </div>
                                <div>
                                    <button type="button" onclick="addDutyPeriod()" class="w-full h-10 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200 flex items-center justify-center">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="duty_periods" class="mt-4 space-y-4"></div>
                            <div class="mt-4 space-y-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="sleeping_accommodation" value="1" class="form-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Sleeping Accommodation Available</span>
                                </label>
                                <label class="inline-flex items-center block">
                                    <input type="checkbox" name="duties_non_cumulative" value="1" class="form-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Duties are non-cumulative</span>
                                </label>
                            </div>
                        </div>

                        <!-- Flying Duty Period -->
                        <div class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                            <h4 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-4">Flying Duty Period</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                                <div>
                                    <label for="flying_start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Start
                                    </label>
                                    <div class="relative">
                                        <input type="time" 
                                               id="flying_start" 
                                               name="flying_start[]" 
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <i class="fas fa-clock absolute right-3 top-3 text-gray-400"></i>
                                    </div>
                                </div>
                                <div>
                                    <label for="flying_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        End
                                    </label>
                                    <div class="relative">
                                        <input type="time" 
                                               id="flying_end" 
                                               name="flying_end[]" 
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <i class="fas fa-clock absolute right-3 top-3 text-gray-400"></i>
                                    </div>
                                </div>
                                <div>
                                    <button type="button" onclick="addFlyingPeriod()" class="w-full h-10 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200 flex items-center justify-center">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="flying_periods" class="mt-4 space-y-4"></div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <div>
                                    <label for="flight_hours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Approx. Hours
                                    </label>
                                    <input type="number" 
                                           id="flight_hours" 
                                           name="flight_hours" 
                                           step="0.1"
                                           value="0.0"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label for="sectors" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Sectors
                                    </label>
                                    <input type="number" 
                                           id="sectors" 
                                           name="sectors" 
                                           value="0"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                            </div>
                        </div>

                        <!-- Work Practice -->
                        <div>
                            <h4 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-4">Work Practice</h4>
                            <div class="mb-4">
                                <label for="work_practice" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Work Practice
                                </label>
                                <select id="work_practice" 
                                        name="work_practice" 
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">No Work Practice Specified</option>
                                    <!-- TODO: Populate from database -->
                                </select>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                                <div>
                                    <label for="shift_start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Shift: Start <i class="fas fa-question-circle text-gray-400" title="Help text"></i>
                                    </label>
                                    <div class="relative">
                                        <input type="time" 
                                               id="shift_start" 
                                               name="shift_start[]" 
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <i class="fas fa-clock absolute right-3 top-3 text-gray-400"></i>
                                    </div>
                                </div>
                                <div>
                                    <label for="shift_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        End
                                    </label>
                                    <div class="relative">
                                        <input type="time" 
                                               id="shift_end" 
                                               name="shift_end[]" 
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <i class="fas fa-clock absolute right-3 top-3 text-gray-400"></i>
                                    </div>
                                </div>
                                <div>
                                    <button type="button" onclick="addShiftPeriod()" class="w-full h-10 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200 flex items-center justify-center">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="shift_periods" class="mt-4 space-y-4"></div>
                        </div>
                    </div>

                    <!-- Additional Information Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Additional Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- AL -->
                            <div>
                                <label for="al" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    AL
                                </label>
                                <input type="number" 
                                       id="al" 
                                       name="al" 
                                       step="0.1"
                                       value="0.0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- FL -->
                            <div>
                                <label for="fl" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    FL
                                </label>
                                <input type="number" 
                                       id="fl" 
                                       name="fl" 
                                       step="0.1"
                                       value="0.0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <!-- Checkboxes -->
                        <div class="mt-6 space-y-2">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="start_of_new_tour" value="1" class="form-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Start of New Tour</span>
                            </label>
                            <label class="inline-flex items-center block">
                                <input type="checkbox" name="enable_bulk_duty_update" value="1" class="form-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Enable Bulk Duty Update</span>
                            </label>
                            <label class="inline-flex items-center block">
                                <input type="checkbox" name="allowed_in_timesheet" value="1" checked class="form-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Allowed In Timesheet</span>
                            </label>
                            <label class="inline-flex items-center block">
                                <input type="checkbox" name="show_in_scheduler_quick_create" value="1" class="form-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Show in scheduler quick create menu</span>
                            </label>
                            <label class="inline-flex items-center block">
                                <input type="checkbox" name="enabled" value="1" checked class="form-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Enabled</span>
                            </label>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-3">
                        <a href="index.php" 
                           class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600 transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600 transition-colors duration-200">
                            <i class="fas fa-save mr-2"></i>
                            Save
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script>
        // Sync color picker with hex input
        document.getElementById('text_color').addEventListener('input', function(e) {
            document.getElementById('text_color_hex').value = e.target.value;
        });
        
        document.getElementById('text_color_hex').addEventListener('input', function(e) {
            if (/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(e.target.value)) {
                document.getElementById('text_color').value = e.target.value;
            }
        });

        let dutyPeriodCount = 0;
        let flyingPeriodCount = 0;
        let shiftPeriodCount = 0;

        function addDutyPeriod() {
            dutyPeriodCount++;
            const container = document.getElementById('duty_periods');
            const div = document.createElement('div');
            div.className = 'grid grid-cols-1 md:grid-cols-3 gap-4 items-end';
            div.innerHTML = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start</label>
                    <div class="relative">
                        <input type="time" name="duty_start[]" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <i class="fas fa-clock absolute right-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End</label>
                    <div class="relative">
                        <input type="time" name="duty_end[]" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <i class="fas fa-clock absolute right-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                <div>
                    <button type="button" onclick="this.closest('div').remove()" class="w-full h-10 px-4 py-2 bg-red-200 dark:bg-red-700 text-red-700 dark:text-red-300 rounded-md hover:bg-red-300 dark:hover:bg-red-600 transition-colors duration-200 flex items-center justify-center">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(div);
        }

        function addFlyingPeriod() {
            flyingPeriodCount++;
            const container = document.getElementById('flying_periods');
            const div = document.createElement('div');
            div.className = 'grid grid-cols-1 md:grid-cols-3 gap-4 items-end';
            div.innerHTML = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start</label>
                    <div class="relative">
                        <input type="time" name="flying_start[]" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <i class="fas fa-clock absolute right-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End</label>
                    <div class="relative">
                        <input type="time" name="flying_end[]" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <i class="fas fa-clock absolute right-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                <div>
                    <button type="button" onclick="this.closest('div').remove()" class="w-full h-10 px-4 py-2 bg-red-200 dark:bg-red-700 text-red-700 dark:text-red-300 rounded-md hover:bg-red-300 dark:hover:bg-red-600 transition-colors duration-200 flex items-center justify-center">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(div);
        }

        function addShiftPeriod() {
            shiftPeriodCount++;
            const container = document.getElementById('shift_periods');
            const div = document.createElement('div');
            div.className = 'grid grid-cols-1 md:grid-cols-3 gap-4 items-end';
            div.innerHTML = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Shift: Start</label>
                    <div class="relative">
                        <input type="time" name="shift_start[]" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <i class="fas fa-clock absolute right-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End</label>
                    <div class="relative">
                        <input type="time" name="shift_end[]" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <i class="fas fa-clock absolute right-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                <div>
                    <button type="button" onclick="this.closest('div').remove()" class="w-full h-10 px-4 py-2 bg-red-200 dark:bg-red-700 text-red-700 dark:text-red-300 rounded-md hover:bg-red-300 dark:hover:bg-red-600 transition-colors duration-200 flex items-center justify-center">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(div);
        }
    </script>
</body>
</html>

