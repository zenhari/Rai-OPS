<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/fleet/mel_items/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';
$melData = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'fetch_mel_data') {
        // Fetch MEL data from external API
        $melData = fetchMELData();
        
        if ($melData === false) {
            $error = 'Failed to fetch MEL data from external API.';
        } elseif (empty($melData['data'])) {
            $message = 'No MEL data found.';
        } else {
            $message = 'MEL data fetched successfully.';
        }
    }
}

function fetchMELData() {
    $apiKey = '91d692cf-6b08-4fce-a2e2-fa9505192faa';
    $baseUrl = 'etl.raimonairways.net/api/mel_items.php';
    
    // Try HTTP first, then HTTPS if HTTP fails
    $protocols = ['http', 'https'];
    
    foreach ($protocols as $protocol) {
        $url = $protocol . '://' . $baseUrl;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // If there's a cURL error, try next protocol
        if ($curlError) {
            error_log("MEL API cURL Error ({$protocol}): " . $curlError);
            continue;
        }
        
        // If HTTP code is not 200, try next protocol
        if ($httpCode !== 200) {
            error_log("MEL API HTTP Error ({$protocol}): " . $httpCode);
            continue;
        }
        
        // Try to decode JSON
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("MEL API JSON Error ({$protocol}): " . json_last_error_msg());
            continue;
        }
        
        // Check if response is valid
        if (!$decodedResponse || !isset($decodedResponse['ok']) || !$decodedResponse['ok']) {
            error_log("MEL API Response Error ({$protocol}): " . substr($response, 0, 200));
            continue;
        }
        
        // Success! Return the response
        error_log("MEL API Success using {$protocol}");
        return $decodedResponse;
    }
    
    // If both protocols failed, return false
    error_log("MEL API: Both HTTP and HTTPS failed");
    return false;
}

function safeOutput($value) {
    if ($value === null || $value === '') {
        return 'N/A';
    }
    // Replace "Nill" with "Nil" in the output
    $value = str_ireplace('Nill', 'Nil', $value);
    return htmlspecialchars($value);
}

function formatDate($date) {
    if (empty($date) || $date === '0000-00-00' || $date === 'Nill' || $date === 'Nil' || $date === 'NIL') {
        return 'N/A';
    }
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format('M j, Y');
    } catch (Exception $e) {
        // Replace "Nill" with "Nil" in the output
        return str_ireplace('Nill', 'Nil', $date);
    }
}

function formatStatus($isVoided, $isClosed) {
    if ($isVoided == 1) {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                    <i class="fas fa-ban text-xs mr-1"></i>
                    Voided
                </span>';
    } elseif ($isClosed == 1) {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                    <i class="fas fa-check-circle text-xs mr-1"></i>
                    Closed
                </span>';
    } else {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                    <i class="fas fa-clock text-xs mr-1"></i>
                    Open
                </span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEL Items - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">MEL Items</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Minimum Equipment List items from external API</p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="../" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Fleet
                            </a>
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

                <!-- Fetch Data Form -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Fetch MEL Data</h2>
                    <form method="POST" class="flex items-end space-x-4">
                        <input type="hidden" name="action" value="fetch_mel_data">
                        <div class="flex-1">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Click the button below to fetch MEL items from the external API.</p>
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                            <i class="fas fa-sync mr-2"></i>
                            Fetch MEL Data
                        </button>
                    </form>
                </div>

                <!-- MEL Data Display -->
                <?php if ($melData): ?>
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-list text-blue-500 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Items</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        <?php 
                                        // Count filtered items (excluding "Nill | Nill")
                                        $filteredCount = 0;
                                        if (!empty($melData['data'])) {
                                            foreach ($melData['data'] as $item) {
                                                $referenceRevnoDate = strtoupper(trim($item['reference_revno_date'] ?? ''));
                                                if (stripos($referenceRevnoDate, 'Nill | Nill') === false && 
                                                    stripos($referenceRevnoDate, 'NIL | NIL') === false &&
                                                    stripos($referenceRevnoDate, 'Nill|Nill') === false &&
                                                    stripos($referenceRevnoDate, 'NIL|NIL') === false) {
                                                    $filteredCount++;
                                                }
                                            }
                                        }
                                        echo safeOutput($filteredCount);
                                        ?>
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        (Filtered: excluding "Nill | Nill")
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-file-alt text-green-500 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Page</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo safeOutput($melData['page'] ?? 1); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-database text-purple-500 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Per Page</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo safeOutput($melData['per_page'] ?? 0); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MEL Data Table -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">MEL Items Details</h2>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Detailed MEL information for each ETL number</p>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="exportMELData()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                        <i class="fas fa-download mr-2"></i>
                                        Export CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="melTable">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ETL Number</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Report Number</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">MEL Category</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Rev No</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Due Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Extension Duration</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reference Rev/Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (empty($melData['data'])): ?>
                                        <tr>
                                            <td colspan="9" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                No MEL data found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $filteredData = [];
                                        foreach ($melData['data'] as $item) {
                                            // Filter: Only show items where reference_revno_date is NOT "Nill | Nill"
                                            $referenceRevnoDate = strtoupper(trim($item['reference_revno_date'] ?? ''));
                                            
                                            // Skip items where reference_revno_date is "Nill | Nill" (case-insensitive)
                                            if (stripos($referenceRevnoDate, 'Nill | Nill') !== false || 
                                                stripos($referenceRevnoDate, 'NIL | NIL') !== false ||
                                                stripos($referenceRevnoDate, 'Nill|Nill') !== false ||
                                                stripos($referenceRevnoDate, 'NIL|NIL') !== false) {
                                                continue; // Skip this item
                                            }
                                            
                                            $filteredData[] = $item;
                                        }
                                        
                                        if (empty($filteredData)): ?>
                                            <tr>
                                                <td colspan="9" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                    No MEL data found with valid reference.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($filteredData as $item): ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                        <?php echo safeOutput($item['id']); ?>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                            <?php echo safeOutput($item['etl_number']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                        <?php echo safeOutput($item['report_number']); ?>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                        <?php echo safeOutput($item['mel_cat']); ?>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                        <?php echo safeOutput($item['rev_no']); ?>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                        <?php echo formatDate($item['due_date']); ?>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <?php echo formatStatus($item['is_voided'], $item['is_closed']); ?>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                        <?php echo safeOutput($item['extension_duration']); ?>
                                                    </td>
                                                    <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">
                                                        <?php echo safeOutput($item['reference_revno_date']); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Data State -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-12 text-center">
                        <i class="fas fa-clipboard-list text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No MEL Data</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">Click "Fetch MEL Data" to load the MEL items from the external API.</p>
                        <div class="text-sm text-gray-400 dark:text-gray-500">
                            <p>MEL data is fetched from external API:</p>
                            <p class="font-mono">http://etl.raimonairways.net/api/mel_items.php</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function exportMELData() {
            const table = document.getElementById('melTable');
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            rows.forEach(row => {
                const cols = row.querySelectorAll('td, th');
                const rowData = Array.from(cols).map(col => {
                    // Remove HTML tags and get text content
                    const text = col.textContent.replace(/"/g, '""').trim();
                    return '"' + text + '"';
                });
                csv.push(rowData.join(','));
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'mel_items_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>

