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
        body { font-family: 'Roboto', sans-serif; }
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
            border: 1px solid #000;
            padding: 4px 8px;
            font-size: 11px;
            text-align: left;
        }
        .form-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: center;
        }
        .dark .form-table th {
            background-color: #374151;
        }
        .checkbox-cell {
            text-align: center;
        }
        .checkbox-cell input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        @media print {
            .form-table {
                font-size: 9px;
            }
            .form-table td, .form-table th {
                padding: 2px 4px;
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dispatch Handover</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Flight dispatch handover form</p>
                        </div>
                        <div class="flex space-x-3">
                            <button onclick="window.print()" 
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-print mr-2"></i>
                                Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../../includes/permission_banner.php'; ?>
                
                <!-- Dispatch Handover Form -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden print-content">
                    <div class="p-6">
                        <!-- Main Form Table -->
                        <table class="form-table min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <!-- Header Row -->
                            <thead>
                                <tr>
                                    <th colspan="2" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">a) Date</th>
                                    <th colspan="2" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">b) Call sign</th>
                                    <th colspan="4" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">c) Reg & Type</th>
                                    <th colspan="4" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">d) Sector</th>
                                    <th colspan="3" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">e) EOBT</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Row 1 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" rowspan="6" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">1.</td>
                                    <td rowspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>NEA</strong><br>
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                    <td colspan="3" rowspan="6" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>ERJ145</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">1.</td>
                                </tr>
                                <!-- Row 2 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">2.</td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">2.</td>
                                </tr>
                                <!-- Row 3 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">3.</td>
                                    <td rowspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>NEB</strong><br>
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">3.</td>
                                </tr>
                                <!-- Row 4 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">4.</td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">4.</td>
                                </tr>
                                <!-- Row 5 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">5.</td>
                                    <td rowspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>NEC</strong><br>
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">5.</td>
                                </tr>
                                <!-- Row 6 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">6.</td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">6.</td>
                                </tr>
                                <!-- Row 7: PIC, FO, FD, FLIGHT RULES, TYPE OF FLIGHT, FLIGHT PERM -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>f) PIC</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>g) FO</strong>
                                    </td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>h) FD</strong>
                                    </td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>i) FLIGHT RULES</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>j) TYPE OF FLIGHT</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>k) FLIGHT<br>PERM</strong>
                                    </td>
                                </tr>
                                <!-- Row 8 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">1.</td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        Docs&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;follow up
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>I ☐ Y ☐</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>V ☐ Z ☐</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        revenue
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <!-- Row 9 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">2.</td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>I ☐ Y ☐</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>V ☐ Z ☐</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <!-- Row 10 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">3.</td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>I ☐ Y ☐</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>V ☐ Z ☐</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <!-- Row 11 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">4.</td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>I ☐ Y ☐</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>V ☐ Z ☐</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <!-- Row 12 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">5.</td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>I ☐ Y ☐</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>V ☐ Z ☐</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <!-- Row 13 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">6.</td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>I ☐ Y ☐</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>V ☐ Z ☐</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <!-- Row 14: WX analyzed -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>l) WX analyzed</strong><br>
                                        <strong>CHECKED</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>DEP AD</strong><br><br>
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>DSTN AD</strong><br><br>
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>DSTN ALTN</strong><br><br>
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>OTHER ALTN</strong><br><br>
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <!-- Row 15: NOTAM -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>M) NOTAM</strong><br><br>
                                        <strong>CHECKED</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>DEP AD</strong><br><br>
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>DSTN AD</strong><br><br>
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>DSTN ALTN</strong><br><br>
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>OTHER ALTN</strong><br><br>
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <!-- Row 16: PAX/CARGO weight -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td rowspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>n) PAX/CARGO weight (LBS)</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>Sector 1</strong>
                                    </td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>Sector 2</strong>
                                    </td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>Sector 3</strong>
                                    </td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>Sector 4</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>Sector 5</strong>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- MEL/CDL Table -->
                        <table class="form-table mt-4 min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th style="width: 20%;" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">
                                        <strong>o) MEL/CDL reference</strong>
                                    </th>
                                    <th style="width: 80%;" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">
                                        <strong>MEL/CDL limitation</strong>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>

                        <!-- OFP GENERATE -->
                        <table class="form-table mt-4 min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th colspan="10" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">
                                        <strong>p) OFP GENERATE</strong>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td rowspan="9" style="width: 20%;" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>q) ATS ROUTE</strong><br><br><br><br><br><br><br><br>
                                        RVSM ☐
                                    </td>
                                    <td colspan="8" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>r) OFP ROUTE</strong>
                                    </td>
                                    <td style="width: 19%;" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>s) ATS ROUTE/CRZ LVL</strong>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="8" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>1.</strong> Route main
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>TALTN:</strong>
                                    </td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>ERA:</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>ALTN:</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="8" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>2.</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>TALTN:</strong>
                                    </td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>ERA:</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>ALTN:</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="8" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>3.</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>TALTN:</strong>
                                    </td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>ERA:</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>ALTN:</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td rowspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="8" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>4.</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>TALTN:</strong>
                                    </td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>ERA:</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>ALTN:</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="8" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>5.</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>TALTN:</strong>
                                    </td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>ERA:</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>ALTN:</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td rowspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="8" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>6.</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>TALTN:</strong>
                                    </td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>ERA:</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>ALTN:</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <!-- FUEL Row -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>t) FUEL</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>T/O FUEL</strong>
                                    </td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>TRIP FUEL</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>TAXI FUEL</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>ARRIVAL FUEL(EST)</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>FUEL USED(EST)</strong>
                                    </td>
                                </tr>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"><?php echo $i; ?>.</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <?php echo $i == 1 ? 't/o-trip-taxi' : ''; ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <?php echo $i == 1 ? 'Trip+taxi' : ''; ?>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                                <!-- EFB SOFTWARE HANDOVER -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td colspan="10" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>u) EFB SOFTWARE HANDOVER</strong>
                                    </td>
                                </tr>
                                <!-- SECTORS -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <strong>v) SECTORS</strong>
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        CREW IPAD
                                    </td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        REMAINING IPAD
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        CREW POWER BANK
                                    </td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        REMAINING POWER BANK
                                    </td>
                                </tr>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"><?php echo $i; ?>.</td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="3" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td colspan="2" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>

                        <!-- Post-Flight Table -->
                        <table class="form-table mt-4 min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th colspan="7" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">
                                        <strong>w) Post‑Flight (after landing)</strong>
                                    </th>
                                </tr>
                                <tr>
                                    <th style="width: 14%;" class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">CALL SIGN</th>
                                    <th style="width: 14%;" class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">ACTUAL OFF-BLOCK</th>
                                    <th style="width: 14%;" class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">ACTUAL ON-BLOCK:</th>
                                    <th style="width: 14%;" class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">DELAYS CODES/REMARKS</th>
                                    <th style="width: 14%;" class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">ARRIVAL FUEL</th>
                                    <th style="width: 14%;" class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">FUEL USED</th>
                                    <th style="width: 14%;" class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">NOTOC</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"><?php echo $i; ?>.</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"><?php echo $i == 1 ? 'dashboard' : ''; ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"><?php echo $i <= 3 ? ' /' : ''; ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"><?php echo $i == 1 ? 'OFP' : ''; ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600"></td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 text-center">
                                        <input type="checkbox" class="checkbox-cell">
                                    </td>
                                </tr>
                                <?php endfor; ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">DOCs CONTOL</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">x) CHECKED</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">y) CONFIRMED</td>
                                    <td colspan="4" class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">z) DISPATCH MANAGER SIGN: REYHANE DAVOODMANESH<br>LICENSE NUMBER:</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Crew Briefing Table -->
                        <table class="form-table mt-4 min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th colspan="2" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">
                                        <strong>CREW BRIEFING</strong>
                                    </th>
                                </tr>
                                <tr>
                                    <th style="width: 50%;" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">
                                        <strong>DEPARTURE AD</strong>
                                    </th>
                                    <th style="width: 50%;" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wider border border-gray-300 dark:border-gray-600">
                                        <strong>DSTN AD</strong>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>☐ DEP / ERA METAR & TAF & SIG Wx</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>☐ DSTN/ DSTN ALTN METAR & TAF & SIG Wx</strong>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>☐ T/O ALTN METAR & TAF & SIG Wx</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>☐ DSTN/ DSTN ALTN NOTAM</strong>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>☐ DEP / ERA NOTAM</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>☐ NAV INADEQUACY</strong>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>☐ T/O ALTN NOTAM</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>☐ MEL/CDL confirmed</strong>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>☐ ATS FPL CONFIRMED</strong>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>☐ COPIES OF MANDATORY DOCS</strong>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>PIC SIGN:</strong><br><br><br>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600">
                                        <strong>DISPATCHER SIGN:</strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
