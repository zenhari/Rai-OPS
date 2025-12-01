<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/profile/my_certificate.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get current user's national ID
$nationalId = $current_user['national_id'] ?? '';

// Fetch certificate data from API
$certificateData = [];
$loading = false;

if (!empty($nationalId)) {
    $loading = true;
    try {
        $baseUrl = 'portal.raimonairways.net/api/cer_api.php?token=f35c82b4-de5a-4192-8ef6-6aeceb3875d0';
        
        // Try multiple URL configurations: https with SSL, https without SSL, and http
        $urls = [
            ['url' => 'https://' . $baseUrl, 'ssl_verify' => true],
            ['url' => 'https://' . $baseUrl, 'ssl_verify' => false],
            ['url' => 'http://' . $baseUrl, 'ssl_verify' => false]
        ];
        
        $lastError = null;
        $success = false;
        $attempts = [];
        
        foreach ($urls as $index => $urlConfig) {
            $apiUrl = $urlConfig['url'];
            $sslVerify = $urlConfig['ssl_verify'];
            $protocol = parse_url($apiUrl, PHP_URL_SCHEME);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['nationalid' => $nationalId]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);
            
            // Log attempt
            $attemptInfo = [
                'protocol' => $protocol,
                'ssl_verify' => $sslVerify,
                'http_code' => $httpCode,
                'curl_error' => $curlError,
                'curl_errno' => $curlErrno
            ];
            $attempts[] = $attemptInfo;
            
            // If successful, parse the response
            if (!$curlError && $httpCode === 200 && !empty($response)) {
                $decodedResponse = json_decode($response, true);
                
                if (json_last_error() === JSON_ERROR_NONE && $decodedResponse) {
                    // Check if response has data
                    if (isset($decodedResponse['data']) || isset($decodedResponse['certificates']) || (is_array($decodedResponse) && !empty($decodedResponse))) {
                        $certificateData = $decodedResponse;
                        $success = true;
                        error_log("Certificate API Success for national_id $nationalId using: $protocol (SSL Verify: " . ($sslVerify ? 'Yes' : 'No') . ")");
                        break;
                    } elseif (isset($decodedResponse['success']) && $decodedResponse['success']) {
                        $certificateData = $decodedResponse['data'] ?? $decodedResponse;
                        $success = true;
                        error_log("Certificate API Success for national_id $nationalId using: $protocol (SSL Verify: " . ($sslVerify ? 'Yes' : 'No') . ")");
                        break;
                    }
                }
            }
            
            // Store error for this attempt
            $errorMsg = "Protocol: $protocol, SSL Verify: " . ($sslVerify ? 'Yes' : 'No') . ", HTTP Code: $httpCode";
            if ($curlError) {
                $errorMsg .= ", cURL Error: $curlError (Code: $curlErrno)";
            }
            $lastError = $errorMsg;
        }
        
        if (!$success) {
            $allErrors = implode(' | ', array_map(function($attempt) {
                return $attempt['protocol'] . ' (SSL: ' . ($attempt['ssl_verify'] ? 'Yes' : 'No') . ') - HTTP: ' . $attempt['http_code'] . ($attempt['curl_error'] ? ' - Error: ' . $attempt['curl_error'] : '');
            }, $attempts));
            $error = 'Failed to fetch certificate data from API. Attempted: ' . $allErrors;
            error_log("Certificate API Error for national_id $nationalId. All attempts: " . json_encode($attempts));
        }
        
        $loading = false;
    } catch (Exception $e) {
        $error = 'Error fetching certificate data: ' . $e->getMessage();
        error_log("Certificate API Exception: " . $e->getMessage());
        $loading = false;
    }
} else {
    $error = 'National ID not found for your account. Please contact administrator to add your National ID.';
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificate - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { 
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        @media print {
            .no-print { display: none !important; }
        }
        
        /* Certificate Card - Minimal Design */
        .certificate-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .certificate-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
            z-index: 1;
        }
        
        .certificate-card:hover::before {
            transform: scaleX(1);
        }
        
        .certificate-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .certificate-card:hover {
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            }
        }
        
        .certificate-image {
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .certificate-card:hover .certificate-image {
            transform: scale(1.08);
        }
        
        /* Image Modal - Minimal & Elegant */
        .image-modal {
            backdrop-filter: blur(8px);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Loading State */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-spinner {
            animation: spin 1s linear infinite;
        }
        
        /* Smooth Scrollbar */
        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
        }
        
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(156, 163, 175, 0.5);
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(156, 163, 175, 0.7);
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .custom-scrollbar {
                scrollbar-color: rgba(75, 85, 99, 0.5) transparent;
            }
            
            html:not(.light) .custom-scrollbar::-webkit-scrollbar-thumb {
                background: rgba(75, 85, 99, 0.5);
            }
            
            html:not(.light) .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background: rgba(75, 85, 99, 0.7);
            }
        }
        
        /* Badge Animation */
        .cert-badge {
            animation: fadeInScale 0.5s ease;
        }
        
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Gradient Background for Cards */
        .gradient-bg {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0e7ff 100%);
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .gradient-bg {
                background: linear-gradient(135deg, #1e293b 0%, #1e1b4b 100%);
            }
        }
        
        /* Hover Overlay */
        .hover-overlay {
            transition: all 0.3s ease;
        }
        
        .certificate-card:hover .hover-overlay {
            opacity: 1;
        }
        
        /* Typography */
        .cert-title {
            font-weight: 600;
            letter-spacing: -0.025em;
        }
        
        /* Card Shadow on Hover */
        .certificate-card {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .certificate-card {
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3), 0 1px 2px 0 rgba(0, 0, 0, 0.2);
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 no-print">
                <div class="px-6 py-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white cert-title">My Certificates</h1>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                <i class="fas fa-user-circle mr-1.5"></i>
                                <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?>
                                <?php if (!empty($nationalId)): ?>
                                    <span class="mx-2">•</span>
                                    <span class="font-mono text-xs">ID: <?php echo htmlspecialchars($nationalId); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="index.php" 
                               class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-all duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back
                            </a>
                            <button onclick="window.print()" 
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 dark:bg-blue-500 hover:bg-blue-700 dark:hover:bg-blue-600 rounded-lg transition-all duration-200 shadow-sm hover:shadow">
                                <i class="fas fa-print mr-2"></i>
                                Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
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

                <?php if ($loading): ?>
                    <div class="flex items-center justify-center min-h-[60vh]">
                        <div class="text-center">
                            <div class="inline-block relative mb-6">
                                <div class="w-16 h-16 border-4 border-blue-200 dark:border-blue-800 border-t-blue-600 dark:border-t-blue-400 rounded-full loading-spinner"></div>
                                <i class="fas fa-certificate text-blue-600 dark:text-blue-400 text-2xl absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"></i>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400 text-lg font-medium">Loading certificates...</p>
                            <p class="text-gray-500 dark:text-gray-500 text-sm mt-2">Please wait</p>
                        </div>
                    </div>
                <?php elseif (!empty($certificateData)): ?>
                    <?php
                    // Handle different response formats
                    $certificates = [];
                    if (isset($certificateData['data']) && is_array($certificateData['data'])) {
                        $certificates = $certificateData['data'];
                    } elseif (isset($certificateData['certificates']) && is_array($certificateData['certificates'])) {
                        $certificates = $certificateData['certificates'];
                    } elseif (is_array($certificateData) && isset($certificateData[0])) {
                        $certificates = $certificateData;
                    } else {
                        $certificates = [$certificateData];
                    }
                    $totalCertificates = count($certificates);
                    ?>
                    
                    <!-- Stats Summary -->
                    <div class="mb-8">
                        <div class="inline-flex items-center px-4 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <i class="fas fa-certificate text-blue-600 dark:text-blue-400 mr-3"></i>
                            <span class="text-sm font-medium text-blue-900 dark:text-blue-200">
                                <?php echo $totalCertificates; ?> Certificate<?php echo $totalCertificates != 1 ? 's' : ''; ?> Found
                            </span>
                        </div>
                    </div>
                    
                    <!-- Certificate Cards Grid -->
                    <div class="mb-6">
                        <?php if (empty($certificates)): ?>
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-16 text-center">
                                <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                    <i class="fas fa-inbox text-gray-400 dark:text-gray-500 text-4xl"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No Certificates Found</h3>
                                <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                                    No certificate records found for your National ID. Please contact administrator if you believe this is an error.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                <?php foreach ($certificates as $index => $cert): 
                                        // Try different possible field names for certificate number
                                        $certificateNo = '';
                                        $possibleKeys = ['certificateno', 'certificate_no', 'CertificateNo', 'certificateNo', 'CERTIFICATENO', 'certificate_number', 'CertificateNumber'];
                                        foreach ($possibleKeys as $key) {
                                            if (isset($cert[$key]) && !empty($cert[$key])) {
                                                $certificateNo = $cert[$key];
                                                break;
                                            }
                                        }
                                        
                                        $imageUrl = '';
                                        if (!empty($certificateNo)) {
                                            $imageUrl = 'https://portal.raimonairways.net/raimon-cer/cer/' . htmlspecialchars($certificateNo) . '.jpg';
                                        }
                                        
                                        // Get other fields for display
                                        $excludedKeys = ['certificateno', 'certificate_no', 'CertificateNo', 'certificateNo', 'CERTIFICATENO', 'certificate_number', 'CertificateNumber'];
                                        $displayFields = [];
                                        foreach ($cert as $key => $value) {
                                            if (!in_array(strtolower($key), array_map('strtolower', $excludedKeys))) {
                                                $displayFields[$key] = $value;
                                            }
                                        }
                                    ?>
                                        <div class="certificate-card bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden group" 
                                             data-image-url="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES); ?>"
                                             data-certificate-no="<?php echo htmlspecialchars($certificateNo, ENT_QUOTES); ?>"
                                             data-details="<?php echo htmlspecialchars(json_encode($displayFields), ENT_QUOTES, 'UTF-8'); ?>"
                                             onclick="openImageModalFromCard(this)">
                                            <!-- Certificate Image -->
                                            <div class="relative h-72 gradient-bg overflow-hidden">
                                                <?php if (!empty($imageUrl)): ?>
                                                    <img src="<?php echo $imageUrl; ?>" 
                                                         alt="Certificate <?php echo htmlspecialchars($certificateNo); ?>"
                                                         class="certificate-image w-full h-full object-cover"
                                                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'300\'%3E%3Crect fill=\'%23e5e7eb\' width=\'400\' height=\'300\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%239ca3af\' font-family=\'sans-serif\' font-size=\'16\'%3ENo Image Available%3C/text%3E%3C/svg%3E';">
                                                <?php else: ?>
                                                    <div class="flex items-center justify-center w-full h-full">
                                                        <div class="text-center">
                                                            <i class="fas fa-certificate text-gray-400 dark:text-gray-500 text-6xl mb-2"></i>
                                                            <p class="text-gray-500 dark:text-gray-400 text-sm">No Image</p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Overlay on hover -->
                                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-black/0 hover-overlay opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                                    <div class="transform translate-y-4 group-hover:translate-y-0 transition-transform duration-300">
                                                        <div class="bg-white/20 backdrop-blur-sm rounded-full p-4">
                                                            <i class="fas fa-expand text-white text-2xl"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Certificate No Badge -->
                                                <?php if (!empty($certificateNo)): ?>
                                                    <div class="absolute top-4 right-4 cert-badge bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm text-gray-900 dark:text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-lg border border-gray-200 dark:border-gray-700">
                                                        <i class="fas fa-hashtag mr-1 text-blue-600 dark:text-blue-400"></i>
                                                        <?php echo htmlspecialchars($certificateNo); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Certificate Details -->
                                            <div class="p-5">
                                                <div class="flex items-center justify-between mb-4">
                                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                                        Certificate #<?php echo $index + 1; ?>
                                                    </h3>
                                                    <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">
                                                        <?php echo $totalCertificates; ?> total
                                                    </span>
                                                </div>
                                                
                                                <!-- Display first few important fields -->
                                                <div class="space-y-2.5 mb-4">
                                                    <?php 
                                                    $fieldCount = 0;
                                                    foreach ($displayFields as $key => $value): 
                                                        if ($fieldCount >= 2) break; // Show only first 2 fields
                                                        if (is_array($value) || is_object($value) || empty($value)) continue;
                                                        $fieldCount++;
                                                    ?>
                                                        <div class="flex items-start gap-2">
                                                            <span class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide flex-shrink-0">
                                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?>:
                                                            </span>
                                                            <span class="text-sm text-gray-900 dark:text-white font-medium flex-1 truncate" title="<?php echo htmlspecialchars($value); ?>">
                                                                <?php 
                                                                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                                                                    echo date('M d, Y', strtotime($value));
                                                                } else {
                                                                    echo htmlspecialchars(strlen($value) > 25 ? substr($value, 0, 25) . '...' : $value);
                                                                }
                                                                ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    
                                                    <?php if (count($displayFields) > 2): ?>
                                                        <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                                                            <span class="text-xs text-blue-600 dark:text-blue-400 font-medium inline-flex items-center">
                                                                <i class="fas fa-ellipsis-h mr-1.5"></i>
                                                                <?php echo count($displayFields) - 2; ?> more field<?php echo (count($displayFields) - 2) != 1 ? 's' : ''; ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Click to view hint -->
                                                <div class="pt-3 border-t border-gray-100 dark:border-gray-700">
                                                    <p class="text-xs text-center text-gray-400 dark:text-gray-500 font-medium">
                                                        <i class="fas fa-eye mr-1.5"></i>
                                                        Click to view details
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif (!$loading && empty($error)): ?>
                    <!-- No Data State -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                        <div class="p-12 text-center">
                            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                <i class="fas fa-inbox text-gray-400 dark:text-gray-500 text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No Certificate Records</h3>
                            <p class="text-gray-500 dark:text-gray-400">
                                No certificate records found for National ID: <?php echo htmlspecialchars($nationalId); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Certificate Details Modal - Minimal -->
    <div id="imageModal" class="fixed inset-0 bg-black/80 image-modal z-50 hidden items-center justify-center p-4" onclick="closeImageModal()">
        <div class="relative max-w-3xl w-full max-h-[90vh] flex flex-col modal-content bg-white dark:bg-gray-800 rounded-2xl shadow-2xl" onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h3 id="modalCertificateNo" class="text-gray-900 dark:text-white text-xl font-bold mb-1"></h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        Certificate Details
                    </p>
                </div>
                <button onclick="closeImageModal()" 
                        class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg p-2 transition-all duration-200">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <!-- Modal Content -->
            <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
                <!-- Download Button -->
                <div class="mb-6">
                    <button id="downloadImageBtn" 
                            onclick="downloadCertificateImage()"
                            class="inline-flex items-center justify-center w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition-all duration-200 shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-download mr-2"></i>
                        Download Certificate Image
                    </button>
                </div>
                
                <!-- Certificate Details - Minimal -->
                <div id="modalDetailsContent" class="space-y-3">
                    <!-- Details will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function openImageModalFromCard(cardElement) {
            const imageUrl = cardElement.getAttribute('data-image-url') || '';
            const certificateNo = cardElement.getAttribute('data-certificate-no') || '';
            const detailsJson = cardElement.getAttribute('data-details') || '{}';
            
            let details = {};
            try {
                details = JSON.parse(detailsJson);
            } catch (e) {
                console.error('Error parsing details JSON:', e);
                details = {};
            }
            
            openImageModal(imageUrl, certificateNo, details);
        }

        let currentImageUrl = '';
        let currentCertificateNo = '';

        function openImageModal(imageUrl, certificateNo, details) {
            const modal = document.getElementById('imageModal');
            const modalCertificateNo = document.getElementById('modalCertificateNo');
            const modalDetailsContent = document.getElementById('modalDetailsContent');
            const downloadImageBtn = document.getElementById('downloadImageBtn');
            
            // Store current image URL and certificate number
            currentImageUrl = imageUrl || '';
            currentCertificateNo = certificateNo || '';
            
            // Set certificate number
            modalCertificateNo.textContent = certificateNo ? 'Certificate #' + certificateNo : 'Certificate Details';
            
            // Enable/disable download button
            if (currentImageUrl && currentImageUrl !== '') {
                downloadImageBtn.disabled = false;
                downloadImageBtn.style.display = 'inline-flex';
            } else {
                downloadImageBtn.disabled = true;
                downloadImageBtn.style.display = 'none';
            }
            
            // Populate details - Minimal design
            if (details && typeof details === 'object' && Object.keys(details).length > 0) {
                modalDetailsContent.innerHTML = '';
                for (const [key, value] of Object.entries(details)) {
                    if (value !== null && value !== '' && typeof value !== 'object') {
                        const fieldName = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                        let displayValue = String(value);
                        
                        // Format dates
                        if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) {
                            try {
                                displayValue = new Date(value).toLocaleDateString('en-US', { 
                                    year: 'numeric', 
                                    month: 'short', 
                                    day: 'numeric' 
                                });
                            } catch (e) {
                                displayValue = value;
                            }
                        }
                        
                        // Escape HTML to prevent XSS
                        const fieldNameEscaped = fieldName.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        const displayValueEscaped = displayValue.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        
                        const detailItem = document.createElement('div');
                        detailItem.className = 'flex items-start justify-between py-2.5 border-b border-gray-200 dark:border-gray-700 last:border-0';
                        detailItem.innerHTML = `
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400 flex-shrink-0 w-1/3">
                                ${fieldNameEscaped}
                            </span>
                            <span class="text-sm text-gray-900 dark:text-white font-medium text-right flex-1 ml-4">
                                ${displayValueEscaped}
                            </span>
                        `;
                        modalDetailsContent.appendChild(detailItem);
                    }
                }
                
                if (modalDetailsContent.children.length === 0) {
                    modalDetailsContent.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-sm text-center py-4">No additional details available</p>';
                }
            } else {
                modalDetailsContent.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-sm text-center py-4">No additional details available</p>';
            }
            
            // Show modal
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        // Download certificate image
        function downloadCertificateImage() {
            if (!currentImageUrl || currentImageUrl === '') {
                return;
            }
            
            const downloadBtn = document.getElementById('downloadImageBtn');
            const originalText = downloadBtn.innerHTML;
            downloadBtn.disabled = true;
            downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Downloading...';
            
            // Try direct download first
            const link = document.createElement('a');
            link.href = currentImageUrl;
            link.download = currentCertificateNo ? `certificate-${currentCertificateNo}.jpg` : 'certificate.jpg';
            link.target = '_blank';
            
            // Try to download
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Reset button after a delay
            setTimeout(() => {
                downloadBtn.disabled = false;
                downloadBtn.innerHTML = originalText;
            }, 1000);
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
    </script>
</body>
</html>

