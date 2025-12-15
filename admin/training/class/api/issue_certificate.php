<?php
/**
 * API Endpoint for automatically issuing certificates from class view
 * This endpoint receives student and class information and issues a certificate
 */

// Disable error display and start output buffering
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set custom error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        ob_end_flush();
    }
});

// Start output buffering to prevent any accidental output
if (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

try {
    // From admin/training/class/api/ to root: go up 4 levels
    // Use relative path: ../../../../config.php
    $configPath = __DIR__ . '/../../../../config.php';
    if (!file_exists($configPath)) {
        // Try alternative path in case of different structure
        $configPath = dirname(dirname(dirname(dirname(__DIR__)))) . '/config.php';
        if (!file_exists($configPath)) {
            throw new Exception('Config file not found. Tried: ' . __DIR__ . '/../../../../config.php and ' . $configPath);
        }
    }
    require_once $configPath;
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Configuration error: ' . $e->getMessage()]);
    ob_end_flush();
    exit();
}

// Clean any output that might have been generated
ob_clean();

// Set content type to JSON
header('Content-Type: application/json');

// Check if GD extension is loaded
if (!extension_loaded('gd')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'GD extension is not loaded. Certificate generation requires GD extension.']);
    ob_end_flush();
    exit();
}

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    ob_end_flush();
    exit();
}

// Check access
if (!checkPageAccessEnhanced('admin/training/class/view.php')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    ob_end_flush();
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    ob_end_flush();
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    ob_end_flush();
    exit();
}

// Extract required data
$userId = intval($input['user_id'] ?? 0);
$classId = intval($input['class_id'] ?? 0);
$certificateno = trim($input['certificateno'] ?? '');
$issueDate = trim($input['issue_date'] ?? '');
$expireDate = trim($input['expire_date'] ?? '');

// Validation
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    ob_end_flush();
    exit();
}

if ($classId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid class ID']);
    ob_end_flush();
    exit();
}

if (empty($certificateno)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Certificate number is required']);
    ob_end_flush();
    exit();
}

if (empty($issueDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Issue date is required']);
    ob_end_flush();
    exit();
}

try {
    $db = getDBConnection();
    
    // Get user information
    $user = getUserById($userId);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        ob_end_flush();
        exit();
    }
    
    // Get class information (including department & issuance_auth for template selection)
    $stmt = $db->prepare("SELECT c.id, c.name, c.duration,
                                 c.department, c.issuance_auth,
                                 MIN(cs.start_date) as start_date,
                                 MAX(cs.end_date) as end_date
                          FROM classes c
                          LEFT JOIN class_schedules cs ON c.id = cs.class_id
                          WHERE c.id = ?
                          GROUP BY c.id");
    $stmt->execute([$classId]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Class not found']);
        ob_end_flush();
        exit();
    }
    
    // Determine department & issuance_auth for this class (used for DB and template)
    $department = strtolower($class['department'] ?? 'training');
    $issuanceAuth = strtolower($class['issuance_auth'] ?? 'completion');

    // Normalize values
    if (!in_array($department, ['training', 'operation'], true)) {
        $department = 'training';
    }
    if (!in_array($issuanceAuth, ['attendance', 'completion'], true)) {
        $issuanceAuth = 'completion';
    }
    
    // Prepare certificate data
    $nationalid = $user['national_id'] ?? '';
    $name = strtoupper(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    $email = $user['email'] ?? '';
    $mobile = $user['mobile'] ?? ($user['phone'] ?? '');
    
    // Get birthday from date_of_birth field
    $birthday = null;
    if (!empty($user['date_of_birth'])) {
        // Convert to Y-m-d format
        $birthday = date('Y-m-d', strtotime($user['date_of_birth']));
    }
    
    $coursename = $class['name'] ?? '';
    $courseduration = $class['duration'] ?? '';
    $start_date = $class['start_date'] ?? '';
    if ($start_date) {
        $start_date = date('Y-m-d', strtotime($start_date));
    }
    $end_date = $class['end_date'] ?? '';
    if ($end_date) {
        $end_date = date('Y-m-d', strtotime($end_date));
    }
    
    // Use provided certificate number or generate one
    if (empty($certificateno)) {
        // Generate certificate number (use timestamp + user ID)
        $certificateno = date('Ymd') . sprintf('%04d', $userId);
    }
    
    // Add RMAW- prefix if not already present
    $fullCertificateno = 'RMAW-' . $certificateno;
    
    // Check if certificate number already exists
    $stmt = $db->prepare("SELECT id FROM certificates WHERE certificateno = ?");
    $stmt->execute([$fullCertificateno]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Certificate number already exists: ' . $fullCertificateno]);
        ob_end_flush();
        exit();
    }
    
    // Validate required fields
    if (empty($nationalid)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User National ID is required']);
        ob_end_flush();
        exit();
    }
    
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User name is required']);
        ob_end_flush();
        exit();
    }
    
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User email is required']);
        ob_end_flush();
        exit();
    }
    
    if (empty($coursename)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Course name is required']);
        ob_end_flush();
        exit();
    }
    
    // Check if birthday is required (check certificates table structure)
    // For now, we'll allow null but warn if missing
    if (empty($birthday)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'User date of birth is required. Please update the user profile with date of birth information before issuing certificate.'
        ]);
        ob_end_flush();
        exit();
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
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
            $birthday, // Already validated to be not empty above
            $coursename,
            $courseduration ?: null,
            $start_date ?: null,
            $end_date ?: null,
            $issueDate,
            $expireDate ?: null,
            null,          // certificate_type (reserved for future use)
            $issuanceAuth  // issuance_auth from class (NOT NULL)
        ]);
        
        $certificateId = $db->lastInsertId();
        
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
        curl_close($ch);
        
        if ($qrBinary === false || $httpCode !== 200) {
            throw new Exception('Failed to generate QR code: ' . ($curlErr ?: 'HTTP ' . $httpCode));
        }
        
        // Ensure directories exist
        $baseDir = dirname(dirname(dirname(dirname(__DIR__)))); // Go up to root
        $qrDirectory = $baseDir . '/admin/users/certificate/qr';
        $cerDirectory = $baseDir . '/admin/users/certificate/cer';
        
        // Normalize paths
        $qrDirectory = str_replace(['\\', '//'], '/', $qrDirectory);
        $cerDirectory = str_replace(['\\', '//'], '/', $cerDirectory);
        
        // Create directories if they don't exist
        if (!is_dir($qrDirectory)) {
            if (!@mkdir($qrDirectory, 0775, true)) {
                throw new Exception('Failed to create QR directory');
            }
        }
        
        if (!is_dir($cerDirectory)) {
            if (!@mkdir($cerDirectory, 0775, true)) {
                throw new Exception('Failed to create certificate directory');
            }
        }
        
        // Detect image type
        $imageType = 'jpeg';
        if (substr($qrBinary, 0, 2) === "\x89\x50") {
            $imageType = 'png';
        } elseif (substr($qrBinary, 0, 2) === "\xFF\xD8") {
            $imageType = 'jpeg';
        } elseif (substr($qrBinary, 0, 4) === "GIF8") {
            $imageType = 'gif';
        }
        
        // Save QR code
        $qrFilePath = $qrDirectory . '/' . $fullCertificateno . '.' . $imageType;
        if (file_put_contents($qrFilePath, $qrBinary) === false) {
            throw new Exception('Failed to save QR code image');
        }
        
        // Determine template based on department & issuance_auth
        // Go up from admin/training/class/api to admin/training/certificate/templates
        $baseDir = dirname(dirname(dirname(dirname(__DIR__)))); // Go to root

        $templateFilename = 'training_completion.jpg';
        if ($department === 'training' && $issuanceAuth === 'attendance') {
            $templateFilename = 'training_attendance.jpg';
        } elseif ($department === 'training' && $issuanceAuth === 'completion') {
            $templateFilename = 'training_completion.jpg';
        } elseif ($department === 'operation' && $issuanceAuth === 'completion') {
            $templateFilename = 'operation_completion.jpg';
        } elseif ($department === 'operation' && $issuanceAuth === 'attendance') {
            $templateFilename = 'operation_attendance.jpg';
        }

        $templateFile = $baseDir . '/admin/training/certificate/templates/' . $templateFilename;
        
        // Normalize path
        $templateFile = str_replace(['\\', '//'], '/', $templateFile);
        
        if (!file_exists($templateFile)) {
            throw new Exception('Certificate template not found: ' . $templateFile);
        }
        
        // Load template image
        $image = imagecreatefromjpeg($templateFile);
        if (!$image) {
            throw new Exception('Failed to load certificate template');
        }
        
        // Load font
        $fontFile = $baseDir . '/admin/training/certificate/templates/arial.ttf';
        $fontFile = str_replace(['\\', '//'], '/', $fontFile);
        
        if (!file_exists($fontFile)) {
            throw new Exception('Font file not found: ' . $fontFile);
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
        if ($issueDate) {
            imagettftext($image, $fontSize, $angle, 600, 1740, $fontColor, $fontFile, $issueDate);
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
        if ($expireDate) {
            imagettftext($image, 40, $angle, 2210, 1740, $fontColor, $fontFile, $expireDate);
        }
        if ($end_date) {
            imagettftext($image, 40, $angle, 2150, 1640, $fontColor, $fontFile, $end_date);
        }
        if ($start_date) {
            imagettftext($image, 40, $angle, 600, 1640, $fontColor, $fontFile, $start_date);
        }
        
        // Save certificate image
        $cerFilePath = $cerDirectory . '/' . $fullCertificateno . '.jpg';
        
        // Remove existing file if it exists
        if (file_exists($cerFilePath)) {
            @unlink($cerFilePath);
        }
        
        // Save the image
        $saved = @imagejpeg($image, $cerFilePath, 100);
        if (!$saved) {
            imagedestroy($image);
            throw new Exception('Failed to save certificate image');
        }
        
        // Verify file was created
        if (!file_exists($cerFilePath)) {
            imagedestroy($image);
            throw new Exception('Certificate file was not created');
        }
        
        // Load QR code image
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
                $qrImage = @imagecreatefrompng($qrFilePath);
                if (!$qrImage) {
                    $qrImage = @imagecreatefromjpeg($qrFilePath);
                }
                break;
        }
        
        if (!$qrImage) {
            imagedestroy($image);
            throw new Exception('Failed to load QR code image');
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
        if ($imageType === 'png') {
            imagealphablending($resultImage, true);
            imagesavealpha($resultImage, true);
        }
        imagecopy($resultImage, $qrImage, 2900, 1990, 0, 0, $qrWidth, $qrHeight);
        
        // Save final certificate with QR
        $saved = @imagejpeg($resultImage, $cerFilePath, 100);
        if (!$saved) {
            imagedestroy($image);
            imagedestroy($qrImage);
            imagedestroy($resultImage);
            throw new Exception('Failed to save final certificate image');
        }
        
        // Verify file was created/updated
        if (!file_exists($cerFilePath) || filesize($cerFilePath) == 0) {
            imagedestroy($image);
            imagedestroy($qrImage);
            imagedestroy($resultImage);
            throw new Exception('Final certificate file was not created or is empty');
        }
        
        // Clean up
        imagedestroy($image);
        imagedestroy($qrImage);
        imagedestroy($resultImage);
        
        // Commit transaction
        $db->commit();
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Certificate issued successfully',
            'certificate_id' => $certificateId,
            'certificate_no' => $fullCertificateno,
            'certificate_url' => base_url() . 'admin/users/certificate/cer/' . $fullCertificateno . '.jpg'
        ]);
        ob_end_flush();
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error issuing certificate via API: " . $e->getMessage());
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Failed to issue certificate: ' . $e->getMessage()
        ]);
        ob_end_flush();
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Database error in issue_certificate API: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    ob_end_flush();
    exit();
} catch (Exception $e) {
    error_log("Error in issue_certificate API: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
    ob_end_flush();
    exit();
}
