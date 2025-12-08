<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/rlss/search_mro/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';
$errorDetails = '';
$searchResults = [];
$searchData = null;

// Handle AJAX requests
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Handle get token request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'get_token') {
    header('Content-Type: application/json');
    $tokenResult = getLocatoryToken();
    
    if (is_array($tokenResult) && isset($tokenResult['error'])) {
        echo json_encode(['success' => false, 'error' => $tokenResult['error']]);
    } elseif ($tokenResult) {
        echo json_encode(['success' => true, 'token' => $tokenResult]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to get token']);
    }
    exit;
}

// Handle search form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'search') {
    $pn = trim($_POST['pn'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validate: either pn or description must be provided, but not both
    if (empty($pn) && empty($description)) {
        $error = 'Either Part Number (PN) or Description must be provided.';
    } elseif (!empty($pn) && !empty($description)) {
        $error = 'Please provide either Part Number (PN) OR Description, not both.';
    } elseif (!empty($description) && (strlen($description) < 3 || strlen($description) > 500)) {
        $error = 'Description must be between 3 and 500 characters.';
    } else {
        // Step 1: Get token from API
        $tokenResult = getLocatoryToken();
        
        if (is_array($tokenResult) && isset($tokenResult['error'])) {
            $error = 'Failed to get authentication token.';
            $errorDetails = $tokenResult['error'];
        } elseif ($tokenResult) {
            // Token retrieved successfully
            $token = $tokenResult;
            
            // Step 2: Search with token
            $searchResult = searchMROCapabilities($token, $pn, $description);
            
            if (is_array($searchResult) && isset($searchResult['error'])) {
                $error = 'Failed to search MRO capabilities.';
                $errorDetails = $searchResult['error'];
            } elseif ($searchResult && isset($searchResult['response']) && is_array($searchResult['response'])) {
                $searchResults = $searchResult['response'];
                if (empty($searchResults)) {
                    $error = 'No results found for your search criteria.';
                }
            } else {
                $error = 'No results found or API error occurred.';
            }
        } else {
            $error = 'Failed to get authentication token.';
            $errorDetails = 'Please check server logs for details.';
        }
    }
}

// Function to get token from Locatory API
function getLocatoryToken() {
    $url = 'https://api.locatory.com/v1/get-user-token';
    $authHeader = 'Basic bW9oYW1tYWRob3NzZWluLm1pcnphZWk6WGhGcWhmbFo=';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authHeader
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    if ($curlError || $curlErrno) {
        $errorMsg = "cURL Error #{$curlErrno}: {$curlError}";
        error_log("Locatory Token API {$errorMsg}");
        error_log("Locatory Token API URL: {$url}");
        error_log("Locatory Token API HTTP Code: {$httpCode}");
        if ($response) {
            error_log("Locatory Token API Response: " . substr($response, 0, 500));
        }
        return ['error' => $errorMsg . ' (HTTP Code: ' . $httpCode . ')'];
    }
    
    if ($httpCode !== 200) {
        $errorMsg = "HTTP Error Code: {$httpCode}";
        error_log("Locatory Token API {$errorMsg}, Response: " . substr($response, 0, 500));
        return ['error' => $errorMsg . ($response ? ' - ' . substr($response, 0, 200) : '')];
    }
    
    if (empty($response)) {
        $errorMsg = "Empty response from API";
        error_log("Locatory Token API: {$errorMsg}");
        return ['error' => $errorMsg];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = "JSON Error: " . json_last_error_msg();
        error_log("Locatory Token API {$errorMsg}");
        error_log("Locatory Token API Response: " . substr($response, 0, 500));
        return ['error' => $errorMsg . ' - Response: ' . substr($response, 0, 200)];
    }
    
    if ($data && isset($data['data']['token'])) {
        return $data['data']['token'];
    }
    
    $errorMsg = "Token not found in response";
    error_log("Locatory Token API: {$errorMsg}. Response: " . substr($response, 0, 500));
    return ['error' => $errorMsg . ' - Response: ' . substr($response, 0, 200)];
}

// Function to search MRO capabilities
function searchMROCapabilities($token, $pn = '', $description = '') {
    $url = 'https://api.locatory.com/v1/capabilities/search';
    
    // Build request data - either capabilities with pn OR description, not both
    $postData = [];
    
    if (!empty($pn)) {
        // Use capabilities array with pn
        $postData['capabilities'] = [
            [
                'pn' => $pn
            ]
        ];
    } elseif (!empty($description)) {
        // Use description key
        $postData['description'] = $description;
    } else {
        return ['error' => 'Either PN or Description must be provided'];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'locatory-token: ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    if ($curlError || $curlErrno) {
        $errorMsg = "cURL Error #{$curlErrno}: {$curlError}";
        error_log("Locatory MRO Search API {$errorMsg}");
        error_log("Locatory MRO Search API URL: {$url}");
        error_log("Locatory MRO Search API HTTP Code: {$httpCode}");
        if ($response) {
            error_log("Locatory MRO Search API Response: " . substr($response, 0, 500));
        }
        return ['error' => $errorMsg . ' (HTTP Code: ' . $httpCode . ')'];
    }
    
    if ($httpCode !== 200) {
        $errorMsg = "HTTP Error Code: {$httpCode}";
        error_log("Locatory MRO Search API {$errorMsg}, Response: " . substr($response, 0, 500));
        return ['error' => $errorMsg . ($response ? ' - ' . substr($response, 0, 200) : '')];
    }
    
    if (empty($response)) {
        $errorMsg = "Empty response from API";
        error_log("Locatory MRO Search API: {$errorMsg}");
        return ['error' => $errorMsg];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = "JSON Error: " . json_last_error_msg();
        error_log("Locatory MRO Search API {$errorMsg}");
        error_log("Locatory MRO Search API Response: " . substr($response, 0, 500));
        return ['error' => $errorMsg . ' - Response: ' . substr($response, 0, 200)];
    }
    
    return $data;
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RLSS - Search MRO - <?php echo PROJECT_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../../../../assets/images/favicon.ico">
    
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
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            html {
                color-scheme: dark;
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900" onload="applyDarkMode()">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">RLSS - Search MRO</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Search for MRO capabilities using Locatory API</p>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Search Form -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mb-6">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Search Parameters</h2>
                        
                        <?php if ($error): ?>
                            <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-md p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-circle text-red-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-red-800 dark:text-red-200"><?php echo htmlspecialchars($error); ?></p>
                                        <?php if ($errorDetails): ?>
                                            <p class="text-xs text-red-600 dark:text-red-300 mt-2"><?php echo htmlspecialchars($errorDetails); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($message): ?>
                            <div class="mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-md p-4">
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
                        
                        <form method="POST" action="" class="space-y-4" id="searchForm">
                            <input type="hidden" name="action" value="search">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="pn" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Part Number (PN)
                                    </label>
                                    <input type="text" id="pn" name="pn"
                                           value="<?php echo htmlspecialchars($_POST['pn'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., xxxxx5">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Search by part number</p>
                                </div>
                                
                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Description
                                    </label>
                                    <input type="text" id="description" name="description"
                                           value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="3-500 characters">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Search by description (3-500 chars)</p>
                                </div>
                            </div>
                            
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-md p-3">
                                <p class="text-xs text-blue-800 dark:text-blue-200">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>Note:</strong> Please provide either Part Number (PN) OR Description, not both. Part Number has priority if both are provided.
                                </p>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-search mr-2"></i>
                                    Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Results Table -->
                <?php if (!empty($searchResults)): ?>
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Search Results (<?php echo count($searchResults); ?> found)
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Part Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Company</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Details</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($searchResults as $result): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-semibold">
                                        <?php echo htmlspecialchars($result['pn'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($result['description'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <div class="font-medium"><?php echo htmlspecialchars($result['cmpn_name'] ?? 'N/A'); ?></div>
                                        <?php if (!empty($result['cmpn_hash'])): ?>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Hash: <?php echo htmlspecialchars($result['cmpn_hash']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($result['location'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                        <div class="space-y-1">
                                            <?php if (!empty($result['phone'])): ?>
                                                <div>Phone: <?php echo htmlspecialchars($result['phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($result['email'])): ?>
                                                <div>Email: <a href="mailto:<?php echo htmlspecialchars($result['email']); ?>" class="text-blue-600 dark:text-blue-400 hover:underline"><?php echo htmlspecialchars($result['email']); ?></a></div>
                                            <?php endif; ?>
                                            <?php if (!empty($result['website'])): ?>
                                                <div>Website: <a href="<?php echo htmlspecialchars($result['website']); ?>" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline"><?php echo htmlspecialchars($result['website']); ?></a></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)): ?>
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="p-6 text-center">
                        <i class="fas fa-inbox text-4xl text-gray-400 mb-4"></i>
                        <p class="text-lg text-gray-600 dark:text-gray-400">No results found for your search criteria.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Dark mode detection from browser preference
        function applyDarkMode() {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const html = document.documentElement;
            
            if (prefersDark) {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
            
            // Listen for system preference changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (e.matches) {
                    html.classList.add('dark');
                } else {
                    html.classList.remove('dark');
                }
            });
        }
        
        // Form validation: ensure only one field is filled
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            const pn = document.getElementById('pn').value.trim();
            const description = document.getElementById('description').value.trim();
            
            if (pn && description) {
                e.preventDefault();
                alert('Please provide either Part Number (PN) OR Description, not both. Part Number will be used if both are provided.');
                return false;
            }
            
            if (!pn && !description) {
                e.preventDefault();
                alert('Please provide either Part Number (PN) or Description.');
                return false;
            }
            
            if (description && (description.length < 3 || description.length > 500)) {
                e.preventDefault();
                alert('Description must be between 3 and 500 characters.');
                return false;
            }
        });
        
        // Auto-clear description when PN is filled
        document.getElementById('pn').addEventListener('input', function() {
            if (this.value.trim()) {
                document.getElementById('description').value = '';
            }
        });
        
        // Auto-clear PN when description is filled
        document.getElementById('description').addEventListener('input', function() {
            if (this.value.trim()) {
                document.getElementById('pn').value = '';
            }
        });
    </script>
</body>
</html>
