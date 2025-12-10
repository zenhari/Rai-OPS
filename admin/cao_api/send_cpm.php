<?php
/**
 * CAO API - Send CPM (Container/Pallet Message)
 * 
 * این اسکریپت پیام CPM را می‌سازد.
 * در صورت نبود ULD، پیام با N/ ارسال می‌شود.
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
            FltDate
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
    
    // Check if ULD exists (you may need to add ULD field to flights table)
    // For now, we'll assume no ULD exists and send N/
    $hasUld = false; // TODO: Check ULD data from database
    
    // Build CPM message
    $message = buildCpmMessage($flight, $hasUld);
    
    // Send to CAO API
    $result = sendToCaoApi('CPM', [$message]);
    
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
 * Build CPM message from flight data
 */
function buildCpmMessage($flight, $hasUld = false) {
    // Extract flight number
    $flightNumber = $flight['TaskName'] ?? $flight['FlightNo'] ?? '';
    
    // Build message
    $message = "CPM\n";
    $message .= "{$flightNumber}\n";
    
    if (!$hasUld) {
        // No ULD - send N/ for all compartments
        $message .= "-11/N\n";
        $message .= "-12/N\n";
        $message .= "-13/N";
    } else {
        // TODO: Build ULD details if available
        // Format: -11/<ULD_NUMBER> or -11/N if empty
        $message .= "-11/N\n";
        $message .= "-12/N\n";
        $message .= "-13/N";
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

