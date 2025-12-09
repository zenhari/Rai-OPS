<?php
require_once '../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/notam.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle AJAX request for NOTAM data
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'get_notam') {
    header('Content-Type: application/json');
    
    try {
        $db = getDBConnection();
        $today = date('Y-m-d');
        
        // Get all unique routes from today's flights
        $stmt = $db->prepare("
            SELECT DISTINCT Route 
            FROM flights 
            WHERE DATE(FltDate) = ?
            AND Route IS NOT NULL 
            AND Route != ''
            ORDER BY Route
        ");
        $stmt->execute([$today]);
        $routes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($routes)) {
            echo json_encode([
                'success' => false,
                'message' => 'No flights found for today.',
                'data' => []
            ]);
            exit;
        }
        
        // Extract all IATA codes from routes (e.g., "THR-RAS" or "THR - RAS" -> ["THR", "RAS"])
        $iataCodes = [];
        foreach ($routes as $route) {
            // Handle both "THR-RAS" and "THR - RAS" formats
            $route = str_replace(' ', '', $route); // Remove spaces
            $parts = explode('-', $route);
            foreach ($parts as $part) {
                $code = trim($part);
                // Only add if it's a valid IATA code (3 letters)
                if (!empty($code) && strlen($code) == 3 && ctype_alpha($code) && !in_array($code, $iataCodes)) {
                    $iataCodes[] = strtoupper($code);
                }
            }
        }
        
        if (empty($iataCodes)) {
            echo json_encode([
                'success' => false,
                'message' => 'No IATA codes found in routes.',
                'data' => []
            ]);
            exit;
        }
        
        // Get ICAO codes from stations table
        // First check if icao_code column exists, if not use iata_code
        $placeholders = str_repeat('?,', count($iataCodes) - 1) . '?';
        
        // Try to get icao_code, fallback to iata_code if column doesn't exist
        try {
            $stmt = $db->prepare("
                SELECT DISTINCT COALESCE(icao_code, iata_code) as code
                FROM stations 
                WHERE iata_code IN ($placeholders)
                AND (icao_code IS NOT NULL OR iata_code IS NOT NULL)
                AND (icao_code != '' OR iata_code != '')
            ");
            $stmt->execute($iataCodes);
            $icaoCodes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            // If icao_code column doesn't exist, use iata_code
            $stmt = $db->prepare("
                SELECT DISTINCT iata_code 
                FROM stations 
                WHERE iata_code IN ($placeholders)
                AND iata_code IS NOT NULL 
                AND iata_code != ''
            ");
            $stmt->execute($iataCodes);
            $icaoCodes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        if (empty($icaoCodes)) {
            echo json_encode([
                'success' => false,
                'message' => 'No ICAO codes found for the IATA codes.',
                'data' => [],
                'iata_codes' => $iataCodes
            ]);
            exit;
        }
        
        // Call NOTAM API
        $locations = implode(',', $icaoCodes);
        $url = "http://192.168.201.23:300/notam?locations=" . urlencode($locations);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60, // Increased timeout to 60 seconds
            CURLOPT_CONNECTTIMEOUT => 10, // Connection timeout
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer 9b1ce0da-09ae-41e5-9dcf-97d6cee9b1fd',
                'Content-Type: application/json'
            ],
            CURLOPT_VERBOSE => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);
        
        if ($curlError) {
            $errorDetails = [
                'error' => $curlError,
                'url' => $url,
                'icao_codes' => $icaoCodes,
                'total_codes' => count($icaoCodes)
            ];
            
            // Check if it's a timeout error
            if (strpos($curlError, 'timed out') !== false || strpos($curlError, 'timeout') !== false) {
                $errorDetails['suggestion'] = 'The API server may be slow or unreachable. Please try again later or contact the administrator.';
            }
            
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching NOTAM data: ' . $curlError,
                'data' => [],
                'details' => $errorDetails
            ]);
            exit;
        }
        
        if ($httpCode !== 200) {
            echo json_encode([
                'success' => false,
                'message' => "API returned HTTP code {$httpCode}",
                'data' => [],
                'response' => substr($response, 0, 500),
                'url' => $url,
                'icao_codes' => $icaoCodes
            ]);
            exit;
        }
        
        $notamData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode([
                'success' => false,
                'message' => 'Error decoding JSON: ' . json_last_error_msg(),
                'data' => []
            ]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $notamData ?: [],
            'icao_codes' => $icaoCodes,
            'iata_codes' => $iataCodes,
            'routes' => $routes
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'data' => []
        ]);
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NOTAM - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">NOTAM</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Notice to Airmen for today's flight routes</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Info Card -->
                    <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <div class="flex">
                            <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mt-0.5 mr-3"></i>
                            <div class="text-sm text-blue-800 dark:text-blue-300">
                                <p class="font-semibold mb-1">NOTAM Information:</p>
                                <p>This page displays NOTAMs for all airports used in today's flights. Data is automatically fetched from today's flight routes.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Loading Progress Bar -->
                    <div id="loading-container" class="hidden mb-6">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Loading NOTAM data...</span>
                                <span id="loading-percent" class="text-sm font-medium text-blue-600 dark:text-blue-400">0%</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                                <div id="progress-bar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Results Container -->
                    <div id="results-container" class="hidden">
                        <!-- Summary Card -->
                        <div id="summary-card" class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6"></div>

                        <!-- NOTAM Results -->
                        <div id="notam-results" class="space-y-4"></div>
                    </div>

                    <!-- Error Message -->
                    <div id="error-message" class="hidden mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <div class="flex">
                            <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mt-0.5 mr-3"></i>
                            <div class="text-sm text-red-800 dark:text-red-300">
                                <p id="error-text"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Load Button -->
                    <div class="text-center">
                        <button id="load-notam-btn" 
                                class="bg-blue-600 text-white font-semibold py-3 px-8 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 dark:focus:ring-blue-800 transition-all flex items-center justify-center mx-auto">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Load Today's NOTAMs
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loadBtn = document.getElementById('load-notam-btn');
            const loadingContainer = document.getElementById('loading-container');
            const resultsContainer = document.getElementById('results-container');
            const errorMessage = document.getElementById('error-message');
            const errorText = document.getElementById('error-text');
            const progressBar = document.getElementById('progress-bar');
            const loadingPercent = document.getElementById('loading-percent');
            const summaryCard = document.getElementById('summary-card');
            const notamResults = document.getElementById('notam-results');

            loadBtn.addEventListener('click', function() {
                // Reset UI
                loadBtn.disabled = true;
                loadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
                loadingContainer.classList.remove('hidden');
                resultsContainer.classList.add('hidden');
                errorMessage.classList.add('hidden');
                
                // Simulate progress
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += 5;
                    if (progress <= 90) {
                        progressBar.style.width = progress + '%';
                        loadingPercent.textContent = progress + '%';
                    }
                }, 200);

                // Make AJAX request
                const formData = new FormData();
                formData.append('action', 'get_notam');

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    clearInterval(progressInterval);
                    progressBar.style.width = '100%';
                    loadingPercent.textContent = '100%';
                    
                    setTimeout(() => {
                        loadingContainer.classList.add('hidden');
                        
                        if (data.success) {
                            displayResults(data);
                        } else {
                            let errorMsg = data.message || 'Failed to load NOTAM data.';
                            
                            // Add more details if available
                            if (data.details) {
                                if (data.details.suggestion) {
                                    errorMsg += '\n\n' + data.details.suggestion;
                                }
                                if (data.details.icao_codes && data.details.icao_codes.length > 0) {
                                    errorMsg += '\n\nICAO Codes being queried: ' + data.details.icao_codes.join(', ');
                                }
                            }
                            
                            showError(errorMsg);
                        }
                        
                        loadBtn.disabled = false;
                        loadBtn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Load Today\'s NOTAMs';
                    }, 500);
                })
                .catch(error => {
                    clearInterval(progressInterval);
                    loadingContainer.classList.add('hidden');
                    showError('Error: ' + error.message);
                    loadBtn.disabled = false;
                    loadBtn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Load Today\'s NOTAMs';
                });
            });

            function displayResults(data) {
                // Display summary
                summaryCard.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                            <div class="text-sm text-gray-600 dark:text-gray-400">Routes Found</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">${data.routes?.length || 0}</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                            <div class="text-sm text-gray-600 dark:text-gray-400">IATA Codes</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">${data.iata_codes?.length || 0}</div>
                        </div>
                        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                            <div class="text-sm text-gray-600 dark:text-gray-400">ICAO Codes</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">${data.icao_codes?.length || 0}</div>
                        </div>
                        <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4">
                            <div class="text-sm text-gray-600 dark:text-gray-400">NOTAMs Found</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">${data.data?.length || 0}</div>
                        </div>
                    </div>
                `;

                // Display NOTAMs
                if (data.data && data.data.length > 0) {
                    notamResults.innerHTML = data.data.map((notam, index) => `
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        ${notam['Location'] || 'N/A'} - ${notam['Number'] || 'N/A'}
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Class: ${notam['Class'] || 'N/A'}
                                    </p>
                                </div>
                                <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-xs font-medium">
                                    #${index + 1}
                                </span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Start Date UTC</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">${notam['Start Date UTC'] || 'N/A'}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">End Date UTC</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">${notam['End Date UTC 1'] || 'N/A'}</p>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Condition</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">${notam['Condition'] || 'N/A'}</p>
                            </div>
                        </div>
                    `).join('');
                } else {
                    notamResults.innerHTML = `
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center">
                            <i class="fas fa-info-circle text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-600 dark:text-gray-400">No NOTAMs found for today's routes.</p>
                        </div>
                    `;
                }

                resultsContainer.classList.remove('hidden');
            }

            function showError(message) {
                // Replace \n with <br> for HTML display
                errorText.innerHTML = message.replace(/\n/g, '<br>');
                errorMessage.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>
