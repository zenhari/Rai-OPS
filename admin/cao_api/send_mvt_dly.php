<?php
/**
 * CAO API - Send MVT DLY (Delay) Message
 * 
 * این اسکریپت پیام MVT-DLY را از داده‌های Delay Codes جدول flights می‌سازد
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
    
    // Get flight data with all delay codes
    $stmt = $db->prepare("
        SELECT 
            FlightID,
            TaskName,
            FlightNo,
            FltDate,
            Rego,
            Route,
            TaskStart,
            delay_diversion_codes,
            delay_diversion_codes_2,
            delay_diversion_codes_3,
            delay_diversion_codes_4,
            delay_diversion_codes_5,
            minutes_1,
            minutes_2,
            minutes_3,
            minutes_4,
            minutes_5,
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
    
    // Check if flight has delays
    $hasDelay = false;
    for ($i = 1; $i <= 5; $i++) {
        $codeField = $i === 1 ? 'delay_diversion_codes' : "delay_diversion_codes_{$i}";
        $minutesField = "minutes_{$i}";
        if (!empty($flight[$codeField]) && !empty($flight[$minutesField]) && intval($flight[$minutesField]) > 0) {
            $hasDelay = true;
            break;
        }
    }
    
    if (!$hasDelay) {
        http_response_code(400);
        echo json_encode(['error' => 'Flight has no delays']);
        exit();
    }
    
    // Build MVT-DLY messages (one for each delay code)
    $messages = buildMvtDlyMessages($flight);
    
    if (empty($messages)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid delay messages to send']);
        exit();
    }
    
    // Send to CAO API
    $result = sendToCaoApi('MVT', $messages);
    
    echo json_encode([
        'success' => $result['success'],
        'messages' => $messages,
        'response' => $result['response'],
        'http_code' => $result['http_code']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Build MVT-DLY messages from flight data
 */
function buildMvtDlyMessages($flight) {
    $messages = [];
    
    // Extract flight number
    $flightNumber = $flight['TaskName'] ?? $flight['FlightNo'] ?? '';
    
    // Extract date (format: DDMMM - e.g., 10DEC)
    $flightDate = new DateTime($flight['FltDate']);
    $dateStr = $flightDate->format('d') . strtoupper($flightDate->format('M'));
    
    // Extract route (origin)
    $route = $flight['Route'] ?? '';
    $routeParts = explode('-', $route);
    $origin = trim($routeParts[0] ?? '');
    
    // Aircraft registration
    $aircraftReg = $flight['Rego'] ?? '';
    
    // Scheduled departure time (STD)
    $estimatedDep = '';
    if (!empty($flight['TaskStart'])) {
        $stdTime = new DateTime($flight['TaskStart']);
        $estimatedDep = $stdTime->format('Hi');
    }
    
    // Day of month
    $day = $flightDate->format('d');
    
    // SI (Special Information)
    $si = $flight['remark_1'] ?? 'NORMAL OPS';
    
    // Build message for each delay code
    for ($i = 1; $i <= 5; $i++) {
        $codeField = $i === 1 ? 'delay_diversion_codes' : "delay_diversion_codes_{$i}";
        $minutesField = "minutes_{$i}";
        
        $delayCode = $flight[$codeField] ?? '';
        $delayMinutes = intval($flight[$minutesField] ?? 0);
        
        if (!empty($delayCode) && $delayMinutes > 0) {
            $message = "MVT\n";
            $message .= "{$flightNumber}/{$dateStr}.{$aircraftReg}.{$origin}\n";
            
            if ($estimatedDep) {
                $message .= "ED{$day}{$estimatedDep}\n";
            }
            
            $message .= "DL{$delayCode}{$delayMinutes}\n";
            
            if ($si) {
                $message .= "SI {$si}";
            }
            
            $messages[] = $message;
        }
    }
    
    return $messages;
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

