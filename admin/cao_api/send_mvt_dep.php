<?php
/**
 * CAO API - Send MVT DEP (Departure) Message
 * 
 * این اسکریپت پیام MVT-DEP را از داده‌های جدول flights می‌سازد
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
            TaskStart,
            actual_out_utc,
            actual_off_utc,
            air_time_min,
            total_pax,
            delay_diversion_codes,
            minutes_1,
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
    
    // Build MVT-DEP message
    $message = buildMvtDepMessage($flight);
    
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
 * Build MVT-DEP message from flight data
 */
function buildMvtDepMessage($flight) {
    // Extract flight number
    $flightNumber = $flight['TaskName'] ?? $flight['FlightNo'] ?? '';
    
    // Extract date (format: DDMMM - e.g., 10DEC)
    $flightDate = new DateTime($flight['FltDate']);
    $dateStr = $flightDate->format('d') . strtoupper($flightDate->format('M'));
    
    // Extract route (origin and destination)
    $route = $flight['Route'] ?? '';
    $routeParts = explode('-', $route);
    $origin = trim($routeParts[0] ?? '');
    $destination = trim($routeParts[1] ?? $routeParts[0] ?? '');
    
    // Aircraft registration
    $aircraftReg = $flight['Rego'] ?? '';
    
    // Times
    $offBlock = '';
    $airborne = '';
    $eta = '';
    
    if (!empty($flight['actual_out_utc'])) {
        $offBlockTime = new DateTime($flight['actual_out_utc']);
        $offBlock = $offBlockTime->format('Hi');
    }
    
    if (!empty($flight['actual_off_utc'])) {
        $airborneTime = new DateTime($flight['actual_off_utc']);
        $airborne = $airborneTime->format('Hi');
        
        // Calculate ETA (airborne + air_time_min)
        if (!empty($flight['air_time_min'])) {
            $etaTime = clone $airborneTime;
            $etaTime->modify('+' . intval($flight['air_time_min']) . ' minutes');
            $eta = $etaTime->format('Hi');
        }
    }
    
    // Passenger count
    $totalPax = intval($flight['total_pax'] ?? 0);
    
    // Delay block
    $delayBlock = '';
    if (!empty($flight['delay_diversion_codes']) && !empty($flight['minutes_1'])) {
        $delayCode = $flight['delay_diversion_codes'];
        $delayMinutes = intval($flight['minutes_1']);
        $delayBlock = "DL{$delayCode}{$delayMinutes}";
    }
    
    // SI (Special Information)
    $si = $flight['remark_1'] ?? 'NORMAL OPS';
    
    // Build message
    $message = "MVT\n";
    $message .= "{$flightNumber}/{$dateStr}.{$aircraftReg}.{$origin}\n";
    
    if ($offBlock && $airborne) {
        $message .= "AD{$offBlock}/{$airborne}";
        if ($eta && $destination) {
            $message .= " EA{$eta} {$destination}";
        }
        $message .= "\n";
    }
    
    if ($delayBlock) {
        $message .= "{$delayBlock}\n";
    }
    
    if ($totalPax > 0) {
        $message .= "PX{$totalPax}\n";
    }
    
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

