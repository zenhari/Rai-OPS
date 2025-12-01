<?php
/**
 * Import Station Info from CSV
 * This script imports station information from the CSV file into the station_info table
 * 
 * Usage: Run this script from command line or via browser
 * php import_station_info.php
 */

require_once '../config.php';

// Set execution time limit for large imports
set_time_limit(300);

$csvFile = __DIR__ . '/Location Mehdi_20251108_1045.csv';
$imported = 0;
$errors = 0;
$errorMessages = [];

if (!file_exists($csvFile)) {
    die("Error: CSV file not found: $csvFile\n");
}

try {
    $db = getDBConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Open CSV file
    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        throw new Exception("Cannot open CSV file");
    }
    
    // Skip first line (title)
    fgetcsv($handle);
    
    // Read header line
    $headers = fgetcsv($handle);
    if (!$headers) {
        throw new Exception("Cannot read CSV headers");
    }
    
    // Map CSV columns to database fields
    $columnMap = [
        'LocationID' => 'location_id',
        'Address_Line1' => 'address_line1',
        'Address_Line2' => 'address_line2',
        'ALACallFrequency' => 'ala_call_frequency',
        'ALACallSign' => 'ala_call_sign',
        'ALACallType' => 'ala_call_type',
        'ALAChanges' => 'ala_changes',
        'ALACompanyAirportCategorisation' => 'ala_company_airport_categorisation',
        'ALADateEdited' => 'ala_date_edited',
        'ALADateInspection' => 'ala_date_inspection',
        'ALADescriptions' => 'ala_descriptions',
        'ALADistance' => 'ala_distance',
        'ALAElevation' => 'ala_elevation',
        'ALAFuelNotes' => 'ala_fuel_notes',
        'ALAGPSWayPointAssign' => 'ala_gps_waypoint_assign',
        'ALAID' => 'ala_id',
        'ALALastUpdatedByID' => 'ala_last_updated_by_id',
        'ALALastUpdatedByName' => 'ala_last_updated_by_name',
        'ALALightingFrequency' => 'ala_lighting_frequency',
        'ALALightingNotes' => 'ala_lighting_notes',
        'ALALightingType' => 'ala_lighting_type',
        'ALALocationIdentifier' => 'ala_location_identifier',
        'ALANavaids' => 'ala_navaids',
        'ALANightOperations' => 'ala_night_operations',
        'ALAObstacle_Hazards' => 'ala_obstacle_hazards',
        'ALAOperatingHours' => 'ala_operating_hours',
        'ALARemarks_Restrictions' => 'ala_remarks_restrictions',
        'ALATrack' => 'ala_track',
        'ALAType' => 'ala_type',
        'ALAUpdateContact' => 'ala_update_contact',
        'ALAWindsock' => 'ala_windsock',
        'BaseID' => 'base_id',
        'BaseManager' => 'base_manager',
        'BaseName' => 'base_name',
        'BaseShortName' => 'base_short_name',
        'Country' => 'country',
        'FuelAllBatchNo' => 'fuel_all_batch_no',
        'FuelAllContactAuth' => 'fuel_all_contact_auth',
        'FuelAllControllingAuth' => 'fuel_all_controlling_auth',
        'FuelAllType' => 'fuel_all_type',
        'FuelMeasurement' => 'fuel_measurement',
        'FuelMinExpiry' => 'fuel_min_expiry',
        'FuelTotalQty' => 'fuel_total_qty',
        'FuelTotalQtyRemaining' => 'fuel_total_qty_remaining',
        'FuelUpdatedAt' => 'fuel_updated_at',
        'GPS_Latitude' => 'gps_latitude',
        'GPS_Longitude' => 'gps_longitude',
        'GPSCoordinates' => 'gps_coordinates',
        'GPSWayPoint' => 'gps_waypoint',
        'HLS_ID' => 'hls_id',
        'HLSBest_Approach_Direction' => 'hls_best_approach_direction',
        'HLSBest_Departure_Direction' => 'hls_best_departure_direction',
        'HLSCAContact' => 'hls_ca_contact',
        'HLSCallFrequency' => 'hls_call_frequency',
        'HLSCallSign' => 'hls_call_sign',
        'HLSCallType' => 'hls_call_type',
        'HLSCAOperator' => 'hls_ca_operator',
        'HLSDescription' => 'hls_description',
        'HLSDimensions' => 'hls_dimensions',
        'HLSElevation' => 'hls_elevation',
        'HLSGPSWayPoint' => 'hls_gps_waypoint',
        'HLSGPSWayPointAssign' => 'hls_gps_waypoint_assign',
        'HLSLastUpdated' => 'hls_last_updated',
        'HLSLastUpdatedByID' => 'hls_last_updated_by_id',
        'HLSLastUpdatedByName' => 'hls_last_updated_by_name',
        'HLSLighitngControlledBy' => 'hls_lighting_controlled_by',
        'HLSLighting' => 'hls_lighting',
        'HLSLightingContact' => 'hls_lighting_contact',
        'HLSLightingFrequency' => 'hls_lighting_frequency',
        'HLSLightingNotes' => 'hls_lighting_notes',
        'HLSNavaids' => 'hls_navaids',
        'HLSNightOperations' => 'hls_night_operations',
        'HLSObstacles_Hazards' => 'hls_obstacles_hazards',
        'HLSOperatingHours' => 'hls_operating_hours',
        'HLSPositionBearing' => 'hls_position_bearing',
        'HLSPositionDirection' => 'hls_position_direction',
        'HLSPositionLocation' => 'hls_position_location',
        'HLSRemark_Restrications' => 'hls_remark_restrictions',
        'HLSSlope' => 'hls_slope',
        'HLSType' => 'hls_type',
        'HLSUpdateContact' => 'hls_update_contact',
        'HLSWindsock' => 'hls_windsock',
        'LocationName' => 'location_name',
        'LocationType' => 'location_type',
        'LocationTypeID' => 'location_type_id',
        'PostCode' => 'postcode',
        'SlotCoordination' => 'slot_coordination',
        'State' => 'state',
        'SuburbCity' => 'suburb_city'
    ];
    
    // Prepare INSERT statement
    $fields = array_values($columnMap);
    $placeholders = array_fill(0, count($fields), '?');
    $sql = "INSERT INTO station_info (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $db->prepare($sql);
    
    // Read and process each row
    $rowNum = 2; // Start from row 2 (after header)
    while (($data = fgetcsv($handle)) !== FALSE) {
        $rowNum++;
        
        // Skip empty rows
        if (empty(array_filter($data))) {
            continue;
        }
        
        // Map CSV data to database fields
        $values = [];
        foreach ($headers as $index => $header) {
            $dbField = $columnMap[$header] ?? null;
            if ($dbField) {
                $value = isset($data[$index]) ? trim($data[$index]) : null;
                
                // Handle empty strings as NULL
                if ($value === '' || $value === null) {
                    $value = null;
                }
                
                // Convert boolean strings
                if (in_array($dbField, ['ala_night_operations', 'ala_windsock', 'hls_night_operations', 'hls_windsock'])) {
                    $value = ($value === 'TRUE' || $value === '1' || $value === true) ? 1 : 0;
                }
                
                // Convert dates
                if (in_array($dbField, ['ala_date_edited', 'ala_date_inspection', 'fuel_updated_at', 'hls_last_updated'])) {
                    if (!empty($value)) {
                        // Try to parse date
                        $date = date_create($value);
                        $value = $date ? $date->format('Y-m-d') : null;
                    } else {
                        $value = null;
                    }
                }
                
                // Convert numeric fields
                if (in_array($dbField, ['location_id', 'ala_id', 'ala_last_updated_by_id', 'base_id', 'fuel_min_expiry', 'hls_id', 'hls_last_updated_by_id', 'location_type_id'])) {
                    $value = !empty($value) ? intval($value) : null;
                }
                
                if (in_array($dbField, ['gps_latitude', 'gps_longitude', 'fuel_total_qty', 'fuel_total_qty_remaining'])) {
                    $value = !empty($value) ? floatval($value) : null;
                }
                
                $values[] = $value;
            }
        }
        
        // Execute INSERT
        try {
            $stmt->execute($values);
            $imported++;
        } catch (PDOException $e) {
            $errors++;
            $errorMessages[] = "Row $rowNum: " . $e->getMessage();
            error_log("Error importing row $rowNum: " . $e->getMessage());
        }
    }
    
    fclose($handle);
    
    // Commit transaction
    $db->commit();
    
    echo "Import completed successfully!\n";
    echo "Imported: $imported records\n";
    echo "Errors: $errors records\n";
    
    if (!empty($errorMessages)) {
        echo "\nError details:\n";
        foreach ($errorMessages as $msg) {
            echo "- $msg\n";
        }
    }
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Import error: " . $e->getMessage());
    exit(1);
}

