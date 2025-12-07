<?php
/**
 * Flights API
 * Returns flight information from the flights table
 * 
 * Authentication: Token-based
 * Token: 69040872-3ba8-4681-a9ec-273c832f3ca0
 * 
 * FILTERS (فیلترها):
 * 
 * === DATE FILTERS (فیلترهای تاریخ) ===
 * - date: تاریخ دقیق (YYYY-MM-DD) - مثال: 2025-07-21
 * - date_exact: همان date (تاریخ دقیق)
 * - date_from: از تاریخ (YYYY-MM-DD) - مثال: 2025-07-01
 * - date_to: تا تاریخ (YYYY-MM-DD) - مثال: 2025-07-31
 * 
 * === REGO FILTERS (فیلترهای Rego) ===
 * - rego: جستجوی جزئی (LIKE) - مثال: EP-NEB یا NEB
 * - rego_exact: جستجوی دقیق (=) - مثال: EP-NEB
 * - rego_list: لیست چند Rego (comma-separated) - مثال: EP-NEB,EP-ABC,EP-XYZ
 * 
 * === ROUTE FILTERS (فیلترهای Route) ===
 * - route: جستجوی جزئی (LIKE) - مثال: RAS-THR یا RAS
 * - route_exact: جستجوی دقیق (=) - مثال: RAS-THR
 * - route_list: لیست چند Route (comma-separated) - مثال: RAS-THR,THR-AZD
 * - route_from: مبدا (اولین بخش Route) - مثال: RAS
 * - route_to: مقصد (دومین بخش Route) - مثال: THR
 * 
 * === OTHER FILTERS ===
 * - flight_id: FlightID دقیق
 * - flight_no: جستجوی جزئی FlightNo
 * 
 * === PAGINATION ===
 * - limit: تعداد رکوردها (پیش‌فرض: 100، حداکثر: 1000)
 * - offset: برای pagination (پیش‌فرض: 0)
 * 
 * EXAMPLES:
 * - ?token=XXX&date=2025-07-21
 * - ?token=XXX&rego_exact=EP-NEB&date_from=2025-07-01&date_to=2025-07-31
 * - ?token=XXX&route_exact=RAS-THR&rego_list=EP-NEB,EP-ABC
 * - ?token=XXX&route_from=RAS&route_to=THR&limit=50
 */

// Include database configuration
require_once __DIR__ . '/../config.php';

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// CORS headers (if needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, token');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Define API token
define('API_TOKEN', '69040872-3ba8-4681-a9ec-273c832f3ca0');

// Get token from request (check header, GET, or POST)
$token = null;
if (isset($_SERVER['HTTP_TOKEN'])) {
    $token = $_SERVER['HTTP_TOKEN'];
} elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
} elseif (isset($_POST['token'])) {
    $token = $_POST['token'];
}

// Validate token
if (empty($token) || $token !== API_TOKEN) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Invalid or missing token'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Get database connection
    $db = getDBConnection();
    
    // Get query parameters
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $limit = max(1, min(1000, $limit)); // Limit between 1 and 1000
    $offset = max(0, $offset);
    
    // ==================== FILTER PARAMETERS ====================
    
    // Date Filters (تاریخ)
    $date = isset($_GET['date']) ? trim($_GET['date']) : null; // تاریخ دقیق (YYYY-MM-DD)
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null; // از تاریخ
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null; // تا تاریخ
    $dateExact = isset($_GET['date_exact']) ? trim($_GET['date_exact']) : null; // تاریخ دقیق (مشابه date)
    
    // Rego Filters
    $rego = isset($_GET['rego']) ? trim($_GET['rego']) : null; // جستجوی جزئی (LIKE)
    $regoExact = isset($_GET['rego_exact']) ? trim($_GET['rego_exact']) : null; // جستجوی دقیق (=)
    $regoList = isset($_GET['rego_list']) ? trim($_GET['rego_list']) : null; // لیست چند Rego (comma-separated)
    
    // Route Filters
    $route = isset($_GET['route']) ? trim($_GET['route']) : null; // جستجوی جزئی (LIKE)
    $routeExact = isset($_GET['route_exact']) ? trim($_GET['route_exact']) : null; // جستجوی دقیق (=)
    $routeList = isset($_GET['route_list']) ? trim($_GET['route_list']) : null; // لیست چند Route (comma-separated)
    $routeFrom = isset($_GET['route_from']) ? trim($_GET['route_from']) : null; // مبدا (اولین بخش Route)
    $routeTo = isset($_GET['route_to']) ? trim($_GET['route_to']) : null; // مقصد (دومین بخش Route)
    
    // Other optional filters
    $flightId = isset($_GET['flight_id']) ? trim($_GET['flight_id']) : null;
    $flightNo = isset($_GET['flight_no']) ? trim($_GET['flight_no']) : null;
    
    // ==================== BUILD WHERE CLAUSE ====================
    $whereConditions = [];
    $params = [];
    
    // Date Filters
    if ($date !== null && $date !== '') {
        // تاریخ دقیق
        $whereConditions[] = "DATE(FltDate) = ?";
        $params[] = $date;
    } elseif ($dateExact !== null && $dateExact !== '') {
        // تاریخ دقیق (مشابه date)
        $whereConditions[] = "DATE(FltDate) = ?";
        $params[] = $dateExact;
    } else {
        // بازه تاریخ
        if ($dateFrom !== null && $dateFrom !== '') {
            $whereConditions[] = "DATE(FltDate) >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo !== null && $dateTo !== '') {
            $whereConditions[] = "DATE(FltDate) <= ?";
            $params[] = $dateTo;
        }
    }
    
    // Rego Filters
    if ($regoExact !== null && $regoExact !== '') {
        // جستجوی دقیق Rego
        $whereConditions[] = "Rego = ?";
        $params[] = $regoExact;
    } elseif ($regoList !== null && $regoList !== '') {
        // لیست چند Rego
        $regoArray = array_map('trim', explode(',', $regoList));
        $regoArray = array_filter($regoArray); // حذف مقادیر خالی
        if (!empty($regoArray)) {
            $placeholders = str_repeat('?,', count($regoArray) - 1) . '?';
            $whereConditions[] = "Rego IN ($placeholders)";
            $params = array_merge($params, $regoArray);
        }
    } elseif ($rego !== null && $rego !== '') {
        // جستجوی جزئی Rego
        $whereConditions[] = "Rego LIKE ?";
        $params[] = "%$rego%";
    }
    
    // Route Filters
    if ($routeExact !== null && $routeExact !== '') {
        // جستجوی دقیق Route
        $whereConditions[] = "Route = ?";
        $params[] = $routeExact;
    } elseif ($routeList !== null && $routeList !== '') {
        // لیست چند Route
        $routeArray = array_map('trim', explode(',', $routeList));
        $routeArray = array_filter($routeArray); // حذف مقادیر خالی
        if (!empty($routeArray)) {
            $placeholders = str_repeat('?,', count($routeArray) - 1) . '?';
            $whereConditions[] = "Route IN ($placeholders)";
            $params = array_merge($params, $routeArray);
        }
    } elseif ($routeFrom !== null && $routeFrom !== '' || $routeTo !== null && $routeTo !== '') {
        // فیلتر بر اساس مبدا و/یا مقصد (Route format: "FROM-TO" مثل "RAS-THR")
        if ($routeFrom !== null && $routeFrom !== '' && $routeTo !== null && $routeTo !== '') {
            // هر دو مبدا و مقصد مشخص شده - Route باید دقیقاً "FROM-TO" باشد
            $whereConditions[] = "Route = ?";
            $params[] = "$routeFrom-$routeTo";
        } elseif ($routeFrom !== null && $routeFrom !== '') {
            // فقط مبدا - Route باید با "FROM-" شروع شود
            $whereConditions[] = "Route LIKE ?";
            $params[] = "$routeFrom-%";
        } elseif ($routeTo !== null && $routeTo !== '') {
            // فقط مقصد - Route باید با "-TO" تمام شود
            $whereConditions[] = "Route LIKE ?";
            $params[] = "%-$routeTo";
        }
    } elseif ($route !== null && $route !== '') {
        // جستجوی جزئی Route
        $whereConditions[] = "Route LIKE ?";
        $params[] = "%$route%";
    }
    
    // Other filters
    if ($flightId !== null && $flightId !== '') {
        $whereConditions[] = "FlightID = ?";
        $params[] = $flightId;
    }
    
    if ($flightNo !== null && $flightNo !== '') {
        $whereConditions[] = "FlightNo LIKE ?";
        $params[] = "%$flightNo%";
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM flights $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get flights data
    // Note: LIMIT and OFFSET must be integers, not placeholders
    $sql = "SELECT * FROM flights $whereClause ORDER BY FltDate DESC, id DESC LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    $stmt = $db->prepare($sql);
    
    $stmt->execute($params);
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build applied filters info
    $appliedFilters = [];
    if ($date !== null && $date !== '') $appliedFilters['date'] = $date;
    if ($dateExact !== null && $dateExact !== '') $appliedFilters['date_exact'] = $dateExact;
    if ($dateFrom !== null && $dateFrom !== '') $appliedFilters['date_from'] = $dateFrom;
    if ($dateTo !== null && $dateTo !== '') $appliedFilters['date_to'] = $dateTo;
    if ($rego !== null && $rego !== '') $appliedFilters['rego'] = $rego;
    if ($regoExact !== null && $regoExact !== '') $appliedFilters['rego_exact'] = $regoExact;
    if ($regoList !== null && $regoList !== '') $appliedFilters['rego_list'] = $regoList;
    if ($route !== null && $route !== '') $appliedFilters['route'] = $route;
    if ($routeExact !== null && $routeExact !== '') $appliedFilters['route_exact'] = $routeExact;
    if ($routeList !== null && $routeList !== '') $appliedFilters['route_list'] = $routeList;
    if ($routeFrom !== null && $routeFrom !== '') $appliedFilters['route_from'] = $routeFrom;
    if ($routeTo !== null && $routeTo !== '') $appliedFilters['route_to'] = $routeTo;
    if ($flightId !== null && $flightId !== '') $appliedFilters['flight_id'] = $flightId;
    if ($flightNo !== null && $flightNo !== '') $appliedFilters['flight_no'] = $flightNo;
    
    // Format response
    $response = [
        'success' => true,
        'data' => $flights,
        'pagination' => [
            'total' => intval($totalCount),
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ],
        'filters_applied' => $appliedFilters,
        'count' => count($flights)
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log("Flights API Error: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log("Flights API Error: " . $e->getMessage());
}
