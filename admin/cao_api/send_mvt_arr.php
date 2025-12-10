<?php
/**
 * CAO API - Send MVT ARR (Arrival) Message
 * 
 * این اسکریپت پیام MVT-ARR را از داده‌های جدول flights می‌سازد
 * و به API CAO ارسال می‌کند.
 */

require_once '../../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get Flight ID from request
$flightId = $_GET['flight_id'] ?? $_POST['flight_id'] ?? null;

if (!$flightId) {
    http_response_code(400);
    echo json_encode(['error' => 'Flight ID is required']);
    exit();
}

try {
    $db = getDBConnection();
    
    // Get flight data
    $stmt = $db->prepare("
        SELECT 
            FlightID,
            TaskName,
            FlightNo,
            FltDate,
            Rego,
            Route,
            actual_on_utc,
            actual_in_utc,
            remark_1
        FROM flights 
        WHERE FlightID = ?
    ");
    $stmt->execute([$flightId]);
    $flight = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$flight) {
        http_response_code(404);
        echo json_encode(['error' => 'Flight not found']);
        exit();
    }
    
    // Build MVT-ARR message
    $message = buildMvtArrMessage($flight);
    
    // Send to CAO API
    $result = sendToCaoApi('MVT', [$message]);
    
    echo json_encode([
        'success' => $result['success'],
        'message' => $message,
        'response' => $result['response'],
        'http_code' => $result['http_code']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Build MVT-ARR message from flight data
 */
function buildMvtArrMessage($flight) {
    // Extract flight number
    $flightNumber = $flight['TaskName'] ?? $flight['FlightNo'] ?? '';
    
    // Extract date (format: DDMMM - e.g., 10DEC)
    $flightDate = new DateTime($flight['FltDate']);
    $dateStr = $flightDate->format('d') . strtoupper($flightDate->format('M'));
    
    // Extract route (origin and destination)
    $route = $flight['Route'] ?? '';
    $routeParts = explode('-', $route);
    $origin = trim($routeParts[0] ?? '');
    
    // Aircraft registration
    $aircraftReg = $flight['Rego'] ?? '';
    
    // Times
    $touchdown = '';
    $onBlock = '';
    
    if (!empty($flight['actual_on_utc'])) {
        $touchdownTime = new DateTime($flight['actual_on_utc']);
        $touchdown = $touchdownTime->format('Hi');
    }
    
    if (!empty($flight['actual_in_utc'])) {
        $onBlockTime = new DateTime($flight['actual_in_utc']);
        $onBlock = $onBlockTime->format('Hi');
    }
    
    // Day of month
    $day = $flightDate->format('d');
    
    // SI (Special Information)
    $si = $flight['remark_1'] ?? 'NORMAL OPS';
    
    // Build message
    $message = "MVT\n";
    $message .= "{$flightNumber}/{$dateStr}.{$aircraftReg}.{$origin}\n";
    
    if ($touchdown && $onBlock) {
        $message .= "AA{$touchdown}/{$onBlock}\n";
    }
    
    $message .= "FLD{$day}\n";
    
    if ($si) {
        $message .= "SI {$si}";
    }
    
    return $message;
}

/**
 * Send message to CAO API
 */
function sendToCaoApi($messageType, $messages) {
    $token = "3aea9ada385ce8dca95f125a0fc1c793";
    $url = "https://caadc.cao.ir/api/flight/messages";
    
    $payload = [
        "messageType" => $messageType,
        "messages" => $messages
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}",
        "Content-Type: application/json",
        "Accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'response' => $response,
        'http_code' => $httpCode,
        'error' => $error
    ];
}

