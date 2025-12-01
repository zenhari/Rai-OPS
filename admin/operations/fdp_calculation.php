<?php
require_once '../../config.php';

// Check access for this page
checkPageAccessWithRedirect('admin/operations/fdp_calculation.php');

$current_user = getCurrentUser();

// Get crew members for filter
$crewMembers = getAllCrewMembersForFDP();

// Initialize empty data - will be loaded progressively
$fdpSummary = [];
$fdpViolations = [];
$dutyViolations = [];
$flightViolations = [];
$filteredData = [];
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FDP Calculation - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">FDP Calculation</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Complete Flight Duty Period calculations for all crew members and all dates</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Start Calculation Button -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <div class="text-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">FDP Calculation</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Click the button below to start calculating FDP for all crew members</p>
                        <button onclick="startFDPCalculation()" id="startCalculationBtn" class="px-8 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors duration-200">
                            <i class="fas fa-calculator mr-2"></i>Start FDP Calculation
                        </button>
                    </div>
                </div>

                <!-- Progress Indicator -->
                <div id="progressIndicator" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6 hidden">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Calculating FDP Data</h3>
                        <span id="progressPercentage" class="text-sm font-medium text-blue-600 dark:text-blue-400">0%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 mb-4">
                        <div id="progressBar" class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <div id="progressMessage" class="text-sm text-gray-600 dark:text-gray-400">Starting calculations...</div>
                    <div id="currentCrew" class="text-sm font-medium text-blue-600 dark:text-blue-400 mt-2"></div>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-md flex items-center justify-center">
                                    <i class="fas fa-users text-blue-600 dark:text-blue-400"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Crew Members</p>
                                <p id="totalCrewCount" class="text-2xl font-semibold text-gray-900 dark:text-white">0</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-md flex items-center justify-center">
                                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">FDP Violations</p>
                                <p id="fdpViolationsCount" class="text-2xl font-semibold text-gray-900 dark:text-white">0</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900 rounded-md flex items-center justify-center">
                                    <i class="fas fa-clock text-orange-600 dark:text-orange-400"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Duty Violations</p>
                                <p id="dutyViolationsCount" class="text-2xl font-semibold text-gray-900 dark:text-white">0</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-100 dark:bg-yellow-900 rounded-md flex items-center justify-center">
                                    <i class="fas fa-plane text-yellow-600 dark:text-yellow-400"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Flight Time Violations</p>
                                <p id="flightViolationsCount" class="text-2xl font-semibold text-gray-900 dark:text-white">0</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                            <button onclick="showTab('summary')" id="tab-summary" class="tab-button py-4 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600 dark:text-blue-400">
                                <i class="fas fa-chart-bar mr-2"></i>Summary
                            </button>
                            <button onclick="showTab('violations')" id="tab-violations" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300">
                                <i class="fas fa-exclamation-triangle mr-2"></i>Violations
                            </button>
                            <button onclick="showTab('details')" id="tab-details" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300">
                                <i class="fas fa-list mr-2"></i>Detailed Data
                            </button>
                        </nav>
                    </div>

                    <!-- Summary Tab -->
                    <div id="content-summary" class="tab-content p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Crew Member</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Duty Days</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Sectors</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight Hours</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FDP Hours</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Duty Hours</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Duty</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="summaryTableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex items-center justify-center">
                                                <i class="fas fa-calculator text-2xl text-gray-400 mr-3"></i>
                                                <span>Click "Start FDP Calculation" to begin...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Violations Tab -->
                    <div id="content-violations" class="tab-content p-6 hidden">
                        <!-- Filters -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Filters</h4>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date Range</label>
                                    <input type="date" id="violationDateFrom" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-600 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                                    <input type="date" id="violationDateTo" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-600 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Crew Member</label>
                                    <input type="text" id="violationCrewFilter" placeholder="Search crew..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-600 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Violation Type</label>
                                    <select id="violationTypeFilter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-600 dark:text-white">
                                        <option value="">All Types</option>
                                        <option value="fdp">FDP Violations</option>
                                        <option value="duty">Duty Time Violations</option>
                                        <option value="flight">Flight Time Violations</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <!-- FDP Violations -->
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-medium text-gray-900 dark:text-white">FDP Violations</h4>
                                    <button onclick="exportViolations('fdp')" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                        <i class="fas fa-download mr-1"></i>Export
                                    </button>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Crew Member</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Position</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sectors</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FDP Start</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FDP End</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FDP Hours</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Max Allowed</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight Hours</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Routes</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aircraft</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="fdpViolationsTableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr>
                                                <td colspan="12" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                    No FDP violations found
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Duty Time Violations -->
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-medium text-gray-900 dark:text-white">Duty Time Violations</h4>
                                    <button onclick="exportViolations('duty')" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                        <i class="fas fa-download mr-1"></i>Export
                                    </button>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Crew Member</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Position</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">7-Day</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">14-Day</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">28-Day</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Violation Type</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Base</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Flights</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Duty</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="dutyViolationsTableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr>
                                                <td colspan="11" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                    No duty violations found
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Flight Time Violations -->
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-medium text-gray-900 dark:text-white">Flight Time Violations</h4>
                                    <button onclick="exportViolations('flight')" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                        <i class="fas fa-download mr-1"></i>Export
                                    </button>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Crew Member</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Position</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">28-Day</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Calendar Year</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">12-Month</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Violation Type</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Base</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Flights</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Flight</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="flightViolationsTableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr>
                                                <td colspan="11" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                    No flight time violations found
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Data Tab -->
                    <div id="content-details" class="tab-content p-6 hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Crew Member</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sectors</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FDP Start</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FDP End</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">FDP Hours</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Routes</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (empty($filteredData)): ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                No data found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($filteredData as $data): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($data['crew_member']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo date('M j, Y', strtotime($data['date'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo $data['sectors']; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo date('H:i', strtotime($data['fdp_start'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo date('H:i', strtotime($data['fdp_end'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo number_format($data['fdp_hours'], 1); ?>h
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php if ($data['fdp_hours'] > 14): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                            EXCEEDED
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                            COMPLIANT
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($data['routes']); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- FDP Calculation Methodology Section -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mt-6">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-6">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                            <i class="fas fa-book mr-2 text-blue-600 dark:text-blue-400"></i>
                            FDP Calculation Methodology
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Complete explanation of Flight Duty Period calculation methods and related limitations
                        </p>
                    </div>

                    <div class="space-y-6">
                        <!-- Basic Data & Time Reference -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-clock mr-2 text-blue-600 dark:text-blue-400"></i>
                                1. Basic Data & Time Reference
                            </h3>
                            <div class="space-y-3 text-gray-700 dark:text-gray-300">
                                <p><strong>1.1</strong> All calculations are performed in <strong>local / reference time</strong> as defined in OM-A 7.2.</p>
                                <p><strong>1.2</strong> For each sector for each crew member, RAIOPS reads from the flights/roster tables:</p>
                                <ul class="list-disc list-inside ml-4 space-y-1">
                                    <li><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">TaskStart</code> = <strong>OFF-BLOCK time</strong></li>
                                    <li><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">TaskEnd</code> = <strong>ON-BLOCK time</strong></li>
                                    <li>Duty type (active sector, positioning, ferry, split duty, etc.)</li>
                                </ul>
                                <p><strong>1.3</strong> Standard <strong>reporting times</strong> are taken from <strong>Table-7 – Standard Reporting Time (7.3.8.2)</strong>:</p>
                                <div class="bg-white dark:bg-gray-800 rounded p-3 mt-2">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-100 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Duty Type</th>
                                                <th class="px-3 py-2 text-right">Reporting Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="border-t border-gray-200 dark:border-gray-600">
                                                <td class="px-3 py-2">First leg active with PAX – Domestic</td>
                                                <td class="px-3 py-2 text-right font-mono">00:45</td>
                                            </tr>
                                            <tr class="border-t border-gray-200 dark:border-gray-600">
                                                <td class="px-3 py-2">First leg active with PAX – International</td>
                                                <td class="px-3 py-2 text-right font-mono">01:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-200 dark:border-gray-600">
                                                <td class="px-3 py-2">First leg active with NO PAX (ferry/delivery/local) or positioning/split duty</td>
                                                <td class="px-3 py-2 text-right font-mono">00:30</td>
                                            </tr>
                                            <tr class="border-t border-gray-200 dark:border-gray-600">
                                                <td class="px-3 py-2">Simulator positioning</td>
                                                <td class="px-3 py-2 text-right font-mono">01:30</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p><strong>1.4</strong> <strong>Post-flight duty times</strong> are taken from <strong>Table-1 – Post flight Duty (7.4.2.2.1)</strong>:</p>
                                <div class="bg-white dark:bg-gray-800 rounded p-3 mt-2">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-100 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Duty Type</th>
                                                <th class="px-3 py-2 text-right">Post-Flight Duty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="border-t border-gray-200 dark:border-gray-600">
                                                <td class="px-3 py-2">Last leg active with PAX</td>
                                                <td class="px-3 py-2 text-right font-mono">00:20</td>
                                            </tr>
                                            <tr class="border-t border-gray-200 dark:border-gray-600">
                                                <td class="px-3 py-2">Crew positioning / split duty</td>
                                                <td class="px-3 py-2 text-right font-mono">00:20</td>
                                            </tr>
                                            <tr class="border-t border-gray-200 dark:border-gray-600">
                                                <td class="px-3 py-2">Last leg active with NO PAX (positioning/ferry/delivery/local)</td>
                                                <td class="px-3 py-2 text-right font-mono">00:10</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- FDP Definition & Calculation -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-info-circle mr-2 text-green-600 dark:text-green-400"></i>
                                2. Flight Duty Period (FDP) Definition & Calculation
                            </h3>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4 border-l-4 border-blue-500">
                                <p class="text-gray-700 dark:text-gray-300 mb-2">
                                    <strong>Definition:</strong> FDP is the period from <strong>reporting time</strong> for the first duty that includes at least one operating sector, until <strong>ON-BLOCK time</strong> of the last operating sector in that duty, in accordance with OM-A 7.2 and 7.3.1.
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    <strong>Note:</strong> FDP does <strong>NOT</strong> include the post-flight duty time; that is part of the <strong>duty period</strong>, not FDP.
                                </p>
                            </div>
                            
                            <div class="space-y-4">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-blue-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">2.1 FDP Start</h4>
                                    <p class="text-gray-700 dark:text-gray-300 mb-2">
                                        <strong>FDP Start = First TaskStart – StandardReportingTime</strong>
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        If an explicit reporting time is present in the roster, RAIOPS uses that. Otherwise, RAIOPS calculates using StandardReportingTime from Table-7 according to duty type (domestic PAX, international, positioning, etc.).
                                    </p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-green-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">2.2 FDP End</h4>
                                    <p class="text-gray-700 dark:text-gray-300 mb-2">
                                        <strong>FDP End = TaskEnd of the last operating sector</strong>
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        FDP does NOT include the post-flight duty time; that is part of the duty period, not FDP (per 7.4.2.2.1).
                                    </p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-purple-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">2.3 FDP Hours</h4>
                                    <p class="text-gray-700 dark:text-gray-300 mb-2">
                                        <strong>FDP Hours = FDP End – FDP Start</strong>
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        For reports, RAIOPS stores FDP both in HH:MM and decimal hours (e.g. 7:20 → 7.33 h).
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Duty Period Definition & Calculation -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-calendar-alt mr-2 text-orange-600 dark:text-orange-400"></i>
                                3. Duty Period Definition & Calculation
                            </h3>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4 border-l-4 border-orange-500">
                                <p class="text-gray-700 dark:text-gray-300 mb-2">
                                    <strong>Definition:</strong> Duty Period runs from <strong>reporting time</strong> (same as FDP start) until the end of <strong>post-flight duty</strong> after the last sector, in line with OM-A 7.2 & 7.4.2.2.1.
                                </p>
                            </div>
                            
                            <div class="space-y-4">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-orange-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">3.1 Duty Start</h4>
                                    <p class="text-gray-700 dark:text-gray-300 mb-2">
                                        <strong>Duty Start = FDP Start</strong>
                                    </p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-orange-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">3.2 Duty End</h4>
                                    <p class="text-gray-700 dark:text-gray-300 mb-2">
                                        <strong>Duty End = Last TaskEnd + PostFlightDutyTime</strong>
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        PostFlightDutyTime is taken from Table-1 Post Flight Duty (7.4.2.2.1) according to the last duty type of the day.
                                    </p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-orange-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">3.3 Duty Hours</h4>
                                    <p class="text-gray-700 dark:text-gray-300 mb-2">
                                        <strong>Duty Hours = Duty End – Duty Start</strong>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Flight Time & Sectors -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-plane mr-2 text-red-600 dark:text-red-400"></i>
                                4. Flight Time & Sectors
                            </h3>
                            
                            <div class="space-y-4">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-red-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">4.1 Flight Time (Block Time)</h4>
                                    <p class="text-gray-700 dark:text-gray-300 mb-2">
                                        <strong>Flight Hours = Σ (TaskEnd – TaskStart)</strong>
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        For all operating sectors that day on which the crew member is assigned as operating crew, in line with 7.3.1. Stored in both HH:MM and decimal hours.
                                    </p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-indigo-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">4.2 Sectors</h4>
                                    <p class="text-gray-700 dark:text-gray-300 mb-2">
                                        <strong>Sectors = count of all flight legs (TaskStart–TaskEnd pairs) where crew is operating crew</strong>
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Each OFF-BLOCK → ON-BLOCK leg = 1 sector, as per OM-A definition of "Sector".
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- FDP Limits -->
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-exclamation-triangle mr-2 text-red-600 dark:text-red-400"></i>
                                5. FDP Limits & Violations
                            </h3>
                            
                            <div class="space-y-4 mb-4">
                                <p class="text-gray-700 dark:text-gray-300">
                                    Instead of a fixed "14 hours" limit, RAIOPS must apply the <strong>maximum FDP tables from Chapter 7</strong>.
                                </p>
                                
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">5.1 Maximum Daily FDP (basic)</h4>
                                    <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                        <li>RAIOPS computes <strong>reference time</strong> and determines whether the crew is <strong>acclimatised</strong> or in <strong>unknown state of acclimatisation</strong>, as per OM-A 7.2 & 7.3.1.1.</li>
                                        <li>RAIOPS then reads the <strong>maximum basic daily FDP</strong> from:
                                            <ul class="list-disc list-inside ml-6 mt-1">
                                                <li><strong>Table-1: Maximum daily FDP — acclimatised crew members</strong>, or</li>
                                                <li><strong>Table-2: Maximum daily FDP — crew in unknown state of acclimatisation</strong>,</li>
                                            </ul>
                                            using:
                                            <ul class="list-disc list-inside ml-6 mt-1">
                                                <li>Start of FDP at reference time, and</li>
                                                <li>Number of Sectors</li>
                                            </ul>
                                        </li>
                                    </ol>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">5.2 Extensions & Discretion</h4>
                                    <ul class="list-disc list-inside space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                        <li>If an <strong>extension</strong> under 7.3.2 is planned and approved (up to +1 hour, not more than twice in 7 days, and within WOCL & sector limits), RAIOPS adds the permitted extension to Max_FDP_Basic.</li>
                                        <li>If <strong>commander's discretion</strong> is used under 7.3.2.1, RAIOPS records actual FDP, allowed FDP limit, amount of discretion, and link to the discretion report.</li>
                                    </ul>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">5.3 FDP Violation Logic</h4>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">
                                        RAIOPS flags an FDP <strong>violation</strong> when:
                                    </p>
                                    <p class="text-sm font-mono bg-gray-100 dark:bg-gray-700 p-2 rounded">
                                        FDP_Hours > Max_FDP_Allowed
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                        where Max_FDP_Allowed = Max_FDP_Basic (no planned extension) or Max_FDP_Planned (with extension), unless an appropriate discretion record exists.
                                    </p>
                                </div>
                            </div>

                            <!-- Table-1: Acclimatised crew members -->
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-3">5.1.1 Table-1 – Maximum daily FDP — Acclimatised crew members</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    The maximum daily FDP <strong>without the use of extensions</strong> for acclimatised crew members shall be in accordance with the values in Table-1 below.
                                </p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-xs border border-gray-300 dark:border-gray-600">
                                        <thead class="bg-gray-100 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-2 py-2 text-left border border-gray-300 dark:border-gray-600">Start of FDP at reference time</th>
                                                <th class="px-2 py-2 text-center border border-gray-300 dark:border-gray-600">1–2 Sectors</th>
                                                <th class="px-2 py-2 text-center border border-gray-300 dark:border-gray-600">3 Sectors</th>
                                                <th class="px-2 py-2 text-center border border-gray-300 dark:border-gray-600">4 Sectors</th>
                                                <th class="px-2 py-2 text-center border border-gray-300 dark:border-gray-600">5 Sectors</th>
                                                <th class="px-2 py-2 text-center border border-gray-300 dark:border-gray-600">6 Sectors</th>
                                                <th class="px-2 py-2 text-center border border-gray-300 dark:border-gray-600">7 Sectors</th>
                                                <th class="px-2 py-2 text-center border border-gray-300 dark:border-gray-600">8 Sectors</th>
                                                <th class="px-2 py-2 text-center border border-gray-300 dark:border-gray-600">9 Sectors</th>
                                                <th class="px-2 py-2 text-center border border-gray-300 dark:border-gray-600">10 Sectors</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-gray-900 dark:text-white">
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">0600–1329</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">13:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">1330–1359</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">1400–1429</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">1430–1459</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">1500–1529</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">1530–1559</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">1600–1629</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">1630–1659</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">1700–0459</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">0500–0514</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">0515–0529</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">0530–0544</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:30</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 font-mono text-xs">0545–0559</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">12:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">11:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">10:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:45</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:15</td>
                                                <td class="px-2 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono">09:00</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Table-2: Unknown state of acclimatisation -->
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-3">5.1.2 Table-2 – Maximum daily FDP — Unknown state of acclimatisation</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    The maximum daily FDP when crew members are in an <strong>unknown state of acclimatisation</strong> shall be in accordance with Table-2 below.
                                </p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-xs border border-gray-300 dark:border-gray-600">
                                        <thead class="bg-gray-100 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-3 py-2 text-left border border-gray-300 dark:border-gray-600">Sectors</th>
                                                <th class="px-3 py-2 text-center border border-gray-300 dark:border-gray-600">1</th>
                                                <th class="px-3 py-2 text-center border border-gray-300 dark:border-gray-600">2</th>
                                                <th class="px-3 py-2 text-center border border-gray-300 dark:border-gray-600">3</th>
                                                <th class="px-3 py-2 text-center border border-gray-300 dark:border-gray-600">4</th>
                                                <th class="px-3 py-2 text-center border border-gray-300 dark:border-gray-600">5</th>
                                                <th class="px-3 py-2 text-center border border-gray-300 dark:border-gray-600">6</th>
                                                <th class="px-3 py-2 text-center border border-gray-300 dark:border-gray-600">7</th>
                                                <th class="px-3 py-2 text-center border border-gray-300 dark:border-gray-600">8</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 font-semibold">Maximum daily FDP (hh:mm)</td>
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono text-gray-900 dark:text-white">11:00</td>
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono text-gray-900 dark:text-white">11:00</td>
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono text-gray-900 dark:text-white">10:30</td>
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono text-gray-900 dark:text-white">10:00</td>
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono text-gray-900 dark:text-white">09:30</td>
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono text-gray-900 dark:text-white">09:00</td>
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono text-gray-900 dark:text-white">09:00</td>
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-center font-mono text-gray-900 dark:text-white">09:00</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-3">
                                    If RAIOPS evaluates a crew as <strong>"unknown state of acclimatisation"</strong> (per acclimatisation Table-1 in 7.2), then it must ignore Table-1 (acclimatised) and use this Table-2 instead for Max_FDP_Basic.
                                </p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">6. Duty Time & Flight Time Cumulative Limits</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        RAIOPS uses the cumulative limits given in 7.3.1 Flight Times and Duty Periods:
                                    </p>
                                    <h5 class="font-semibold text-gray-900 dark:text-white mb-2">6.1 Duty Time Limits</h5>
                                    <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300 mb-4">
                                        <li class="flex items-start">
                                            <i class="fas fa-check-circle text-red-500 mr-2 mt-1"></i>
                                            <span><strong>60 duty hours</strong> in any <strong>7</strong> consecutive days</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-check-circle text-red-500 mr-2 mt-1"></i>
                                            <span><strong>110 duty hours</strong> in any <strong>14</strong> consecutive days</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-check-circle text-red-500 mr-2 mt-1"></i>
                                            <span><strong>190 duty hours</strong> in any <strong>28</strong> consecutive days</span>
                                        </li>
                                    </ul>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                        RAIOPS maintains rolling windows over the roster and flags when totals exceed these limits.
                                    </p>
                                    <h5 class="font-semibold text-gray-900 dark:text-white mb-2 mt-4">6.2 Flight Time Limits</h5>
                                    <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                        <li class="flex items-start">
                                            <i class="fas fa-check-circle text-red-500 mr-2 mt-1"></i>
                                            <span><strong>100 flight hours</strong> in any <strong>28</strong> consecutive days</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-check-circle text-red-500 mr-2 mt-1"></i>
                                            <span><strong>900 flight hours</strong> in a <strong>calendar year</strong></span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-check-circle text-red-500 mr-2 mt-1"></i>
                                            <span><strong>1000 flight hours</strong> in any <strong>12</strong> consecutive calendar months</span>
                                        </li>
                                    </ul>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                        Again, RAIOPS keeps rolling totals and flags exceedances.
                                    </p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Important Notes</h4>
                                    <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                        <li class="flex items-start">
                                            <i class="fas fa-info-circle text-blue-500 mr-2 mt-1"></i>
                                            <span>All calculations are performed in <strong>local / reference time</strong> as defined in OM-A 7.2</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-info-circle text-blue-500 mr-2 mt-1"></i>
                                            <span>TaskStart and TaskEnd are extracted from the flights/roster tables</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-info-circle text-blue-500 mr-2 mt-1"></i>
                                            <span>Calculations are performed separately for each crew member</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-info-circle text-blue-500 mr-2 mt-1"></i>
                                            <span>FDP and Duty Period are <strong>separate</strong> calculations (FDP does NOT include post-flight duty)</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Example Calculation -->
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-lightbulb mr-2 text-yellow-600 dark:text-yellow-400"></i>
                                7. Calculation Example
                            </h3>
                            
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                                <p class="text-gray-700 dark:text-gray-300 mb-3">
                                    <strong>Assumption:</strong> Two <strong>domestic PAX</strong> flights in one day:
                                </p>
                                <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300 mb-4">
                                    <li>Flight 1: TaskStart = 08:00, TaskEnd = 10:30</li>
                                    <li>Flight 2: TaskStart = 12:00, TaskEnd = 14:15</li>
                                    <li>Standard reporting for first leg with PAX, domestic: <strong>00:45</strong> (Table-7)</li>
                                    <li>Post-flight duty for last leg active with PAX: <strong>00:20</strong> (Table-1)</li>
                                </ul>
                                
                                <div class="bg-gray-50 dark:bg-gray-700 rounded p-3 space-y-2 text-sm">
                                    <p><strong>1. FDP Start:</strong> 08:00 - 00:45 = <strong>07:15</strong></p>
                                    <p><strong>2. FDP End:</strong> 14:15 (last TaskEnd, <strong>NOT</strong> including post-flight duty)</p>
                                    <p><strong>3. FDP Hours:</strong> 07:15 → 14:15 = <strong>7:00 h</strong></p>
                                    <p><strong>4. Duty Start:</strong> 07:15 (same as FDP Start)</p>
                                    <p><strong>5. Duty End:</strong> 14:15 + 00:20 = <strong>14:35</strong></p>
                                    <p><strong>6. Duty Hours:</strong> 07:15 → 14:35 = <strong>7:20 h = 7.33 h</strong></p>
                                    <p><strong>7. Flight Hours:</strong> (10:30 - 08:00) + (14:15 - 12:00) = 2:30 + 2:15 = <strong>4:45 h = 4.75 h</strong></p>
                                    <p><strong>8. Sectors:</strong> <strong>2</strong></p>
                                    <p class="mt-3 pt-3 border-t border-gray-300 dark:border-gray-600">
                                        <strong>9. Max FDP Check:</strong> RAIOPS finds Max_FDP_Basic from Table-1 based on FDP start reference time (07:15 falls in 0500-0514 range) and 2 sectors = <strong>12:00</strong>. Since FDP Hours (7:00) ≤ Max_FDP_Basic (12:00), <strong>no violation</strong>.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RAIOPS FTL Calculation Rules Section -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mt-6">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-6">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                            <i class="fas fa-book-open mr-2 text-purple-600 dark:text-purple-400"></i>
                            RAIOPS FTL Calculation Rules (Implementation of OM-A Chapter 7)
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Complete implementation of all FTL topics including split duty, standby, reserve, rest types, delayed reporting, positioning, extensions, discretion, and more
                        </p>
                    </div>

                    <div class="space-y-6">
                        <!-- 1. Positioning -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-map-marker-alt mr-2 text-blue-600 dark:text-blue-400"></i>
                                1. Positioning (OM-A 7.3.5)
                            </h3>
                            <div class="space-y-3 text-gray-700 dark:text-gray-300">
                                <p><strong>Definition:</strong> Any duty with type = POSITIONING and the crew not operating is "positioning".</p>
                                
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-blue-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Counting as Duty and FDP</h4>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li>All positioning time counts as <strong>Duty Time</strong>.</li>
                                        <li>If positioning is after report but before an operating sector:
                                            <ul class="list-disc list-inside ml-6 mt-1">
                                                <li>It counts inside FDP duration, but</li>
                                                <li>It does <strong>not</strong> count as a sector.</li>
                                            </ul>
                                        </li>
                                        <li>If positioning is after last sector, it is duty only, not FDP.</li>
                                    </ul>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-blue-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Cumulative Limits</h4>
                                    <p class="text-sm">Positioning minutes must be included in the 7/14/28-day duty totals and used when checking against duty limits (60/110/190 h).</p>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Standby -->
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-clock mr-2 text-green-600 dark:text-green-400"></i>
                                2. Standby (Home / Other Standby) – OM-A 7.3.6
                            </h3>
                            <div class="space-y-3 text-gray-700 dark:text-gray-300">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-green-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Data Fields</h4>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">standby_type</code> (OTHER_STBY; no airport standby planned)</li>
                                        <li><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">standby_start_local</code>, <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">standby_end_local</code></li>
                                        <li><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">call_time</code> (if crew is called)</li>
                                        <li><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">report_time</code> (if FDP assigned)</li>
                                        <li><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">assigned_duty_id</code> (NULL if no duty)</li>
                                    </ul>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-green-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Max Duration</h4>
                                    <p class="text-sm">Other (home/other) standby must not exceed <strong>16 hours</strong>.</p>
                                    <p class="text-sm font-mono bg-gray-100 dark:bg-gray-700 p-2 rounded mt-2">if (standby_end − standby_start) > 16:00 → flag FTL violation: "STANDBY > 16h"</p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-green-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Counting Standby as Duty</h4>
                                    <p class="text-sm">25% of "other standby" duration counts as Duty Time for ORO.FTL.210 limits.</p>
                                    <p class="text-sm font-mono bg-gray-100 dark:bg-gray-700 p-2 rounded mt-2">standby_duty_equivalent = 0.25 × standby_duration</p>
                                    <p class="text-sm mt-2">Add this to cumulative duty hours, <strong>not</strong> to FDP.</p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-green-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Impact on Max FDP when Called from Standby</h4>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li>Compute time between <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">standby_start</code> and <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">report_time</code> as <strong>STBY_USED</strong>.</li>
                                        <li>If <strong>STBY_USED ≤ 6h</strong> → max FDP is taken normally from FDP tables starting at report_time.</li>
                                        <li>If <strong>STBY_USED > 6h</strong> → max_FDP_from_table is reduced by (STBY_USED − 6h).</li>
                                        <li>If split duty is used later in that FDP, the "6 hours" threshold becomes <strong>8 hours</strong> instead of 6.</li>
                                        <li><strong>Night standby special rule (23:00–07:00):</strong> If standby starts between 23:00 and 07:00, the time between 23:00 and 07:00 does not count towards FDP reduction until crew is contacted.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Airport Standby -->
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-plane-departure mr-2 text-yellow-600 dark:text-yellow-400"></i>
                                3. Airport Standby (OM-A 7.3.7)
                            </h3>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-yellow-500">
                                <p class="text-sm text-gray-700 dark:text-gray-300">OM-A states Raimon does not plan airport standby for flight or cabin crew.</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-2"><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">standby_type = AIRPORT_STBY</code> should normally not be used. If ever used in future, separate logic may be added (but currently not applicable).</p>
                            </div>
                        </div>

                        <!-- 4. Standby Scheme -->
                        <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-calendar-alt mr-2 text-indigo-600 dark:text-indigo-400"></i>
                                4. Standby Scheme (Table-5)
                            </h3>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-indigo-500">
                                <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">RAIOPS must support three patterns:</p>
                                <table class="min-w-full text-sm border border-gray-300 dark:border-gray-600">
                                    <thead class="bg-gray-100 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-3 py-2 text-left border border-gray-300 dark:border-gray-600">Code</th>
                                            <th class="px-3 py-2 text-left border border-gray-300 dark:border-gray-600">Meaning (local time)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="border-t border-gray-300 dark:border-gray-600">
                                            <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 font-mono">AM</td>
                                            <td class="px-3 py-2 border border-gray-300 dark:border-gray-600">Standby 00:00 – 11:59</td>
                                        </tr>
                                        <tr class="border-t border-gray-300 dark:border-gray-600">
                                            <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 font-mono">PM</td>
                                            <td class="px-3 py-2 border border-gray-300 dark:border-gray-600">Standby 12:00 – 23:59</td>
                                        </tr>
                                        <tr class="border-t border-gray-300 dark:border-gray-600">
                                            <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 font-mono">8H</td>
                                            <td class="px-3 py-2 border border-gray-300 dark:border-gray-600">8-hour standby with start & end defined</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-3">If crew is not available during assigned standby window, system may flag <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">ABSENT_ON_STANDBY</code> – to be reported by DISPATCH to Crew Scheduling.</p>
                            </div>
                        </div>

                        <!-- 5. Home Base / Dual Base -->
                        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-home mr-2 text-purple-600 dark:text-purple-400"></i>
                                5. Home Base / Dual Base / Travel Time (OM-A 7.3.8)
                            </h3>
                            <div class="space-y-3 text-gray-700 dark:text-gray-300">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-purple-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Home Base Table</h4>
                                    <p class="text-sm">THR (OIII), IKA (OIIE), RAS (OIGG)</p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-purple-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Rules</h4>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li>Each crew has a home base code (or dual base).</li>
                                        <li>For home base change: First recurrent extended recovery rest before duties at new base must be ≥ 72 hours, including 3 local nights.</li>
                                        <li>Travel time between old base and new base is recorded as positioning duty.</li>
                                        <li>Travel time from residence to home base: If usual travel time > 90 min, crew should consider temporary accommodation closer to base (informational, not a numeric check).</li>
                                        <li><strong>Dual base THR/IKA:</strong> RAIOPS may treat both airports as a single home/dual base regarding FTL calculations.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- 6. Reserve -->
                        <div class="bg-pink-50 dark:bg-pink-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-user-clock mr-2 text-pink-600 dark:text-pink-400"></i>
                                6. Reserve (OM-A 7.3.9)
                            </h3>
                            <div class="space-y-3 text-gray-700 dark:text-gray-300">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-pink-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Data Fields</h4>
                                    <p class="text-sm"><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">reserve_start</code>, <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">reserve_end</code>, <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">assigned_duty_id</code>, <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">rest_given_if_not_called</code></p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-pink-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Rules</h4>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li>If crew on reserve is assigned to a duty: FDP starts at reporting time for that assigned duty.</li>
                                        <li><strong>Counting as duty:</strong> Reserve time itself does <strong>not</strong> count as duty for the purpose of ORO.FTL.210 and ORO.FTL.235 cumulative duty/rest limits.</li>
                                        <li><strong>Limits on consecutive reserve days:</strong> Max 7 consecutive reserve days.</li>
                                        <li><strong>Rest on reserve:</strong> If crew is not contacted, they still must receive at least 10 hours rest, including 8 hours sleep opportunity.</li>
                                        <li>Recurrent extended rest rules (36h / 168h etc.) also apply while on reserve.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- 7. Split Duty -->
                        <div class="bg-teal-50 dark:bg-teal-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-pause-circle mr-2 text-teal-600 dark:text-teal-400"></i>
                                7. Split Duty (OM-A 7.4.1)
                            </h3>
                            <div class="space-y-3 text-gray-700 dark:text-gray-300">
                                <p class="text-sm">Split duty is one FDP with a break on the ground.</p>
                                
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-teal-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Data for Each FDP with Split Duty</h4>
                                    <p class="text-sm"><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">break_start</code>, <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">break_end</code>, <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">suitable_accommodation_provided</code>, <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">WOCL_encroached</code></p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-teal-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Minimum Ground Break</h4>
                                    <p class="text-sm">BREAK_NET - 00:50 (50 minutes) must be ≥ 3:00 h.</p>
                                    <p class="text-sm font-mono bg-gray-100 dark:bg-gray-700 p-2 rounded mt-2">if (BREAK_NET - 00:50) < 3:00 and split flag is used → no FDP extension allowed</p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-teal-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Maximum FDP Extension from Split Duty</h4>
                                    <p class="text-sm">FDP may be increased by up to 50% of BREAK_NET.</p>
                                    <p class="text-sm font-mono bg-gray-100 dark:bg-gray-700 p-2 rounded mt-2">FDP_max_split = FDP_max_normal + 0.5 × BREAK_NET</p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-teal-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Accommodation Requirements</h4>
                                    <p class="text-sm">Suitable accommodation must be provided:</p>
                                    <ul class="list-disc list-inside space-y-1 text-sm mt-1">
                                        <li>For breaks ≥ 6:00, or</li>
                                        <li>For breaks that touch WOCL (02:00–05:59)</li>
                                    </ul>
                                    <p class="text-sm mt-2">In all other split duty cases, at least accommodation (quiet place) is provided.</p>
                                </div>
                            </div>
                        </div>

                        <!-- 8. Rest Periods -->
                        <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-bed mr-2 text-orange-600 dark:text-orange-400"></i>
                                8. Rest Periods (OM-A 7.4.2)
                            </h3>
                            <div class="space-y-3 text-gray-700 dark:text-gray-300">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-orange-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">8.2 Minimum Rest for All Crew (7.4.2.2)</h4>
                                    <p class="text-sm mb-2"><strong>Key rule:</strong> Minimum rest is at least the length of the preceding duty or 10 hours, whichever is greater.</p>
                                    <ul class="list-disc list-inside space-y-1 text-sm mt-2">
                                        <li><strong>Before FDP starting at home base:</strong> Minimum rest: max(preceding_duty_duration, 12:00)</li>
                                        <li><strong>Before FDP starting away from home base:</strong> Minimum rest: max(preceding_duty_duration, 10:00)</li>
                                        <li>Within that 10 h, operator must ensure 8 hours sleep opportunity, considering travel and other needs.</li>
                                    </ul>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-orange-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">8.3 Post-Flight Duty (7.4.2.2.1 – Table-1)</h4>
                                    <p class="text-sm mb-2">Post-flight time must be added to FDP end to obtain Duty End.</p>
                                    <table class="min-w-full text-xs border border-gray-300 dark:border-gray-600 mt-2">
                                        <thead class="bg-gray-100 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-3 py-2 text-left border border-gray-300 dark:border-gray-600">Duty Type</th>
                                                <th class="px-3 py-2 text-right border border-gray-300 dark:border-gray-600">Post-flight duty added</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600">Last leg active, with PAX</td>
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-right font-mono">00:20</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600">Crew positioning, Split duty last leg</td>
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-right font-mono">00:20</td>
                                            </tr>
                                            <tr class="border-t border-gray-300 dark:border-gray-600">
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600">Last leg active with no PAX (positioning / ferry / delivery / local)</td>
                                                <td class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-right font-mono">00:10</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-orange-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">8.4 Recurrent Extended Recovery Rest (7.4.2.3)</h4>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li>Regular extended recovery rest: Minimum 36 hours, including 2 local nights.</li>
                                        <li>Time between end of one extended recovery rest and start of the next: ≤ 168 hours.</li>
                                        <li>Twice per month: Extended recovery rest is increased to 2 local days.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- 9. Nutrition -->
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-utensils mr-2 text-red-600 dark:text-red-400"></i>
                                9. Nutrition (OM-A 7.4.3)
                            </h3>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-red-500">
                                <p class="text-sm text-gray-700 dark:text-gray-300">For FDP > 6 hours, there must be meal and drink opportunities.</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">At least two meals plus refreshments and fruit if FDP is long.</p>
                                <p class="text-sm font-mono bg-gray-100 dark:bg-gray-700 p-2 rounded mt-2">When FDP > 6:00, show alert: "Ensure 2 meal opportunities planned (OM-A 7.4.3)."</p>
                            </div>
                        </div>

                        <!-- 10. FDP Extensions & Discretion -->
                        <div class="bg-cyan-50 dark:bg-cyan-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-exclamation-circle mr-2 text-cyan-600 dark:text-cyan-400"></i>
                                10. FDP Extensions & Discretion (7.3.2 & 7.4.4)
                            </h3>
                            <div class="space-y-3 text-gray-700 dark:text-gray-300">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-cyan-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">10.1 Planned FDP Extensions (without in-flight rest) – OM-A 7.3.2</h4>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li><strong>Max extension:</strong> Daily FDP for acclimatised crew may be extended by up to +1:00.</li>
                                        <li>Not more than twice in 7 consecutive days.</li>
                                        <li><strong>Sector limits when using extension:</strong>
                                            <ul class="list-disc list-inside ml-6 mt-1">
                                                <li>Max 5 sectors if WOCL not encroached</li>
                                                <li>Max 4 sectors if WOCL encroached by ≤ 2h</li>
                                                <li>Max 2 sectors if WOCL encroached by > 2h</li>
                                            </ul>
                                        </li>
                                        <li><strong>Rest increase:</strong> Either increase both pre-flight and post-flight rest by 2:00 each, or increase post-flight rest by 4:00.</li>
                                    </ul>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-cyan-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">10.2 Commander's Discretion (7.3.2.1 & 7.4.4.1 / .2 / .3 / .5)</h4>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li><strong>Maximum:</strong> FDP may be increased by up to 2:00 beyond regulatory max.</li>
                                        <li><strong>Reduction of rest:</strong> Commander may reduce a rest period exceptionally, but rest at accommodation cannot be reduced below 10:00.</li>
                                        <li><strong>When used:</strong> Only in unforeseen circumstances after reporting (emergencies, technical, sick pax, ATC, adverse weather, etc.).</li>
                                        <li>System must flag when FDP is extended beyond normal/extension limit, and require a discretion report reference.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- 11. Delayed Reporting -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-hourglass-half mr-2 text-gray-600 dark:text-gray-400"></i>
                                11. Delayed Reporting (OM-A 7.3.4 & Table-4)
                            </h3>
                            <div class="space-y-3 text-gray-700 dark:text-gray-300">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-gray-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Logic Summary</h4>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li><strong>Delay < 4 hours (first notification):</strong> Max FDP is still calculated based on original reporting time. FDP starts at delayed reporting time.</li>
                                        <li><strong>Delay ≥ 4 hours (first notification):</strong> Max FDP is based on the more limiting of original reporting time, or delayed reporting time. FDP starts at delayed reporting time.</li>
                                        <li><strong>Second notification of further delay:</strong> Max FDP is based on the first delayed reporting time. FDP starts at 1 hour after second notification, or original delayed reporting time, whichever is earlier.</li>
                                        <li><strong>Delay ≥ 10 hours:</strong> If crew is not disturbed during that delay, the delay counts as a rest period. Max FDP is based on new delayed reporting time, FDP starts at that time.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- 12. Records & Scheduling -->
                        <div class="bg-slate-50 dark:bg-slate-900/20 rounded-lg p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fas fa-database mr-2 text-slate-600 dark:text-slate-400"></i>
                                12. Records (OM-A 7.6) & Scheduling Logic (7.7)
                            </h3>
                            <div class="space-y-3 text-gray-700 dark:text-gray-300">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-slate-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">12.1 Records (7.6)</h4>
                                    <p class="text-sm mb-2">RAIOPS must keep for each crew:</p>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li>Start, end, duration of each duty and FDP</li>
                                        <li>Role (PIC/FO/CCM etc.)</li>
                                        <li>Duration of each rest period before a flight duty or standby</li>
                                        <li>Dates of days off</li>
                                        <li>7-day total duty/times</li>
                                    </ul>
                                    <p class="text-sm mt-2">These records kept ≥ 24 months. All commander's discretion reports at least 3 months.</p>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border-l-4 border-slate-500">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">12.2 Scheduling Rules (7.7 – overview)</h4>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li>Duty roster published: Monthly, Flight schedule at least 3 days in advance</li>
                                        <li>Standby only rostered after sufficient rest</li>
                                        <li>Use company software (RAIOPS / backup) to publish rosters (email / SMS)</li>
                                        <li>Reserve / STBY callouts registered and acknowledged</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Crew Details Modal -->
    <div id="crewDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Crew Member Details</h3>
                    <button onclick="closeCrewDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div id="crewDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let fdpSummaryData = [];
        let fdpViolationsData = [];
        let dutyViolationsData = [];
        let flightViolationsData = [];
        let filteredData = [];
        let isCalculating = false;

        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                button.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            });
            
            // Show selected tab content
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Add active class to selected tab button
            const activeButton = document.getElementById('tab-' + tabName);
            activeButton.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            activeButton.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
            
            // Update tab content if needed
            if (tabName === 'violations') {
                updateViolationsTab();
            } else if (tabName === 'details') {
                updateDetailsTab();
            }
        }

        function startFDPCalculation() {
            if (isCalculating) {
                return;
            }

            isCalculating = true;
            const startBtn = document.getElementById('startCalculationBtn');
            const progressIndicator = document.getElementById('progressIndicator');
            
            // Clear previous data
            clearData();
            
            // Update button state
            startBtn.disabled = true;
            startBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Calculating...';
            
            // Show progress indicator
            progressIndicator.classList.remove('hidden');
            
            // Start progressive loading
            const url = `../api/get_fdp_progressive.php`;
            
            // Start progressive loading with fetch and streaming
            fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/event-stream',
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                
                function readStream() {
                    return reader.read().then(({ done, value }) => {
                        if (done) {
                            return;
                        }
                        
                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop(); // Keep incomplete line in buffer
                        
                        for (const line of lines) {
                            if (line.startsWith('data: ')) {
                                try {
                                    const jsonData = line.substring(6); // Remove 'data: ' prefix
                                    const data = JSON.parse(jsonData);
                                    
                                    switch (data.type) {
                                        case 'progress':
                                            updateProgress(data);
                                            break;
                                        case 'crew_completed':
                                            addCrewToTable(data);
                                            break;
                                        case 'completed':
                                            handleCompletion(data);
                                            return;
                                        case 'error':
                                            handleError(data);
                                            return;
                                    }
                                } catch (e) {
                                    console.error('Error parsing SSE data:', e);
                                }
                            }
                        }
                        
                        return readStream();
                    });
                }
                
                return readStream();
            })
            .catch(error => {
                console.error('Fetch error:', error);
                handleError({ message: 'Connection error occurred: ' + error.message });
            });
        }

        function updateProgress(data) {
            const progressBar = document.getElementById('progressBar');
            const progressPercentage = document.getElementById('progressPercentage');
            const progressMessage = document.getElementById('progressMessage');
            const currentCrew = document.getElementById('currentCrew');
            
            progressBar.style.width = data.percentage + '%';
            progressPercentage.textContent = data.percentage + '%';
            progressMessage.textContent = data.message;
            
            if (data.current_crew) {
                currentCrew.textContent = `Processing: ${data.current_crew}`;
            }
        }

        function addCrewToTable(data) {
            if (data.summary) {
                fdpSummaryData.push(data.summary);
                updateSummaryTable();
                updateSummaryCards();
            }
            
            if (data.violations) {
                if (data.violations.fdp_violations) {
                    fdpViolationsData = fdpViolationsData.concat(data.violations.fdp_violations);
                }
                if (data.violations.duty_violations) {
                    dutyViolationsData = dutyViolationsData.concat(data.violations.duty_violations);
                }
                if (data.violations.flight_violations) {
                    flightViolationsData = flightViolationsData.concat(data.violations.flight_violations);
                }
                if (data.violations.filtered_data) {
                    filteredData = filteredData.concat(data.violations.filtered_data);
                }
            }
        }

        function updateSummaryTable() {
            const tbody = document.getElementById('summaryTableBody');
            
            if (fdpSummaryData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            No FDP data found
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            fdpSummaryData.forEach(summary => {
                html += `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                ${summary.crew_member}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${summary.total_duty_days}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${summary.total_sectors}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${summary.total_flight_hours}h
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${summary.total_fdp_hours}h
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${summary.total_duty_hours}h
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${summary.last_duty_date ? new Date(summary.last_duty_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="viewCrewDetails('${summary.crew_member}', ${summary.crew_id})" 
                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        function updateSummaryCards() {
            document.getElementById('totalCrewCount').textContent = fdpSummaryData.length;
            
            const totalViolations = fdpSummaryData.reduce((sum, crew) => sum + crew.fdp_violations, 0);
            document.getElementById('fdpViolationsCount').textContent = totalViolations;
            
            // Update duty and flight violations count
            document.getElementById('dutyViolationsCount').textContent = dutyViolationsData.length;
            document.getElementById('flightViolationsCount').textContent = flightViolationsData.length;
        }

        function clearData() {
            fdpSummaryData = [];
            fdpViolationsData = [];
            dutyViolationsData = [];
            flightViolationsData = [];
            filteredData = [];
            updateSummaryTable();
            updateSummaryCards();
            updateViolationsTab();
            updateDetailsTab();
        }

        function updateViolationsTab() {
            updateFDPViolationsTable();
            updateDutyViolationsTable();
            updateFlightViolationsTable();
        }

        function updateFDPViolationsTable() {
            const tbody = document.getElementById('fdpViolationsTableBody');
            if (!tbody) return;
            
            if (fdpViolationsData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="12" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            No FDP violations found
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            fdpViolationsData.forEach(violation => {
                html += `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            ${violation.crew_member}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${new Date(violation.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.position || 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.sectors}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.fdp_start ? new Date(violation.fdp_start).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.fdp_end ? new Date(violation.fdp_end).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-red-600 dark:text-red-400 font-medium">
                            ${violation.fdp_hours.toFixed(1)}h
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.max_allowed !== undefined && violation.max_allowed !== null ? violation.max_allowed.toFixed(1) + 'h' : '14.0h'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.flight_hours !== undefined && violation.flight_hours !== null ? violation.flight_hours.toFixed(1) + 'h' : 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.routes || 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.aircraft || 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button onclick="viewViolationDetails('fdp', '${violation.crew_member}', '${violation.date}')" 
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="sendViolationAlert('fdp', '${violation.crew_member}', '${violation.date}')" 
                                        class="text-orange-600 hover:text-orange-900 dark:text-orange-400 dark:hover:text-orange-300" title="Send Alert">
                                    <i class="fas fa-bell"></i>
                                </button>
                                <button onclick="markViolationResolved('fdp', '${violation.crew_member}', '${violation.date}')" 
                                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300" title="Mark Resolved">
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        function updateDutyViolationsTable() {
            const tbody = document.getElementById('dutyViolationsTableBody');
            if (!tbody) return;
            
            if (dutyViolationsData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            No duty violations found
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            dutyViolationsData.forEach(violation => {
                const violationType = getDutyViolationType(violation);
                html += `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            ${violation.crew_member}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${new Date(violation.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.position || 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.duty_7d ? violation.duty_7d.toFixed(1) + 'h' : '0.0h'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.duty_14d ? violation.duty_14d.toFixed(1) + 'h' : '0.0h'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.duty_28d ? violation.duty_28d.toFixed(1) + 'h' : '0.0h'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                ${violationType}
                            </span>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.base || 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.total_flights || 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.last_duty_date ? new Date(violation.last_duty_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button onclick="viewViolationDetails('duty', '${violation.crew_member}', '${violation.date}')" 
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="sendViolationAlert('duty', '${violation.crew_member}', '${violation.date}')" 
                                        class="text-orange-600 hover:text-orange-900 dark:text-orange-400 dark:hover:text-orange-300" title="Send Alert">
                                    <i class="fas fa-bell"></i>
                                </button>
                                <button onclick="markViolationResolved('duty', '${violation.crew_member}', '${violation.date}')" 
                                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300" title="Mark Resolved">
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        function updateFlightViolationsTable() {
            const tbody = document.getElementById('flightViolationsTableBody');
            if (!tbody) return;
            
            if (flightViolationsData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            No flight time violations found
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            flightViolationsData.forEach(violation => {
                const violationType = getFlightViolationType(violation);
                html += `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            ${violation.crew_member}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${new Date(violation.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.position || 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.flight_28d ? violation.flight_28d.toFixed(1) + 'h' : '0.0h'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.flight_calendar ? violation.flight_calendar.toFixed(1) + 'h' : '0.0h'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.flight_12mo ? violation.flight_12mo.toFixed(1) + 'h' : '0.0h'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                ${violationType}
                            </span>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.base || 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.total_flights || 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${violation.last_flight_date ? new Date(violation.last_flight_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : 'N/A'}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button onclick="viewViolationDetails('flight', '${violation.crew_member}', '${violation.date}')" 
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="sendViolationAlert('flight', '${violation.crew_member}', '${violation.date}')" 
                                        class="text-orange-600 hover:text-orange-900 dark:text-orange-400 dark:hover:text-orange-300" title="Send Alert">
                                    <i class="fas fa-bell"></i>
                                </button>
                                <button onclick="markViolationResolved('flight', '${violation.crew_member}', '${violation.date}')" 
                                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300" title="Mark Resolved">
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        function getDutyViolationType(violation) {
            if (violation.duty_7d && violation.duty_7d > 60) return '7-day limit exceeded';
            if (violation.duty_14d && violation.duty_14d > 110) return '14-day limit exceeded';
            if (violation.duty_28d && violation.duty_28d > 190) return '28-day limit exceeded';
            return 'Duty Limit';
        }

        function getFlightViolationType(violation) {
            if (violation.flight_28d > 100) return '28-day limit exceeded';
            if (violation.flight_calendar > 900) return 'Calendar year limit exceeded';
            if (violation.flight_12mo > 1000) return '12-month limit exceeded';
            return 'Flight Limit';
        }

        function exportViolations(type) {
            console.log(`Exporting ${type} violations...`);
            // Implementation for export functionality
            alert(`Exporting ${type} violations...`);
        }

        function viewViolationDetails(type, crewMember, date) {
            console.log(`Viewing ${type} violation details for ${crewMember} on ${date}`);
            // Implementation for viewing violation details
            alert(`Viewing ${type} violation details for ${crewMember} on ${date}`);
        }

        function sendViolationAlert(type, crewMember, date) {
            console.log(`Sending ${type} violation alert for ${crewMember} on ${date}`);
            // Implementation for sending alerts
            alert(`Sending ${type} violation alert for ${crewMember} on ${date}`);
        }

        function markViolationResolved(type, crewMember, date) {
            console.log(`Marking ${type} violation as resolved for ${crewMember} on ${date}`);
            // Implementation for marking violations as resolved
            alert(`Marking ${type} violation as resolved for ${crewMember} on ${date}`);
        }

        function updateDetailsTab() {
            const tbody = document.querySelector('#content-details tbody');
            if (!tbody) return;
            
            if (filteredData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            No data found
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            filteredData.forEach(data => {
                html += `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            ${data.crew_member}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${new Date(data.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${data.sectors}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${new Date(data.fdp_start).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${new Date(data.fdp_end).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${data.fdp_hours.toFixed(1)}h
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            ${data.fdp_hours > 14 ? 
                                `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">EXCEEDED</span>` :
                                `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">COMPLIANT</span>`
                            }
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${data.routes}
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        function handleCompletion(data) {
            isCalculating = false;
            const startBtn = document.getElementById('startCalculationBtn');
            const progressIndicator = document.getElementById('progressIndicator');
            
            // Update button state
            startBtn.disabled = false;
            startBtn.innerHTML = '<i class="fas fa-calculator mr-2"></i>Start FDP Calculation';
            
            // Hide progress indicator
            progressIndicator.classList.add('hidden');
            
            // Show completion message
            const progressMessage = document.getElementById('progressMessage');
            progressMessage.textContent = 'FDP calculations completed successfully!';
            progressMessage.className = 'text-sm text-green-600 dark:text-green-400';
        }

        function handleError(data) {
            isCalculating = false;
            const startBtn = document.getElementById('startCalculationBtn');
            const progressIndicator = document.getElementById('progressIndicator');
            
            // Update button state
            startBtn.disabled = false;
            startBtn.innerHTML = '<i class="fas fa-calculator mr-2"></i>Start FDP Calculation';
            
            // Show error message
            const progressMessage = document.getElementById('progressMessage');
            progressMessage.textContent = 'Error: ' + data.message;
            progressMessage.className = 'text-sm text-red-600 dark:text-red-400';
            
            console.error('FDP Calculation Error:', data);
        }

        function viewCrewDetails(crewMember, crewId) {
            console.log('Loading crew details for:', crewMember, 'ID:', crewId);
            
            // Show loading state
            document.getElementById('crewDetailsContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i><p class="mt-2 text-gray-500">Loading crew details...</p></div>';
            document.getElementById('crewDetailsModal').classList.remove('hidden');
            
            // Build query string with both crew_member and crew_id
            let queryParams = `crew_member=${encodeURIComponent(crewMember)}`;
            if (crewId) {
                queryParams += `&crew_id=${crewId}`;
            }
            
            // Load crew details via AJAX
            fetch(`../api/get_crew_fdp_details.php?${queryParams}`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        document.getElementById('crewDetailsContent').innerHTML = data.html;
                    } else {
                        document.getElementById('crewDetailsContent').innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-2xl"></i><p class="mt-2">Error: ${data.message}</p></div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('crewDetailsContent').innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-2xl"></i><p class="mt-2">Error loading crew details: ${error.message}</p></div>`;
                });
        }

        function closeCrewDetailsModal() {
            document.getElementById('crewDetailsModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('crewDetailsModal');
            if (event.target === modal) {
                closeCrewDetailsModal();
            }
        }
    </script>
</body>
</html>
