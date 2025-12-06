<?php
/**
 * Raimon Fleet Management System Configuration
 * Project: Raimon Fleet
 * Company: Raimon Airways
 * PHP Version: 8.2.12
 * Database: MariaDB 10.4.32
 */

// Project Information
define('PROJECT_NAME', 'Rai-OPS');
define('COMPANY_NAME', 'Raimon Airways');
define('VERSION', '1.6.0');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'raiops_data');
define('DB_USER', 'raiops_user');
define('DB_PASS', '3140025@Sep'); // No password
define('DB_CHARSET', 'utf8mb4');

// SMS API Configuration (SMS.ir)
define('SMS_API_URL', 'https://api.sms.ir/v1/send');
define('SMS_USERNAME', 'raimon');
define('SMS_PASSWORD', 'LLSj3yLhOBVxP6fb7nBzRbeqmCXgnGp3NB2f2Y7aPWKWxq4n');
define('SMS_LINE', '3000212480');

// WhatsApp API Configuration
define('WHATSAPP_API_URL', 'http://5.252.227.233:3000/send');

// SMS Function
function sendSMS($mobile, $text) {
    $url = SMS_API_URL . '?' . http_build_query([
        'username' => SMS_USERNAME,
        'password' => SMS_PASSWORD,
        'line' => SMS_LINE,
        'mobile' => $mobile,
        'text' => $text
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'success' => $httpCode == 200,
        'response' => $response,
        'http_code' => $httpCode
    ];
}

// WhatsApp Function
function sendWhatsApp($targetNumbers, $textMessage) {
    $data = [
        'targetNumber' => is_array($targetNumbers) ? $targetNumbers : [$targetNumbers],
        'textMessage' => $textMessage
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, WHATSAPP_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'success' => $httpCode == 200,
        'response' => $response,
        'http_code' => $httpCode
    ];
}

// Session Configuration
session_start();

// Timezone
date_default_timezone_set('Asia/Tehran');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Base URL (static) and dynamic resolver based on current request
define('BASE_URL', 'http://localhost/');

// Compute dynamic base URL from request to avoid localhost hardcoding issues
function base_url() {
    $scheme = 'http';
    if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
        $scheme = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/';
    // Attempt to detect project root segment automatically (e.g., /)
    $projectSegment = '/';
    $pos = strpos($scriptPath, $projectSegment);
    $basePath = '/';
    if ($pos !== false) {
        $basePath = substr($scriptPath, 0, $pos + strlen($projectSegment));
    }
    // Fallback to configured BASE_URL path if detection fails
    if ($basePath === '/' && defined('BASE_URL')) {
        $parsed = parse_url(BASE_URL);
        if (!empty($parsed['path'])) {
            $basePath = rtrim($parsed['path'], '/') . '/';
        }
    }
    return rtrim($scheme . '://' . $host, '/') . $basePath;
}

// File Upload Configuration
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Security
define('ENCRYPTION_KEY', 'raimon_fleet_2024_secure_key');

// Database Connection Function
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Authentication Functions
function loginUser($username, $password) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['position'] = $user['position'];
            $_SESSION['role'] = $user['role'] ?? 'user'; // Default to 'user' if role is not set
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['login_time'] = time();
            
            // Update last login time
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Log login activity
            logActivity('login', 'login.php', [
                'page_name' => 'User Login',
                'section' => 'Authentication'
            ]);
            
            return true;
        }
        return false;
    } catch(PDOException $e) {
        return false;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function logoutUser() {
    // Log logout activity before destroying session
    if (isLoggedIn()) {
        logActivity('logout', 'logout.php', [
            'page_name' => 'User Logout',
            'section' => 'Authentication'
        ]);
    }
    
    session_destroy();
    session_start();
}

// Get current user information
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT u.*, r.name as role_name, r.display_name as role_display_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Set role field for backward compatibility
        if ($user && $user['role_name']) {
            $user['role'] = $user['role_name'];
        } else {
            $user['role'] = 'employee'; // Default fallback
        }
        
        return $user;
    } catch(PDOException $e) {
        return null;
    }
}

// Check if user has specific role
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $current_user = getCurrentUser();
    $userRole = $current_user['role_name'] ?? 'employee';
    
    return $userRole === $role;
}

// Check if user has any of the specified roles
function hasAnyRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $current_user = getCurrentUser();
    $userRole = $current_user['role_name'] ?? 'employee';
    
    return in_array($userRole, $roles);
}

// User Management Functions
function getAllUsers($limit = null, $offset = 0, $search = []) {
    try {
        $pdo = getDBConnection();
        
        $whereConditions = [];
        $params = [];
        
        // Add search conditions
        if (!empty($search['quick_search'])) {
            // Quick search across all fields
            $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.position LIKE ? OR r.name LIKE ? OR r.display_name LIKE ? OR u.asic_number LIKE ?)";
            $searchTerm = '%' . $search['quick_search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        } else {
            // Individual field searches
            if (!empty($search['name'])) {
                $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
                $searchTerm = '%' . $search['name'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($search['position'])) {
                $whereConditions[] = "u.position LIKE ?";
                $params[] = '%' . $search['position'] . '%';
            }
            
            if (!empty($search['role'])) {
                $whereConditions[] = "(r.name LIKE ? OR r.display_name LIKE ?)";
                $searchTerm = '%' . $search['role'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($search['asic_number'])) {
                $whereConditions[] = "u.asic_number LIKE ?";
                $params[] = '%' . $search['asic_number'] . '%';
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT u.*, r.name as role_name, r.display_name as role_display_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                $whereClause 
                ORDER BY u.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add role field for backward compatibility
        foreach ($users as &$user) {
            $user['role'] = $user['role_name'] ?? 'employee';
        }
        
        return $users;
    } catch(PDOException $e) {
        return [];
    }
}

function getUserById($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT u.*, r.name as role_name, r.display_name as role_display_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Add role field for backward compatibility
        if ($user && $user['role_name']) {
            $user['role'] = $user['role_name'];
        } else {
            $user['role'] = 'employee'; // Default fallback
        }
        
        return $user;
    } catch(PDOException $e) {
        return null;
    }
}

function updateUserStatus($id, $status) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    } catch(PDOException $e) {
        return false;
    }
}

function deleteUser($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        return $stmt->execute([$id]);
    } catch(PDOException $e) {
        return false;
    }
}

function getUsersCount($search = []) {
    try {
        $pdo = getDBConnection();
        
        $whereConditions = [];
        $params = [];
        
        // Add search conditions
        if (!empty($search['quick_search'])) {
            // Quick search across all fields
            $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.position LIKE ? OR r.name LIKE ? OR r.display_name LIKE ? OR u.asic_number LIKE ?)";
            $searchTerm = '%' . $search['quick_search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        } else {
            // Individual field searches
            if (!empty($search['name'])) {
                $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
                $searchTerm = '%' . $search['name'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($search['position'])) {
                $whereConditions[] = "u.position LIKE ?";
                $params[] = '%' . $search['position'] . '%';
            }
            
            if (!empty($search['role'])) {
                // Support exact role match (for stats cards) or LIKE search
                if (in_array($search['role'], ['pilot', 'administrator', 'admin'])) {
                    $whereConditions[] = "r.name = ?";
                    $params[] = $search['role'] == 'administrator' ? 'admin' : $search['role'];
                } else {
                    $whereConditions[] = "(r.name LIKE ? OR r.display_name LIKE ?)";
                    $searchTerm = '%' . $search['role'] . '%';
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
            }
            
            if (isset($search['flight_crew'])) {
                $whereConditions[] = "u.flight_crew = ?";
                $params[] = intval($search['flight_crew']);
            }
            
            if (!empty($search['asic_number'])) {
                $whereConditions[] = "u.asic_number LIKE ?";
                $params[] = '%' . $search['asic_number'] . '%';
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT COUNT(*) as count FROM users u LEFT JOIN roles r ON u.role_id = r.id $whereClause";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    } catch(PDOException $e) {
        return 0;
    }
}

function updateUser($id, $data) {
    $logDetails = [];
    $logDetails['user_id'] = $id;
    $logDetails['timestamp'] = date('Y-m-d H:i:s');
    $logDetails['data_fields'] = array_keys($data);
    $logDetails['data_count'] = count($data);
    
    try {
        $pdo = getDBConnection();
        $logDetails['db_connection'] = 'success';
        
        // Build dynamic update query
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id' && $key !== 'password') {
                $fields[] = "`$key` = ?";
                $values[] = $value;
            }
        }
        
        $logDetails['fields_to_update'] = $fields;
        $logDetails['values_count'] = count($values);
        
        if (empty($fields)) {
            $logDetails['error'] = 'No fields to update';
            $logDetails['result'] = 'failed';
            logUserUpdateError($logDetails);
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $logDetails['sql_query'] = $sql;
        $logDetails['sql_values'] = $values;
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        $logDetails['affected_rows'] = $stmt->rowCount();
        $logDetails['result'] = $result ? 'success' : 'failed';
        
        if (!$result) {
            $logDetails['error'] = 'SQL execution failed';
            $logDetails['pdo_error'] = $stmt->errorInfo();
        }
        
        logUserUpdateError($logDetails);
        return $result;
        
    } catch(PDOException $e) {
        $logDetails['error'] = 'PDO Exception';
        $logDetails['exception_message'] = $e->getMessage();
        $logDetails['exception_code'] = $e->getCode();
        $logDetails['exception_file'] = $e->getFile();
        $logDetails['exception_line'] = $e->getLine();
        $logDetails['result'] = 'failed';
        logUserUpdateError($logDetails);
        return false;
    } catch(Exception $e) {
        $logDetails['error'] = 'General Exception';
        $logDetails['exception_message'] = $e->getMessage();
        $logDetails['exception_code'] = $e->getCode();
        $logDetails['exception_file'] = $e->getFile();
        $logDetails['exception_line'] = $e->getLine();
        $logDetails['result'] = 'failed';
        logUserUpdateError($logDetails);
        return false;
    }
}

/**
 * Log user update errors and details
 */
function logUserUpdateError($logDetails) {
    $logFile = __DIR__ . '/logs/user_update_errors.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = [
        'timestamp' => $logDetails['timestamp'],
        'user_id' => $logDetails['user_id'],
        'result' => $logDetails['result'],
        'data_fields' => $logDetails['data_fields'],
        'data_count' => $logDetails['data_count'],
        'fields_to_update' => $logDetails['fields_to_update'] ?? null,
        'values_count' => $logDetails['values_count'] ?? null,
        'sql_query' => $logDetails['sql_query'] ?? null,
        'sql_values' => $logDetails['sql_values'] ?? null,
        'affected_rows' => $logDetails['affected_rows'] ?? null,
        'db_connection' => $logDetails['db_connection'] ?? null,
        'error' => $logDetails['error'] ?? null,
        'exception_message' => $logDetails['exception_message'] ?? null,
        'exception_code' => $logDetails['exception_code'] ?? null,
        'exception_file' => $logDetails['exception_file'] ?? null,
        'exception_line' => $logDetails['exception_line'] ?? null,
        'pdo_error' => $logDetails['pdo_error'] ?? null,
        'server_info' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]
    ];
    
    $logLine = date('Y-m-d H:i:s') . " - " . json_encode($logEntry, JSON_PRETTY_PRINT) . "\n" . str_repeat("-", 80) . "\n";
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Log flight-related errors
 */
function logFlightError($logDetails) {
    $logFile = __DIR__ . '/logs/flight_errors.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = [
        'timestamp' => $logDetails['timestamp'],
        'action' => $logDetails['action'],
        'sql' => $logDetails['sql'],
        'values' => $logDetails['values'],
        'error_code' => $logDetails['error_code'],
        'error_message' => $logDetails['error_message'],
        'server_info' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]
    ];
    
    $logLine = date('Y-m-d H:i:s') . " - " . json_encode($logEntry, JSON_PRETTY_PRINT) . "\n" . str_repeat("-", 80) . "\n";
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Get the latest user update error log
 */
function getLatestUserUpdateError() {
    $logFile = __DIR__ . '/logs/user_update_errors.log';
    
    if (!file_exists($logFile)) {
        return null;
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    $latestEntry = '';
    $inEntry = false;
    
    // Get the last complete log entry
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (strpos($lines[$i], '---') === 0) {
            if ($inEntry) {
                break;
            }
            $inEntry = true;
        } elseif ($inEntry) {
            $latestEntry = $lines[$i] . "\n" . $latestEntry;
        }
    }
    
    return $latestEntry;
}

/**
 * Get the latest flight error log
 */
function getLatestFlightError() {
    $logFile = __DIR__ . '/logs/flight_errors.log';
    
    if (!file_exists($logFile)) {
        return null;
    }
    
    $content = file_get_contents($logFile);
    $lines = explode("\n", $content);
    
    // Find the last complete log entry
    $lastEntry = '';
    $inEntry = false;
    
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = $lines[$i];
        
        if (strpos($line, '---') === 0) {
            if ($inEntry) {
                break; // End of previous entry
            }
            $inEntry = true;
        } elseif ($inEntry && strpos($line, ' - ') !== false) {
            $lastEntry = $line;
            break;
        }
    }
    
    if ($lastEntry) {
        $jsonStart = strpos($lastEntry, ' - ') + 3;
        $jsonPart = substr($lastEntry, $jsonStart);
        return json_decode($jsonPart, true);
    }
    
    return null;
}

// Air Safety Reports (ASR) Functions
function getASRReports($limit = 20, $offset = 0, $search = []) {
    try {
        $pdo = getDBConnection();
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($search['report_number'])) {
            $whereConditions[] = "report_number LIKE ?";
            $params[] = '%' . $search['report_number'] . '%';
        }
        
        if (!empty($search['aircraft_registration'])) {
            $whereConditions[] = "aircraft_registration LIKE ?";
            $params[] = '%' . $search['aircraft_registration'] . '%';
        }
        
        if (!empty($search['flight_number'])) {
            $whereConditions[] = "flight_number LIKE ?";
            $params[] = '%' . $search['flight_number'] . '%';
        }
        
        if (!empty($search['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $search['status'];
        }
        
        if (!empty($search['date_from'])) {
            $whereConditions[] = "report_date >= ?";
            $params[] = $search['date_from'];
        }
        
        if (!empty($search['date_to'])) {
            $whereConditions[] = "report_date <= ?";
            $params[] = $search['date_to'];
        }
        
        if (!empty($search['quick_search'])) {
            $whereConditions[] = "(report_number LIKE ? OR aircraft_registration LIKE ? OR flight_number LIKE ? OR short_description LIKE ?)";
            $searchTerm = '%' . $search['quick_search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT * FROM air_safety_reports $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

function getASRReportsCount($search = []) {
    try {
        $pdo = getDBConnection();
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($search['report_number'])) {
            $whereConditions[] = "report_number LIKE ?";
            $params[] = '%' . $search['report_number'] . '%';
        }
        
        if (!empty($search['aircraft_registration'])) {
            $whereConditions[] = "aircraft_registration LIKE ?";
            $params[] = '%' . $search['aircraft_registration'] . '%';
        }
        
        if (!empty($search['flight_number'])) {
            $whereConditions[] = "flight_number LIKE ?";
            $params[] = '%' . $search['flight_number'] . '%';
        }
        
        if (!empty($search['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $search['status'];
        }
        
        if (!empty($search['date_from'])) {
            $whereConditions[] = "report_date >= ?";
            $params[] = $search['date_from'];
        }
        
        if (!empty($search['date_to'])) {
            $whereConditions[] = "report_date <= ?";
            $params[] = $search['date_to'];
        }
        
        if (!empty($search['quick_search'])) {
            $whereConditions[] = "(report_number LIKE ? OR aircraft_registration LIKE ? OR flight_number LIKE ? OR short_description LIKE ?)";
            $searchTerm = '%' . $search['quick_search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT COUNT(*) FROM air_safety_reports $whereClause";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch(PDOException $e) {
        return 0;
    }
}

function getASRReportById($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM air_safety_reports WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return null;
    }
}

function createASRReport($data) {
    try {
        $pdo = getDBConnection();
        
        // Generate report number
        $reportNumber = 'ASR-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $sql = "INSERT INTO air_safety_reports (
            report_number, report_date, status, aircraft_type, aircraft_registration, 
            operator, flight_number, departure_airport, destination_airport, diversion_airport,
            place_of_occurrence, occurrence_date, occurrence_time_utc, technical_log_seq_no,
            purpose_flight, purpose_other, pilot_name, pilot_license, pilot_rating,
            total_flight_hours, hours_on_type, hours_last_90_days, occurrence_type,
            occurrence_other, severity_risk, avoiding_action_taken, minimum_vertical_separation,
            short_description, detailed_description, action_taken, recommendations,
            weather_conditions, aircraft_condition, crew_condition, other_aircraft_involved,
            bird_strike_type_of_birds, bird_strike_nr_seen, bird_strike_nr_struck, bird_strike_damage_description,
            ground_found_name, ground_found_location, ground_found_shift, ground_found_type, ground_found_component_description,
            ground_found_part_no, ground_found_serial_no, ground_found_atc_chapter, ground_found_tag_no,
            airprox_events, reported_to_atc, minimum_horizontal_separation, atc_instructions_issued,
            frequency_in_use, airprox_heading, cleared_altitude_fl, tcas_alert,
            types_of_ra, ra_followed, vertical_deviation, tcas_alert_was,
            airprox_signature_name, airprox_signature, airprox_signature_date,
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $reportNumber,
            $data['report_date'] ?? date('Y-m-d'),
            $data['status'] ?? 'draft',
            $data['aircraft_type'] ?? null,
            $data['aircraft_registration'] ?? null,
            $data['operator'] ?? null,
            $data['flight_number'] ?? null,
            $data['departure_airport'] ?? null,
            $data['destination_airport'] ?? null,
            $data['diversion_airport'] ?? null,
            $data['place_of_occurrence'] ?? null,
            $data['occurrence_date'] ?? null,
            $data['occurrence_time_utc'] ?? null,
            $data['technical_log_seq_no'] ?? null,
            $data['purpose_flight'] ?? null,
            $data['purpose_other'] ?? null,
            $data['pilot_name'] ?? null,
            $data['pilot_license'] ?? null,
            $data['pilot_rating'] ?? null,
            $data['total_flight_hours'] ?? null,
            $data['hours_on_type'] ?? null,
            $data['hours_last_90_days'] ?? null,
            $data['occurrence_type'] ?? null,
            $data['occurrence_other'] ?? null,
            $data['severity_risk'] ?? null,
            $data['avoiding_action_taken'] ?? null,
            $data['minimum_vertical_separation'] ?? null,
            $data['short_description'] ?? null,
            $data['detailed_description'] ?? null,
            $data['action_taken'] ?? null,
            $data['recommendations'] ?? null,
            $data['weather_conditions'] ?? null,
            $data['aircraft_condition'] ?? null,
            $data['crew_condition'] ?? null,
            $data['other_aircraft_involved'] ?? null,
            $data['bird_strike_type_of_birds'] ?? null,
            $data['bird_strike_nr_seen'] ?? null,
            $data['bird_strike_nr_struck'] ?? null,
            $data['bird_strike_damage_description'] ?? null,
            $data['ground_found_name'] ?? null,
            $data['ground_found_location'] ?? null,
            $data['ground_found_shift'] ?? null,
            $data['ground_found_type'] ?? null,
            $data['ground_found_component_description'] ?? null,
            $data['ground_found_part_no'] ?? null,
            $data['ground_found_serial_no'] ?? null,
            $data['ground_found_atc_chapter'] ?? null,
            $data['ground_found_tag_no'] ?? null,
            $data['airprox_events'] ?? null,
            $data['reported_to_atc'] ?? null,
            $data['minimum_horizontal_separation'] ?? null,
            $data['atc_instructions_issued'] ?? null,
            $data['frequency_in_use'] ?? null,
            $data['airprox_heading'] ?? null,
            $data['cleared_altitude_fl'] ?? null,
            $data['tcas_alert'] ?? null,
            $data['types_of_ra'] ?? null,
            $data['ra_followed'] ?? null,
            $data['vertical_deviation'] ?? null,
            $data['tcas_alert_was'] ?? null,
            $data['airprox_signature_name'] ?? null,
            $data['airprox_signature'] ?? null,
            $data['airprox_signature_date'] ?? null,
            $data['created_by'] ?? null
        ]);
    } catch(PDOException $e) {
        return false;
    }
}

function updateASRReport($id, $data) {
    try {
        $pdo = getDBConnection();
        
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "`$key` = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE air_safety_reports SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    } catch(PDOException $e) {
        return false;
    }
}

function deleteASRReport($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM air_safety_reports WHERE id = ?");
        return $stmt->execute([$id]);
    } catch(PDOException $e) {
        return false;
    }
}

function updateASRReportStatus($id, $status) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE air_safety_reports SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    } catch(PDOException $e) {
        return false;
    }
}

// Profile Management Functions
function updateUserProfile($id, $data) {
    try {
        $pdo = getDBConnection();
        
        // Build dynamic update query for profile fields only
        $allowedFields = [
            'first_name', 'last_name', 'position', 'email', 'mobile', 'alternative_mobile',
            'phone', 'fax', 'alternate_email', 'address_line_1', 'address_line_2',
            'suburb_city', 'postcode', 'state', 'country', 'latitude', 'longitude',
            'emergency_contact_name', 'emergency_contact_number', 'emergency_contact_email',
            'emergency_contact_alternate_email', 'passport_number', 'passport_nationality',
            'passport_expiry_date', 'driver_licence_number', 'frequent_flyer_number',
            'other_award_scheme_name', 'other_award_scheme_number', 'individual_leave_entitlements',
            'using_standalone_annual_leave', 'leave_days', 'roles_groups',
            'selected_roles_groups', 'receive_scheduled_emails', 'picture',
            'asic_number', 'national_id'
        ];
        
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "`$key` = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    } catch(PDOException $e) {
        return false;
    }
}

function changePassword($id, $currentPassword, $newPassword) {
    try {
        $pdo = getDBConnection();
        
        // If current password is provided, verify it first
        if ($currentPassword !== null && $currentPassword !== '') {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return false; // Current password is incorrect
            }
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashedPassword, $id]);
    } catch(PDOException $e) {
        return false;
    }
}

function createUser($data, &$errorMessage = null) {
    try {
        $pdo = getDBConnection();
        
        // Check for duplicate username
        if (!empty($data['username'])) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$data['username']]);
            if ($stmt->fetch()) {
                $errorMessage = "Username '{$data['username']}' already exists.";
                error_log("createUser: Username '{$data['username']}' already exists");
                return false;
            }
        }
        
        // Check for duplicate email (if email is provided and should be unique)
        if (!empty($data['email'])) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND email IS NOT NULL AND email != ''");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                $errorMessage = "Email '{$data['email']}' already exists.";
                error_log("createUser: Email '{$data['email']}' already exists");
                return false;
            }
        }
        
        // Hash password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // Set default values
        $data['status'] = $data['status'] ?? 'active';
        
        // Check if role_id column exists in users table
        $hasRoleId = false;
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role_id'");
            $hasRoleId = $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            // If we can't check, assume it doesn't exist
            $hasRoleId = false;
        }
        
        // Handle role_id vs role based on table structure
        if ($hasRoleId) {
            // Table has role_id column - use it (like updateUser does)
            if (!isset($data['role_id']) || empty($data['role_id'])) {
                $data['role_id'] = 2; // Default to employee role
            }
            // Remove role if it exists (we're using role_id)
            unset($data['role']);
        } else {
            // Table has role enum column - convert role_id to role
            if (isset($data['role_id']) && !empty($data['role_id'])) {
                try {
                    $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
                    $stmt->execute([$data['role_id']]);
                    $roleRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($roleRow && isset($roleRow['name'])) {
                        $data['role'] = $roleRow['name'];
                    }
                } catch (PDOException $e) {
                    error_log("createUser: Could not fetch role from roles table: " . $e->getMessage());
                    $data['role'] = 'employee'; // Fallback
                }
            } else {
                $data['role'] = 'employee'; // Default
            }
            // Remove role_id if it exists (we're using role)
            unset($data['role_id']);
        }
        
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Build insert query - include all provided fields
        // Required fields: first_name, last_name, position, username, password
        $requiredFields = ['first_name', 'last_name', 'position', 'username', 'password'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errorMessage = "Required field '{$field}' is missing or empty.";
                error_log("createUser: Required field '{$field}' is missing or empty");
                return false;
            }
        }
        
        // Get valid columns from users table to filter out invalid fields
        $validColumns = [];
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $column) {
                $validColumns[] = $column['Field'];
            }
        } catch (PDOException $e) {
            error_log("createUser: Could not get table columns: " . $e->getMessage());
            // If we can't get columns, use all fields (fallback)
            $validColumns = array_keys($data);
        }
        
        // Filter data to only include valid columns
        $filteredData = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $validColumns)) {
                $filteredData[$key] = $value;
            } else {
                error_log("createUser: Skipping invalid column '{$key}'");
            }
        }
        
        if (empty($filteredData)) {
            $errorMessage = "No valid fields to insert.";
            error_log("createUser: No valid fields to insert");
            return false;
        }
        
        // Build insert query - include only valid fields
        $fields = array_keys($filteredData);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO users (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        error_log("createUser: Attempting INSERT with fields: " . implode(', ', $fields));
        error_log("createUser: SQL: " . $sql);
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute(array_values($filteredData));
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            $errorMessage = "Database error: " . ($errorInfo[2] ?? 'Unknown error occurred.');
            error_log("createUser: SQL execution failed - " . print_r($errorInfo, true));
            error_log("createUser: Error Code: " . ($errorInfo[0] ?? 'N/A'));
            error_log("createUser: Error Message: " . ($errorInfo[2] ?? 'N/A'));
            return false;
        }
        
        return true;
    } catch(PDOException $e) {
        $errorCode = $e->getCode();
        $errorMsg = $e->getMessage();
        
        // Check for common MySQL/MariaDB error codes
        if ($errorCode == 23000 || strpos($errorMsg, 'Duplicate entry') !== false) {
            // Extract the duplicate field from error message
            if (strpos($errorMsg, 'username') !== false) {
                $errorMessage = "Username already exists.";
            } elseif (strpos($errorMsg, 'email') !== false) {
                $errorMessage = "Email already exists.";
            } else {
                $errorMessage = "Duplicate entry detected. Please check username and email.";
            }
        } elseif ($errorCode == 42000 || strpos($errorMsg, 'Unknown column') !== false) {
            $errorMessage = "Database structure error. Please contact administrator.";
        } else {
            $errorMessage = "Database error: " . $errorMsg;
        }
        
        error_log("createUser: PDO Exception - " . $errorMsg);
        error_log("createUser: Error Code: " . $errorCode);
        error_log("createUser: SQL State: " . $e->getCode());
        error_log("createUser: Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

// Aircraft Management Functions
function getAllAircraft() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM aircraft ORDER BY registration ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

function getAircraftById($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM aircraft WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return false;
    }
}

function createAircraft($data) {
    try {
        $pdo = getDBConnection();
        
        // Set default values
        $data['enabled'] = $data['enabled'] ?? 1;
        $data['status'] = $data['status'] ?? 'active';
        $data['nvfr'] = isset($data['nvfr']) ? 1 : 0;
        $data['ifr'] = isset($data['ifr']) ? 1 : 0;
        $data['spifr'] = isset($data['spifr']) ? 1 : 0;
        $data['number_of_engines'] = intval($data['number_of_engines'] ?? 1);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Build insert query
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO aircraft (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute(array_values($data));
        
        if (!$result) {
            error_log("Aircraft creation failed: " . implode(', ', $stmt->errorInfo()));
        }
        
        return $result;
    } catch(PDOException $e) {
        error_log("Aircraft creation error: " . $e->getMessage());
        return false;
    }
}

function updateAircraft($id, $data) {
    try {
        $pdo = getDBConnection();
        
        // Set default values
        $data['enabled'] = isset($data['enabled']) ? 1 : 0;
        $data['nvfr'] = isset($data['nvfr']) ? 1 : 0;
        $data['ifr'] = isset($data['ifr']) ? 1 : 0;
        $data['spifr'] = isset($data['spifr']) ? 1 : 0;
        $data['number_of_engines'] = intval($data['number_of_engines'] ?? 1);
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Build dynamic update query
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "`$key` = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE aircraft SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    } catch(PDOException $e) {
        return false;
    }
}

function deleteAircraft($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM aircraft WHERE id = ?");
        return $stmt->execute([$id]);
    } catch(PDOException $e) {
        return false;
    }
}

function updateAircraftStatus($id, $status) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE aircraft SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $id]);
    } catch(PDOException $e) {
        return false;
    }
}

function getAircraftCount() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM aircraft");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch(PDOException $e) {
        return 0;
    }
}

// Shift Code Management Functions
function getAllShiftCodes() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM shift_code ORDER BY code ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error getting shift codes: " . $e->getMessage());
        return [];
    }
}

function getShiftCodeById($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM shift_code WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        if ($result) {
            if (!empty($result['duties'])) {
                $result['duties'] = json_decode($result['duties'], true) ?: [];
            } else {
                $result['duties'] = [];
            }
            
            if (!empty($result['flying_duty_periods'])) {
                $result['flying_duty_periods'] = json_decode($result['flying_duty_periods'], true) ?: [];
            } else {
                $result['flying_duty_periods'] = [];
            }
            
            if (!empty($result['shift_periods'])) {
                $result['shift_periods'] = json_decode($result['shift_periods'], true) ?: [];
            } else {
                $result['shift_periods'] = [];
            }
        }
        
        return $result;
    } catch(PDOException $e) {
        error_log("Error getting shift code: " . $e->getMessage());
        return false;
    }
}

function createShiftCode($data, $userId = null) {
    try {
        $pdo = getDBConnection();
        
        // Prepare duties array
        $duties = [];
        if (!empty($data['duty_start']) && is_array($data['duty_start'])) {
            foreach ($data['duty_start'] as $index => $start) {
                if (!empty($start) && !empty($data['duty_end'][$index])) {
                    $duties[] = [
                        'start' => $start,
                        'end' => $data['duty_end'][$index]
                    ];
                }
            }
        }
        
        // Prepare flying duty periods array
        $flyingPeriods = [];
        if (!empty($data['flying_start']) && is_array($data['flying_start'])) {
            foreach ($data['flying_start'] as $index => $start) {
                if (!empty($start) && !empty($data['flying_end'][$index])) {
                    $flyingPeriods[] = [
                        'start' => $start,
                        'end' => $data['flying_end'][$index]
                    ];
                }
            }
        }
        
        // Prepare shift periods array
        $shiftPeriods = [];
        if (!empty($data['shift_start']) && is_array($data['shift_start'])) {
            foreach ($data['shift_start'] as $index => $start) {
                if (!empty($start) && !empty($data['shift_end'][$index])) {
                    $shiftPeriods[] = [
                        'start' => $start,
                        'end' => $data['shift_end'][$index]
                    ];
                }
            }
        }
        
        $sql = "INSERT INTO shift_code (
            code, description, text_color, background_color, base, department, category,
            duties, sleeping_accommodation, duties_non_cumulative,
            flying_duty_periods, flight_hours, sectors,
            work_practice, shift_periods,
            al, fl, start_of_new_tour, enable_bulk_duty_update,
            allowed_in_timesheet, show_in_scheduler_quick_create, enabled,
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['code'] ?? '',
            $data['description'] ?? null,
            $data['text_color'] ?? '#000000',
            $data['background_color'] ?? '#FFFFFF',
            $data['base'] ?? 'Common',
            $data['department'] ?? 'Common',
            $data['category'] ?? 'Duty',
            !empty($duties) ? json_encode($duties, JSON_UNESCAPED_UNICODE) : null,
            isset($data['sleeping_accommodation']) ? 1 : 0,
            isset($data['duties_non_cumulative']) ? 1 : 0,
            !empty($flyingPeriods) ? json_encode($flyingPeriods, JSON_UNESCAPED_UNICODE) : null,
            floatval($data['flight_hours'] ?? 0),
            intval($data['sectors'] ?? 0),
            $data['work_practice'] ?? null,
            !empty($shiftPeriods) ? json_encode($shiftPeriods, JSON_UNESCAPED_UNICODE) : null,
            floatval($data['al'] ?? 0),
            floatval($data['fl'] ?? 0),
            isset($data['start_of_new_tour']) ? 1 : 0,
            isset($data['enable_bulk_duty_update']) ? 1 : 0,
            isset($data['allowed_in_timesheet']) ? 1 : 1,
            isset($data['show_in_scheduler_quick_create']) ? 1 : 0,
            isset($data['enabled']) ? 1 : 1,
            $userId
        ]);
        
        if (!$result) {
            error_log("Shift code creation failed: " . implode(', ', $stmt->errorInfo()));
        }
        
        return $result ? $pdo->lastInsertId() : false;
    } catch(PDOException $e) {
        error_log("Shift code creation error: " . $e->getMessage());
        return false;
    }
}

function updateShiftCode($id, $data, $userId = null) {
    try {
        $pdo = getDBConnection();
        
        // Prepare duties array
        $duties = [];
        if (!empty($data['duty_start']) && is_array($data['duty_start'])) {
            foreach ($data['duty_start'] as $index => $start) {
                if (!empty($start) && !empty($data['duty_end'][$index])) {
                    $duties[] = [
                        'start' => $start,
                        'end' => $data['duty_end'][$index]
                    ];
                }
            }
        }
        
        // Prepare flying duty periods array
        $flyingPeriods = [];
        if (!empty($data['flying_start']) && is_array($data['flying_start'])) {
            foreach ($data['flying_start'] as $index => $start) {
                if (!empty($start) && !empty($data['flying_end'][$index])) {
                    $flyingPeriods[] = [
                        'start' => $start,
                        'end' => $data['flying_end'][$index]
                    ];
                }
            }
        }
        
        // Prepare shift periods array
        $shiftPeriods = [];
        if (!empty($data['shift_start']) && is_array($data['shift_start'])) {
            foreach ($data['shift_start'] as $index => $start) {
                if (!empty($start) && !empty($data['shift_end'][$index])) {
                    $shiftPeriods[] = [
                        'start' => $start,
                        'end' => $data['shift_end'][$index]
                    ];
                }
            }
        }
        
        $sql = "UPDATE shift_code SET
            code = ?, description = ?, text_color = ?, background_color = ?, base = ?, department = ?, category = ?,
            duties = ?, sleeping_accommodation = ?, duties_non_cumulative = ?,
            flying_duty_periods = ?, flight_hours = ?, sectors = ?,
            work_practice = ?, shift_periods = ?,
            al = ?, fl = ?, start_of_new_tour = ?, enable_bulk_duty_update = ?,
            allowed_in_timesheet = ?, show_in_scheduler_quick_create = ?, enabled = ?,
            updated_by = ?, updated_at = NOW()
            WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['code'] ?? '',
            $data['description'] ?? null,
            $data['text_color'] ?? '#000000',
            $data['background_color'] ?? '#FFFFFF',
            $data['base'] ?? 'Common',
            $data['department'] ?? 'Common',
            $data['category'] ?? 'Duty',
            !empty($duties) ? json_encode($duties, JSON_UNESCAPED_UNICODE) : null,
            isset($data['sleeping_accommodation']) ? 1 : 0,
            isset($data['duties_non_cumulative']) ? 1 : 0,
            !empty($flyingPeriods) ? json_encode($flyingPeriods, JSON_UNESCAPED_UNICODE) : null,
            floatval($data['flight_hours'] ?? 0),
            intval($data['sectors'] ?? 0),
            $data['work_practice'] ?? null,
            !empty($shiftPeriods) ? json_encode($shiftPeriods, JSON_UNESCAPED_UNICODE) : null,
            floatval($data['al'] ?? 0),
            floatval($data['fl'] ?? 0),
            isset($data['start_of_new_tour']) ? 1 : 0,
            isset($data['enable_bulk_duty_update']) ? 1 : 0,
            isset($data['allowed_in_timesheet']) ? 1 : 1,
            isset($data['show_in_scheduler_quick_create']) ? 1 : 0,
            isset($data['enabled']) ? 1 : 1,
            $userId,
            $id
        ]);
        
        if (!$result) {
            error_log("Shift code update failed: " . implode(', ', $stmt->errorInfo()));
        }
        
        return $result;
    } catch(PDOException $e) {
        error_log("Shift code update error: " . $e->getMessage());
        return false;
    }
}

function toggleShiftCodeStatus($id, $enabled) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE shift_code SET enabled = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$enabled ? 1 : 0, $id]);
    } catch(PDOException $e) {
        error_log("Error toggling shift code status: " . $e->getMessage());
        return false;
    }
}

// Roster Management Functions
function getCrewUsers() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, position, picture, national_id
            FROM users
            WHERE flight_crew = 1 AND status = 'active'
            ORDER BY first_name, last_name
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get personnel docs images for users with national_id
        foreach ($users as &$user) {
            if (!empty($user['national_id']) && empty($user['picture'])) {
                $docStmt = $pdo->prepare("SELECT idcard_path FROM personnel_docs WHERE national_id = ?");
                $docStmt->execute([$user['national_id']]);
                $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
                if ($doc && !empty($doc['idcard_path'])) {
                    $user['personnel_image'] = $doc['idcard_path'];
                }
            }
        }
        
        return $users;
    } catch(PDOException $e) {
        error_log("Error getting crew users: " . $e->getMessage());
        return [];
    }
}

function getRosterAssignments($startDate, $endDate) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT r.*, sc.code as shift_code, sc.background_color, sc.text_color
            FROM roster r
            LEFT JOIN shift_code sc ON r.shift_code_id = sc.id
            WHERE r.date >= ? AND r.date <= ?
            ORDER BY r.date, r.user_id
        ");
        $stmt->execute([$startDate, $endDate]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize by user_id and date
        $assignments = [];
        foreach ($results as $row) {
            $assignments[$row['user_id']][$row['date']] = $row;
        }
        
        // Get FDP shift code
        $fdpStmt = $pdo->prepare("SELECT id, code, background_color, text_color FROM shift_code WHERE code = 'FDP' AND enabled = 1 LIMIT 1");
        $fdpStmt->execute();
        $fdpShiftCode = $fdpStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get users assigned to flights (Crew1-Crew10) for the date range
        if ($fdpShiftCode) {
            // Get all unique user_id and date combinations from flights
            $flightsStmt = $pdo->prepare("
                SELECT DISTINCT DATE(f.FltDate) as flight_date, f.Crew1 as user_id FROM flights f WHERE DATE(f.FltDate) >= ? AND DATE(f.FltDate) <= ? AND f.Crew1 IS NOT NULL
                UNION
                SELECT DISTINCT DATE(f.FltDate) as flight_date, f.Crew2 as user_id FROM flights f WHERE DATE(f.FltDate) >= ? AND DATE(f.FltDate) <= ? AND f.Crew2 IS NOT NULL
                UNION
                SELECT DISTINCT DATE(f.FltDate) as flight_date, f.Crew3 as user_id FROM flights f WHERE DATE(f.FltDate) >= ? AND DATE(f.FltDate) <= ? AND f.Crew3 IS NOT NULL
                UNION
                SELECT DISTINCT DATE(f.FltDate) as flight_date, f.Crew4 as user_id FROM flights f WHERE DATE(f.FltDate) >= ? AND DATE(f.FltDate) <= ? AND f.Crew4 IS NOT NULL
                UNION
                SELECT DISTINCT DATE(f.FltDate) as flight_date, f.Crew5 as user_id FROM flights f WHERE DATE(f.FltDate) >= ? AND DATE(f.FltDate) <= ? AND f.Crew5 IS NOT NULL
                UNION
                SELECT DISTINCT DATE(f.FltDate) as flight_date, f.Crew6 as user_id FROM flights f WHERE DATE(f.FltDate) >= ? AND DATE(f.FltDate) <= ? AND f.Crew6 IS NOT NULL
                UNION
                SELECT DISTINCT DATE(f.FltDate) as flight_date, f.Crew7 as user_id FROM flights f WHERE DATE(f.FltDate) >= ? AND DATE(f.FltDate) <= ? AND f.Crew7 IS NOT NULL
                UNION
                SELECT DISTINCT DATE(f.FltDate) as flight_date, f.Crew8 as user_id FROM flights f WHERE DATE(f.FltDate) >= ? AND DATE(f.FltDate) <= ? AND f.Crew8 IS NOT NULL
                UNION
                SELECT DISTINCT DATE(f.FltDate) as flight_date, f.Crew9 as user_id FROM flights f WHERE DATE(f.FltDate) >= ? AND DATE(f.FltDate) <= ? AND f.Crew9 IS NOT NULL
                UNION
                SELECT DISTINCT DATE(f.FltDate) as flight_date, f.Crew10 as user_id FROM flights f WHERE DATE(f.FltDate) >= ? AND DATE(f.FltDate) <= ? AND f.Crew10 IS NOT NULL
                ORDER BY flight_date, user_id
            ");
            $params = array_merge(
                [$startDate, $endDate], // Crew1
                [$startDate, $endDate], // Crew2
                [$startDate, $endDate], // Crew3
                [$startDate, $endDate], // Crew4
                [$startDate, $endDate], // Crew5
                [$startDate, $endDate], // Crew6
                [$startDate, $endDate], // Crew7
                [$startDate, $endDate], // Crew8
                [$startDate, $endDate], // Crew9
                [$startDate, $endDate]  // Crew10
            );
            $flightsStmt->execute($params);
            $flightAssignments = $flightsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add FDP assignments for users in flights (only if no existing assignment)
            foreach ($flightAssignments as $flightAssignment) {
                $flightDate = $flightAssignment['flight_date'];
                $userId = $flightAssignment['user_id'];
                
                if (!empty($userId) && is_numeric($userId)) {
                    $userId = intval($userId);
                    
                    // Only add FDP if there's no existing roster assignment for this user/date
                    if (!isset($assignments[$userId][$flightDate])) {
                        $assignments[$userId][$flightDate] = [
                            'user_id' => $userId,
                            'date' => $flightDate,
                            'shift_code_id' => $fdpShiftCode['id'],
                            'shift_code' => $fdpShiftCode['code'],
                            'background_color' => $fdpShiftCode['background_color'],
                            'text_color' => $fdpShiftCode['text_color'],
                            'from_flights' => true // Flag to indicate this is auto-generated from flights
                        ];
                    }
                }
            }
        }
        
        return $assignments;
    } catch(PDOException $e) {
        error_log("Error getting roster assignments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get roster assignments for a specific user
 * Includes FDP assignments from flights table
 */
function getUserRosterAssignments($userId, $startDate = null, $endDate = null) {
    try {
        // Default to current month if dates not provided
        if (!$startDate) {
            $startDate = date('Y-m-01');
        }
        if (!$endDate) {
            $endDate = date('Y-m-t');
        }
        
        // Get all roster assignments for the date range
        $allAssignments = getRosterAssignments($startDate, $endDate);
        
        // Filter for specific user
        $userAssignments = [];
        if (isset($allAssignments[$userId])) {
            $userAssignments = $allAssignments[$userId];
        }
        
        // Sort by date
        ksort($userAssignments);
        
        return array_values($userAssignments);
    } catch(PDOException $e) {
        error_log("Error getting user roster assignments: " . $e->getMessage());
        return [];
    }
}

function saveRosterAssignment($userId, $date, $shiftCodeId, $currentUserId) {
    try {
        $pdo = getDBConnection();
        
        // Check if assignment already exists
        $stmt = $pdo->prepare("SELECT id FROM roster WHERE user_id = ? AND date = ?");
        $stmt->execute([$userId, $date]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing
            $stmt = $pdo->prepare("
                UPDATE roster 
                SET shift_code_id = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([$shiftCodeId, $currentUserId, $existing['id']]);
        } else {
            // Insert new
            $stmt = $pdo->prepare("
                INSERT INTO roster (user_id, date, shift_code_id, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$userId, $date, $shiftCodeId, $currentUserId, $currentUserId]);
        }
    } catch(PDOException $e) {
        error_log("Error saving roster assignment: " . $e->getMessage());
        return false;
    }
}

function clearRosterAssignment($userId, $date) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM roster WHERE user_id = ? AND date = ?");
        return $stmt->execute([$userId, $date]);
    } catch(PDOException $e) {
        error_log("Error clearing roster assignment: " . $e->getMessage());
        return false;
    }
}

function bulkSaveRosterAssignments($assignments, $deletions = [], $currentUserId) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Handle deletions first
        foreach ($deletions as $deletion) {
            $userId = intval($deletion['user_id']);
            $date = $deletion['date'];
            
            // Delete assignment if it exists
            $stmt = $pdo->prepare("DELETE FROM roster WHERE user_id = ? AND date = ?");
            $stmt->execute([$userId, $date]);
        }
        
        // Handle assignments (insert/update)
        foreach ($assignments as $assignment) {
            $userId = intval($assignment['user_id']);
            $date = $assignment['date'];
            $shiftCodeId = !empty($assignment['shift_code_id']) ? intval($assignment['shift_code_id']) : null;
            
            if (!$shiftCodeId) {
                continue; // Skip if no shift_code_id
            }
            
            // Check if assignment already exists
            $stmt = $pdo->prepare("SELECT id FROM roster WHERE user_id = ? AND date = ?");
            $stmt->execute([$userId, $date]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                    // Update existing
                    $stmt = $pdo->prepare("
                        UPDATE roster 
                        SET shift_code_id = ?, updated_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$shiftCodeId, $currentUserId, $existing['id']]);
                } else {
                    // Insert new
                    $stmt = $pdo->prepare("
                        INSERT INTO roster (user_id, date, shift_code_id, created_by, updated_by)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $date, $shiftCodeId, $currentUserId, $currentUserId]);
            }
        }
        
        $pdo->commit();
        return true;
    } catch(PDOException $e) {
        $pdo->rollBack();
        error_log("Error bulk saving roster assignments: " . $e->getMessage());
        return false;
    }
}

// Role Management Functions
function getAllRoles() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($column) {
            // Extract ENUM values from the column definition
            preg_match_all("/'([^']+)'/", $column['Type'], $matches);
            return $matches[1];
        }
        return [];
    } catch(PDOException $e) {
        return [];
    }
}

function addRole($roleName) {
    try {
        $pdo = getDBConnection();
        
        // Get current ENUM values
        $currentRoles = getAllRoles();
        
        // Check if role already exists
        if (in_array($roleName, $currentRoles)) {
            return false; // Role already exists
        }
        
        // Add new role to ENUM
        $newRoles = array_merge($currentRoles, [$roleName]);
        $enumValues = "'" . implode("','", $newRoles) . "'";
        
        $sql = "ALTER TABLE users MODIFY COLUMN role ENUM($enumValues) DEFAULT 'employee'";
        $pdo->exec($sql);
        
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

function deleteRole($roleName) {
    try {
        $pdo = getDBConnection();
        
        // Check if any users have this role
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
        $stmt->execute([$roleName]);
        $userCount = $stmt->fetchColumn();
        
        if ($userCount > 0) {
            return false; // Cannot delete role that is in use
        }
        
        // Get current ENUM values
        $currentRoles = getAllRoles();
        
        // Remove the role from ENUM
        $newRoles = array_filter($currentRoles, function($role) use ($roleName) {
            return $role !== $roleName;
        });
        
        if (empty($newRoles)) {
            return false; // Cannot delete all roles
        }
        
        $enumValues = "'" . implode("','", $newRoles) . "'";
        
        $sql = "ALTER TABLE users MODIFY COLUMN role ENUM($enumValues) DEFAULT 'employee'";
        $pdo->exec($sql);
        
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

function updateRole($oldRole, $newRole) {
    try {
        $pdo = getDBConnection();
        
        // Get current ENUM values
        $currentRoles = getAllRoles();
        
        // Check if new role already exists
        if (in_array($newRole, $currentRoles)) {
            return false; // New role already exists
        }
        
        // Update users with old role to new role
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE role = ?");
        $stmt->execute([$newRole, $oldRole]);
        
        // Update ENUM definition
        $newRoles = array_map(function($role) use ($oldRole, $newRole) {
            return $role === $oldRole ? $newRole : $role;
        }, $currentRoles);
        
        $enumValues = "'" . implode("','", $newRoles) . "'";
        
        $sql = "ALTER TABLE users MODIFY COLUMN role ENUM($enumValues) DEFAULT 'employee'";
        $pdo->exec($sql);
        
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

function getRoleUsageCount($roleName) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
        $stmt->execute([$roleName]);
        return $stmt->fetchColumn();
    } catch(PDOException $e) {
        return 0;
    }
}

// Roles Table Functions (for the roles table)
function getAllRolesFromTable() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT * FROM roles WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

function getRoleById($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return null;
    }
}

function getRoleByName($name) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return null;
    }
}

function createRole($data) {
    try {
        $pdo = getDBConnection();
        
        // Check if role name already exists
        $existingRole = getRoleByName($data['name']);
        if ($existingRole) {
            return ['success' => false, 'message' => 'Role name already exists'];
        }
        
        $sql = "INSERT INTO roles (name, display_name, description, color, permissions, can_manage_users, can_manage_aircraft, can_manage_personnel, can_manage_fleet, can_view_reports, can_manage_system, can_manage_roles, is_system_role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['name'],
            $data['display_name'] ?? $data['name'],
            $data['description'] ?? null,
            $data['color'] ?? '#3B82F6',
            $data['permissions'] ?? null,
            $data['can_manage_users'] ?? 0,
            $data['can_manage_aircraft'] ?? 0,
            $data['can_manage_personnel'] ?? 0,
            $data['can_manage_fleet'] ?? 0,
            $data['can_view_reports'] ?? 0,
            $data['can_manage_system'] ?? 0,
            $data['can_manage_roles'] ?? 0,
            $data['is_system_role'] ?? 0,
            $data['is_active'] ?? 1
        ]);
        
        if ($result) {
            return ['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Role created successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to create role'];
        }
    } catch(PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function updateRoleInTable($id, $data) {
    try {
        $pdo = getDBConnection();
        
        // Check if role name already exists (excluding current role)
        if (isset($data['name'])) {
            $existingRole = getRoleByName($data['name']);
            if ($existingRole && $existingRole['id'] != $id) {
                return ['success' => false, 'message' => 'Role name already exists'];
            }
        }
        
        $fields = [];
        $values = [];
        
        $allowedFields = ['name', 'display_name', 'description', 'color', 'permissions', 'can_manage_users', 'can_manage_aircraft', 'can_manage_personnel', 'can_manage_fleet', 'can_view_reports', 'can_manage_system', 'can_manage_roles', 'is_system_role', 'is_active'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return ['success' => false, 'message' => 'No fields to update'];
        }
        
        $values[] = $id;
        $sql = "UPDATE roles SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            return ['success' => true, 'message' => 'Role updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update role'];
        }
    } catch(PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function deleteRoleFromTable($id) {
    try {
        $pdo = getDBConnection();
        
        // Check if role is in use
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
        $stmt->execute([$id]);
        $userCount = $stmt->fetchColumn();
        
        if ($userCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete role that is in use by ' . $userCount . ' user(s)'];
        }
        
        // Check if it's a system role
        $stmt = $pdo->prepare("SELECT is_system_role FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($role && $role['is_system_role']) {
            return ['success' => false, 'message' => 'Cannot delete system role'];
        }
        
        // Delete the role
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Role deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete role'];
        }
    } catch(PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function getRoleUsageCountFromTable($roleId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
        $stmt->execute([$roleId]);
        return $stmt->fetchColumn();
    } catch(PDOException $e) {
        return 0;
    }
}

// Page Permission Functions
function getPagePermission($pagePath) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM page_permissions WHERE page_path = ?");
        $stmt->execute([$pagePath]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return null;
    }
}

function getAllPagePermissions() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT * FROM page_permissions ORDER BY page_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

function updatePagePermission($id, $requiredRoles, $description = null, $pageName = null) {
    try {
        $pdo = getDBConnection();
        
        $fields = [];
        $values = [];
        
        $fields[] = "required_roles = ?";
        $values[] = $requiredRoles;
        
        if ($description !== null) {
            $fields[] = "description = ?";
            $values[] = $description;
        }
        
        if ($pageName !== null) {
            $fields[] = "page_name = ?";
            $values[] = $pageName;
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $id;
        
        $sql = "UPDATE page_permissions SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    } catch(PDOException $e) {
        return false;
    }
}

// Personnel Recency Management Functions
function getAllPersonnelRecency($limit = null, $offset = 0, $search = []) {
    try {
        $pdo = getDBConnection();
        
        $whereConditions = [];
        $params = [];
        
        // Add search conditions
        if (!empty($search['quick_search'])) {
            // Quick search across name fields
            $whereConditions[] = "(FirstName LIKE ? OR LastName LIKE ? OR Name LIKE ?)";
            $searchTerm = '%' . $search['quick_search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        } else {
            // Individual field searches
            if (!empty($search['first_name'])) {
                $whereConditions[] = "FirstName LIKE ?";
                $params[] = '%' . $search['first_name'] . '%';
            }
            
            if (!empty($search['last_name'])) {
                $whereConditions[] = "LastName LIKE ?";
                $params[] = '%' . $search['last_name'] . '%';
            }
            
            if (!empty($search['name'])) {
                $whereConditions[] = "Name LIKE ?";
                $params[] = '%' . $search['name'] . '%';
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT * FROM recencypersonnel $whereClause ORDER BY LastUpdated DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

function getPersonnelRecencyCount($search = []) {
    try {
        $pdo = getDBConnection();
        
        $whereConditions = [];
        $params = [];
        
        // Add search conditions
        if (!empty($search['quick_search'])) {
            // Quick search across name fields
            $whereConditions[] = "(FirstName LIKE ? OR LastName LIKE ? OR Name LIKE ?)";
            $searchTerm = '%' . $search['quick_search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        } else {
            // Individual field searches
            if (!empty($search['first_name'])) {
                $whereConditions[] = "FirstName LIKE ?";
                $params[] = '%' . $search['first_name'] . '%';
            }
            
            if (!empty($search['last_name'])) {
                $whereConditions[] = "LastName LIKE ?";
                $params[] = '%' . $search['last_name'] . '%';
            }
            
            if (!empty($search['name'])) {
                $whereConditions[] = "Name LIKE ?";
                $params[] = '%' . $search['name'] . '%';
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT COUNT(*) as count FROM recencypersonnel $whereClause";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    } catch(PDOException $e) {
        return 0;
    }
}

function getPersonnelRecencyById($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM recencypersonnel WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return null;
    }
}

function createPersonnelRecency($data) {
    try {
        $pdo = getDBConnection();
        $sql = "INSERT INTO recencypersonnel (
            RecencyPersonnelItemID, RecencyItemID, PersonnelID, TypeCode, 
            LastUpdated, Expires, Value, ModifiedBy, ModifiedAt, CFMaster, 
            DocID, DocName, Name, LastName, FirstName, HomeBaseID, 
            HomeDepartmentID, PrimaryDepartmentName, BaseName, BaseShortName, 
            IntegrationReferenceCode
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['RecencyPersonnelItemID'] ?? null,
            $data['RecencyItemID'] ?? null,
            $data['PersonnelID'] ?? null,
            $data['TypeCode'] ?? null,
            $data['LastUpdated'] ?? null,
            $data['Expires'] ?? null,
            $data['Value'] ?? null,
            $data['ModifiedBy'] ?? null,
            $data['ModifiedAt'] ?? null,
            $data['CFMaster'] ?? null,
            $data['DocID'] ?? null,
            $data['DocName'] ?? null,
            $data['Name'] ?? null,
            $data['LastName'] ?? null,
            $data['FirstName'] ?? null,
            $data['HomeBaseID'] ?? null,
            $data['HomeDepartmentID'] ?? null,
            $data['PrimaryDepartmentName'] ?? null,
            $data['BaseName'] ?? null,
            $data['BaseShortName'] ?? null,
            $data['IntegrationReferenceCode'] ?? null
        ]);
    } catch(PDOException $e) {
        return false;
    }
}

function updatePersonnelRecency($id, $data) {
    try {
        $pdo = getDBConnection();
        $sql = "UPDATE recencypersonnel SET 
            RecencyPersonnelItemID = ?, RecencyItemID = ?, PersonnelID = ?, TypeCode = ?, 
            LastUpdated = ?, Expires = ?, Value = ?, ModifiedBy = ?, ModifiedAt = ?, CFMaster = ?, 
            DocID = ?, DocName = ?, Name = ?, LastName = ?, FirstName = ?, HomeBaseID = ?, 
            HomeDepartmentID = ?, PrimaryDepartmentName = ?, BaseName = ?, BaseShortName = ?, 
            IntegrationReferenceCode = ?
            WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['RecencyPersonnelItemID'] ?? null,
            $data['RecencyItemID'] ?? null,
            $data['PersonnelID'] ?? null,
            $data['TypeCode'] ?? null,
            $data['LastUpdated'] ?? null,
            $data['Expires'] ?? null,
            $data['Value'] ?? null,
            $data['ModifiedBy'] ?? null,
            $data['ModifiedAt'] ?? null,
            $data['CFMaster'] ?? null,
            $data['DocID'] ?? null,
            $data['DocName'] ?? null,
            $data['Name'] ?? null,
            $data['LastName'] ?? null,
            $data['FirstName'] ?? null,
            $data['HomeBaseID'] ?? null,
            $data['HomeDepartmentID'] ?? null,
            $data['PrimaryDepartmentName'] ?? null,
            $data['BaseName'] ?? null,
            $data['BaseShortName'] ?? null,
            $data['IntegrationReferenceCode'] ?? null,
            $id
        ]);
    } catch(PDOException $e) {
        return false;
    }
}

function deletePersonnelRecency($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM recencypersonnel WHERE id = ?");
        return $stmt->execute([$id]);
    } catch(PDOException $e) {
        return false;
    }
}

function hasPageAccess($pagePath, $userRole = null) {
    if ($userRole === null) {
        $current_user = getCurrentUser();
        $userRole = $current_user['role_name'] ?? 'employee';
    }
    
    $permission = getPagePermission($pagePath);
    if (!$permission) {
        // If no permission defined, deny access by default (secure behavior)
        return false;
    }
    
    $requiredRoles = json_decode($permission['required_roles'], true);
    if (!$requiredRoles || !is_array($requiredRoles)) {
        // If required_roles is invalid, deny access
        return false;
    }
    
    return in_array($userRole, $requiredRoles);
}

function checkPageAccess($pagePath, $userRole = null) {
    if (!hasPageAccess($pagePath, $userRole)) {
        header('Location: /login.php');
        exit();
    }
}

function getCurrentPagePath() {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $path = str_replace('/', '', $scriptName);
    return ltrim($path, '/');
}

function scanForNewPages() {
    $db = getDBConnection();
    $newPages = [];
    
    // Define directories to scan
    $directories = [
        'dashboard/',
        'admin/',
        'admin/users/',
        'admin/users/personnel_recency/',
        'admin/roles/',
        'admin/fleet/',
        'admin/fleet/aircraft/',
        'admin/profile/'
    ];
    
    foreach ($directories as $dir) {
        $fullPath = __DIR__ . '/' . $dir;
        if (is_dir($fullPath)) {
            $files = glob($fullPath . '*.php');
            foreach ($files as $file) {
                $relativePath = str_replace(__DIR__ . '/', '', $file);
                
                // Check if this page already exists in permissions
                $stmt = $db->prepare("SELECT id FROM page_permissions WHERE page_path = ?");
                $stmt->execute([$relativePath]);
                
                if (!$stmt->fetch()) {
                    $newPages[] = [
                        'path' => $relativePath,
                        'name' => generatePageName($relativePath),
                        'description' => generatePageDescription($relativePath)
                    ];
                }
            }
        }
    }
    
    return $newPages;
}

function generatePageName($path) {
    $name = basename($path, '.php');
    $name = str_replace(['_', '-'], ' ', $name);
    $name = ucwords($name);
    
    // Special cases
    $specialNames = [
        'index' => 'List',
        'add' => 'Add New',
        'edit' => 'Edit',
        'profile' => 'Profile',
        'personnel_recency' => 'Personnel Recency'
    ];
    
    if (isset($specialNames[$name])) {
        $name = $specialNames[$name];
    }
    
    return $name;
}

function generatePageDescription($path) {
    $descriptions = [
        'dashboard/index.php' => 'Main dashboard page',
        'admin/users/index.php' => 'List all users',
        'admin/users/add.php' => 'Add new user',
        'admin/users/edit.php' => 'Edit user information',
        'admin/users/personnel_recency/index.php' => 'List personnel recency records',
        'admin/users/personnel_recency/add.php' => 'Add new personnel recency record',
        'admin/users/personnel_recency/edit.php' => 'Edit personnel recency record',
        'admin/roles/index.php' => 'Manage user roles',
        'admin/role_permission.php' => 'Manage page permissions',
        'admin/fleet/aircraft/index.php' => 'List all aircraft',
        'admin/fleet/aircraft/edit.php' => 'Edit aircraft information',
        'admin/profile/index.php' => 'User profile page',
        'admin/logout.php' => 'Logout confirmation page'
    ];
    
    return $descriptions[$path] ?? 'System page';
}

function addNewPagePermission($pagePath, $pageName, $requiredRoles = ['admin'], $description = '') {
    $db = getDBConnection();
    
    try {
        // Check if page already exists
        $existing = getPagePermission($pagePath);
        if ($existing) {
            return false; // Already exists
        }
        
        $stmt = $db->prepare("INSERT INTO page_permissions (page_path, page_name, required_roles, description) VALUES (?, ?, ?, ?)");
        $rolesJson = json_encode($requiredRoles);
        $result = $stmt->execute([$pagePath, $pageName, $rolesJson, $description]);
        
        if (!$result) {
            error_log("Failed to add page permission: " . print_r($stmt->errorInfo(), true));
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error adding page permission: " . $e->getMessage());
        return false;
    }
}

function addAllNewPages($defaultRoles = ['admin']) {
    $newPages = scanForNewPages();
    $added = 0;
    
    foreach ($newPages as $page) {
        if (addNewPagePermission($page['path'], $page['name'], $defaultRoles, $page['description'])) {
            $added++;
        }
    }
    
    return $added;
}

// Flight Management Functions
function getAllFlights($limit = 50, $offset = 0) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM flights ORDER BY TaskStart DESC LIMIT " . intval($limit) . " OFFSET " . intval($offset));
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFlightById($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM flights WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateFlight($id, $data) {
    $db = getDBConnection();
    
    // Build dynamic update query
    $fields = [];
    $values = [];
    
    // Get column names from database to validate field names
    static $validColumns = null;
    if ($validColumns === null) {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM flights");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $validColumns = array_flip($columns);
        } catch (PDOException $e) {
            error_log("Error getting column names: " . $e->getMessage());
            $validColumns = [];
        }
    }
    
    foreach ($data as $key => $value) {
        if ($key !== 'id') {
            // Skip invalid column names
            if (!empty($validColumns) && !isset($validColumns[$key])) {
                error_log("updateFlight: Skipping invalid column '$key' for flight ID $id");
                continue;
            }
            
            $fields[] = "`$key` = ?";
            // Convert empty strings to null for nullable fields
            $values[] = ($value === '' || $value === null) ? null : $value;
        }
    }
    
    if (empty($fields)) {
        error_log("updateFlight: No valid fields to update for flight ID $id");
        return false;
    }
    
    $values[] = $id;
    $sql = "UPDATE flights SET " . implode(', ', $fields) . " WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($values);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            $errorMessage = isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error';
            error_log("updateFlight failed for flight ID $id. SQL: $sql");
            error_log("updateFlight error: " . json_encode($errorInfo));
            error_log("updateFlight values: " . json_encode($values));
            
            // Store error message in session for display
            @session_start();
            $_SESSION['update_flight_error'] = $errorMessage;
            
            return false;
        }
        
        return true;
    } catch (PDOException $e) {
        $errorMessage = $e->getMessage();
        error_log("updateFlight PDOException for flight ID $id: " . $errorMessage);
        error_log("updateFlight SQL: $sql");
        error_log("updateFlight values: " . json_encode($values));
        
        // Store error message in session for display
        @session_start();
        $_SESSION['update_flight_error'] = $errorMessage;
        
        return false;
    }
}

/**
 * Log flight changes to JSON file
 * @param int $flightId Flight ID
 * @param array $oldData Original flight data
 * @param array $newData Updated flight data
 * @param array $user Current user data
 * @return bool Success status
 */
function logFlightChanges($flightId, $oldData, $newData, $user) {
    // Compare old and new data to find changes
    $changes = [];
    $changedFields = [];
    
    foreach ($newData as $field => $newValue) {
        $oldValue = $oldData[$field] ?? null;
        
        // Normalize values for comparison (handle null, empty string, etc.)
        $oldNormalized = ($oldValue === null || $oldValue === '') ? null : $oldValue;
        $newNormalized = ($newValue === null || $newValue === '') ? null : $newValue;
        
        // Check if value actually changed
        if ($oldNormalized != $newNormalized) {
            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue
            ];
            $changedFields[] = $field;
        }
    }
    
    // Only log if there are actual changes
    if (empty($changes)) {
        return true;
    }
    
    // Prepare log entry
    $logEntry = [
        'id' => uniqid('log_', true),
        'flight_id' => $flightId,
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => [
            'id' => $user['id'] ?? null,
            'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'username' => $user['username'] ?? null,
            'role' => $user['role'] ?? null
        ],
        'changed_fields' => $changedFields,
        'changes' => $changes
    ];
    
    // Ensure log directory exists
    $logDir = __DIR__ . '/full_log';
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true)) {
            error_log("Failed to create log directory: $logDir");
            return false;
        }
    }
    
    // Log file path
    $logFile = $logDir . '/flight_log.json';
    
    // Read existing logs
    $logs = [];
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        if (!empty($content)) {
            $logs = json_decode($content, true);
            if (!is_array($logs)) {
                $logs = [];
            }
        }
    }
    
    // Add new log entry at the beginning
    array_unshift($logs, $logEntry);
    
    // Keep only last 10000 entries to prevent file from growing too large
    if (count($logs) > 10000) {
        $logs = array_slice($logs, 0, 10000);
    }
    
    // Write back to file
    $json = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($logFile, $json) === false) {
        error_log("Failed to write flight log: $logFile");
        return false;
    }
    
    return true;
}

function createFlight($data) {
    $db = getDBConnection();
    
    $fields = array_keys($data);
    $placeholders = array_fill(0, count($fields), '?');
    $values = array_values($data);
    
    $sql = "INSERT INTO flights (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    
    try {
        $stmt = $db->prepare($sql);
        if ($stmt->execute($values)) {
            return $db->lastInsertId();
        }
        
        // Log SQL error if execution fails
        $errorInfo = $stmt->errorInfo();
        logFlightError([
            'action' => 'createFlight',
            'sql' => $sql,
            'values' => $values,
            'error_code' => $errorInfo[0],
            'error_message' => $errorInfo[2],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return false;
    } catch (PDOException $e) {
        // Log PDO exception
        logFlightError([
            'action' => 'createFlight',
            'sql' => $sql,
            'values' => $values,
            'error_code' => $e->getCode(),
            'error_message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return false;
    }
}

function deleteFlight($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM flights WHERE id = ?");
    return $stmt->execute([$id]);
}

function getFlightsCount() {
    $db = getDBConnection();
    $stmt = $db->query("SELECT COUNT(*) FROM flights");
    return $stmt->fetchColumn();
}

function getFlightsByDateRange($startDate, $endDate) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM flights WHERE FltDate BETWEEN ? AND ? ORDER BY FltDate DESC");
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFlightsByAircraft($aircraftId) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM flights WHERE AircraftID = ? ORDER BY FltDate DESC");
    $stmt->execute([$aircraftId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFlightsByPilot($pilotId) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM flights WHERE CmdPilotID = ? ORDER BY FltDate DESC");
    $stmt->execute([$pilotId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFlightStats() {
    $db = getDBConnection();
    
    $stats = [];
    
    // Total flights
    $stmt = $db->query("SELECT COUNT(*) FROM flights");
    $stats['total_flights'] = $stmt->fetchColumn();
    
    // Total flight hours
    $stmt = $db->query("SELECT SUM(FlightHours) FROM flights WHERE FlightHours IS NOT NULL");
    $stats['total_hours'] = $stmt->fetchColumn() ?: 0;
    
    // Flights this month
    $stmt = $db->query("SELECT COUNT(*) FROM flights WHERE MONTH(FltDate) = MONTH(CURRENT_DATE()) AND YEAR(FltDate) = YEAR(CURRENT_DATE())");
    $stats['flights_this_month'] = $stmt->fetchColumn();
    
    // Active aircraft count
    $stmt = $db->query("SELECT COUNT(DISTINCT AircraftID) FROM flights WHERE AircraftID IS NOT NULL");
    $stats['active_aircraft'] = $stmt->fetchColumn();
    
    return $stats;
}

// Crew Management Functions
function getAllCrewMembers() {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.position, r.name as role_name, r.display_name as role_display_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.status = 'active' 
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add role field for backward compatibility
    foreach ($results as &$result) {
        $result['role'] = $result['role_name'] ?? 'employee';
    }
    
    return $results;
}

function getCrewMembersByRole($roles = ['pilot', 'crew']) {
    $db = getDBConnection();
    $placeholders = str_repeat('?,', count($roles) - 1) . '?';
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.position, r.name as role_name, r.display_name as role_display_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.status = 'active' 
        AND r.name IN ($placeholders) 
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute($roles);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add role field for backward compatibility
    foreach ($results as &$result) {
        $result['role'] = $result['role_name'] ?? 'employee';
    }
    
    return $results;
}

function getCrewMemberById($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.position, r.name as role_name, r.display_name as role_display_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Add role field for backward compatibility
    if ($result) {
        $result['role'] = $result['role_name'] ?? 'employee';
    }
    
    return $result;
}

function formatCrewNames($crewIds) {
    if (empty($crewIds)) {
        return '';
    }
    
    $crewIds = is_string($crewIds) ? explode(',', $crewIds) : $crewIds;
    $crewNames = [];
    
    foreach ($crewIds as $id) {
        $id = trim($id);
        if (!empty($id)) {
            $crew = getCrewMemberById($id);
            if ($crew) {
                $crewNames[] = $crew['first_name'] . ' ' . $crew['last_name'];
            }
        }
    }
    
    return implode(', ', $crewNames);
}

function parseCrewIds($crewString) {
    if (empty($crewString)) {
        return [];
    }
    
    // Split by comma and clean up
    $ids = array_map('trim', explode(',', $crewString));
    return array_filter($ids, function($id) {
        return !empty($id) && is_numeric($id);
    });
}

function parseCrewNames($crewString) {
    if (empty($crewString)) {
        return [];
    }
    
    // Split by comma and clean up
    $names = array_map('trim', explode(',', $crewString));
    return array_filter($names, function($name) {
        return !empty($name);
    });
}

// ==================== INDIVIDUAL ACCESS FUNCTIONS ====================

/**
 * Grant individual access to a specific user for a specific page
 */
function grantIndividualAccess($pagePath, $userId, $grantedBy, $expiresAt = null, $notes = null) {
    $db = getDBConnection();
    
    // Check if access already exists
    $stmt = $db->prepare("SELECT id FROM individual_access WHERE page_path = ? AND user_id = ?");
    $stmt->execute([$pagePath, $userId]);
    
    if ($stmt->fetch()) {
        // Update existing access
        $stmt = $db->prepare("UPDATE individual_access SET granted_by = ?, expires_at = ?, notes = ?, is_active = 1, granted_at = NOW() WHERE page_path = ? AND user_id = ?");
        return $stmt->execute([$grantedBy, $expiresAt, $notes, $pagePath, $userId]);
    } else {
        // Create new access
        $stmt = $db->prepare("INSERT INTO individual_access (page_path, user_id, granted_by, expires_at, notes) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$pagePath, $userId, $grantedBy, $expiresAt, $notes]);
    }
}

/**
 * Revoke individual access for a specific user and page
 */
function revokeIndividualAccess($pagePath, $userId) {
    $db = getDBConnection();
    $stmt = $db->prepare("UPDATE individual_access SET is_active = 0 WHERE page_path = ? AND user_id = ?");
    return $stmt->execute([$pagePath, $userId]);
}

/**
 * Check if a user has individual access to a specific page
 */
function hasIndividualAccess($pagePath, $userId) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id FROM individual_access WHERE page_path = ? AND user_id = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->execute([$pagePath, $userId]);
    return $stmt->fetch() !== false;
}

/**
 * Get all individual access records for a specific page
 */
function getIndividualAccessForPage($pagePath) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT ia.*, u.first_name, u.last_name, u.position, r.name as role_name, r.display_name as role_display_name,
               gb.first_name as granted_by_first_name, gb.last_name as granted_by_last_name
        FROM individual_access ia
        JOIN users u ON ia.user_id = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        JOIN users gb ON ia.granted_by = gb.id
        WHERE ia.page_path = ? AND ia.is_active = 1
        ORDER BY ia.granted_at DESC
    ");
    $stmt->execute([$pagePath]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add role field for backward compatibility
    foreach ($results as &$result) {
        $result['role'] = $result['role_name'] ?? 'employee';
    }
    
    return $results;
}

/**
 * Get all individual access records for a specific user
 */
function getIndividualAccessForUser($userId) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT ia.*, pp.page_name, pp.description,
               gb.first_name as granted_by_first_name, gb.last_name as granted_by_last_name
        FROM individual_access ia
        LEFT JOIN page_permissions pp ON ia.page_path = pp.page_path
        JOIN users gb ON ia.granted_by = gb.id
        WHERE ia.user_id = ? AND ia.is_active = 1
        ORDER BY ia.granted_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all users who don't have individual access to a specific page
 */
function getUsersWithoutIndividualAccess($pagePath) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.position, r.name as role_name, r.display_name as role_display_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.status = 'active' 
        AND u.id NOT IN (
            SELECT user_id FROM individual_access 
            WHERE page_path = ? AND is_active = 1
        )
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$pagePath]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add role field for backward compatibility
    foreach ($results as &$result) {
        $result['role'] = $result['role_name'] ?? 'employee';
    }
    
    return $results;
}

/**
 * Enhanced page access check that considers both role-based and individual access
 */
function hasPageAccessEnhanced($pagePath, $userRole, $userId = null) {
    // First check individual access (higher priority)
    if ($userId && hasIndividualAccess($pagePath, $userId)) {
        return true;
    }
    
    // Then check role-based access
    return hasPageAccess($pagePath, $userRole);
}

/**
 * Enhanced page access check for current user
 */
function checkPageAccessEnhanced($pagePath) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $current_user = getCurrentUser();
    return hasPageAccessEnhanced($pagePath, $current_user['role_name'] ?? 'employee', $current_user['id']);
}

/**
 * Check page access and redirect to access denied page if denied
 */
function checkPageAccessWithRedirect($pagePath) {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }
    
    $current_user = getCurrentUser();
    $hasAccess = hasPageAccessEnhanced($pagePath, $current_user['role_name'] ?? 'employee', $current_user['id']);
    
    if (!$hasAccess) {
        $encodedPage = urlencode($pagePath);
        header("Location: /access_denied.php?page=$encodedPage");
        exit();
    }
    
    return true;
}

// ==================== PROFILE IMAGE FUNCTIONS ====================

/**
 * Upload profile image
 */
function uploadProfileImage($file, $userId) {
    try {
        // Check if file was uploaded successfully
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF images are allowed.'];
        }
        
        // Validate file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File size too large. Maximum size is 5MB.'];
        }
        
        // Create uploads directory if it doesn't exist
        $uploadDir = __DIR__ . '/uploads/profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $userId . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Return relative path for database storage
            $relativePath = 'uploads/profile/' . $filename;
            return ['success' => true, 'path' => $relativePath, 'message' => 'Image uploaded successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to move uploaded file.'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Upload error: ' . $e->getMessage()];
    }
}

/**
 * Delete profile image
 */
function deleteProfileImage($imagePath) {
    try {
        if (empty($imagePath)) {
            return true;
        }
        
        $fullPath = __DIR__ . '/' . $imagePath;
        
        // Check if file exists and delete it
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return true; // File doesn't exist, consider it deleted
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get profile image URL
 */
function getProfileImageUrl($imagePath) {
    if (empty($imagePath)) {
        return '/assets/images/default-avatar.svg';
    }
    
    $fullPath = __DIR__ . '/' . $imagePath;
    
    // Check if file exists
    if (file_exists($fullPath)) {
        return '/' . $imagePath;
    }
    
    // Return default avatar if file doesn't exist
    return '/assets/images/default-avatar.svg';
}

// ==================== FLIGHT MONITORING FUNCTIONS ====================

/**
 * Get flights for monitoring by date
 */
function getFlightsForMonitoring($date, $user_id = null) {
    $db = getDBConnection();
    
    $whereConditions = ["DATE(FltDate) = ?"];
    $params = [$date];
    
    // If user_id is provided, filter by user's flights
    if ($user_id) {
        $whereConditions[] = "CmdPilotID = ?";
        $params[] = $user_id;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    $sql = "SELECT f.*, 
                   f.Rego as aircraft_rego,
                   f.ACType as aircraft_type,
                   f.FirstName as pilot_first_name, 
                   f.LastName as pilot_last_name,
                   f.minutes_1,
                   f.minutes_2,
                   f.minutes_3,
                   f.minutes_4,
                   f.minutes_5
            FROM flights f
            $whereClause
            ORDER BY f.TaskStart ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calculate total delay minutes for a flight
 */
function calculateFlightDelay($flight) {
    $delay_minutes = 0;
    
    // Sum all delay minutes fields (they are stored as varchar)
    $delay_minutes += intval($flight['minutes_1'] ?? 0);
    $delay_minutes += intval($flight['minutes_2'] ?? 0);
    $delay_minutes += intval($flight['minutes_3'] ?? 0);
    $delay_minutes += intval($flight['minutes_4'] ?? 0);
    $delay_minutes += intval($flight['minutes_5'] ?? 0);
    
    return $delay_minutes;
}

/**
 * Get flight monitoring statistics
 */
function getFlightMonitoringStats($date) {
    $db = getDBConnection();
    
    $stats = [];
    
    // Total flights for the date
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM flights WHERE DATE(FltDate) = ?");
    $stmt->execute([$date]);
    $stats['total_flights'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Departed flights (assuming TaskStart is not null means departed)
    $stmt = $db->prepare("SELECT COUNT(*) as departed FROM flights WHERE DATE(FltDate) = ? AND TaskStart IS NOT NULL");
    $stmt->execute([$date]);
    $stats['departed'] = $stmt->fetch(PDO::FETCH_ASSOC)['departed'];
    
    // Arrived flights (assuming TaskEnd is not null means arrived)
    $stmt = $db->prepare("SELECT COUNT(*) as arrived FROM flights WHERE DATE(FltDate) = ? AND TaskEnd IS NOT NULL");
    $stmt->execute([$date]);
    $stats['arrived'] = $stmt->fetch(PDO::FETCH_ASSOC)['arrived'];
    
    // Canceled flights (assuming no TaskStart means canceled)
    $stmt = $db->prepare("SELECT COUNT(*) as canceled FROM flights WHERE DATE(FltDate) = ? AND TaskStart IS NULL");
    $stmt->execute([$date]);
    $stats['canceled'] = $stmt->fetch(PDO::FETCH_ASSOC)['canceled'];
    
    // Calculate total delay from minutes fields
    $stmt = $db->prepare("
        SELECT 
            SUM(COALESCE(minutes_1, 0) + 
                COALESCE(minutes_2, 0) + 
                COALESCE(minutes_3, 0) + 
                COALESCE(minutes_4, 0) + 
                COALESCE(minutes_5, 0)) as total_delay_minutes
        FROM flights 
        WHERE DATE(FltDate) = ?
    ");
    $stmt->execute([$date]);
    $total_delay_minutes = $stmt->fetch(PDO::FETCH_ASSOC)['total_delay_minutes'] ?? 0;
    
    // Count delayed flights (flights with any delay)
    $stmt = $db->prepare("
        SELECT COUNT(*) as delayed_count 
        FROM flights 
        WHERE DATE(FltDate) = ? 
        AND (COALESCE(minutes_1, 0) + 
             COALESCE(minutes_2, 0) + 
             COALESCE(minutes_3, 0) + 
             COALESCE(minutes_4, 0) + 
             COALESCE(minutes_5, 0)) > 0
    ");
    $stmt->execute([$date]);
    $stats['delayed'] = $stmt->fetch(PDO::FETCH_ASSOC)['delayed_count'];
    
    // Convert total delay minutes to hours and minutes
    $stats['total_delay_hours'] = floor($total_delay_minutes / 60);
    $stats['total_delay_minutes'] = $total_delay_minutes % 60;
    
    // Total passengers
    $stmt = $db->prepare("SELECT SUM(CAST(total_pax AS UNSIGNED)) as total_pax FROM flights WHERE DATE(FltDate) = ? AND total_pax IS NOT NULL AND total_pax != ''");
    $stmt->execute([$date]);
    $stats['total_pax'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_pax'] ?? 0;
    
    // Total block time
    $stmt = $db->prepare("SELECT SUM(TIMESTAMPDIFF(MINUTE, TaskStart, TaskEnd)) as total_block_time FROM flights WHERE DATE(FltDate) = ? AND TaskStart IS NOT NULL AND TaskEnd IS NOT NULL");
    $stmt->execute([$date]);
    $total_block_minutes = $stmt->fetch(PDO::FETCH_ASSOC)['total_block_time'] ?? 0;
    $stats['total_block_hours'] = floor($total_block_minutes / 60);
    $stats['total_block_minutes'] = $total_block_minutes % 60;
    
    return $stats;
}

/**
 * Get aircraft list for timeline
 */
function getAircraftForTimeline($date) {
    $db = getDBConnection();
    
    $sql = "SELECT DISTINCT f.Rego as registration, f.ACType as aircraft_type, f.AircraftID as id
            FROM flights f
            WHERE DATE(f.FltDate) = ? AND f.Rego IS NOT NULL
            ORDER BY f.Rego";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get flight status color for timeline
 */
function getFlightStatusColor($status, $delay_minutes = 0) {
    if ($delay_minutes > 30) {
        return 'bg-red-500'; // Delayed
    } elseif ($delay_minutes > 15) {
        return 'bg-orange-500'; // Slightly delayed
    }
    
    switch ($status) {
        case 'scheduled':
            return 'bg-blue-500';
        case 'departed':
            return 'bg-green-500';
        case 'arrived':
            return 'bg-green-600';
        case 'canceled':
            return 'bg-gray-500';
        case 'delayed':
            return 'bg-red-500';
        default:
            return 'bg-blue-500';
    }
}

/**
 * Format time for timeline display
 */
function formatTimeForTimeline($time) {
    if (!$time) return '';
    return date('H:i', strtotime($time));
}

/**
 * Calculate flight duration in hours
 */
function calculateFlightDuration($departure_time, $arrival_time) {
    if (!$departure_time || !$arrival_time) return 0;
    
    $departure = new DateTime($departure_time);
    $arrival = new DateTime($arrival_time);
    $diff = $departure->diff($arrival);
    
    return round($diff->h + ($diff->i / 60), 1);
}

/**
 * Get flight details for modal
 */
function getFlightDetailsForModal($flight_id) {
    $db = getDBConnection();
    
    $sql = "SELECT f.*, 
                   f.Rego as aircraft_rego,
                   f.ACType as aircraft_type,
                   f.FirstName as pilot_first_name, 
                   f.LastName as pilot_last_name,
                   f.OtherCrew as other_crew,
                   f.AllCrew as all_crew,
                   f.ScheduledTaskStatus as status
            FROM flights f
            WHERE f.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$flight_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ==================== CREW SCHEDULING FUNCTIONS ====================

/**
 * Get flights for crew scheduling with aircraft information
 */
function getFlightsForCrewScheduling($date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT f.*, 
               a.registration as aircraft_registration,
               a.aircraft_type,
               a.manufacturer
        FROM flights f
        LEFT JOIN aircraft a ON f.AircraftID = a.id
        WHERE DATE(f.FltDate) = ?
        ORDER BY f.FltDate ASC, f.FlightNo ASC
    ");
    $stmt->execute([$date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get flights grouped by date for crew scheduling
 */
function getFlightsGroupedByDate($startDate = null, $endDate = null) {
    if (!$startDate) {
        $startDate = date('Y-m-d');
    }
    if (!$endDate) {
        $endDate = $startDate;
    }
    
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT f.*, 
               a.registration as aircraft_registration,
               a.aircraft_type,
               a.manufacturer,
               a.serial_number,
               a.aircraft_category,
               a.base_location,
               a.responsible_personnel,
               a.aircraft_owner,
               a.aircraft_operator,
               a.date_of_manufacture,
               a.nvfr,
               a.ifr,
               a.spifr,
               a.engine_type,
               a.number_of_engines,
               a.engine_model,
               a.engine_serial_number,
               a.avionics,
               a.other_avionics_information,
               a.internal_configuration,
               a.external_configuration,
               a.airframe_type,
               a.enabled,
               a.status as aircraft_status
        FROM flights f
        LEFT JOIN aircraft a ON f.Rego = a.registration
        WHERE DATE(f.FltDate) BETWEEN ? AND ?
        ORDER BY f.Rego ASC, f.FltDate ASC, f.TaskStart ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by date
    $grouped = [];
    foreach ($flights as $flight) {
        $date = date('Y-m-d', strtotime($flight['FltDate']));
        if (!isset($grouped[$date])) {
            $grouped[$date] = [];
        }
        $grouped[$date][] = $flight;
    }
    
    // Sort flights within each date group by Rego, then FltDate, then TaskStart (ascending)
    foreach ($grouped as $date => &$dateFlights) {
        usort($dateFlights, function($a, $b) {
            // First sort by Rego (aircraft registration)
            $regoA = $a['Rego'] ?? '';
            $regoB = $b['Rego'] ?? '';
            
            if ($regoA !== $regoB) {
                // Handle empty Rego values - put them at the end
                if (empty($regoA) && !empty($regoB)) {
                    return 1;
                }
                if (!empty($regoA) && empty($regoB)) {
                    return -1;
                }
                if (empty($regoA) && empty($regoB)) {
                    return 0;
                }
                // Compare Rego alphabetically
                $regoCompare = strcmp($regoA, $regoB);
                if ($regoCompare !== 0) {
                    return $regoCompare;
                }
            }
            
            // If Rego is the same, sort by FltDate
            $fltDateA = $a['FltDate'] ?? '';
            $fltDateB = $b['FltDate'] ?? '';
            
            if ($fltDateA !== $fltDateB) {
                // Handle empty FltDate values - put them at the end
                if (empty($fltDateA) && !empty($fltDateB)) {
                    return 1;
                }
                if (!empty($fltDateA) && empty($fltDateB)) {
                    return -1;
                }
                if (empty($fltDateA) && empty($fltDateB)) {
                    return 0;
                }
                // Compare FltDate
                $dateA = strtotime($fltDateA);
                $dateB = strtotime($fltDateB);
                
                if ($dateA === false) {
                    return 1;
                }
                if ($dateB === false) {
                    return -1;
                }
                
                $dateCompare = $dateA - $dateB;
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }
            }
            
            // If Rego and FltDate are the same, sort by TaskStart
            $taskStartA = $a['TaskStart'] ?? '';
            $taskStartB = $b['TaskStart'] ?? '';
            
            // Handle empty TaskStart values - put them at the end
            if (empty($taskStartA) && empty($taskStartB)) {
                return 0;
            }
            if (empty($taskStartA)) {
                return 1;
            }
            if (empty($taskStartB)) {
                return -1;
            }
            
            // Compare TaskStart times
            $timeA = strtotime($taskStartA);
            $timeB = strtotime($taskStartB);
            
            if ($timeA === false) {
                return 1;
            }
            if ($timeB === false) {
                return -1;
            }
            
            return $timeA - $timeB;
        });
    }
    unset($dateFlights); // Unset reference
    
    return $grouped;
}

/**
 * Update crew assignment for a flight
 */
function updateCrewAssignment($flightId, $crewData) {
    $db = getDBConnection();
    
    $fields = [];
    $values = [];
    
    // Generate Crew1-Crew10 fields dynamically
    $crewFields = [];
    $roleFields = [];
    for ($i = 1; $i <= 10; $i++) {
        $crewFields[] = "Crew{$i}";
        $roleFields[] = "Crew{$i}_role";
    }
    
    // Process crew fields - allow null values (for clearing)
    foreach ($crewFields as $field) {
        if (array_key_exists($field, $crewData)) {
            $fields[] = "$field = ?";
            // Convert empty string to null for database
            $value = $crewData[$field];
            if ($value === '' || $value === null) {
                $values[] = null;
            } else {
                $values[] = $value;
            }
        }
    }
    
    // Process role fields - allow null values (for clearing)
    foreach ($roleFields as $field) {
        if (array_key_exists($field, $crewData)) {
            $fields[] = "$field = ?";
            // Convert empty string to null for database
            $value = $crewData[$field];
            if ($value === '' || $value === null) {
                $values[] = null;
            } else {
                $values[] = $value;
            }
        }
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $values[] = $flightId;
    $sql = "UPDATE flights SET " . implode(', ', $fields) . " WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($values);
        
        // Log update for debugging
        if (!$result) {
            error_log("Failed to update crew assignment for flight ID $flightId. SQL: $sql, Values: " . json_encode($values) . ", Error: " . json_encode($stmt->errorInfo()));
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Database error in updateCrewAssignment: " . $e->getMessage());
        error_log("SQL: $sql, Values: " . json_encode($values));
        return false;
    }
}

/**
 * Get users for crew selection
 */
function getUsersForCrewSelection() {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.position, r.name as role_name, r.display_name as role_display_name
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.status = 'active' 
        AND u.flight_crew = 1
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add role field for backward compatibility
    foreach ($results as &$result) {
        $result['role'] = $result['role_name'] ?? 'employee';
    }
    
    return $results;
}

/**
 * Get Crew1 (formerly LSP) information from flights table
 */
function getLSPInfoFromFlights($flightId) {
    $db = getDBConnection();
    
    // Get Crew1 and Crew1_role data from flights table (backward compatibility - keeping function name)
    $stmt = $db->prepare("
        SELECT f.Crew1, f.Crew1_role, f.FlightNo
        FROM flights f
        WHERE f.id = ?
    ");
    $stmt->execute([$flightId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $lsp = $result['Crew1'] ?? '';
        $lspRole = $result['Crew1_role'] ?? '';
        $flightNo = $result['FlightNo'] ?? '';
        
        // If Crew1 is a user ID, get user information
        if (!empty($lsp) && is_numeric($lsp)) {
            $stmt = $db->prepare("
                SELECT u.first_name, u.last_name, u.position
                FROM users u
                WHERE u.id = ?
            ");
            $stmt->execute([$lsp]);
            $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userInfo) {
                return [
                    'full_name' => trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')),
                    'position' => $lspRole ?: ($userInfo['position'] ?? 'Pilot'),
                    'flight_no' => $flightNo,
                    'lsp_id' => $lsp
                ];
            }
        }
        
        // Return basic info even if no user found
        return [
            'full_name' => $lsp ?: '',
            'position' => $lspRole ?: 'Pilot',
            'flight_no' => $flightNo,
            'lsp_id' => $lsp
        ];
    }
    
    return null;
}

/**
 * Get all stations for dropdown (IATA codes for flights)
 */
function getStations() {
    $db = getDBConnection();
    
    $stmt = $db->prepare("
        SELECT id, station_name, iata_code, icao_code
        FROM stations
        ORDER BY station_name ASC
    ");
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all countries
 */
function getAllCountries() {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id, name, iso_code_2, iso_code_3 FROM countries ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // If countries table doesn't exist, return empty array
        return [];
    }
}

/**
 * Get stations with ICAO codes for weather API
 */
function getWeatherStations() {
    $db = getDBConnection();
    
    $stmt = $db->prepare("
        SELECT id, station_name, iata_code, icao_code
        FROM stations
        WHERE icao_code IS NOT NULL AND icao_code != ''
        ORDER BY station_name ASC
    ");
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get crew locations for a specific date
 */
function getCrewLocationsForDate($date) {
    $db = getDBConnection();
    
    // Build query to get Crew1-Crew10 fields
    $crewFields = [];
    $crewRoleFields = [];
    for ($i = 1; $i <= 10; $i++) {
        $crewFields[] = "f.Crew{$i}";
        $crewRoleFields[] = "f.Crew{$i}_role";
    }
    $crewFieldsStr = implode(', ', $crewFields);
    $crewRoleFieldsStr = implode(', ', $crewRoleFields);
    
    // Get all flights for the selected date with crew assignments
    $stmt = $db->prepare("
        SELECT f.id as FlightID, f.FlightNo, f.Route, f.FltDate, f.TaskStart, f.TaskEnd,
               $crewFieldsStr, $crewRoleFieldsStr
        FROM flights f
        WHERE DATE(f.FltDate) = ?
        AND (
            f.Crew1 IS NOT NULL OR f.Crew2 IS NOT NULL OR f.Crew3 IS NOT NULL OR 
            f.Crew4 IS NOT NULL OR f.Crew5 IS NOT NULL OR f.Crew6 IS NOT NULL OR 
            f.Crew7 IS NOT NULL OR f.Crew8 IS NOT NULL OR f.Crew9 IS NOT NULL OR 
            f.Crew10 IS NOT NULL
        )
        ORDER BY f.TaskStart ASC, f.FltDate ASC
    ");
    $stmt->execute([$date]);
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($flights)) {
        return [];
    }
    
    // Get all users for lookup
    $usersStmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.position, u.picture
        FROM users u
        WHERE u.status = 'active'
    ");
    $usersStmt->execute();
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create user lookup by ID
    $usersById = [];
    foreach ($users as $user) {
        $usersById[$user['id']] = $user;
    }
    
    // Parse crew information from Crew1-Crew10 fields
    $crewMembers = [];
    
    foreach ($flights as $flight) {
        // Parse route to get origin and destination
        $route = $flight['Route'] ?? '';
        $origin = '';
        $destination = '';
        
        if (strpos($route, '-') !== false) {
            $routeParts = explode('-', $route);
            $origin = trim($routeParts[0] ?? '');
            $destination = trim($routeParts[1] ?? '');
        }
        
        // Process Crew1-Crew10 fields
        for ($i = 1; $i <= 10; $i++) {
            $crewField = "Crew{$i}";
            $crewRoleField = "Crew{$i}_role";
            $crewUserId = $flight[$crewField] ?? null;
            $crewRole = $flight[$crewRoleField] ?? '';
            
            if (empty($crewUserId)) {
                continue;
            }
            
            // Get user information
            $user = $usersById[$crewUserId] ?? null;
            if (!$user) {
                continue;
            }
            
            $crewKey = $crewUserId; // Use user ID as key
            
            // Initialize crew member if not exists
            if (!isset($crewMembers[$crewKey])) {
                $crewMembers[$crewKey] = [
                    'user_id' => $crewUserId,
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'position' => $user['position'] ?? 'Crew Member',
                    'picture' => $user['picture'] ?? null,
                    'flights' => []
                ];
            }
            
            // Add flight to crew member
            $crewMembers[$crewKey]['flights'][] = [
                'flight_no' => $flight['FlightNo'],
                'origin' => $origin,
                'destination' => $destination,
                'role' => $crewRole ?: 'Crew Member',
                'flt_date' => $flight['FltDate'],
                'task_start' => $flight['TaskStart'],
                'task_end' => $flight['TaskEnd']
            ];
        }
    }
    
    // Process crew locations
    $crewLocations = [];
    foreach ($crewMembers as $crewKey => $crewData) {
        if (empty($crewData['flights'])) {
            continue;
        }
        
        // Sort flights by time
        usort($crewData['flights'], function($a, $b) {
            $timeA = $a['task_start'] ?? $a['flt_date'];
            $timeB = $b['task_start'] ?? $b['flt_date'];
            return strtotime($timeA) - strtotime($timeB);
        });
        
        // Determine final location (destination of last flight)
        $finalLocation = end($crewData['flights'])['destination'] ?? 'Unknown';
        
        $crewLocations[] = [
            'id' => $crewData['user_id'],
            'first_name' => $crewData['first_name'],
            'last_name' => $crewData['last_name'],
            'position' => $crewData['position'],
            'picture' => $crewData['picture'] ?? null,
            'flights' => $crewData['flights'],
            'final_location' => $finalLocation
        ];
    }
    
    // Sort crew members by name
    usort($crewLocations, function($a, $b) {
        return strcmp($a['first_name'] . ' ' . $a['last_name'], $b['first_name'] . ' ' . $b['last_name']);
    });
    
    return $crewLocations;
}

/**
 * Check passenger tickets for a mobile number within date range (±3 days)
 * Uses POST with headers per required cURL and sends {"date": "<selectedDate>"}.
 */
function checkPassengerTickets($mobile, $selectedDate) {
    if (empty($mobile) || empty($selectedDate)) {
        return [];
    }

    // Format mobile number (assumes your helper exists)
    $formattedMobile = formatMobileNumber($mobile);
    if (empty($formattedMobile)) {
        error_log("checkPassengerTickets: Empty formatted mobile for input: $mobile, Date: $selectedDate");
        return [];
    }

    // Date window: ±3 days from selectedDate
    $centerTs  = strtotime($selectedDate);
    if ($centerTs === false) {
        error_log("checkPassengerTickets: Invalid date format: $selectedDate");
        return [];
    }
    $startDate = date('Y-m-d', strtotime('-3 days', $centerTs));
    $endDate   = date('Y-m-d', strtotime('+3 days', $centerTs));

    // API endpoint + POST body
    // Try both https and http (some services may use http)
    $baseUrl = 'portal.raimonairways.net/all_pax/all_ticket_contact.php?passenger_contact=' . urlencode($formattedMobile);
    
    // Try multiple URL variations: https with SSL, https without SSL, http
    $urls = [
        ['url' => 'https://' . $baseUrl, 'ssl_verify' => true],
        ['url' => 'https://' . $baseUrl, 'ssl_verify' => false],
        ['url' => 'http://' . $baseUrl, 'ssl_verify' => false]
    ];
    
    // Debug log
    error_log("checkPassengerTickets: Calling API with Mobile: $formattedMobile (original: $mobile), Date: $selectedDate, Date Range: $startDate to $endDate");
    
    $payload = json_encode([
        'date' => date('Y-m-d', $centerTs), // per your cURL spec
    ], JSON_UNESCAPED_UNICODE);

    $headers = [
        'X-API-KEY: f9164750-acf1-440c-9248-77b74c54fee8',
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $response = false;
    $httpCode = 0;
    $curlError = '';
    $lastUrl = '';
    
    // Try each URL configuration
    foreach ($urls as $urlConfig) {
        $url = $urlConfig['url'];
        $sslVerify = $urlConfig['ssl_verify'];
        $lastUrl = $url;
        $isHttps = (strpos($url, 'https://') === 0);
        
        $ch = curl_init();
        
        // Basic curl options
        $curlOptions = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30, // Increased timeout for production
            CURLOPT_CONNECTTIMEOUT => 15, // Increased connection timeout
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_ENCODING       => '', // Accept any encoding (gzip, deflate, etc.)
        ];
        
        // SSL options
        if ($isHttps) {
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = $sslVerify;
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = $sslVerify ? 2 : 0;
        }
        
        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        // If successful, break the loop
        if ($httpCode === 200 && $response !== false && !empty($response)) {
            $protocol = $isHttps ? 'HTTPS' : 'HTTP';
            $sslInfo = ($isHttps && $sslVerify) ? ' (with SSL verification)' : (($isHttps && !$sslVerify) ? ' (without SSL verification)' : '');
            error_log("checkPassengerTickets: Successfully connected via $protocol$sslInfo - $url");
            break;
        } else {
            $protocol = $isHttps ? 'HTTPS' : 'HTTP';
            $sslInfo = ($isHttps && $sslVerify) ? ' (with SSL verification)' : (($isHttps && !$sslVerify) ? ' (without SSL verification)' : '');
            error_log("checkPassengerTickets: Failed to connect via $protocol$sslInfo - HTTP $httpCode, cURL Error #$curlErrno: $curlError, URL: $url, Total Time: " . ($curlInfo['total_time'] ?? 'N/A') . "s");
        }
    }

    if ($httpCode !== 200 || $response === false) {
        error_log("checkPassengerTickets API error: All connection attempts failed. Last attempt - HTTP $httpCode, cURL error: $curlError, Last URL: $lastUrl, Mobile: $formattedMobile, Date: $selectedDate");
        return [];
    }

    $data = json_decode($response, true);
    
    // Check for both 'success' and 'status' fields (API might use either)
    $isSuccess = (isset($data['success']) && $data['success']) || 
                 (isset($data['status']) && ($data['status'] === 'success' || $data['status'] === 'Success'));
    
    if (!is_array($data) || !$isSuccess || !isset($data['data']) || !is_array($data['data'])) {
        error_log("checkPassengerTickets API response invalid: " . json_encode($data) . ", Mobile: $formattedMobile, Date: $selectedDate");
        return [];
    }

    // Filter tickets within date range
    $filteredTickets = [];
    $totalTickets = count($data['data']);
    foreach ($data['data'] as $ticket) {
        $depRaw = $ticket['departure_date'] ?? '';
        if (!$depRaw) continue;

        // Normalize to Y-m-d for robust comparison
        $depTs = strtotime($depRaw);
        if ($depTs === false) continue;
        $depYmd = date('Y-m-d', $depTs);

        if ($depYmd >= $startDate && $depYmd <= $endDate) {
            $filteredTickets[] = [
                'origin'              => $ticket['origin']              ?? '',
                'destination'         => $ticket['destination']         ?? '',
                'flight_no'           => $ticket['flight_no']           ?? '',
                'departure_date'      => $depYmd,
                'passenger_full_name' => $ticket['passenger_full_name'] ?? '',
                'pnr'                 => $ticket['pnr']                 ?? '',
                'ticket_code'         => $ticket['ticket_code']         ?? ''
            ];
        }
    }
    
    // Debug log
    error_log("checkPassengerTickets: API returned $totalTickets tickets, filtered to " . count($filteredTickets) . " tickets in date range ($startDate to $endDate) for Mobile: $formattedMobile");

    return $filteredTickets;
}


/**
 * Format mobile number to Iranian format (09129382810)
 */
function formatMobileNumber($mobile) {
    if (empty($mobile)) {
        return '';
    }
    
    // Remove all non-numeric characters
    $mobile = preg_replace('/\D/', '', $mobile);
    
    // Handle different formats
    if (strlen($mobile) >= 10) {
        // If it starts with 98 (country code), remove it
        if (substr($mobile, 0, 2) === '98') {
            $mobile = substr($mobile, 2);
        }
        // If it starts with 0, keep it
        if (substr($mobile, 0, 1) === '0') {
            return substr($mobile, 0, 11); // Limit to 11 digits
        }
        // If it doesn't start with 0, add it
        return '0' . substr($mobile, 0, 10);
    }
    
    return $mobile;
}

/**
 * Get users who are actually assigned to flights (used in crew assignments)
 */
function getUsersAssignedToFlights($date = null) {
    $db = getDBConnection();
    
    // Build UNION queries for Crew1-Crew10
    $crewFields = [];
    for ($i = 1; $i <= 10; $i++) {
        $crewFields[] = "SELECT DISTINCT Crew{$i} FROM flights WHERE Crew{$i} IS NOT NULL AND Crew{$i} != ''";
    }
    $unionQuery = implode(' UNION ', $crewFields);
    
    if ($date) {
        // If date is provided, filter by that specific date
        $crewFieldsWithDate = [];
        for ($i = 1; $i <= 10; $i++) {
            $crewFieldsWithDate[] = "SELECT DISTINCT Crew{$i} FROM flights WHERE Crew{$i} IS NOT NULL AND Crew{$i} != '' AND DATE(FltDate) = ?";
        }
        $unionQueryWithDate = implode(' UNION ', $crewFieldsWithDate);
        
        $stmt = $db->prepare("
            SELECT DISTINCT u.id, u.first_name, u.last_name, u.position, u.email, u.mobile, u.status,
                   r.name as role_name, r.display_name as role_display_name
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.flight_crew = 1
            AND u.id IN (
                $unionQueryWithDate
            )
            ORDER BY u.first_name, u.last_name
        ");
        // Execute with date parameter repeated 10 times (once for each Crew field)
        $dateParams = array_fill(0, 10, $date);
        $stmt->execute($dateParams);
    } else {
        // If no date provided, get all assigned users
        $stmt = $db->prepare("
            SELECT DISTINCT u.id, u.first_name, u.last_name, u.position, u.email, u.mobile, u.status,
                   r.name as role_name, r.display_name as role_display_name
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.flight_crew = 1
            AND u.id IN (
                $unionQuery
            )
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute();
    }
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add role field for backward compatibility
    foreach ($results as &$result) {
        $result['role'] = $result['role_name'] ?? 'employee';
    }
    
    return $results;
}

/**
 * Get unique aircraft types from aircraft table
 */
function getAircraftTypes() {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT DISTINCT aircraft_type 
        FROM aircraft 
        WHERE status = 'active' 
        AND aircraft_type IS NOT NULL 
        AND aircraft_type != ''
        ORDER BY aircraft_type
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get all cockpit roles from database
 */
function getAllCockpitRoles() {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM cockpit_roles ORDER BY sort_order, label");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting cockpit roles: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all cabin roles from database
 */
function getAllCabinRoles() {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM cabin_roles ORDER BY sort_order, label");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting cabin roles: " . $e->getMessage());
        return [];
    }
}

/**
 * Get unique aircraft types from aircraft table
 */
function getUniqueAircraftTypes() {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT DISTINCT aircraft_type FROM aircraft WHERE status = 'active' AND aircraft_type IS NOT NULL AND aircraft_type != '' ORDER BY aircraft_type");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Error getting unique aircraft types: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user endorsements
 */
function getUserEndorsements($userId) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM user_endorsement WHERE user_id = ? ORDER BY aircraft_type, role_code");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting user endorsements: " . $e->getMessage());
        return [];
    }
}

/**
 * Save user endorsements (delete old and insert new)
 */
function saveUserEndorsements($userId, $endorsements) {
    try {
        $db = getDBConnection();
        $db->beginTransaction();
        
        // Delete existing endorsements for this user
        $stmt = $db->prepare("DELETE FROM user_endorsement WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Insert new endorsements
        if (!empty($endorsements)) {
            $stmt = $db->prepare("INSERT INTO user_endorsement (user_id, aircraft_type, role_code, role_type) VALUES (?, ?, ?, ?)");
            foreach ($endorsements as $endorsement) {
                $stmt->execute([
                    $userId,
                    $endorsement['aircraft_type'],
                    $endorsement['role_code'],
                    $endorsement['role_type'] // 'cockpit' or 'cabin'
                ]);
            }
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error saving user endorsements: " . $e->getMessage());
        return false;
    }
}

/**
 * Get aircraft types with manufacturer
 */
function getAircraftTypesWithManufacturer() {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT DISTINCT aircraft_type, manufacturer
        FROM aircraft 
        WHERE status = 'active' 
        AND aircraft_type IS NOT NULL 
        AND aircraft_type != ''
        ORDER BY manufacturer, aircraft_type
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get aircraft registrations
 */
function getAircraftRegistrations() {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT DISTINCT registration 
        FROM aircraft 
        WHERE status = 'active' 
        AND registration IS NOT NULL 
        AND registration != ''
        ORDER BY registration
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ==================== HOME BASE FUNCTIONS ====================

/**
 * Get all home base records
 */
function getAllHomeBases($limit = null, $offset = 0, $search = []) {
    $db = getDBConnection();
    
    $whereConditions = [];
    $params = [];
    
    // Add search conditions
    if (!empty($search['location_name'])) {
        $whereConditions[] = "location_name LIKE ?";
        $params[] = '%' . $search['location_name'] . '%';
    }
    
    if (!empty($search['short_name'])) {
        $whereConditions[] = "short_name LIKE ?";
        $params[] = '%' . $search['short_name'] . '%';
    }
    
    if (!empty($search['country'])) {
        $whereConditions[] = "country LIKE ?";
        $params[] = '%' . $search['country'] . '%';
    }
    
    if (isset($search['published']) && $search['published'] !== '') {
        $whereConditions[] = "published = ?";
        $params[] = $search['published'];
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "SELECT * FROM home_base $whereClause ORDER BY created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get home base by ID
 */
function getHomeBaseById($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM home_base WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Create new home base
 */
function createHomeBase($data) {
    $db = getDBConnection();
    
    $fields = [
        'published', 'publish_no', 'pending_changes', 'last_survey',
        'location_name', 'short_name', 'timezone', 'site_properties',
        'gps_coordinates', 'latitude', 'longitude', 'magnetic_variation',
        'address_line_1', 'address_line_2', 'city_suburb', 'state',
        'postcode', 'country', 'owned_by_base', 'slot_coordination', 'status'
    ];
    
    $values = [];
    $placeholders = [];
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $values[] = $data[$field];
            $placeholders[] = '?';
        } else {
            $values[] = null;
            $placeholders[] = '?';
        }
    }
    
    $sql = "INSERT INTO home_base (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $db->prepare($sql);
    
    return $stmt->execute($values);
}

/**
 * Update home base
 */
function updateHomeBase($id, $data) {
    $db = getDBConnection();
    
    $fields = [
        'published', 'publish_no', 'pending_changes', 'last_survey',
        'location_name', 'short_name', 'timezone', 'site_properties',
        'gps_coordinates', 'latitude', 'longitude', 'magnetic_variation',
        'address_line_1', 'address_line_2', 'city_suburb', 'state',
        'postcode', 'country', 'owned_by_base', 'slot_coordination', 'status'
    ];
    
    $setParts = [];
    $values = [];
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $setParts[] = "$field = ?";
            $values[] = $data[$field];
        }
    }
    
    $values[] = $id;
    
    $sql = "UPDATE home_base SET " . implode(', ', $setParts) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    return $stmt->execute($values);
}

/**
 * Delete home base
 */
function deleteHomeBase($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM home_base WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Get home base count
 */
function getHomeBaseCount($search = []) {
    $db = getDBConnection();
    
    $whereConditions = [];
    $params = [];
    
    // Add search conditions
    if (!empty($search['location_name'])) {
        $whereConditions[] = "location_name LIKE ?";
        $params[] = '%' . $search['location_name'] . '%';
    }
    
    if (!empty($search['short_name'])) {
        $whereConditions[] = "short_name LIKE ?";
        $params[] = '%' . $search['short_name'] . '%';
    }
    
    if (!empty($search['country'])) {
        $whereConditions[] = "country LIKE ?";
        $params[] = '%' . $search['country'] . '%';
    }
    
    if (isset($search['published']) && $search['published'] !== '') {
        $whereConditions[] = "published = ?";
        $params[] = $search['published'];
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "SELECT COUNT(*) as count FROM home_base $whereClause";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'];
}

/**
 * Get published home bases
 */
function getPublishedHomeBases() {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM home_base WHERE published = 1 AND status = 'active' ORDER BY location_name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== SAFETY REPORTS FUNCTIONS ====================

/**
 * Get all safety reports
 */
function getAllSafetyReports($limit = null, $offset = 0, $search = []) {
    $db = getDBConnection();
    
    $whereConditions = [];
    $params = [];
    
    // Add search conditions
    if (!empty($search['report_type'])) {
        $whereConditions[] = "report_type LIKE ?";
        $params[] = '%' . $search['report_type'] . '%';
    }
    
    if (!empty($search['report_no'])) {
        $whereConditions[] = "report_no LIKE ?";
        $params[] = '%' . $search['report_no'] . '%';
    }
    
    if (!empty($search['reporter_name'])) {
        $whereConditions[] = "reporter_name LIKE ?";
        $params[] = '%' . $search['reporter_name'] . '%';
    }
    
    if (!empty($search['report_title'])) {
        $whereConditions[] = "report_title LIKE ?";
        $params[] = '%' . $search['report_title'] . '%';
    }
    
    if (!empty($search['event_base'])) {
        $whereConditions[] = "event_base LIKE ?";
        $params[] = '%' . $search['event_base'] . '%';
    }
    
    if (!empty($search['event_department'])) {
        $whereConditions[] = "event_department LIKE ?";
        $params[] = '%' . $search['event_department'] . '%';
    }
    
    if (isset($search['status']) && $search['status'] !== '') {
        $whereConditions[] = "status = ?";
        $params[] = $search['status'];
    }
    
    if (isset($search['confidential']) && $search['confidential'] !== '') {
        $whereConditions[] = "confidential = ?";
        $params[] = $search['confidential'];
    }
    
    // Filter by user if not admin/manager
    $current_user = getCurrentUser();
    if (!hasAnyRole(['admin', 'manager'])) {
        $whereConditions[] = "created_by = ?";
        $params[] = $current_user['id'];
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "SELECT sr.*, u.first_name, u.last_name FROM safety_reports sr 
            LEFT JOIN users u ON sr.created_by = u.id 
            $whereClause ORDER BY sr.created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get safety report by ID
 */
function getSafetyReportById($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT sr.*, u.first_name, u.last_name FROM safety_reports sr 
                         LEFT JOIN users u ON sr.created_by = u.id 
                         WHERE sr.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Create new safety report
 */
function createSafetyReport($data) {
    $db = getDBConnection();
    
    $fields = [
        'report_type', 'report_no', 'submit_on_behalf_of', 'reporter_name',
        'reporter_address_line_1', 'reporter_address_line_2', 'reporter_suburb',
        'reporter_state', 'reporter_postcode', 'reporter_country', 'reporter_telephone',
        'reporter_fax', 'reporter_email', 'confidential', 'report_title',
        'event_date_time', 'event_base', 'event_department', 'report_description',
        'status', 'created_by'
    ];
    
    $values = [];
    $placeholders = [];
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $values[] = $data[$field];
            $placeholders[] = '?';
        } else {
            $values[] = null;
            $placeholders[] = '?';
        }
    }
    
    $sql = "INSERT INTO safety_reports (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $db->prepare($sql);
    
    return $stmt->execute($values);
}

/**
 * Update safety report
 */
function updateSafetyReport($id, $data) {
    $db = getDBConnection();
    
    $fields = [
        'report_type', 'report_no', 'submit_on_behalf_of', 'reporter_name',
        'reporter_address_line_1', 'reporter_address_line_2', 'reporter_suburb',
        'reporter_state', 'reporter_postcode', 'reporter_country', 'reporter_telephone',
        'reporter_fax', 'reporter_email', 'confidential', 'report_title',
        'event_date_time', 'event_base', 'event_department', 'report_description',
        'status'
    ];
    
    $setParts = [];
    $values = [];
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $setParts[] = "$field = ?";
            $values[] = $data[$field];
        }
    }
    
    $values[] = $id;
    
    $sql = "UPDATE safety_reports SET " . implode(', ', $setParts) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    return $stmt->execute($values);
}

/**
 * Delete safety report
 */
function deleteSafetyReport($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM safety_reports WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Get safety reports count
 */
function getSafetyReportsCount($search = []) {
    $db = getDBConnection();
    
    $whereConditions = [];
    $params = [];
    
    // Add search conditions
    if (!empty($search['report_type'])) {
        $whereConditions[] = "report_type LIKE ?";
        $params[] = '%' . $search['report_type'] . '%';
    }
    
    if (!empty($search['report_no'])) {
        $whereConditions[] = "report_no LIKE ?";
        $params[] = '%' . $search['report_no'] . '%';
    }
    
    if (!empty($search['reporter_name'])) {
        $whereConditions[] = "reporter_name LIKE ?";
        $params[] = '%' . $search['reporter_name'] . '%';
    }
    
    if (!empty($search['report_title'])) {
        $whereConditions[] = "report_title LIKE ?";
        $params[] = '%' . $search['report_title'] . '%';
    }
    
    if (!empty($search['event_base'])) {
        $whereConditions[] = "event_base LIKE ?";
        $params[] = '%' . $search['event_base'] . '%';
    }
    
    if (!empty($search['event_department'])) {
        $whereConditions[] = "event_department LIKE ?";
        $params[] = '%' . $search['event_department'] . '%';
    }
    
    if (isset($search['status']) && $search['status'] !== '') {
        $whereConditions[] = "status = ?";
        $params[] = $search['status'];
    }
    
    if (isset($search['confidential']) && $search['confidential'] !== '') {
        $whereConditions[] = "confidential = ?";
        $params[] = $search['confidential'];
    }
    
    // Filter by user if not admin/manager
    $current_user = getCurrentUser();
    if (!hasAnyRole(['admin', 'manager'])) {
        $whereConditions[] = "created_by = ?";
        $params[] = $current_user['id'];
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "SELECT COUNT(*) as count FROM safety_reports $whereClause";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'];
}

/**
 * Get safety report statistics
 */
function getSafetyReportStats() {
    $db = getDBConnection();
    
    $stats = [];
    
    // Total reports
    $stmt = $db->query("SELECT COUNT(*) as total FROM safety_reports");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Reports by status
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM safety_reports GROUP BY status");
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['by_status'] = [];
    foreach ($status_counts as $row) {
        $stats['by_status'][$row['status']] = $row['count'];
    }
    
    // Reports by type
    $stmt = $db->query("SELECT report_type, COUNT(*) as count FROM safety_reports GROUP BY report_type");
    $type_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['by_type'] = [];
    foreach ($type_counts as $row) {
        $stats['by_type'][$row['report_type']] = $row['count'];
    }
    
    // Confidential reports
    $stmt = $db->query("SELECT COUNT(*) as count FROM safety_reports WHERE confidential = 1");
    $stats['confidential'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    return $stats;
}

/**
 * Get safety report status color
 */
function getSafetyReportStatusColor($status) {
    $colors = [
        'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        'submitted' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'under_review' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'resolved' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'closed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    ];
    
    return $colors[$status] ?? $colors['draft'];
}

/**
 * Get safety report status icon
 */
function getSafetyReportStatusIcon($status) {
    $icons = [
        'draft' => 'fas fa-edit',
        'submitted' => 'fas fa-paper-plane',
        'under_review' => 'fas fa-search',
        'resolved' => 'fas fa-check-circle',
        'closed' => 'fas fa-times-circle'
    ];
    
    return $icons[$status] ?? $icons['draft'];
}

// ==================== ODB NOTIFICATION FUNCTIONS ====================

/**
 * Create a new ODB notification
 */
function createODBNotification($title, $message, $priority, $targetRoles, $createdBy, $expiresAt = null, $filePath = null) {
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO odb_notifications (title, message, priority, target_roles, created_by, expires_at, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $rolesJson = json_encode($targetRoles);
    return $stmt->execute([$title, $message, $priority, $rolesJson, $createdBy, $expiresAt, $filePath]);
}

/**
 * Get all ODB notifications
 */
function getAllODBNotifications($limit = null, $offset = 0) {
    $db = getDBConnection();
    $sql = "SELECT odb.*, u.first_name, u.last_name 
            FROM odb_notifications odb 
            JOIN users u ON odb.created_by = u.id 
            WHERE odb.is_active = 1 
            ORDER BY odb.created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get ODB notification by ID
 */
function getODBNotificationById($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT odb.*, u.first_name, u.last_name 
                         FROM odb_notifications odb 
                         JOIN users u ON odb.created_by = u.id 
                         WHERE odb.id = ? AND odb.is_active = 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get active ODB notifications for a specific user based on their role
 */
function getActiveODBNotificationsForUser($userId) {
    $db = getDBConnection();
    
    // Get user's role
    $user = getUserById($userId);
    if (!$user) return [];
    
    $userRole = $user['role'] ?? 'employee';
    
    // Get notifications where user's role is in target_roles
    $stmt = $db->prepare("SELECT odb.*, u.first_name, u.last_name 
                         FROM odb_notifications odb 
                         JOIN users u ON odb.created_by = u.id 
                         WHERE odb.is_active = 1 
                         AND (odb.expires_at IS NULL OR odb.expires_at > NOW())
                         AND JSON_CONTAINS(odb.target_roles, ?)
                         ORDER BY odb.priority DESC, odb.created_at DESC");
    
    $stmt->execute([json_encode($userRole)]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if user has acknowledged a notification
 */
function hasUserAcknowledgedNotification($notificationId, $userId) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id FROM odb_acknowledgments WHERE notification_id = ? AND user_id = ?");
    $stmt->execute([$notificationId, $userId]);
    return $stmt->fetch() !== false;
}

/**
 * Acknowledge a notification
 */
function acknowledgeODBNotification($notificationId, $userId, $ipAddress = null, $userAgent = null) {
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO odb_acknowledgments (notification_id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$notificationId, $userId, $ipAddress, $userAgent]);
}

/**
 * Get acknowledgment statistics for a notification
 */
function getODBNotificationStats($notificationId) {
    $db = getDBConnection();
    
    // Get notification details
    $notification = getODBNotificationById($notificationId);
    if (!$notification) return null;
    
    $targetRoles = json_decode($notification['target_roles'], true);
    
    // Get all users with target roles
    $placeholders = str_repeat('?,', count($targetRoles) - 1) . '?';
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, r.name as role_name, r.display_name as role_display_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE r.name IN ($placeholders) AND u.status = 'active'
    ");
    $stmt->execute($targetRoles);
    $targetUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add role field for backward compatibility
    foreach ($targetUsers as &$user) {
        $user['role'] = $user['role_name'] ?? 'employee';
    }
    
    // Get acknowledgments
    $stmt = $db->prepare("
        SELECT oa.*, u.first_name, u.last_name, r.name as role_name, r.display_name as role_display_name 
        FROM odb_acknowledgments oa 
        JOIN users u ON oa.user_id = u.id 
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE oa.notification_id = ? 
        ORDER BY oa.acknowledged_at DESC
    ");
    $stmt->execute([$notificationId]);
    $acknowledgments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add role field for backward compatibility
    foreach ($acknowledgments as &$ack) {
        $ack['role'] = $ack['role_name'] ?? 'employee';
    }
    
    // Create acknowledgment map
    $acknowledgedUserIds = array_column($acknowledgments, 'user_id');
    
    // Separate acknowledged and not acknowledged users
    $acknowledgedUsers = [];
    $notAcknowledgedUsers = [];
    
    foreach ($targetUsers as $user) {
        if (in_array($user['id'], $acknowledgedUserIds)) {
            $acknowledgedUsers[] = $user;
        } else {
            $notAcknowledgedUsers[] = $user;
        }
    }
    
    return [
        'notification' => $notification,
        'total_target_users' => count($targetUsers),
        'acknowledged_count' => count($acknowledgedUsers),
        'not_acknowledged_count' => count($notAcknowledgedUsers),
        'acknowledged_users' => $acknowledgedUsers,
        'not_acknowledged_users' => $notAcknowledgedUsers,
        'acknowledgments' => $acknowledgments
    ];
}

/**
 * Update ODB notification
 */
function updateODBNotification($id, $title, $message, $priority, $targetRoles, $expiresAt = null) {
    $db = getDBConnection();
    $stmt = $db->prepare("UPDATE odb_notifications SET title = ?, message = ?, priority = ?, target_roles = ?, expires_at = ? WHERE id = ?");
    $rolesJson = json_encode($targetRoles);
    return $stmt->execute([$title, $message, $priority, $rolesJson, $expiresAt, $id]);
}

/**
 * Delete ODB notification (soft delete)
 */
function deleteODBNotification($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("UPDATE odb_notifications SET is_active = 0 WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Get ODB notifications count
 */
function getODBNotificationsCount() {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM odb_notifications WHERE is_active = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

/**
 * Get unread ODB notifications count for current user
 */
function getUnreadODBCount($userId = null) {
    if (!$userId) {
        $user = getCurrentUser();
        if (!$user) return 0;
        $userId = $user['id'];
    }
    
    $db = getDBConnection();
    
    // Get user's role
    $user = getUserById($userId);
    if (!$user) return 0;
    
    $userRole = $user['role'] ?? 'employee';
    
    // Count notifications that user hasn't acknowledged
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM odb_notifications odb 
        WHERE odb.is_active = 1 
        AND (odb.expires_at IS NULL OR odb.expires_at > NOW())
        AND JSON_CONTAINS(odb.target_roles, ?)
        AND odb.id NOT IN (
            SELECT notification_id 
            FROM odb_acknowledgments 
            WHERE user_id = ?
        )
    ");
    
    $stmt->execute([json_encode($userRole), $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

/**
 * Get priority color for ODB notification
 */
function getODBPriorityColor($priority) {
    switch ($priority) {
        case 'critical':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        case 'urgent':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
        case 'normal':
        default:
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
    }
}

/**
 * Get priority icon for ODB notification
 */
function getODBPriorityIcon($priority) {
    switch ($priority) {
        case 'critical':
            return 'fas fa-exclamation-triangle';
        case 'urgent':
            return 'fas fa-exclamation-circle';
        case 'normal':
        default:
            return 'fas fa-info-circle';
    }
}

/**
 * Calculate days since notification was created
 */
function getDaysSinceCreated($createdAt) {
    $created = new DateTime($createdAt);
    $now = new DateTime();
    $diff = $now->diff($created);
    return $diff->days;
}

/**
 * Handle ODB file upload
 */
function handleODBFileUpload($file) {
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error'];
    }
    
    // Validate file type
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
    $fileType = $file['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Only PDF, JPG, PNG files are allowed.'];
    }
    
    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File size too large. Maximum size is 5MB.'];
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/uploads/odb/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create upload directory'];
        }
    }
    
    // Generate unique filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('odb_', true) . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return [
            'success' => true, 
            'file_path' => 'uploads/odb/' . $fileName,
            'file_name' => $file['name']
        ];
    } else {
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }
}

/**
 * Get file URL for ODB notification
 */
function getODBFileUrl($filePath) {
    if (empty($filePath)) {
        return null;
    }
    
    // Check if file exists
    $fullPath = __DIR__ . '/' . $filePath;
    if (!file_exists($fullPath)) {
        return null;
    }
    
    // Use dynamic base_url() function to get current host instead of hardcoded localhost
    return base_url() . ltrim($filePath, '/');
}

// ==================== ROUTES AND STATIONS FUNCTIONS ====================

/**
 * Get all stations
 */
function getAllStations($filter = null) {
    $db = getDBConnection();
    
    if ($filter === 'base') {
        $stmt = $db->prepare("SELECT * FROM stations WHERE is_base = 1 ORDER BY station_name");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("SELECT * FROM stations ORDER BY station_name");
        $stmt->execute();
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all stations with search filters
 */
function getAllStationsWithSearch($searchName = '', $searchIata = '', $searchIcao = '', $searchBase = '') {
    $db = getDBConnection();
    
    $whereConditions = [];
    $params = [];
    
    if (!empty($searchName)) {
        $whereConditions[] = "station_name LIKE ?";
        $params[] = "%$searchName%";
    }
    
    if (!empty($searchIata)) {
        $whereConditions[] = "iata_code LIKE ?";
        $params[] = "%$searchIata%";
    }
    
    if (!empty($searchIcao)) {
        $whereConditions[] = "icao_code LIKE ?";
        $params[] = "%$searchIcao%";
    }
    
    if ($searchBase !== '') {
        $whereConditions[] = "is_base = ?";
        $params[] = $searchBase;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "SELECT * FROM stations $whereClause ORDER BY station_name";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get station by ID
 */
function getStationById($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM stations WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get station by IATA code
 */
function getStationByIATACode($iataCode) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM stations WHERE iata_code = ?");
    $stmt->execute([$iataCode]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get station by ICAO code
 */
function getStationByICAOCode($icaoCode) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM stations WHERE icao_code = ?");
    $stmt->execute([$icaoCode]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get station info by ALA location identifier (ICAO Code)
 */
function getStationInfoByALAIdentifier($alaLocationIdentifier) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM station_info WHERE ala_location_identifier = ? LIMIT 1");
        $stmt->execute([$alaLocationIdentifier]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // If station_info table doesn't exist, return null
        return null;
    }
}

/**
 * Update or insert station info by ALA location identifier (ICAO Code)
 */
function updateStationInfoByALAIdentifier($alaLocationIdentifier, $data) {
    try {
        $db = getDBConnection();
        
        // Check if record exists
        $existing = getStationInfoByALAIdentifier($alaLocationIdentifier);
        
        $allowedFields = [
            'ala_call_frequency', 'ala_call_sign', 'ala_call_type', 'ala_elevation',
            'ala_fuel_notes', 'ala_navaids', 'ala_operating_hours', 'ala_night_operations',
            'ala_remarks_restrictions', 'fuel_all_type', 'fuel_measurement'
        ];
        
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "`$key` = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        if ($existing) {
            // Update existing record
            $values[] = $alaLocationIdentifier;
            $sql = "UPDATE station_info SET " . implode(', ', $fields) . " WHERE ala_location_identifier = ?";
        } else {
            // Insert new record
            $fields[] = "`ala_location_identifier` = ?";
            $values[] = $alaLocationIdentifier;
            $sql = "INSERT INTO station_info SET " . implode(', ', $fields);
        }
        
        $stmt = $db->prepare($sql);
        return $stmt->execute($values);
    } catch(PDOException $e) {
        error_log("Error updating station_info: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate trip duration using Neshan Distance Matrix API
 * @param float $originLat Origin latitude
 * @param float $originLng Origin longitude
 * @param float $destinationLat Destination latitude
 * @param float $destinationLng Destination longitude
 * @param string $type Vehicle type: 'car' or 'motorcycle' (default: 'car')
 * @param bool $withTraffic Whether to include traffic in calculation (default: true)
 * @return array|null Returns array with 'duration' (seconds), 'duration_text', 'distance' (meters), 'distance_text', or null on error
 */
function calculateTripDuration($originLat, $originLng, $destinationLat, $destinationLng, $type = 'car', $withTraffic = true) {
    // Validate coordinates
    if (!is_numeric($originLat) || !is_numeric($originLng) || !is_numeric($destinationLat) || !is_numeric($destinationLng)) {
        return null;
    }
    
    if ($originLat < -90 || $originLat > 90 || $destinationLat < -90 || $destinationLat > 90) {
        return null;
    }
    
    if ($originLng < -180 || $originLng > 180 || $destinationLng < -180 || $destinationLng > 180) {
        return null;
    }
    
    // Neshan API Key
    $apiKey = 'service.5df42f240fcc498f92a114c2af4e6a45';
    
    // Build API URL
    $baseUrl = $withTraffic 
        ? 'https://api.neshan.org/v1/distance-matrix'
        : 'https://api.neshan.org/v1/distance-matrix/no-traffic';
    
    $origins = "$originLat,$originLng";
    $destinations = "$destinationLat,$destinationLng";
    
    $url = $baseUrl . '?type=' . urlencode($type) . '&origins=' . urlencode($origins) . '&destinations=' . urlencode($destinations);
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Api-Key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Check for cURL errors
    if ($curlError) {
        error_log("Neshan Distance Matrix API cURL Error: " . $curlError);
        return null;
    }
    
    // Check HTTP status code
    if ($httpCode !== 200) {
        error_log("Neshan Distance Matrix API HTTP Error: " . $httpCode . " - Response: " . $response);
        return null;
    }
    
    // Parse JSON response
    $data = json_decode($response, true);
    
    if (!$data || $data['status'] !== 'Ok') {
        error_log("Neshan Distance Matrix API Error: Invalid response - " . $response);
        return null;
    }
    
    // Extract duration and distance from response
    if (isset($data['rows'][0]['elements'][0])) {
        $element = $data['rows'][0]['elements'][0];
        
        if ($element['status'] !== 'Ok') {
            error_log("Neshan Distance Matrix API Error: Element status - " . $element['status']);
            return null;
        }
        
        return [
            'duration' => $element['duration']['value'] ?? 0, // in seconds
            'duration_text' => $element['duration']['text'] ?? '0 دقیقه', // in Persian
            'distance' => $element['distance']['value'] ?? 0, // in meters
            'distance_text' => $element['distance']['text'] ?? '0 متر' // in Persian
        ];
    }
    
    return null;
}

/**
 * Get origin ICAO code from flight route
 * Route format: "IATA-IATA" (e.g., "RAS-THR")
 * Returns ICAO code of origin station, or null if not found
 */
function getOriginICAOFromRoute($route) {
    if (empty($route)) {
        return null;
    }
    
    // Extract origin IATA code (part before '-')
    $parts = explode('-', $route);
    if (empty($parts[0])) {
        return null;
    }
    
    $originIata = trim($parts[0]);
    
    // Get station by IATA code
    $station = getStationByIATACode($originIata);
    
    if ($station && !empty($station['icao_code'])) {
        return $station['icao_code'];
    }
    
    return null;
}

/**
 * Fetch METAR temperature from Aviation Weather API
 * Returns temperature in Celsius, or null if not available
 */
function fetchMETARTemperature($icaoCode) {
    if (empty($icaoCode)) {
        return null;
    }
    
    $url = "https://aviationweather.gov/api/data/metar?ids={$icaoCode}&format=json";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, '1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response || !empty($curlError)) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    // Check if data is an array and has at least one element
    if (is_array($data) && !empty($data) && isset($data[0]['temp'])) {
        return $data[0]['temp'];
    }
    
    return null;
}

/**
 * Get route code based on ICAO codes from flight route
 * Route format: "IATA-IATA" (e.g., "RAS-THR")
 * Returns ICAO-based route code (e.g., "OIGG-OIII"), or null if not found
 */
function getRouteCodeICAOFromFlightRoute($route) {
    if (empty($route)) {
        return null;
    }
    
    // Extract origin and destination IATA codes
    $parts = explode('-', $route);
    if (count($parts) < 2 || empty($parts[0]) || empty($parts[1])) {
        return null;
    }
    
    $originIata = trim($parts[0]);
    $destinationIata = trim($parts[1]);
    
    // Get origin station
    $originStation = getStationByIATACode($originIata);
    if (!$originStation || empty($originStation['icao_code'])) {
        return null;
    }
    
    // Get destination station
    $destinationStation = getStationByIATACode($destinationIata);
    if (!$destinationStation || empty($destinationStation['icao_code'])) {
        return null;
    }
    
    // Generate ICAO-based route code
    return generateRouteCodeFromICAO($originStation['icao_code'], $destinationStation['icao_code']);
}

/**
 * Get aircraft ID from registration
 */
function getAircraftIdByRegistration($registration) {
    if (empty($registration)) {
        return null;
    }
    
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id FROM aircraft WHERE registration = ?");
    $stmt->execute([$registration]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['id'] : null;
}

/**
 * Get payload weight based on temperature
 * Returns the appropriate payload weight column based on temperature
 * Logic based on examples:
 * - 17°C → temperature_20
 * - 20°C → temperature_20
 * - 22°C → temperature_25
 * - 32°C → temperature_25
 * So:
 * - temp <= 20 → temperature_20
 * - 20 < temp <= 32 → temperature_25
 * - 32 < temp <= 37 → temperature_35
 * - temp > 37 → temperature_40
 */
function getPayloadWeightByTemperature($payloadData, $temperature) {
    if (!$payloadData || $temperature === null) {
        return null;
    }
    
    // Determine which temperature column to use
    if ($temperature <= 20) {
        return $payloadData['temperature_20'] ?? null;
    } elseif ($temperature <= 32) {
        return $payloadData['temperature_25'] ?? null;
    } elseif ($temperature <= 37) {
        return $payloadData['temperature_35'] ?? null;
    } else {
        return $payloadData['temperature_40'] ?? null;
    }
}

/**
 * Create new station
 */
function createStation($data) {
    $db = getDBConnection();
    
    $sql = "INSERT INTO stations (station_name, iata_code, icao_code, is_base, short_name, timezone, latitude, longitude, magnetic_variation, address_line1, address_line2, city_suburb, state, postcode, country, owned_by_base, slot_coordination, is_ala, is_fuel_depot, is_base_office, is_customs_immigration, is_fixed_base_operators, is_hls, is_maintenance_engineering) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    return $stmt->execute([
        $data['station_name'],
        $data['iata_code'],
        $data['icao_code'] ?? null,
        $data['is_base'] ?? 0,
        $data['short_name'] ?? null,
        $data['timezone'] ?? null,
        $data['latitude'] ?? null,
        $data['longitude'] ?? null,
        $data['magnetic_variation'] ?? null,
        $data['address_line1'] ?? null,
        $data['address_line2'] ?? null,
        $data['city_suburb'] ?? null,
        $data['state'] ?? null,
        $data['postcode'] ?? null,
        $data['country'] ?? null,
        $data['owned_by_base'] ?? null,
        $data['slot_coordination'] ?? null,
        $data['is_ala'] ?? 0,
        $data['is_fuel_depot'] ?? 0,
        $data['is_base_office'] ?? 0,
        $data['is_customs_immigration'] ?? 0,
        $data['is_fixed_base_operators'] ?? 0,
        $data['is_hls'] ?? 0,
        $data['is_maintenance_engineering'] ?? 0
    ]);
}

/**
 * Update station
 */
function updateStation($id, $data) {
    $db = getDBConnection();
    
    $fields = [];
    $values = [];
    
    $allowedFields = [
        'station_name', 'iata_code', 'icao_code', 'is_base', 'short_name', 'timezone', 
        'latitude', 'longitude', 'magnetic_variation', 'address_line1', 'address_line2', 
        'city_suburb', 'state', 'postcode', 'country', 'owned_by_base', 'slot_coordination',
        'is_ala', 'is_fuel_depot', 'is_base_office', 'is_customs_immigration', 
        'is_fixed_base_operators', 'is_hls', 'is_maintenance_engineering'
    ];
    
    foreach ($data as $key => $value) {
        if (in_array($key, $allowedFields)) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $values[] = $id;
    $sql = "UPDATE stations SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    return $stmt->execute($values);
}

/**
 * Delete station
 */
function deleteStation($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM stations WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Get all routes with station information
 */
function getAllRoutes($status = null) {
    $db = getDBConnection();
    
    $sql = "SELECT r.*, 
                   o.station_name as origin_name, o.iata_code as origin_iata,
                   d.station_name as destination_name, d.iata_code as destination_iata
            FROM routes r
            JOIN stations o ON r.origin_station_id = o.id
            JOIN stations d ON r.destination_station_id = d.id";
    
    if ($status) {
        $sql .= " WHERE r.status = ?";
        $sql .= " ORDER BY r.route_name";
        $stmt = $db->prepare($sql);
        $stmt->execute([$status]);
    } else {
        $sql .= " ORDER BY r.route_name";
        $stmt = $db->prepare($sql);
        $stmt->execute();
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get route by ID
 */
function getRouteById($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT r.*, 
                                 o.station_name as origin_name, o.iata_code as origin_iata,
                                 d.station_name as destination_name, d.iata_code as destination_iata
                          FROM routes r
                          JOIN stations o ON r.origin_station_id = o.id
                          JOIN stations d ON r.destination_station_id = d.id
                          WHERE r.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Create new route
 */
function createRoute($data) {
    $db = getDBConnection();
    
    $sql = "INSERT INTO routes (route_code, route_name, origin_station_id, destination_station_id, 
                               distance_nm, flight_time_minutes, aircraft_types, frequency, status, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    return $stmt->execute([
        $data['route_code'],
        $data['route_name'],
        $data['origin_station_id'],
        $data['destination_station_id'],
        $data['distance_nm'] ?? null,
        $data['flight_time_minutes'] ?? null,
        $data['aircraft_types'] ?? null,
        $data['frequency'] ?? null,
        $data['status'] ?? 'active',
        $data['notes'] ?? null
    ]);
}

/**
 * Update route
 */
function updateRoute($id, $data) {
    $db = getDBConnection();
    
    $fields = [];
    $values = [];
    
    $allowedFields = ['route_code', 'route_name', 'origin_station_id', 'destination_station_id',
                     'distance_nm', 'flight_time_minutes', 'aircraft_types', 'frequency', 'status', 'notes'];
    
    foreach ($data as $key => $value) {
        if (in_array($key, $allowedFields)) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $values[] = $id;
    $sql = "UPDATE routes SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    return $stmt->execute($values);
}

/**
 * Delete route
 */
function deleteRoute($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM routes WHERE id = ?");
    return $stmt->execute([$id]);
}

// ==================== ROUTE PRICING FUNCTIONS ====================

/**
 * Get route price by route ID
 */
function getRoutePriceByRouteId($routeId) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM route_prices WHERE route_id = ?");
    $stmt->execute([$routeId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Delete route price
 */
function deleteRoutePrice($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM route_prices WHERE id = ?");
    return $stmt->execute([$id]);
}

// ==================== CATERING FUNCTIONS ====================

/**
 * Add new catering configuration
 */
function addCatering($name, $customName, $passengerFood, $equipment, $transportation, $storage, $waste, $qualityInspection, $packaging, $specialServices) {
    $db = getDBConnection();
    
    $stmt = $db->prepare("INSERT INTO catering (name, custom_name, passenger_food, equipment, transportation, storage, waste, quality_inspection, packaging, special_services) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    return $stmt->execute([
        $name,
        $customName,
        $passengerFood,
        $equipment,
        $transportation,
        $storage,
        $waste,
        $qualityInspection,
        $packaging,
        $specialServices
    ]);
}

/**
 * Update catering configuration
 */
function updateCatering($id, $name, $customName, $passengerFood, $equipment, $transportation, $storage, $waste, $qualityInspection, $packaging, $specialServices) {
    $db = getDBConnection();
    
    $stmt = $db->prepare("UPDATE catering 
                          SET name = ?, custom_name = ?, passenger_food = ?, equipment = ?, transportation = ?, 
                              storage = ?, waste = ?, quality_inspection = ?, packaging = ?, special_services = ?
                          WHERE id = ?");
    
    return $stmt->execute([
        $name,
        $customName,
        $passengerFood,
        $equipment,
        $transportation,
        $storage,
        $waste,
        $qualityInspection,
        $packaging,
        $specialServices,
        $id
    ]);
}

/**
 * Delete catering configuration
 */
function deleteCatering($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM catering WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Get catering by ID
 */
function getCateringById($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM catering WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get all catering configurations
 */
function getAllCatering() {
    $db = getDBConnection();
    $stmt = $db->query("SELECT * FROM catering ORDER BY name ASC, custom_name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== IFSO COSTS FUNCTIONS ====================

/**
 * Add new IFSO cost record
 */
function addIFSOCost($monthlyPrepayment, $salaries, $salariesCount, $training, $trainingCount, $transport, $transportCount, $ifsoPremium, $ifsoPremiumCount, $monthlyAccommodation, $monthlyAccommodationCount) {
    $db = getDBConnection();
    
    $stmt = $db->prepare("INSERT INTO ifso_costs (monthly_prepayment, salaries, salaries_count, training, training_count, transport, transport_count, ifso_premium, ifso_premium_count, monthly_accommodation, monthly_accommodation_count) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    return $stmt->execute([
        $monthlyPrepayment,
        $salaries,
        $salariesCount,
        $training,
        $trainingCount,
        $transport,
        $transportCount,
        $ifsoPremium,
        $ifsoPremiumCount,
        $monthlyAccommodation,
        $monthlyAccommodationCount
    ]);
}

/**
 * Update IFSO cost record
 */
function updateIFSOCost($id, $monthlyPrepayment, $salaries, $salariesCount, $training, $trainingCount, $transport, $transportCount, $ifsoPremium, $ifsoPremiumCount, $monthlyAccommodation, $monthlyAccommodationCount) {
    $db = getDBConnection();
    
    $stmt = $db->prepare("UPDATE ifso_costs 
                          SET monthly_prepayment = ?, salaries = ?, salaries_count = ?, training = ?, training_count = ?, 
                              transport = ?, transport_count = ?, ifso_premium = ?, ifso_premium_count = ?, 
                              monthly_accommodation = ?, monthly_accommodation_count = ?
                          WHERE id = ?");
    
    return $stmt->execute([
        $monthlyPrepayment,
        $salaries,
        $salariesCount,
        $training,
        $trainingCount,
        $transport,
        $transportCount,
        $ifsoPremium,
        $ifsoPremiumCount,
        $monthlyAccommodation,
        $monthlyAccommodationCount,
        $id
    ]);
}

/**
 * Delete IFSO cost record
 */
function deleteIFSOCost($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM ifso_costs WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Get IFSO cost by ID
 */
function getIFSOCostById($id) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM ifso_costs WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get all IFSO costs
 */
function getAllIFSOCosts() {
    $db = getDBConnection();
    $stmt = $db->query("SELECT * FROM ifso_costs ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get routes count
 */
function getRoutesCount($status = null) {
    $db = getDBConnection();
    
    if ($status) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM routes WHERE status = ?");
        $stmt->execute([$status]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM routes");
        $stmt->execute();
    }
    
    return $stmt->fetchColumn();
}

/**
 * Get all routes with fix time information
 */
function getAllRoutesWithFixTime() {
    $db = getDBConnection();
    
    $sql = "SELECT r.*, 
                   o.station_name as origin_station, o.iata_code as origin_iata,
                   d.station_name as destination_station, d.iata_code as destination_iata
            FROM routes r
            JOIN stations o ON r.origin_station_id = o.id
            JOIN stations d ON r.destination_station_id = d.id
            ORDER BY r.route_code";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update fix time for a specific route
 */
function updateRouteFixTime($routeId, $fixTime) {
    $db = getDBConnection();
    
    // If fix time is 0, set it to NULL
    $fixTimeValue = $fixTime > 0 ? $fixTime : null;
    
    $stmt = $db->prepare("UPDATE routes SET fix_time = ? WHERE id = ?");
    return $stmt->execute([$fixTimeValue, $routeId]);
}

/**
 * Get route distance by IATA codes
 * Returns distance in kilometers, or null if route not found
 */
function getRouteDistanceByIATA($originIata, $destinationIata) {
    if (empty($originIata) || empty($destinationIata)) {
        return null;
    }
    
    $db = getDBConnection();
    
    // Find route by matching origin and destination IATA codes
    $stmt = $db->prepare("SELECT r.distance_nm 
                          FROM routes r
                          JOIN stations o ON r.origin_station_id = o.id
                          JOIN stations d ON r.destination_station_id = d.id
                          WHERE o.iata_code = ? AND d.iata_code = ?
                          LIMIT 1");
    $stmt->execute([$originIata, $destinationIata]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && isset($result['distance_nm']) && $result['distance_nm'] !== null) {
        // Convert nautical miles to kilometers (1 NM = 1.852 km)
        $distanceKm = floatval($result['distance_nm']) * 1.852;
        return $distanceKm;
    }
    
    return null;
}

/**
 * Bulk update fix time for all routes
 */
function bulkUpdateFixTime($fixTime) {
    $db = getDBConnection();
    
    // If fix time is 0, set it to NULL
    $fixTimeValue = $fixTime > 0 ? $fixTime : null;
    
    $stmt = $db->prepare("UPDATE routes SET fix_time = ?");
    $stmt->execute([$fixTimeValue]);
    
    return $stmt->rowCount();
}

// ==================== PAYLOAD DATA FUNCTIONS ====================

/**
 * Generate route code based on ICAO codes (origin_icao-destination_icao)
 */
function generateRouteCodeFromICAO($originIcao, $destinationIcao) {
    if (empty($originIcao) || empty($destinationIcao)) {
        return null;
    }
    return $originIcao . '-' . $destinationIcao;
}

/**
 * Get route code based on ICAO codes for a route ID
 */
function getRouteCodeFromICAO($routeId) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT o.icao_code as origin_icao, d.icao_code as destination_icao
                          FROM routes r
                          JOIN stations o ON r.origin_station_id = o.id
                          JOIN stations d ON r.destination_station_id = d.id
                          WHERE r.id = ?");
    $stmt->execute([$routeId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['origin_icao'] && $result['destination_icao']) {
        return generateRouteCodeFromICAO($result['origin_icao'], $result['destination_icao']);
    }
    
    return null;
}

/**
 * Get all routes with payload data for a specific aircraft (or all if aircraft_id is null)
 * Route codes are based on ICAO codes (origin_icao-destination_icao)
 */
function getAllRoutesWithPayloadData($aircraftId = null) {
    $db = getDBConnection();
    
    $sql = "SELECT r.*, 
                   o.station_name as origin_station, o.iata_code as origin_iata, o.icao_code as origin_icao,
                   d.station_name as destination_station, d.iata_code as destination_iata, d.icao_code as destination_icao,
                   CONCAT(IFNULL(o.icao_code, ''), '-', IFNULL(d.icao_code, '')) as route_code_icao,
                   p.id as payload_id, p.aircraft_id, p.aircraft_registration,
                   p.temperature_20, p.temperature_25, p.temperature_35, p.temperature_40, p.notes as payload_notes,
                   a.registration as aircraft_reg, a.aircraft_type
            FROM routes r
            JOIN stations o ON r.origin_station_id = o.id
            JOIN stations d ON r.destination_station_id = d.id";
    
    if ($aircraftId) {
        $sql .= " LEFT JOIN payload_data p ON CONCAT(IFNULL(o.icao_code, ''), '-', IFNULL(d.icao_code, '')) = p.route_code AND p.aircraft_id = ?";
    } else {
        $sql .= " LEFT JOIN payload_data p ON CONCAT(IFNULL(o.icao_code, ''), '-', IFNULL(d.icao_code, '')) = p.route_code";
    }
    
    $sql .= " LEFT JOIN aircraft a ON p.aircraft_id = a.id
            WHERE o.icao_code IS NOT NULL AND o.icao_code != '' 
            AND d.icao_code IS NOT NULL AND d.icao_code != ''
            ORDER BY route_code_icao";
    
    $stmt = $db->prepare($sql);
    
    if ($aircraftId) {
        $stmt->execute([$aircraftId]);
    } else {
        $stmt->execute();
    }
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update route_code in results to use ICAO-based route code
    foreach ($results as &$result) {
        $result['route_code'] = $result['route_code_icao'];
    }
    
    return $results;
}

/**
 * Get payload data for a specific route and aircraft combination
 */
function getPayloadDataByRouteCodeAndAircraft($routeCode, $aircraftId) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM payload_data WHERE route_code = ? AND aircraft_id = ?");
    $stmt->execute([$routeCode, $aircraftId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Create or update payload data for a route and aircraft combination
 */
function savePayloadData($routeCode, $aircraftId, $data, $userId = null) {
    $db = getDBConnection();
    
    // Get aircraft registration for reference
    $aircraftReg = null;
    if ($aircraftId) {
        $stmt = $db->prepare("SELECT registration FROM aircraft WHERE id = ?");
        $stmt->execute([$aircraftId]);
        $aircraft = $stmt->fetch(PDO::FETCH_ASSOC);
        $aircraftReg = $aircraft['registration'] ?? null;
    }
    
    // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation
    // This handles both insert and update in one query, preventing race conditions
    // Works with unique key on (route_code, aircraft_id)
    $stmt = $db->prepare("INSERT INTO payload_data 
                         (route_code, aircraft_id, aircraft_registration, temperature_20, temperature_25, temperature_35, temperature_40, notes, created_by) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                             temperature_20 = VALUES(temperature_20),
                             temperature_25 = VALUES(temperature_25),
                             temperature_35 = VALUES(temperature_35),
                             temperature_40 = VALUES(temperature_40),
                             notes = VALUES(notes),
                             aircraft_registration = VALUES(aircraft_registration),
                             updated_at = CURRENT_TIMESTAMP,
                             created_by = VALUES(created_by)");
    
    return $stmt->execute([
        $routeCode,
        $aircraftId,
        $aircraftReg,
        $data['temperature_20'] ?? null,
        $data['temperature_25'] ?? null,
        $data['temperature_35'] ?? null,
        $data['temperature_40'] ?? null,
        $data['notes'] ?? null,
        $userId
    ]);
}

/**
 * Delete payload data for a route and aircraft combination
 */
function deletePayloadData($routeCode, $aircraftId) {
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM payload_data WHERE route_code = ? AND aircraft_id = ?");
    return $stmt->execute([$routeCode, $aircraftId]);
}

/**
 * Get all aircraft for dropdown
 */
function getAllAircraftForPayload() {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, registration, aircraft_type FROM aircraft WHERE status = 'active' ORDER BY registration");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all routes with ICAO codes for dropdown selection
 */
function getAllRoutesForPayloadDropdown() {
    $db = getDBConnection();
    
    $sql = "SELECT r.*, 
                   o.station_name as origin_station, o.icao_code as origin_icao,
                   d.station_name as destination_station, d.icao_code as destination_icao,
                   CONCAT(IFNULL(o.icao_code, ''), '-', IFNULL(d.icao_code, '')) as route_code_icao
            FROM routes r
            JOIN stations o ON r.origin_station_id = o.id
            JOIN stations d ON r.destination_station_id = d.id
            WHERE o.icao_code IS NOT NULL AND o.icao_code != '' 
            AND d.icao_code IS NOT NULL AND d.icao_code != ''
            ORDER BY route_code_icao";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update route_code in results to use ICAO-based route code
    foreach ($results as &$result) {
        $result['route_code'] = $result['route_code_icao'];
    }
    
    return $results;
}

/**
 * Get stations count
 */
function getStationsCount($filter = null) {
    $db = getDBConnection();
    
    if ($filter === 'base') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM stations WHERE is_base = 1");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM stations");
        $stmt->execute();
    }
    
    return $stmt->fetchColumn();
}


// Flight Time Management Functions
/**
 * Get all unique crew members from flights table
 */
function getAllCrewMembersFromFlights() {
    try {
        $pdo = getDBConnection();
        // Get all unique crew member IDs from Crew1 to Crew10
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as name
            FROM flights f
            INNER JOIN users u ON (
                f.Crew1 = u.id OR f.Crew2 = u.id OR f.Crew3 = u.id OR 
                f.Crew4 = u.id OR f.Crew5 = u.id OR f.Crew6 = u.id OR 
                f.Crew7 = u.id OR f.Crew8 = u.id OR f.Crew9 = u.id OR 
                f.Crew10 = u.id
            )
            WHERE f.TaskStart IS NOT NULL 
            AND f.TaskEnd IS NOT NULL
            AND f.TaskStart != '' 
            AND f.TaskEnd != ''
            AND u.id IS NOT NULL
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $allCrewMembers = [];
        foreach ($results as $row) {
            $crewId = $row['id'];
            $crewName = $row['name'];
            if (!empty($crewId) && !isset($allCrewMembers[$crewId])) {
                $allCrewMembers[$crewId] = $crewName;
            }
        }
        
        // Return as array of IDs (for backward compatibility, we'll use ID as key)
        // But we'll store name for display
        return array_keys($allCrewMembers);
    } catch(PDOException $e) {
        error_log("Error getting crew members from flights: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate total flight hours for a specific crew member
 * @param int|string $crewMemberId - User ID (int) or name (string) for backward compatibility
 */
function getCrewMemberFlightHours($crewMemberId) {
    // If it's a string (name), try to find user ID first
    if (is_string($crewMemberId) && !is_numeric($crewMemberId)) {
        // Try to find user by name (backward compatibility)
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE CONCAT(first_name, ' ', last_name) = ? LIMIT 1");
        $stmt->execute([$crewMemberId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $crewMemberId = $user['id'];
        } else {
            return [
                'total_hours' => 0,
                'flight_count' => 0,
                'flights' => []
            ];
        }
    }
    
    // Use the new function with periods but return only basic data for backward compatibility
    $data = getCrewMemberFlightHoursWithPeriods(intval($crewMemberId));
    return [
        'total_hours' => $data['total_hours'],
        'flight_count' => $data['flight_count'],
        'flights' => $data['flights']
    ];
}

/**
 * Get crew member flight hours with period calculations
 */
function getCrewMemberFlightHoursWithPeriods($crewMemberId) {
    try {
        $pdo = getDBConnection();
        // Get user info first
        $user = getUserById($crewMemberId);
        if (!$user) {
            return [
                'total_hours' => 0,
                'flight_count' => 0,
                'flights' => [],
                'periods' => [
                    '24h' => 0,
                    '7d' => 0,
                    '14d' => 0,
                    '28d' => 0,
                    '12m' => 0,
                    '1cy' => 0,
                    '168h' => 0
                ]
            ];
        }
        
        // Get flights where this crew member is in Crew1-Crew10
        $stmt = $pdo->prepare("
            SELECT 
                f.id,
                f.TaskStart,
                f.TaskEnd,
                f.FltDate,
                f.Route,
                f.Rego,
                f.TaskName,
                f.FlightNo
            FROM flights f
            WHERE (
                f.Crew1 = ? OR f.Crew2 = ? OR f.Crew3 = ? OR 
                f.Crew4 = ? OR f.Crew5 = ? OR f.Crew6 = ? OR 
                f.Crew7 = ? OR f.Crew8 = ? OR f.Crew9 = ? OR 
                f.Crew10 = ?
            )
            AND f.TaskStart IS NOT NULL 
            AND f.TaskEnd IS NOT NULL
            AND f.TaskStart != '' 
            AND f.TaskEnd != ''
            ORDER BY f.FltDate DESC
        ");
        $stmt->execute([$crewMemberId, $crewMemberId, $crewMemberId, $crewMemberId, $crewMemberId, 
                        $crewMemberId, $crewMemberId, $crewMemberId, $crewMemberId, $crewMemberId]);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalHours = 0;
        $flightDetails = [];
        $today = new DateTime();
        
        // Initialize period calculations
        $periods = [
            '24h' => 0,     // 24 hours before
            '7d' => 0,      // 7 days before
            '14d' => 0,     // 14 days before  
            '28d' => 0,     // 28 days before
            '12m' => 0,     // 12 months before
            '1cy' => 0,     // 1 calendar year
            '168h' => 0     // 168 hours (7 days) before
        ];
        
        foreach ($flights as $flight) {
            $taskStart = new DateTime($flight['TaskStart']);
            $taskEnd = new DateTime($flight['TaskEnd']);
            $flightDate = new DateTime($flight['FltDate']);
            $duration = $taskStart->diff($taskEnd);
            
            // Convert to decimal hours
            $hours = $duration->h + ($duration->i / 60) + ($duration->s / 3600);
            $totalHours += $hours;
            
            // Calculate periods
            $daysDiff = $today->diff($flightDate)->days;
            $hoursDiff = $today->diff($taskStart)->h + ($today->diff($taskStart)->days * 24);
            
            // 24 hours before
            if ($hoursDiff <= 24) {
                $periods['24h'] += $hours;
            }
            
            // 7 days before
            if ($daysDiff <= 7) {
                $periods['7d'] += $hours;
            }
            
            // 14 days before
            if ($daysDiff <= 14) {
                $periods['14d'] += $hours;
            }
            
            // 28 days before
            if ($daysDiff <= 28) {
                $periods['28d'] += $hours;
            }
            
            // 12 months before
            if ($daysDiff <= 365) {
                $periods['12m'] += $hours;
            }
            
            // 1 calendar year (current year)
            if ($flightDate->format('Y') == $today->format('Y')) {
                $periods['1cy'] += $hours;
            }
            
            // 168 hours (7 days) before
            if ($hoursDiff <= 168) {
                $periods['168h'] += $hours;
            }
            
            $flightDetails[] = [
                'id' => $flight['id'],
                'date' => $flight['FltDate'],
                'route' => $flight['Route'],
                'rego' => $flight['Rego'],
                'flight_no' => $flight['FlightNo'],
                'task_start' => $flight['TaskStart'],
                'task_end' => $flight['TaskEnd'],
                'hours' => round($hours, 2)
            ];
        }
        
        return [
            'total_hours' => round($totalHours, 2),
            'flight_count' => count($flightDetails),
            'flights' => $flightDetails,
            'periods' => $periods
        ];
        
    } catch (Exception $e) {
        error_log("Error getting crew member flight hours: " . $e->getMessage());
        return [
            'total_hours' => 0,
            'flight_count' => 0,
            'flights' => [],
            'periods' => [
                '24h' => 0,
                '7d' => 0,
                '14d' => 0,
                '28d' => 0,
                '12m' => 0,
                '1cy' => 0,
                '168h' => 0
            ]
        ];
    }
}

/**
 * Get flight time summary for all crew members
 */
function getFlightTimeSummary() {
    try {
        $pdo = getDBConnection();
        // Get all unique crew member IDs from Crew1 to Crew10 with their names
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as name
            FROM flights f
            INNER JOIN users u ON (
                f.Crew1 = u.id OR f.Crew2 = u.id OR f.Crew3 = u.id OR 
                f.Crew4 = u.id OR f.Crew5 = u.id OR f.Crew6 = u.id OR 
                f.Crew7 = u.id OR f.Crew8 = u.id OR f.Crew9 = u.id OR 
                f.Crew10 = u.id
            )
            WHERE f.TaskStart IS NOT NULL 
            AND f.TaskEnd IS NOT NULL
            AND f.TaskStart != '' 
            AND f.TaskEnd != ''
            AND u.id IS NOT NULL
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute();
        $crewMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $summary = [];
        foreach ($crewMembers as $member) {
            $memberId = $member['id'];
            $memberName = $member['name'];
            $memberData = getCrewMemberFlightHoursWithPeriods($memberId);
            $summary[] = [
                'id' => $memberId,
                'name' => $memberName,
                'total_hours' => $memberData['total_hours'],
                'flight_count' => $memberData['flight_count'],
                'flights' => $memberData['flights'],
                'periods' => $memberData['periods']
            ];
        }
        
        // Sort by total hours descending
        usort($summary, function($a, $b) {
            return $b['total_hours'] <=> $a['total_hours'];
        });
        
        return $summary;
    } catch(PDOException $e) {
        error_log("Error getting flight time summary: " . $e->getMessage());
        return [];
    }
}

/**
 * FDP (Flight Duty Period) Calculation Functions
 */

/**
 * Calculate FDP for a specific crew member on a specific date
 * @param int|string $crewMemberId - User ID (int) or name (string) for backward compatibility
 * @param string $date - Date in Y-m-d format
 */
function calculateCrewMemberFDP($crewMemberId, $date) {
    try {
        $pdo = getDBConnection();
        
        // If it's a string (name), try to find user ID first
        $crewId = null;
        if (is_string($crewMemberId) && !is_numeric($crewMemberId)) {
            // Try to find user by name (backward compatibility)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE CONCAT(first_name, ' ', last_name) = ? LIMIT 1");
            $stmt->execute([$crewMemberId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $crewId = $user['id'];
            } else {
                return null;
            }
        } else {
            $crewId = intval($crewMemberId);
        }
        
        // Get user info for display
        $user = getUserById($crewId);
        if (!$user) {
            return null;
        }
        $crewMemberName = $user['first_name'] . ' ' . $user['last_name'];
        
        // Get all flights for the crew member on the specific date with roles
        $stmt = $pdo->prepare("
            SELECT 
                f.id,
                f.TaskStart,
                f.TaskEnd,
                f.actual_in_utc,
                f.actual_out_utc,
                f.Route,
                f.Rego,
                f.TaskName,
                f.FlightNo,
                f.Crew1, f.Crew2, f.Crew3, f.Crew4, f.Crew5,
                f.Crew6, f.Crew7, f.Crew8, f.Crew9, f.Crew10,
                f.Crew1_role, f.Crew2_role, f.Crew3_role, f.Crew4_role, f.Crew5_role,
                f.Crew6_role, f.Crew7_role, f.Crew8_role, f.Crew9_role, f.Crew10_role
            FROM flights f
            WHERE (
                f.Crew1 = ? OR f.Crew2 = ? OR f.Crew3 = ? OR 
                f.Crew4 = ? OR f.Crew5 = ? OR f.Crew6 = ? OR 
                f.Crew7 = ? OR f.Crew8 = ? OR f.Crew9 = ? OR 
                f.Crew10 = ?
            )
            AND DATE(f.FltDate) = ?
            AND f.TaskStart IS NOT NULL 
            AND f.TaskEnd IS NOT NULL
            AND f.TaskStart != '' 
            AND f.TaskEnd != ''
            ORDER BY f.TaskStart ASC
        ");
        $stmt->execute([$crewId, $crewId, $crewId, $crewId, $crewId, 
                        $crewId, $crewId, $crewId, $crewId, $crewId, $date]);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($flights)) {
            return null;
        }
        
        // All flights are valid since we filtered by Crew1-Crew10
        // Also determine position (role) for each flight
        $validFlights = [];
        $positions = []; // Collect all positions for this crew member
        
        foreach ($flights as $flight) {
            $position = null;
            // Find which Crew position this member is in
            for ($i = 1; $i <= 10; $i++) {
                $crewField = "Crew{$i}";
                $roleField = "Crew{$i}_role";
                if ($flight[$crewField] == $crewId) {
                    $position = $flight[$roleField] ?? "Crew{$i}";
                    if (!empty($position)) {
                        $positions[] = $position;
                    }
                    break;
                }
            }
            // Add position to flight data
            $flight['position'] = $position;
            $validFlights[] = $flight;
        }
        
        // Determine the most common position (or first one if all are different)
        $position = null;
        if (!empty($positions)) {
            $positionCounts = array_count_values($positions);
            arsort($positionCounts);
            $position = array_key_first($positionCounts);
        }
        
        // Calculate FDP times
        $firstTaskStart = new DateTime($validFlights[0]['TaskStart']);
        
        // For last flight, use actual_in_utc if available, otherwise use TaskEnd
        $lastFlight = $validFlights[count($validFlights) - 1];
        if (!empty($lastFlight['actual_in_utc']) && $lastFlight['actual_in_utc'] !== null) {
            // Use actual_in_utc if it's not null/empty
            $lastTaskEnd = new DateTime($lastFlight['actual_in_utc']);
        } else {
            // Fallback to TaskEnd if actual_in_utc is not available
            $lastTaskEnd = new DateTime($lastFlight['TaskEnd']);
        }
        
        // FDP Start: First TaskStart - StandardReportingTime (default: 45 minutes for domestic PAX)
        // TODO: Determine actual reporting time based on duty type (domestic PAX, international, positioning, etc.)
        $fdpStart = clone $firstTaskStart;
        $fdpStart->sub(new DateInterval('PT45M'));
        
        // FDP End: Last TaskEnd (NOT including post-flight duty)
        $fdpEnd = clone $lastTaskEnd;
        
        // Duty Start: First TaskStart - StandardReportingTime (same as FDP Start)
        $dutyStart = clone $firstTaskStart;
        $dutyStart->sub(new DateInterval('PT45M'));
        
        // Duty End: Last TaskEnd + PostFlightDutyTime (default: 20 minutes for last leg active with PAX)
        // TODO: Determine actual post-flight duty time based on last duty type
        $dutyEnd = clone $lastTaskEnd;
        $dutyEnd->add(new DateInterval('PT20M'));
        
        // Calculate durations
        $fdpDuration = $fdpStart->diff($fdpEnd);
        $dutyDuration = $dutyStart->diff($dutyEnd);
        
        // Convert to decimal hours
        $fdpHours = $fdpDuration->h + ($fdpDuration->i / 60) + ($fdpDuration->s / 3600);
        $dutyHours = $dutyDuration->h + ($dutyDuration->i / 60) + ($dutyDuration->s / 3600);
        
        // Calculate flight hours
        $totalFlightHours = 0;
        foreach ($validFlights as $flight) {
            $taskStart = new DateTime($flight['TaskStart']);
            $taskEnd = new DateTime($flight['TaskEnd']);
            $duration = $taskStart->diff($taskEnd);
            $hours = $duration->h + ($duration->i / 60) + ($duration->s / 3600);
            $totalFlightHours += $hours;
        }
        
        // Check for Split Duty: if there are multiple flights, check for breaks between them
        $hasSplitDuty = false;
        $splitDutyBreak = null;
        $splitDutyBreakNet = null;
        $maxFDPWithSplit = null;
        
        if (count($validFlights) > 1) {
            // Check breaks between consecutive flights
            for ($i = 0; $i < count($validFlights) - 1; $i++) {
                // For current flight end: use actual_in_utc if available, otherwise use TaskEnd
                $currentFlight = $validFlights[$i];
                if (!empty($currentFlight['actual_in_utc']) && $currentFlight['actual_in_utc'] !== null) {
                    $currentFlightEnd = new DateTime($currentFlight['actual_in_utc']);
                } else {
                    $currentFlightEnd = new DateTime($currentFlight['TaskEnd']);
                }
                
                // For next flight start: use actual_out_utc if available, otherwise use TaskStart
                $nextFlight = $validFlights[$i + 1];
                if (!empty($nextFlight['actual_out_utc']) && $nextFlight['actual_out_utc'] !== null) {
                    $nextFlightStart = new DateTime($nextFlight['actual_out_utc']);
                } else {
                    $nextFlightStart = new DateTime($nextFlight['TaskStart']);
                }
                
                // Calculate break duration (exclusive of pre/post-flight duties)
                // Break = time between TaskEnd of current flight and TaskStart of next flight
                $breakDuration = $currentFlightEnd->diff($nextFlightStart);
                $breakHours = $breakDuration->h + ($breakDuration->i / 60) + ($breakDuration->days * 24);
                
                // Subtract post-flight duty (20 min = 0.333h) and pre-flight duty (45 min = 0.75h) to get BREAK_NET
                // Post-flight duty for last leg active with PAX: 20 min
                // Pre-flight duty (standard reporting): 45 min
                $breakNet = $breakHours - (20.0 / 60.0) - (45.0 / 60.0); // Subtract 1.083 hours total
                
                // BREAK_NET - 00:50 (50 minutes) must be >= 3:00 h
                // 50 minutes = 0.833 hours
                $breakNetAdjusted = $breakNet - (50.0 / 60.0);
                
                // If (BREAK_NET - 50 min) >= 3 hours, this is split duty
                if ($breakNetAdjusted >= 3.0) {
                    $hasSplitDuty = true;
                    $splitDutyBreak = $breakHours;
                    $splitDutyBreakNet = $breakNet;
                    
                    // Calculate Max FDP with split duty extension
                    $maxFDPNormal = calculateMaxFDP($fdpStart->format('Y-m-d H:i:s'), count($validFlights), true);
                    if ($maxFDPNormal !== null) {
                        $maxFDPWithSplit = calculateMaxFDPWithSplitDuty($maxFDPNormal, $breakNet);
                    }
                    break; // Only consider the first qualifying break
                }
            }
        }
        
        return [
            'date' => $date,
            'crew_member' => $crewMemberName,
            'position' => $position ?? 'N/A',
            'sectors' => count($validFlights),
            'fdp_start' => $fdpStart->format('Y-m-d H:i:s'),
            'fdp_end' => $fdpEnd->format('Y-m-d H:i:s'),
            'duty_start' => $dutyStart->format('Y-m-d H:i:s'),
            'duty_end' => $dutyEnd->format('Y-m-d H:i:s'),
            'fdp_hours' => round($fdpHours, 2),
            'duty_hours' => round($dutyHours, 2),
            'flight_hours' => round($totalFlightHours, 2),
            'routes' => implode(', ', array_column($validFlights, 'Route')),
            'aircraft' => implode(', ', array_column($validFlights, 'Rego')),
            'flights' => $validFlights,
            'has_split_duty' => $hasSplitDuty,
            'split_duty_break' => $splitDutyBreak,
            'split_duty_break_net' => $splitDutyBreakNet,
            'max_fdp_with_split' => $maxFDPWithSplit
        ];
        
    } catch (Exception $e) {
        error_log("Error calculating FDP for crew member: " . $e->getMessage());
        return null;
    }
}

/**
 * Get FDP data for a crew member with date range
 * @param int|string $crewMemberId - User ID (int) or name (string) for backward compatibility
 * @param string|null $startDate - Start date in Y-m-d format
 * @param string|null $endDate - End date in Y-m-d format
 */
function getCrewMemberFDPData($crewMemberId, $startDate = null, $endDate = null) {
    try {
        $pdo = getDBConnection();
        
        // If it's a string (name), try to find user ID first
        $crewId = null;
        if (is_string($crewMemberId) && !is_numeric($crewMemberId)) {
            // Try to find user by name (backward compatibility)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE CONCAT(first_name, ' ', last_name) = ? LIMIT 1");
            $stmt->execute([$crewMemberId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $crewId = $user['id'];
            } else {
                return [];
            }
        } else {
            $crewId = intval($crewMemberId);
        }
        
        // Get all unique dates for the crew member
        $sql = "
            SELECT DISTINCT DATE(f.FltDate) as flight_date
            FROM flights f
            WHERE (
                f.Crew1 = ? OR f.Crew2 = ? OR f.Crew3 = ? OR 
                f.Crew4 = ? OR f.Crew5 = ? OR f.Crew6 = ? OR 
                f.Crew7 = ? OR f.Crew8 = ? OR f.Crew9 = ? OR 
                f.Crew10 = ?
            )
            AND f.TaskStart IS NOT NULL 
            AND f.TaskEnd IS NOT NULL
            AND f.TaskStart != '' 
            AND f.TaskEnd != ''
        ";
        
        $params = [$crewId, $crewId, $crewId, $crewId, $crewId, 
                   $crewId, $crewId, $crewId, $crewId, $crewId];
        
        if ($startDate && $endDate) {
            $sql .= " AND DATE(f.FltDate) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY flight_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $fdpData = [];
        foreach ($dates as $date) {
            $fdp = calculateCrewMemberFDP($crewId, $date);
            if ($fdp) {
                $fdpData[] = $fdp;
            }
        }
        
        return $fdpData;
        
    } catch (Exception $e) {
        error_log("Error getting FDP data for crew member: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all crew members from flights table
 */
function getAllCrewMembersForFDP() {
    try {
        $pdo = getDBConnection();
        // Get all unique crew member IDs from Crew1 to Crew10
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as name
            FROM flights f
            INNER JOIN users u ON (
                f.Crew1 = u.id OR f.Crew2 = u.id OR f.Crew3 = u.id OR 
                f.Crew4 = u.id OR f.Crew5 = u.id OR f.Crew6 = u.id OR 
                f.Crew7 = u.id OR f.Crew8 = u.id OR f.Crew9 = u.id OR 
                f.Crew10 = u.id
            )
            WHERE f.TaskStart IS NOT NULL 
            AND f.TaskEnd IS NOT NULL
            AND f.TaskStart != '' 
            AND f.TaskEnd != ''
            AND u.id IS NOT NULL
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $allCrewMembers = [];
        foreach ($results as $row) {
            $crewId = $row['id'];
            $crewName = $row['name'];
            if (!empty($crewId) && !isset($allCrewMembers[$crewId])) {
                $allCrewMembers[$crewId] = $crewName;
            }
        }
        
        // Return as array of IDs (for backward compatibility, we'll use ID as key)
        // But we'll store name for display
        return array_keys($allCrewMembers);
    } catch(PDOException $e) {
        error_log("Error getting crew members for FDP: " . $e->getMessage());
        return [];
    }
}

/**
 * Get FDP compliance data for a specific crew member
 */
function getCrewMemberFDPCompliance($crewMemberName) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                crew_member,
                DutyDate,
                Sectors,
                DayFDPStart,
                DayFDPEnd,
                DayFDPHours,
                DayDutyHours,
                DayFlightHours,
                Routes,
                Aircraft,
                FDPStartLocalTime,
                MaxFDP_Hours,
                FDP_Status
            FROM v_fdp_compliance_crew 
            WHERE crew_member = ?
            ORDER BY DutyDate DESC
        ");
        $stmt->execute([$crewMemberName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

/**
 * Get rolling duty limits for a specific crew member
 */
function getCrewMemberDutyLimits($crewMemberName) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                crew_member,
                DutyDate,
                DayDutyHours,
                DutyH_7d,
                DutyH_14d,
                DutyH_28d
            FROM v_duty_rolling_crew 
            WHERE crew_member = ?
            ORDER BY DutyDate DESC
        ");
        $stmt->execute([$crewMemberName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

/**
 * Get rolling flight time limits for a specific crew member
 */
function getCrewMemberFlightLimits($crewMemberName) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                crew_member,
                DutyDate,
                DayFlightHours,
                FltH_28d,
                FltH_CalendarYear,
                FltH_12mo
            FROM v_flight_rolling_crew 
            WHERE crew_member = ?
            ORDER BY DutyDate DESC
        ");
        $stmt->execute([$crewMemberName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

/**
 * Calculate Max FDP based on FDP start time and sectors using Table-1 (acclimatised) or Table-2 (unknown acclimatisation)
 * @param string $fdpStart - FDP start time in 'Y-m-d H:i:s' format
 * @param int $sectors - Number of sectors
 * @param bool $isAcclimatised - Whether crew is acclimatised (default: true, uses Table-1)
 * @return float|null - Max FDP in decimal hours, or null if unable to calculate
 */
function calculateMaxFDP($fdpStart, $sectors, $isAcclimatised = true) {
    try {
        if (empty($fdpStart) || $sectors < 1) {
            return null;
        }
        
        $fdpDateTime = new DateTime($fdpStart);
        $fdpStartTime = $fdpDateTime->format('Hi'); // HHMM format (e.g., 0715, 1400)
        $fdpStartMinutes = intval(substr($fdpStartTime, 0, 2)) * 60 + intval(substr($fdpStartTime, 2, 2));
        
        // Cap sectors at 10 for table lookup
        $sectors = min($sectors, 10);
        
        // Table-2: Unknown state of acclimatisation (simpler, based only on sectors)
        if (!$isAcclimatised) {
            $table2 = [
                1 => 11.0,  // 11:00
                2 => 11.0,  // 11:00
                3 => 10.5,  // 10:30
                4 => 10.0,  // 10:00
                5 => 9.5,   // 09:30
                6 => 9.0,   // 09:00
                7 => 9.0,   // 09:00
                8 => 9.0    // 09:00
            ];
            
            if (isset($table2[$sectors])) {
                return $table2[$sectors];
            }
            return 9.0; // Default for 9+ sectors
        }
        
        // Table-1: Acclimatised crew members (based on FDP start time and sectors)
        // Time ranges in minutes from start of day
        $timeRanges = [
            ['start' => 360, 'end' => 809],   // 0600-1329 (06:00 to 13:29)
            ['start' => 810, 'end' => 839],   // 1330-1359
            ['start' => 840, 'end' => 869],   // 1400-1429
            ['start' => 870, 'end' => 899],   // 1430-1459
            ['start' => 900, 'end' => 929],   // 1500-1529
            ['start' => 930, 'end' => 959],   // 1530-1559
            ['start' => 960, 'end' => 989],   // 1600-1629
            ['start' => 990, 'end' => 1019],   // 1630-1659
            ['start' => 1020, 'end' => 299],  // 1700-0459 (wraps around)
            ['start' => 300, 'end' => 314],   // 0500-0514
            ['start' => 315, 'end' => 329],   // 0515-0529
            ['start' => 330, 'end' => 344],   // 0530-0544
            ['start' => 345, 'end' => 359]    // 0545-0559
        ];
        
        // Table-1 values: [time_range_index][sectors] => hours
        // Note: Column "1–2 Sectors" means both 1 and 2 sectors use the same value
        $table1 = [
            0 => [1 => 13.0, 2 => 13.0, 3 => 12.5, 4 => 12.0, 5 => 11.5, 6 => 11.0, 7 => 10.5, 8 => 10.0, 9 => 9.5, 10 => 9.0],  // 0600-1329
            1 => [1 => 12.75, 2 => 12.75, 3 => 12.25, 4 => 11.75, 5 => 11.25, 6 => 10.75, 7 => 10.25, 8 => 9.75, 9 => 9.25, 10 => 9.0],  // 1330-1359
            2 => [1 => 12.5, 2 => 12.5, 3 => 12.0, 4 => 11.5, 5 => 11.0, 6 => 10.5, 7 => 10.0, 8 => 9.5, 9 => 9.0, 10 => 9.0],  // 1400-1429
            3 => [1 => 12.25, 2 => 12.25, 3 => 11.75, 4 => 11.25, 5 => 10.75, 6 => 10.25, 7 => 9.75, 8 => 9.25, 9 => 9.0, 10 => 9.0],  // 1430-1459
            4 => [1 => 12.0, 2 => 12.0, 3 => 11.5, 4 => 11.0, 5 => 10.5, 6 => 10.0, 7 => 9.5, 8 => 9.0, 9 => 9.0, 10 => 9.0],  // 1500-1529
            5 => [1 => 11.75, 2 => 11.75, 3 => 11.25, 4 => 10.75, 5 => 10.25, 6 => 9.75, 7 => 9.25, 8 => 9.0, 9 => 9.0, 10 => 9.0],  // 1530-1559
            6 => [1 => 11.5, 2 => 11.5, 3 => 11.0, 4 => 10.5, 5 => 10.0, 6 => 9.5, 7 => 9.0, 8 => 9.0, 9 => 9.0, 10 => 9.0],  // 1600-1629
            7 => [1 => 11.25, 2 => 11.25, 3 => 10.75, 4 => 10.25, 5 => 9.75, 6 => 9.25, 7 => 9.0, 8 => 9.0, 9 => 9.0, 10 => 9.0],  // 1630-1659
            8 => [1 => 11.0, 2 => 11.0, 3 => 10.5, 4 => 10.0, 5 => 9.5, 6 => 9.0, 7 => 9.0, 8 => 9.0, 9 => 9.0, 10 => 9.0],  // 1700-0459
            9 => [1 => 12.0, 2 => 12.0, 3 => 11.5, 4 => 11.0, 5 => 10.5, 6 => 10.0, 7 => 9.5, 8 => 9.0, 9 => 9.0, 10 => 9.0],  // 0500-0514
            10 => [1 => 12.25, 2 => 12.25, 3 => 11.75, 4 => 11.25, 5 => 10.75, 6 => 10.25, 7 => 9.75, 8 => 9.25, 9 => 9.0, 10 => 9.0],  // 0515-0529
            11 => [1 => 12.5, 2 => 12.5, 3 => 12.0, 4 => 11.5, 5 => 11.0, 6 => 10.5, 7 => 10.0, 8 => 9.5, 9 => 9.0, 10 => 9.0],  // 0530-0544
            12 => [1 => 12.75, 2 => 12.75, 3 => 12.25, 4 => 11.75, 5 => 11.25, 6 => 10.75, 7 => 10.25, 8 => 9.75, 9 => 9.25, 10 => 9.0]  // 0545-0559
        ];
        
        // Find which time range the FDP start falls into
        $timeRangeIndex = null;
        foreach ($timeRanges as $index => $range) {
            if ($range['start'] <= $range['end']) {
                // Normal range (doesn't wrap around)
                if ($fdpStartMinutes >= $range['start'] && $fdpStartMinutes <= $range['end']) {
                    $timeRangeIndex = $index;
                    break;
                }
            } else {
                // Wraps around midnight (1700-0459)
                if ($fdpStartMinutes >= $range['start'] || $fdpStartMinutes <= $range['end']) {
                    $timeRangeIndex = $index;
                    break;
                }
            }
        }
        
        // If no range found, default to first range (0600-1329)
        if ($timeRangeIndex === null) {
            $timeRangeIndex = 0;
        }
        
        // Get max FDP from table
        if (isset($table1[$timeRangeIndex][$sectors])) {
            return $table1[$timeRangeIndex][$sectors];
        }
        
        // Default fallback
        return 9.0;
        
    } catch (Exception $e) {
        error_log("Error calculating Max FDP: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate Max FDP with standby reduction
 * @param string $fdpStart - FDP start time in 'Y-m-d H:i:s' format
 * @param int $sectors - Number of sectors
 * @param bool $isAcclimatised - Whether crew is acclimatised
 * @param float|null $standbyUsed - Standby time used in hours (if called from standby)
 * @param bool $hasSplitDuty - Whether split duty is used in this FDP
 * @return float|null - Max FDP in decimal hours, or null if unable to calculate
 */
function calculateMaxFDPWithStandby($fdpStart, $sectors, $isAcclimatised = true, $standbyUsed = null, $hasSplitDuty = false) {
    try {
        // Get base max FDP
        $maxFDP = calculateMaxFDP($fdpStart, $sectors, $isAcclimatised);
        
        if ($maxFDP === null || $standbyUsed === null || $standbyUsed <= 0) {
            return $maxFDP;
        }
        
        // Determine threshold (6h normally, 8h if split duty)
        $threshold = $hasSplitDuty ? 8.0 : 6.0;
        
        // If standby used > threshold, reduce max FDP
        if ($standbyUsed > $threshold) {
            $reduction = $standbyUsed - $threshold;
            $maxFDP = max(0, $maxFDP - $reduction);
        }
        
        return $maxFDP;
    } catch (Exception $e) {
        error_log("Error calculating Max FDP with standby: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate Max FDP with split duty extension
 * @param float $maxFDPNormal - Normal max FDP in hours
 * @param float $breakNet - Net break duration in hours (exclusive of pre/post-flight duties)
 * @return float - Max FDP with split duty extension
 */
function calculateMaxFDPWithSplitDuty($maxFDPNormal, $breakNet) {
    try {
        // BREAK_NET - 00:50 (50 minutes) must be >= 3:00 h
        // 50 minutes = 0.833 hours
        $breakNetAdjusted = $breakNet - (50.0 / 60.0);
        
        // Minimum break must be 3 hours after subtracting 50 minutes
        if ($breakNetAdjusted < 3.0) {
            return $maxFDPNormal; // No extension if (BREAK_NET - 50 min) < 3h
        }
        
        // FDP may be increased by up to 50% of BREAK_NET
        $extension = 0.5 * $breakNet;
        return $maxFDPNormal + $extension;
    } catch (Exception $e) {
        error_log("Error calculating Max FDP with split duty: " . $e->getMessage());
        return $maxFDPNormal;
    }
}

/**
 * Calculate standby duty equivalent
 * @param float $standbyDuration - Standby duration in hours
 * @return float - Duty equivalent in hours (25% of standby duration)
 */
function calculateStandbyDutyEquivalent($standbyDuration) {
    return 0.25 * $standbyDuration;
}

/**
 * Check if standby duration exceeds limit
 * @param float $standbyDuration - Standby duration in hours
 * @return bool - True if exceeds 16 hours
 */
function checkStandbyDurationLimit($standbyDuration) {
    return $standbyDuration > 16.0;
}

/**
 * Calculate minimum rest period
 * @param float $precedingDutyDuration - Preceding duty duration in hours
 * @param bool $isHomeBase - Whether duty starts at home base
 * @return float - Minimum rest required in hours
 */
function calculateMinimumRest($precedingDutyDuration, $isHomeBase = true) {
    if ($isHomeBase) {
        // Before FDP starting at home base: max(preceding_duty_duration, 12:00)
        return max($precedingDutyDuration, 12.0);
    } else {
        // Before FDP starting away from home base: max(preceding_duty_duration, 10:00)
        return max($precedingDutyDuration, 10.0);
    }
}

/**
 * Get post-flight duty time based on duty type
 * @param string $dutyType - Duty type (e.g., 'PAX', 'POSITIONING', 'NO_PAX')
 * @return float - Post-flight duty time in hours
 */
function getPostFlightDutyTime($dutyType) {
    // Table-1: Standard Post-Flight Duty
    switch (strtoupper($dutyType)) {
        case 'PAX':
        case 'LAST_LEG_ACTIVE_WITH_PAX':
            return 20.0 / 60.0; // 00:20 = 0.333 hours
        case 'POSITIONING':
        case 'CREW_POSITIONING':
        case 'SPLIT_DUTY_LAST_LEG':
            return 20.0 / 60.0; // 00:20
        case 'NO_PAX':
        case 'FERRY':
        case 'DELIVERY':
        case 'LOCAL':
        case 'LAST_LEG_ACTIVE_NO_PAX':
            return 10.0 / 60.0; // 00:10
        default:
            return 20.0 / 60.0; // Default to 20 minutes
    }
}

/**
 * Check if rest period meets minimum requirements
 * @param float $restDuration - Rest duration in hours
 * @param float $precedingDutyDuration - Preceding duty duration in hours
 * @param bool $isHomeBase - Whether duty starts at home base
 * @return array - ['compliant' => bool, 'required' => float, 'actual' => float]
 */
function checkRestCompliance($restDuration, $precedingDutyDuration, $isHomeBase = true) {
    $required = calculateMinimumRest($precedingDutyDuration, $isHomeBase);
    return [
        'compliant' => $restDuration >= $required,
        'required' => $required,
        'actual' => $restDuration
    ];
}

/**
 * Check if extended recovery rest meets requirements
 * @param float $restDuration - Rest duration in hours
 * @param int $localNights - Number of local nights included
 * @return array - ['compliant' => bool, 'required_duration' => float, 'required_nights' => int]
 */
function checkExtendedRecoveryRest($restDuration, $localNights) {
    $requiredDuration = 36.0; // 36 hours minimum
    $requiredNights = 2; // 2 local nights minimum
    
    return [
        'compliant' => $restDuration >= $requiredDuration && $localNights >= $requiredNights,
        'required_duration' => $requiredDuration,
        'required_nights' => $requiredNights,
        'actual_duration' => $restDuration,
        'actual_nights' => $localNights
    ];
}

/**
 * Check if FDP extension is allowed
 * @param int $sectors - Number of sectors
 * @param float|null $woclEncroachment - WOCL encroachment in hours (null if not encroached)
 * @param int $extensionsIn7Days - Number of extensions in last 7 days
 * @return array - ['allowed' => bool, 'max_extension' => float, 'reason' => string]
 */
function checkFDPExtensionAllowed($sectors, $woclEncroachment = null, $extensionsIn7Days = 0) {
    // Max extension: up to +1:00
    $maxExtension = 1.0;
    
    // Not more than twice in 7 consecutive days
    if ($extensionsIn7Days >= 2) {
        return [
            'allowed' => false,
            'max_extension' => 0,
            'reason' => 'Maximum 2 extensions allowed in 7 consecutive days'
        ];
    }
    
    // Sector limits when using extension
    if ($woclEncroachment === null || $woclEncroachment <= 0) {
        // WOCL not encroached: Max 5 sectors
        if ($sectors > 5) {
            return [
                'allowed' => false,
                'max_extension' => 0,
                'reason' => 'Max 5 sectors allowed when WOCL not encroached'
            ];
        }
    } elseif ($woclEncroachment <= 2.0) {
        // WOCL encroached by ≤ 2h: Max 4 sectors
        if ($sectors > 4) {
            return [
                'allowed' => false,
                'max_extension' => 0,
                'reason' => 'Max 4 sectors allowed when WOCL encroached by ≤ 2h'
            ];
        }
    } else {
        // WOCL encroached by > 2h: Max 2 sectors
        if ($sectors > 2) {
            return [
                'allowed' => false,
                'max_extension' => 0,
                'reason' => 'Max 2 sectors allowed when WOCL encroached by > 2h'
            ];
        }
    }
    
    return [
        'allowed' => true,
        'max_extension' => $maxExtension,
        'reason' => ''
    ];
}

/**
 * Check nutrition requirements for FDP
 * @param float $fdpHours - FDP duration in hours
 * @return array - ['required' => bool, 'message' => string]
 */
function checkNutritionRequirements($fdpHours) {
    if ($fdpHours > 6.0) {
        return [
            'required' => true,
            'message' => 'Ensure 2 meal opportunities planned (OM-A 7.4.3)'
        ];
    }
    return [
        'required' => false,
        'message' => ''
    ];
}

/**
 * Get FDP summary for all crew members
 */
function getFDPSummary() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                crew_member,
                COUNT(DISTINCT DutyDate) as total_duty_days,
                SUM(Sectors) as total_sectors,
                SUM(DayFlightHours) as total_flight_hours,
                SUM(DayFDPHours) as total_fdp_hours,
                SUM(DayDutyHours) as total_duty_hours,
                COUNT(CASE WHEN FDP_Status = 'EXCEEDED' THEN 1 END) as fdp_violations,
                MAX(DutyDate) as last_duty_date,
                MIN(DutyDate) as first_duty_date
            FROM v_fdp_compliance_crew 
            GROUP BY crew_member
            ORDER BY total_flight_hours DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

/**
 * Get FDP violations (exceeded limits)
 */
function getFDPViolations() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                c.crew_member,
                c.DutyDate,
                c.Sectors,
                c.DayFDPHours,
                c.MaxFDP_Hours,
                c.FDP_Status,
                c.Routes,
                c.Aircraft
            FROM v_fdp_compliance_crew c
            WHERE c.FDP_Status = 'EXCEEDED'
            ORDER BY c.DutyDate DESC, c.DayFDPHours DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

/**
 * Get duty limit violations
 */
function getDutyLimitViolations() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                d.crew_member,
                d.DutyDate,
                d.DayDutyHours,
                d.DutyH_7d,
                d.DutyH_14d,
                d.DutyH_28d,
                CASE 
                    WHEN d.DutyH_7d > 60 THEN '7-day limit exceeded'
                    WHEN d.DutyH_14d > 110 THEN '14-day limit exceeded'
                    WHEN d.DutyH_28d > 190 THEN '28-day limit exceeded'
                    ELSE 'No violation'
                END as violation_type
            FROM v_duty_rolling_crew d
            WHERE d.DutyH_7d > 60 
               OR d.DutyH_14d > 110 
               OR d.DutyH_28d > 190
            ORDER BY d.DutyDate DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

/**
 * Get flight time limit violations
 */
function getFlightTimeLimitViolations() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                f.crew_member,
                f.DutyDate,
                f.DayFlightHours,
                f.FltH_28d,
                f.FltH_CalendarYear,
                f.FltH_12mo,
                CASE 
                    WHEN f.FltH_28d > 100 THEN '28-day limit exceeded'
                    WHEN f.FltH_CalendarYear > 900 THEN 'Calendar year limit exceeded'
                    WHEN f.FltH_12mo > 1000 THEN '12-month limit exceeded'
                    ELSE 'No violation'
                END as violation_type
            FROM v_flight_rolling_crew f
            WHERE f.FltH_28d > 100 
               OR f.FltH_CalendarYear > 900 
               OR f.FltH_12mo > 1000
            ORDER BY f.DutyDate DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

/**
 * Get detailed FDP data for a specific date range
 */
function getFDPDataByDateRange($startDate, $endDate, $crewMember = null) {
    try {
        $pdo = getDBConnection();
        
        $sql = "
            SELECT 
                c.crew_member,
                c.DutyDate,
                c.Sectors,
                c.DayFDPStart,
                c.DayFDPEnd,
                c.DayFDPHours,
                c.DayDutyHours,
                c.DayFlightHours,
                c.Routes,
                c.Aircraft,
                c.FDPStartLocalTime,
                c.MaxFDP_Hours,
                c.FDP_Status
            FROM v_fdp_compliance_crew c
            WHERE c.DutyDate BETWEEN ? AND ?
        ";
        
        $params = [$startDate, $endDate];
        
        if ($crewMember) {
            $sql .= " AND c.crew_member = ?";
            $params[] = $crewMember;
        }
        
        $sql .= " ORDER BY c.DutyDate DESC, c.crew_member";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

// ========================================
// TICKETS FUNCTIONS
// ========================================

/**
 * Save tickets data to database
 */
function saveTicketsToDatabase($tickets_data, $user_id = null) {
    try {
        $db = getDBConnection();
        
        // Clear existing tickets first
        $db->exec("TRUNCATE TABLE tickets");
        
        if (empty($tickets_data)) {
            return true;
        }
        
        $sql = "INSERT INTO tickets (
            origin, destination, sales_date_gmt, passenger_full_name, 
            coupon_status, passenger_contact, pnr, docs, departure_date, 
            flight_no, ticket_code, flight_class_code, office_code, office_name
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        
        foreach ($tickets_data as $ticket) {
            $stmt->execute([
                $ticket['origin'] ?? null,
                $ticket['destination'] ?? null,
                $ticket['sales_date_gmt'] ?? null,
                $ticket['passenger_full_name'] ?? null,
                $ticket['coupon_status'] ?? null,
                $ticket['passenger_contact'] ?? null,
                $ticket['pnr'] ?? null,
                $ticket['docs'] ?? null,
                $ticket['departure_date'] ?? null,
                $ticket['flight_no'] ?? null,
                $ticket['ticket_code'] ?? null,
                $ticket['flight_class_code'] ?? null,
                $ticket['office_code'] ?? null,
                $ticket['office_name'] ?? null
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error saving tickets to database: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all tickets from database with pagination
 */
function getAllTickets($page = 1, $limit = 100, $search = '') {
    try {
        $db = getDBConnection();
        $offset = ($page - 1) * $limit;
        
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE 
                ticket_code LIKE ? OR 
                docs LIKE ? OR 
                passenger_contact LIKE ? OR 
                passenger_full_name LIKE ? OR 
                flight_no LIKE ? OR 
                pnr LIKE ?";
            $searchParam = "%$search%";
            $params = array_fill(0, 6, $searchParam);
        }
        
        $sql = "SELECT * FROM tickets $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting tickets: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total count of tickets
 */
function getTicketsCount($search = '') {
    try {
        $db = getDBConnection();
        
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE 
                ticket_code LIKE ? OR 
                docs LIKE ? OR 
                passenger_contact LIKE ? OR 
                passenger_full_name LIKE ? OR 
                flight_no LIKE ? OR 
                pnr LIKE ?";
            $searchParam = "%$search%";
            $params = array_fill(0, 6, $searchParam);
        }
        
        $sql = "SELECT COUNT(*) as total FROM tickets $whereClause";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting tickets count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Call external API to get tickets data (JSON POST + headers)
 *
 * @param array $filters   Associative array of filters to send in JSON body
 * @return array           Decoded JSON response as array with 'success'/'data' or error details
 */
function fetchTicketsFromAPI(array $filters = []) {
    // If you prefer not to hardcode, pass it as a parameter or read from config/env
    $apiUrl = 'https://mehdizenhari.com/pax/pax_api.php';
    $apiKey = 'f9164750-acf1-440c-9248-77b74c54fee8';

    try {
        $payload = json_encode($filters, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new Exception('Failed to encode filters to JSON: ' . json_last_error_msg());
        }

        $headers = [
            'X-API-KEY: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: 1.0 (+fetchTicketsFromAPI)'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => true,  // keep secure (set false only if you must)
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("cURL Error: $curlError");
        }
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: $httpCode; Body: " . substr($response, 0, 500));
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new Exception('JSON decode error: ' . json_last_error_msg());
        }

        return $data;
    } catch (Exception $e) {
        error_log("Error fetching tickets from API: " . $e->getMessage());
        return [
            'success' => false,
            'error'   => $e->getMessage(),
            'data'    => []
        ];
    }
}

// ========================================
// CREW LIST FUNCTIONS
// ========================================

/**
 * Get crew routes grouped by date and crew members
 */
function getCrewRoutesByDate($startDate = null, $endDate = null) {
    if (!$startDate) {
        $startDate = date('Y-m-d');
    }
    if (!$endDate) {
        $endDate = $startDate;
    }
    
    try {
        $db = getDBConnection();
        
        // Get all flights for the date range with Crew1-Crew10
        $stmt = $db->prepare("
            SELECT 
                f.id,
                f.FltDate,
                f.Route,
                f.TaskStart,
                f.TaskEnd,
                f.TaskName,
                f.FlightNo,
                f.Rego,
                f.Crew1, f.Crew2, f.Crew3, f.Crew4, f.Crew5,
                f.Crew6, f.Crew7, f.Crew8, f.Crew9, f.Crew10,
                f.Crew1_role, f.Crew2_role, f.Crew3_role, f.Crew4_role, f.Crew5_role,
                f.Crew6_role, f.Crew7_role, f.Crew8_role, f.Crew9_role, f.Crew10_role
            FROM flights f
            WHERE DATE(f.FltDate) BETWEEN ? AND ?
            AND (
                f.Crew1 IS NOT NULL OR f.Crew2 IS NOT NULL OR f.Crew3 IS NOT NULL OR 
                f.Crew4 IS NOT NULL OR f.Crew5 IS NOT NULL OR f.Crew6 IS NOT NULL OR 
                f.Crew7 IS NOT NULL OR f.Crew8 IS NOT NULL OR f.Crew9 IS NOT NULL OR 
                f.Crew10 IS NOT NULL
            )
            AND f.TaskStart IS NOT NULL
            AND f.TaskEnd IS NOT NULL
            ORDER BY f.FltDate ASC, f.TaskStart ASC
        ");
        $stmt->execute([$startDate, $endDate]);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($flights)) {
            return [];
        }
        
        // Get user names for all crew IDs
        $crewIds = [];
        foreach ($flights as $flight) {
            for ($i = 1; $i <= 10; $i++) {
                $crewField = "Crew{$i}";
                if (!empty($flight[$crewField])) {
                    $crewIds[$flight[$crewField]] = $flight[$crewField];
                }
            }
        }
        
        $crewNames = [];
        if (!empty($crewIds)) {
            $placeholders = str_repeat('?,', count($crewIds) - 1) . '?';
            $stmt = $db->prepare("
                SELECT id, CONCAT(first_name, ' ', last_name) as name
                FROM users
                WHERE id IN ($placeholders)
            ");
            $stmt->execute(array_values($crewIds));
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($users as $user) {
                $crewNames[$user['id']] = $user['name'];
            }
        }
        
        // Group flights by crew members
        $crewRoutes = [];
        
        foreach ($flights as $flight) {
            $date = date('Y-m-d', strtotime($flight['FltDate']));
            $flightId = $flight['id'];
            
            // Get all crew members for this flight
            $flightCrew = [];
            for ($i = 1; $i <= 10; $i++) {
                $crewField = "Crew{$i}";
                $roleField = "Crew{$i}_role";
                if (!empty($flight[$crewField])) {
                    $crewId = $flight[$crewField];
                    $crewName = $crewNames[$crewId] ?? "ID: {$crewId}";
                    $flightCrew[] = [
                        'id' => $crewId,
                        'name' => $crewName,
                        'role' => $flight[$roleField] ?? "Crew{$i}"
                    ];
                }
            }
            
            if (empty($flightCrew)) continue;
            
            // Add flight to each crew member's data
            foreach ($flightCrew as $crew) {
                $crewId = $crew['id'];
                $crewName = $crew['name'];
                
                if (!isset($crewRoutes[$date])) {
                    $crewRoutes[$date] = [];
                }
                
                if (!isset($crewRoutes[$date][$crewId])) {
                    $crewRoutes[$date][$crewId] = [
                        'name' => $crewName,
                        'routes' => [],
                        'first_task_start' => null,
                        'last_task_end' => null,
                        'flights' => [],
                        'flight_ids' => [] // Track which flights this crew member was on
                    ];
                }
                
                // Add route to the crew member's routes
                if (!empty($flight['Route'])) {
                    $crewRoutes[$date][$crewId]['routes'][] = $flight['Route'];
                }
                
                // Track first task start and last task end
                if ($crewRoutes[$date][$crewId]['first_task_start'] === null || 
                    $flight['TaskStart'] < $crewRoutes[$date][$crewId]['first_task_start']) {
                    $crewRoutes[$date][$crewId]['first_task_start'] = $flight['TaskStart'];
                }
                
                if ($crewRoutes[$date][$crewId]['last_task_end'] === null || 
                    $flight['TaskEnd'] > $crewRoutes[$date][$crewId]['last_task_end']) {
                    $crewRoutes[$date][$crewId]['last_task_end'] = $flight['TaskEnd'];
                }
                
                // Store flight details
                $crewRoutes[$date][$crewId]['flights'][] = [
                    'flight_id' => $flightId,
                    'flight_no' => $flight['TaskName'] ?: $flight['FlightNo'],
                    'route' => $flight['Route'],
                    'rego' => $flight['Rego'],
                    'task_start' => $flight['TaskStart'],
                    'task_end' => $flight['TaskEnd']
                ];
                
                // Track flight IDs
                if (!in_array($flightId, $crewRoutes[$date][$crewId]['flight_ids'])) {
                    $crewRoutes[$date][$crewId]['flight_ids'][] = $flightId;
                }
            }
        }
        
        // Group crew members by shared flights (same manifest)
        foreach ($crewRoutes as $date => &$crewMembers) {
            // Create flight groups: crew members who share the same flights
            $flightGroups = [];
            
            foreach ($crewMembers as $crewId => $data) {
                // Sort flight IDs and create a key
                $sortedFlightIds = $data['flight_ids'];
                sort($sortedFlightIds);
                $flightIdsKey = implode(',', $sortedFlightIds);
                
                if (!isset($flightGroups[$flightIdsKey])) {
                    $flightGroups[$flightIdsKey] = [];
                }
                
                $flightGroups[$flightIdsKey][] = [
                    'id' => $crewId,
                    'name' => $data['name'],
                    'data' => $data
                ];
            }
            
            // Now create manifest groups based on shared flights
            $manifestGroups = [];
            foreach ($flightGroups as $flightIdsKey => $crewGroup) {
                // Create continuous route for this group
                $allRoutes = [];
                foreach ($crewGroup as $member) {
                    if (isset($member['data']['routes']) && is_array($member['data']['routes'])) {
                        $allRoutes = array_merge($allRoutes, $member['data']['routes']);
                    }
                }
                $allRoutes = array_unique($allRoutes);
                $continuousRoute = !empty($allRoutes) ? createContinuousRoute($allRoutes) : 'Unknown Route';
                
                // Find earliest start and latest end
                $earliestStart = null;
                $latestEnd = null;
                $allFlights = [];
                $allFlightIds = [];
                
                foreach ($crewGroup as $member) {
                    if (isset($member['data']['first_task_start']) && 
                        ($earliestStart === null || $member['data']['first_task_start'] < $earliestStart)) {
                        $earliestStart = $member['data']['first_task_start'];
                    }
                    if (isset($member['data']['last_task_end']) && 
                        ($latestEnd === null || $member['data']['last_task_end'] > $latestEnd)) {
                        $latestEnd = $member['data']['last_task_end'];
                    }
                    if (isset($member['data']['flights']) && is_array($member['data']['flights'])) {
                        $allFlights = array_merge($allFlights, $member['data']['flights']);
                    }
                    if (isset($member['data']['flight_ids']) && is_array($member['data']['flight_ids'])) {
                        $allFlightIds = array_merge($allFlightIds, $member['data']['flight_ids']);
                    }
                }
                
                // Remove duplicate flights
                $uniqueFlights = [];
                $seenFlightIds = [];
                foreach ($allFlights as $flight) {
                    $flightId = $flight['flight_id'] ?? null;
                    if ($flightId && !in_array($flightId, $seenFlightIds)) {
                        $uniqueFlights[] = $flight;
                        $seenFlightIds[] = $flightId;
                    } elseif (!$flightId) {
                        // If no flight_id, use route + task_start as key
                        $flightKey = ($flight['route'] ?? '') . '_' . ($flight['task_start'] ?? '');
                        if (!in_array($flightKey, $seenFlightIds)) {
                            $uniqueFlights[] = $flight;
                            $seenFlightIds[] = $flightKey;
                        }
                    }
                }
                
                // Sort flights by task_start
                usort($uniqueFlights, function($a, $b) {
                    $timeA = strtotime($a['task_start'] ?? '');
                    $timeB = strtotime($b['task_start'] ?? '');
                    return $timeA - $timeB;
                });
                
                $manifestGroups[$continuousRoute] = [
                    'crew_members' => $crewGroup,
                    'continuous_route' => $continuousRoute,
                    'first_task_start' => $earliestStart,
                    'last_task_end' => $latestEnd,
                    'flights' => $uniqueFlights,
                    'flight_ids' => array_unique($allFlightIds)
                ];
            }
            
            // Update crew members data with manifest group info
            foreach ($crewMembers as $crewId => &$data) {
                // Find which manifest group this crew member belongs to
                foreach ($manifestGroups as $route => $manifest) {
                    foreach ($manifest['crew_members'] as $member) {
                        if ($member['id'] == $crewId) {
                            $data['continuous_route'] = $manifest['continuous_route'] ?? $route;
                            $data['manifest_group'] = array_column($manifest['crew_members'], 'name');
                            $data['manifest_route'] = $route;
                            break 2;
                        }
                    }
                }
                
                // Fallback: if no manifest group found, set default values
                if (!isset($data['continuous_route'])) {
                    $data['continuous_route'] = createContinuousRoute($data['routes'] ?? []);
                    $data['manifest_group'] = [$data['name']];
                    $data['manifest_route'] = $data['continuous_route'];
                }
            }
        }
        
        return $crewRoutes;
        
    } catch (Exception $e) {
        error_log("Error getting crew routes: " . $e->getMessage());
        return [];
    }
}

/**
 * Create continuous route string from array of routes
 */
function createContinuousRoute($routes) {
    if (empty($routes)) {
        return '';
    }
    
    // Remove duplicates while preserving order
    $uniqueRoutes = array_unique($routes);
    
    // Create continuous route string
    $continuousRoute = implode('-', $uniqueRoutes);
    
    // Clean up the route string (remove duplicate airports in sequence)
    $airports = explode('-', $continuousRoute);
    $cleanedAirports = [];
    
    foreach ($airports as $airport) {
        if (empty($cleanedAirports) || end($cleanedAirports) !== $airport) {
            $cleanedAirports[] = $airport;
        }
    }
    
    return implode('-', $cleanedAirports);
}

/**
 * Get crew statistics for a date range
 */
function getCrewStatistics($startDate = null, $endDate = null) {
    $crewRoutes = getCrewRoutesByDate($startDate, $endDate);
    
    $stats = [
        'total_days' => count($crewRoutes),
        'total_crew_members' => 0,
        'total_crew_groups' => 0,
        'unique_routes' => [],
        'crew_by_date' => []
    ];
    
    foreach ($crewRoutes as $date => $crewMembers) {
        $stats['crew_by_date'][$date] = [
            'crew_count' => count($crewMembers),
            'unique_routes' => []
        ];
        
        $routeGroups = [];
        foreach ($crewMembers as $crewMember => $data) {
            $stats['total_crew_members']++;
            $routeKey = $data['continuous_route'] ?? $data['manifest_route'] ?? createContinuousRoute($data['routes'] ?? []);
            
            // Ensure routeKey is not empty
            if (empty($routeKey)) {
                $routeKey = 'Unknown Route';
            }
            
            if (!in_array($routeKey, $stats['unique_routes'])) {
                $stats['unique_routes'][] = $routeKey;
            }
            
            if (!in_array($routeKey, $stats['crew_by_date'][$date]['unique_routes'])) {
                $stats['crew_by_date'][$date]['unique_routes'][] = $routeKey;
            }
            
            if (!isset($routeGroups[$routeKey])) {
                $routeGroups[$routeKey] = [];
            }
            $routeGroups[$routeKey][] = $crewMember;
        }
        
        $stats['crew_by_date'][$date]['crew_groups'] = count($routeGroups);
        $stats['total_crew_groups'] += count($routeGroups);
    }
    
    return $stats;
}

/**
 * Get Journey Log data for flights with standardized timing
 */
function getJourneyLogData($startDate = null, $endDate = null, $aircraft = '', $route = '') {
    try {
        $db = getDBConnection();
        
        // Default to last 7 days if no dates provided
        if (!$startDate) {
            $startDate = date('Y-m-d', strtotime('-7 days'));
        }
        if (!$endDate) {
            $endDate = date('Y-m-d');
        }
        
        $whereConditions = ["DATE(f.FltDate) BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if (!empty($aircraft)) {
            $whereConditions[] = "f.Rego LIKE ?";
            $params[] = "%{$aircraft}%";
        }
        
        if (!empty($route)) {
            $whereConditions[] = "f.Route LIKE ?";
            $params[] = "%{$route}%";
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $db->prepare("
            SELECT 
                f.FltDate,
                f.TaskName,
                f.FlightNo,
                f.Rego,
                f.Route,
                f.FirstName,
                f.LastName,
                f.TaskStart,
                f.TaskEnd,
                f.off_block,
                f.on_block,
                f.takeoff,
                f.landed,
                f.actual_out_utc,
                f.actual_off_utc,
                f.actual_on_utc,
                f.actual_in_utc,
                f.block_time_min,
                f.air_time_min,
                f.calc_warn,
                f.ACType,
                f.uplift_fuel,
                f.weight
            FROM flights f
            WHERE {$whereClause}
            ORDER BY f.FltDate DESC, f.TaskName ASC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting journey log data: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Journey Log statistics
 */
function getJourneyLogStatistics($startDate = null, $endDate = null, $aircraft = '', $route = '') {
    try {
        $db = getDBConnection();
        
        // Default to last 7 days if no dates provided
        if (!$startDate) {
            $startDate = date('Y-m-d', strtotime('-7 days'));
        }
        if (!$endDate) {
            $endDate = date('Y-m-d');
        }
        
        $whereConditions = ["DATE(f.FltDate) BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if (!empty($aircraft)) {
            $whereConditions[] = "f.Rego LIKE ?";
            $params[] = "%{$aircraft}%";
        }
        
        if (!empty($route)) {
            $whereConditions[] = "f.Route LIKE ?";
            $params[] = "%{$route}%";
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_flights,
                COALESCE(SUM(f.block_time_min), 0) as total_block_time_minutes,
                COALESCE(SUM(f.air_time_min), 0) as total_air_time_minutes,
                COALESCE(SUM(CASE WHEN f.calc_warn = 1 THEN 1 ELSE 0 END), 0) as time_warnings
            FROM flights f
            WHERE {$whereClause}
        ");
        
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_flights' => (int)$result['total_flights'],
            'total_block_time' => round($result['total_block_time_minutes'] / 60, 1),
            'total_air_time' => round($result['total_air_time_minutes'] / 60, 1),
            'time_warnings' => (int)$result['time_warnings']
        ];
        
    } catch (Exception $e) {
        error_log("Error getting journey log statistics: " . $e->getMessage());
        return [
            'total_flights' => 0,
            'total_block_time' => 0,
            'total_air_time' => 0,
            'time_warnings' => 0
        ];
    }
}

/**
 * Get pilot-based journey log data
 */
function getPilotJourneyLogData($startDate = null, $endDate = null, $pilotName = '') {
    if (!$startDate) {
        $startDate = date('Y-m-d', strtotime('-7 days'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-d');
    }
    
    try {
        $db = getDBConnection();
        
        $whereConditions = [
            'DATE(f.FltDate) BETWEEN ? AND ?',
            'f.TaskStart IS NOT NULL',
            'f.TaskEnd IS NOT NULL'
        ];
        $params = [$startDate, $endDate];
        
        if (!empty($pilotName)) {
            $whereConditions[] = 'CONCAT(f.FirstName, " ", f.LastName) LIKE ?';
            $params[] = "%{$pilotName}%";
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $db->prepare("
            SELECT 
                f.FltDate,
                f.TaskName,
                f.FlightNo,
                f.Rego,
                f.Route,
                f.FirstName,
                f.LastName,
                f.TaskStart,
                f.TaskEnd,
                f.off_block,
                f.on_block,
                f.takeoff,
                f.landed,
                f.actual_out_utc,
                f.actual_off_utc,
                f.actual_on_utc,
                f.actual_in_utc,
                f.block_time_min,
                f.air_time_min,
                f.calc_warn,
                f.ACType,
                f.uplift_fuel,
                f.weight,
                CONCAT(f.FirstName, ' ', f.LastName) AS pilot_name
            FROM flights f
            WHERE {$whereClause}
            ORDER BY f.FirstName, f.LastName, f.FltDate ASC, f.TaskStart ASC
        ");
        
        $stmt->execute($params);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by pilot
        $pilotData = [];
        foreach ($flights as $flight) {
            $pilotName = $flight['pilot_name'];
            if (!isset($pilotData[$pilotName])) {
                $pilotData[$pilotName] = [
                    'pilot_name' => $pilotName,
                    'flights' => [],
                    'total_block_time' => 0,
                    'total_air_time' => 0,
                    'flight_count' => 0
                ];
            }
            
            $pilotData[$pilotName]['flights'][] = $flight;
            $pilotData[$pilotName]['total_block_time'] += $flight['block_time_min'] ?: 0;
            $pilotData[$pilotName]['total_air_time'] += $flight['air_time_min'] ?: 0;
            $pilotData[$pilotName]['flight_count']++;
        }
        
        return $pilotData;
        
    } catch (Exception $e) {
        error_log("Error getting pilot journey log data: " . $e->getMessage());
        return [];
    }
}

/**
 * Save pilot journey log data
 */
function savePilotJourneyLog($pilotName, $logData) {
    try {
        $db = getDBConnection();
        
        // Create or update pilot journey log record
        $stmt = $db->prepare("
            INSERT INTO pilot_journey_logs (pilot_name, log_date, log_data, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
            log_data = VALUES(log_data),
            updated_at = NOW()
        ");
        
        $logJson = json_encode($logData);
        $logDate = $logData['selected_date'] ?? date('Y-m-d');
        $stmt->execute([$pilotName, $logDate, $logJson]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error saving pilot journey log: " . $e->getMessage());
        return false;
    }
}

/**
 * Get available pilots for a specific date
 */
function getAvailablePilotsForDate($date) {
    try {
        $db = getDBConnection();
        
        // Get all unique crew member IDs from Crew1-Crew10 for the selected date
        // Only include crew members with role "PIC" (Pilot In Command)
        // Use f.id to ensure each flight is counted only once, even if it has multiple PICs
        $stmt = $db->prepare("
            SELECT 
                f.id as flight_id,
                f.Crew1, f.Crew2, f.Crew3, f.Crew4, f.Crew5,
                f.Crew6, f.Crew7, f.Crew8, f.Crew9, f.Crew10,
                f.Crew1_role, f.Crew2_role, f.Crew3_role, f.Crew4_role, f.Crew5_role,
                f.Crew6_role, f.Crew7_role, f.Crew8_role, f.Crew9_role, f.Crew10_role,
                f.block_time_min,
                f.air_time_min
            FROM flights f
            WHERE DATE(f.FltDate) = ?
            AND f.TaskStart IS NOT NULL
            AND f.TaskEnd IS NOT NULL
            AND (
                (f.Crew1 IS NOT NULL AND f.Crew1_role = 'PIC') OR
                (f.Crew2 IS NOT NULL AND f.Crew2_role = 'PIC') OR
                (f.Crew3 IS NOT NULL AND f.Crew3_role = 'PIC') OR
                (f.Crew4 IS NOT NULL AND f.Crew4_role = 'PIC') OR
                (f.Crew5 IS NOT NULL AND f.Crew5_role = 'PIC') OR
                (f.Crew6 IS NOT NULL AND f.Crew6_role = 'PIC') OR
                (f.Crew7 IS NOT NULL AND f.Crew7_role = 'PIC') OR
                (f.Crew8 IS NOT NULL AND f.Crew8_role = 'PIC') OR
                (f.Crew9 IS NOT NULL AND f.Crew9_role = 'PIC') OR
                (f.Crew10 IS NOT NULL AND f.Crew10_role = 'PIC')
            )
            GROUP BY f.id
        ");
        
        $stmt->execute([$date]);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($flights)) {
            return [];
        }
        
        // Collect all unique crew IDs with PIC role and count flights
        // Use a set to track which crew members are in each flight (to avoid double counting)
        $crewData = [];
        foreach ($flights as $flight) {
            // Track unique crew IDs in this flight
            $flightCrewIds = [];
            for ($i = 1; $i <= 10; $i++) {
                $crewField = "Crew{$i}";
                $crewRoleField = "Crew{$i}_role";
                $crewId = $flight[$crewField] ?? null;
                $crewRole = $flight[$crewRoleField] ?? '';
                
                // Only include crew members with role "PIC"
                if (!empty($crewId) && strtoupper(trim($crewRole)) === 'PIC' && !in_array($crewId, $flightCrewIds)) {
                    $flightCrewIds[] = $crewId;
                    
                    if (!isset($crewData[$crewId])) {
                        $crewData[$crewId] = [
                            'id' => $crewId,
                            'flight_count' => 0,
                            'total_block_time' => 0,
                            'total_air_time' => 0
                        ];
                    }
                    
                    // Count this flight only once per crew member
                    $crewData[$crewId]['flight_count']++;
                    $crewData[$crewId]['total_block_time'] += ($flight['block_time_min'] ?? 0);
                    $crewData[$crewId]['total_air_time'] += ($flight['air_time_min'] ?? 0);
                }
            }
        }
        
        if (empty($crewData)) {
            return [];
        }
        
        // Get user details from users table
        $crewIds = array_keys($crewData);
        $placeholders = str_repeat('?,', count($crewIds) - 1) . '?';
        $stmt = $db->prepare("
            SELECT id, first_name, last_name
            FROM users
            WHERE id IN ($placeholders)
            AND status = 'active'
        ");
        $stmt->execute($crewIds);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine user data with flight statistics
        $pilots = [];
        foreach ($users as $user) {
            $crewId = $user['id'];
            if (isset($crewData[$crewId])) {
                $pilots[] = [
                    'pilot_name' => trim($user['first_name'] . ' ' . $user['last_name']),
                    'flight_count' => $crewData[$crewId]['flight_count'],
                    'total_block_time' => $crewData[$crewId]['total_block_time'],
                    'total_air_time' => $crewData[$crewId]['total_air_time']
                ];
            }
        }
        
        // Sort by pilot name
        usort($pilots, function($a, $b) {
            return strcmp($a['pilot_name'], $b['pilot_name']);
        });
        
        return $pilots;
        
    } catch (Exception $e) {
        error_log("Error getting available pilots for date: " . $e->getMessage());
        return [];
    }
}

/**
 * Get saved pilot journey logs
 */
function getSavedPilotJourneyLogs($pilotName = '') {
    try {
        $db = getDBConnection();
        
        $whereClause = '';
        $params = [];
        
        if (!empty($pilotName)) {
            $whereClause = 'WHERE pilot_name = ?';
            $params[] = $pilotName;
        }
        
        $stmt = $db->prepare("
            SELECT pilot_name, log_date, log_data, created_at, updated_at
            FROM pilot_journey_logs 
            {$whereClause}
            ORDER BY updated_at DESC
        ");
        
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON data
        foreach ($logs as &$log) {
            $log['log_data'] = json_decode($log['log_data'], true);
        }
        
        return $logs;
        
    } catch (Exception $e) {
        error_log("Error getting saved pilot journey logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Save journey log form data to database
 * Uses a single journey_log table instead of multiple tables
 */
function saveJourneyLogFormData($pilotName, $logDate, $formData) {
    try {
        $db = getDBConnection();
        
        // Check if journey_log table exists
        $stmt = $db->query("SHOW TABLES LIKE 'journey_log'");
        $journeyLogTableExists = $stmt->rowCount() > 0;
        
        if (!$journeyLogTableExists) {
            // Try to check if old tables exist
            $oldTablesExist = false;
            try {
                $stmt = $db->query("SHOW TABLES LIKE 'journey_log_entries'");
                $oldTablesExist = $stmt->rowCount() > 0;
            } catch (Exception $e) {
                $oldTablesExist = false;
            }
            
            if ($oldTablesExist) {
                throw new Exception("Table 'journey_log' does not exist, but old tables exist. Please run 'database/create_journey_log_table.sql' to create the new table.");
            } else {
                throw new Exception("Table 'journey_log' does not exist. Please run 'database/create_journey_log_table.sql' to create it.");
            }
        }
        
        // Start transaction
        $db->beginTransaction();
        
        // Delete existing entry for this pilot and date
        $stmt = $db->prepare("DELETE FROM journey_log WHERE pilot_name = ? AND (selected_date = ? OR log_date = ?)");
        $stmt->execute([$pilotName, $logDate, $logDate]);
        
        // Collect flight data (1-20 flights) into JSON array
        $flightsData = [];
        for ($i = 1; $i <= 20; $i++) {
            if (isset($formData["flight_no_$i"]) && !empty($formData["flight_no_$i"])) {
                $flightsData[] = [
                    'flight_number' => $i,
                    'flight_no' => $formData["flight_no_$i"] ?? null,
                    'pc_fo' => $formData["pc_fo_$i"] ?? null,
                    'from_airport' => $formData["from_$i"] ?? null,
                    'to_airport' => $formData["to_$i"] ?? null,
                    'ofb' => $formData["ofb_$i"] ?? null,
                    'onb' => $formData["onb_$i"] ?? null,
                    'block_time' => $formData["block_time_$i"] ?? null,
                    'atd' => $formData["atd_$i"] ?? null,
                    'ata' => $formData["ata_$i"] ?? null,
                    'air_time' => $formData["air_time_$i"] ?? null,
                    'atl_no' => $formData["atl_no_$i"] ?? null,
                    'off_block' => $formData["off_block_$i"] ?? null,
                    'takeoff' => $formData["takeoff_$i"] ?? null,
                    'landing' => $formData["landing_$i"] ?? null,
                    'on_block' => $formData["on_block_$i"] ?? null,
                    'trip_time' => $formData["trip_time_$i"] ?? null,
                    'flight_time' => $formData["flight_time_$i"] ?? null,
                    'night_time' => $formData["night_time_$i"] ?? null,
                    'uplift_ltr' => $formData["uplift_ltr_$i"] ?? null,
                    'ramp_fuel' => $formData["ramp_fuel_$i"] ?? null,
                    'arr_fuel' => $formData["arr_fuel_$i"] ?? null,
                    'total_fuel' => $formData["total_fuel_$i"] ?? null,
                    'fuel_page_no' => $formData["fuel_page_no_$i"] ?? null
                ];
            }
        }
        
        // Collect crew data (1-20 crew members) into JSON array
        $crewData = [];
        for ($i = 1; $i <= 20; $i++) {
            if (isset($formData["crew_name_$i"]) && !empty($formData["crew_name_$i"])) {
                $crewData[] = [
                    'crew_number' => $i,
                    'crew_rank' => $formData["crew_rank_$i"] ?? null,
                    'crew_name' => $formData["crew_name_$i"] ?? null,
                    'crew_national_id' => $formData["crew_national_id_$i"] ?? null,
                    'reporting_hr' => $formData["reporting_hr_$i"] ?? null,
                    'reporting_min' => $formData["reporting_min_$i"] ?? null,
                    'eng_shutdown_hr' => $formData["eng_shutdown_hr_$i"] ?? null,
                    'eng_shutdown_min' => $formData["eng_shutdown_min_$i"] ?? null,
                    'fdp_time' => $formData["fdp_time_$i"] ?? null
                ];
            }
        }
        
        // Helper function to convert empty strings to null
        $toNull = function($value) {
            return ($value === '' || $value === null) ? null : $value;
        };
        
        // Helper function to convert checkbox value to int
        $checkboxToInt = function($value) {
            return (isset($value) && ($value === true || $value === 'true' || $value === 'on' || $value === '1' || $value === 1)) ? 1 : 0;
        };
        
        // Prepare values array with proper data type conversion
        $values = [
            $pilotName,
            $logDate,
            $logDate, // log_date (legacy)
            $toNull($formData['aircraft_type'] ?? null),
            $toNull($formData['aircraft_registration'] ?? null),
            $toNull($formData['flight_date'] ?? null),
            $toNull($formData['sector_aircraft_type'] ?? null),
            $toNull($formData['sector_aircraft_reg'] ?? null),
            $toNull($formData['sector_date'] ?? null),
            // Handle checkbox values
            $checkboxToInt($formData['sector1_cm1'] ?? null),
            $checkboxToInt($formData['sector1_cm2'] ?? null),
            $checkboxToInt($formData['sector2_cm1'] ?? null),
            $checkboxToInt($formData['sector2_cm2'] ?? null),
            $checkboxToInt($formData['sector3_cm1'] ?? null),
            $checkboxToInt($formData['sector3_cm2'] ?? null),
            $checkboxToInt($formData['sector4_cm1'] ?? null),
            $checkboxToInt($formData['sector4_cm2'] ?? null),
            ($formData['sector_number'] ?? null) !== null && $formData['sector_number'] !== '' ? (int)$formData['sector_number'] : null,
            $toNull($formData['commander_comments'] ?? null),
            $toNull($formData['commander_signature'] ?? null),
            null, // flights_data as JSON - will be set below
            null  // crew_data as JSON - will be set below
        ];
        
        // Encode flights data to JSON
        $flightsJson = json_encode($flightsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonError = json_last_error();
        if ($jsonError !== JSON_ERROR_NONE) {
            throw new Exception("JSON encoding error for flights_data: " . json_last_error_msg() . " (Error code: $jsonError)");
        }
        $values[20] = $flightsJson; // Set flights_data
        
        // Encode crew data to JSON
        $crewJson = json_encode($crewData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonError = json_last_error();
        if ($jsonError !== JSON_ERROR_NONE) {
            throw new Exception("JSON encoding error for crew_data: " . json_last_error_msg() . " (Error code: $jsonError)");
        }
        $values[21] = $crewJson; // Set crew_data
        
        // Validate values count matches placeholders (22)
        if (count($values) !== 22) {
            $errorMsg = "Values count mismatch. Expected 22, got " . count($values) . ". Form data keys: " . json_encode(array_keys($formData));
            error_log("Journey Log Error: " . $errorMsg);
            throw new Exception($errorMsg);
        }
        
        $stmt = $db->prepare("
            INSERT INTO journey_log (
                pilot_name, selected_date, log_date,
                aircraft_type, aircraft_registration, flight_date,
                sector_aircraft_type, sector_aircraft_reg, sector_date,
                sector1_cm1, sector1_cm2, sector2_cm1, sector2_cm2, sector3_cm1, sector3_cm2, sector4_cm1, sector4_cm2,
                sector_number, commander_comments, commander_signature,
                flights_data, crew_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute($values);
        } catch (PDOException $e) {
            // Get detailed error information
            $errorInfo = $stmt->errorInfo();
            $errorMessage = "Error inserting into journey_log: " . $e->getMessage();
            if (!empty($errorInfo)) {
                $errorMessage .= " | SQL State: " . ($errorInfo[0] ?? 'N/A') . " | Driver Error: " . ($errorInfo[2] ?? 'N/A');
            }
            
            // Prepare detailed error log
            $detailedError = [
                'error_message' => $errorMessage,
                'pdo_exception' => $e->getMessage(),
                'sql_state' => $errorInfo[0] ?? 'N/A',
                'driver_error' => $errorInfo[2] ?? 'N/A',
                'values_count' => count($values),
                'pilot_name' => $pilotName,
                'log_date' => $logDate,
                'values_preview' => array_map(function($v) {
                    if (is_string($v) && strlen($v) > 100) {
                        return substr($v, 0, 100) . '... (truncated)';
                    }
                    return $v;
                }, $values)
            ];
            
            // Log detailed error information
            error_log("Journey Log Insert Error: " . json_encode($detailedError, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            throw new Exception($errorMessage . " | Check error logs for details.");
        }
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        
        // Get detailed error information
        $errorDetails = [
            'timestamp' => date('Y-m-d H:i:s'),
            'error_message' => $e->getMessage(),
            'exception_type' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'form_data_keys' => array_keys($formData ?? []),
            'form_data_count' => count($formData ?? []),
            'pilot_name' => $pilotName ?? 'N/A',
            'log_date' => $logDate ?? 'N/A',
            'values_count' => isset($values) ? count($values) : 'N/A',
            'flights_data_count' => isset($flightsData) ? count($flightsData) : 'N/A',
            'crew_data_count' => isset($crewData) ? count($crewData) : 'N/A'
        ];
        
        // Log to PHP error log
        error_log("Error saving journey log: " . json_encode($errorDetails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Also log to a specific file for easier debugging
        $logFile = __DIR__ . '/logs/journey_log_errors.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logEntry = date('Y-m-d H:i:s') . " - " . json_encode($errorDetails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n" . str_repeat("-", 80) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        return false;
    }
}

/**
 * Get journey log entries from database
 */
function getJourneyLogEntries($pilotName = '', $logDate = '') {
    try {
        $db = getDBConnection();
        
        $whereClause = "1=1";
        $params = [];
        
        if (!empty($pilotName)) {
            $whereClause .= " AND pilot_name = ?";
            $params[] = $pilotName;
        }
        
        if (!empty($logDate)) {
            $whereClause .= " AND log_date = ?";
            $params[] = $logDate;
        }
        
        $stmt = $db->prepare("
            SELECT * FROM journey_log_entries 
            WHERE {$whereClause}
            ORDER BY log_date DESC, leg_number ASC
        ");
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting journey log entries: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch approved documents from E-Lib API
 */
function getApprovedDocuments() {
    try {
        $url = 'https://portal.raimonairways.net/dcc/api/all_doc.php?token=b074b2d8-39d6-4f2e-9edc-fd9ab1ad17ea&date_from=2025-01-01&date_to=2025-12-31';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("E-Lib API cURL Error: " . $error);
            return ['success' => false, 'error' => 'Connection error: ' . $error];
        }
        
        if ($httpCode !== 200) {
            error_log("E-Lib API HTTP Error: " . $httpCode);
            return ['success' => false, 'error' => 'HTTP error: ' . $httpCode];
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['success']) || !$data['success']) {
            error_log("E-Lib API Response Error: " . $response);
            return ['success' => false, 'error' => 'Invalid API response'];
        }
        
        // Filter only approved documents and get latest revision for each document_code
        $approvedDocs = [];
        $latestRevisions = [];
        
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $doc) {
                if (isset($doc['final_approval_status']) && $doc['final_approval_status'] === 'approved') {
                    $docCode = $doc['document_code'];
                    $revision = intval($doc['revision'] ?? 0);
                    
                    // Keep only the latest revision for each document_code
                    if (!isset($latestRevisions[$docCode]) || $revision > $latestRevisions[$docCode]['revision']) {
                        $latestRevisions[$docCode] = $doc;
                    }
                }
            }
            
            // Convert to array
            $approvedDocs = array_values($latestRevisions);
        }
        
        return [
            'success' => true,
            'data' => $approvedDocs,
            'total' => count($approvedDocs),
            'meta' => $data['meta'] ?? []
        ];
        
    } catch (Exception $e) {
        error_log("E-Lib API Exception: " . $e->getMessage());
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}

/**
 * Get unique departments from approved documents
 */
function getDocumentDepartments() {
    $result = getApprovedDocuments();
    if (!$result['success']) {
        return [];
    }
    
    $departments = [];
    foreach ($result['data'] as $doc) {
        if (isset($doc['department']) && !empty($doc['department'])) {
            $departments[$doc['department']] = $doc['department_name'] ?? $doc['department'];
        }
    }
    
    return $departments;
}

/**
 * Get pilots from users table with role 'pilot'
 */
function getPilotsFromUsers() {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.position, r.name as role_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE r.name = 'pilot'
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting pilots from users: " . $e->getMessage());
        return [];
    }
}

/**
 * Get home bases from home_base table
 */
function getHomeBases() {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT id, location_name, short_name, city_suburb, state, country, status
            FROM home_base
            WHERE status = 'active'
            ORDER BY location_name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting home bases: " . $e->getMessage());
        return [];
    }
}

/**
 * Get routes from routes table with station information
 */
function getRoutes() {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT id, route_code, route_name, distance_nm, flight_time_minutes, status, notes
            FROM routes
            WHERE status = 'active'
            ORDER BY route_code
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting routes: " . $e->getMessage());
        return [];
    }
}

/**
 * Log user activity
 * 
 * @param string $actionType - view, create, update, delete, login, logout, export, print
 * @param string $pagePath - Path to the page (e.g., 'admin/users/edit.php')
 * @param array $options - Additional options:
 *   - page_name: Display name of the page
 *   - section: Section name (e.g., 'User Form', 'Flight Table')
 *   - field_name: Field that was changed
 *   - old_value: Previous value
 *   - new_value: New value
 *   - record_id: ID of the record being modified
 *   - record_type: Type of record (e.g., 'user', 'flight', 'box')
 *   - changes: Array of changes [['field' => 'name', 'old' => 'John', 'new' => 'Jane']]
 */
function logActivity($actionType, $pagePath, $options = []) {
    try {
        // Get current user
        $user = getCurrentUser();
        if (!$user || !isset($user['id'])) {
            return false; // Don't log if user is not logged in
        }
        
        $db = getDBConnection();
        
        // Prepare data
        $pageName = $options['page_name'] ?? basename($pagePath);
        $section = $options['section'] ?? null;
        $fieldName = $options['field_name'] ?? null;
        $oldValue = $options['old_value'] ?? null;
        $newValue = $options['new_value'] ?? null;
        $recordId = $options['record_id'] ?? null;
        $recordType = $options['record_type'] ?? null;
        
        // Handle multiple changes
        $changesSummary = null;
        if (isset($options['changes']) && is_array($options['changes'])) {
            $changesSummary = json_encode($options['changes']);
            // If multiple changes, set field_name to null
            if (count($options['changes']) > 1) {
                $fieldName = null;
                $oldValue = null;
                $newValue = null;
            } else {
                // Single change
                $change = $options['changes'][0];
                $fieldName = $change['field'] ?? $fieldName;
                $oldValue = $change['old'] ?? $oldValue;
                $newValue = $change['new'] ?? $newValue;
            }
        }
        
        // Truncate long values
        if ($oldValue && strlen($oldValue) > 1000) {
            $oldValue = substr($oldValue, 0, 1000) . '...';
        }
        if ($newValue && strlen($newValue) > 1000) {
            $newValue = substr($newValue, 0, 1000) . '...';
        }
        
        // Get IP address and user agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Insert log
        $stmt = $db->prepare("INSERT INTO activity_logs 
            (user_id, action_type, page_path, page_name, section, field_name, old_value, new_value, 
             record_id, record_type, changes_summary, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $user['id'],
            $actionType,
            $pagePath,
            $pageName,
            $section,
            $fieldName,
            $oldValue,
            $newValue,
            $recordId,
            $recordType,
            $changesSummary,
            $ipAddress,
            $userAgent
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get the next Flight ID based on the highest existing Flight ID
 */
function getNextFlightID() {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT MAX(CAST(FlightID AS UNSIGNED)) as max_flight_id
            FROM flights
            WHERE FlightID REGEXP '^[0-9]+$'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $maxId = $result['max_flight_id'] ?? 0;
        return $maxId + 1;
    } catch (Exception $e) {
        error_log("Error getting next flight ID: " . $e->getMessage());
        return 1; // Default to 1 if error
    }
}

/**
 * Check for duplicate flight number on the same date
 */
function checkDuplicateFlightNumber($flightNo, $flightDate) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id, FlightNo, FltDate FROM flights WHERE FlightNo = ? AND FltDate = ?");
        $stmt->execute([$flightNo, $flightDate]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error checking duplicate flight number: " . $e->getMessage());
        return false;
    }
}

/**
 * Check for duplicate flight number on the same date (for edit - excluding current flight)
 */
function checkDuplicateFlightNumberForEdit($currentFlightId, $flightNo, $flightDate) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id, FlightNo, FltDate FROM flights WHERE FlightNo = ? AND FltDate = ? AND id != ?");
        $stmt->execute([$flightNo, $flightDate, $currentFlightId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error checking duplicate flight number for edit: " . $e->getMessage());
        return false;
    }
}

/**
 * Get flights assigned to a specific pilot
 */
function getPilotFlights($pilotId) {
    try {
        $db = getDBConnection();
        
        // Build WHERE clause for Crew1-Crew10
        $whereConditions = [];
        $params = [];
        for ($i = 1; $i <= 10; $i++) {
            $whereConditions[] = "Crew{$i} = ?";
            $params[] = $pilotId;
        }
        $whereClause = implode(' OR ', $whereConditions);
        
        $stmt = $db->prepare("
            SELECT * FROM flights 
            WHERE $whereClause
            ORDER BY TaskStart DESC, FltDate DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting pilot flights: " . $e->getMessage());
        return [];
    }
}

// ================ Notification Functions ================

/**
 * Ensure notifications tables exist
 */
function ensureNotificationsTablesExist() {
    try {
        $pdo = getDBConnection();
        
        // Create notifications table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notifications` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `title` varchar(255) NOT NULL,
              `message` text NOT NULL,
              `target_role` varchar(50) DEFAULT NULL COMMENT 'Specific role name from roles table',
              `target_user_id` int(11) DEFAULT NULL COMMENT 'Specific user ID (if targeting individual user)',
              `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
              `type` enum('info','warning','success','error') DEFAULT 'info',
              `is_active` tinyint(1) DEFAULT 1,
              `expires_at` datetime DEFAULT NULL COMMENT 'When notification expires',
              `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created the notification',
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_target_role` (`target_role`),
              KEY `idx_target_user_id` (`target_user_id`),
              KEY `idx_is_active` (`is_active`),
              KEY `idx_expires_at` (`expires_at`),
              KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System notifications for users'
        ");
        
        // Create user_notification_read table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_notification_read` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `notification_id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `read_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_notification_user` (`notification_id`, `user_id`),
              KEY `idx_user_id` (`user_id`),
              KEY `idx_notification_id` (`notification_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track which users have read which notifications'
        ");
        
        return true;
    } catch (PDOException $e) {
        error_log("Error creating notifications tables: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a new notification
 */
function createNotification($title, $message, $targetRole = null, $targetUserId = null, $priority = 'normal', $type = 'info', $expiresAt = null, $createdBy = null) {
    try {
        $pdo = getDBConnection();
        ensureNotificationsTablesExist();
        
        $sql = "INSERT INTO notifications (title, message, target_role, target_user_id, priority, type, expires_at, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $title,
            $message,
            $targetRole,
            $targetUserId,
            $priority,
            $type,
            $expiresAt,
            $createdBy
        ]);
        
        if ($result) {
            return ['success' => true, 'id' => $pdo->lastInsertId()];
        }
        
        return ['success' => false, 'message' => 'Failed to create notification'];
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get notifications for a user based on their role
 */
function getUserNotifications($userId, $userRole, $unreadOnly = false) {
    try {
        $pdo = getDBConnection();
        ensureNotificationsTablesExist();
        
        $now = date('Y-m-d H:i:s');
        
        // Base query
        $sql = "SELECT n.*, 
                       CASE WHEN unr.id IS NOT NULL THEN 1 ELSE 0 END as is_read,
                       unr.read_at
                FROM notifications n
                LEFT JOIN user_notification_read unr ON n.id = unr.notification_id AND unr.user_id = ?
                WHERE n.is_active = 1
                  AND (n.expires_at IS NULL OR n.expires_at > ?)
                  AND (
                      n.target_role = ? OR 
                      n.target_user_id = ? OR
                      (n.target_role IS NULL AND n.target_user_id IS NULL)
                  )";
        
        if ($unreadOnly) {
            $sql .= " AND unr.id IS NULL";
        }
        
        $sql .= " ORDER BY n.created_at DESC LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $now, $userRole, $userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read for a user
 */
function markNotificationAsRead($notificationId, $userId) {
    try {
        $pdo = getDBConnection();
        ensureNotificationsTablesExist();
        
        $sql = "INSERT INTO user_notification_read (notification_id, user_id) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$notificationId, $userId]);
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all notifications (admin view)
 */
function getAllNotifications($limit = 100, $includeExpired = true) {
    try {
        $pdo = getDBConnection();
        ensureNotificationsTablesExist();
        
        // Build query - start with base SELECT
        $sql = "SELECT n.*, 
                       u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM user_notification_read WHERE notification_id = n.id) as read_count
                FROM notifications n
                LEFT JOIN users u ON n.created_by = u.id";
        
        // Add WHERE clause if needed
        if (!$includeExpired) {
            $sql .= " WHERE n.is_active = 1 AND (n.expires_at IS NULL OR n.expires_at > NOW())";
        }
        
        // Add ORDER BY and LIMIT
        $sql .= " ORDER BY n.created_at DESC LIMIT " . intval($limit);
        
        // Execute query
        $stmt = $pdo->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error getting all notifications: " . $e->getMessage());
        error_log("SQL Query: " . ($sql ?? 'N/A'));
        return [];
    } catch (Exception $e) {
        error_log("Error getting all notifications (general): " . $e->getMessage());
        return [];
    }
}

/**
 * Delete notification
 */
function deleteNotification($notificationId) {
    try {
        $pdo = getDBConnection();
        ensureNotificationsTablesExist();
        
        $sql = "DELETE FROM notifications WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$notificationId]);
    } catch (PDOException $e) {
        error_log("Error deleting notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Update notification
 */
function updateNotification($notificationId, $title, $message, $targetRole, $targetUserId, $priority, $type, $isActive, $expiresAt) {
    try {
        $pdo = getDBConnection();
        ensureNotificationsTablesExist();
        
        $sql = "UPDATE notifications 
                SET title = ?, message = ?, target_role = ?, target_user_id = ?, 
                    priority = ?, type = ?, is_active = ?, expires_at = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $title, $message, $targetRole, $targetUserId,
            $priority, $type, $isActive, $expiresAt, $notificationId
        ]);
    } catch (PDOException $e) {
        error_log("Error updating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get users who have read a notification
 */
function getNotificationReaders($notificationId) {
    try {
        $pdo = getDBConnection();
        ensureNotificationsTablesExist();
        
        $sql = "SELECT unr.*, u.first_name, u.last_name, u.position, u.email, u.role
                FROM user_notification_read unr
                JOIN users u ON unr.user_id = u.id
                WHERE unr.notification_id = ?
                ORDER BY unr.read_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$notificationId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting notification readers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get notification statistics (readers count, target users count)
 */
function getNotificationStats($notificationId) {
    try {
        $pdo = getDBConnection();
        ensureNotificationsTablesExist();
        
        // Get notification
        $sql = "SELECT * FROM notifications WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$notificationId]);
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$notification) {
            return null;
        }
        
        // Count readers
        $sql = "SELECT COUNT(*) as read_count FROM user_notification_read WHERE notification_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$notificationId]);
        $readCount = $stmt->fetch(PDO::FETCH_ASSOC)['read_count'];
        
        // Count target users (users who should see this notification)
        $targetUserCount = 0;
        if ($notification['target_user_id']) {
            $targetUserCount = 1;
        } elseif ($notification['target_role']) {
            $sql = "SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$notification['target_role']]);
            $targetUserCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } else {
            // All users
            $sql = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
            $stmt = $pdo->query($sql);
            $targetUserCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        }
        
        return [
            'read_count' => (int)$readCount,
            'target_count' => (int)$targetUserCount,
            'unread_count' => max(0, (int)$targetUserCount - (int)$readCount)
        ];
    } catch (PDOException $e) {
        error_log("Error getting notification stats: " . $e->getMessage());
        return null;
    }
}

// ================ Message Functions ================

/**
 * Ensure messages tables exist
 */
function ensureMessagesTablesExist() {
    try {
        $pdo = getDBConnection();
        
        // Check if messages table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            // Check existing columns
            $stmt = $pdo->query("SHOW COLUMNS FROM messages");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // If recipient_id exists but receiver_id doesn't, rename the column
            if (in_array('recipient_id', $columns) && !in_array('receiver_id', $columns)) {
                $pdo->exec("ALTER TABLE messages CHANGE COLUMN recipient_id receiver_id int(11) NOT NULL COMMENT 'User ID who receives the message'");
            }
            
            // If parent_message_id exists but parent_id doesn't, rename the column
            if (in_array('parent_message_id', $columns) && !in_array('parent_id', $columns)) {
                $pdo->exec("ALTER TABLE messages CHANGE COLUMN parent_message_id parent_id int(11) DEFAULT NULL COMMENT 'Parent message ID for replies'");
            }
            
            // If is_deleted_by_sender exists but sender_deleted doesn't, rename the column
            if (in_array('is_deleted_by_sender', $columns) && !in_array('sender_deleted', $columns)) {
                $pdo->exec("ALTER TABLE messages CHANGE COLUMN is_deleted_by_sender sender_deleted tinyint(1) DEFAULT 0 COMMENT 'Deleted by sender'");
            }
            
            // If is_deleted_by_recipient exists but receiver_deleted doesn't, rename the column
            if (in_array('is_deleted_by_recipient', $columns) && !in_array('receiver_deleted', $columns)) {
                $pdo->exec("ALTER TABLE messages CHANGE COLUMN is_deleted_by_recipient receiver_deleted tinyint(1) DEFAULT 0 COMMENT 'Deleted by receiver'");
            }
            
            // Add missing columns if they don't exist
            if (!in_array('receiver_id', $columns)) {
                $pdo->exec("ALTER TABLE messages ADD COLUMN receiver_id int(11) NOT NULL COMMENT 'User ID who receives the message' AFTER sender_id");
            }
            if (!in_array('parent_id', $columns)) {
                $pdo->exec("ALTER TABLE messages ADD COLUMN parent_id int(11) DEFAULT NULL COMMENT 'Parent message ID for replies' AFTER message");
            }
            if (!in_array('sender_deleted', $columns)) {
                $pdo->exec("ALTER TABLE messages ADD COLUMN sender_deleted tinyint(1) DEFAULT 0 COMMENT 'Deleted by sender' AFTER read_at");
            }
            if (!in_array('receiver_deleted', $columns)) {
                $pdo->exec("ALTER TABLE messages ADD COLUMN receiver_deleted tinyint(1) DEFAULT 0 COMMENT 'Deleted by receiver' AFTER sender_deleted");
            }
        } else {
            // Create messages table if it doesn't exist
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `messages` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `sender_id` int(11) NOT NULL COMMENT 'User ID who sent the message',
                  `receiver_id` int(11) NOT NULL COMMENT 'User ID who receives the message',
                  `subject` varchar(255) DEFAULT NULL COMMENT 'Message subject',
                  `message` text NOT NULL COMMENT 'Message content',
                  `parent_id` int(11) DEFAULT NULL COMMENT 'Parent message ID for replies',
                  `is_read` tinyint(1) DEFAULT 0 COMMENT 'Whether receiver has read the message',
                  `read_at` datetime DEFAULT NULL COMMENT 'When message was read',
                  `sender_deleted` tinyint(1) DEFAULT 0 COMMENT 'Deleted by sender',
                  `receiver_deleted` tinyint(1) DEFAULT 0 COMMENT 'Deleted by receiver',
                  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_sender_id` (`sender_id`),
                  KEY `idx_receiver_id` (`receiver_id`),
                  KEY `idx_parent_id` (`parent_id`),
                  KEY `idx_is_read` (`is_read`),
                  KEY `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User messages system'
            ");
        }
        
        // Create message_attachments table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `message_attachments` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `message_id` int(11) NOT NULL,
              `file_name` varchar(255) NOT NULL COMMENT 'Original file name',
              `file_path` varchar(500) NOT NULL COMMENT 'Path to stored file',
              `file_type` varchar(100) DEFAULT NULL COMMENT 'MIME type',
              `file_size` int(11) DEFAULT NULL COMMENT 'File size in bytes',
              `file_category` enum('image','document','other') DEFAULT 'other' COMMENT 'File category',
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_message_id` (`message_id`),
              KEY `idx_file_category` (`file_category`),
              FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Message attachments (images, documents)'
        ");
        
        return true;
    } catch (PDOException $e) {
        error_log("Error creating messages tables: " . $e->getMessage());
        return false;
    }
}

// ================ Trip Management Functions ================

/**
 * Ensure trip_driver_assignments table exists
 */
function ensureTripDriverAssignmentsTableExists() {
    try {
        $pdo = getDBConnection();
        
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'trip_driver_assignments'");
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            // Create trip_driver_assignments table without foreign keys first
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `trip_driver_assignments` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `flight_id` int(11) NOT NULL COMMENT 'Flight ID from flights table',
                  `crew_user_id` int(11) NOT NULL COMMENT 'Crew member user ID (from Crew1-Crew10)',
                  `crew_position` varchar(10) DEFAULT NULL COMMENT 'Crew position (Crew1, Crew2, etc.)',
                  `driver_id` int(11) NOT NULL COMMENT 'Driver user ID (role_id = 18)',
                  `assignment_date` date NOT NULL COMMENT 'Date of assignment',
                  `pickup_time` time DEFAULT NULL COMMENT 'Scheduled pickup time',
                  `dropoff_time` time DEFAULT NULL COMMENT 'Scheduled dropoff time',
                  `pickup_location` varchar(255) DEFAULT NULL COMMENT 'Pickup location',
                  `dropoff_location` varchar(255) DEFAULT NULL COMMENT 'Dropoff location',
                  `notes` text DEFAULT NULL COMMENT 'Additional notes',
                  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created the assignment',
                  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_flight_id` (`flight_id`),
                  KEY `idx_crew_user_id` (`crew_user_id`),
                  KEY `idx_driver_id` (`driver_id`),
                  KEY `idx_assignment_date` (`assignment_date`),
                  UNIQUE KEY `unique_flight_crew_driver` (`flight_id`, `crew_user_id`, `driver_id`, `assignment_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Driver assignments for crew members on flights'
            ");
            
            // Try to add foreign keys (ignore errors if they fail)
            try {
                $pdo->exec("ALTER TABLE `trip_driver_assignments` 
                    ADD CONSTRAINT `fk_trip_flight_id` FOREIGN KEY (`flight_id`) REFERENCES `flights`(`FlightID`) ON DELETE CASCADE");
            } catch (PDOException $e) {
                error_log("Warning: Could not add foreign key for flight_id: " . $e->getMessage());
            }
            
            try {
                $pdo->exec("ALTER TABLE `trip_driver_assignments` 
                    ADD CONSTRAINT `fk_trip_crew_user_id` FOREIGN KEY (`crew_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE");
            } catch (PDOException $e) {
                error_log("Warning: Could not add foreign key for crew_user_id: " . $e->getMessage());
            }
            
            try {
                $pdo->exec("ALTER TABLE `trip_driver_assignments` 
                    ADD CONSTRAINT `fk_trip_driver_id` FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE");
            } catch (PDOException $e) {
                error_log("Warning: Could not add foreign key for driver_id: " . $e->getMessage());
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error creating trip_driver_assignments table: " . $e->getMessage());
        return false;
    }
}

/**
 * Get users with Transport role (role_id = 18)
 */
function getTransportUsers() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.position, u.mobile, u.phone, u.email, u.picture,
                   r.name as role_name, r.display_name as role_display_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.role_id = 18 AND u.status = 'active'
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add role field for backward compatibility
        foreach ($users as &$user) {
            $user['role'] = $user['role_name'] ?? 'transport';
        }
        
        return $users;
    } catch (PDOException $e) {
        error_log("Error getting transport users: " . $e->getMessage());
        return [];
    }
}

/**
 * Get flights with crew information for a specific date
 */
function getFlightsWithCrewByDate($date) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                f.FlightID,
                f.FlightNo,
                f.TaskName,
                f.Route,
                f.FltDate,
                f.TaskStart,
                f.TaskEnd,
                f.Rego,
                f.ACType,
                f.Crew1, f.Crew2, f.Crew3, f.Crew4, f.Crew5,
                f.Crew6, f.Crew7, f.Crew8, f.Crew9, f.Crew10,
                f.Crew1_role, f.Crew2_role, f.Crew3_role, f.Crew4_role, f.Crew5_role,
                f.Crew6_role, f.Crew7_role, f.Crew8_role, f.Crew9_role, f.Crew10_role
            FROM flights f
            WHERE DATE(f.FltDate) = ?
            ORDER BY f.TaskStart ASC, f.FlightNo ASC
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting flights with crew by date: " . $e->getMessage());
        return [];
    }
}

/**
 * Get crew members for a flight (from Crew1-Crew10)
 */
function getFlightCrewMembers($flightId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                f.Crew1, f.Crew2, f.Crew3, f.Crew4, f.Crew5,
                f.Crew6, f.Crew7, f.Crew8, f.Crew9, f.Crew10,
                f.Crew1_role, f.Crew2_role, f.Crew3_role, f.Crew4_role, f.Crew5_role,
                f.Crew6_role, f.Crew7_role, f.Crew8_role, f.Crew9_role, f.Crew10_role
            FROM flights f
            WHERE f.FlightID = ?
        ");
        $stmt->execute([$flightId]);
        $flight = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$flight) {
            return [];
        }
        
        $crewMembers = [];
        for ($i = 1; $i <= 10; $i++) {
            $crewField = "Crew{$i}";
            $roleField = "Crew{$i}_role";
            if (!empty($flight[$crewField])) {
                $userId = $flight[$crewField];
                $user = getUserById($userId);
                if ($user) {
                    // Combine address_line_1 and address_line_2
                    $address = trim(($user['address_line_1'] ?? '') . ' ' . ($user['address_line_2'] ?? ''));
                    $address = trim($address);
                    
                    $crewMembers[] = [
                        'id' => $userId,
                        'crew_position' => "Crew{$i}",
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'position' => $user['position'],
                        'role' => $flight[$roleField] ?? '',
                        'picture' => $user['picture'] ?? null,
                        'mobile' => $user['mobile'] ?? null,
                        'phone' => $user['phone'] ?? null,
                        'address' => $address ?: null,
                    ];
                }
            }
        }
        
        return $crewMembers;
    } catch (PDOException $e) {
        error_log("Error getting flight crew members: " . $e->getMessage());
        return [];
    }
}

/**
 * Assign driver to crew member
 */
function assignDriverToCrew($flightId, $crewUserId, $crewPosition, $driverId, $assignmentDate, $pickupTime = null, $dropoffTime = null, $pickupLocation = null, $dropoffLocation = null, $notes = null, $createdBy = null) {
    try {
        $pdo = getDBConnection();
        ensureTripDriverAssignmentsTableExists();
        
        // Validate inputs
        if (empty($flightId) || empty($crewUserId) || empty($driverId) || empty($assignmentDate)) {
            error_log("Error assigning driver: Missing required fields. flightId: $flightId, crewUserId: $crewUserId, driverId: $driverId, assignmentDate: $assignmentDate");
            return false;
        }
        
        // Verify flight exists
        $stmt = $pdo->prepare("SELECT FlightID FROM flights WHERE FlightID = ?");
        $stmt->execute([$flightId]);
        if ($stmt->rowCount() == 0) {
            error_log("Error assigning driver: Flight ID $flightId does not exist");
            return false;
        }
        
        // Verify crew user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$crewUserId]);
        if ($stmt->rowCount() == 0) {
            error_log("Error assigning driver: Crew user ID $crewUserId does not exist");
            return false;
        }
        
        // Verify driver exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role_id = 18");
        $stmt->execute([$driverId]);
        if ($stmt->rowCount() == 0) {
            error_log("Error assigning driver: Driver ID $driverId does not exist or is not a transport user");
            return false;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO trip_driver_assignments 
            (flight_id, crew_user_id, crew_position, driver_id, assignment_date, pickup_time, dropoff_time, pickup_location, dropoff_location, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                pickup_time = VALUES(pickup_time),
                dropoff_time = VALUES(dropoff_time),
                pickup_location = VALUES(pickup_location),
                dropoff_location = VALUES(dropoff_location),
                notes = VALUES(notes),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $result = $stmt->execute([
            $flightId, $crewUserId, $crewPosition, $driverId, $assignmentDate,
            $pickupTime, $dropoffTime, $pickupLocation, $dropoffLocation, $notes, $createdBy
        ]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("Error assigning driver to crew: " . print_r($errorInfo, true));
            return false;
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error assigning driver to crew: " . $e->getMessage() . " | SQL State: " . $e->getCode());
        return false;
    }
}

/**
 * Unassign driver from crew member
 */
function unassignDriverFromCrew($flightId, $crewUserId, $driverId, $assignmentDate) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            DELETE FROM trip_driver_assignments
            WHERE flight_id = ? AND crew_user_id = ? AND driver_id = ? AND assignment_date = ?
        ");
        return $stmt->execute([$flightId, $crewUserId, $driverId, $assignmentDate]);
    } catch (PDOException $e) {
        error_log("Error unassigning driver from crew: " . $e->getMessage());
        return false;
    }
}

/**
 * Get driver assignments for a flight
 */
function getDriverAssignmentsForFlight($flightId, $assignmentDate = null) {
    try {
        $pdo = getDBConnection();
        ensureTripDriverAssignmentsTableExists();
        
        if ($assignmentDate) {
            $stmt = $pdo->prepare("
                SELECT tda.*, 
                       u.first_name as driver_first_name, u.last_name as driver_last_name,
                       u.mobile as driver_mobile, u.phone as driver_phone, u.picture as driver_picture,
                       crew.first_name as crew_first_name, crew.last_name as crew_last_name
                FROM trip_driver_assignments tda
                LEFT JOIN users u ON tda.driver_id = u.id
                LEFT JOIN users crew ON tda.crew_user_id = crew.id
                WHERE tda.flight_id = ? AND tda.assignment_date = ?
            ");
            $stmt->execute([$flightId, $assignmentDate]);
        } else {
            $stmt = $pdo->prepare("
                SELECT tda.*, 
                       u.first_name as driver_first_name, u.last_name as driver_last_name,
                       u.mobile as driver_mobile, u.phone as driver_phone, u.picture as driver_picture,
                       crew.first_name as crew_first_name, crew.last_name as crew_last_name
                FROM trip_driver_assignments tda
                LEFT JOIN users u ON tda.driver_id = u.id
                LEFT JOIN users crew ON tda.crew_user_id = crew.id
                WHERE tda.flight_id = ?
            ");
            $stmt->execute([$flightId]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting driver assignments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get driver assignments for a date
 */
function getDriverAssignmentsByDate($date) {
    try {
        $pdo = getDBConnection();
        ensureTripDriverAssignmentsTableExists();
        
        $stmt = $pdo->prepare("
            SELECT tda.*, 
                   u.first_name as driver_first_name, u.last_name as driver_last_name,
                   u.mobile as driver_mobile, u.phone as driver_phone,
                   crew.first_name as crew_first_name, crew.last_name as crew_last_name,
                   f.FlightNo, f.TaskName, f.Route, f.TaskStart, f.TaskEnd
            FROM trip_driver_assignments tda
            LEFT JOIN users u ON tda.driver_id = u.id
            LEFT JOIN users crew ON tda.crew_user_id = crew.id
            LEFT JOIN flights f ON tda.flight_id = f.FlightID
            WHERE tda.assignment_date = ?
            ORDER BY tda.pickup_time ASC, f.TaskStart ASC
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting driver assignments by date: " . $e->getMessage());
        return [];
    }
}

/**
 * Get driver assignments for a specific driver (user)
 */
function getDriverAssignmentsByDriverId($driverId, $date = null) {
    try {
        $pdo = getDBConnection();
        ensureTripDriverAssignmentsTableExists();
        
        if ($date) {
            $stmt = $pdo->prepare("
                SELECT tda.*, 
                       crew.first_name as crew_first_name, crew.last_name as crew_last_name,
                       crew.mobile as crew_mobile, crew.phone as crew_phone,
                       crew.position as crew_position,
                       crew.address_line_1 as crew_address_line_1,
                       crew.address_line_2 as crew_address_line_2,
                       f.FlightID, f.FlightNo, f.TaskName, f.Route, f.FltDate, 
                       f.TaskStart, f.TaskEnd, f.Rego, f.ACType
                FROM trip_driver_assignments tda
                LEFT JOIN users crew ON tda.crew_user_id = crew.id
                LEFT JOIN flights f ON tda.flight_id = f.FlightID
                WHERE tda.driver_id = ? AND tda.assignment_date = ?
                ORDER BY tda.assignment_date ASC, tda.pickup_time ASC, f.TaskStart ASC
            ");
            $stmt->execute([$driverId, $date]);
        } else {
            $stmt = $pdo->prepare("
                SELECT tda.*, 
                       crew.first_name as crew_first_name, crew.last_name as crew_last_name,
                       crew.mobile as crew_mobile, crew.phone as crew_phone,
                       crew.position as crew_position,
                       crew.address_line_1 as crew_address_line_1,
                       crew.address_line_2 as crew_address_line_2,
                       f.FlightID, f.FlightNo, f.TaskName, f.Route, f.FltDate, 
                       f.TaskStart, f.TaskEnd, f.Rego, f.ACType
                FROM trip_driver_assignments tda
                LEFT JOIN users crew ON tda.crew_user_id = crew.id
                LEFT JOIN flights f ON tda.flight_id = f.FlightID
                WHERE tda.driver_id = ?
                ORDER BY tda.assignment_date ASC, tda.pickup_time ASC, f.TaskStart ASC
            ");
            $stmt->execute([$driverId]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting driver assignments by driver ID: " . $e->getMessage());
        return [];
    }
}

/**
 * Send a message
 */
function sendMessage($senderId, $receiverId, $subject, $message, $parentId = null) {
    try {
        $pdo = getDBConnection();
        ensureMessagesTablesExist();
        
        $sql = "INSERT INTO messages (sender_id, receiver_id, subject, message, parent_id) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $senderId,
            $receiverId,
            $subject,
            $message,
            $parentId
        ]);
        
        if ($result) {
            return ['success' => true, 'id' => $pdo->lastInsertId()];
        }
        
        return ['success' => false, 'message' => 'Failed to send message'];
    } catch (PDOException $e) {
        error_log("Error sending message: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Add attachment to message
 */
function addMessageAttachment($messageId, $fileName, $filePath, $fileType, $fileSize, $fileCategory = 'other') {
    try {
        $pdo = getDBConnection();
        ensureMessagesTablesExist();
        
        $sql = "INSERT INTO message_attachments (message_id, file_name, file_path, file_type, file_size, file_category) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $messageId,
            $fileName,
            $filePath,
            $fileType,
            $fileSize,
            $fileCategory
        ]);
    } catch (PDOException $e) {
        error_log("Error adding message attachment: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's inbox messages
 */
function getInboxMessages($userId, $limit = 50) {
    try {
        $pdo = getDBConnection();
        ensureMessagesTablesExist();
        
        $sql = "SELECT m.*, 
                       s.first_name as sender_first_name, s.last_name as sender_last_name, s.position as sender_position, s.picture as sender_picture,
                       r.first_name as receiver_first_name, r.last_name as receiver_last_name, r.picture as receiver_picture,
                       (SELECT COUNT(*) FROM message_attachments WHERE message_id = m.id) as attachment_count
                FROM messages m
                JOIN users s ON m.sender_id = s.id
                JOIN users r ON m.receiver_id = r.id
                WHERE m.receiver_id = ? AND m.receiver_deleted = 0
                ORDER BY m.created_at DESC
                LIMIT " . intval($limit);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attachments for each message
        foreach ($messages as &$message) {
            $message['attachments'] = getMessageAttachments($message['id']);
        }
        
        return $messages;
    } catch (PDOException $e) {
        error_log("Error getting inbox messages: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's sent messages
 */
function getSentMessages($userId, $limit = 50) {
    try {
        $pdo = getDBConnection();
        ensureMessagesTablesExist();
        
        $sql = "SELECT m.*, 
                       s.first_name as sender_first_name, s.last_name as sender_last_name, s.picture as sender_picture,
                       r.first_name as receiver_first_name, r.last_name as receiver_last_name, r.position as receiver_position, r.picture as receiver_picture,
                       (SELECT COUNT(*) FROM message_attachments WHERE message_id = m.id) as attachment_count
                FROM messages m
                JOIN users s ON m.sender_id = s.id
                JOIN users r ON m.receiver_id = r.id
                WHERE m.sender_id = ? AND m.sender_deleted = 0
                ORDER BY m.created_at DESC
                LIMIT " . intval($limit);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attachments for each message
        foreach ($messages as &$message) {
            $message['attachments'] = getMessageAttachments($message['id']);
        }
        
        return $messages;
    } catch (PDOException $e) {
        error_log("Error getting sent messages: " . $e->getMessage());
        return [];
    }
}

/**
 * Get message by ID
 */
function getMessageById($messageId, $userId) {
    try {
        $pdo = getDBConnection();
        ensureMessagesTablesExist();
        
        $sql = "SELECT m.*, 
                       s.first_name as sender_first_name, s.last_name as sender_last_name, s.position as sender_position, s.email as sender_email,
                       r.first_name as receiver_first_name, r.last_name as receiver_last_name, r.position as receiver_position, r.email as receiver_email
                FROM messages m
                JOIN users s ON m.sender_id = s.id
                JOIN users r ON m.receiver_id = r.id
                WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$messageId, $userId, $userId]);
        
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($message) {
            $message['attachments'] = getMessageAttachments($messageId);
            
            // Get thread (parent and replies)
            if ($message['parent_id']) {
                $message['parent'] = getMessageById($message['parent_id'], $userId);
            }
            
            // Get replies
            $sql = "SELECT m.*, 
                           s.first_name as sender_first_name, s.last_name as sender_last_name,
                           r.first_name as receiver_first_name, r.last_name as receiver_last_name
                    FROM messages m
                    JOIN users s ON m.sender_id = s.id
                    JOIN users r ON m.receiver_id = r.id
                    WHERE m.parent_id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
                    ORDER BY m.created_at ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$messageId, $userId, $userId]);
            $message['replies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($message['replies'] as &$reply) {
                $reply['attachments'] = getMessageAttachments($reply['id']);
            }
        }
        
        return $message;
    } catch (PDOException $e) {
        error_log("Error getting message by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Get message attachments
 */
function getMessageAttachments($messageId) {
    try {
        $pdo = getDBConnection();
        ensureMessagesTablesExist();
        
        $sql = "SELECT * FROM message_attachments WHERE message_id = ? ORDER BY created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$messageId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting message attachments: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark message as read
 */
function markMessageAsRead($messageId, $userId) {
    try {
        $pdo = getDBConnection();
        ensureMessagesTablesExist();
        
        $sql = "UPDATE messages 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ? AND receiver_id = ? AND is_read = 0";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$messageId, $userId]);
    } catch (PDOException $e) {
        error_log("Error marking message as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete message (soft delete)
 */
function deleteMessage($messageId, $userId) {
    try {
        $pdo = getDBConnection();
        ensureMessagesTablesExist();
        
        // Check if user is sender or receiver
        $sql = "SELECT sender_id, receiver_id FROM messages WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$messageId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$message) {
            return false;
        }
        
        if ($message['sender_id'] == $userId) {
            $sql = "UPDATE messages SET sender_deleted = 1 WHERE id = ?";
        } elseif ($message['receiver_id'] == $userId) {
            $sql = "UPDATE messages SET receiver_deleted = 1 WHERE id = ?";
        } else {
            return false;
        }
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$messageId]);
    } catch (PDOException $e) {
        error_log("Error deleting message: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread message count for user
 */
function getUnreadMessageCount($userId) {
    try {
        $pdo = getDBConnection();
        ensureMessagesTablesExist();
        
        $sql = "SELECT COUNT(*) as count 
                FROM messages 
                WHERE receiver_id = ? AND is_read = 0 AND receiver_deleted = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error getting unread message count: " . $e->getMessage());
        return 0;
    }
}


?>
