<?php
require_once '../../../config.php';

// Check access
if (!isLoggedIn() || !checkPageAccessEnhanced('admin/fleet/aircraft/index.php')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

// Set JSON header
header('Content-Type: application/json');

try {
    $aircraft_id = intval($_GET['id'] ?? 0);
    
    if ($aircraft_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid aircraft ID']);
        exit();
    }
    
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT 
            id,
            registration,
            serial_number,
            aircraft_type,
            aircraft_category,
            manufacturer,
            date_of_manufacture,
            status,
            base_location,
            nvfr,
            ifr,
            spifr,
            engine_type,
            number_of_engines,
            engine_model,
            avionics,
            internal_configuration,
            external_configuration,
            airframe_type,
            created_at,
            updated_at
        FROM aircraft 
        WHERE id = ?
    ");
    
    $stmt->execute([$aircraft_id]);
    $aircraft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$aircraft) {
        echo json_encode(['success' => false, 'error' => 'Aircraft not found']);
        exit();
    }
    
    // Convert boolean values to proper format
    $aircraft['nvfr'] = (bool)$aircraft['nvfr'];
    $aircraft['ifr'] = (bool)$aircraft['ifr'];
    $aircraft['spifr'] = (bool)$aircraft['spifr'];
    
    echo json_encode([
        'success' => true,
        'aircraft' => $aircraft
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
