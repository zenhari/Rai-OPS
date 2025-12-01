<?php
require_once '../../config.php';

// Load mPDF library
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    // Check if mPDF class is available (version 6.1 uses 'mPDF', version 8.x uses '\Mpdf\Mpdf')
    if (!class_exists('mPDF') && !class_exists('\Mpdf\Mpdf')) {
        die('Error: mPDF library is not installed. Please run "composer install" or "composer require mpdf/mpdf" to install mPDF.');
    }
} else {
    die('Error: Composer autoloader not found. Please run "composer install" to install dependencies.');
}

// Access control
checkPageAccessWithRedirect('admin/efb/index.php');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDBConnection();

// Ensure upload directory exists
// Use absolute path based on __DIR__ which is admin/efb
$uploadBaseDir = __DIR__;
if (!is_dir($uploadBaseDir)) {
    // Create the efb directory if it doesn't exist
    if (!@mkdir($uploadBaseDir, 0775, true)) {
        // Try with 0755 if 0775 fails
        if (!@mkdir($uploadBaseDir, 0755, true)) {
            // Log error but continue - will check again when needed
            error_log("Failed to create base directory: " . $uploadBaseDir);
        }
    }
}
// Ensure base directory is writable
if (is_dir($uploadBaseDir) && !is_writable($uploadBaseDir)) {
    @chmod($uploadBaseDir, 0775);
    // If still not writable, try 0755
    if (!is_writable($uploadBaseDir)) {
        @chmod($uploadBaseDir, 0755);
    }
}

// Get filter parameters
$filterDate = $_GET['filter_date'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');

// Check if we're viewing EFB for a specific flight
$viewEFB = isset($_GET['view_efb']) && $_GET['view_efb'] == '1';
$selectedFlightId = !empty($_GET['flight_id']) ? (int)$_GET['flight_id'] : null;
$selectedDate = $_GET['flight_date'] ?? '';

// Handle file upload and deletion
$message = '';
$error = '';

// Handle OFP data deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_ofp_data') {
    $flightId = !empty($_POST['flight_id']) ? (int)$_POST['flight_id'] : null;
    $fltDate = trim($_POST['flt_date'] ?? '');
    $ofpIndex = isset($_POST['ofp_index']) ? (int)$_POST['ofp_index'] : -1;
    
    if ($flightId && $fltDate && $ofpIndex >= 0) {
        $checkStmt = $db->prepare("SELECT id, ofp_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
        $checkStmt->execute([$flightId, $fltDate]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing && !empty($existing['ofp_data'])) {
            $ofpData = json_decode($existing['ofp_data'], true) ?: [];
            if (isset($ofpData[$ofpIndex])) {
                unset($ofpData[$ofpIndex]);
                $ofpData = array_values($ofpData); // Re-index array
                
                if (empty($ofpData)) {
                    $updateStmt = $db->prepare("UPDATE efb_records SET ofp_data = NULL WHERE id = ?");
                    $updateStmt->execute([$existing['id']]);
                } else {
                    $updateStmt = $db->prepare("UPDATE efb_records SET ofp_data = ? WHERE id = ?");
                    $updateStmt->execute([json_encode($ofpData, JSON_UNESCAPED_SLASHES), $existing['id']]);
                }
                
                $message = 'OFP data deleted successfully.';
                header('Location: ?view_efb=1&flight_id=' . $flightId . '&flight_date=' . urlencode($fltDate) . '&msg=' . urlencode($message));
                exit;
            }
        }
    }
}

// Handle GD/CL file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_gd_cl_file') {
    $flightId = !empty($_POST['flight_id']) ? (int)$_POST['flight_id'] : null;
    $fltDate = trim($_POST['flt_date'] ?? '');
    $filePath = trim($_POST['file_path'] ?? '');
    
    if ($flightId && $fltDate && $filePath) {
        $checkStmt = $db->prepare("SELECT id, gd_cl_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
        $checkStmt->execute([$flightId, $fltDate]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing && !empty($existing['gd_cl_data'])) {
            $files = json_decode($existing['gd_cl_data'], true) ?: [];
            $updatedFiles = [];
            $fileDeleted = false;
            
            foreach ($files as $file) {
                if (($file['path'] ?? '') !== $filePath) {
                    $updatedFiles[] = $file;
                } else {
                    $fileDeleted = true;
                    $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filePath), DIRECTORY_SEPARATOR);
                    if (file_exists($absolutePath)) {
                        @unlink($absolutePath);
                    }
                }
            }
            
            if ($fileDeleted) {
                if (empty($updatedFiles)) {
                    $updateStmt = $db->prepare("UPDATE efb_records SET gd_cl_data = NULL WHERE id = ?");
                    $updateStmt->execute([$existing['id']]);
                } else {
                    $updateStmt = $db->prepare("UPDATE efb_records SET gd_cl_data = ? WHERE id = ?");
                    $updateStmt->execute([json_encode($updatedFiles, JSON_UNESCAPED_SLASHES), $existing['id']]);
                }
                $message = 'File deleted successfully.';
                header('Location: ?view_efb=1&flight_id=' . $flightId . '&flight_date=' . urlencode($fltDate) . '&msg=' . urlencode($message));
                exit;
            }
        }
    }
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_ats_rpl_file') {
    $flightId = !empty($_POST['flight_id']) ? (int)$_POST['flight_id'] : null;
    $fltDate = trim($_POST['flt_date'] ?? '');
    $filePath = trim($_POST['file_path'] ?? '');
    
    if ($flightId && $fltDate && $filePath) {
        // Get EFB record
        $checkStmt = $db->prepare("SELECT id, ats_rpl_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
        $checkStmt->execute([$flightId, $fltDate]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing && !empty($existing['ats_rpl_data'])) {
            $files = json_decode($existing['ats_rpl_data'], true) ?: [];
            $updatedFiles = [];
            $fileDeleted = false;
            
            foreach ($files as $file) {
                if (($file['path'] ?? '') !== $filePath) {
                    $updatedFiles[] = $file;
                } else {
                    $fileDeleted = true;
                    // Delete physical file
                    $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filePath), DIRECTORY_SEPARATOR);
                    if (file_exists($absolutePath)) {
                        @unlink($absolutePath);
                    }
                }
            }
            
            if ($fileDeleted) {
                if (empty($updatedFiles)) {
                    // If no files left, set to NULL
                    $updateStmt = $db->prepare("UPDATE efb_records SET ats_rpl_data = NULL WHERE id = ?");
                    $updateStmt->execute([$existing['id']]);
                } else {
                    // Update with remaining files
                    $updateStmt = $db->prepare("UPDATE efb_records SET ats_rpl_data = ? WHERE id = ?");
                    $updateStmt->execute([json_encode($updatedFiles, JSON_UNESCAPED_SLASHES), $existing['id']]);
                }
                $message = 'File deleted successfully.';
                // Redirect to prevent resubmission
                header('Location: ?view_efb=1&flight_id=' . $flightId . '&flight_date=' . urlencode($fltDate) . '&msg=' . urlencode($message));
                exit;
            } else {
                $error = 'File not found.';
            }
        } else {
            $error = 'EFB record not found.';
        }
    } else {
        $error = 'Invalid request.';
    }
}

// Handle file upload for ATS / RPL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_ats_rpl') {
    $flightId = !empty($_POST['flight_id']) ? (int)$_POST['flight_id'] : null;
    $taskName = trim($_POST['task_name'] ?? '');
    $fltDate = trim($_POST['flt_date'] ?? '');
    
    if ($flightId && $taskName && $fltDate && isset($_FILES['ats_rpl_file']) && $_FILES['ats_rpl_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['ats_rpl_file'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate PDF
        if ($fileExt !== 'pdf') {
            $error = 'Only PDF files are allowed.';
        } else {
            // Create directory structure: admin/efb/YYYY-MM-DD/FlightID/
            $dateFolder = date('Y-m-d', strtotime($fltDate));
            $flightFolder = (string)$flightId;
            $uploadDir = $uploadBaseDir . DIRECTORY_SEPARATOR . $dateFolder . DIRECTORY_SEPARATOR . $flightFolder;
            
            // Ensure base directory exists first
            if (!is_dir($uploadBaseDir)) {
                if (!@mkdir($uploadBaseDir, 0775, true)) {
                    if (!@mkdir($uploadBaseDir, 0755, true)) {
                        $error = 'Failed to create base directory: ' . $uploadBaseDir;
                    }
                }
            }
            
            // Ensure base directory is writable
            if (is_dir($uploadBaseDir) && !is_writable($uploadBaseDir)) {
                @chmod($uploadBaseDir, 0775);
                if (!is_writable($uploadBaseDir)) {
                    @chmod($uploadBaseDir, 0755);
                }
            }
            
            // Now create the full directory structure
            if (empty($error) && !is_dir($uploadDir)) {
                if (!@mkdir($uploadDir, 0775, true)) {
                    if (!@mkdir($uploadDir, 0755, true)) {
                        $error = 'Failed to create upload directory: ' . $uploadDir;
                    }
                }
            }
            
            // Check if directory is writable
            if (empty($error) && is_dir($uploadDir) && !is_writable($uploadDir)) {
                @chmod($uploadDir, 0775);
                if (!is_writable($uploadDir)) {
                    @chmod($uploadDir, 0755);
                }
            }
            
            // Generate unique filename
            $safeFileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($file['name'], PATHINFO_FILENAME));
            $fileName = $safeFileName . '_' . time() . '.pdf';
            $filePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
            
            // Relative path for database: admin/efb/YYYY-MM-DD/FlightID/filename.pdf
            $relativePath = 'admin/efb/' . $dateFolder . '/' . $flightFolder . '/' . $fileName;
            
            if (@move_uploaded_file($file['tmp_name'], $filePath)) {
                // Find or create EFB record
                $checkStmt = $db->prepare("SELECT id, ats_rpl_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
                $checkStmt->execute([$flightId, $fltDate]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                $fileData = [
                    'path' => $relativePath,
                    'original_name' => $file['name'],
                    'stored_name' => $fileName,
                    'upload_time' => date('Y-m-d H:i:s'),
                    'size' => $file['size']
                ];
                
                if ($existing) {
                    // Update existing record
                    $existingData = json_decode($existing['ats_rpl_data'] ?? '[]', true) ?: [];
                    $existingData[] = $fileData;
                    $updateStmt = $db->prepare("UPDATE efb_records SET ats_rpl_data = ? WHERE id = ?");
                    $updateStmt->execute([json_encode($existingData, JSON_UNESCAPED_SLASHES), $existing['id']]);
                } else {
                    // Create new record
                    $insertStmt = $db->prepare("INSERT INTO efb_records (flight_id, task_name, flt_date, ats_rpl_data, created_by) VALUES (?, ?, ?, ?, ?)");
                    $currentUser = getCurrentUser();
                    $insertStmt->execute([
                        $flightId,
                        $taskName,
                        $fltDate,
                        json_encode([$fileData], JSON_UNESCAPED_SLASHES),
                        $currentUser['id'] ?? null
                    ]);
                }
                
                $message = 'ATS / RPL file uploaded successfully.';
                // Redirect to prevent resubmission
                header('Location: ?view_efb=1&flight_id=' . $flightId . '&flight_date=' . urlencode($fltDate) . '&msg=' . urlencode($message));
                exit;
            } else {
                $error = 'Failed to upload file.';
            }
        }
    } else {
        $error = 'Invalid file or missing information.';
    }
}

// Function to fetch METAR data (from metar_tafor.php)
function fetchWeatherData($stationCode) {
    $url = "https://aviationweather.gov/api/data/metar?ids={$stationCode}&format=json";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, '1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    return $data;
}

// Function to extract ICAO codes from OFP raw_data (ALTN and RTS)
function extractICAOCodesFromOFP($rawData) {
    $icaoCodes = [];
    
    if (empty($rawData)) {
        return $icaoCodes;
    }
    
    // Try to parse from JSON first
    $jsonData = @json_decode($rawData, true);
    if (is_array($jsonData)) {
        // Check parsed_data for ALTN and RTS fields
        if (isset($jsonData['parsed_data']['binfo']['ALTN'])) {
            $altn = $jsonData['parsed_data']['binfo']['ALTN'];
            if (is_string($altn)) {
                // ALTN format: "OIII, OINZ" (comma-separated)
                $codes = preg_split('/[\s,]+/', $altn);
                foreach ($codes as $code) {
                    $code = strtoupper(trim($code));
                    if (preg_match('/^[A-Z]{4}$/', $code) && !in_array($code, $icaoCodes)) {
                        $icaoCodes[] = $code;
                    }
                }
            }
        }
        
        if (isset($jsonData['parsed_data']['binfo']['RTS'])) {
            $rts = $jsonData['parsed_data']['binfo']['RTS'];
            if (is_string($rts)) {
                // RTS format: "OIII - OIGG" (dash-separated)
                $codes = preg_split('/[\s\-]+/', $rts);
                foreach ($codes as $code) {
                    $code = strtoupper(trim($code));
                    if (preg_match('/^[A-Z]{4}$/', $code) && !in_array($code, $icaoCodes)) {
                        $icaoCodes[] = $code;
                    }
                }
            }
        }
        
        // Also check raw_data directly if it's a string
        if (isset($jsonData['raw_data']) && is_string($jsonData['raw_data'])) {
            $rawData = $jsonData['raw_data'];
        }
    }
    
    // Parse pipe-delimited format: binfo:|KEY=VALUE;KEY2=VALUE2||
    // Extract binfo section
    if (preg_match('/binfo:\|([^|]+)\|/', $rawData, $binfoMatch)) {
        $binfoContent = $binfoMatch[1];
        
        // Parse semicolon-separated key-value pairs
        $pairs = explode(';', $binfoContent);
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (empty($pair)) continue;
            
            // Check for ALTN=OIII, OINZ format
            if (preg_match('/^ALTN\s*=\s*(.+)$/i', $pair, $altnMatch)) {
                $altnValue = trim($altnMatch[1]);
                // Split by comma and spaces
                $codes = preg_split('/[\s,]+/', $altnValue);
                foreach ($codes as $code) {
                    $code = strtoupper(trim($code));
                    if (preg_match('/^[A-Z]{4}$/', $code) && !in_array($code, $icaoCodes)) {
                        $icaoCodes[] = $code;
                    }
                }
            }
            
            // Check for RTS=OIII - OIGG format
            if (preg_match('/^RTS\s*=\s*(.+)$/i', $pair, $rtsMatch)) {
                $rtsValue = trim($rtsMatch[1]);
                // Split by dash and spaces
                $codes = preg_split('/[\s\-–]+/', $rtsValue);
                foreach ($codes as $code) {
                    $code = strtoupper(trim($code));
                    if (preg_match('/^[A-Z]{4}$/', $code) && !in_array($code, $icaoCodes)) {
                        $icaoCodes[] = $code;
                    }
                }
            }
        }
    }
    
    // Also try direct patterns for ALTN= and RTS= in the raw data (in case binfo section parsing fails)
    if (preg_match('/ALTN\s*=\s*([A-Z]{4}(?:\s*,\s*[A-Z]{4})*)/i', $rawData, $altnDirectMatch)) {
        $altnValue = trim($altnDirectMatch[1]);
        $codes = preg_split('/[\s,]+/', $altnValue);
        foreach ($codes as $code) {
            $code = strtoupper(trim($code));
            if (preg_match('/^[A-Z]{4}$/', $code) && !in_array($code, $icaoCodes)) {
                $icaoCodes[] = $code;
            }
        }
    }
    
    if (preg_match('/RTS\s*=\s*([A-Z]{4})\s*[-–]\s*([A-Z]{4})/i', $rawData, $rtsDirectMatch)) {
        $code1 = strtoupper(trim($rtsDirectMatch[1]));
        $code2 = strtoupper(trim($rtsDirectMatch[2]));
        if (preg_match('/^[A-Z]{4}$/', $code1) && !in_array($code1, $icaoCodes)) {
            $icaoCodes[] = $code1;
        }
        if (preg_match('/^[A-Z]{4}$/', $code2) && !in_array($code2, $icaoCodes)) {
            $icaoCodes[] = $code2;
        }
    }
    
    // Try to parse ALTN from raw_data string
    // ALTN can appear in different formats in OFP raw data
    // Patterns: "Key==ALTN", "ALTN/XXXX", "ALTN XXXX", "ALTN:XXXX", "ALTN: OIII, OINZ", etc.
    
    // First, try to find "Key==ALTN" pattern
    if (preg_match('/Key\s*==\s*ALTN[:\s]+([A-Z]{4}(?:\s*,\s*[A-Z]{4})*)/i', $rawData, $altnMatch)) {
        $altnValue = $altnMatch[1];
        $codes = preg_split('/[\s,]+/', $altnValue);
        foreach ($codes as $code) {
            $code = strtoupper(trim($code));
            if (preg_match('/^[A-Z]{4}$/', $code) && !in_array($code, $icaoCodes)) {
                $icaoCodes[] = $code;
            }
        }
    }
    
    // Try other ALTN patterns
    $altnPatterns = [
        '/ALTN[\/\s:]+([A-Z]{4}(?:\s*,\s*[A-Z]{4})*)/i',
        '/ALTN[\/\s:]+([A-Z]{3,4}(?:\s*,\s*[A-Z]{3,4})*)/i',
        '/ALTERNATE[\/\s:]+([A-Z]{4}(?:\s*,\s*[A-Z]{4})*)/i',
        '/ALTERNATE[\/\s:]+([A-Z]{3,4}(?:\s*,\s*[A-Z]{3,4})*)/i',
    ];
    
    foreach ($altnPatterns as $pattern) {
        if (preg_match_all($pattern, $rawData, $matches)) {
            foreach ($matches[1] as $match) {
                $codes = preg_split('/[\s,]+/', $match);
                foreach ($codes as $code) {
                    $code = strtoupper(trim($code));
                    if (preg_match('/^[A-Z]{4}$/', $code) && !in_array($code, $icaoCodes)) {
                        $icaoCodes[] = $code;
                    }
                }
            }
        }
    }
    
    // Try to parse RTS from raw_data string
    // RTS format: "Key==RTS", "RTS OIII - OIGG" or "RTS: OIII-OIGG"
    
    // First, try to find "Key==RTS" pattern
    if (preg_match('/Key\s*==\s*RTS[:\s]+([A-Z]{4})\s*[-–]\s*([A-Z]{4})/i', $rawData, $rtsMatch)) {
        $code1 = strtoupper(trim($rtsMatch[1]));
        $code2 = strtoupper(trim($rtsMatch[2]));
        if (preg_match('/^[A-Z]{4}$/', $code1) && !in_array($code1, $icaoCodes)) {
            $icaoCodes[] = $code1;
        }
        if (preg_match('/^[A-Z]{4}$/', $code2) && !in_array($code2, $icaoCodes)) {
            $icaoCodes[] = $code2;
        }
    }
    
    // Try other RTS patterns
    $rtsPatterns = [
        '/RTS[\/\s:]+([A-Z]{4})\s*[-–]\s*([A-Z]{4})/i',
        '/ROUTE[\/\s:]+([A-Z]{4})\s*[-–]\s*([A-Z]{4})/i',
    ];
    
    foreach ($rtsPatterns as $pattern) {
        if (preg_match_all($pattern, $rawData, $matches)) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $code1 = strtoupper(trim($matches[1][$i]));
                $code2 = strtoupper(trim($matches[2][$i]));
                if (preg_match('/^[A-Z]{4}$/', $code1) && !in_array($code1, $icaoCodes)) {
                    $icaoCodes[] = $code1;
                }
                if (preg_match('/^[A-Z]{4}$/', $code2) && !in_array($code2, $icaoCodes)) {
                    $icaoCodes[] = $code2;
                }
            }
        }
    }
    
    return array_unique($icaoCodes);
}

// Function to extract alternate airports (ALTN) from OFP raw_data
function extractAlternateAirports($rawData, $db) {
    $alternates = [];
    
    if (empty($rawData)) {
        return $alternates;
    }
    
    // Try to parse ALTN from raw_data
    // ALTN can appear in different formats in OFP raw data
    // Common patterns: "ALTN/XXXX", "ALTN XXXX", "ALTN:XXXX", etc.
    $patterns = [
        '/ALTN[\/\s:]+([A-Z]{4})/i',
        '/ALTN[\/\s:]+([A-Z]{3,4})/i',
        '/ALTERNATE[\/\s:]+([A-Z]{4})/i',
        '/ALTERNATE[\/\s:]+([A-Z]{3,4})/i',
    ];
    
    $foundCodes = [];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $rawData, $matches)) {
            foreach ($matches[1] as $code) {
                $code = strtoupper(trim($code));
                if (strlen($code) >= 3 && strlen($code) <= 4 && !in_array($code, $foundCodes)) {
                    $foundCodes[] = $code;
                }
            }
        }
    }
    
    // Also try to extract from parsed_data if available (if raw_data is JSON)
    $jsonData = @json_decode($rawData, true);
    if (is_array($jsonData)) {
        // Check parsed_data for ALTN fields
        if (isset($jsonData['parsed_data']['binfo']['ALTN'])) {
            $altn = $jsonData['parsed_data']['binfo']['ALTN'];
            if (is_string($altn)) {
                $codes = preg_split('/[\s,\-]+/', $altn);
                foreach ($codes as $code) {
                    $code = strtoupper(trim($code));
                    if (strlen($code) >= 3 && strlen($code) <= 4 && !in_array($code, $foundCodes)) {
                        $foundCodes[] = $code;
                    }
                }
            }
        }
        
        // Check for RTA (1st Alternate Route) and RTB (2nd Alternate Route)
        if (isset($jsonData['parsed_data']['binfo']['RTA'])) {
            $rta = $jsonData['parsed_data']['binfo']['RTA'];
            if (is_string($rta)) {
                $codes = preg_split('/[\s,\-]+/', $rta);
                foreach ($codes as $code) {
                    $code = strtoupper(trim($code));
                    if (strlen($code) >= 3 && strlen($code) <= 4 && !in_array($code, $foundCodes)) {
                        $foundCodes[] = $code;
                    }
                }
            }
        }
        
        if (isset($jsonData['parsed_data']['binfo']['RTB'])) {
            $rtb = $jsonData['parsed_data']['binfo']['RTB'];
            if (is_string($rtb)) {
                $codes = preg_split('/[\s,\-]+/', $rtb);
                foreach ($codes as $code) {
                    $code = strtoupper(trim($code));
                    if (strlen($code) >= 3 && strlen($code) <= 4 && !in_array($code, $foundCodes)) {
                        $foundCodes[] = $code;
                    }
                }
            }
        }
    }
    
    // Convert codes to ICAO and get IATA if available
    foreach ($foundCodes as $code) {
        $icaoCode = $code;
        $iataCode = $code;
        
        // Check if code is already ICAO (4 letters) or IATA (3 letters)
        if (strlen($code) === 4) {
            // Likely ICAO, try to find IATA
            $stationStmt = $db->prepare("SELECT iata_code FROM stations WHERE icao_code = ? LIMIT 1");
            $stationStmt->execute([$code]);
            $station = $stationStmt->fetch(PDO::FETCH_ASSOC);
            if ($station && !empty($station['iata_code'])) {
                $iataCode = $station['iata_code'];
            }
        } elseif (strlen($code) === 3) {
            // Likely IATA, try to find ICAO
            $stationStmt = $db->prepare("SELECT icao_code FROM stations WHERE iata_code = ? LIMIT 1");
            $stationStmt->execute([$code]);
            $station = $stationStmt->fetch(PDO::FETCH_ASSOC);
            if ($station && !empty($station['icao_code'])) {
                $icaoCode = $station['icao_code'];
            } else {
                // If not found, assume it's already ICAO
                $icaoCode = $code;
            }
        }
        
        $alternates[] = [
            'code' => $code,
            'icao' => $icaoCode,
            'iata' => $iataCode
        ];
    }
    
    return $alternates;
}

// Function to fetch TAF (Terminal Aerodrome Forecast) data
function fetchTAFData($stationCode) {
    // Try JSON format first
    $url = "https://aviationweather.gov/api/data/taf?ids={$stationCode}&format=json";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, '1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // If JSON format works, return it
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data !== null && !empty($data)) {
            return $data;
        }
    }
    
    // Fallback to raw format
    $url = "https://aviationweather.gov/api/data/taf?ids={$stationCode}&format=raw";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, '1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        // Return raw TAF data in a structured format
        $rawTAF = trim($response);
        if (!empty($rawTAF)) {
            return [
                [
                    'icaoId' => $stationCode,
                    'rawTAF' => $rawTAF,
                    'format' => 'raw'
                ]
            ];
        }
    }
    
    return null;
}

// Handle Actual WX (METAR) generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_actual_wx') {
    $flightId = !empty($_POST['flight_id']) ? (int)$_POST['flight_id'] : null;
    $fltDate = trim($_POST['flt_date'] ?? '');
    
    if ($flightId && $fltDate) {
        // Get flight details
        $flightStmt = $db->prepare("SELECT FlightID, TaskName, Route, FltDate FROM flights WHERE FlightID = ? AND DATE(FltDate) = ? LIMIT 1");
        $flightStmt->execute([$flightId, $fltDate]);
        $flight = $flightStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($flight && !empty($flight['Route'])) {
            // Extract origin and destination airports from Route (e.g., RAS-MHD-THR → RAS, THR)
            $route = $flight['Route'];
            $airportCodes = array_filter(array_map('trim', explode('-', $route)));
            
            if (!empty($airportCodes) && count($airportCodes) >= 2) {
                // Get origin (first airport) and destination (last airport) - these are IATA codes
                $originIataCode = reset($airportCodes);
                $destinationIataCode = end($airportCodes);
                
                // Convert IATA codes to ICAO codes from stations table
                $originIcaoCode = null;
                $destinationIcaoCode = null;
                
                // Get origin ICAO code
                $stationStmt = $db->prepare("SELECT icao_code FROM stations WHERE iata_code = ? LIMIT 1");
                $stationStmt->execute([$originIataCode]);
                $station = $stationStmt->fetch(PDO::FETCH_ASSOC);
                if ($station && !empty($station['icao_code'])) {
                    $originIcaoCode = $station['icao_code'];
                } else {
                    $originIcaoCode = $originIataCode; // Fallback
                }
                
                // Get destination ICAO code
                if ($originIataCode !== $destinationIataCode) {
                    $stationStmt->execute([$destinationIataCode]);
                    $station = $stationStmt->fetch(PDO::FETCH_ASSOC);
                    if ($station && !empty($station['icao_code'])) {
                        $destinationIcaoCode = $station['icao_code'];
                    } else {
                        $destinationIcaoCode = $destinationIataCode; // Fallback
                    }
                } else {
                    $destinationIcaoCode = $originIcaoCode; // Same airport
                }
                
                // Fetch METAR data for both origin and destination airports using ICAO codes
                $allWeatherData = [];
                $airportsData = [];
                
                // Fetch origin METAR
                if ($originIcaoCode) {
                    $weatherData = fetchWeatherData($originIcaoCode);
                    if ($weatherData && !empty($weatherData)) {
                        $allWeatherData = array_merge($allWeatherData, $weatherData);
                        $airportsData[] = [
                            'iata' => $originIataCode,
                            'icao' => $originIcaoCode,
                            'type' => 'origin'
                        ];
                    }
                }
                
                // Fetch destination METAR (if different from origin)
                if ($destinationIcaoCode && $destinationIcaoCode !== $originIcaoCode) {
                    $weatherData = fetchWeatherData($destinationIcaoCode);
                    if ($weatherData && !empty($weatherData)) {
                        $allWeatherData = array_merge($allWeatherData, $weatherData);
                        $airportsData[] = [
                            'iata' => $destinationIataCode,
                            'icao' => $destinationIcaoCode,
                            'type' => 'destination'
                        ];
                    }
                }
                
                // Check if OFP data exists and extract alternate airports
                $alternateAirports = [];
                $checkOFPStmt = $db->prepare("SELECT ofp_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
                $checkOFPStmt->execute([$flightId, $fltDate]);
                $ofpRecord = $checkOFPStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ofpRecord && !empty($ofpRecord['ofp_data'])) {
                    $ofpDataArray = json_decode($ofpRecord['ofp_data'], true);
                    if (is_array($ofpDataArray) && !empty($ofpDataArray)) {
                        // Get the most recent OFP data
                        $latestOFP = end($ofpDataArray);
                        if (isset($latestOFP['raw_data']) && !empty($latestOFP['raw_data'])) {
                            $alternateAirports = extractAlternateAirports($latestOFP['raw_data'], $db);
                            
                            // Fetch METAR for alternate airports
                            foreach ($alternateAirports as $alt) {
                                if (!empty($alt['icao'])) {
                                    $altIcao = $alt['icao'];
                                    // Skip if already fetched (origin/destination)
                                    $alreadyFetched = false;
                                    foreach ($airportsData as $apt) {
                                        if ($apt['icao'] === $altIcao) {
                                            $alreadyFetched = true;
                                            break;
                                        }
                                    }
                                    
                                    if (!$alreadyFetched) {
                                        $weatherData = fetchWeatherData($altIcao);
                                        if ($weatherData && !empty($weatherData)) {
                                            $allWeatherData = array_merge($allWeatherData, $weatherData);
                                            $airportsData[] = [
                                                'iata' => $alt['iata'] ?? $alt['code'],
                                                'icao' => $altIcao,
                                                'type' => 'alternate'
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (!empty($allWeatherData)) {
                    // Generate HTML for METAR data
                    // Pass variables to the included file
                    $route = $flight['Route'];
                    $fltDate = $flight['FltDate'];
                    ob_start();
                    include __DIR__ . '/generate_metar_html.php';
                    $metarHTML = ob_get_clean();
                    
                    // Create directory structure
                    $dateFolder = date('Y-m-d', strtotime($fltDate));
                    $flightFolder = (string)$flightId;
                    $uploadDir = $uploadBaseDir . DIRECTORY_SEPARATOR . $dateFolder . DIRECTORY_SEPARATOR . $flightFolder;
                    
                    // Ensure base directory exists first
                    if (!is_dir($uploadBaseDir)) {
                        if (!@mkdir($uploadBaseDir, 0775, true)) {
                            if (!@mkdir($uploadBaseDir, 0755, true)) {
                                $error = 'Failed to create base directory: ' . $uploadBaseDir;
                            }
                        }
                    }
                    
                    // Ensure base directory is writable
                    if (is_dir($uploadBaseDir) && !is_writable($uploadBaseDir)) {
                        @chmod($uploadBaseDir, 0775);
                        if (!is_writable($uploadBaseDir)) {
                            @chmod($uploadBaseDir, 0755);
                        }
                    }
                    
                    // Now create the full directory structure
                    if (empty($error) && !is_dir($uploadDir)) {
                        // Try to create with 0775 first
                        if (!@mkdir($uploadDir, 0775, true)) {
                            // Try with 0755 if 0775 fails
                            if (!@mkdir($uploadDir, 0755, true)) {
                                $error = 'Failed to create upload directory: ' . $uploadDir . '. Please check directory permissions.';
                            }
                        }
                    }
                    
                    // Check if directory was created successfully and is writable
                    if (empty($error) && (!is_dir($uploadDir) || !is_writable($uploadDir))) {
                        if (is_dir($uploadDir)) {
                            // Try to change permissions
                            @chmod($uploadDir, 0775);
                            if (!is_writable($uploadDir)) {
                                @chmod($uploadDir, 0755);
                            }
                            // Check again
                            if (!is_writable($uploadDir)) {
                                $error = 'Upload directory exists but is not writable: ' . $uploadDir . '. Current permissions may be too restrictive.';
                            }
                        } else {
                            $error = 'Upload directory does not exist: ' . $uploadDir . '. Please check if parent directories exist and are writable.';
                        }
                    }
                    
                    // Generate PDF file name
                    $pdfFileName = 'metar_' . $flightId . '_' . time() . '.pdf';
                    $pdfFilePath = $uploadDir . DIRECTORY_SEPARATOR . $pdfFileName;
                    $relativePath = 'admin/efb/' . $dateFolder . '/' . $flightFolder . '/' . $pdfFileName;
                    
                    // Generate PDF using mPDF
                    $pdfGenerated = false;
                    if (empty($error)) {
                        try {
                            // Create mPDF instance (version 6.1 uses 'mPDF', version 8.x uses '\Mpdf\Mpdf')
                            $mpdfClass = class_exists('mPDF') ? 'mPDF' : '\Mpdf\Mpdf';
                            $mpdf = new $mpdfClass([
                                'mode' => 'utf-8',
                                'format' => 'A4',
                                'margin_left' => 5,
                                'margin_right' => 5,
                                'margin_top' => 5,
                                'margin_bottom' => 5,
                                'margin_header' => 0,
                                'margin_footer' => 0,
                                'tempDir' => sys_get_temp_dir()
                            ]);
                            
                            // Write HTML content
                            $mpdf->WriteHTML($metarHTML);
                            
                            // Output PDF to file
                            $mpdf->Output($pdfFilePath, 'F');
                            
                            if (file_exists($pdfFilePath) && filesize($pdfFilePath) > 0) {
                                $pdfGenerated = true;
                            } else {
                                $error = 'PDF file was not created successfully.';
                            }
                        } catch (\Exception $e) {
                            $error = 'PDF generation failed: ' . $e->getMessage();
                        }
                    }
                    
                    // Only proceed if no errors occurred and PDF was generated
                    if (empty($error) && $pdfGenerated && isset($pdfFilePath) && file_exists($pdfFilePath)) {
                        // Save to database - only if PDF was generated successfully
                        $fileData = [
                            'path' => $relativePath,
                            'original_name' => 'METAR_' . $originIataCode . ($originIataCode !== $destinationIataCode ? '_' . $destinationIataCode : '') . '_' . date('Y-m-d', strtotime($fltDate)) . '.pdf',
                            'stored_name' => $pdfFileName,
                            'upload_time' => date('Y-m-d H:i:s'),
                            'size' => filesize($pdfFilePath),
                            'type' => 'pdf',
                            'route' => $route,
                            'origin_iata' => $originIataCode,
                            'origin_icao' => $originIcaoCode,
                            'destination_iata' => $destinationIataCode,
                            'destination_icao' => $destinationIcaoCode,
                            'airports' => $airportsData
                        ];
                        
                        // Find or create EFB record
                        $checkStmt = $db->prepare("SELECT id, actual_wx_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
                        $checkStmt->execute([$flightId, $fltDate]);
                        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $currentUser = getCurrentUser();
                        if ($existing) {
                            $existingData = json_decode($existing['actual_wx_data'] ?? '[]', true) ?: [];
                            $existingData[] = $fileData;
                            $updateStmt = $db->prepare("UPDATE efb_records SET actual_wx_data = ? WHERE id = ?");
                            $updateStmt->execute([json_encode($existingData, JSON_UNESCAPED_SLASHES), $existing['id']]);
                        } else {
                            $insertStmt = $db->prepare("INSERT INTO efb_records (flight_id, task_name, flt_date, actual_wx_data, created_by) VALUES (?, ?, ?, ?, ?)");
                            $insertStmt->execute([
                                $flightId,
                                $flight['TaskName'],
                                $fltDate,
                                json_encode([$fileData], JSON_UNESCAPED_SLASHES),
                                $currentUser['id'] ?? null
                            ]);
                        }
                        
                        $message = 'Actual WX (METAR) generated successfully.';
                        header('Location: ?view_efb=1&flight_id=' . $flightId . '&flight_date=' . urlencode($fltDate) . '&msg=' . urlencode($message));
                        exit;
                    }
                } else {
                    if (empty($allWeatherData)) {
                        $error = 'No weather data available for airports: ' . $originIataCode . ' (ICAO: ' . $originIcaoCode . ')' . 
                                 ($originIataCode !== $destinationIataCode ? ', ' . $destinationIataCode . ' (ICAO: ' . $destinationIcaoCode . ')' : '');
                    }
                }
            } else {
                $error = 'Route must contain at least 2 airports (origin and destination).';
            }
        } else {
            $error = 'Flight not found or route is empty.';
        }
    } else {
        $error = 'Invalid request.';
    }
}

// Handle Actual WX file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_actual_wx_file') {
    $flightId = !empty($_POST['flight_id']) ? (int)$_POST['flight_id'] : null;
    $fltDate = trim($_POST['flt_date'] ?? '');
    $filePath = trim($_POST['file_path'] ?? '');
    
    if ($flightId && $fltDate && $filePath) {
        $checkStmt = $db->prepare("SELECT id, actual_wx_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
        $checkStmt->execute([$flightId, $fltDate]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing && !empty($existing['actual_wx_data'])) {
            $files = json_decode($existing['actual_wx_data'], true) ?: [];
            $updatedFiles = [];
            $fileDeleted = false;
            
            foreach ($files as $file) {
                if (($file['path'] ?? '') !== $filePath) {
                    $updatedFiles[] = $file;
                } else {
                    $fileDeleted = true;
                    $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filePath), DIRECTORY_SEPARATOR);
                    if (file_exists($absolutePath)) {
                        @unlink($absolutePath);
                    }
                }
            }
            
            if ($fileDeleted) {
                if (empty($updatedFiles)) {
                    $updateStmt = $db->prepare("UPDATE efb_records SET actual_wx_data = NULL WHERE id = ?");
                    $updateStmt->execute([$existing['id']]);
                } else {
                    $updateStmt = $db->prepare("UPDATE efb_records SET actual_wx_data = ? WHERE id = ?");
                    $updateStmt->execute([json_encode($updatedFiles, JSON_UNESCAPED_SLASHES), $existing['id']]);
                }
                $message = 'File deleted successfully.';
                header('Location: ?view_efb=1&flight_id=' . $flightId . '&flight_date=' . urlencode($fltDate) . '&msg=' . urlencode($message));
                exit;
            }
        }
    }
}

// Handle WX Forecast (TAF) generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_wx_forecast') {
    $flightId = !empty($_POST['flight_id']) ? (int)$_POST['flight_id'] : null;
    $fltDate = trim($_POST['flt_date'] ?? '');
    
    if ($flightId && $fltDate) {
        // Get flight details
        $flightStmt = $db->prepare("SELECT FlightID, TaskName, Route, FltDate FROM flights WHERE FlightID = ? AND DATE(FltDate) = ? LIMIT 1");
        $flightStmt->execute([$flightId, $fltDate]);
        $flight = $flightStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($flight && !empty($flight['Route'])) {
            // Extract origin and destination airports from Route
            $route = $flight['Route'];
            $airportCodes = array_filter(array_map('trim', explode('-', $route)));
            
            if (!empty($airportCodes) && count($airportCodes) >= 2) {
                // Get origin and destination - these are IATA codes
                $originIataCode = reset($airportCodes);
                $destinationIataCode = end($airportCodes);
                
                // Convert IATA codes to ICAO codes from stations table
                $originIcaoCode = null;
                $destinationIcaoCode = null;
                
                // Get origin ICAO code
                $stationStmt = $db->prepare("SELECT icao_code FROM stations WHERE iata_code = ? LIMIT 1");
                $stationStmt->execute([$originIataCode]);
                $station = $stationStmt->fetch(PDO::FETCH_ASSOC);
                if ($station && !empty($station['icao_code'])) {
                    $originIcaoCode = $station['icao_code'];
                } else {
                    $originIcaoCode = $originIataCode; // Fallback
                }
                
                // Get destination ICAO code
                if ($originIataCode !== $destinationIataCode) {
                    $stationStmt->execute([$destinationIataCode]);
                    $station = $stationStmt->fetch(PDO::FETCH_ASSOC);
                    if ($station && !empty($station['icao_code'])) {
                        $destinationIcaoCode = $station['icao_code'];
                    } else {
                        $destinationIcaoCode = $destinationIataCode; // Fallback
                    }
                } else {
                    $destinationIcaoCode = $originIcaoCode; // Same airport
                }
                
                // Fetch TAF data for both origin and destination airports using ICAO codes
                $allTAFData = [];
                $airportsData = [];
                
                // Fetch origin TAF
                if ($originIcaoCode) {
                    $tafData = fetchTAFData($originIcaoCode);
                    if ($tafData && !empty($tafData)) {
                        $allTAFData = array_merge($allTAFData, $tafData);
                        $airportsData[] = [
                            'iata' => $originIataCode,
                            'icao' => $originIcaoCode,
                            'type' => 'origin'
                        ];
                    }
                }
                
                // Fetch destination TAF (if different from origin)
                if ($destinationIcaoCode && $destinationIcaoCode !== $originIcaoCode) {
                    $tafData = fetchTAFData($destinationIcaoCode);
                    if ($tafData && !empty($tafData)) {
                        $allTAFData = array_merge($allTAFData, $tafData);
                        $airportsData[] = [
                            'iata' => $destinationIataCode,
                            'icao' => $destinationIcaoCode,
                            'type' => 'destination'
                        ];
                    }
                }
                
                // Check if OFP data exists and extract alternate airports
                $alternateAirports = [];
                $checkOFPStmt = $db->prepare("SELECT ofp_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
                $checkOFPStmt->execute([$flightId, $fltDate]);
                $ofpRecord = $checkOFPStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ofpRecord && !empty($ofpRecord['ofp_data'])) {
                    $ofpDataArray = json_decode($ofpRecord['ofp_data'], true);
                    if (is_array($ofpDataArray) && !empty($ofpDataArray)) {
                        // Get the most recent OFP data
                        $latestOFP = end($ofpDataArray);
                        if (isset($latestOFP['raw_data']) && !empty($latestOFP['raw_data'])) {
                            $alternateAirports = extractAlternateAirports($latestOFP['raw_data'], $db);
                            
                            // Fetch TAF for alternate airports
                            foreach ($alternateAirports as $alt) {
                                if (!empty($alt['icao'])) {
                                    $altIcao = $alt['icao'];
                                    // Skip if already fetched (origin/destination)
                                    $alreadyFetched = false;
                                    foreach ($airportsData as $apt) {
                                        if ($apt['icao'] === $altIcao) {
                                            $alreadyFetched = true;
                                            break;
                                        }
                                    }
                                    
                                    if (!$alreadyFetched) {
                                        $tafData = fetchTAFData($altIcao);
                                        if ($tafData && !empty($tafData)) {
                                            $allTAFData = array_merge($allTAFData, $tafData);
                                            $airportsData[] = [
                                                'iata' => $alt['iata'] ?? $alt['code'],
                                                'icao' => $altIcao,
                                                'type' => 'alternate'
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (!empty($allTAFData)) {
                    // Generate HTML for TAF data
                    $route = $flight['Route'];
                    $fltDate = $flight['FltDate'];
                    // Pass airportsData to template for type labels
                    ob_start();
                    include __DIR__ . '/generate_taf_html.php';
                    $tafHTML = ob_get_clean();
                    
                    // Create directory structure
                    $dateFolder = date('Y-m-d', strtotime($fltDate));
                    $flightFolder = (string)$flightId;
                    $uploadDir = $uploadBaseDir . DIRECTORY_SEPARATOR . $dateFolder . DIRECTORY_SEPARATOR . $flightFolder;
                    
                    // Ensure base directory exists first
                    if (!is_dir($uploadBaseDir)) {
                        if (!@mkdir($uploadBaseDir, 0775, true)) {
                            if (!@mkdir($uploadBaseDir, 0755, true)) {
                                $error = 'Failed to create base directory: ' . $uploadBaseDir;
                            }
                        }
                    }
                    
                    // Ensure base directory is writable
                    if (is_dir($uploadBaseDir) && !is_writable($uploadBaseDir)) {
                        @chmod($uploadBaseDir, 0775);
                        if (!is_writable($uploadBaseDir)) {
                            @chmod($uploadBaseDir, 0755);
                        }
                    }
                    
                    // Now create the full directory structure
                    if (empty($error) && !is_dir($uploadDir)) {
                        // Try to create with 0775 first
                        if (!@mkdir($uploadDir, 0775, true)) {
                            // Try with 0755 if 0775 fails
                            if (!@mkdir($uploadDir, 0755, true)) {
                                $error = 'Failed to create upload directory: ' . $uploadDir . '. Please check directory permissions.';
                            }
                        }
                    }
                    
                    // Check if directory was created successfully and is writable
                    if (empty($error) && (!is_dir($uploadDir) || !is_writable($uploadDir))) {
                        if (is_dir($uploadDir)) {
                            // Try to change permissions
                            @chmod($uploadDir, 0775);
                            if (!is_writable($uploadDir)) {
                                @chmod($uploadDir, 0755);
                            }
                            // Check again
                            if (!is_writable($uploadDir)) {
                                $error = 'Upload directory exists but is not writable: ' . $uploadDir . '. Current permissions may be too restrictive.';
                            }
                        } else {
                            $error = 'Upload directory does not exist: ' . $uploadDir . '. Please check if parent directories exist and are writable.';
                        }
                    }
                    
                    // Generate PDF file name
                    $pdfFileName = 'taf_' . $flightId . '_' . time() . '.pdf';
                    $pdfFilePath = $uploadDir . DIRECTORY_SEPARATOR . $pdfFileName;
                    $relativePath = 'admin/efb/' . $dateFolder . '/' . $flightFolder . '/' . $pdfFileName;
                    
                    // Generate PDF using mPDF
                    $pdfGenerated = false;
                    if (empty($error)) {
                        try {
                            // Create mPDF instance (version 6.1 uses 'mPDF', version 8.x uses '\Mpdf\Mpdf')
                            $mpdfClass = class_exists('mPDF') ? 'mPDF' : '\Mpdf\Mpdf';
                            $mpdf = new $mpdfClass([
                                'mode' => 'utf-8',
                                'format' => 'A4',
                                'margin_left' => 5,
                                'margin_right' => 5,
                                'margin_top' => 5,
                                'margin_bottom' => 5,
                                'margin_header' => 0,
                                'margin_footer' => 0,
                                'tempDir' => sys_get_temp_dir()
                            ]);
                            
                            // Write HTML content
                            $mpdf->WriteHTML($tafHTML);
                            
                            // Output PDF to file
                            $mpdf->Output($pdfFilePath, 'F');
                            
                            if (file_exists($pdfFilePath) && filesize($pdfFilePath) > 0) {
                                $pdfGenerated = true;
                            } else {
                                $error = 'PDF file was not created successfully.';
                            }
                        } catch (\Exception $e) {
                            $error = 'PDF generation failed: ' . $e->getMessage();
                        }
                    }
                    
                    // Only proceed if no errors occurred and PDF was generated
                    if (empty($error) && $pdfGenerated && isset($pdfFilePath) && file_exists($pdfFilePath)) {
                        // Save to database - only if PDF was generated successfully
                        $fileData = [
                            'path' => $relativePath,
                            'original_name' => 'TAF_' . $originIataCode . ($originIataCode !== $destinationIataCode ? '_' . $destinationIataCode : '') . '_' . date('Y-m-d', strtotime($fltDate)) . '.pdf',
                            'stored_name' => $pdfFileName,
                            'upload_time' => date('Y-m-d H:i:s'),
                            'size' => filesize($pdfFilePath),
                            'type' => 'pdf',
                            'route' => $route,
                            'origin_iata' => $originIataCode,
                            'origin_icao' => $originIcaoCode,
                            'destination_iata' => $destinationIataCode,
                            'destination_icao' => $destinationIcaoCode,
                            'airports' => $airportsData
                        ];
                        
                        // Find or create EFB record
                        $checkStmt = $db->prepare("SELECT id, wx_forecast_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
                        $checkStmt->execute([$flightId, $fltDate]);
                        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $currentUser = getCurrentUser();
                        if ($existing) {
                            $existingData = json_decode($existing['wx_forecast_data'] ?? '[]', true) ?: [];
                            $existingData[] = $fileData;
                            $updateStmt = $db->prepare("UPDATE efb_records SET wx_forecast_data = ? WHERE id = ?");
                            $updateStmt->execute([json_encode($existingData, JSON_UNESCAPED_SLASHES), $existing['id']]);
                        } else {
                            $insertStmt = $db->prepare("INSERT INTO efb_records (flight_id, task_name, flt_date, wx_forecast_data, created_by) VALUES (?, ?, ?, ?, ?)");
                            $insertStmt->execute([
                                $flightId,
                                $flight['TaskName'],
                                $fltDate,
                                json_encode([$fileData], JSON_UNESCAPED_SLASHES),
                                $currentUser['id'] ?? null
                            ]);
                        }
                        
                        $message = 'WX Forecast (TAF) generated successfully.';
                        header('Location: ?view_efb=1&flight_id=' . $flightId . '&flight_date=' . urlencode($fltDate) . '&msg=' . urlencode($message));
                        exit;
                    }
                } elseif (!empty($error)) {
                    // Error already set above
                } else {
                    $error = 'No TAF data available for airports: ' . $originIataCode . ' (ICAO: ' . $originIcaoCode . ')' . 
                             ($originIataCode !== $destinationIataCode ? ', ' . $destinationIataCode . ' (ICAO: ' . $destinationIcaoCode . ')' : '');
                }
            } else {
                $error = 'Route must contain at least 2 airports (origin and destination).';
            }
        } else {
            $error = 'Flight not found or route is empty.';
        }
    } else {
        $error = 'Invalid request.';
    }
}

// Handle OFP data loading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'load_ofp') {
    $flightId = !empty($_POST['flight_id']) ? (int)$_POST['flight_id'] : null;
    $fltDate = trim($_POST['flt_date'] ?? '');
    
    if ($flightId && $fltDate) {
        // Get flight details
        $flightStmt = $db->prepare("SELECT FlightID, TaskName, Route, FltDate FROM flights WHERE FlightID = ? AND DATE(FltDate) = ? LIMIT 1");
        $flightStmt->execute([$flightId, $fltDate]);
        $flight = $flightStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($flight && !empty($flight['Route'])) {
            // Load OFP data from log files
            $log_dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'skyputer' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
            
            // Extract date for filtering
            $filterDate = date('Y-m-d', strtotime($fltDate));
            
            // Extract route - get origin and destination (IATA codes)
            $route = $flight['Route'];
            $routeParts = array_filter(array_map('trim', explode('-', $route)));
            
            // Convert IATA codes to ICAO codes
            $originIata = !empty($routeParts) ? reset($routeParts) : '';
            $destinationIata = !empty($routeParts) && count($routeParts) > 1 ? end($routeParts) : $originIata;
            
            $originIcao = $originIata;
            $destinationIcao = $destinationIata;
            
            // Get origin ICAO code
            if (!empty($originIata)) {
                $stationStmt = $db->prepare("SELECT icao_code FROM stations WHERE iata_code = ? LIMIT 1");
                $stationStmt->execute([$originIata]);
                $station = $stationStmt->fetch(PDO::FETCH_ASSOC);
                if ($station && !empty($station['icao_code'])) {
                    $originIcao = $station['icao_code'];
                }
            }
            
            // Get destination ICAO code
            if (!empty($destinationIata) && $destinationIata !== $originIata) {
                $stationStmt->execute([$destinationIata]);
                $station = $stationStmt->fetch(PDO::FETCH_ASSOC);
                if ($station && !empty($station['icao_code'])) {
                    $destinationIcao = $station['icao_code'];
                }
            } else {
                $destinationIcao = $originIcao;
            }
            
            // Create search patterns for both IATA and ICAO
            $searchPatterns = [];
            if (!empty($originIata) && !empty($destinationIata)) {
                // IATA patterns
                $searchPatterns[] = $originIata . ' - ' . $destinationIata;
                $searchPatterns[] = $originIata . '-' . $destinationIata;
                $searchPatterns[] = $originIata . ' ' . $destinationIata;
                
                // ICAO patterns
                if ($originIcao !== $originIata || $destinationIcao !== $destinationIata) {
                    $searchPatterns[] = $originIcao . ' - ' . $destinationIcao;
                    $searchPatterns[] = $originIcao . '-' . $destinationIcao;
                    $searchPatterns[] = $originIcao . ' ' . $destinationIcao;
                }
                
                // Mixed patterns
                $searchPatterns[] = $originIata . ' - ' . $destinationIcao;
                $searchPatterns[] = $originIcao . ' - ' . $destinationIata;
            }
            
            // Load OFP data
            $ofpData = [];
            if (is_dir($log_dir)) {
                $pattern = $log_dir . 'skyputer_ofp-*.json';
                $found_files = glob($pattern);
                
                foreach ($found_files as $file_path) {
                    // Extract date from filename
                    $filename = basename($file_path);
                    if (preg_match('/skyputer_ofp-(\d{4})-(\d{2})-(\d{2})-(\d{2})\.json/', $filename, $matches)) {
                        $file_date = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
                        
                        // Filter by date
                        if ($file_date !== $filterDate) {
                            continue;
                        }
                    } else {
                        continue;
                    }
                    
                    $content = @file_get_contents($file_path);
                    if ($content === false) {
                        continue;
                    }
                    
                    $json_data = @json_decode($content, true);
                    if (!is_array($json_data)) {
                        continue;
                    }
                    
                    // Process each record
                    foreach ($json_data as $record) {
                        if (!is_array($record)) {
                            continue;
                        }
                        
                        // Extract route from record
                        $recordRoute = '';
                        if (isset($record['parsed_data']['binfo']['RTS'])) {
                            $recordRoute = $record['parsed_data']['binfo']['RTS'];
                        } elseif (isset($record['parsed_data']['binfo']['|RTS'])) {
                            $recordRoute = $record['parsed_data']['binfo']['|RTS'];
                        } elseif (isset($record['flight_info']['route'])) {
                            $recordRoute = $record['flight_info']['route'];
                        }
                        
                        // Check if route matches (flexible matching with both IATA and ICAO)
                        $routeMatch = false;
                        if (!empty($searchPatterns) && !empty($recordRoute)) {
                            // Normalize record route for comparison
                            $normalizedRecord = str_replace([' ', '-'], '', strtoupper($recordRoute));
                            
                            // Check against all search patterns
                            foreach ($searchPatterns as $pattern) {
                                $normalizedPattern = str_replace([' ', '-'], '', strtoupper($pattern));
                                if (stripos($normalizedRecord, $normalizedPattern) !== false || 
                                    stripos($normalizedPattern, $normalizedRecord) !== false) {
                                    $routeMatch = true;
                                    break;
                                }
                            }
                            
                            // Also check individual airports (both IATA and ICAO)
                            if (!$routeMatch) {
                                // Check origin
                                $originMatch = false;
                                if (!empty($originIata) && stripos($recordRoute, $originIata) !== false) {
                                    $originMatch = true;
                                }
                                if (!$originMatch && !empty($originIcao) && stripos($recordRoute, $originIcao) !== false) {
                                    $originMatch = true;
                                }
                                
                                // Check destination
                                $destMatch = false;
                                if (!empty($destinationIata) && stripos($recordRoute, $destinationIata) !== false) {
                                    $destMatch = true;
                                }
                                if (!$destMatch && !empty($destinationIcao) && stripos($recordRoute, $destinationIcao) !== false) {
                                    $destMatch = true;
                                }
                                
                                // If both origin and destination match, it's a match
                                if ($originMatch && $destMatch) {
                                    $routeMatch = true;
                                }
                            }
                        } else {
                            // If no route filter, match by date only
                            $routeMatch = true;
                        }
                        
                        if ($routeMatch && !empty($record['raw_data'])) {
                            $ofpData[] = [
                                'raw_data' => $record['raw_data'],
                                'request_id' => $record['request_id'] ?? '',
                                'timestamp' => $record['timestamp_local'] ?? $record['timestamp_utc'] ?? '',
                                'route' => $recordRoute
                            ];
                        }
                    }
                }
            }
            
            if (!empty($ofpData)) {
                // Get the most recent OFP data (first one after sorting)
                usort($ofpData, function($a, $b) {
                    $time_a = strtotime($a['timestamp'] ?? '');
                    $time_b = strtotime($b['timestamp'] ?? '');
                    return $time_b - $time_a;
                });
                
                $selectedOFP = reset($ofpData);
                $rawData = $selectedOFP['raw_data'];
                
                // Save to database
                $fileData = [
                    'raw_data' => $rawData,
                    'request_id' => $selectedOFP['request_id'] ?? '',
                    'timestamp' => $selectedOFP['timestamp'] ?? '',
                    'route' => $selectedOFP['route'] ?? $route,
                    'load_time' => date('Y-m-d H:i:s')
                ];
                
                // Find or create EFB record
                $checkStmt = $db->prepare("SELECT id, ofp_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
                $checkStmt->execute([$flightId, $fltDate]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                $currentUser = getCurrentUser();
                if ($existing) {
                    $existingData = json_decode($existing['ofp_data'] ?? '[]', true) ?: [];
                    $existingData[] = $fileData;
                    $updateStmt = $db->prepare("UPDATE efb_records SET ofp_data = ? WHERE id = ?");
                    $updateStmt->execute([json_encode($existingData, JSON_UNESCAPED_SLASHES), $existing['id']]);
                } else {
                    $insertStmt = $db->prepare("INSERT INTO efb_records (flight_id, task_name, flt_date, ofp_data, created_by) VALUES (?, ?, ?, ?, ?)");
                    $insertStmt->execute([
                        $flightId,
                        $flight['TaskName'],
                        $fltDate,
                        json_encode([$fileData], JSON_UNESCAPED_SLASHES),
                        $currentUser['id'] ?? null
                    ]);
                }
                
                $message = 'OFP data loaded successfully.';
                header('Location: ?view_efb=1&flight_id=' . $flightId . '&flight_date=' . urlencode($fltDate) . '&msg=' . urlencode($message));
                exit;
            } else {
                $error = 'No OFP data found for this flight (Route: ' . $route . ' [IATA] / ' . $originIcao . '-' . $destinationIcao . ' [ICAO], Date: ' . $filterDate . ').';
            }
        } else {
            $error = 'Flight not found or route is empty.';
        }
    } else {
        $error = 'Invalid request.';
    }
}

// Handle NOTAM generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_notam') {
    $flightId = !empty($_POST['flight_id']) ? (int)$_POST['flight_id'] : null;
    $fltDate = trim($_POST['flt_date'] ?? '');
    
    if ($flightId && $fltDate) {
        // Get OFP data from efb_records
        $checkStmt = $db->prepare("SELECT id, ofp_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
        $checkStmt->execute([$flightId, $fltDate]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing && !empty($existing['ofp_data'])) {
            $ofpDataArray = json_decode($existing['ofp_data'], true) ?: [];
            
            if (!empty($ofpDataArray)) {
                // Get the latest OFP data
                $latestOFP = end($ofpDataArray);
                $rawData = $latestOFP['raw_data'] ?? '';
                
                if (!empty($rawData)) {
                    // Extract ICAO codes from OFP data
                    $icaoCodes = extractICAOCodesFromOFP($rawData);
                    
                    if (!empty($icaoCodes)) {
                        // Fetch NOTAM data from API for each ICAO code
                        $notamData = [];
                        $apiToken = 'NJ2P6hwGXH8F5TD7f1HhHb1nsjAavzAisirj';
                        $apiUrl = 'https://api.aviapages.com/v3/notams/?decode=true';
                        
                        foreach ($icaoCodes as $icao) {
                            $url = $apiUrl . '&icao=' . urlencode($icao);
                            
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Authorization: Token ' . $apiToken
                            ]);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                            
                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            
                            if ($httpCode === 200 && !empty($response)) {
                                $notamResponse = json_decode($response, true);
                                if (is_array($notamResponse) && isset($notamResponse[$icao])) {
                                    $notamData[$icao] = $notamResponse[$icao];
                                }
                            }
                            
                            // Small delay to avoid rate limiting
                            usleep(200000); // 0.2 seconds
                        }
                        
                        // Save NOTAM data to database
                        $notamRecord = [
                            'icao_codes' => $icaoCodes,
                            'notam_data' => $notamData,
                            'generated_at' => date('Y-m-d H:i:s'),
                            'ofp_timestamp' => $latestOFP['timestamp'] ?? ''
                        ];
                        
                        // Check if notam_data column exists, create it if it doesn't
                        try {
                            $checkColumnStmt = $db->query("SHOW COLUMNS FROM efb_records LIKE 'notam_data'");
                            $columnExists = $checkColumnStmt->fetch(PDO::FETCH_ASSOC);
                            if (!$columnExists) {
                                $alterStmt = $db->prepare("ALTER TABLE efb_records ADD COLUMN notam_data TEXT");
                                $alterStmt->execute();
                            }
                        } catch (PDOException $e) {
                            // Try to create column anyway
                            try {
                                $alterStmt = $db->prepare("ALTER TABLE efb_records ADD COLUMN notam_data TEXT");
                                $alterStmt->execute();
                            } catch (PDOException $e2) {
                                // Column might already exist, continue
                            }
                        }
                        
                        // Check if record exists
                        $checkNotamStmt = $db->prepare("SELECT id, notam_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
                        $checkNotamStmt->execute([$flightId, $fltDate]);
                        $notamRecordExists = $checkNotamStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($notamRecordExists) {
                            // Update existing record
                            $updateStmt = $db->prepare("UPDATE efb_records SET notam_data = ? WHERE id = ?");
                            $updateStmt->execute([json_encode($notamRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $notamRecordExists['id']]);
                        } else {
                            // Create new record with NOTAM data
                            $currentUser = getCurrentUser();
                            // Get flight info
                            $flightStmt = $db->prepare("SELECT FlightID, TaskName, Route, FltDate FROM flights WHERE FlightID = ? AND DATE(FltDate) = ? LIMIT 1");
                            $flightStmt->execute([$flightId, $fltDate]);
                            $flight = $flightStmt->fetch(PDO::FETCH_ASSOC);
                            
                            $insertStmt = $db->prepare("INSERT INTO efb_records (flight_id, task_name, flt_date, notam_data, created_by) VALUES (?, ?, ?, ?, ?)");
                            $insertStmt->execute([
                                $flightId,
                                $flight['TaskName'] ?? '',
                                $fltDate,
                                json_encode($notamRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                                $currentUser['id'] ?? null
                            ]);
                        }
                        
                        if (empty($error)) {
                            $message = 'NOTAM data generated successfully for ' . count($icaoCodes) . ' ICAO code(s).';
                            header('Location: ?view_efb=1&flight_id=' . $flightId . '&flight_date=' . urlencode($fltDate) . '&msg=' . urlencode($message));
                            exit;
                        }
                    } else {
                        $error = 'No ICAO codes found in OFP data. Please load OFP data first.';
                    }
                } else {
                    $error = 'OFP raw data is empty. Please load OFP data first.';
                }
            } else {
                $error = 'No OFP data found. Please load OFP data first.';
            }
        } else {
            $error = 'No OFP data found. Please load OFP data first.';
        }
    } else {
        $error = 'Invalid request.';
    }
}

// Handle NOTAM PDF generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_notam_pdf') {
    $flightId = !empty($_POST['flight_id']) ? (int)$_POST['flight_id'] : null;
    $fltDate = trim($_POST['flt_date'] ?? '');
    
    if ($flightId && $fltDate) {
        // Get flight info
        $flightStmt = $db->prepare("SELECT FlightID, TaskName, Route, FltDate FROM flights WHERE FlightID = ? AND DATE(FltDate) = ? LIMIT 1");
        $flightStmt->execute([$flightId, $fltDate]);
        $flight = $flightStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($flight) {
            // Get NOTAM data
            $checkStmt = $db->prepare("SELECT id, notam_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
            $checkStmt->execute([$flightId, $fltDate]);
            $efbRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($efbRecord && !empty($efbRecord['notam_data'])) {
                $notamRecord = json_decode($efbRecord['notam_data'], true);
                
                if ($notamRecord && !empty($notamRecord['notam_data'])) {
                    // Generate HTML for NOTAM data
                    ob_start();
                    ?>
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                font-size: 10pt;
                                margin: 0;
                                padding: 10px;
                            }
                            .header {
                                text-align: center;
                                border-bottom: 2px solid #333;
                                padding-bottom: 10px;
                                margin-bottom: 15px;
                            }
                            .header h1 {
                                margin: 0;
                                font-size: 16pt;
                                color: #d97706;
                            }
                            .header h2 {
                                margin: 5px 0;
                                font-size: 12pt;
                                color: #666;
                            }
                            .flight-info {
                                background-color: #f3f4f6;
                                padding: 10px;
                                margin-bottom: 15px;
                                border-radius: 5px;
                            }
                            .flight-info table {
                                width: 100%;
                                border-collapse: collapse;
                            }
                            .flight-info td {
                                padding: 3px 5px;
                                font-size: 9pt;
                            }
                            .flight-info td:first-child {
                                font-weight: bold;
                                width: 30%;
                            }
                            .icao-section {
                                margin-bottom: 20px;
                                page-break-inside: avoid;
                            }
                            .icao-header {
                                background-color: #ea580c;
                                color: white;
                                padding: 8px;
                                font-weight: bold;
                                font-size: 11pt;
                                border-radius: 3px 3px 0 0;
                            }
                            .notam-item {
                                border: 1px solid #ddd;
                                border-top: none;
                                padding: 10px;
                                margin-bottom: 10px;
                                background-color: #fff;
                            }
                            .notam-item:first-of-type {
                                border-top: 1px solid #ddd;
                            }
                            .notam-id {
                                font-weight: bold;
                                color: #ea580c;
                                font-size: 11pt;
                                margin-bottom: 5px;
                            }
                            .notam-dates {
                                font-size: 9pt;
                                color: #666;
                                margin-bottom: 8px;
                            }
                            .notam-subject {
                                font-weight: bold;
                                font-size: 10pt;
                                margin-bottom: 5px;
                                color: #333;
                            }
                            .notam-description {
                                font-size: 9pt;
                                line-height: 1.4;
                                color: #444;
                                margin-top: 5px;
                            }
                            .notam-raw {
                                font-family: 'Courier New', monospace;
                                font-size: 8pt;
                                background-color: #f9fafb;
                                padding: 8px;
                                border-left: 3px solid #ea580c;
                                margin-top: 8px;
                                white-space: pre-wrap;
                                word-wrap: break-word;
                            }
                            .footer {
                                margin-top: 20px;
                                padding-top: 10px;
                                border-top: 1px solid #ddd;
                                text-align: center;
                                font-size: 8pt;
                                color: #666;
                            }
                            .no-notams {
                                text-align: center;
                                padding: 20px;
                                color: #999;
                                font-style: italic;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>NOTAM REPORT</h1>
                            <h2>Notices to Airmen</h2>
                        </div>
                        
                        <div class="flight-info">
                            <table>
                                <tr>
                                    <td>Flight:</td>
                                    <td><?php echo htmlspecialchars($flight['TaskName'] ?? ''); ?></td>
                                </tr>
                                <tr>
                                    <td>Route:</td>
                                    <td><?php echo htmlspecialchars($flight['Route'] ?? ''); ?></td>
                                </tr>
                                <tr>
                                    <td>Flight Date:</td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($flight['FltDate']))); ?></td>
                                </tr>
                                <tr>
                                    <td>Generated:</td>
                                    <td><?php echo htmlspecialchars($notamRecord['generated_at'] ?? date('Y-m-d H:i:s')); ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <?php 
                        $hasNotams = false;
                        foreach ($notamRecord['notam_data'] as $icao => $notams): 
                            if (is_array($notams) && !empty($notams)): 
                                $hasNotams = true;
                        ?>
                        <div class="icao-section">
                            <div class="icao-header">
                                ICAO: <?php echo htmlspecialchars($icao); ?> - <?php echo count($notams); ?> NOTAM<?php echo count($notams) > 1 ? 's' : ''; ?>
                            </div>
                            
                            <?php foreach ($notams as $notam): ?>
                            <div class="notam-item">
                                <?php if (isset($notam['notam_decoded'])): ?>
                                    <div class="notam-id">
                                        <?php echo htmlspecialchars($notam['notam_decoded']['head']['notam_id'] ?? ''); ?>/<?php echo htmlspecialchars($notam['notam_decoded']['head']['notam_iss_year'] ?? ''); ?>
                                        <?php if (isset($notam['notam_decoded']['Q']['Q'])): ?>
                                            - <?php echo htmlspecialchars($notam['notam_decoded']['Q']['Q']['subject'] ?? ''); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notam-dates">
                                        Valid: <?php echo htmlspecialchars($notam['notam_decoded']['B'] ?? ''); ?>
                                        <?php if (isset($notam['notam_decoded']['C']['Time'])): ?>
                                            - <?php echo htmlspecialchars($notam['notam_decoded']['C']['Time']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (isset($notam['notam_decoded']['Q']['Q'])): ?>
                                        <div class="notam-subject">
                                            <?php echo htmlspecialchars($notam['notam_decoded']['Q']['Q']['subject_type'] ?? ''); ?>: 
                                            <?php echo htmlspecialchars($notam['notam_decoded']['Q']['Q']['subject'] ?? ''); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($notam['notam_decoded']['E'])): ?>
                                        <div class="notam-description">
                                            <?php echo htmlspecialchars($notam['notam_decoded']['E']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($notam['notam_decoded']['F']) || isset($notam['notam_decoded']['G'])): ?>
                                        <div class="notam-description" style="margin-top: 5px;">
                                            <?php if (isset($notam['notam_decoded']['F'])): ?>
                                                Lower Limit: <?php echo htmlspecialchars($notam['notam_decoded']['F']); ?>
                                            <?php endif; ?>
                                            <?php if (isset($notam['notam_decoded']['G'])): ?>
                                                Upper Limit: <?php echo htmlspecialchars($notam['notam_decoded']['G']); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if (isset($notam['text'])): ?>
                                    <div class="notam-raw">
                                        <?php echo htmlspecialchars($notam['text']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php 
                            endif;
                        endforeach; 
                        
                        if (!$hasNotams):
                        ?>
                        <div class="no-notams">
                            No NOTAMs found for the specified ICAO codes.
                        </div>
                        <?php endif; ?>
                        
                        <div class="footer">
                            Generated on <?php echo date('Y-m-d H:i:s'); ?> | Electronic Flight Bag System
                        </div>
                    </body>
                    </html>
                    <?php
                    $notamHTML = ob_get_clean();
                    
                    // Create directory structure
                    $dateFolder = date('Y-m-d', strtotime($fltDate));
                    $flightFolder = (string)$flightId;
                    $uploadDir = $uploadBaseDir . DIRECTORY_SEPARATOR . $dateFolder . DIRECTORY_SEPARATOR . $flightFolder;
                    
                    // Ensure directories exist
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0775, true);
                    }
                    
                    // Generate PDF file name
                    $pdfFileName = 'notam_' . $flightId . '_' . time() . '.pdf';
                    $pdfFilePath = $uploadDir . DIRECTORY_SEPARATOR . $pdfFileName;
                    $relativePath = 'admin/efb/' . $dateFolder . '/' . $flightFolder . '/' . $pdfFileName;
                    
                    // Generate PDF using mPDF
                    $pdfGenerated = false;
                    if (empty($error) && is_dir($uploadDir) && is_writable($uploadDir)) {
                        try {
                            $mpdfClass = class_exists('mPDF') ? 'mPDF' : '\Mpdf\Mpdf';
                            $mpdf = new $mpdfClass([
                                'mode' => 'utf-8',
                                'format' => 'A4',
                                'margin_left' => 10,
                                'margin_right' => 10,
                                'margin_top' => 15,
                                'margin_bottom' => 15,
                                'margin_header' => 5,
                                'margin_footer' => 5,
                                'tempDir' => sys_get_temp_dir()
                            ]);
                            
                            $mpdf->WriteHTML($notamHTML);
                            $mpdf->Output($pdfFilePath, 'F');
                            
                            if (file_exists($pdfFilePath) && filesize($pdfFilePath) > 0) {
                                $pdfGenerated = true;
                                
                                // Save to database
                                $fileData = [
                                    'path' => $relativePath,
                                    'original_name' => 'NOTAM_Report_' . $flight['TaskName'] . '_' . date('Y-m-d', strtotime($fltDate)) . '.pdf',
                                    'type' => 'pdf',
                                    'generated_at' => date('Y-m-d H:i:s')
                                ];
                                
                                // Check if notam_pdf_data column exists
                                try {
                                    $checkColumnStmt = $db->query("SHOW COLUMNS FROM efb_records LIKE 'notam_pdf_data'");
                                    $columnExists = $checkColumnStmt->fetch(PDO::FETCH_ASSOC);
                                    if (!$columnExists) {
                                        $alterStmt = $db->prepare("ALTER TABLE efb_records ADD COLUMN notam_pdf_data TEXT");
                                        $alterStmt->execute();
                                    }
                                } catch (PDOException $e) {
                                    try {
                                        $alterStmt = $db->prepare("ALTER TABLE efb_records ADD COLUMN notam_pdf_data TEXT");
                                        $alterStmt->execute();
                                    } catch (PDOException $e2) {
                                        // Column might already exist
                                    }
                                }
                                
                                // Get existing PDF files
                                $checkPdfStmt = $db->prepare("SELECT id, notam_pdf_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
                                $checkPdfStmt->execute([$flightId, $fltDate]);
                                $pdfRecord = $checkPdfStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($pdfRecord) {
                                    $existingPdfs = json_decode($pdfRecord['notam_pdf_data'] ?? '[]', true) ?: [];
                                    $existingPdfs[] = $fileData;
                                    $updateStmt = $db->prepare("UPDATE efb_records SET notam_pdf_data = ? WHERE id = ?");
                                    $updateStmt->execute([json_encode($existingPdfs, JSON_UNESCAPED_SLASHES), $pdfRecord['id']]);
                                } else {
                                    $currentUser = getCurrentUser();
                                    $insertStmt = $db->prepare("INSERT INTO efb_records (flight_id, task_name, flt_date, notam_pdf_data, created_by) VALUES (?, ?, ?, ?, ?)");
                                    $insertStmt->execute([
                                        $flightId,
                                        $flight['TaskName'] ?? '',
                                        $fltDate,
                                        json_encode([$fileData], JSON_UNESCAPED_SLASHES),
                                        $currentUser['id'] ?? null
                                    ]);
                                }
                                
                                $message = 'NOTAM PDF generated successfully.';
                                header('Location: ?view_efb=1&flight_id=' . $flightId . '&flight_date=' . urlencode($fltDate) . '&msg=' . urlencode($message));
                                exit;
                            } else {
                                $error = 'PDF file was not created successfully.';
                            }
                        } catch (\Exception $e) {
                            $error = 'PDF generation failed: ' . $e->getMessage();
                        }
                    } else {
                        $error = 'Upload directory is not writable.';
                    }
                } else {
                    $error = 'No NOTAM data found. Please generate NOTAM data first.';
                }
            } else {
                $error = 'No NOTAM data found. Please generate NOTAM data first.';
            }
        } else {
            $error = 'Flight not found.';
        }
    } else {
        $error = 'Invalid request.';
    }
}

// Handle WX Forecast file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_wx_forecast_file') {
    $flightId = !empty($_POST['flight_id']) ? (int)$_POST['flight_id'] : null;
    $fltDate = trim($_POST['flt_date'] ?? '');
    $filePath = trim($_POST['file_path'] ?? '');
    
    if ($flightId && $fltDate && $filePath) {
        $checkStmt = $db->prepare("SELECT id, wx_forecast_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
        $checkStmt->execute([$flightId, $fltDate]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing && !empty($existing['wx_forecast_data'])) {
            $files = json_decode($existing['wx_forecast_data'], true) ?: [];
            $updatedFiles = [];
            $fileDeleted = false;
            
            foreach ($files as $file) {
                if (($file['path'] ?? '') !== $filePath) {
                    $updatedFiles[] = $file;
                } else {
                    $fileDeleted = true;
                    $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filePath), DIRECTORY_SEPARATOR);
                    if (file_exists($absolutePath)) {
                        @unlink($absolutePath);
                    }
                }
            }
            
            if ($fileDeleted) {
                if (empty($updatedFiles)) {
                    $updateStmt = $db->prepare("UPDATE efb_records SET wx_forecast_data = NULL WHERE id = ?");
                    $updateStmt->execute([$existing['id']]);
                } else {
                    $updateStmt = $db->prepare("UPDATE efb_records SET wx_forecast_data = ? WHERE id = ?");
                    $updateStmt->execute([json_encode($updatedFiles, JSON_UNESCAPED_SLASHES), $existing['id']]);
                }
                $message = 'File deleted successfully.';
                header('Location: ?view_efb=1&flight_id=' . $flightId . '&flight_date=' . urlencode($fltDate) . '&msg=' . urlencode($message));
                exit;
            }
        }
    }
}

// Handle Manifest generation for GD or CL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_manifest') {
    $flightId = !empty($_POST['flight_id']) ? (int)$_POST['flight_id'] : null;
    $fltDate = trim($_POST['flt_date'] ?? '');
    
    if ($flightId && $fltDate) {
        // Get flight details
        $flightStmt = $db->prepare("SELECT FlightID, TaskName, TaskStart, TaskEnd, Route, FltDate, Rego FROM flights WHERE FlightID = ? AND DATE(FltDate) = ? LIMIT 1");
        $flightStmt->execute([$flightId, $fltDate]);
        $flight = $flightStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($flight) {
            // Get crew data for this flight
            $crewStmt = $db->prepare("
                SELECT 
                    f.Crew1, f.Crew2, f.Crew3, f.Crew4, f.Crew5,
                    f.Crew6, f.Crew7, f.Crew8, f.Crew9, f.Crew10,
                    f.Crew1_role, f.Crew2_role, f.Crew3_role, f.Crew4_role, f.Crew5_role,
                    f.Crew6_role, f.Crew7_role, f.Crew8_role, f.Crew9_role, f.Crew10_role
                FROM flights f
                WHERE f.FlightID = ? AND DATE(f.FltDate) = ?
            ");
            $crewStmt->execute([$flightId, $fltDate]);
            $crewData = $crewStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get crew member details
            $crewIds = [];
            for ($i = 1; $i <= 10; $i++) {
                if (!empty($crewData["Crew{$i}"])) {
                    $crewIds[] = $crewData["Crew{$i}"];
                }
            }
            
            $crewMembers = [];
            if (!empty($crewIds)) {
                $placeholders = str_repeat('?,', count($crewIds) - 1) . '?';
                $userStmt = $db->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name, national_id FROM users WHERE id IN ($placeholders)");
                $userStmt->execute($crewIds);
                $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($users as $user) {
                    // Find role for this crew member
                    $role = '';
                    for ($i = 1; $i <= 10; $i++) {
                        if ($crewData["Crew{$i}"] == $user['id']) {
                            $role = $crewData["Crew{$i}_role"] ?? '';
                            break;
                        }
                    }
                    
                    // Filter out TD role
                    if (strtoupper(trim($role)) !== 'TD') {
                        $crewMembers[] = [
                            'id' => $user['id'],
                            'name' => $user['name'],
                            'role' => $role,
                            'national_id' => $user['national_id'] ?? ''
                        ];
                    }
                }
            }
            
            // Sort crew by role priority
            $rolePriority = ['TRE' => 1, 'TRI' => 2, 'NC' => 3, 'PIC' => 4, 'DIC' => 5, 'FO' => 6, 'SP' => 7, 'OBS' => 8, 'CCE' => 9, 'CCI' => 10, 'SCC' => 11, 'CC' => 12];
            usort($crewMembers, function($a, $b) use ($rolePriority) {
                $roleA = strtoupper(trim($a['role'] ?? ''));
                $roleB = strtoupper(trim($b['role'] ?? ''));
                $priorityA = $rolePriority[$roleA] ?? 999;
                $priorityB = $rolePriority[$roleB] ?? 999;
                if ($priorityA === $priorityB) {
                    return strcmp($a['name'] ?? '', $b['name'] ?? '');
                }
                return $priorityA - $priorityB;
            });
            
            // Generate Manifest HTML (similar to crew_list.php)
            $dateFormatted = date('d/m/Y', strtotime($fltDate));
            $route = $flight['Route'] ?? '';
            $taskStart = $flight['TaskStart'] ?? '';
            $taskEnd = $flight['TaskEnd'] ?? '';
            $aircraftRego = $flight['Rego'] ?? 'N/A';
            $taskName = $flight['TaskName'] ?? '';
            
            // Format times
            $formatTime = function($dateTimeString) {
                if (!$dateTimeString) return '';
                try {
                    $date = new DateTime($dateTimeString);
                    return $date->format('H:i');
                } catch (Exception $e) {
                    return '';
                }
            };
            
            $scheduleDepTime = $formatTime($taskStart);
            $scheduleArrTime = $formatTime($taskEnd);
            
            // Get current user for dispatch
            $currentUser = getCurrentUser();
            $dispatchName = '';
            $dispatchLicense = '';
            if ($currentUser) {
                $dispatchName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
                $dispatchLicense = $currentUser['dispatch_license'] ?? '';
            }
            
            // Generate Manifest HTML
            ob_start();
            include __DIR__ . '/generate_manifest_html.php';
            $manifestHTML = ob_get_clean();
            
            // Save HTML to a temporary file and convert to PDF using a simple approach
            // For now, we'll save the HTML and use a client-side conversion
            // Or we can use a headless browser approach
            
            // Create directory structure
            $dateFolder = date('Y-m-d', strtotime($fltDate));
            $flightFolder = (string)$flightId;
            $uploadDir = $uploadBaseDir . DIRECTORY_SEPARATOR . $dateFolder . DIRECTORY_SEPARATOR . $flightFolder;
            
            // Ensure base directory exists first
            if (!is_dir($uploadBaseDir)) {
                if (!@mkdir($uploadBaseDir, 0775, true)) {
                    if (!@mkdir($uploadBaseDir, 0755, true)) {
                        $error = 'Failed to create base directory: ' . $uploadBaseDir;
                    }
                }
            }
            
            // Ensure base directory is writable
            if (is_dir($uploadBaseDir) && !is_writable($uploadBaseDir)) {
                @chmod($uploadBaseDir, 0775);
                if (!is_writable($uploadBaseDir)) {
                    @chmod($uploadBaseDir, 0755);
                }
            }
            
            // Now create the full directory structure
            if (empty($error) && !is_dir($uploadDir)) {
                if (!@mkdir($uploadDir, 0775, true)) {
                    if (!@mkdir($uploadDir, 0755, true)) {
                        $error = 'Failed to create upload directory: ' . $uploadDir;
                    }
                }
            }
            
            // Check if directory is writable
            if (empty($error) && is_dir($uploadDir) && !is_writable($uploadDir)) {
                @chmod($uploadDir, 0775);
                if (!is_writable($uploadDir)) {
                    @chmod($uploadDir, 0755);
                }
            }
            
            // Generate PDF file name
            $pdfFileName = 'manifest_' . $flightId . '_' . time() . '.pdf';
            $pdfFilePath = $uploadDir . DIRECTORY_SEPARATOR . $pdfFileName;
            $relativePath = 'admin/efb/' . $dateFolder . '/' . $flightFolder . '/' . $pdfFileName;
            
            // Generate PDF using mPDF
            $pdfGenerated = false;
            if (empty($error) && is_dir($uploadDir) && is_writable($uploadDir)) {
                try {
                    // Create mPDF instance (version 6.1 uses 'mPDF', version 8.x uses '\Mpdf\Mpdf')
                    $mpdfClass = class_exists('mPDF') ? 'mPDF' : '\Mpdf\Mpdf';
                    $mpdf = new $mpdfClass([
                        'mode' => 'utf-8',
                        'format' => 'A4',
                        'margin_left' => 5,
                        'margin_right' => 5,
                        'margin_top' => 5,
                        'margin_bottom' => 5,
                        'margin_header' => 0,
                        'margin_footer' => 0,
                        'tempDir' => sys_get_temp_dir()
                    ]);
                    
                    // Write HTML content
                    $mpdf->WriteHTML($manifestHTML);
                    
                    // Output PDF to file
                    $mpdf->Output($pdfFilePath, 'F');
                    
                    if (file_exists($pdfFilePath) && filesize($pdfFilePath) > 0) {
                        $pdfGenerated = true;
                    } else {
                        $error = 'PDF file was not created successfully.';
                    }
                } catch (\Exception $e) {
                    $error = 'PDF generation failed: ' . $e->getMessage();
                }
            } else {
                if (empty($error)) {
                    $error = 'Upload directory is not writable: ' . $uploadDir;
                }
            }
            
            // Only proceed if no errors occurred and PDF was generated
            if (empty($error) && $pdfGenerated && isset($pdfFilePath) && file_exists($pdfFilePath)) {
                // Save to database
                $fileData = [
                    'path' => $relativePath,
                    'original_name' => 'Manifest_' . $taskName . '_' . $dateFormatted . '.pdf',
                    'stored_name' => $pdfFileName,
                    'upload_time' => date('Y-m-d H:i:s'),
                    'size' => filesize($pdfFilePath),
                    'type' => 'pdf'
                ];
                
                // Find or create EFB record
                $checkStmt = $db->prepare("SELECT id, gd_cl_data FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
                $checkStmt->execute([$flightId, $fltDate]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $existingData = json_decode($existing['gd_cl_data'] ?? '[]', true) ?: [];
                    $existingData[] = $fileData;
                    $updateStmt = $db->prepare("UPDATE efb_records SET gd_cl_data = ? WHERE id = ?");
                    $updateStmt->execute([json_encode($existingData, JSON_UNESCAPED_SLASHES), $existing['id']]);
                } else {
                    $insertStmt = $db->prepare("INSERT INTO efb_records (flight_id, task_name, flt_date, gd_cl_data, created_by) VALUES (?, ?, ?, ?, ?)");
                    $insertStmt->execute([
                        $flightId,
                        $taskName,
                        $fltDate,
                        json_encode([$fileData], JSON_UNESCAPED_SLASHES),
                        $currentUser['id'] ?? null
                    ]);
                }
                
                $message = 'Manifest generated successfully.';
                header('Location: ?view_efb=1&flight_id=' . $flightId . '&flight_date=' . urlencode($fltDate) . '&msg=' . urlencode($message));
                exit;
            } elseif (!empty($error)) {
                // Error already set above
            } else {
                $error = 'PDF generation failed. Please check server configuration.';
            }
        } else {
            $error = 'Flight not found.';
        }
    } else {
        $error = 'Invalid request.';
    }
}

// Handle redirect message
if (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
}

// If viewing EFB, get flight details
$selectedFlight = null;
$efbRecord = null;
if ($viewEFB && $selectedFlightId && $selectedDate) {
    $flightStmt = $db->prepare("SELECT FlightID, TaskName, TaskStart, TaskEnd, Route, FltDate FROM flights WHERE FlightID = ? AND DATE(FltDate) = ? LIMIT 1");
    $flightStmt->execute([$selectedFlightId, $selectedDate]);
    $selectedFlight = $flightStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get EFB record if exists
    if ($selectedFlight) {
        // Ensure notam_data and notam_pdf_data columns exist before selecting
        $columnsToCheck = ['notam_data', 'notam_pdf_data'];
        foreach ($columnsToCheck as $column) {
            try {
                $checkColumnStmt = $db->query("SHOW COLUMNS FROM efb_records LIKE '{$column}'");
                $columnExists = $checkColumnStmt->fetch(PDO::FETCH_ASSOC);
                if (!$columnExists) {
                    $alterStmt = $db->prepare("ALTER TABLE efb_records ADD COLUMN {$column} TEXT");
                    $alterStmt->execute();
                }
            } catch (PDOException $e) {
                // Try to create column anyway
                try {
                    $alterStmt = $db->prepare("ALTER TABLE efb_records ADD COLUMN {$column} TEXT");
                    $alterStmt->execute();
                } catch (PDOException $e2) {
                    // Column might already exist, continue
                }
            }
        }
        
        $efbStmt = $db->prepare("SELECT * FROM efb_records WHERE flight_id = ? AND flt_date = ? LIMIT 1");
        $efbStmt->execute([$selectedFlightId, $selectedDate]);
        $efbRecord = $efbStmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Build query for flights list
$where = [];
$params = [];

if (!empty($filterDate)) {
    $where[] = "DATE(FltDate) = :date";
    $params[':date'] = $filterDate;
}

if (!empty($search)) {
    $where[] = "(TaskName LIKE :search OR Route LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get all flights (only if not viewing EFB)
if (!$viewEFB) {
    $sql = "SELECT 
                FlightID,
                TaskName,
                TaskStart,
                TaskEnd,
                Route,
                FltDate
            FROM flights 
            {$whereClause}
            ORDER BY FltDate DESC, TaskStart DESC
            LIMIT 500";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $flights = [];
}

// Helper function for safe output
function efb_safe($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EFB - Electronic Flight Bag - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { 
            font-family: 'Roboto', sans-serif; 
        }
        /* Minimal design with browser-based dark/light mode */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-primary: #111827;
                --bg-secondary: #1f2937;
                --text-primary: #f9fafb;
                --text-secondary: #d1d5db;
                --border-color: #374151;
                --card-bg: #1f2937;
            }
        }
        @media (prefers-color-scheme: light) {
            :root {
                --bg-primary: #ffffff;
                --bg-secondary: #f9fafb;
                --text-primary: #111827;
                --text-secondary: #6b7280;
                --border-color: #e5e7eb;
                --card-bg: #ffffff;
            }
        }
        body {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
        }
        .minimal-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }
        .minimal-text-primary {
            color: var(--text-primary);
        }
        .minimal-text-secondary {
            color: var(--text-secondary);
        }
        .minimal-input {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        .minimal-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .minimal-btn {
            background-color: #3b82f6;
            color: white;
            border: none;
        }
        .minimal-btn:hover {
            background-color: #2563eb;
        }
        .minimal-header {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
        }
        .minimal-table-header {
            background-color: var(--bg-secondary);
            color: var(--text-secondary);
        }
        .minimal-table-row {
            border-bottom: 1px solid var(--border-color);
        }
        .minimal-table-row:hover {
            background-color: var(--bg-secondary);
        }
    </style>
</head>
<body class="h-full">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="minimal-header px-6 py-4">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-briefcase text-xl minimal-text-primary"></i>
                    <h1 class="text-xl font-semibold minimal-text-primary">Electronic Flight Bag</h1>
                </div>
            </div>

            <div class="flex-1 p-6">
                <?php include '../../includes/permission_banner.php'; ?>

                <!-- Messages -->
                <?php if (!empty($message)): ?>
                <div class="mb-4 minimal-card p-3 border-l-2 border-green-500">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-check-circle text-green-500"></i>
                        <p class="text-sm minimal-text-primary"><?php echo efb_safe($message); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                <div class="mb-4 minimal-card p-3 border-l-2 border-red-500">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                        <p class="text-sm minimal-text-primary"><?php echo efb_safe($error); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filters Card -->
                <div class="minimal-card p-4 mb-4">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm minimal-text-secondary mb-1">Date</label>
                            <input type="date" name="filter_date" value="<?php echo efb_safe($filterDate); ?>" 
                                   class="w-full px-3 py-2 minimal-input rounded">
                        </div>
                        <div>
                            <label class="block text-sm minimal-text-secondary mb-1">Search</label>
                            <input type="text" name="search" value="<?php echo efb_safe($search); ?>" 
                                   placeholder="Task Name or Route..." 
                                   class="w-full px-3 py-2 minimal-input rounded">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" 
                                    class="w-full px-4 py-2 minimal-btn rounded">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Flights Table Card -->
                <div class="minimal-card">
                    <div class="px-4 py-3 border-b" style="border-color: var(--border-color);">
                        <h2 class="text-sm font-medium minimal-text-primary">
                            <?php echo count($flights); ?> flight(s)
                            <?php if (!empty($filterDate)): ?>
                                - <?php echo efb_safe($filterDate); ?>
                            <?php endif; ?>
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="minimal-table-header">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium minimal-text-secondary uppercase">
                                        ID
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium minimal-text-secondary uppercase">
                                        Task Name
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium minimal-text-secondary uppercase">
                                        Date
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium minimal-text-secondary uppercase">
                                        Start
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium minimal-text-secondary uppercase">
                                        End
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium minimal-text-secondary uppercase">
                                        Route
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium minimal-text-secondary uppercase">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($flights)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center">
                                        <p class="text-sm minimal-text-secondary">No flights found</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($flights as $flight): ?>
                                    <tr class="minimal-table-row">
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <span class="text-xs minimal-text-primary">
                                                #<?php echo efb_safe($flight['FlightID']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <span class="text-sm minimal-text-primary">
                                                <?php echo efb_safe($flight['TaskName']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm minimal-text-secondary">
                                            <?php echo efb_safe($flight['FltDate'] ? date('Y-m-d', strtotime($flight['FltDate'])) : 'N/A'); ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm minimal-text-secondary">
                                            <?php echo efb_safe($flight['TaskStart'] ? date('H:i', strtotime($flight['TaskStart'])) : 'N/A'); ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm minimal-text-secondary">
                                            <?php echo efb_safe($flight['TaskEnd'] ? date('H:i', strtotime($flight['TaskEnd'])) : 'N/A'); ?>
                                        </td>
                                        <td class="px-4 py-2 text-sm minimal-text-secondary">
                                            <?php echo efb_safe($flight['Route'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <a href="?view_efb=1&flight_id=<?php echo (int)$flight['FlightID']; ?>&flight_date=<?php echo urlencode(date('Y-m-d', strtotime($flight['FltDate']))); ?>" 
                                               class="text-xs px-3 py-1 minimal-btn rounded">
                                                EFB
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- EFB Management Section -->
                <?php if ($viewEFB && $selectedFlight): ?>
                <div class="mt-6 bg-white dark:bg-gray-800 shadow-lg rounded-xl border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg mr-3">
                                    <i class="fas fa-briefcase text-purple-600 dark:text-purple-400"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">EFB Management</h2>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Flight: <?php echo efb_safe($selectedFlight['TaskName']); ?> - 
                                        Date: <?php echo efb_safe(date('Y-m-d', strtotime($selectedFlight['FltDate']))); ?>
                                    </p>
                                </div>
                            </div>
                            <a href="?" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 
                                              text-gray-700 dark:text-gray-300 rounded-lg transition-all duration-200 flex items-center">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to List
                            </a>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- ATS / RPL Card -->
                            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 
                                        border-2 border-blue-200 dark:border-blue-800 rounded-xl p-6 shadow-md hover:shadow-lg transition-all duration-200">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 bg-blue-500 rounded-lg mr-3">
                                        <i class="fas fa-file-alt text-white text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">ATS / RPL</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Air Traffic Services / Repetitive Flight Plan</p>
                                
                                <!-- Upload Form -->
                                <form method="POST" enctype="multipart/form-data" class="mt-4">
                                    <input type="hidden" name="action" value="upload_ats_rpl">
                                    <input type="hidden" name="flight_id" value="<?php echo (int)$selectedFlightId; ?>">
                                    <input type="hidden" name="task_name" value="<?php echo efb_safe($selectedFlight['TaskName'] ?? ''); ?>">
                                    <input type="hidden" name="flt_date" value="<?php echo efb_safe($selectedDate); ?>">
                                    
                                    <label class="block border-2 border-dashed border-blue-300 dark:border-blue-600 rounded-lg p-4 cursor-pointer hover:border-blue-500 dark:hover:border-blue-400 transition-colors duration-200">
                                        <div class="flex flex-col items-center justify-center text-center">
                                            <i class="fas fa-cloud-upload-alt text-blue-500 text-2xl mb-2"></i>
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Upload PDF File</span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Click to browse</span>
                                        </div>
                                        <input type="file" name="ats_rpl_file" accept=".pdf,application/pdf" class="hidden" onchange="this.form.submit()">
                                    </label>
                                </form>
                                
                                <!-- Display uploaded files -->
                                <?php 
                                $atsRplFiles = [];
                                if ($efbRecord && !empty($efbRecord['ats_rpl_data'])) {
                                    $atsRplFiles = json_decode($efbRecord['ats_rpl_data'], true) ?: [];
                                }
                                ?>
                                <?php if (!empty($atsRplFiles)): ?>
                                <div class="mt-4 space-y-2">
                                    <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Uploaded Files:</div>
                                    <?php foreach ($atsRplFiles as $file): ?>
                                    <div class="flex items-center justify-between bg-white dark:bg-gray-700 p-2 rounded border border-gray-200 dark:border-gray-600">
                                        <div class="flex items-center flex-1 min-w-0">
                                            <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                                            <span class="text-xs text-gray-700 dark:text-gray-300 truncate"><?php echo efb_safe($file['original_name'] ?? basename($file['path'])); ?></span>
                                        </div>
                                        <div class="flex items-center space-x-2 ml-2">
                                            <a href="<?php echo efb_safe(base_url() . ltrim($file['path'], '/')); ?>" target="_blank" 
                                               class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" 
                                               title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                                <input type="hidden" name="action" value="delete_ats_rpl_file">
                                                <input type="hidden" name="flight_id" value="<?php echo (int)$selectedFlightId; ?>">
                                                <input type="hidden" name="flt_date" value="<?php echo efb_safe($selectedDate); ?>">
                                                <input type="hidden" name="file_path" value="<?php echo efb_safe($file['path']); ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- NOTAM's Card -->
                            <div class="bg-gradient-to-br from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 
                                        border-2 border-orange-200 dark:border-orange-800 rounded-xl p-6 shadow-md hover:shadow-lg transition-all duration-200">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 bg-orange-500 rounded-lg mr-3">
                                        <i class="fas fa-bullhorn text-white text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">NOTAM's</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Notices to Airmen</p>
                                
                                <!-- Generate NOTAM Button -->
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="action" value="generate_notam">
                                    <input type="hidden" name="flight_id" value="<?php echo (int)$selectedFlightId; ?>">
                                    <input type="hidden" name="flt_date" value="<?php echo efb_safe($selectedDate); ?>">
                                    
                                    <button type="submit" 
                                            class="w-full px-4 py-2.5 bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 
                                                   text-white font-medium rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 
                                                   transition-all duration-200 flex items-center justify-center">
                                        <i class="fas fa-bullhorn mr-2"></i>
                                        Generate Notam
                                    </button>
                                </form>
                                
                                <!-- Display NOTAM data -->
                                <?php 
                                $notamRecord = null;
                                if ($efbRecord && !empty($efbRecord['notam_data'])) {
                                    $notamRecord = json_decode($efbRecord['notam_data'], true);
                                }
                                
                                $notamPdfFiles = [];
                                if ($efbRecord && !empty($efbRecord['notam_pdf_data'])) {
                                    $notamPdfFiles = json_decode($efbRecord['notam_pdf_data'], true) ?: [];
                                }
                                ?>
                                
                                <?php if ($notamRecord && !empty($notamRecord['notam_data'])): ?>
                                <!-- Generate PDF Button -->
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="action" value="generate_notam_pdf">
                                    <input type="hidden" name="flight_id" value="<?php echo (int)$selectedFlightId; ?>">
                                    <input type="hidden" name="flt_date" value="<?php echo efb_safe($selectedDate); ?>">
                                    
                                    <button type="submit" 
                                            class="w-full px-4 py-2.5 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 
                                                   text-white font-medium rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 
                                                   transition-all duration-200 flex items-center justify-center">
                                        <i class="fas fa-file-pdf mr-2"></i>
                                        Generate NOTAM PDF
                                    </button>
                                </form>
                                
                                <!-- Display generated PDF files -->
                                <?php if (!empty($notamPdfFiles)): ?>
                                <div class="mt-4 space-y-2">
                                    <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Generated PDF Files:</div>
                                    <?php foreach ($notamPdfFiles as $file): ?>
                                    <div class="flex items-center justify-between bg-white dark:bg-gray-700 p-2 rounded border border-gray-200 dark:border-gray-600">
                                        <div class="flex items-center flex-1 min-w-0">
                                            <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                                            <span class="text-xs text-gray-700 dark:text-gray-300 truncate"><?php echo efb_safe($file['original_name'] ?? basename($file['path'])); ?></span>
                                        </div>
                                        <div class="flex items-center space-x-2 ml-2">
                                            <a href="<?php echo efb_safe(base_url() . ltrim($file['path'], '/')); ?>" target="_blank" 
                                               class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" 
                                               title="View/Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php endif; ?>
                            </div>

                            <!-- GD or CL Card -->
                            <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 
                                        border-2 border-green-200 dark:border-green-800 rounded-xl p-6 shadow-md hover:shadow-lg transition-all duration-200">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 bg-green-500 rounded-lg mr-3">
                                        <i class="fas fa-clipboard-check text-white text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">GD or CL</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">General Declaration or Cargo List</p>
                                
                                <!-- Generate Manifest Button -->
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="action" value="generate_manifest">
                                    <input type="hidden" name="flight_id" value="<?php echo (int)$selectedFlightId; ?>">
                                    <input type="hidden" name="flt_date" value="<?php echo efb_safe($selectedDate); ?>">
                                    
                                    <button type="submit" 
                                            class="w-full px-4 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 
                                                   text-white font-medium rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 
                                                   transition-all duration-200 flex items-center justify-center">
                                        <i class="fas fa-file-pdf mr-2"></i>
                                        Generate Manifest
                                    </button>
                                </form>
                                
                                <!-- Display generated files -->
                                <?php 
                                $gdClFiles = [];
                                if ($efbRecord && !empty($efbRecord['gd_cl_data'])) {
                                    $gdClFiles = json_decode($efbRecord['gd_cl_data'], true) ?: [];
                                }
                                ?>
                                <?php if (!empty($gdClFiles)): ?>
                                <div class="mt-4 space-y-2">
                                    <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Generated Files:</div>
                                    <?php foreach ($gdClFiles as $file): ?>
                                    <div class="flex items-center justify-between bg-white dark:bg-gray-700 p-2 rounded border border-gray-200 dark:border-gray-600">
                                        <div class="flex items-center flex-1 min-w-0">
                                            <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                                            <span class="text-xs text-gray-700 dark:text-gray-300 truncate"><?php echo efb_safe($file['original_name'] ?? basename($file['path'])); ?></span>
                                        </div>
                                        <div class="flex items-center space-x-2 ml-2">
                                            <a href="<?php echo efb_safe(base_url() . ltrim($file['path'], '/')); ?>" target="_blank" 
                                               class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" 
                                               title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (($file['type'] ?? '') === 'html'): ?>
                                            <button onclick="convertHtmlToPdf('<?php echo efb_safe(base_url() . ltrim($file['path'], '/')); ?>', '<?php echo efb_safe(str_replace('.html', '.pdf', $file['original_name'] ?? 'manifest.pdf')); ?>')" 
                                                    class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300" 
                                                    title="Convert to PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </button>
                                            <?php endif; ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                                <input type="hidden" name="action" value="delete_gd_cl_file">
                                                <input type="hidden" name="flight_id" value="<?php echo (int)$selectedFlightId; ?>">
                                                <input type="hidden" name="flt_date" value="<?php echo efb_safe($selectedDate); ?>">
                                                <input type="hidden" name="file_path" value="<?php echo efb_safe($file['path']); ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Permissions Card -->
                            <div class="bg-gradient-to-br from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 
                                        border-2 border-purple-200 dark:border-purple-800 rounded-xl p-6 shadow-md hover:shadow-lg transition-all duration-200">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 bg-purple-500 rounded-lg mr-3">
                                        <i class="fas fa-id-badge text-white text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Permissions</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Flight Permissions and Authorizations</p>
                                <div class="text-xs text-gray-500 dark:text-gray-500 italic">Configuration pending...</div>
                            </div>

                            <!-- Actual WX Card -->
                            <div class="bg-gradient-to-br from-cyan-50 to-blue-50 dark:from-cyan-900/20 dark:to-blue-900/20 
                                        border-2 border-cyan-200 dark:border-cyan-800 rounded-xl p-6 shadow-md hover:shadow-lg transition-all duration-200">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 bg-cyan-500 rounded-lg mr-3">
                                        <i class="fas fa-cloud-sun text-white text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Actual WX</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Actual Weather Conditions (METAR)</p>
                                
                                <!-- Generate METAR Button -->
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="action" value="generate_actual_wx">
                                    <input type="hidden" name="flight_id" value="<?php echo (int)$selectedFlightId; ?>">
                                    <input type="hidden" name="flt_date" value="<?php echo efb_safe($selectedDate); ?>">
                                    
                                    <button type="submit" 
                                            class="w-full px-4 py-2.5 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 
                                                   text-white font-medium rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 
                                                   transition-all duration-200 flex items-center justify-center">
                                        <i class="fas fa-cloud-sun mr-2"></i>
                                        Generate METAR Report
                                    </button>
                                </form>
                                
                                <!-- Display generated files -->
                                <?php 
                                $actualWxFiles = [];
                                if ($efbRecord && !empty($efbRecord['actual_wx_data'])) {
                                    $actualWxFiles = json_decode($efbRecord['actual_wx_data'], true) ?: [];
                                }
                                ?>
                                <?php if (!empty($actualWxFiles)): ?>
                                <div class="mt-4 space-y-2">
                                    <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Generated Reports:</div>
                                    <?php foreach ($actualWxFiles as $file): ?>
                                    <div class="flex items-center justify-between bg-white dark:bg-gray-700 p-2 rounded border border-gray-200 dark:border-gray-600">
                                        <div class="flex items-center flex-1 min-w-0">
                                            <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                                            <span class="text-xs text-gray-700 dark:text-gray-300 truncate"><?php echo efb_safe($file['original_name'] ?? basename($file['path'])); ?></span>
                                        </div>
                                        <div class="flex items-center space-x-2 ml-2">
                                            <a href="<?php echo efb_safe(base_url() . ltrim($file['path'], '/')); ?>" target="_blank" 
                                               class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" 
                                               title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (($file['type'] ?? '') === 'html' || ($file['needs_conversion'] ?? false)): ?>
                                            <button onclick="convertMetarHtmlToPdf('<?php echo efb_safe(base_url() . ltrim($file['path'], '/')); ?>', '<?php echo efb_safe(str_replace('.html', '.pdf', $file['original_name'] ?? 'metar.pdf')); ?>')" 
                                                    class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300" 
                                                    title="Convert to PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </button>
                                            <?php endif; ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                                <input type="hidden" name="action" value="delete_actual_wx_file">
                                                <input type="hidden" name="flight_id" value="<?php echo (int)$selectedFlightId; ?>">
                                                <input type="hidden" name="flt_date" value="<?php echo efb_safe($selectedDate); ?>">
                                                <input type="hidden" name="file_path" value="<?php echo efb_safe($file['path']); ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- WX Forecast Card -->
                            <div class="bg-gradient-to-br from-sky-50 to-cyan-50 dark:from-sky-900/20 dark:to-cyan-900/20 
                                        border-2 border-sky-200 dark:border-sky-800 rounded-xl p-6 shadow-md hover:shadow-lg transition-all duration-200">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 bg-sky-500 rounded-lg mr-3">
                                        <i class="fas fa-cloud text-white text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">WX Forecast</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Weather Forecast (TAF)</p>
                                
                                <!-- Generate TAF Button -->
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="action" value="generate_wx_forecast">
                                    <input type="hidden" name="flight_id" value="<?php echo (int)$selectedFlightId; ?>">
                                    <input type="hidden" name="flt_date" value="<?php echo efb_safe($selectedDate); ?>">
                                    
                                    <button type="submit" 
                                            class="w-full px-4 py-2.5 bg-gradient-to-r from-sky-600 to-cyan-600 hover:from-sky-700 hover:to-cyan-700 
                                                   text-white font-medium rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 
                                                   transition-all duration-200 flex items-center justify-center">
                                        <i class="fas fa-cloud mr-2"></i>
                                        Generate TAF Report
                                    </button>
                                </form>
                                
                                <!-- Display generated files -->
                                <?php 
                                $wxForecastFiles = [];
                                if ($efbRecord && !empty($efbRecord['wx_forecast_data'])) {
                                    $wxForecastFiles = json_decode($efbRecord['wx_forecast_data'], true) ?: [];
                                }
                                ?>
                                <?php if (!empty($wxForecastFiles)): ?>
                                <div class="mt-4 space-y-2">
                                    <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Generated Reports:</div>
                                    <?php foreach ($wxForecastFiles as $file): ?>
                                    <div class="flex items-center justify-between bg-white dark:bg-gray-700 p-2 rounded border border-gray-200 dark:border-gray-600">
                                        <div class="flex items-center flex-1 min-w-0">
                                            <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                                            <span class="text-xs text-gray-700 dark:text-gray-300 truncate"><?php echo efb_safe($file['original_name'] ?? basename($file['path'])); ?></span>
                                        </div>
                                        <div class="flex items-center space-x-2 ml-2">
                                            <a href="<?php echo efb_safe(base_url() . ltrim($file['path'], '/')); ?>" target="_blank" 
                                               class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" 
                                               title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                                <input type="hidden" name="action" value="delete_wx_forecast_file">
                                                <input type="hidden" name="flight_id" value="<?php echo (int)$selectedFlightId; ?>">
                                                <input type="hidden" name="flt_date" value="<?php echo efb_safe($selectedDate); ?>">
                                                <input type="hidden" name="file_path" value="<?php echo efb_safe($file['path']); ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Significant WX Card -->
                            <div class="bg-gradient-to-br from-yellow-50 to-amber-50 dark:from-yellow-900/20 dark:to-amber-900/20 
                                        border-2 border-yellow-200 dark:border-yellow-800 rounded-xl p-6 shadow-md hover:shadow-lg transition-all duration-200">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 bg-yellow-500 rounded-lg mr-3">
                                        <i class="fas fa-bolt text-white text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Significant WX</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Significant Weather Information</p>
                                <div class="text-xs text-gray-500 dark:text-gray-500 italic">Configuration pending...</div>
                            </div>

                            <!-- Wind & Temp Chart Card -->
                            <div class="bg-gradient-to-br from-teal-50 to-green-50 dark:from-teal-900/20 dark:to-green-900/20 
                                        border-2 border-teal-200 dark:border-teal-800 rounded-xl p-6 shadow-md hover:shadow-lg transition-all duration-200">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 bg-teal-500 rounded-lg mr-3">
                                        <i class="fas fa-wind text-white text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Wind & Temp Chart</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Wind and Temperature Charts</p>
                                <div class="text-xs text-gray-500 dark:text-gray-500 italic">Configuration pending...</div>
                            </div>

                            <!-- Others Card -->
                            <div class="bg-gradient-to-br from-gray-50 to-slate-50 dark:from-gray-900/20 dark:to-slate-900/20 
                                        border-2 border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-md hover:shadow-lg transition-all duration-200">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 bg-gray-500 rounded-lg mr-3">
                                        <i class="fas fa-folder-open text-white text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Others</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Other Documents</p>
                                <div class="text-xs text-gray-500 dark:text-gray-500 italic">Configuration pending...</div>
                            </div>

                            <!-- Journey Log Card -->
                            <div class="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 
                                        border-2 border-indigo-200 dark:border-indigo-800 rounded-xl p-6 shadow-md hover:shadow-lg transition-all duration-200">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 bg-indigo-500 rounded-lg mr-3">
                                        <i class="fas fa-book text-white text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Journey Log</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Flight Journey Log</p>
                                <div class="text-xs text-gray-500 dark:text-gray-500 italic">Configuration pending...</div>
                            </div>

                            <!-- OFP Card -->
                            <div class="bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 
                                        border-2 border-red-200 dark:border-red-800 rounded-xl p-6 shadow-md hover:shadow-lg transition-all duration-200">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 bg-red-500 rounded-lg mr-3">
                                        <i class="fas fa-file-pdf text-white text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">OFP</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Operational Flight Plan</p>
                                
                                <!-- Load OFP Button -->
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="action" value="load_ofp">
                                    <input type="hidden" name="flight_id" value="<?php echo (int)$selectedFlightId; ?>">
                                    <input type="hidden" name="flt_date" value="<?php echo efb_safe($selectedDate); ?>">
                                    
                                    <button type="submit" 
                                            class="w-full px-4 py-2.5 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 
                                                   text-white font-medium rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 
                                                   transition-all duration-200 flex items-center justify-center">
                                        <i class="fas fa-download mr-2"></i>
                                        Load OFP Data
                                    </button>
                                </form>
                                
                                <!-- Display loaded OFP data -->
                                <?php 
                                $ofpFiles = [];
                                if ($efbRecord && !empty($efbRecord['ofp_data'])) {
                                    $ofpFiles = json_decode($efbRecord['ofp_data'], true) ?: [];
                                }
                                ?>
                                <?php if (!empty($ofpFiles)): ?>
                                <div class="mt-4 space-y-2">
                                    <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Loaded OFP Data:</div>
                                    <?php foreach ($ofpFiles as $index => $ofp): ?>
                                    <div class="flex items-center justify-between bg-white dark:bg-gray-700 p-2 rounded border border-gray-200 dark:border-gray-600">
                                        <div class="flex items-center flex-1 min-w-0">
                                            <i class="fas fa-file-code text-red-500 mr-2"></i>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs text-gray-700 dark:text-gray-300 truncate">
                                                    OFP Data <?php echo ($index + 1); ?>
                                                    <?php if (!empty($ofp['route'])): ?>
                                                        - <?php echo efb_safe($ofp['route']); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($ofp['timestamp'])): ?>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        <?php echo efb_safe($ofp['timestamp']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2 ml-2">
                                            <button onclick="viewOFPData(<?php echo $index; ?>)" 
                                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" 
                                                    title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this OFP data?');">
                                                <input type="hidden" name="action" value="delete_ofp_data">
                                                <input type="hidden" name="flight_id" value="<?php echo (int)$selectedFlightId; ?>">
                                                <input type="hidden" name="flt_date" value="<?php echo efb_safe($selectedDate); ?>">
                                                <input type="hidden" name="ofp_index" value="<?php echo $index; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- OFP Data Modal -->
                                <div id="ofpModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                                        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">OFP Raw Data</h3>
                                            <button onclick="closeOFPModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                <i class="fas fa-times text-xl"></i>
                                            </button>
                                        </div>
                                        <div class="p-4 overflow-auto flex-1">
                                            <pre id="ofpDataContent" class="text-xs bg-gray-50 dark:bg-gray-900 p-4 rounded border border-gray-200 dark:border-gray-700 whitespace-pre-wrap break-words font-mono text-gray-800 dark:text-gray-200"></pre>
                                        </div>
                                    </div>
                                </div>
                                
                                <script>
                                const ofpDataList = <?php echo json_encode($ofpFiles, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
                                
                                function viewOFPData(index) {
                                    if (ofpDataList[index] && ofpDataList[index].raw_data) {
                                        document.getElementById('ofpDataContent').textContent = ofpDataList[index].raw_data;
                                        document.getElementById('ofpModal').classList.remove('hidden');
                                    }
                                }
                                
                                function closeOFPModal() {
                                    document.getElementById('ofpModal').classList.add('hidden');
                                }
                                
                                // Close modal on outside click
                                document.getElementById('ofpModal').addEventListener('click', function(e) {
                                    if (e.target === this) {
                                        closeOFPModal();
                                    }
                                });
                                </script>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- html2pdf.js library for client-side PDF conversion -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <script>
        // Function to convert METAR HTML to PDF on client side
        function convertMetarHtmlToPdf(htmlUrl, filename) {
            // Show loading message
            const loadingMsg = document.createElement('div');
            loadingMsg.id = 'pdf-loading';
            loadingMsg.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            loadingMsg.innerHTML = '<div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg"><div class="flex items-center"><i class="fas fa-spinner fa-spin text-2xl text-blue-600 mr-3"></i><span class="text-gray-700 dark:text-gray-300">Converting to PDF...</span></div></div>';
            document.body.appendChild(loadingMsg);
            
            // Fetch HTML content
            fetch(htmlUrl)
                .then(response => response.text())
                .then(html => {
                    // Create a temporary container
                    const container = document.createElement('div');
                    container.style.position = 'absolute';
                    container.style.left = '-9999px';
                    container.innerHTML = html;
                    document.body.appendChild(container);
                    
                    // Get the body content (for METAR, we want the whole page)
                    const bodyContent = container.querySelector('body') || container;
                    
                    // Configure PDF options
                    const opt = {
                        margin: [0.5, 0.5, 0.5, 0.5],
                        filename: filename,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { 
                            scale: 2, 
                            useCORS: true, 
                            logging: false,
                            backgroundColor: '#ffffff'
                        },
                        jsPDF: { unit: 'cm', format: 'a4', orientation: 'portrait' }
                    };
                    
                    // Generate PDF from the entire body content
                    html2pdf().set(opt).from(bodyContent).save().then(() => {
                        document.body.removeChild(loadingMsg);
                        document.body.removeChild(container);
                    }).catch(err => {
                        console.error('PDF generation error:', err);
                        alert('Error generating PDF: ' + err.message);
                        document.body.removeChild(loadingMsg);
                        document.body.removeChild(container);
                    });
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    alert('Error loading HTML file: ' + err.message);
                    document.body.removeChild(loadingMsg);
                });
        }
    </script>
</body>
</html>
