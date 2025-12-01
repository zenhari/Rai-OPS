<?php
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$search = $_GET['search'] ?? '';
$limit = intval($_GET['limit'] ?? 50);

if (empty($search) || strlen($search) < 1) {
    echo json_encode(['success' => true, 'stations' => []]);
    exit();
}

try {
    $db = getDBConnection();
    
    // Escape search term for safety
    $searchPattern = '%' . $search . '%';
    $exactPattern = $search . '%';
    
    // Use integer for LIMIT instead of placeholder (some MySQL versions don't support LIMIT with placeholders)
    $limit = max(1, min(100, intval($limit))); // Ensure limit is between 1 and 100
    
    // Search in station_name, iata_code, icao_code, short_name
    // Handle NULL values properly in WHERE clause
    $stmt = $db->prepare("
        SELECT 
            id,
            station_name,
            iata_code,
            COALESCE(icao_code, '') as icao_code,
            COALESCE(short_name, '') as short_name,
            COALESCE(country, '') as country
        FROM stations
        WHERE 
            (station_name LIKE ? OR
            iata_code LIKE ? OR
            (icao_code IS NOT NULL AND icao_code LIKE ?) OR
            (short_name IS NOT NULL AND short_name LIKE ?))
        ORDER BY 
            CASE 
                WHEN iata_code LIKE ? THEN 1
                WHEN station_name LIKE ? THEN 2
                WHEN icao_code IS NOT NULL AND icao_code LIKE ? THEN 3
                ELSE 4
            END,
            station_name ASC
        LIMIT " . intval($limit) . "
    ");
    
    $stmt->execute([
        $searchPattern,
        $searchPattern,
        $searchPattern,
        $searchPattern,
        $exactPattern,
        $exactPattern,
        $exactPattern
    ]);
    
    $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results
    $formattedStations = [];
    foreach ($stations as $station) {
        $icaoCode = $station['icao_code'] ?? '';
        $formattedStations[] = [
            'id' => intval($station['id']),
            'station_name' => $station['station_name'] ?? '',
            'iata_code' => $station['iata_code'] ?? '',
            'icao_code' => $icaoCode,
            'short_name' => $station['short_name'] ?? '',
            'country' => $station['country'] ?? '',
            'display' => ($station['station_name'] ?? '') . ' (' . ($station['iata_code'] ?? '') . ($icaoCode ? ' / ' . $icaoCode : '') . ')'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'stations' => $formattedStations
    ]);
    
} catch (PDOException $e) {
    error_log("Error searching stations: " . $e->getMessage());
    error_log("SQL Error: " . $e->getTraceAsString());
    if (isset($stmt)) {
        error_log("SQL Error Info: " . print_r($stmt->errorInfo() ?? [], true));
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error searching stations',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error searching stations: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error searching stations',
        'error' => $e->getMessage()
    ]);
}

