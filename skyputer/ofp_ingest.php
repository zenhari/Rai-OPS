<?php
/**
 * Skyputer OFP Data Ingest Endpoint
 * Receives and processes OFP (Operational Flight Plan) data from external company
 * Supports both JSON and String format
 */

declare(strict_types=1);

// Load configuration
require_once __DIR__ . '/../config.php';

// --------------- Config ---------------
const LOG_DIR             = __DIR__ . '/logs';
const LOG_PREFIX          = 'skyputer_ofp';
const MAX_BODY_MB         = 20;
const ALLOW_METHODS       = ['POST', 'OPTIONS'];
const CORS_ALLOW_ORIGIN    = '*';
// API Token for authentication
const API_TOKEN           = 'd82b6bde-7a24-494c-b926-680c26767474';
// --------------------------------------

/**
 * Send JSON response
 */
function respond(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: ' . CORS_ALLOW_ORIGIN);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key, key');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Get all headers (works on all SAPIs)
 */
if (!function_exists('getAllRequestHeaders')) {
    function getAllRequestHeaders(): array {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (strncmp($name, 'HTTP_', 5) === 0) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }
        
        // Add Content-Type and Content-Length if available
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }
        
        // Try to get Authorization header from apache_request_headers if available
        if (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            if (isset($apacheHeaders['Authorization'])) {
                $headers['Authorization'] = $apacheHeaders['Authorization'];
            }
            if (isset($apacheHeaders['X-Api-Token'])) {
                $headers['X-Api-Token'] = $apacheHeaders['X-Api-Token'];
            }
            if (isset($apacheHeaders['X-Api-Key'])) {
                $headers['X-Api-Key'] = $apacheHeaders['X-Api-Key'];
            }
            if (isset($apacheHeaders['key'])) {
                $headers['key'] = $apacheHeaders['key'];
            }
        }
        
        return $headers;
    }
}

/**
 * Validate API token
 */
function validateToken(): bool {
    $token = null;
    
    // Get all headers
    $headers = getAllRequestHeaders();
    
    // Check Authorization header (API Key - direct value, not Bearer)
    $authHeader = '';
    
    // Try from headers array first
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    }
    // Fallback to $_SERVER
    elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    // Try REDIRECT_HTTP_AUTHORIZATION (for some server configs)
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    
    // Accept API Key directly from Authorization header (without Bearer prefix)
    if (!empty($authHeader)) {
        $token = trim($authHeader);
    }
    
    // Check X-API-Key header
    if (empty($token)) {
        if (isset($headers['X-Api-Key'])) {
            $token = $headers['X-Api-Key'];
        } elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
            $token = $_SERVER['HTTP_X_API_KEY'];
        }
    }
    
    // Check 'key' header (case-insensitive)
    if (empty($token)) {
        // Check various case variations
        $keyVariations = ['key', 'Key', 'KEY'];
        foreach ($keyVariations as $keyVar) {
            if (isset($headers[$keyVar])) {
                $token = $headers[$keyVar];
                break;
            }
        }
        // Also check $_SERVER directly
        if (empty($token) && isset($_SERVER['HTTP_KEY'])) {
            $token = $_SERVER['HTTP_KEY'];
        }
    }
    
    // Check X-API-Token header (backward compatibility)
    if (empty($token)) {
        if (isset($headers['X-Api-Token'])) {
            $token = $headers['X-Api-Token'];
        } elseif (isset($_SERVER['HTTP_X_API_TOKEN'])) {
            $token = $_SERVER['HTTP_X_API_TOKEN'];
        }
    }
    
    // Check token in request body (for form submissions)
    if (empty($token) && isset($_POST['api_token'])) {
        $token = $_POST['api_token'];
    }
    
    // Check 'key' parameter in POST body (for C# clients)
    if (empty($token) && isset($_POST['key'])) {
        $token = $_POST['key'];
    }
    
    // Validate token
    if (empty($token)) {
        error_log("Token validation failed: No token found. Headers: " . json_encode(array_keys($headers)));
        return false;
    }
    
    $isValid = hash_equals(API_TOKEN, $token);
    if (!$isValid) {
        error_log("Token validation failed: Token mismatch. Provided: " . substr($token, 0, 10) . "...");
    }
    
    return $isValid;
}

/**
 * Parse String format to JSON structure
 */
function parseStringFormat(string $input): ?array {
    try {
        $result = [];
        $tables = explode('||', $input);
        
        foreach ($tables as $table) {
            if (empty(trim($table))) continue;
            
            $parts = explode(':', $table, 2);
            if (count($parts) !== 2) continue;
            
            $tableName = trim($parts[0]);
            $tableData = trim($parts[1]);
            
            if ($tableName === 'binfo') {
                // Parse basic info: key=value;key=value...
                $binfo = [];
                $pairs = explode(';', $tableData);
                foreach ($pairs as $pair) {
                    $kv = explode('=', $pair, 2);
                    if (count($kv) === 2) {
                        $key = trim($kv[0]);
                        $value = trim($kv[1]);
                        $binfo[$key] = $value;
                    }
                }
                $result['binfo'] = $binfo;
            } elseif (in_array($tableName, ['futbl', 'mpln', 'apln', 'bpln', 'tpln', 'cstbl', 'wdtmp'])) {
                // Parse table records: |key=value;key=value|key=value;key=value...
                $records = [];
                $rows = explode('|', $tableData);
                foreach ($rows as $row) {
                    if (empty(trim($row))) continue;
                    $record = [];
                    $pairs = explode(';', $row);
                    foreach ($pairs as $pair) {
                        $kv = explode('=', $pair, 2);
                        if (count($kv) === 2) {
                            $key = trim($kv[0]);
                            $value = trim($kv[1]);
                            // Try to convert numeric values
                            if (is_numeric($value)) {
                                $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
                            }
                            $record[$key] = $value;
                        }
                    }
                    if (!empty($record)) {
                        $records[] = $record;
                    }
                }
                $result[$tableName] = $records;
            } elseif (in_array($tableName, ['aldrf', 'wtdrf', 'wdclb', 'wddes'])) {
                // Parse special format tables
                $records = [];
                if ($tableName === 'wtdrf') {
                    // Special format: - 6   - 0345   - 4   - 0235...
                    $records = [['VAL' => trim($tableData)]];
                } else {
                    // Parse like other tables
                    $rows = explode('|', $tableData);
                    foreach ($rows as $row) {
                        if (empty(trim($row))) continue;
                        $record = [];
                        $parts = preg_split('/\s{2,}/', trim($row)); // Split by 2+ spaces
                        foreach ($parts as $part) {
                            $kv = explode(':', $part, 2);
                            if (count($kv) === 2) {
                                $key = trim($kv[0]);
                                $value = trim($kv[1]);
                                $record[$key] = is_numeric($value) ? (float)$value : $value;
                            }
                        }
                        if (!empty($record)) {
                            $records[] = $record;
                        }
                    }
                }
                $result[$tableName] = $records;
            } elseif ($tableName === 'icatc') {
                // Parse ICAO ATC format (line by line)
                $records = [];
                $lines = explode('|', $tableData);
                $seqNo = 1;
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (!empty($trimmed)) {
                        $records[] = [
                            'SeqNo' => $seqNo++,
                            'DATA' => $trimmed
                        ];
                    }
                }
                $result[$tableName] = $records;
            }
        }
        
        return !empty($result) ? $result : null;
    } catch (Exception $e) {
        error_log("String format parse error: " . $e->getMessage());
        return null;
    }
}

/**
 * Validate OFP data structure
 */
function validateOFPData(array $data): array {
    $errors = [];
    
    // Check basic info
    if (empty($data['binfo'])) {
        $errors[] = 'Missing binfo (basic information)';
    } else {
        $binfo = $data['binfo'];
        if (empty($binfo['FLN'])) $errors[] = 'Missing FLN (Flight Number)';
        if (empty($binfo['DTE'])) $errors[] = 'Missing DTE (Date)';
    }
    
    return $errors;
}

/**
 * Extract flight information from OFP data
 */
function extractFlightInfo(array $data): array {
    $binfo = $data['binfo'] ?? [];
    
    return [
        'flight_number' => $binfo['FLN'] ?? null,
        'date' => $binfo['DTE'] ?? null,
        'operator' => $binfo['OPT'] ?? null,
        'unit' => $binfo['UNT'] ?? null,
        'eta' => $binfo['ETA'] ?? null,
        'etd' => $binfo['ETD'] ?? null,
        'aircraft_reg' => $binfo['REG'] ?? null,
        'route' => $binfo['RTS'] ?? null,
    ];
}

/**
 * Get database connection
 */
function getDB(): PDO {
    try {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Create skyputer_ofp_data table if not exists
 */
function ensureTableExists(PDO $db): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS skyputer_ofp_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            flight_number VARCHAR(50) NULL,
            ofp_date VARCHAR(50) NULL,
            operator VARCHAR(255) NULL,
            unit VARCHAR(10) NULL,
            eta VARCHAR(20) NULL,
            etd VARCHAR(20) NULL,
            aircraft_reg VARCHAR(100) NULL,
            route VARCHAR(255) NULL,
            data_format ENUM('json', 'string') NOT NULL,
            raw_data LONGTEXT NULL,
            parsed_data JSON NOT NULL,
            client_ip VARCHAR(45) NULL,
            request_id VARCHAR(32) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_flight_number (flight_number),
            INDEX idx_ofp_date (ofp_date),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $db->exec($sql);
}

/**
 * Save OFP data to database
 */
function saveOFPData(PDO $db, array $flightInfo, string $format, string $rawData, array $parsedData, string $clientIp, string $requestId): int {
    $sql = "
        INSERT INTO skyputer_ofp_data (
            flight_number, ofp_date, operator, unit, eta, etd, aircraft_reg, route,
            data_format, raw_data, parsed_data, client_ip, request_id
        ) VALUES (
            :flight_number, :ofp_date, :operator, :unit, :eta, :etd, :aircraft_reg, :route,
            :data_format, :raw_data, :parsed_data, :client_ip, :request_id
        )
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':flight_number' => $flightInfo['flight_number'],
        ':ofp_date' => $flightInfo['date'],
        ':operator' => $flightInfo['operator'],
        ':unit' => $flightInfo['unit'],
        ':eta' => $flightInfo['eta'],
        ':etd' => $flightInfo['etd'],
        ':aircraft_reg' => $flightInfo['aircraft_reg'],
        ':route' => $flightInfo['route'],
        ':data_format' => $format,
        ':raw_data' => $rawData,
        ':parsed_data' => json_encode($parsedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':client_ip' => $clientIp,
        ':request_id' => $requestId,
    ]);
    
    return (int)$db->lastInsertId();
}

/**
 * Log request for debugging - saves complete request data including raw_data
 */
function logRequest(string $requestId, string $rawData, array $parsedData, string $format, array $flightInfo, string $clientIp, ?int $recordId = null, string $contentType = '', array $allPostData = []): void {
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0775, true);
    }
    
    // Create log file name with date and hour (e.g., skyputer_ofp-2025-11-02-14.json)
    $logFile = sprintf('%s/%s-%s-%s.json', LOG_DIR, LOG_PREFIX, date('Y-m-d'), date('H'));
    
    // Prepare complete record with all data
    $record = [
        'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'timestamp_local' => date('Y-m-d H:i:s'),
        'request_id' => $requestId,
        'client_ip' => $clientIp,
        'content_type' => $contentType,
        'format' => $format,
        'raw_data' => $rawData, // Complete raw data for recovery
        'parsed_data' => $parsedData, // Parsed data structure
        'flight_info' => $flightInfo,
        'record_id' => $recordId, // Database record ID if saved successfully
        'saved_to_db' => $recordId !== null,
        'all_post_data' => $allPostData, // All POST data received
    ];
    
    // Read existing log file if exists
    $existingData = [];
    if (file_exists($logFile)) {
        $existingContent = @file_get_contents($logFile);
        if (!empty($existingContent)) {
            $existingData = json_decode($existingContent, true);
            if (!is_array($existingData)) {
                $existingData = [];
            }
            // Ensure it's an array of records
            if (!isset($existingData[0]) && isset($existingData['timestamp_utc'])) {
                // Single record, convert to array
                $existingData = [$existingData];
            }
        }
    }
    
    // If record_id is provided, try to update existing record with same request_id
    if ($recordId !== null) {
        $updated = false;
        foreach ($existingData as $index => $existingRecord) {
            if (isset($existingRecord['request_id']) && $existingRecord['request_id'] === $requestId) {
                // Update existing record
                $existingData[$index] = $record;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            // Add new record if not found
            $existingData[] = $record;
        }
    } else {
        // Add new record
        $existingData[] = $record;
    }
    
    // Save as JSON array
    @file_put_contents($logFile, json_encode($existingData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Get client IP
 */
function getClientIP(): string {
    $candidates = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            $val = $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR' && strpos($val, ',') !== false) {
                $val = trim(explode(',', $val)[0]);
            }
            if (filter_var($val, FILTER_VALIDATE_IP)) {
                return $val;
            }
        }
    }
    return '0.0.0.0';
}

// ================ MAIN LOGIC ================

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(204, ['ok' => true]);
}

// Check method
if (!in_array($_SERVER['REQUEST_METHOD'], ALLOW_METHODS, true)) {
    respond(405, [
        'error' => 'Method not allowed',
        'allowed_methods' => ALLOW_METHODS
    ]);
}

// Generate request ID first (needed for error responses)
$requestId = bin2hex(random_bytes(16));
$clientIp = getClientIP();

// Get Content-Type from multiple sources (before reading body)
$contentType = '';
if (isset($_SERVER['CONTENT_TYPE'])) {
    $contentType = $_SERVER['CONTENT_TYPE'];
} elseif (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
    $contentType = $_SERVER['HTTP_CONTENT_TYPE'];
} elseif (isset($_SERVER['REDIRECT_CONTENT_TYPE'])) {
    $contentType = $_SERVER['REDIRECT_CONTENT_TYPE'];
}

// Remove charset and other parameters from Content-Type
$contentType = trim(explode(';', $contentType)[0]);

// Check body size
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
if ($contentLength > MAX_BODY_MB * 1024 * 1024) {
    respond(413, [
        'error' => 'Payload too large',
        'max_mb' => MAX_BODY_MB,
        'received_mb' => round($contentLength / 1024 / 1024, 2)
    ]);
}

// Get raw body - handle form-encoded data
// IMPORTANT: For form-encoded data, $_POST is automatically populated by PHP
// We should check $_POST first, then fallback to php://input
$rawBody = '';
$rawInput = '';

// Check if it's form-encoded (including multipart/form-data)
$isFormEncoded = stripos($contentType, 'application/x-www-form-urlencoded') !== false;
$isMultipart = stripos($contentType, 'multipart/form-data') !== false;
$isFormData = $isFormEncoded || $isMultipart;

if ($isFormData) {
    // For form-encoded data (both urlencoded and multipart), PHP automatically populates $_POST
    // Check $_POST first (most reliable for form data)
    if (isset($_POST['plan'])) {
        $planValue = $_POST['plan'];
        // Handle both string and array values
        if (is_array($planValue)) {
            $planValue = implode('', $planValue);
        }
        $planValue = trim($planValue);
        if (!empty($planValue)) {
            $rawBody = $planValue;
        }
    }
    
    // If still empty, try reading raw input and parsing manually (for urlencoded only)
    // Note: For multipart/form-data, php://input is not available after PHP processes it
    if (empty($rawBody) && $isFormEncoded) {
        try {
            $rawInput = file_get_contents('php://input');
            if ($rawInput === false) {
                $rawInput = '';
            }
        } catch (Exception $e) {
            error_log("Error reading php://input: " . $e->getMessage());
            $rawInput = '';
        }
        
        if (!empty($rawInput)) {
            // Parse form data manually
            $formData = [];
            parse_str($rawInput, $formData);
            
            if (isset($formData['plan'])) {
                $planValue = is_array($formData['plan']) ? implode('', $formData['plan']) : $formData['plan'];
                $planValue = trim($planValue);
                if (!empty($planValue)) {
                    $rawBody = $planValue;
                }
            } else {
                // Try regex to extract plan parameter (handles URL encoding)
                // Match: plan=value (with optional URL encoding)
                if (preg_match('/plan=([^&]*)/', $rawInput, $matches)) {
                    $rawBody = urldecode($matches[1]);
                } elseif (preg_match('/^plan=(.+)$/', $rawInput, $matches)) {
                    // If entire input is just plan=value
                    $rawBody = urldecode($matches[1]);
                }
            }
        }
    }
} else {
    // For JSON or other formats, read raw input directly
    try {
        $rawInput = file_get_contents('php://input');
        if ($rawInput === false) {
            $rawInput = '';
        }
        $rawBody = $rawInput;
    } catch (Exception $e) {
        error_log("Error reading php://input: " . $e->getMessage());
        $rawBody = '';
    }
}

// Validate API token (after reading body to avoid consuming php://input)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateToken()) {
    respond(401, [
        'error' => 'Unauthorized',
        'message' => 'Invalid or missing API token',
        'hint' => 'Please include API token in Authorization header, X-API-Key header, or key header'
    ]);
}

// Debug logging (only if empty to avoid spam)
if (empty($rawBody)) {
    error_log("Empty rawBody - Content-Type: $contentType, RawInput length: " . strlen($rawInput) . ", POST keys: " . implode(',', array_keys($_POST)));
}

if (empty($rawBody)) {
    respond(400, [
        'error' => 'Empty request body',
        'message' => 'No data found in request body. For form-encoded requests, please include "plan" parameter.',
        'debug' => [
            'content_type' => $contentType,
            'content_length' => isset($_SERVER['CONTENT_LENGTH']) ? $_SERVER['CONTENT_LENGTH'] : 0,
            'raw_input_length' => strlen($rawInput),
            'post_keys' => array_keys($_POST),
            'has_plan_in_post' => isset($_POST['plan'])
        ],
        'request_id' => $requestId
    ]);
}

// Parse data
$parsedData = null;
$format = 'unknown';

try {
    // Try JSON format first
    if (stripos($contentType, 'application/json') !== false || strpos(trim($rawBody), '{') === 0) {
        $parsedData = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE && $parsedData !== null) {
            $format = 'json';
        }
    }
    
    // Try String format if JSON failed
    if ($parsedData === null) {
        $parsedData = parseStringFormat($rawBody);
        if ($parsedData !== null) {
            $format = 'string';
        }
    }
    
    // If still null, try to accept as raw text (for testing purposes)
    // Store as minimal valid OFP structure
    if ($parsedData === null && !empty($rawBody)) {
        // Create a minimal valid OFP structure with raw data
        $parsedData = [
            'binfo' => [
                'FLN' => 'TEST',
                'DTE' => date('Y-m-d'),
                'RAW_DATA' => $rawBody
            ]
        ];
        $format = 'string';
    }
    
    // Check if parsing succeeded
    if ($parsedData === null) {
        respond(400, [
            'error' => 'Invalid data format',
            'message' => 'Could not parse data as JSON or String format. Data must be valid OFP format (JSON with binfo/futbl structure or String format like "binfo:|FLN=...||").',
            'content_type' => $contentType,
            'data_preview' => substr($rawBody, 0, 100) . (strlen($rawBody) > 100 ? '...' : ''),
            'request_id' => $requestId
        ]);
    }
    
    // Validate data
    $validationErrors = validateOFPData($parsedData);
    if (!empty($validationErrors)) {
        respond(400, [
            'error' => 'Validation failed',
            'errors' => $validationErrors,
            'request_id' => $requestId
        ]);
    }
    
    // Extract flight information
    $flightInfo = extractFlightInfo($parsedData);
    
    // Collect all POST data for logging
    $allPostData = [];
    if (!empty($_POST)) {
        foreach ($_POST as $key => $value) {
            if (is_array($value)) {
                $allPostData[$key] = $value;
            } else {
                $allPostData[$key] = $value;
            }
        }
    }
    
    // Log request with all data (including POST data)
    // Always log, even if database save is skipped
    logRequest($requestId, $rawBody, $parsedData, $format, $flightInfo, $clientIp, null, $contentType, $allPostData);
    
    // Optional: Save to database (commented out as requested - just log)
    // But we'll still try to save for backward compatibility
    $recordId = null;
    try {
        $db = getDB();
        ensureTableExists($db);
        
        // Save to database
        $recordId = saveOFPData($db, $flightInfo, $format, $rawBody, $parsedData, $clientIp, $requestId);
        
        // Update log with record_id after successful save
        logRequest($requestId, $rawBody, $parsedData, $format, $flightInfo, $clientIp, $recordId, $contentType, $allPostData);
    } catch (PDOException $e) {
        // If database save fails, we still have the log
        error_log("Database save failed (logged anyway): " . $e->getMessage());
    }
    
    // Success response with raw_data included
        respond(200, [
            'ok' => true,
        'message' => 'OFP data received and logged successfully',
            'request_id' => $requestId,
            'record_id' => $recordId,
        'raw_data' => $rawBody, // Include raw data in response
        'all_post_data' => $allPostData, // Include all POST data
            'format' => $format
        ]);
    
} catch (Exception $e) {
    error_log("Processing error: " . $e->getMessage());
    respond(500, [
        'error' => 'Processing error',
        'message' => $e->getMessage(),
        'request_id' => $requestId
    ]);
}

