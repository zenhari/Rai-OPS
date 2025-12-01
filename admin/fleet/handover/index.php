<?php
require_once '../../../config.php';
checkPageAccessWithRedirect('admin/fleet/handover/index.php');

$message = '';
$error = '';
$handoverData = null;
$totalRecords = 0;
$currentPage = 1;
$recordsPerPage = 50;
$totalPages = 0;

// Handle form submission and pagination
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $currentPage = max(1, intval($_GET['page'] ?? 1));
    
    // Fetch handover data from external API
    $handoverData = fetchHandoverData();
    
    if ($handoverData === false) {
        $error = 'Failed to fetch handover data from external API.';
    } elseif (empty($handoverData['tables']['handover'])) {
        $message = 'No handover data found.';
    } else {
        $totalRecords = $handoverData['counts']['handover'] ?? 0;
        $totalPages = ceil($totalRecords / $recordsPerPage);
        
        // Apply pagination to data
        $startIndex = ($currentPage - 1) * $recordsPerPage;
        $handoverData['tables']['handover'] = array_slice($handoverData['tables']['handover'], $startIndex, $recordsPerPage);
        
        $message = "Found {$totalRecords} handover record(s). Showing page {$currentPage} of {$totalPages}.";
    }
}

function fetchHandoverData() {
    $url = 'https://portal.raimonairways.net/api/handover_api.php?token=f35c82b4-de5a-4192-8ef6-6aeceb3875d0';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'token=f35c82b4-de5a-4192-8ef6-6aeceb3875d0');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("HandOver API cURL Error: " . $curlError);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("HandOver API HTTP Error: " . $httpCode . " Response: " . $response);
        return false;
    }

    $decodedResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("HandOver API JSON Error: " . json_last_error_msg() . " Response: " . $response);
        return false;
    }

    if (!isset($decodedResponse['status']) || $decodedResponse['status'] !== 'ok') {
        error_log("HandOver API Response Status Error: " . ($decodedResponse['status'] ?? 'N/A'));
        return false;
    }

    return $decodedResponse;
}

function safeOutput($value) {
    return htmlspecialchars($value ?? '');
}

function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('M j, Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HandOver Management - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1a202c;
                color: #e2e8f0;
            }
            .dark\:bg-gray-800 { background-color: #2d3748; }
            .dark\:bg-gray-700 { background-color: #4a5568; }
            .dark\:text-white { color: #ffffff; }
            .dark\:text-gray-300 { color: #cbd5e0; }
            .dark\:text-gray-400 { color: #a0aec0; }
            .dark\:border-gray-700 { border-color: #4a5568; }
            .dark\:border-gray-600 { border-color: #718096; }
            .dark\:hover\:bg-gray-700:hover { background-color: #4a5568; }
            .dark\:hover\:text-white:hover { color: #ffffff; }
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">HandOver Management</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage handover records and shift information</p>
                        </div>
                        <div class="flex space-x-3">
                            <button onclick="refreshData()" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-sync mr-2"></i>
                                Refresh Data
                            </button>
                            <button onclick="exportToCSV()" 
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-download mr-2"></i>
                                Export CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../../includes/permission_banner.php'; ?>
                
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800 dark:text-green-200"><?php echo htmlspecialchars($message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800 dark:text-red-200"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <?php if ($handoverData && isset($handoverData['counts'])): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exchange-alt text-2xl text-blue-600"></i>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">HandOver Records</dt>
                                            <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($handoverData['counts']['handover']); ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-users text-2xl text-green-600"></i>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Crew Data</dt>
                                            <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($handoverData['counts']['crewdata']); ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-plane text-2xl text-purple-600"></i>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Flight Information</dt>
                                            <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($handoverData['counts']['flightinformation']); ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-tools text-2xl text-orange-600"></i>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Part Replacements</dt>
                                            <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($handoverData['counts']['partreplacementrecord']); ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- HandOver Data Display -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">HandOver Records (<?php echo $totalRecords; ?>)</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Displaying <?php echo count($handoverData['tables']['handover'] ?? []); ?> records on page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Station</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Day/Night</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Shift</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Supervisor</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Remarks</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($handoverData['tables']['handover'])): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No handover data found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($handoverData['tables']['handover'] as $handover): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo safeOutput($handover['id']); ?>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo formatDate($handover['date']); ?>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo safeOutput($handover['station']); ?>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $handover['day_night'] === 'Day' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200'; ?>">
                                                    <?php echo safeOutput($handover['day_night']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo safeOutput($handover['shift']); ?>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo safeOutput($handover['shift_supervisor']); ?>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                <div class="max-w-xs truncate" title="<?php echo safeOutput($handover['remarks']); ?>">
                                                    <?php echo safeOutput($handover['remarks']); ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="openHandoverDetailsModal(<?php echo htmlspecialchars(json_encode($handover), ENT_QUOTES); ?>)" 
                                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="printHandoverRecord(<?php echo htmlspecialchars(json_encode($handover), ENT_QUOTES); ?>)" 
                                                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300" title="Print">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="px-6 py-4 flex items-center justify-between border-t border-gray-200 dark:border-gray-700">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <a href="?page=<?php echo max(1, $currentPage - 1); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600">
                                    Previous
                                </a>
                                <a href="?page=<?php echo min($totalPages, $currentPage + 1); ?>" 
                                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600">
                                    Next
                                </a>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        Showing <span class="font-medium"><?php echo (($currentPage - 1) * $recordsPerPage) + 1; ?></span> to <span class="font-medium"><?php echo min($currentPage * $recordsPerPage, $totalRecords); ?></span> of <span class="font-medium"><?php echo $totalRecords; ?></span> results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($currentPage > 1): ?>
                                            <a href="?page=<?php echo $currentPage - 1; ?>" 
                                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600">
                                                <span class="sr-only">Previous</span>
                                                <i class="fas fa-chevron-left h-5 w-5"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php
                                        $startPage = max(1, $currentPage - 2);
                                        $endPage = min($totalPages, $currentPage + 2);

                                        if ($startPage > 1) {
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">...</span>';
                                        }

                                        for ($i = $startPage; $i <= $endPage; $i++):
                                        ?>
                                            <a href="?page=<?php echo $i; ?>" 
                                               class="<?php echo $i === $currentPage ? 'z-10 bg-blue-50 border-blue-500 text-blue-600 dark:bg-blue-900 dark:border-blue-400 dark:text-blue-200' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($endPage < $totalPages): ?>
                                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">...</span>
                                        <?php endif; ?>

                                        <?php if ($currentPage < $totalPages): ?>
                                            <a href="?page=<?php echo $currentPage + 1; ?>" 
                                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600">
                                                <span class="sr-only">Next</span>
                                                <i class="fas fa-chevron-right h-5 w-5"></i>
                                            </a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- HandOver Details Modal -->
    <div id="handoverDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-7xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">HandOver Details</h3>
                    <button onclick="closeHandoverDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div id="handoverDetailsContent" class="space-y-6">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentHandoverData = null;
        
        // Make handover data available globally for modal
        window.handoverData = <?php echo json_encode($handoverData); ?>;

        function openHandoverDetailsModal(handoverData) {
            try {
                currentHandoverData = typeof handoverData === 'string' ? JSON.parse(handoverData) : handoverData;
                populateHandoverDetailsModal(currentHandoverData);
                document.getElementById('handoverDetailsModal').classList.remove('hidden');
            } catch (error) {
                console.error('Error opening handover details modal:', error);
                alert('Error opening handover details. Please try again.');
            }
        }

        function closeHandoverDetailsModal() {
            document.getElementById('handoverDetailsModal').classList.add('hidden');
            currentHandoverData = null;
        }

        function populateHandoverDetailsModal(handover) {
            const content = document.getElementById('handoverDetailsContent');
            
            // Get related data for this handover
            const handoverId = handover.id;
            const crewData = window.handoverData?.tables?.crewdata?.filter(item => item.handover_id == handoverId) || [];
            const flightInfo = window.handoverData?.tables?.flightinformation?.filter(item => item.handover_id == handoverId) || [];
            const carriedForwardItems = window.handoverData?.tables?.carriedforwarditemlist?.filter(item => item.handover_id == handoverId) || [];
            
            const details = `
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Basic Information</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">ID</label>
                                <p class="text-sm text-gray-900 dark:text-white">${handover.id || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Date</label>
                                <p class="text-sm text-gray-900 dark:text-white">${handover.date ? new Date(handover.date).toLocaleDateString() : 'N/A'}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Station</label>
                                <p class="text-sm text-gray-900 dark:text-white">${handover.station || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Day/Night</label>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${handover.day_night === 'Day' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200'}">
                                    ${handover.day_night || 'N/A'}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Shift Information</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Shift</label>
                                <p class="text-sm text-gray-900 dark:text-white">${handover.shift || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Shift Supervisor</label>
                                <p class="text-sm text-gray-900 dark:text-white">${handover.shift_supervisor || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Sign 1</label>
                                <p class="text-sm text-gray-900 dark:text-white">${handover.sign1 || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Sign 2</label>
                                <p class="text-sm text-gray-900 dark:text-white">${handover.sign2 || 'N/A'}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Remarks</h4>
                    <div class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap">${handover.remarks || 'No remarks available'}</div>
                </div>
                
                <!-- Crew Data Section -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3 flex items-center">
                        <i class="fas fa-users mr-2 text-green-600"></i>
                        Crew Data (${crewData.length})
                    </h4>
                    ${crewData.length > 0 ? `
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                <thead class="bg-gray-100 dark:bg-gray-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Certifying Staff</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Non-Certified Staff</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Contractual Staff</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Vacation</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mission</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Training</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sick Leave</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-700 divide-y divide-gray-200 dark:divide-gray-600">
                                    ${crewData.map(crew => `
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${crew.certifying_staff || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${crew.non_certified_staff || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${crew.contractual_staff || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${crew.vacation || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${crew.mission || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${crew.training || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${crew.sick_leave || 'N/A'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : `
                        <p class="text-sm text-gray-500 dark:text-gray-400">No crew data available for this handover.</p>
                    `}
                </div>
                
                <!-- Flight Information Section -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3 flex items-center">
                        <i class="fas fa-plane mr-2 text-purple-600"></i>
                        Flight Information (${flightInfo.length})
                    </h4>
                    ${flightInfo.length > 0 ? `
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                <thead class="bg-gray-100 dark:bg-gray-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">AC Reg</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flight No</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type of Check</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Technical Delay Reason</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Staff</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-700 divide-y divide-gray-200 dark:divide-gray-600">
                                    ${flightInfo.map(flight => `
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${flight.ac_reg || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${flight.flt_no || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${flight.type_of_check || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${flight.technical_delay_reason || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${flight.staff || 'N/A'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : `
                        <p class="text-sm text-gray-500 dark:text-gray-400">No flight information available for this handover.</p>
                    `}
                </div>
                
                <!-- Carried Forward Items Section -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3 flex items-center">
                        <i class="fas fa-list-alt mr-2 text-orange-600"></i>
                        Carried Forward Items (${carriedForwardItems.length})
                    </h4>
                    ${carriedForwardItems.length > 0 ? `
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                <thead class="bg-gray-100 dark:bg-gray-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">AC Reg</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Defect Description</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Open Date</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Due Date</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">MEL Ref</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">NIS</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-700 divide-y divide-gray-200 dark:divide-gray-600">
                                    ${carriedForwardItems.map(item => `
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${item.carriedac_reg || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${item.defect_description || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${item.open_date ? new Date(item.open_date).toLocaleDateString() : 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${item.due_date ? new Date(item.due_date).toLocaleDateString() : 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${item.mel_ref || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${item.cat || 'N/A'}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(item.status)}">
                                                    ${item.status || 'N/A'}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">${item.nis || 'N/A'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : `
                        <p class="text-sm text-gray-500 dark:text-gray-400">No carried forward items available for this handover.</p>
                    `}
                </div>
            `;
            
            content.innerHTML = details;
        }
        
        function getStatusColor(status) {
            if (!status) return 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200';
            
            const statusLower = status.toLowerCase();
            if (statusLower.includes('open') || statusLower.includes('pending')) {
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
            } else if (statusLower.includes('closed') || statusLower.includes('completed')) {
                return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            } else if (statusLower.includes('urgent') || statusLower.includes('critical')) {
                return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            } else {
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
            }
        }

        function printHandoverRecord(handoverData) {
            try {
                const handover = typeof handoverData === 'string' ? JSON.parse(handoverData) : handoverData;
                printHandoverFromModal(handover);
            } catch (error) {
                console.error('Error printing handover record:', error);
                alert('Error printing handover record. Please try again.');
            }
        }

        function printHandoverFromModal(handover) {
            const printWindow = window.open('', '_blank');
            
            // Get related data for this handover
            const handoverId = handover.id;
            const crewData = window.handoverData?.tables?.crewdata?.filter(item => item.handover_id == handoverId) || [];
            const flightInfo = window.handoverData?.tables?.flightinformation?.filter(item => item.handover_id == handoverId) || [];
            const carriedForwardItems = window.handoverData?.tables?.carriedforwarditemlist?.filter(item => item.handover_id == handoverId) || [];
            
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>HandOver Record - ${handover.id}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .section { margin-bottom: 20px; }
                        .section h3 { background-color: #f3f4f6; padding: 10px; margin: 0 0 10px 0; }
                        .field { margin-bottom: 10px; }
                        .field label { font-weight: bold; display: inline-block; width: 150px; }
                        .remarks { background-color: #f9fafb; padding: 15px; border-left: 4px solid #3b82f6; }
                        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; font-weight: bold; }
                        .no-data { font-style: italic; color: #666; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>HandOver Record</h1>
                        <p>Generated on: ${new Date().toLocaleString()}</p>
                    </div>
                    
                    <div class="section">
                        <h3>Basic Information</h3>
                        <div class="field"><label>ID:</label> ${handover.id || 'N/A'}</div>
                        <div class="field"><label>Date:</label> ${handover.date ? new Date(handover.date).toLocaleDateString() : 'N/A'}</div>
                        <div class="field"><label>Station:</label> ${handover.station || 'N/A'}</div>
                        <div class="field"><label>Day/Night:</label> ${handover.day_night || 'N/A'}</div>
                    </div>
                    
                    <div class="section">
                        <h3>Shift Information</h3>
                        <div class="field"><label>Shift:</label> ${handover.shift || 'N/A'}</div>
                        <div class="field"><label>Supervisor:</label> ${handover.shift_supervisor || 'N/A'}</div>
                        <div class="field"><label>Sign 1:</label> ${handover.sign1 || 'N/A'}</div>
                        <div class="field"><label>Sign 2:</label> ${handover.sign2 || 'N/A'}</div>
                    </div>
                    
                    <div class="section">
                        <h3>Remarks</h3>
                        <div class="remarks">${handover.remarks || 'No remarks available'}</div>
                    </div>
                    
                    <div class="section">
                        <h3>Crew Data (${crewData.length})</h3>
                        ${crewData.length > 0 ? `
                            <table>
                                <thead>
                                    <tr>
                                        <th>Certifying Staff</th>
                                        <th>Non-Certified Staff</th>
                                        <th>Contractual Staff</th>
                                        <th>Vacation</th>
                                        <th>Mission</th>
                                        <th>Training</th>
                                        <th>Sick Leave</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${crewData.map(crew => `
                                        <tr>
                                            <td>${crew.certifying_staff || 'N/A'}</td>
                                            <td>${crew.non_certified_staff || 'N/A'}</td>
                                            <td>${crew.contractual_staff || 'N/A'}</td>
                                            <td>${crew.vacation || 'N/A'}</td>
                                            <td>${crew.mission || 'N/A'}</td>
                                            <td>${crew.training || 'N/A'}</td>
                                            <td>${crew.sick_leave || 'N/A'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        ` : '<p class="no-data">No crew data available for this handover.</p>'}
                    </div>
                    
                    <div class="section">
                        <h3>Flight Information (${flightInfo.length})</h3>
                        ${flightInfo.length > 0 ? `
                            <table>
                                <thead>
                                    <tr>
                                        <th>AC Reg</th>
                                        <th>Flight No</th>
                                        <th>Type of Check</th>
                                        <th>Technical Delay Reason</th>
                                        <th>Staff</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${flightInfo.map(flight => `
                                        <tr>
                                            <td>${flight.ac_reg || 'N/A'}</td>
                                            <td>${flight.flt_no || 'N/A'}</td>
                                            <td>${flight.type_of_check || 'N/A'}</td>
                                            <td>${flight.technical_delay_reason || 'N/A'}</td>
                                            <td>${flight.staff || 'N/A'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        ` : '<p class="no-data">No flight information available for this handover.</p>'}
                    </div>
                    
                    <div class="section">
                        <h3>Carried Forward Items (${carriedForwardItems.length})</h3>
                        ${carriedForwardItems.length > 0 ? `
                            <table>
                                <thead>
                                    <tr>
                                        <th>AC Reg</th>
                                        <th>Defect Description</th>
                                        <th>Open Date</th>
                                        <th>Due Date</th>
                                        <th>MEL Ref</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>NIS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${carriedForwardItems.map(item => `
                                        <tr>
                                            <td>${item.carriedac_reg || 'N/A'}</td>
                                            <td>${item.defect_description || 'N/A'}</td>
                                            <td>${item.open_date ? new Date(item.open_date).toLocaleDateString() : 'N/A'}</td>
                                            <td>${item.due_date ? new Date(item.due_date).toLocaleDateString() : 'N/A'}</td>
                                            <td>${item.mel_ref || 'N/A'}</td>
                                            <td>${item.cat || 'N/A'}</td>
                                            <td>${item.status || 'N/A'}</td>
                                            <td>${item.nis || 'N/A'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        ` : '<p class="no-data">No carried forward items available for this handover.</p>'}
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }

        function refreshData() {
            window.location.reload();
        }

        function exportToCSV() {
            if (!currentHandoverData) {
                alert('No data available to export.');
                return;
            }
            
            // This would need to be implemented with server-side CSV generation
            alert('CSV export functionality will be implemented.');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('handoverDetailsModal');
            if (event.target === modal) {
                closeHandoverDetailsModal();
            }
        }
    </script>
</body>
</html>
