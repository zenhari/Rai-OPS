<?php
require_once '../../../config.php';

// Check if GD extension is loaded
if (!extension_loaded('gd')) {
    die('
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>GD Extension Required - ' . PROJECT_NAME . '</title>
        <script src="/assets/js/tailwind.js"></script>
        <link rel="stylesheet" href="/assets/css/roboto.css">
        <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    </head>
    <body class="bg-gray-50 dark:bg-gray-900">
        <div class="min-h-screen flex items-center justify-center p-6">
            <div class="max-w-md w-full bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-5xl mb-4"></i>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">GD Extension Required</h1>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The PHP GD extension is not enabled. This extension is required for certificate image generation.
                    </p>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md p-4 text-left mb-4">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200 font-semibold mb-2">To enable GD extension:</p>
                        <ol class="text-sm text-yellow-700 dark:text-yellow-300 list-decimal list-inside space-y-1">
                            <li>Open your <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">php.ini</code> file</li>
                            <li>Find the line: <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">;extension=gd</code></li>
                            <li>Remove the semicolon: <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">extension=gd</code></li>
                            <li>Restart your web server (Apache/XAMPP)</li>
                        </ol>
                    </div>
                    <a href="/dashboard/" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    ');
}

// Check access
checkPageAccessWithRedirect('admin/training/certificate/issue_certificate.php');

$current_user = getCurrentUser();
$message = '';
$error = '';
$certificateGenerated = false;
$certificateImageUrl = '';

$db = getDBConnection();

// Get user role from form or default to Training
$userRole = $_POST['user_role'] ?? 'Training'; // Can be 'Training' or 'Operation'

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nationalid = trim($_POST['nationalid'] ?? '');
    $certificateno = trim($_POST['certificateno'] ?? '');
    $name = strtoupper(trim($_POST['name'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $birthday = trim($_POST['birthday'] ?? '');
    $coursename = trim($_POST['coursename'] ?? '');
    $courseduration = trim($_POST['courseduration'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $issue_date = trim($_POST['issue_date'] ?? '');
    $expire_date = trim($_POST['expire_date'] ?? '');
    $certificate_type = trim($_POST['certificate_type'] ?? '');
    $issuance_auth = trim($_POST['issuance_auth'] ?? 'completion');
    
    // Validation
    if (empty($nationalid)) {
        $error = 'National ID is required.';
    } elseif (empty($certificateno)) {
        $error = 'Certificate Number is required.';
    } elseif (empty($name)) {
        $error = 'Name is required.';
    } elseif (empty($email)) {
        $error = 'Email is required.';
    } elseif (empty($coursename)) {
        $error = 'Course Name is required.';
    } elseif (empty($issue_date)) {
        $error = 'Issue Date is required.';
    } else {
        // Add RMAW- prefix to certificate number
        $fullCertificateno = 'RMAW-' . $certificateno;
        
        // Check if certificate number already exists
        $stmt = $db->prepare("SELECT id FROM certificates WHERE certificateno = ?");
        $stmt->execute([$fullCertificateno]);
        if ($stmt->fetch()) {
            $error = 'Certificate number already exists.';
        } else {
            try {
                // Start transaction
                $db->beginTransaction();
                
                // Insert into database
                $stmt = $db->prepare("INSERT INTO certificates 
                    (nationalid, certificateno, name, email, mobile, birthday, coursename, 
                     courseduration, start_date, end_date, issue_date, expire_date, 
                     certificate_type, issuance_auth) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $nationalid,
                    $fullCertificateno,
                    $name,
                    $email,
                    $mobile,
                    $birthday,
                    $coursename,
                    $courseduration,
                    $start_date ?: null,
                    $end_date ?: null,
                    $issue_date,
                    $expire_date ?: null,
                    $certificate_type ?: null,
                    $issuance_auth
                ]);
                
                // Generate QR Code
                $qrCodeContent = base_url() . 'admin/users/certificate/cer/' . $fullCertificateno . '.jpg';
                
                // Use QR API
                $qrApiBase = 'http://portal.raimonairways.net/raimon-cer/qr_api/qrapi.php';
                $qrApiUrl = $qrApiBase . '?' . http_build_query([
                    'size' => 250,
                    'text' => $qrCodeContent,
                ]);
                
                // Fetch QR image via cURL
                $ch = curl_init($qrApiUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 15,
                ]);
                
                $qrBinary = curl_exec($ch);
                $curlErr = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);
                
                if ($qrBinary === false || $httpCode !== 200) {
                    throw new Exception('Failed to generate QR code: ' . ($curlErr ?: 'HTTP ' . $httpCode));
                }
                
                // Ensure directories exist (use centralized location)
                // Build absolute paths properly
                $baseDir = dirname(dirname(dirname(__DIR__))); // Go up from admin/training/certificate to root
                $qrDirectory = $baseDir . '/admin/users/certificate/qr';
                $cerDirectory = $baseDir . '/admin/users/certificate/cer';
                
                // Normalize paths (remove redundant slashes)
                $qrDirectory = str_replace(['\\', '//'], '/', $qrDirectory);
                $cerDirectory = str_replace(['\\', '//'], '/', $cerDirectory);
                
                // Create QR directory if it doesn't exist
                if (!is_dir($qrDirectory)) {
                    if (!@mkdir($qrDirectory, 0775, true)) {
                        $error = error_get_last();
                        throw new Exception('Failed to create QR directory: ' . $qrDirectory . ($error ? ' - ' . $error['message'] : ''));
                    }
                }
                
                // Resolve absolute path for QR directory
                $qrDirectoryResolved = realpath($qrDirectory);
                if ($qrDirectoryResolved !== false) {
                    $qrDirectory = $qrDirectoryResolved;
                }
                
                // Check if QR directory is writable
                if (!is_writable($qrDirectory)) {
                    // Try to fix permissions (only if we can)
                    @chmod($qrDirectory, 0775);
                    if (!is_writable($qrDirectory)) {
                        throw new Exception('QR directory is not writable: ' . $qrDirectory . ' (current permissions: ' . substr(sprintf('%o', fileperms($qrDirectory)), -4) . ')');
                    }
                }
                
                // Create certificate directory if it doesn't exist
                if (!is_dir($cerDirectory)) {
                    if (!@mkdir($cerDirectory, 0775, true)) {
                        $error = error_get_last();
                        throw new Exception('Failed to create certificate directory: ' . $cerDirectory . ($error ? ' - ' . $error['message'] : ''));
                    }
                }
                
                // Resolve absolute path for certificate directory
                $cerDirectoryResolved = realpath($cerDirectory);
                if ($cerDirectoryResolved !== false) {
                    $cerDirectory = $cerDirectoryResolved;
                }
                
                // Check if certificate directory is writable
                if (!is_writable($cerDirectory)) {
                    // Try to fix permissions (only if we can)
                    @chmod($cerDirectory, 0775);
                    if (!is_writable($cerDirectory)) {
                        throw new Exception('Certificate directory is not writable: ' . $cerDirectory . ' (current permissions: ' . substr(sprintf('%o', fileperms($cerDirectory)), -4) . ')');
                    }
                }
                
                // Detect image type from binary data
                $imageType = 'jpeg'; // default
                if (substr($qrBinary, 0, 2) === "\x89\x50") {
                    // PNG file signature
                    $imageType = 'png';
                } elseif (substr($qrBinary, 0, 2) === "\xFF\xD8") {
                    // JPEG file signature
                    $imageType = 'jpeg';
                } elseif (substr($qrBinary, 0, 4) === "GIF8") {
                    // GIF file signature
                    $imageType = 'gif';
                }
                
                // Save QR code with appropriate extension
                $qrFilePath = $qrDirectory . '/' . $fullCertificateno . '.' . $imageType;
                if (file_put_contents($qrFilePath, $qrBinary) === false) {
                    throw new Exception('Failed to save QR code image.');
                }
                
                // Determine template based on role and issuance_auth
                $templateFile = '';
                if ($userRole == 'Training') {
                    if ($issuance_auth == 'completion') {
                        $templateFile = __DIR__ . '/templates/training_completion.jpg';
                    } elseif ($issuance_auth == 'attendance') {
                        $templateFile = __DIR__ . '/templates/training_attendance.jpg';
                    }
                } elseif ($userRole == 'Operation') {
                    if ($issuance_auth == 'completion') {
                        $templateFile = __DIR__ . '/templates/operation_completion.jpg';
                    } elseif ($issuance_auth == 'attendance') {
                        $templateFile = __DIR__ . '/templates/operation_attendance.jpg';
                    }
                }
                
                // Default to training_completion if not found
                if (empty($templateFile) || !file_exists($templateFile)) {
                    $templateFile = __DIR__ . '/templates/training_completion.jpg';
                }
                
                if (!file_exists($templateFile)) {
                    throw new Exception('Certificate template not found.');
                }
                
                // Load template image
                $image = imagecreatefromjpeg($templateFile);
                if (!$image) {
                    throw new Exception('Failed to load certificate template.');
                }
                
                // Load font
                $fontFile = __DIR__ . '/templates/arial.ttf';
                if (!file_exists($fontFile)) {
                    throw new Exception('Font file not found.');
                }
                
                // Define text properties
                $fontSize = 40;
                $fontColor = imagecolorallocate($image, 0, 0, 0);
                $fontCourse = imagecolorallocate($image, 201, 149, 59);
                $angle = 0;
                
                // Add text to image
                if ($nationalid) {
                    imagettftext($image, $fontSize, $angle, 2210, 905, $fontColor, $fontFile, $nationalid);
                }
                if ($birthday) {
                    imagettftext($image, $fontSize, $angle, 2230, 1030, $fontColor, $fontFile, $birthday);
                }
                if ($name) {
                    imagettftext($image, 50, $angle, 570, 905, $fontColor, $fontFile, $name);
                }
                if ($issue_date) {
                    imagettftext($image, $fontSize, $angle, 600, 1740, $fontColor, $fontFile, $issue_date);
                }
                if ($courseduration) {
                    imagettftext($image, $fontSize, $angle, 690, 1542, $fontColor, $fontFile, $courseduration . ' Hrs.');
                }
                if ($coursename) {
                    imagettftext($image, 80, $angle, 350, 1360, $fontCourse, $fontFile, $coursename);
                }
                if ($fullCertificateno) {
                    imagettftext($image, 35, $angle, 2365, 1542, $fontColor, $fontFile, $fullCertificateno);
                }
                if ($expire_date) {
                    imagettftext($image, 40, $angle, 2210, 1740, $fontColor, $fontFile, $expire_date);
                }
                if ($end_date) {
                    imagettftext($image, 40, $angle, 2150, 1640, $fontColor, $fontFile, $end_date);
                }
                if ($start_date) {
                    imagettftext($image, 40, $angle, 600, 1640, $fontColor, $fontFile, $start_date);
                }
                
                // Save certificate image (without QR first)
                $cerFilePath = $cerDirectory . '/' . $fullCertificateno . '.jpg';
                
                // Remove existing file if it exists (to avoid permission issues)
                if (file_exists($cerFilePath)) {
                    if (!unlink($cerFilePath)) {
                        imagedestroy($image);
                        throw new Exception('Failed to remove existing certificate file: ' . $cerFilePath);
                    }
                }
                
                // Try to save the image
                $saved = @imagejpeg($image, $cerFilePath, 100);
                if (!$saved) {
                    imagedestroy($image);
                    $errorDetails = error_get_last();
                    $errorMsg = 'Failed to save certificate image to: ' . $cerFilePath;
                    if ($errorDetails) {
                        $errorMsg .= ' - Error: ' . $errorDetails['message'];
                    }
                    if (!is_writable($cerDirectory)) {
                        $errorMsg .= ' - Directory is not writable';
                    }
                    // Check disk space
                    $freeSpace = disk_free_space($cerDirectory);
                    if ($freeSpace !== false && $freeSpace < 1024 * 1024) { // Less than 1MB
                        $errorMsg .= ' - Insufficient disk space';
                    }
                    throw new Exception($errorMsg);
                }
                
                // Verify file was created
                if (!file_exists($cerFilePath)) {
                    imagedestroy($image);
                    throw new Exception('Certificate file was not created: ' . $cerFilePath);
                }
                
                // Load QR code image based on detected type
                $qrImage = null;
                switch ($imageType) {
                    case 'png':
                        $qrImage = imagecreatefrompng($qrFilePath);
                        break;
                    case 'jpeg':
                    case 'jpg':
                        $qrImage = imagecreatefromjpeg($qrFilePath);
                        break;
                    case 'gif':
                        $qrImage = imagecreatefromgif($qrFilePath);
                        break;
                    default:
                        // Try to detect from file
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $qrFilePath);
                        finfo_close($finfo);
                        
                        if (strpos($mimeType, 'png') !== false) {
                            $qrImage = imagecreatefrompng($qrFilePath);
                        } elseif (strpos($mimeType, 'jpeg') !== false || strpos($mimeType, 'jpg') !== false) {
                            $qrImage = imagecreatefromjpeg($qrFilePath);
                        } elseif (strpos($mimeType, 'gif') !== false) {
                            $qrImage = imagecreatefromgif($qrFilePath);
                        } else {
                            // Try PNG first, then JPEG
                            $qrImage = @imagecreatefrompng($qrFilePath);
                            if (!$qrImage) {
                                $qrImage = @imagecreatefromjpeg($qrFilePath);
                            }
                        }
                        break;
                }
                
                if (!$qrImage) {
                    imagedestroy($image);
                    throw new Exception('Failed to load QR code image. File type: ' . $imageType);
                }
                
                // Get dimensions
                $mainWidth = imagesx($image);
                $mainHeight = imagesy($image);
                $qrWidth = imagesx($qrImage);
                $qrHeight = imagesy($qrImage);
                
                // Create result image
                $resultImage = imagecreatetruecolor($mainWidth, $mainHeight);
                
                // Copy main image
                imagecopy($resultImage, $image, 0, 0, 0, 0, $mainWidth, $mainHeight);
                
                // Add QR code at position (2900, 1990)
                // If PNG, preserve transparency, otherwise just copy
                if ($imageType === 'png') {
                    // Enable alpha blending for PNG transparency
                    imagealphablending($resultImage, true);
                    imagesavealpha($resultImage, true);
                    imagecopy($resultImage, $qrImage, 2900, 1990, 0, 0, $qrWidth, $qrHeight);
                } else {
                    imagecopy($resultImage, $qrImage, 2900, 1990, 0, 0, $qrWidth, $qrHeight);
                }
                
                // Save final certificate with QR (overwrite the previous one)
                $saved = @imagejpeg($resultImage, $cerFilePath, 100);
                if (!$saved) {
                    imagedestroy($image);
                    imagedestroy($qrImage);
                    imagedestroy($resultImage);
                    $errorDetails = error_get_last();
                    $errorMsg = 'Failed to save final certificate image to: ' . $cerFilePath;
                    if ($errorDetails) {
                        $errorMsg .= ' - Error: ' . $errorDetails['message'];
                    }
                    if (!is_writable($cerDirectory)) {
                        $errorMsg .= ' - Directory is not writable';
                    }
                    // Check disk space
                    $freeSpace = disk_free_space($cerDirectory);
                    if ($freeSpace !== false && $freeSpace < 1024 * 1024) { // Less than 1MB
                        $errorMsg .= ' - Insufficient disk space';
                    }
                    throw new Exception($errorMsg);
                }
                
                // Verify file was created/updated
                if (!file_exists($cerFilePath) || filesize($cerFilePath) == 0) {
                    imagedestroy($image);
                    imagedestroy($qrImage);
                    imagedestroy($resultImage);
                    throw new Exception('Final certificate file was not created or is empty: ' . $cerFilePath);
                }
                
                // Clean up
                imagedestroy($image);
                imagedestroy($qrImage);
                imagedestroy($resultImage);
                
                // Commit transaction
                $db->commit();
                
                $message = 'Certificate issued successfully!';
                $certificateGenerated = true;
                $certificateImageUrl = base_url() . 'admin/users/certificate/cer/' . $fullCertificateno . '.jpg';
                
                // Clear form data after successful submission
                $_POST = [];
                
            } catch (Exception $e) {
                // Rollback transaction
                $db->rollBack();
                error_log("Error issuing certificate: " . $e->getMessage());
                $error = 'Failed to issue certificate: ' . $e->getMessage();
            } catch (PDOException $e) {
                // Rollback transaction
                $db->rollBack();
                error_log("Database error issuing certificate: " . $e->getMessage());
                $error = 'Failed to issue certificate. Please try again.';
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
    <title>Issue Certificate - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <i class="fas fa-certificate mr-2"></i>Issue Certificate
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Issue a new training certificate with QR code
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <div class="w-full">
                    <?php if ($message): ?>
                        <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-md">
                            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-md">
                            <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($certificateGenerated && $certificateImageUrl): ?>
                        <!-- Certificate Preview -->
                        <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-image mr-2"></i>Generated Certificate
                            </h2>
                            <div class="flex justify-center mb-4">
                                <img src="<?php echo htmlspecialchars($certificateImageUrl); ?>" 
                                     alt="Certificate" 
                                     class="max-w-full h-auto border-2 border-gray-300 dark:border-gray-600 shadow-lg rounded-lg">
                            </div>
                            <div class="flex justify-center space-x-3">
                                <a href="<?php echo htmlspecialchars($certificateImageUrl); ?>" 
                                   download
                                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                                    <i class="fas fa-download mr-2"></i>Download Certificate
                                </a>
                                <a href="?" 
                                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                                    <i class="fas fa-plus mr-2"></i>Issue Another Certificate
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Certificate Form -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                        <form method="POST" class="p-6 space-y-6">
                            <!-- Personal Information Section -->
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                    <i class="fas fa-user mr-2"></i>Personal Information
                                </h2>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="nationalid" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            National ID <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" id="nationalid" name="nationalid" required
                                               value="<?php echo htmlspecialchars($_POST['nationalid'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label for="certificateno" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Certificate Number <span class="text-red-500">*</span>
                                            <span class="text-xs text-gray-500">(RMAW- prefix will be added automatically)</span>
                                        </label>
                                        <input type="text" id="certificateno" name="certificateno" required
                                               value="<?php echo htmlspecialchars($_POST['certificateno'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Full Name <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" id="name" name="name" required
                                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                               style="text-transform: uppercase;"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               oninput="this.value = this.value.toUpperCase();">
                                    </div>
                                    
                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Email <span class="text-red-500">*</span>
                                        </label>
                                        <input type="email" id="email" name="email" required
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label for="mobile" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Mobile
                                        </label>
                                        <input type="text" id="mobile" name="mobile"
                                               value="<?php 
                                               $mobileValue = trim($_POST['mobile'] ?? '');
                                               if (empty($mobileValue)) {
                                                   $mobileValue = trim($_POST['phone'] ?? '');
                                               }
                                               echo htmlspecialchars($mobileValue); 
                                               ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label for="birthday" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Birthday
                                        </label>
                                        <input type="date" id="birthday" name="birthday"
                                               value="<?php echo htmlspecialchars($_POST['birthday'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                </div>
                            </div>

                            <!-- Course Information Section -->
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                    <i class="fas fa-graduation-cap mr-2"></i>Course Information
                                </h2>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="coursename" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Course Name <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" id="coursename" name="coursename" required
                                               value="<?php echo htmlspecialchars($_POST['coursename'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label for="courseduration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Course Duration
                                        </label>
                                        <input type="text" id="courseduration" name="courseduration"
                                               value="<?php echo htmlspecialchars($_POST['courseduration'] ?? ''); ?>"
                                               placeholder="e.g., 40"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Start Date
                                        </label>
                                        <input type="date" id="start_date" name="start_date"
                                               value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            End Date
                                        </label>
                                        <input type="date" id="end_date" name="end_date"
                                               value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                </div>
                            </div>

                            <!-- Certificate Details Section -->
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                    <i class="fas fa-certificate mr-2"></i>Certificate Details
                                </h2>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="issue_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Issue Date <span class="text-red-500">*</span>
                                        </label>
                                        <input type="date" id="issue_date" name="issue_date" required
                                               value="<?php echo htmlspecialchars($_POST['issue_date'] ?? date('Y-m-d')); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label for="expire_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Expire Date
                                        </label>
                                        <input type="date" id="expire_date" name="expire_date"
                                               value="<?php echo htmlspecialchars($_POST['expire_date'] ?? ''); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    
                                    <div>
                                        <label for="user_role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Department
                                        </label>
                                        <select id="user_role" name="user_role"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                            <option value="Training" <?php echo (($_POST['user_role'] ?? 'Training') === 'Training') ? 'selected' : ''; ?>>Training</option>
                                            <option value="Operation" <?php echo (($_POST['user_role'] ?? '') === 'Operation') ? 'selected' : ''; ?>>Operation</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="issuance_auth" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Issuance Authority
                                        </label>
                                        <select id="issuance_auth" name="issuance_auth"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                            <option value="completion" <?php echo (($_POST['issuance_auth'] ?? 'completion') === 'completion') ? 'selected' : ''; ?>>Completion</option>
                                            <option value="attendance" <?php echo (($_POST['issuance_auth'] ?? '') === 'attendance') ? 'selected' : ''; ?>>Attendance</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <button type="reset"
                                        class="px-6 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                                    <i class="fas fa-redo mr-2"></i>Reset
                                </button>
                                <button type="submit"
                                        class="px-6 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-save mr-2"></i>Issue Certificate
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-fill form fields based on National ID
        document.addEventListener('DOMContentLoaded', function() {
            const nationalIdInput = document.getElementById('nationalid');
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const mobileInput = document.getElementById('mobile');
            const birthdayInput = document.getElementById('birthday');
            
            let searchTimeout = null;
            
            nationalIdInput.addEventListener('input', function() {
                const nationalId = this.value.trim();
                
                // Clear previous timeout
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                // Only search if National ID has at least 5 characters
                if (nationalId.length < 5) {
                    return;
                }
                
                // Debounce: wait 500ms after user stops typing
                searchTimeout = setTimeout(function() {
                    // Show loading indicator
                    nationalIdInput.classList.add('opacity-50');
                    
                    // Fetch user data
                    fetch(`<?php echo base_url(); ?>admin/api/search_user_by_national_id.php?national_id=${encodeURIComponent(nationalId)}`)
                        .then(response => response.json())
                        .then(data => {
                            nationalIdInput.classList.remove('opacity-50');
                            
                            if (data.success && data.user) {
                                const user = data.user;
                                
                                // Fill Full Name (convert to uppercase)
                                if (user.full_name && !nameInput.value) {
                                    nameInput.value = user.full_name.toUpperCase();
                                }
                                
                                // Fill Email
                                if (user.email && !emailInput.value) {
                                    emailInput.value = user.email;
                                }
                                
                                // Fill Mobile (use mobile first, if empty or null use phone)
                                if (!mobileInput.value) {
                                    if (user.mobile && user.mobile.trim() !== '') {
                                        mobileInput.value = user.mobile;
                                    } else if (user.phone && user.phone.trim() !== '') {
                                        mobileInput.value = user.phone;
                                    }
                                }
                                
                                // Fill Birthday
                                if (user.date_of_birth && !birthdayInput.value) {
                                    birthdayInput.value = user.date_of_birth;
                                }
                                
                                // Show success message
                                showNotification('User information loaded successfully', 'success');
                            } else {
                                // User not found - don't show error, just don't fill fields
                                // showNotification('User not found with this National ID', 'info');
                            }
                        })
                        .catch(error => {
                            nationalIdInput.classList.remove('opacity-50');
                            console.error('Error fetching user data:', error);
                            // Don't show error to user, just log it
                        });
                }, 500);
            });
            
            // Helper function to show notifications
            function showNotification(message, type) {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-md shadow-lg ${
                    type === 'success' ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400' :
                    type === 'error' ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400' :
                    'bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-400'
                }`;
                notification.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
                        <span>${message}</span>
                    </div>
                `;
                
                document.body.appendChild(notification);
                
                // Remove after 3 seconds
                setTimeout(() => {
                    notification.style.transition = 'opacity 0.3s';
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }
        });
    </script>
</body>
</html>
