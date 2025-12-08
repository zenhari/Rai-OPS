<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/rlss/part_search/index.php');

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

// Handle create RFQ request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_rfq') {
    header('Content-Type: application/json');
    
    $rfqData = json_decode($_POST['rfq_data'] ?? '{}', true);
    $token = $_POST['token'] ?? '';
    
    if (empty($token)) {
        echo json_encode(['success' => false, 'error' => 'Token is required']);
        exit;
    }
    
    if (empty($rfqData) || !isset($rfqData['parts']) || empty($rfqData['parts'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid RFQ data']);
        exit;
    }
    
    $result = createRFQ($token, $rfqData);
    
    if ($result && isset($result['response']) && isset($result['rfqGroup'])) {
        echo json_encode([
            'success' => true,
            'message' => $result['response'],
            'rfqGroup' => $result['rfqGroup']
        ]);
    } else {
        $errorMsg = 'Failed to create RFQ';
        if (is_array($result) && isset($result['error'])) {
            $errorMsg = $result['error'];
        }
        echo json_encode(['success' => false, 'error' => $errorMsg]);
    }
    exit;
}

// Handle search form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'search') {
    $pn = trim($_POST['pn'] ?? '');
    $cond = trim($_POST['cond'] ?? '');
    $qty = intval($_POST['qty'] ?? 0);
    
    if (empty($pn)) {
        $error = 'Part Number (PN) is required.';
    } elseif (empty($cond)) {
        $error = 'At least one Condition (COND) must be selected.';
    } elseif ($qty <= 0) {
        $error = 'Quantity (QTY) must be greater than 0.';
    } else {
        // Step 1: Get token from API
        $tokenResult = getLocatoryToken();
        
        if (is_array($tokenResult) && isset($tokenResult['error'])) {
            // Token function returned error details
            $error = 'Failed to get authentication token.';
            $errorDetails = $tokenResult['error'];
        } elseif ($tokenResult) {
            // Token retrieved successfully
            $token = $tokenResult;
            
            // Step 2: Search with token
            $searchResult = searchLocatory($token, $pn, $cond, $qty);
            
            if (is_array($searchResult) && isset($searchResult['error'])) {
                // Search function returned error details
                $error = 'Failed to search parts.';
                $errorDetails = $searchResult['error'];
            } elseif ($searchResult && isset($searchResult['response']) && is_array($searchResult['response'])) {
                foreach ($searchResult['response'] as $responseItem) {
                    if (isset($responseItem['searchRezults']) && is_array($responseItem['searchRezults'])) {
                        $searchResults = array_merge($searchResults, $responseItem['searchRezults']);
                    }
                }
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

// Function to search Locatory API
function searchLocatory($token, $pn, $cond, $qty) {
    $url = 'https://api.locatory.com/v1/search';
    
    $postData = [
        'parts' => [
            [
                'pn' => $pn,
                'cond' => $cond,
                'qty' => $qty
            ]
        ],
        'fields' => [
            'price_history',
            'searchHash'
        ]
    ];
    
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
        error_log("Locatory Search API {$errorMsg}");
        error_log("Locatory Search API URL: {$url}");
        error_log("Locatory Search API HTTP Code: {$httpCode}");
        if ($response) {
            error_log("Locatory Search API Response: " . substr($response, 0, 500));
        }
        return ['error' => $errorMsg . ' (HTTP Code: ' . $httpCode . ')'];
    }
    
    if ($httpCode !== 200) {
        $errorMsg = "HTTP Error Code: {$httpCode}";
        error_log("Locatory Search API {$errorMsg}, Response: " . substr($response, 0, 500));
        return ['error' => $errorMsg . ($response ? ' - ' . substr($response, 0, 200) : '')];
    }
    
    if (empty($response)) {
        $errorMsg = "Empty response from API";
        error_log("Locatory Search API: {$errorMsg}");
        return ['error' => $errorMsg];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = "JSON Error: " . json_last_error_msg();
        error_log("Locatory Search API {$errorMsg}");
        error_log("Locatory Search API Response: " . substr($response, 0, 500));
        return ['error' => $errorMsg . ' - Response: ' . substr($response, 0, 200)];
    }
    
    return $data;
}

// Function to create RFQ
function createRFQ($token, $rfqData) {
    $url = 'https://api.locatory.com/v1/parts-rfq';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'locatory-token: ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($rfqData));
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
        error_log("Locatory RFQ API {$errorMsg}");
        error_log("Locatory RFQ API URL: {$url}");
        error_log("Locatory RFQ API HTTP Code: {$httpCode}");
        if ($response) {
            error_log("Locatory RFQ API Response: " . substr($response, 0, 500));
        }
        return ['error' => $errorMsg . ' (HTTP Code: ' . $httpCode . ')'];
    }
    
    if ($httpCode !== 200) {
        $errorMsg = "HTTP Error Code: {$httpCode}";
        error_log("Locatory RFQ API {$errorMsg}, Response: " . substr($response, 0, 500));
        return ['error' => $errorMsg . ($response ? ' - ' . substr($response, 0, 200) : '')];
    }
    
    if (empty($response)) {
        $errorMsg = "Empty response from API";
        error_log("Locatory RFQ API: {$errorMsg}");
        return ['error' => $errorMsg];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = "JSON Error: " . json_last_error_msg();
        error_log("Locatory RFQ API {$errorMsg}");
        error_log("Locatory RFQ API Response: " . substr($response, 0, 500));
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
    <title>RLSS - Parts Search - <?php echo PROJECT_NAME; ?></title>
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
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">RLSS - Parts Search</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Search for aircraft parts using Locatory API</p>
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
                        
                        <form method="POST" action="" class="space-y-4">
                            <input type="hidden" name="action" value="search">
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="pn" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Part Number (PN) *
                                    </label>
                                    <input type="text" id="pn" name="pn" required
                                           value="<?php echo htmlspecialchars($_POST['pn'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., 313">
                                </div>
                                
                                <div>
                                    <label for="cond" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Condition (COND) *
                                    </label>
                                    <input type="text" id="cond" name="cond" required
                                           list="cond-options"
                                           value="<?php echo htmlspecialchars(isset($_POST['cond']) ? (is_array($_POST['cond']) ? implode(',', $_POST['cond']) : $_POST['cond']) : ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., NE,SV or select from list">
                                    <datalist id="cond-options">
                                        <option value="AR">AR</option>
                                        <option value="NE">NE</option>
                                        <option value="NS">NS</option>
                                        <option value="OH">OH</option>
                                        <option value="SV">SV</option>
                                        <option value="AR,NE">AR,NE</option>
                                        <option value="NE,SV">NE,SV</option>
                                        <option value="AR,NE,SV">AR,NE,SV</option>
                                    </datalist>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Type or select: AR, NE, NS, OH, SV (comma-separated for multiple)</p>
                                </div>
                                
                                <div>
                                    <label for="qty" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Quantity (QTY) *
                                    </label>
                                    <input type="number" id="qty" name="qty" required min="1"
                                           value="<?php echo htmlspecialchars($_POST['qty'] ?? '1'); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="e.g., 3">
                                </div>
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Part Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Condition</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Company</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Phone</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($searchResults as $result): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($result['id'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-semibold">
                                        <?php echo htmlspecialchars($result['pn'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($result['cond'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($result['qty'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <div class="font-medium"><?php echo htmlspecialchars($result['cmpn_name'] ?? 'N/A'); ?></div>
                                        <?php if (!empty($result['cmpn_hash'])): ?>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Hash: <?php echo htmlspecialchars($result['cmpn_hash']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <div>Gen: <?php echo htmlspecialchars($result['cmpn_phone_gen'] ?? 'N/A'); ?></div>
                                        <?php if (!empty($result['cmpn_phone_aog'])): ?>
                                            <div class="text-xs text-red-600 dark:text-red-400">AOG: <?php echo htmlspecialchars($result['cmpn_phone_aog']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($result['cmpn_phone_sales'])): ?>
                                            <div class="text-xs text-blue-600 dark:text-blue-400">Sales: <?php echo htmlspecialchars($result['cmpn_phone_sales']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <div>Gen: <a href="mailto:<?php echo htmlspecialchars($result['cmpn_email_gen'] ?? ''); ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                            <?php echo htmlspecialchars($result['cmpn_email_gen'] ?? 'N/A'); ?>
                                        </a></div>
                                        <?php if (!empty($result['cmpn_email_sales'])): ?>
                                            <div class="text-xs">Sales: <a href="mailto:<?php echo htmlspecialchars($result['cmpn_email_sales']); ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                <?php echo htmlspecialchars($result['cmpn_email_sales']); ?>
                                            </a></div>
                                        <?php endif; ?>
                                        <?php if (!empty($result['cmpn_email_aog'])): ?>
                                            <div class="text-xs text-red-600 dark:text-red-400">AOG: <a href="mailto:<?php echo htmlspecialchars($result['cmpn_email_aog']); ?>" class="hover:underline">
                                                <?php echo htmlspecialchars($result['cmpn_email_aog']); ?>
                                            </a></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($result['description'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($result['location'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <button onclick="openRFQModal('<?php echo htmlspecialchars($result['id'] ?? ''); ?>', '<?php echo htmlspecialchars($result['pn'] ?? ''); ?>', '<?php echo htmlspecialchars($result['qty'] ?? '1'); ?>')"
                                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                            <i class="fas fa-paper-plane mr-1"></i>
                                            Send RFQ
                                        </button>
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

    <!-- RFQ Modal -->
    <div id="rfqModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Create RFQ</h3>
                    <button onclick="closeRFQModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-medium">Part ID:</span> <span id="rfqPartId" class="font-semibold"></span><br>
                        <span class="font-medium">Part Number:</span> <span id="rfqPartNumber" class="font-semibold"></span><br>
                        <span class="font-medium">Quantity:</span> <span id="rfqQuantity" class="font-semibold"></span>
                    </p>
                </div>
                
                <form id="rfqForm" class="space-y-4">
                    <input type="hidden" id="rfqPartIdHidden" name="part_id">
                    <input type="hidden" id="rfqQuantityHidden" name="qty">
                    
                    <div>
                        <label for="rfq_priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Priority *
                        </label>
                        <select id="rfq_priority" name="priority" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="Normal" selected>Normal</option>
                            <option value="AOG">AOG</option>
                            <option value="NeedByDate">Need by Date</option>
                            <option value="Routine">Routine</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    
                    <div id="needByDateContainer" class="hidden">
                        <label for="rfq_need_by_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Need By Date (YYYY-MM-DD) *
                        </label>
                        <input type="date" id="rfq_need_by_date" name="need_by_date"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="rfq_valid_until" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Valid Until (YYYY-MM-DD)
                        </label>
                        <input type="date" id="rfq_valid_until" name="valid_until"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="rfq_comment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Comment (max 500 chars)
                        </label>
                        <textarea id="rfq_comment" name="comment" rows="3" maxlength="500"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><span id="commentCharCount">0</span>/500 characters</p>
                    </div>
                    
                    <div>
                        <label for="rfq_ref_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Reference Number (max 50 chars)
                        </label>
                        <input type="text" id="rfq_ref_number" name="ref_number" maxlength="50"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeRFQModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200">
                            <i class="fas fa-paper-plane mr-2"></i>Send RFQ
                        </button>
                    </div>
                </form>
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
        
        // RFQ Modal Functions
        let currentRFQPartId = null;
        let currentRFQToken = null;
        
        function openRFQModal(partId, partNumber, quantity) {
            currentRFQPartId = partId;
            document.getElementById('rfqPartId').textContent = partId;
            document.getElementById('rfqPartNumber').textContent = partNumber;
            document.getElementById('rfqQuantity').textContent = quantity;
            document.getElementById('rfqPartIdHidden').value = partId;
            document.getElementById('rfqQuantityHidden').value = quantity;
            
            // Reset form
            document.getElementById('rfqForm').reset();
            document.getElementById('rfq_priority').value = 'Normal';
            document.getElementById('needByDateContainer').classList.add('hidden');
            document.getElementById('rfq_need_by_date').removeAttribute('required');
            document.getElementById('commentCharCount').textContent = '0';
            
            // Get token for RFQ
            getTokenForRFQ();
            
            const modal = document.getElementById('rfqModal');
            modal.classList.remove('hidden');
        }
        
        function closeRFQModal() {
            const modal = document.getElementById('rfqModal');
            modal.classList.add('hidden');
            currentRFQPartId = null;
            currentRFQToken = null;
        }
        
        // Get token for RFQ
        function getTokenForRFQ() {
            const formData = new FormData();
            formData.append('action', 'get_token');
            
            fetch('', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.token) {
                    currentRFQToken = data.token;
                } else {
                    alert('Failed to get authentication token: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error getting token:', error);
                alert('Error getting authentication token');
            });
        }
        
        // Handle priority change
        document.getElementById('rfq_priority').addEventListener('change', function() {
            const needByDateContainer = document.getElementById('needByDateContainer');
            const needByDateInput = document.getElementById('rfq_need_by_date');
            
            if (this.value === 'NeedByDate') {
                needByDateContainer.classList.remove('hidden');
                needByDateInput.setAttribute('required', 'required');
            } else {
                needByDateContainer.classList.add('hidden');
                needByDateInput.removeAttribute('required');
                needByDateInput.value = '';
            }
        });
        
        // Character count for comment
        document.getElementById('rfq_comment').addEventListener('input', function() {
            document.getElementById('commentCharCount').textContent = this.value.length;
        });
        
        // Handle RFQ form submission
        document.getElementById('rfqForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!currentRFQToken) {
                alert('Please wait for authentication token to be retrieved.');
                return;
            }
            
            if (!currentRFQPartId) {
                alert('Part ID is missing.');
                return;
            }
            
            const priority = document.getElementById('rfq_priority').value;
            const needByDate = document.getElementById('rfq_need_by_date').value;
            const validUntil = document.getElementById('rfq_valid_until').value;
            const comment = document.getElementById('rfq_comment').value;
            const refNumber = document.getElementById('rfq_ref_number').value;
            const qty = document.getElementById('rfqQuantityHidden').value;
            
            // Validate NeedByDate if priority is NeedByDate
            if (priority === 'NeedByDate' && !needByDate) {
                alert('Need By Date is required when priority is "Need by Date".');
                return;
            }
            
            // Prepare RFQ data
            const rfqData = {
                parts: [
                    {
                        id: currentRFQPartId,
                        qty: qty,
                        priority: priority
                    }
                ]
            };
            
            // Add optional fields
            if (priority === 'NeedByDate' && needByDate) {
                rfqData.parts[0].needByDate = needByDate;
            }
            if (validUntil) {
                rfqData.validUntil = validUntil;
            }
            if (comment) {
                rfqData.comment = comment;
            }
            if (refNumber) {
                rfqData.refNumber = refNumber;
            }
            
            // Submit button loading state
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
            
            // Send RFQ
            const formData = new FormData();
            formData.append('action', 'create_rfq');
            formData.append('rfq_data', JSON.stringify(rfqData));
            formData.append('token', currentRFQToken);
            
            fetch('', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
                
                if (data.success) {
                    alert('RFQ created successfully! RFQ Group: ' + (data.rfqGroup || 'N/A'));
                    closeRFQModal();
                } else {
                    alert('Failed to create RFQ: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
                console.error('Error creating RFQ:', error);
                alert('Error creating RFQ: ' + error.message);
            });
        });
        
        // Close RFQ modal when clicking outside
        document.getElementById('rfqModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRFQModal();
            }
        });
    </script>
</body>
</html>
