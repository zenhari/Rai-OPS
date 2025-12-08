<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/dispatch/webform/index.php');

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch Handover - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { 
            font-family: 'Roboto', sans-serif; 
        }
        @media print {
            body * {
                visibility: hidden;
            }
            .print-content, .print-content * {
                visibility: visible;
            }
            .print-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
        .form-table {
            border-collapse: collapse;
            width: 100%;
        }
        .form-table td, .form-table th {
            border: 1px solid #e5e7eb;
            padding: 12px 16px;
            font-size: 14px;
            text-align: left;
            vertical-align: middle;
        }
        .dark .form-table td, .dark .form-table th {
            border-color: #4b5563;
        }
        .form-table th {
            background-color: #f9fafb;
            font-weight: 600;
            text-align: center;
            font-size: 13px;
            letter-spacing: 0.05em;
        }
        .dark .form-table th {
            background-color: #e5e7eb;
            color: #111827;
        }
        .checkbox-cell {
            text-align: center;
        }
        .checkbox-cell input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #3b82f6;
        }
        @media print {
            .form-table {
                font-size: 10px;
            }
            .form-table td, .form-table th {
                padding: 6px 8px;
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
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 no-print">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Dispatch Handover</h1>
                            <p class="text-base text-gray-600 dark:text-gray-400 mt-2 font-medium">Flight dispatch handover form</p>
                        </div>
                        <div class="flex space-x-3">
                            <button onclick="window.print()" 
                                    class="inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm hover:shadow-md">
                                <i class="fas fa-print mr-2.5"></i>
                                Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-8">
                <?php include '../../../includes/permission_banner.php'; ?>
                
                <!-- Dispatch Handover Form -->
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl overflow-hidden print-content border border-gray-200 dark:border-gray-700">
                    <div class="p-8">
                        <!-- Main Form Table -->
                        <div class="overflow-x-auto mb-8">
                            <table class="form-table min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Header Row -->
                                <thead>
                                    <tr>
                                        <th colspan="2" class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">a) Date</th>
                                        <th colspan="2" class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">b) Call sign</th>
                                        <th colspan="4" class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">c) Reg & Type</th>
                                        <th colspan="4" class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">d) Sector</th>
                                        <th colspan="3" class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">e) EOBT</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <!-- Row 1 -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" rowspan="6" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">1.</td>
                                        <td rowspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-semibold text-lg mb-2">NEA</div>
                                            <input type="checkbox" name="rego_nea" class="checkbox-cell">
                                        </td>
                                        <td colspan="3" rowspan="6" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-bold text-xl">ERJ145</div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">1.</td>
                                    </tr>
                                    <!-- Row 2 -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">2.</td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">2.</td>
                                    </tr>
                                    <!-- Row 3 -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">3.</td>
                                        <td rowspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-semibold text-lg mb-2">NEB</div>
                                            <input type="checkbox" name="rego_neb" class="checkbox-cell">
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">3.</td>
                                    </tr>
                                    <!-- Row 4 -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">4.</td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">4.</td>
                                    </tr>
                                    <!-- Row 5 -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">5.</td>
                                        <td rowspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-semibold text-lg mb-2">NEC</div>
                                            <input type="checkbox" name="rego_nec" class="checkbox-cell">
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">5.</td>
                                    </tr>
                                    <!-- Row 6 -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">6.</td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">6.</td>
                                    </tr>
                                    <!-- Row 7: PIC, FO, FD, FLIGHT RULES, TYPE OF FLIGHT, FLIGHT PERM -->
                                    <tr class="bg-gray-100 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            f) PIC
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            g) FO
                                        </td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            h) FD
                                        </td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            i) FLIGHT RULES
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            j) TYPE OF FLIGHT
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            k) FLIGHT<br>PERM
                                        </td>
                                    </tr>
                                    <!-- Row 8 -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">1.</td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white">
                                            Docs&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;follow up
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-medium">
                                            <div class="flex items-center justify-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>I</span>
                                                    <input type="checkbox" name="flight_rules_1_i" class="checkbox-cell">
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>Y</span>
                                                    <input type="checkbox" name="flight_rules_1_y" class="checkbox-cell">
                                                </label>
                                            </div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-medium">
                                            <div class="flex items-center justify-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>V</span>
                                                    <input type="checkbox" name="flight_rules_1_v" class="checkbox-cell">
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>Z</span>
                                                    <input type="checkbox" name="flight_rules_1_z" class="checkbox-cell">
                                                </label>
                                            </div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            revenue
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="flight_perm_1" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <!-- Row 9 -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">2.</td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-medium">
                                            <div class="flex items-center justify-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>I</span>
                                                    <input type="checkbox" name="flight_rules_2_i" class="checkbox-cell">
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>Y</span>
                                                    <input type="checkbox" name="flight_rules_2_y" class="checkbox-cell">
                                                </label>
                                            </div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-medium">
                                            <div class="flex items-center justify-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>V</span>
                                                    <input type="checkbox" name="flight_rules_2_v" class="checkbox-cell">
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>Z</span>
                                                    <input type="checkbox" name="flight_rules_2_z" class="checkbox-cell">
                                                </label>
                                            </div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="flight_perm_2" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <!-- Row 10 -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">3.</td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-medium">
                                            <div class="flex items-center justify-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>I</span>
                                                    <input type="checkbox" name="flight_rules_3_i" class="checkbox-cell">
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>Y</span>
                                                    <input type="checkbox" name="flight_rules_3_y" class="checkbox-cell">
                                                </label>
                                            </div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-medium">
                                            <div class="flex items-center justify-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>V</span>
                                                    <input type="checkbox" name="flight_rules_3_v" class="checkbox-cell">
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>Z</span>
                                                    <input type="checkbox" name="flight_rules_3_z" class="checkbox-cell">
                                                </label>
                                            </div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="flight_perm_3" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <!-- Row 11 -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">4.</td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-medium">
                                            <div class="flex items-center justify-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>I</span>
                                                    <input type="checkbox" name="flight_rules_4_i" class="checkbox-cell">
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>Y</span>
                                                    <input type="checkbox" name="flight_rules_4_y" class="checkbox-cell">
                                                </label>
                                            </div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-medium">
                                            <div class="flex items-center justify-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>V</span>
                                                    <input type="checkbox" name="flight_rules_4_v" class="checkbox-cell">
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>Z</span>
                                                    <input type="checkbox" name="flight_rules_4_z" class="checkbox-cell">
                                                </label>
                                            </div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="flight_perm_4" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <!-- Row 12 -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">5.</td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-medium">
                                            <div class="flex items-center justify-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>I</span>
                                                    <input type="checkbox" name="flight_rules_5_i" class="checkbox-cell">
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>Y</span>
                                                    <input type="checkbox" name="flight_rules_5_y" class="checkbox-cell">
                                                </label>
                                            </div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-medium">
                                            <div class="flex items-center justify-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>V</span>
                                                    <input type="checkbox" name="flight_rules_5_v" class="checkbox-cell">
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>Z</span>
                                                    <input type="checkbox" name="flight_rules_5_z" class="checkbox-cell">
                                                </label>
                                            </div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="flight_perm_5" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <!-- Row 13 -->
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">6.</td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-medium">
                                            <div class="flex items-center justify-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>I</span>
                                                    <input type="checkbox" name="flight_rules_6_i" class="checkbox-cell">
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>Y</span>
                                                    <input type="checkbox" name="flight_rules_6_y" class="checkbox-cell">
                                                </label>
                                            </div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-medium">
                                            <div class="flex items-center justify-center gap-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>V</span>
                                                    <input type="checkbox" name="flight_rules_6_v" class="checkbox-cell">
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <span>Z</span>
                                                    <input type="checkbox" name="flight_rules_6_z" class="checkbox-cell">
                                                </label>
                                            </div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="flight_perm_6" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <!-- Row 14: WX analyzed -->
                                    <tr class="bg-gray-100 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            <div>l) WX analyzed</div>
                                            <div class="mt-1 text-sm font-medium">CHECKED</div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-semibold mb-3">DEP AD</div>
                                            <input type="checkbox" name="wx_dep_ad" class="checkbox-cell">
                                        </td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-semibold mb-3">DSTN AD</div>
                                            <input type="checkbox" name="wx_dstn_ad" class="checkbox-cell">
                                        </td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-semibold mb-3">DSTN ALTN</div>
                                            <input type="checkbox" name="wx_dstn_altn" class="checkbox-cell">
                                        </td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-semibold mb-3">OTHER ALTN</div>
                                            <input type="checkbox" name="wx_other_altn" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <!-- Row 15: NOTAM -->
                                    <tr class="bg-gray-100 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            <div>M) NOTAM</div>
                                            <div class="mt-1 text-sm font-medium">CHECKED</div>
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-semibold mb-3">DEP AD</div>
                                            <input type="checkbox" name="notam_dep_ad" class="checkbox-cell">
                                        </td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-semibold mb-3">DSTN AD</div>
                                            <input type="checkbox" name="notam_dstn_ad" class="checkbox-cell">
                                        </td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-semibold mb-3">DSTN ALTN</div>
                                            <input type="checkbox" name="notam_dstn_altn" class="checkbox-cell">
                                        </td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-semibold mb-3">OTHER ALTN</div>
                                            <input type="checkbox" name="notam_other_altn" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <!-- Row 16: PAX/CARGO weight -->
                                    <tr class="bg-gray-100 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td rowspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            n) PAX/CARGO weight (LBS)
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            Sector 1
                                        </td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            Sector 2
                                        </td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            Sector 3
                                        </td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            Sector 4
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            Sector 5
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- MEL/CDL Table -->
                        <div class="overflow-x-auto mb-8">
                            <table class="form-table min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th style="width: 20%;" class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">
                                            o) MEL/CDL reference
                                        </th>
                                        <th style="width: 80%;" class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">
                                            MEL/CDL limitation
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- OFP GENERATE -->
                        <div class="overflow-x-auto mb-8">
                            <table class="form-table min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th colspan="10" class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">
                                            p) OFP GENERATE
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td rowspan="9" style="width: 20%;" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-semibold mb-4">q) ATS ROUTE</div>
                                            <div class="space-y-2 mt-6">
                                                <div class="text-sm flex items-center justify-center gap-2">
                                                <span>RVSM</span>
                                                <input type="checkbox" name="ats_route_rvsm" class="checkbox-cell">
                                            </div>
                                            </div>
                                        </td>
                                        <td colspan="8" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            r) OFP ROUTE
                                        </td>
                                        <td style="width: 19%;" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            s) ATS ROUTE/CRZ LVL
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="8" class="px-6 py-4 text-base text-gray-900 dark:text-white">
                                            <strong>1.</strong> Route main
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="ats_route_1" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            TALTN:
                                        </td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            ERA:
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            ALTN:
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="ats_route_1_altn" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="8" class="px-6 py-4 text-base text-gray-900 dark:text-white">
                                            <strong>2.</strong>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="ats_route_2" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            TALTN:
                                        </td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            ERA:
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            ALTN:
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="ats_route_2_altn" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="8" class="px-6 py-4 text-base text-gray-900 dark:text-white">
                                            <strong>3.</strong>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="ats_route_3" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            TALTN:
                                        </td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            ERA:
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            ALTN:
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="ats_route_3_altn" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td rowspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="8" class="px-6 py-4 text-base text-gray-900 dark:text-white">
                                            <strong>4.</strong>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="ats_route_4" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            TALTN:
                                        </td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            ERA:
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            ALTN:
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="ats_route_4_altn" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="8" class="px-6 py-4 text-base text-gray-900 dark:text-white">
                                            <strong>5.</strong>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="ats_route_5" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            TALTN:
                                        </td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            ERA:
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            ALTN:
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="ats_route_5_altn" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td rowspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="8" class="px-6 py-4 text-base text-gray-900 dark:text-white">
                                            <strong>6.</strong>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="ats_route_6" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            TALTN:
                                        </td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            ERA:
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            ALTN:
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="ats_route_6_altn" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <!-- FUEL Row -->
                                    <tr class="bg-gray-100 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            t) FUEL
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            T/O FUEL
                                        </td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            TRIP FUEL
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            TAXI FUEL
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            ARRIVAL FUEL(EST)
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            FUEL USED(EST)
                                        </td>
                                    </tr>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium"><?php echo $i; ?>.</td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <?php echo $i == 1 ? 't/o-trip-taxi' : ''; ?>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <?php echo $i == 1 ? 'Trip+taxi' : ''; ?>
                                        </td>
                                    </tr>
                                    <?php endfor; ?>
                                    <!-- EFB SOFTWARE HANDOVER -->
                                    <tr class="bg-gray-100 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td colspan="10" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            u) EFB SOFTWARE HANDOVER
                                        </td>
                                    </tr>
                                    <!-- SECTORS -->
                                    <tr class="bg-gray-100 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            v) SECTORS
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            CREW IPAD
                                        </td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            REMAINING IPAD
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            CREW POWER BANK
                                        </td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white text-center font-semibold">
                                            REMAINING POWER BANK
                                        </td>
                                    </tr>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium"><?php echo $i; ?>.</td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="3" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td colspan="2" class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Post-Flight Table -->
                        <div class="overflow-x-auto mb-8">
                            <table class="form-table min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th colspan="7" class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">
                                            w) Post‑Flight (after landing)
                                        </th>
                                    </tr>
                                    <tr>
                                        <th style="width: 14%;" class="px-6 py-4 text-left text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">CALL SIGN</th>
                                        <th style="width: 14%;" class="px-6 py-4 text-left text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">ACTUAL OFF-BLOCK</th>
                                        <th style="width: 14%;" class="px-6 py-4 text-left text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">ACTUAL ON-BLOCK:</th>
                                        <th style="width: 14%;" class="px-6 py-4 text-left text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">DELAYS CODES/REMARKS</th>
                                        <th style="width: 14%;" class="px-6 py-4 text-left text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">ARRIVAL FUEL</th>
                                        <th style="width: 14%;" class="px-6 py-4 text-left text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">FUEL USED</th>
                                        <th style="width: 14%;" class="px-6 py-4 text-left text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">NOTOC</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium"><?php echo $i; ?>.</td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white"><?php echo $i == 1 ? 'dashboard' : ''; ?></td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white"><?php echo $i <= 3 ? ' /' : ''; ?></td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white"><?php echo $i == 1 ? 'OFP' : ''; ?></td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white"></td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <input type="checkbox" name="notoc_<?php echo $i; ?>" class="checkbox-cell">
                                        </td>
                                    </tr>
                                    <?php endfor; ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">DOCs CONTOL</td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-medium mb-2">x) CHECKED</div>
                                            <input type="checkbox" name="docs_checked" class="checkbox-cell">
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white text-center">
                                            <div class="font-medium mb-2">y) CONFIRMED</div>
                                            <input type="checkbox" name="docs_confirmed" class="checkbox-cell">
                                        </td>
                                        <td colspan="4" class="px-6 py-4 text-base text-gray-900 dark:text-white">
                                            <div class="font-semibold mb-1">z) DISPATCH MANAGER SIGN: REYHANE DAVOODMANESH</div>
                                            <div class="text-sm">LICENSE NUMBER:</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Crew Briefing Table -->
                        <div class="overflow-x-auto">
                            <table class="form-table min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th colspan="2" class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">
                                            CREW BRIEFING
                                        </th>
                                    </tr>
                                    <tr>
                                        <th style="width: 50%;" class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">
                                            DEPARTURE AD
                                        </th>
                                        <th style="width: 50%;" class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-900 dark:bg-gray-200 uppercase tracking-wider">
                                            DSTN AD
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" name="crew_briefing_dep_metar" class="checkbox-cell">
                                                <span>DEP / ERA METAR & TAF & SIG Wx</span>
                                            </label>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" name="crew_briefing_dstn_metar" class="checkbox-cell">
                                                <span>DSTN/ DSTN ALTN METAR & TAF & SIG Wx</span>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" name="crew_briefing_to_altn_metar" class="checkbox-cell">
                                                <span>T/O ALTN METAR & TAF & SIG Wx</span>
                                            </label>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" name="crew_briefing_dstn_altn_notam" class="checkbox-cell">
                                                <span>DSTN/ DSTN ALTN NOTAM</span>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" name="crew_briefing_dep_era_notam" class="checkbox-cell">
                                                <span>DEP / ERA NOTAM</span>
                                            </label>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" name="crew_briefing_nav_inadequacy" class="checkbox-cell">
                                                <span>NAV INADEQUACY</span>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" name="crew_briefing_to_altn_notam" class="checkbox-cell">
                                                <span>T/O ALTN NOTAM</span>
                                            </label>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" name="crew_briefing_mel_cdl_confirmed" class="checkbox-cell">
                                                <span>MEL/CDL confirmed</span>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" name="crew_briefing_ats_fpl_confirmed" class="checkbox-cell">
                                                <span>ATS FPL CONFIRMED</span>
                                            </label>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" name="crew_briefing_mandatory_docs" class="checkbox-cell">
                                                <span>COPIES OF MANDATORY DOCS</span>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white">
                                            <div class="font-semibold mb-4">PIC SIGN:</div>
                                            <div class="h-16 border-b border-gray-300 dark:border-gray-600"></div>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-900 dark:text-white">
                                            <div class="font-semibold mb-4">DISPATCHER SIGN:</div>
                                            <div class="h-16 border-b border-gray-300 dark:border-gray-600"></div>
                                        </td>
                                    </tr>
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
