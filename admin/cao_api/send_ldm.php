<?php
/**
 * CAO API - Send LDM (Load Distribution Message)
 * 
 * این اسکریپت پیام LDM را از داده‌های Pax/Bags/Weight جدول flights می‌سازد
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
            Route,
            adult,
            child,
            infant,
            total_pax,
            pcs,
            weight,
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
    
    // Build LDM message
    $message = buildLdmMessage($flight);
    
    // Send to CAO API
    $result = sendToCaoApi('LDM', [$message]);
    
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
 * Build LDM message from flight data
 */
function buildLdmMessage($flight) {
    // Extract flight number
    $flightNumber = $flight['TaskName'] ?? $flight['FlightNo'] ?? '';
    
    // Extract route (origin)
    $route = $flight['Route'] ?? '';
    $routeParts = explode('-', $route);
    $origin = trim($routeParts[0] ?? '');
    
    // Passenger counts
    $adult = intval($flight['adult'] ?? 0);
    $child = intval($flight['child'] ?? 0);
    $infant = intval($flight['infant'] ?? 0);
    $totalPax = intval($flight['total_pax'] ?? 0);
    
    // Baggage
    $pcs = intval($flight['pcs'] ?? 0);
    $weight = intval($flight['weight'] ?? 0);
    
    // SI (Special Information)
    $si = $flight['remark_1'] ?? 'NORMAL OPS';
    
    // Build message
    $message = "LDM\n";
    $message .= "{$flightNumber}\n";
    
    // Origin and passenger breakdown
    $message .= "-{$origin}.{$adult}/{$child}/{$infant}";
    
    // Total weight
    if ($weight > 0) {
        $message .= ".T{$weight}";
    }
    
    // Compartments (if available - simplified)
    // In real implementation, you might have compartment data
    // For now, we'll skip compartment details
    
    // Total passengers
    if ($totalPax > 0) {
        $message .= ".PAX/{$totalPax}";
    }
    
    // Baggage
    if ($pcs > 0 && $weight > 0) {
        $message .= ".B{$pcs}/{$weight}";
    }
    
    $message .= "\n";
    
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

