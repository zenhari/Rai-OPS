<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/operations/ofp.php');

$current_user = getCurrentUser();

// Get parameters
$request_id = isset($_GET['request_id']) ? $_GET['request_id'] : '';
$file_name = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($request_id) || empty($file_name)) {
    header('Location: ofp.php');
    exit();
}

// Log directory path
$log_dir = __DIR__ . '/../../skyputer/logs/';
$file_path = $log_dir . $file_name;

if (!file_exists($file_path)) {
    header('Location: ofp.php');
    exit();
}

// Load JSON file
$content = @file_get_contents($file_path);
if ($content === false) {
    header('Location: ofp.php');
    exit();
}

$json_data = @json_decode($content, true);
if (!is_array($json_data)) {
    header('Location: ofp.php');
    exit();
}

// Find the record by request_id
$record = null;
foreach ($json_data as $item) {
    if (isset($item['request_id']) && $item['request_id'] === $request_id) {
        $record = $item;
        break;
    }
}

if (!$record) {
    header('Location: ofp.php');
    exit();
}

// OFP Field Labels Mapping
$field_labels = [
    // binfo fields
    'OPT' => 'Operator',
    '|OPT' => 'Operator',
    'UNT' => 'Unit',
    'FPF' => 'FUEL INCLUDES 6.0 PCT PERF FACTOR',
    'FLN' => 'Flight Number',
    'DTE' => 'Date',
    'STD' => 'Estimated Time of Departure',
    'ETA' => 'Estimated Time of Arrival',
    'REG' => 'Aircraft Registration',
    'THM' => 'FMACH:',
    'MCI' => 'FMach',
    'FLL' => 'Flight Level',
    'DOW' => 'Dry Operating Weight',
    'PLD' => 'Payload',
    'EZFW' => 'EZFW',
    'ETOW' => 'ETOW',
    'ELDW' => 'ELDW',
    'CRW' => 'Crew Version',
    'ALTN1' => 'ALTN 1',
    'ALTN2' => 'ALTN 2',
    'RTM' => 'Main Route',
    'RTA' => '1st Alternate Route',
    'RTB' => '2nd Alternate Route',
    'MODA' => 'MODA',
    'MODB' => 'MODB',
    'RTS' => 'Route',
    '|RTS' => 'Route',
    'RTT' => 'Takeoff Alternate Route',
    'STT' => 'Take-off Alternate Summary',
    'DID' => 'Document ID',
    'VDT' => 'OFP Generated Date and Time',
    'NGM' => 'Nautical Ground Mile (Total Distance)',
    'NAM' => 'Nautical Air Mile',
    'CM1' => 'Commander 1',
    'CM2' => 'Commander 2',
    'DSP' => 'Dispatcher',
    // futbl fields
    'PRM' => 'Parameter',
    'TIM' => 'Time of PRM',
    'VAL' => 'Value of PRM',
    // mpln/apln/bpln/tpln fields
    'WAP' => 'Waypoint',
    'GEO' => 'Coordinates',
    'FRQ' => 'Frequency',
    'VIA' => 'Airway',
    'ALT' => 'Flight Phase (CLB / FL / DES)',
    'MEA' => 'Minimum En Route Altitude',
    'GMR' => 'Grid Mora',
    'DIS' => 'Distance',
    'TDS' => 'Total Distance',
    'WID' => 'Wind Information',
    'TRK' => 'Track / Heading',
    'TMP' => 'Temperature / ISA',
    'TME' => 'Time',
    'TTM' => 'Total Time',
    'FRE' => 'Fuel Remaining',
    'FUS' => 'Fuel Used',
    'TAS' => 'True Airspeed',
    'GSP' => 'Ground Speed',
    'LAT' => 'Latitude',
    'LON' => 'Longitude',
    // cstbl fields
    'ETN' => 'Entry Number',
    'APT' => 'Airport',
    'ETP' => 'Entry Type',
    'ATI' => 'Altitude',
    'RWY' => 'Runway',
    'FUR' => 'Fuel Remaining',
    'FUQ' => 'Fuel Quantity',
    'FUD' => 'Fuel Used',
    // pdptbl fields
    'FLL' => 'Flight Level',
    'PDP' => 'Pre-determined Point',
    'PRT' => 'Part Route',
];

// Table descriptions
$table_descriptions = [
    'binfo' => 'Basic Information of the OFP',
    'futbl' => 'Fuel Table Sheet',
    'mpln' => 'Primary Point-to-Point',
    'apln' => '1st Alternate Point-to-Point',
    'bpln' => '2nd Alternate Point-to-Point',
    'tpln' => 'Take-off Alternate Point-to-Point',
    'cstbl' => 'Critical Fuel Scenario',
    'pdptbl' => 'Pre-determined Point Procedure',
    'aldrf' => 'Altitude Drift',
    'wtdrf' => 'Weight Drift',
    'wdtmp' => 'Wind & Temperature Aloft',
    'wdclb' => 'Wind Climb',
    'wddes' => 'Wind Descent',
    'icatc' => 'ICAO ATC Format',
];

/**
 * Get label for a field
 */
function getFieldLabel($field, $labels) {
    return $labels[$field] ?? $field;
}

/**
 * Format time value
 */
function formatTime($time) {
    if (empty($time)) return '';
    if (preg_match('/^(\d{2}):(\d{2}):(\d{2})/', $time, $matches)) {
        $hours = intval($matches[1]);
        $minutes = intval($matches[2]);
        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }
        return sprintf('%dm', $minutes);
    }
    return $time;
}

/**
 * Format time value in 24-hour format (HH:MM)
 */
function formatTime24h($time) {
    if (empty($time)) return '';
    if (preg_match('/^(\d{2}):(\d{2}):(\d{2})/', $time, $matches)) {
        $hours = intval($matches[1]);
        $minutes = intval($matches[2]);
        return sprintf('%02d:%02d', $hours, $minutes);
    }
    return $time;
}

/**
 * Format time value for Point-to-Point table (24-hour format)
 * Converts "11m" to "11" and "1h 25m" to "01:25"
 */
function formatTimeForTable($time) {
    if (empty($time)) return '';
    
    // First, try to parse from "HH:MM:SS" format
    if (preg_match('/^(\d{2}):(\d{2}):(\d{2})/', $time, $matches)) {
        $hours = intval($matches[1]);
        $minutes = intval($matches[2]);
        if ($hours > 0) {
            return sprintf('%02d:%02d', $hours, $minutes);
        }
        return sprintf('%d', $minutes);
    }
    
    // Try to parse from "1h 25m" or "11m" or "1h 01m" format
    // Pattern matches: optional hours (with h), optional minutes (with m)
    if (preg_match('/(?:(\d+)h\s*)?(?:(\d+)m)?/', $time, $matches)) {
        $hours = isset($matches[1]) && $matches[1] !== '' ? intval($matches[1]) : 0;
        $minutes = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : 0;
        
        if ($hours > 0) {
            // If hours exist, format as HH:MM (always 2 digits for minutes)
            return sprintf('%02d:%02d', $hours, $minutes);
        } elseif ($minutes > 0) {
            // If only minutes, return just the number (no leading zero)
            return sprintf('%d', $minutes);
        }
    }
    
    return $time;
}

/**
 * Format value for display
 */
function formatValue($value) {
    if ($value === null || $value === '') return '';
    if (is_numeric($value)) {
        return number_format($value);
    }
    return $value;
}

/**
 * Format distance
 */
function formatDistance($dist) {
    if ($dist === null || $dist === '') return '';
    if (is_numeric($dist)) {
        return number_format($dist, 1);
    }
    return $dist;
}

/**
 * Extract last part after last dot
 */
function extractLastPart($value) {
    if (empty($value)) return '';
    $parts = explode('.', $value);
    return end($parts);
}

// Extract route from parsed_data
$route = '';
if (isset($record['parsed_data']['binfo']['RTS'])) {
    $route = $record['parsed_data']['binfo']['RTS'];
} elseif (isset($record['parsed_data']['binfo']['|RTS'])) {
    $route = $record['parsed_data']['binfo']['|RTS'];
} elseif (isset($record['flight_info']['route'])) {
    $route = $record['flight_info']['route'];
}

// Extract RTS from raw_data (format: RTS=OIMM - OIGG;)
// Prefer raw_data format to get exact airport codes
$rts_raw = '';
if (!empty($record['raw_data'])) {
    if (preg_match('/RTS=([^;|\n]+)/', $record['raw_data'], $matches)) {
        $rts_raw = trim($matches[1]);
    }
}
// Fallback to route if raw_data extraction failed
if (empty($rts_raw)) {
    $rts_raw = $route;
}

// Parse airport codes from RTS (format: "OIMM - OIGG" or "OIMM-OIGG")
$oimm_code = 'OIMM'; // Default
$oigg_code = 'OIGG'; // Default

if (!empty($rts_raw)) {
    // Try to extract airport codes (format: "OIMM - OIGG" or "OIMM-OIGG" or "OIMM OIGG")
    if (preg_match('/\b([A-Z0-9]{4})\s*[- ]\s*([A-Z0-9]{4})\b/', $rts_raw, $matches)) {
        $oimm_code = $matches[1];
        $oigg_code = $matches[2];
    } elseif (preg_match('/\b([A-Z0-9]{4})\b/', $rts_raw, $matches)) {
        // If only one code found, use it for OIMM
        $oimm_code = $matches[1];
    }
}

// Extract flight number
$flight_number = $record['flight_info']['flight_number'] ?? ($record['parsed_data']['binfo']['FLN'] ?? 'N/A');

// Extract date
$record_date = '';
if (isset($record['flight_info']['date'])) {
    $record_date = $record['flight_info']['date'];
} elseif (isset($record['parsed_data']['binfo']['DTE'])) {
    $record_date = $record['parsed_data']['binfo']['DTE'];
} elseif (isset($record['parsed_data']['binfo']['|DTE'])) {
    $record_date = $record['parsed_data']['binfo']['|DTE'];
}

// Extract commander name
$commander = '';
if (isset($record['parsed_data']['binfo']['CM1'])) {
    $commander = $record['parsed_data']['binfo']['CM1'];
}

// Extract cabin senior (CM2 or DSP)
$cabin_senior = '';
if (isset($record['parsed_data']['binfo']['CM2'])) {
    $cabin_senior = $record['parsed_data']['binfo']['CM2'];
} elseif (isset($record['parsed_data']['binfo']['DSP'])) {
    $cabin_senior = $record['parsed_data']['binfo']['DSP'];
}

// Extract operator
$operator = $record['flight_info']['operator'] ?? ($record['parsed_data']['binfo']['OPT'] ?? ($record['parsed_data']['binfo']['|OPT'] ?? ''));

// Extract aircraft registration
$aircraft_reg = $record['flight_info']['aircraft_reg'] ?? ($record['parsed_data']['binfo']['REG'] ?? '');

// Extract ETD and ETA
$etd = $record['flight_info']['etd'] ?? ($record['parsed_data']['binfo']['ETD'] ?? '');
$eta = $record['flight_info']['eta'] ?? ($record['parsed_data']['binfo']['ETA'] ?? '');

// Extract DOW, PLD, CRW
$dow = $record['parsed_data']['binfo']['DOW'] ?? '';
$pld = $record['parsed_data']['binfo']['PLD'] ?? '';
$crw = $record['parsed_data']['binfo']['CRW'] ?? '';

// Extract total fuel from futbl
$total_fuel = 0;
$trip_fuel = 0;
$taxi_fuel = 0;
if (isset($record['parsed_data']['futbl']) && is_array($record['parsed_data']['futbl'])) {
    foreach ($record['parsed_data']['futbl'] as $fuel) {
        if (isset($fuel['PRM'])) {
            if ($fuel['PRM'] === 'TOTAL FUEL' && isset($fuel['VAL'])) {
                $total_fuel = $fuel['VAL'];
            }
            if ($fuel['PRM'] === 'TRIP FUEL' && isset($fuel['VAL'])) {
                $trip_fuel = $fuel['VAL'];
            }
            if ($fuel['PRM'] === 'TAXI' && isset($fuel['VAL'])) {
                $taxi_fuel = $fuel['VAL'];
            }
        }
    }
}

// Extract FLL (Flight Level)
$fll = $record['parsed_data']['binfo']['FLL'] ?? '';

// Extract DID and VDT
$did = $record['parsed_data']['binfo']['DID'] ?? '';
$vdt = $record['parsed_data']['binfo']['VDT'] ?? '';

// Extract VDT from raw_data (for warning box)
$vdt_from_raw = '';
if (!empty($record['raw_data'])) {
    if (preg_match('/VDT=([^;|\n]+)/', $record['raw_data'], $vdt_matches)) {
        $vdt_from_raw = trim($vdt_matches[1]);
    }
}
// Fallback to parsed_data if not found in raw_data
if (empty($vdt_from_raw)) {
    $vdt_from_raw = $vdt;
}

// Extract NGM and NAM
$ngm = $record['parsed_data']['binfo']['NGM'] ?? '';
$nam = $record['parsed_data']['binfo']['NAM'] ?? '';

// Extract RTM, MODA, and MODB
$rtm = $record['parsed_data']['binfo']['RTM'] ?? '';
$moda = $record['parsed_data']['binfo']['MODA'] ?? '';
$modb = $record['parsed_data']['binfo']['MODB'] ?? '';

// If not in parsed_data, try to extract from raw_data
if (empty($moda) && !empty($record['raw_data'])) {
    if (preg_match('/MODA=([^;|\n]+)/', $record['raw_data'], $matches)) {
        $moda = trim($matches[1]);
    }
}

if (empty($modb) && !empty($record['raw_data'])) {
    if (preg_match('/MODB=([^;|\n]+)/', $record['raw_data'], $matches)) {
        $modb = trim($matches[1]);
    }
}

// Extract RTA and RTB (Alternate Routes)
$rta = $record['parsed_data']['binfo']['RTA'] ?? '';
$rtb = $record['parsed_data']['binfo']['RTB'] ?? '';

// Extract ALTN1 and ALTN2
$altn1 = $record['parsed_data']['binfo']['ALTN1'] ?? '';
$altn2 = $record['parsed_data']['binfo']['ALTN2'] ?? '';

// If not in parsed_data, try to extract from raw_data (format: ALTN=OIII, OINZ)
if ((empty($altn1) || empty($altn2)) && !empty($record['raw_data'])) {
    if (preg_match('/ALTN=([^;|\n]+)/', $record['raw_data'], $matches)) {
        $altn_raw = trim($matches[1]);
        // Split by comma and trim each value
        $altn_parts = array_map('trim', explode(',', $altn_raw));
        
        if (count($altn_parts) >= 1 && empty($altn1)) {
            $altn1 = $altn_parts[0];
        }
        if (count($altn_parts) >= 2 && empty($altn2)) {
            $altn2 = $altn_parts[1];
        }
    }
}

// Extract STT (Take-off Alternate Summary)
$stt = $record['parsed_data']['binfo']['STT'] ?? '';

// Extract ELDP and ELDS for ATIS elevations
$eldp = $record['parsed_data']['binfo']['ELDP'] ?? '';
$elds = $record['parsed_data']['binfo']['ELDS'] ?? '';

// If not in parsed_data, try to extract from raw_data
// Format: ELDP=ELEV: 3266 or ELDS=ELEV: -37
if (empty($eldp) && !empty($record['raw_data'])) {
    if (preg_match('/ELDP=([^;|\n]+)/', $record['raw_data'], $matches)) {
        $eldp = trim($matches[1]);
    }
}

if (empty($elds) && !empty($record['raw_data'])) {
    if (preg_match('/ELDS=([^;|\n]+)/', $record['raw_data'], $matches)) {
        $elds = trim($matches[1]);
    }
}

// Parse elevation values from ELDP and ELDS
// Format from raw_data: "ELEV: 3266" or "ELEV: -37"
// Format from parsed_data: might be just "3266" or "-37"
$oimm_elev = '3266'; // Default
$oigg_elev = '-37'; // Default

if (!empty($eldp)) {
    // Extract number from ELDP (handles "ELEV: 3266", "3266", or "ELDP=ELEV: 3266")
    if (preg_match('/(?:ELDP=)?(?:ELEV:\s*)?(-?\d+)/', $eldp, $matches)) {
        $oimm_elev = $matches[1];
    } else {
        // If no number found, use the whole value
        $oimm_elev = $eldp;
    }
}

if (!empty($elds)) {
    // Extract number from ELDS (handles "ELEV: -37", "-37", or "ELDS=ELEV: -37")
    if (preg_match('/(?:ELDS=)?(?:ELEV:\s*)?(-?\d+)/', $elds, $matches)) {
        $oigg_elev = $matches[1];
    } else {
        // If no number found, use the whole value
        $oigg_elev = $elds;
    }
}

$parsed_data = $record['parsed_data'] ?? [];

// Extract CM1, CM2, and DSP from raw_data for approval section
$cm1_raw = $record['parsed_data']['binfo']['CM1'] ?? '';
$cm2_raw = $record['parsed_data']['binfo']['CM2'] ?? '';
$dsp_raw = $record['parsed_data']['binfo']['DSP'] ?? '';

// If not in parsed_data, try to extract from raw_data
if (empty($cm1_raw) && !empty($record['raw_data'])) {
    if (preg_match('/CM1=([^;|\n]+)/', $record['raw_data'], $matches)) {
        $cm1_raw = trim($matches[1]);
    }
}

if (empty($cm2_raw) && !empty($record['raw_data'])) {
    if (preg_match('/CM2=([^;|\n]+)/', $record['raw_data'], $matches)) {
        $cm2_raw = trim($matches[1]);
    }
}

if (empty($dsp_raw) && !empty($record['raw_data'])) {
    if (preg_match('/DSP=([^;|\n]+)/', $record['raw_data'], $matches)) {
        $dsp_raw = trim($matches[1]);
    }
}

// Extract DTE and FLN from raw_data for API call
$dte_raw = $record['parsed_data']['binfo']['DTE'] ?? '';
$fln_raw = $record['parsed_data']['binfo']['FLN'] ?? '';

// If not in parsed_data, try to extract from raw_data
if (empty($dte_raw) && !empty($record['raw_data'])) {
    if (preg_match('/DTE=([^;|\n]+)/', $record['raw_data'], $matches)) {
        $dte_raw = trim($matches[1]);
    }
}

if (empty($fln_raw) && !empty($record['raw_data'])) {
    if (preg_match('/FLN=([^;|\n]+)/', $record['raw_data'], $matches)) {
        $fln_raw = trim($matches[1]);
    }
}

// Convert DTE from "NOV 11 2025" to "2025-11-11"
$api_date = '';
if (!empty($dte_raw)) {
    // Try to parse date format like "NOV 11 2025" or "NOV 11, 2025"
    $date_obj = DateTime::createFromFormat('M d Y', $dte_raw);
    if (!$date_obj) {
        $date_obj = DateTime::createFromFormat('M d, Y', $dte_raw);
    }
    if (!$date_obj) {
        $date_obj = DateTime::createFromFormat('M j Y', $dte_raw);
    }
    if (!$date_obj) {
        $date_obj = DateTime::createFromFormat('M j, Y', $dte_raw);
    }
    if ($date_obj) {
        $api_date = $date_obj->format('Y-m-d');
    }
}

// Convert FLN from "RAI7521" to "7521" (remove "RAI" prefix if present)
$api_flight_number = '';
if (!empty($fln_raw)) {
    // Remove "RAI" prefix if present
    $api_flight_number = preg_replace('/^RAI/i', '', $fln_raw);
    $api_flight_number = trim($api_flight_number);
}

// Fetch journey log data from API
$journey_log_data = null;
$block_off_time = '';
$take_off_time = '';
$landing_time = '';
$block_on_time = '';
$flight_time = '';
$block_time = '';

if (!empty($api_date) && !empty($api_flight_number)) {
    $api_key = '91d692cf-6b08-4fce-a2e2-fa9505192faa';
    $api_base_url = 'etl.raimonairways.net/api/for_journeylog.php';
    
    $post_data = json_encode([
        'date' => $api_date,
        'flight_numbers' => [$api_flight_number]
    ]);
    
    $api_response = null;
    $protocols = ['https', 'http'];
    
    // Try both https and http protocols
    foreach ($protocols as $protocol) {
        try {
            $api_url = $protocol . '://' . $api_base_url;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'key: ' . $api_key,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($http_code == 200 && !empty($response)) {
                $decoded_response = json_decode($response, true);
                if (isset($decoded_response['ok']) && $decoded_response['ok'] === true && 
                    isset($decoded_response['data']) && is_array($decoded_response['data']) && 
                    count($decoded_response['data']) > 0) {
                    $api_response = $decoded_response;
                    break; // Success, exit loop
                }
            }
        } catch (Exception $e) {
            // Continue to next protocol
            continue;
        }
    }
    
    // Process successful API response
    if ($api_response !== null && isset($api_response['data']) && is_array($api_response['data']) && 
        count($api_response['data']) > 0) {
        
        $journey_log_data = $api_response['data'][0];
        
        // Extract times from operations array
        if (isset($journey_log_data['operations']) && is_array($journey_log_data['operations']) && 
            count($journey_log_data['operations']) > 0) {
            
            $operation = $journey_log_data['operations'][0];
            
            // Extract and format times (convert "16:25:00" to "16:25")
            if (isset($operation['block_off_time'])) {
                $block_off_time = substr($operation['block_off_time'], 0, 5);
            }
            if (isset($operation['take_off_time'])) {
                $take_off_time = substr($operation['take_off_time'], 0, 5);
            }
            if (isset($operation['landing_time'])) {
                $landing_time = substr($operation['landing_time'], 0, 5);
            }
            if (isset($operation['block_on_time'])) {
                $block_on_time = substr($operation['block_on_time'], 0, 5);
            }
            if (isset($operation['flight_time'])) {
                $flight_time = substr($operation['flight_time'], 0, 5);
            }
            
            // Calculate block time from block_off_time to block_on_time
            if (!empty($operation['block_off_time']) && !empty($operation['block_on_time'])) {
                try {
                    $block_off = new DateTime($operation['block_off_time']);
                    $block_on = new DateTime($operation['block_on_time']);
                    // Handle case where block_on might be next day
                    if ($block_on < $block_off) {
                        $block_on->modify('+1 day');
                    }
                    $interval = $block_off->diff($block_on);
                    $block_time = sprintf('%02d:%02d', $interval->h, $interval->i);
                } catch (Exception $e) {
                    // If date parsing fails, leave block_time empty
                }
            }
        }
    }
}

// Extract time and distance from alternate routes
$altn1_time = '';
$altn1_distance = '';
$altn2_time = '';
$altn2_distance = '';

// Get time and distance from apln (1st Alternate Point-to-Point)
if (isset($parsed_data['apln']) && is_array($parsed_data['apln']) && !empty($parsed_data['apln'])) {
    $last_row = end($parsed_data['apln']);
    $altn1_time = formatTime($last_row['TTM'] ?? '');
    $altn1_distance = formatDistance($last_row['TDS'] ?? '');
}

// Get time and distance from bpln (2nd Alternate Point-to-Point)
if (isset($parsed_data['bpln']) && is_array($parsed_data['bpln']) && !empty($parsed_data['bpln'])) {
    $last_row = end($parsed_data['bpln']);
    $altn2_time = formatTime($last_row['TTM'] ?? '');
    $altn2_distance = formatDistance($last_row['TDS'] ?? '');
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OFP Details - <?php echo htmlspecialchars($flight_number); ?> - <?php echo PROJECT_NAME; ?></title>
    <script src="../../assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <style>
        :root {
            --ofp-bg: #ffffff;
            --ofp-text: #000000;
            --ofp-border: #000000;
            --ofp-header-bg: #f0f0f0;
            --ofp-section-border: #000000;
            --ofp-info-bg: #ffffff;
            --ofp-highlight-bg: #dbeafe;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --ofp-bg: #1f2937;
                --ofp-text: #f9fafb;
                --ofp-border: #4b5563;
                --ofp-header-bg: #374151;
                --ofp-section-border: #4b5563;
                --ofp-info-bg: #1f2937;
                --ofp-highlight-bg: #1e3a5f;
            }
        }

        body {
            font-family: 'Roboto', sans-serif;
        }
        .ofp-page {
            background: var(--ofp-bg);
            color: var(--ofp-text);
            min-height: 100vh;
            width: 100%;
            margin: 0;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        @media print {
            .ofp-page {
                box-shadow: none;
                margin: 0;
                padding: 10mm;
                width: 100%;
                background: white;
                color: black;
            }
            .no-print {
                display: none;
            }
            body {
                background: white;
            }
            .ofp-table th {
                background-color: #f0f0f0 !important;
            }
            .ofp-table td, .ofp-table th {
                border: 1px solid #000 !important;
                color: #000 !important;
            }
            /* Hide everything except main when printing main only */
            body.print-main-only header,
            body.print-main-only aside,
            body.print-main-only nav,
            body.print-main-only > div > div.lg\\:ml-64 > header {
                display: none !important;
            }
            body.print-main-only > div > div.lg\\:ml-64 > main {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            body.print-main-only > div > div.lg\\:ml-64 {
                width: 100% !important;
                margin: 0 !important;
            }
        }
        .ofp-header {
            border-bottom: 2px solid var(--ofp-border);
            padding-bottom: 15px;
            margin-bottom: 20px;
            text-align: center;
            color: var(--ofp-text);
        }
        .ofp-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 16px;
            margin-bottom: 15px;
            color: var(--ofp-text);
        }
        .ofp-table td, .ofp-table th {
            border: 1px solid var(--ofp-border);
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
            color: var(--ofp-text);
        }
        .ofp-table th {
            background-color: var(--ofp-header-bg);
            font-weight: bold;
            text-align: center;
            color: var(--ofp-text);
        }
        .ofp-section {
            margin-bottom: 20px;
        }
        .ofp-section-title {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 8px;
            padding-bottom: 4px;
            color: var(--ofp-text);
        }
        .ofp-info-box {
            border: 1px solid var(--ofp-border);
            padding: 8px;
            margin-bottom: 10px;
            background-color: var(--ofp-info-bg);
            color: var(--ofp-text);
        }
        .ofp-info-label {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2px;
            color: var(--ofp-text);
        }
        .ofp-info-value {
            font-size: 16px;
            color: var(--ofp-text);
        }
        .ofp-footer {
            color: var(--ofp-text);
            border-top-color: var(--ofp-border);
        }
        pre {
            color: var(--ofp-text);
            background-color: var(--ofp-info-bg);
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="flex flex-col min-h-screen">
        <!-- Include Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="lg:ml-64 flex-1">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 no-print">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-file-alt mr-2"></i>OFP Details
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Flight: <?php echo htmlspecialchars($flight_number); ?> | Route: <?php echo htmlspecialchars($route); ?>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <a href="ofp.php" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </a>
                            <button onclick="downloadWord()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors duration-200">
                                <i class="fas fa-download mr-2"></i>Download Word
                            </button>
                            <button onclick="printMainOnly()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-print mr-2"></i>Print / PDF
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="px-4 sm:px-6 lg:px-8 py-6">
                <div id="ofpContent" class="ofp-page">
                    <!-- Header -->
                    <div class="ofp-header">
                        <table class="ofp-table" style="border: none;">
                            <tr>
                                <td style="border: none; text-align: center; padding: 10px 0; color: var(--ofp-text);">
                                    <img id="raimonLogo" src="/assets/raimon.png" alt="RAIMON AIRWAYS" style="max-height: 80px; margin-bottom: 10px;" onerror="this.style.display='none';">
                                    <div style="font-size: 20px; font-weight: bold; text-align: center; letter-spacing: 1px; color: var(--ofp-text);">RAIMON AIRWAYS</div>
                                    <div style="font-size: 16px; text-align: center; margin-top: 5px; font-weight: bold; color: var(--ofp-text);">OPERATIONAL FLIGHT PLAN</div>
                                </td>
                            </tr>
                        </table>
                        <?php if (!empty($vdt_from_raw)): ?>
                        <div style="background-color: #fef3c7; border: 2px solid #f59e0b; padding: 12px; margin-bottom: 20px; border-radius: 4px; position: relative; z-index: 1; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="font-size: 20px; color: #f59e0b;">⚠️</div>
                                <div style="flex: 1;">
                                    <div style="font-weight: bold; color: #92400e; margin-bottom: 5px; font-size: 16px;">Warning</div>
                                    <div style="color: #78350f; font-size: 14px; line-height: 1.5;">
                                        <strong>OFP Generated Date and Time:</strong> <?php echo htmlspecialchars($vdt_from_raw); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- TRIP INFORMATION Section -->
                    <div class="ofp-section">
                        <div class="ofp-section-title">TRIP INFORMATION</div>
                        <table class="ofp-table">
                            <tr>
                                <th style="width: 20%;">DATE</th>
                                <td style="width: 30%;"><?php echo htmlspecialchars($record_date); ?></td>
                                <th style="width: 20%;">AIRCRAFT</th>
                                <td style="width: 30%;"><?php echo htmlspecialchars($aircraft_reg); ?></td>
                            </tr>
                            <tr>
                                <th>FLIGHT NO</th>
                                <td><?php echo htmlspecialchars($flight_number); ?></td>
                                <th>ROUTE</th>
                                <td><?php echo htmlspecialchars($route); ?></td>
                            </tr>
                            <tr>
                                <th>ETD</th>
                                <td><?php echo htmlspecialchars($etd); ?></td>
                                <th>ETA</th>
                                <td><?php echo htmlspecialchars($eta); ?></td>
                            </tr>
                            <tr>
                                <th>COMMANDER</th>
                                <td><?php echo htmlspecialchars($commander); ?></td>
                                <th>CREW VERSION</th>
                                <td><?php echo htmlspecialchars($crw); ?></td>
                            </tr>
                            
                            <tr>
                                <th>NGM</th>
                                <td><?php echo htmlspecialchars(formatDistance($ngm)); ?></td>
                                <th>NAM</th>
                                <td><?php echo htmlspecialchars(formatDistance($nam)); ?></td>
                            </tr>
                            <tr>
                                <th>MAIN ROUTE</th>
                                <td><?php echo htmlspecialchars($rtm); ?></td>
                                <th>OPERATOR</th>
                                <td><?php echo htmlspecialchars($operator); ?></td>
                                
                            </tr>
                            <tr>
                                <th>ALT1</th>
                                <td><?php echo htmlspecialchars($altn1); ?></td>
                                <th>ALT2</th>
                                <td><?php echo htmlspecialchars($altn2); ?></td>
                            </tr>
                            <?php if ($operator || $cabin_senior): ?>
                            <tr>
                                
                                
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <!-- ATC Clearance Section -->
                    <div class="ofp-section">
                        <div class="ofp-section-title">ATC CLEARANCE</div>
                        <div class="ofp-info-box">
                            <textarea name="ATC Clearance" rows="4" style="width: 100%; padding: 8px; border: 1px solid var(--ofp-border); background-color: var(--ofp-info-bg); color: var(--ofp-text); font-family: inherit; font-size: 16px; resize: vertical;"></textarea>
                        </div>
                    </div>

                    <!-- Take-off Alternate Summary (STT) Section -->
                    <?php if (!empty($stt)): ?>
                    <div class="ofp-section">
                        <div class="ofp-section-title">TAKE-OFF ALTERNATE SUMMARY</div>
                        <div class="ofp-info-box">
                            <div style="font-size: 16px; color: var(--ofp-text); white-space: pre-wrap;"><?php echo htmlspecialchars($stt); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    // Display additional binfo fields that are not shown in TRIP INFORMATION
                    if (isset($parsed_data['binfo']) && is_array($parsed_data['binfo'])) {
                        $binfo_displayed = ['DTE', '|DTE', 'FLN', 'RTS', '|RTS', 'REG', 'ETD', 'ETA', 'CM1', 'CM2', 'DSP', 'DID', 'VDT', 'OPT', '|OPT', 'NGM', 'NAM', 'RTM', 'MODA', 'MODB', 'STT', 'FPF', 'THM', 'ALTN', 'SPT', 'ELDP', 'ELDS', 'ELAL', 'ELBL', 'MSH', 'RTA', 'RTB'];
                        $binfo_keys = array_keys($parsed_data['binfo']);
                        $missing_binfo = array_diff($binfo_keys, $binfo_displayed);
                        
                        if (!empty($missing_binfo)):
                    ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">ADDITIONAL BASIC INFORMATION</div>
                            <div class="ofp-info-box">
                                <table class="ofp-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 30%;">Field</th>
                                            <th style="width: 50%;">Value</th>
                                            <th style="width: 20%;">Input</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Add CREW VERSION row with Pax inputs
                                        $crw_value = $parsed_data['binfo']['CRW'] ?? '';
                                        if (!empty($crw_value) || in_array('CRW', $missing_binfo)):
                                        ?>
                                            <tr>
                                                <td style="font-weight: bold;"><?php echo htmlspecialchars(getFieldLabel('CRW', $field_labels)); ?> (CRW)</td>
                                                <td><?php echo htmlspecialchars($crw_value ?: 'N/A'); ?></td>
                                                <td>&nbsp;</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($missing_binfo as $key): 
                                            // Skip CRW as it's handled separately above
                                            if ($key === 'CRW') continue;
                                            $showInput = in_array($key, ['PLD', 'EZFW', 'ETOW', 'ELDW']);
                                        ?>
                                            <tr>
                                                <td style="font-weight: bold;"><?php echo htmlspecialchars(getFieldLabel($key, $field_labels)); ?> (<?php echo htmlspecialchars($key); ?>)</td>
                                                <td><?php echo htmlspecialchars($parsed_data['binfo'][$key] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($showInput): ?>
                                                        <input type="text" name="<?php echo htmlspecialchars($key); ?>" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background-color: var(--ofp-info-bg); color: var(--ofp-text); font-size: 16px;" />
                                                    <?php else: ?>
                                                        &nbsp;
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                            <tr>
                                                <td colspan="3">
                                                    <div style="display: flex; align-items: center; gap: 5px;">
                                                        <label style="font-size: 16px; color: var(--ofp-text);">Pax:</label>
                                                        <input type="number" name="pax_1" style="width: 60px; padding: 4px; border: 1px solid var(--ofp-border); background-color: var(--ofp-info-bg); color: var(--ofp-text); font-size: 16px;" />
                                                        <input type="number" name="pax_2" style="width: 60px; padding: 4px; border: 1px solid var(--ofp-border); background-color: var(--ofp-info-bg); color: var(--ofp-text); font-size: 16px;" />
                                                        <input type="number" name="pax_3" style="width: 60px; padding: 4px; border: 1px solid var(--ofp-border); background-color: var(--ofp-info-bg); color: var(--ofp-text); font-size: 16px;" />
                                                    </div>
                                                </td>
                                            </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php 
                        endif;
                    }
                    ?>

                    <!-- Alternate Routes Section -->
                    <?php if (!empty($rta) || !empty($rtb) || !empty($altn1) || !empty($altn2)): ?>
                    <div class="ofp-section">
                        <div class="ofp-section-title">ALTERNATE ROUTES</div>
                        <div class="ofp-info-box">
                            <table class="ofp-table">
                                <?php if (!empty($rta)): ?>
                                <tr>
                                    <th style="width: 30%;">1st Alternate Route (RTA)</th>
                                    <td style="width: 70%;"><?php echo htmlspecialchars($rta); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($rtb)): ?>
                                <tr>
                                    <th style="width: 30%;">2nd Alternate Route (RTB)</th>
                                    <td style="width: 70%;"><?php echo htmlspecialchars($rtb); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                            
                            <?php if (!empty($altn1) || !empty($altn2)): ?>
                            <table class="ofp-table" style="margin-top: 15px;">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">Route</th>
                                        <th style="width: 30%;">Time</th>
                                        <th style="width: 30%;">Distance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($altn1)): 
                                        $altn1_last = extractLastPart($altn1);
                                    ?>
                                    <tr>
                                        <td>ALTN 1<?php echo !empty($altn1_last) ? ' (' . htmlspecialchars($altn1_last) . ')' : ''; ?></td>
                                        <td style="text-align: center;"><?php echo htmlspecialchars($altn1_time); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars($altn1_distance); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($altn2)): 
                                        $altn2_last = extractLastPart($altn2);
                                    ?>
                                    <tr>
                                        <td>ALTN 2<?php echo !empty($altn2_last) ? ' (' . htmlspecialchars($altn2_last) . ')' : ''; ?></td>
                                        <td style="text-align: center;"><?php echo htmlspecialchars($altn2_time); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars($altn2_distance); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    // Parse TAF from raw_data
                    $taf = '';
                    if (!empty($record['raw_data'])) {
                        if (preg_match('/taf:\|(.*?)(?:\|\||$)/s', $record['raw_data'], $matches)) {
                            $taf = trim($matches[1]);
                        }
                    }
                    
                    if (!empty($taf)): 
                        // Parse TAF: OIYY:110800Z 1109/1118 12008KT 7000 NSC ...;OIMM:110530Z ...
                        $tafRows = [];
                        $tafEntries = explode(';', $taf);
                        foreach ($tafEntries as $entry) {
                            $entry = trim($entry);
                            if (!empty($entry) && $entry !== ':') {
                                // Extract airport code (first 4 characters before :)
                                if (preg_match('/^([A-Z0-9]{4}):(.+)$/', $entry, $matches)) {
                                    $tafRows[] = [
                                        'airport' => $matches[1],
                                        'forecast' => $matches[2]
                                    ];
                                } else {
                                    // If no airport code, just add the entry
                                    $tafRows[] = [
                                        'airport' => '',
                                        'forecast' => $entry
                                    ];
                                }
                            }
                        }
                    ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">TAF (TERMINAL AERODROME FORECAST)</div>
                            <?php if (!empty($tafRows)): ?>
                                <table class="ofp-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 15%;">AIRPORT</th>
                                            <th style="width: 85%;">FORECAST</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tafRows as $row): ?>
                                        <tr>
                                            <td style="text-align: center; font-weight: bold;"><?php echo htmlspecialchars($row['airport']); ?></td>
                                            <td style="font-family: monospace; font-size: 14px; word-break: break-all;"><?php echo htmlspecialchars($row['forecast']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="ofp-info-box">
                                    <pre style="font-size: 14px; margin: 0; white-space: pre-wrap; font-family: monospace;"><?php echo htmlspecialchars($taf); ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Fuel Table (futbl)
                    if (isset($parsed_data['futbl']) && is_array($parsed_data['futbl']) && count($parsed_data['futbl']) > 0):
                    ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">FUEL TABLE</div>
                            <table class="ofp-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">PARAMETER</th>
                                        <th style="width: 30%;">TIME</th>
                                        <th style="width: 30%;">VALUE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($parsed_data['futbl'] as $row): 
                                        // Skip EZFW, ETOW, ELDW, and ELW
                                        $prm = $row['PRM'] ?? '';
                                        if (in_array($prm, ['EZFW', 'ETOW', 'ELDW', 'ELW'])) {
                                            continue;
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($prm); ?></td>
                                        <td style="text-align: center;"><?php echo htmlspecialchars(formatTime24h($row['TIM'] ?? '')); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars(formatValue($row['VAL'] ?? '')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td style="font-weight: bold;">Commander Extra</td>
                                        <td style="text-align: center; vertical-align: top;">
                                            <textarea name="reason" rows="3" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background-color: var(--ofp-info-bg); color: var(--ofp-text); font-size: 16px; resize: vertical; font-family: inherit;"></textarea>
                                        </td>
                                        <td style="text-align: right; vertical-align: top;">
                                            <textarea name="commander_extra_value" rows="3" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background-color: var(--ofp-info-bg); color: var(--ofp-text); font-size: 16px; resize: vertical; font-family: inherit;"></textarea>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Parse Altitude Drift (aldrf) from raw_data
                    // Extract data from aldrf:| to wtdrf:|
                    $aldrf_raw = '';
                    $aldrf_rows = [];
                    if (!empty($record['raw_data']) && preg_match('/aldrf:\|(.*?)(?=wtdrf:|$)/s', $record['raw_data'], $matches)) {
                        $aldrf_raw = trim($matches[1]);
                        
                        // Split by "/" to get rows (each "/" indicates a new row)
                        $row_strings = explode('/', $aldrf_raw);
                        
                        foreach ($row_strings as $row_str) {
                            $row_str = trim($row_str);
                            if (empty($row_str)) continue;
                            
                            // Remove leading "|" if present
                            $row_str = ltrim($row_str, '|');
                            $row_str = trim($row_str);
                            if (empty($row_str)) continue;
                            
                            // Split by multiple spaces (2 or more) to get columns
                            // Handle negative values like "- 0150" which should become "-0150"
                            $row_str = preg_replace('/-\s+(\d+)/', '-$1', $row_str);
                            
                            // Split by 2 or more spaces
                            $parts = preg_split('/\s{2,}/', $row_str);
                            
                            // If we don't have 7 parts, try splitting by single space
                            if (count($parts) < 7) {
                                $parts = preg_split('/\s+/', $row_str);
                            }
                            
                            // Clean up parts and ensure we have 7 columns
                            $parts = array_map('trim', $parts);
                            $parts = array_filter($parts, function($p) { return $p !== ''; });
                            $parts = array_values($parts);
                            
                            // If we have at least some data, create a row
                            if (count($parts) >= 1) {
                                $aldrf_rows[] = [
                                    'FL' => $parts[0] ?? '',
                                    'AVG_WIND' => $parts[1] ?? '',
                                    'DFUEL' => $parts[2] ?? '',
                                    'DT' => $parts[3] ?? '',
                                    'DSH' => $parts[4] ?? '',
                                    'MSH' => $parts[5] ?? '',
                                    'ISA_DEV' => $parts[6] ?? ''
                                ];
                            }
                        }
                    }
                    ?>
                    
                    <?php if (!empty($aldrf_rows)): ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">ALTITUDE DRIFT</div>
                            <div class="ofp-info-box">
                                <table class="ofp-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 14%;">FL</th>
                                            <th style="width: 14%;">AVG. Wind</th>
                                            <th style="width: 14%;">ΔFuel</th>
                                            <th style="width: 14%;">ΔT</th>
                                            <th style="width: 14%;">ΔSH</th>
                                            <th style="width: 15%;">M.SH</th>
                                            <th style="width: 15%;">ISA.DEV</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_rows = count($aldrf_rows);
                                        $row_index = 0;
                                        foreach ($aldrf_rows as $row): 
                                            $row_index++;
                                            $is_last = ($row_index === $total_rows);
                                            $bold_style = $is_last ? 'font-weight: bold;' : '';
                                            $bg_style = $is_last ? 'background-color: var(--ofp-highlight-bg);' : '';
                                        ?>
                                        <tr style="<?php echo $bg_style; ?>">
                                            <td style="text-align: center; <?php echo $bold_style; ?>"><?php echo htmlspecialchars($row['FL']); ?></td>
                                            <td style="text-align: center; <?php echo $bold_style; ?>"><?php echo htmlspecialchars($row['AVG_WIND']); ?></td>
                                            <td style="text-align: center; <?php echo $bold_style; ?>"><?php echo htmlspecialchars($row['DFUEL']); ?></td>
                                            <td style="text-align: center; <?php echo $bold_style; ?>"><?php echo htmlspecialchars($row['DT']); ?></td>
                                            <td style="text-align: center; <?php echo $bold_style; ?>"><?php echo htmlspecialchars($row['DSH']); ?></td>
                                            <td style="text-align: center; <?php echo $bold_style; ?>"><?php echo htmlspecialchars($row['MSH']); ?></td>
                                            <td style="text-align: center; <?php echo $bold_style; ?>"><?php echo htmlspecialchars($row['ISA_DEV']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php elseif (!empty($aldrf_raw)): ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">ALTITUDE DRIFT</div>
                            <div class="ofp-info-box">
                                <pre style="font-size: 14px; margin: 0; white-space: pre-wrap; color: var(--ofp-text);"><?php echo htmlspecialchars($aldrf_raw); ?></pre>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Function to render point-to-point table
                    function renderPointToPointTable($data, $table_name, $description, $field_labels, $table_descriptions) {
                        if (!isset($data[$table_name]) || !is_array($data[$table_name]) || count($data[$table_name]) === 0) {
                            return '';
                        }
                        
                        $rows = $data[$table_name];
                        $desc = $table_descriptions[$table_name] ?? $table_name;
                        
                        $html = '<div class="ofp-section">';
                        $html .= '<div class="ofp-section-title">' . htmlspecialchars($desc) . '</div>';
                        $html .= '<table class="ofp-table">';
                        $html .= '<thead><tr>';
                        $html .= '<th style="width: 8%; font-size: 13px;">WAYPOINT</th>';
                        $html .= '<th style="width: 12%; font-size: 13px;">COORDINATES</th>';
                        $html .= '<th style="width: 8%; font-size: 13px;">AIRWAY</th>';
                        $html .= '<th style="width: 6%; font-size: 13px;">FL / TROP</th>';
                        $html .= '<th style="width: 5%; font-size: 13px;">MEA</th>';
                        $html .= '<th style="width: 5%; font-size: 13px;">GMR</th>';
                        $html .= '<th style="width: 5%; font-size: 13px;">DIST</th>';
                        $html .= '<th style="width: 5%; font-size: 13px;">TOTAL</th>';
                        $html .= '<th style="width: 6%; font-size: 13px;">WD / WS</th>';
                        $html .= '<th style="width: 6%; font-size: 13px;">TRK / HDG</th>';
                        $html .= '<th style="width: 6%; font-size: 13px;">SAT / ISA</th>';
                        $html .= '<th style="width: 5%; font-size: 13px;">TIME</th>';
                        $html .= '<th style="width: 5%; font-size: 13px;">TOTAL</th>';
                        $html .= '<th colspan="2" style="width: 100px; font-size: 13px;">ETA/ATA</th>';
                        $html .= '<th style="width: 6%; font-size: 13px;">REMAIN</th>';
                        $html .= '<th style="width: 6%; font-size: 13px;">USED</th>';
                        $html .= '<th style="width: 6%; font-size: 13px;">TAS</th>';
                        $html .= '<th style="width: 6%; font-size: 13px;">GSP</th>';
                        $html .= '<th colspan="2" style="width: 100px; font-size: 13px;">ACTUAL REM / USD</th>';
                        $html .= '</tr></thead>';
                        $html .= '<tbody>';
                        
                        foreach ($rows as $row) {
                            // Get ALT (FL / TROP) value
                            $alt_value = $row['ALT'] ?? '';
                            
                            // Get TMP (SAT / ISA) value and extract last part after "/"
                            $tmp_value = $row['TMP'] ?? '';
                            $trop_value = '';
                            
                            if (!empty($tmp_value)) {
                                // Find the last "/" in the string
                                $last_slash_pos = strrpos($tmp_value, '/');
                                if ($last_slash_pos !== false) {
                                    // Get the part after the last "/"
                                    $after_last_slash = substr($tmp_value, $last_slash_pos + 1);
                                    // Remove any whitespace
                                    $after_last_slash = trim($after_last_slash);
                                    // Extract first 3 digits (numeric characters only)
                                    if (preg_match('/\d+/', $after_last_slash, $matches)) {
                                        // Get the first sequence of digits and take first 3
                                        $trop_value = substr($matches[0], 0, 3);
                                    } else {
                                        // If no digits found, take first 3 characters
                                        $trop_value = substr($after_last_slash, 0, 3);
                                    }
                                }
                            }
                            
                            // Combine ALT with "/" + trop_value
                            // Always add "/" + trop_value if trop_value is found (including "0")
                            $fl_trop_display = $alt_value;
                            if ($trop_value !== '') {
                                // Add "/" + trop_value (even if it's "0")
                                $fl_trop_display = $alt_value . '/' . $trop_value;
                            }
                            
                            $html .= '<tr>';
                            $html .= '<td style="text-align: center; font-weight: bold; font-size: 13px;">' . htmlspecialchars($row['WAP'] ?? '') . '</td>';
                            $html .= '<td style="text-align: center; font-size: 13px;">' . htmlspecialchars($row['GEO'] ?? '') . '</td>';
                            $html .= '<td style="text-align: center; font-size: 13px;">' . htmlspecialchars($row['VIA'] ?? '') . '</td>';
                            $html .= '<td style="text-align: center; font-size: 13px;">' . htmlspecialchars($fl_trop_display) . '</td>';
                            $html .= '<td style="text-align: center; font-size: 13px;">' . htmlspecialchars($row['MEA'] ?? '') . '</td>';
                            $html .= '<td style="text-align: center; font-size: 13px;">' . htmlspecialchars($row['GMR'] ?? '') . '</td>';
                            $html .= '<td style="text-align: right; font-size: 13px;">' . htmlspecialchars(formatDistance($row['DIS'] ?? '')) . '</td>';
                            $html .= '<td style="text-align: right; font-size: 13px;">' . htmlspecialchars(formatDistance($row['TDS'] ?? '')) . '</td>';
                            $html .= '<td style="text-align: center; font-size: 13px;">' . htmlspecialchars($row['WID'] ?? '') . '</td>';
                            $html .= '<td style="text-align: center; font-size: 13px;">' . htmlspecialchars($row['TRK'] ?? '') . '</td>';
                            // Hide part after last "/" in SAT / ISA column
                            $tmp_display = $row['TMP'] ?? '';
                            if (!empty($tmp_display)) {
                                $last_slash_pos = strrpos($tmp_display, '/');
                                if ($last_slash_pos !== false) {
                                    $tmp_display = substr($tmp_display, 0, $last_slash_pos);
                                }
                            }
                            $html .= '<td style="text-align: center; font-size: 13px;">' . htmlspecialchars($tmp_display) . '</td>';
                            $html .= '<td style="text-align: center; font-size: 13px;">' . htmlspecialchars(formatTimeForTable($row['TME'] ?? '')) . '</td>';
                            $html .= '<td style="text-align: center; font-size: 13px;">' . htmlspecialchars(formatTimeForTable($row['TTM'] ?? '')) . '</td>';
                            // ETA/ATA - two input fields side by side
                            $html .= '<td style="text-align: center; padding: 2px; white-space: nowrap;"><input type="text" name="eta_' . htmlspecialchars($row['WAP'] ?? '') . '" maxlength="4" style="width: 45px; padding: 2px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" /></td>';
                            $html .= '<td style="text-align: center; padding: 2px; white-space: nowrap;"><input type="text" name="ata_' . htmlspecialchars($row['WAP'] ?? '') . '" maxlength="4" style="width: 45px; padding: 2px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" /></td>';
                            $html .= '<td style="text-align: right; font-size: 13px;">' . htmlspecialchars(formatValue($row['FRE'] ?? '')) . '</td>';
                            $html .= '<td style="text-align: right; font-size: 13px;">' . htmlspecialchars(formatValue($row['FUS'] ?? '')) . '</td>';
                            $html .= '<td style="text-align: right; font-size: 13px;">' . htmlspecialchars(formatValue($row['TAS'] ?? '')) . '</td>';
                            $html .= '<td style="text-align: right; font-size: 13px;">' . htmlspecialchars(formatValue($row['GSP'] ?? '')) . '</td>';
                            // ACTUAL REM / USD - two input fields side by side
                            $html .= '<td style="text-align: center; padding: 2px; white-space: nowrap;"><input type="text" name="actual_rem_' . htmlspecialchars($row['WAP'] ?? '') . '" maxlength="4" style="width: 45px; padding: 2px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" /></td>';
                            $html .= '<td style="text-align: center; padding: 2px; white-space: nowrap;"><input type="text" name="actual_usd_' . htmlspecialchars($row['WAP'] ?? '') . '" maxlength="4" style="width: 45px; padding: 2px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" /></td>';
                            $html .= '</tr>';
                        }
                        
                        $html .= '</tbody></table></div>';
                        return $html;
                    }
                    
                    // Parse Weight Drift (wtdrf) from raw_data
                    // Extract data from wtdrf:| to wdtmp
                    $wtdrf_raw = '';
                    $wtdrf_rows = [];
                    if (!empty($record['raw_data']) && preg_match('/wtdrf:\|(.*?)(?=wdtmp:|$)/s', $record['raw_data'], $matches)) {
                        $wtdrf_raw = trim($matches[1]);
                        
                        // Remove trailing || if present
                        $wtdrf_raw = rtrim($wtdrf_raw, '|');
                        $wtdrf_raw = trim($wtdrf_raw);
                        
                        if (!empty($wtdrf_raw)) {
                            // Split by multiple spaces (2 or more) to get pairs
                            // Pattern: weight (with optional sign) followed by fuel (with optional sign)
                            // Example: "- 2    0008    2   - 0002"
                            
                            // First, normalize spaces and handle negative signs
                            $wtdrf_raw = preg_replace('/-\s+(\d+)/', '-$1', $wtdrf_raw); // "- 2" -> "-2"
                            $wtdrf_raw = preg_replace('/\s{2,}/', ' ', $wtdrf_raw); // Multiple spaces to single space
                            
                            // Split by single space to get all values
                            $values = preg_split('/\s+/', trim($wtdrf_raw));
                            
                            // Process values in pairs (weight, fuel)
                            for ($i = 0; $i < count($values); $i += 2) {
                                if (isset($values[$i]) && isset($values[$i + 1])) {
                                    $weight = trim($values[$i]);
                                    $fuel = trim($values[$i + 1]);
                                    
                                    // Format weight: add sign if missing
                                    if (!preg_match('/^[+-]/', $weight) && $weight !== '') {
                                        // If no sign, assume positive (but don't add +)
                                        $weight = $weight;
                                    }
                                    
                                    // Format fuel: add sign if missing
                                    if (!preg_match('/^[+-]/', $fuel) && $fuel !== '') {
                                        // If no sign, assume positive (but don't add +)
                                        $fuel = $fuel;
                                    }
                                    
                                    if ($weight !== '' || $fuel !== '') {
                                        $wtdrf_rows[] = [
                                            'weight' => $weight,
                                            'fuel' => $fuel
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    ?>
                    
                    <?php if (!empty($wtdrf_rows)): ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">WEIGHT DRIFT</div>
                            <div class="ofp-info-box">
                                <table class="ofp-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50%;">ΔWeight (t)</th>
                                            <th style="width: 50%;">ΔTrip Fuel (lbs)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($wtdrf_rows as $row): ?>
                                        <tr>
                                            <td style="text-align: center;"><?php echo htmlspecialchars($row['weight']); ?></td>
                                            <td style="text-align: center;"><?php echo htmlspecialchars($row['fuel']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- ATIS, Taxi, and Take-Off Information Section -->
                    <div class="ofp-section">
                        <div class="ofp-section-title">ATIS, TAXI & TAKE-OFF INFORMATION</div>
                        <div class="ofp-info-box" style="padding: 0;">
                            <!-- Outer frame with dashed border -->
                            <div style="border: 2px dashed var(--ofp-border);">
                                <!-- Main row: ATIS table + Takeoff box -->
                                <div style="display: flex; align-items: stretch;">
                                    <!-- Left: ATIS table -->
                                    <div style="flex: 1; border-right: 2px solid var(--ofp-border); display: flex; flex-direction: column;">
                                        <div style="display: flex; align-items: stretch; flex: 1;">
                                            <!-- ATIS vertical strip -->
                                            <div style="width: 40px; border-right: 2px solid var(--ofp-border); display: flex; align-items: center; justify-content: center; align-self: stretch;">
                                                <div style="writing-mode: vertical-rl; text-orientation: mixed; letter-spacing: 0.2rem; font-weight: bold; font-size: 18px; color: var(--ofp-text);">A T I S</div>
                                            </div>
                                            
                                            <!-- Airports rows -->
                                            <div style="flex: 1; display: grid; grid-template-rows: repeat(2, 1fr); min-height: calc(40px + 5 * 2.25rem + 2.25rem + 28px);">
                                                <!-- Row 1 & 2: OIMM (merged) -->
                                                <div style="display: flex; border-bottom: 2px solid var(--ofp-border); grid-row: span 2;">
                                                    <!-- Station cell (spans both rows) -->
                                                    <div style="width: 112px; border-right: 2px solid var(--ofp-border); padding: 8px; display: flex; flex-direction: column; justify-content: center;">
                                                        <div style="font-weight: 900; letter-spacing: 0.05em; font-size: 16px; color: var(--ofp-text);"><?php echo htmlspecialchars($oimm_code); ?></div>
                                                        <div style="font-size: 16px; font-weight: 600; color: #1e40af; margin-top: 2px;">ELEV: <?php echo htmlspecialchars($oimm_elev); ?></div>
                                                    </div>
                                                    <!-- QNH inputs container -->
                                                    <div style="flex: 1; display: flex; flex-direction: column;">
                                                        <!-- First QNH input -->
                                                        <div style="flex: 1; position: relative; border-bottom: 1px solid var(--ofp-border); border-right: 2px solid var(--ofp-border);">
                                                            <div style=""></div>
                                                            <input type="text" name="atis_oimm_qnh_1" style="position: absolute; left: 8px; right: 128px; top: 50%; transform: translateY(-50%); height: 24px; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace;" />
                                                            <div style="position: absolute; right: 64px; top: 0; bottom: 0; width: 64px; border-left: 2px solid var(--ofp-border); display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold; color: var(--ofp-text);">QNH</div>
                                                            <div style="position: absolute; right: 0; top: 0; bottom: 0; width: 64px; border-left: 2px solid var(--ofp-border); display: flex; align-items: center; justify-content: center;">
                                                                <input type="number" name="atis_oimm_qnh_1_value" style="width: 100%; height: 100%; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace; text-align: center;" />
                                                            </div>
                                                        </div>
                                                        <!-- Second QNH input -->
                                                        <div style="flex: 1; position: relative; border-right: 2px solid var(--ofp-border);">
                                                            <div style=""></div>
                                                            <input type="text" name="atis_oimm_qnh_2" style="position: absolute; left: 8px; right: 128px; top: 50%; transform: translateY(-50%); height: 24px; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace;" />
                                                            <div style="position: absolute; right: 64px; top: 0; bottom: 0; width: 64px; border-left: 2px solid var(--ofp-border); display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold; color: var(--ofp-text);">QNH</div>
                                                            <div style="position: absolute; right: 0; top: 0; bottom: 0; width: 64px; border-left: 2px solid var(--ofp-border); display: flex; align-items: center; justify-content: center;">
                                                                <input type="number" name="atis_oimm_qnh_2_value" style="width: 100%; height: 100%; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace; text-align: center;" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Row 3: OIGG -->
                                                <div style="display: flex;">
                                                    <div style="width: 112px; border-right: 2px solid var(--ofp-border); padding: 8px;">
                                                        <div style="font-weight: 900; letter-spacing: 0.05em; font-size: 16px; color: var(--ofp-text);"><?php echo htmlspecialchars($oigg_code); ?></div>
                                                        <div style="font-size: 16px; font-weight: 600; color: #1e40af; margin-top: 2px;">ELEV: <?php echo htmlspecialchars($oigg_elev); ?></div>
                                                    </div>
                                                    <div style="flex: 1; position: relative; border-right: 2px solid var(--ofp-border);">
                                                        <div style=""></div>
                                                        <input type="text" name="atis_oigg_qnh" style="position: absolute; left: 8px; right: 128px; top: 50%; transform: translateY(-50%); height: 24px; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace;" />
                                                        <div style="position: absolute; right: 64px; top: 0; bottom: 0; width: 64px; border-left: 2px solid var(--ofp-border); display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold; color: var(--ofp-text);">QNH</div>
                                                        <div style="position: absolute; right: 0; top: 0; bottom: 0; width: 64px; border-left: 2px solid var(--ofp-border); display: flex; align-items: center; justify-content: center;">
                                                            <input type="number" name="atis_oigg_qnh_value" style="width: 100%; height: 100%; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace; text-align: center;" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Right: TAKE-OFF table -->
                                    <div style="width: 224px; display: flex; flex-direction: column;">
                                        <div style="display: grid; grid-template-rows: auto repeat(5, 2.25rem) auto; flex: 1;">
                                            <!-- Header -->
                                            <div style="border-bottom: 2px solid var(--ofp-border); height: 40px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; color: var(--ofp-text);">TAKE-OFF</div>
                                            
                                            <!-- V1 -->
                                            <div style="border-bottom: 2px solid var(--ofp-border); display: grid; grid-template-columns: repeat(3, 1fr);">
                                                <div style="grid-column: span 1; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 16px; color: var(--ofp-text);">V1</div>
                                                <div style="grid-column: span 2; border-left: 2px solid var(--ofp-border);">
                                                    <input type="text" name="takeoff_v1" style="width: 100%; height: 100%; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace;" />
                                                </div>
                                            </div>
                                            
                                            <!-- VR -->
                                            <div style="border-bottom: 2px solid var(--ofp-border); display: grid; grid-template-columns: repeat(3, 1fr);">
                                                <div style="grid-column: span 1; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 16px; color: var(--ofp-text);">VR</div>
                                                <div style="grid-column: span 2; border-left: 2px solid var(--ofp-border);">
                                                    <input type="text" name="takeoff_vr" style="width: 100%; height: 100%; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace;" />
                                                </div>
                                            </div>
                                            
                                            <!-- V2 -->
                                            <div style="border-bottom: 2px solid var(--ofp-border); display: grid; grid-template-columns: repeat(3, 1fr);">
                                                <div style="grid-column: span 1; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 16px; color: var(--ofp-text);">V2</div>
                                                <div style="grid-column: span 2; border-left: 2px solid var(--ofp-border);">
                                                    <input type="text" name="takeoff_v2" style="width: 100%; height: 100%; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace;" />
                                                </div>
                                            </div>
                                            
                                            <!-- Ref Tmp -->
                                            <div style="border-bottom: 2px solid var(--ofp-border); display: grid; grid-template-columns: repeat(3, 1fr);">
                                                <div style="grid-column: span 1; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 600; color: var(--ofp-text);">Ref.Tmp</div>
                                                <div style="grid-column: span 2; border-left: 2px solid var(--ofp-border);">
                                                    <input type="text" name="takeoff_ref_tmp" style="width: 100%; height: 100%; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace;" />
                                                </div>
                                            </div>
                                            
                                            <!-- Flaps -->
                                            <div style="border-bottom: 2px solid var(--ofp-border); display: grid; grid-template-columns: repeat(3, 1fr);">
                                                <div style="grid-column: span 1; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 16px; color: var(--ofp-text);">FLAPS</div>
                                                <div style="grid-column: span 2; border-left: 2px solid var(--ofp-border);">
                                                    <input type="text" name="takeoff_flaps" style="width: 100%; height: 100%; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace;" />
                                                </div>
                                            </div>
                                            
                                            <!-- RWY / COND -->
                                            <div style="border-bottom: 2px solid var(--ofp-border); display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 600; color: var(--ofp-text); height: 2.25rem;">
                                                RWY / COND
                                            </div>
                                            
                                            <!-- Bottom blanks line -->
                                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); border-top: 2px solid var(--ofp-border); height: 28px; font-size: 16px; font-family: ui-monospace, monospace; color: var(--ofp-text);">
                                                <div style="display: flex; align-items: center; justify-content: center;">
                                                    <input type="text" name="takeoff_rwy" placeholder="......" style="width: 100%; height: 100%; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace; text-align: center;" />
                                                </div>
                                                <div style="display: flex; align-items: center; justify-content: center; font-weight: bold;">/</div>
                                                <div style="display: flex; align-items: center; justify-content: center;">
                                                    <input type="text" name="takeoff_cond" placeholder="......" style="width: 100%; height: 100%; padding: 0 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace; text-align: center;" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div> <!-- /Main row -->
                                
                                <!-- Taxi section (separate from ATIS) -->
                                <div style="border-top: 2px solid var(--ofp-border);">
                                    <div style="display: grid; grid-template-columns: repeat(3, 1fr);">
                                        <!-- TAXI OUT -->
                                        <div style="border-right: 2px solid var(--ofp-border); height: 36px; padding: 0 8px; display: flex; align-items: center; gap: 8px; font-weight: bold; font-size: 18px; color: var(--ofp-text);">
                                            <span>TAXI OUT</span>
                                            <input type="text" name="taxi_out" placeholder="......" style="flex: 1; padding: 2px 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace; text-align: left;" />
                                        </div>
                                        
                                        
                                        
                                        <!-- TAXI IN -->
                                        <div style="border-right: 2px solid var(--ofp-border); height: 36px; padding: 0 8px; display: flex; align-items: center; gap: 8px; font-weight: bold; font-size: 18px; color: var(--ofp-text);">
                                            <span>TAXI IN</span>
                                            <input type="text" name="taxi_in" placeholder="......" style="flex: 1; padding: 2px 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace; text-align: left;" />
                                        </div>
                                        
                                        <!-- GATE (TAXI IN) -->
                                        <div class="h-9 flex items-center" style="font-family: ui-monospace, monospace; font-size: 16px; letter-spacing: 0.1em; color: var(--ofp-text);">
                                            <span class="text-right ml-6">GATE</span>
                                            <input type="text" name="taxi_in_gate" placeholder="......" style="flex: 1; padding: 2px 4px; border: 1px solid var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: ui-monospace, monospace; text-align: left;" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- OFP Approval and Signature Section -->
                    <div class="ofp-section">
                        <div class="ofp-info-box" style="padding: 0;">
                            <!-- Header with gray background -->
                            <div style="background-color: var(--ofp-header-bg); padding: 8px; border: 2px dashed var(--ofp-border); border-bottom: none;">
                                <div style="font-weight: bold; font-size: 16px; text-align: center; color: var(--ofp-text);">
                                    OFP VALID FOR SIX HOURS AFTER STD AND APPROVED BY:
                                </div>
                            </div>
                            
                            <!-- Main content area with dashed border -->
                            <div style="border: 2px dashed var(--ofp-border); display: flex;">
                                <!-- Left Column: Crew Member Information -->
                                <div style="flex: 1; border-right: 2px dashed var(--ofp-border); padding: 10px;">
                                    <!-- CM 1 Row -->
                                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                        <div style="flex: 1; font-size: 16px; color: var(--ofp-text);">
                                            CM 1 <?php echo htmlspecialchars($cm1_raw ?: 'N/A'); ?>
                                        </div>
                                        <div style="display: flex; gap: 10px; margin-left: 10px;">
                                            <div style="display: flex; align-items: center; gap: 4px;">
                                                <div style="width: 20px; height: 20px; border: 1px dashed var(--ofp-border); display: flex; align-items: center; justify-content: center; font-size: 14px; color: var(--ofp-text);">PF</div>
                                                <input type="checkbox" name="cm1_pf" style="width: 16px; height: 16px;" />
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 4px;">
                                                <div style="width: 20px; height: 20px; border: 1px dashed var(--ofp-border); display: flex; align-items: center; justify-content: center; font-size: 14px; color: var(--ofp-text);">PM</div>
                                                <input type="checkbox" name="cm1_pm" style="width: 16px; height: 16px;" />
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- CM 2 Row -->
                                    <div style="display: flex; align-items: center;">
                                        <div style="flex: 1; font-size: 16px; color: var(--ofp-text);">
                                            CM 2 <?php echo htmlspecialchars($cm2_raw ?: 'N/A'); ?>
                                        </div>
                                        <div style="display: flex; gap: 10px; margin-left: 10px;">
                                            <div style="display: flex; align-items: center; gap: 4px;">
                                                <div style="width: 20px; height: 20px; border: 1px dashed var(--ofp-border); display: flex; align-items: center; justify-content: center; font-size: 14px; color: var(--ofp-text);">PF</div>
                                                <input type="checkbox" name="cm2_pf" style="width: 16px; height: 16px;" />
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 4px;">
                                                <div style="width: 20px; height: 20px; border: 1px dashed var(--ofp-border); display: flex; align-items: center; justify-content: center; font-size: 14px; color: var(--ofp-text);">PM</div>
                                                <input type="checkbox" name="cm2_pm" style="width: 16px; height: 16px;" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Middle Column: Signature Information -->
                                <div style="flex: 1; border-right: 2px dashed var(--ofp-border); padding: 10px;">
                                    <!-- Commander's Signature -->
                                    <div style="margin-bottom: 15px;">
                                        <div style="font-size: 16px; font-weight: bold; margin-bottom: 5px; color: var(--ofp-text);">Commander's Signature</div>
                                        <textarea name="commander_signature" rows="2" style="width: 100%; height: 40px; border: 1px dashed var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: inherit; padding: 4px; resize: none;"></textarea>
                                        <div style="font-size: 14px; color: var(--ofp-text); margin-top: 4px;"><?php echo htmlspecialchars($commander ?: ''); ?></div>
                                    </div>
                                    
                                    <!-- Dispatcher's Signature -->
                                    <div>
                                        <div style="font-size: 16px; font-weight: bold; margin-bottom: 5px; color: var(--ofp-text);">Dispatcher's Signature</div>
                                        <textarea name="dispatcher_signature" rows="2" style="width: 100%; height: 40px; border: 1px dashed var(--ofp-border); background: transparent; color: var(--ofp-text); font-size: 16px; font-family: inherit; padding: 4px; resize: none;"></textarea>
                                        <div style="font-size: 14px; color: var(--ofp-text); margin-top: 4px;"><?php echo htmlspecialchars($dsp_raw ?: ($cabin_senior ?: '')); ?></div>
                                    </div>
                                </div>
                                
                                <!-- Right Column: Notes and Units Information -->
                                <div style="flex: 1; padding: 10px;">
                                    <div style="font-size: 16px; color: var(--ofp-text); line-height: 1.6;">
                                        <div style="margin-bottom: 8px;">- OFP HAS BEEN PREPARED IN ACCORDANCE</div>
                                        <div style="margin-bottom: 8px;">- WITH COMPANY OPERATION POLICY.</div>
                                        <div style="margin-bottom: 8px;">- MAXIMUM FORECAST IS 36 HOURS AFTER WX</div>
                                        <div style="margin-bottom: 8px;">- DATA UPDATE TIME.</div>
                                        <div style="font-weight: bold; margin-top: 12px; margin-bottom: 8px;">INTERNATIONAL SYSTEM OF UNITS :</div>
                                        <div style="margin-bottom: 4px;">- WIND DIRECTION IN DEGREES</div>
                                        <div style="margin-bottom: 4px;">- WIND SPEED IN KNOTS</div>
                                        <div style="margin-bottom: 4px;">- TEMPERATURE IN CENTIGRADE</div>
                                        <div style="margin-bottom: 4px;">- DISTANCE IN MILES</div>
                                        <div style="margin-bottom: 4px;">- ALTITUDE IN FEETS</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Flight Times Table -->
                    <div class="ofp-section">
                        <div class="ofp-info-box" style="padding: 0;">
                            <table style="width: 100%; border-collapse: collapse; border: 2px solid var(--ofp-border); table-layout: fixed;">
                                <colgroup>
                                    <col style="width: 8.33%;">
                                    <col style="width: 8.33%;">
                                    <col style="width: 8.33%;">
                                    <col style="width: 8.33%;">
                                    <col style="width: 8.33%;">
                                    <col style="width: 8.33%;">
                                    <col style="width: 8.33%;">
                                    <col style="width: 8.33%;">
                                    <col style="width: 8.33%;">
                                    <col style="width: 8.33%;">
                                    <col style="width: 8.33%;">
                                    <col style="width: 8.33%;">
                                </colgroup>
                                <tbody>
                                    <tr>
                                        <!-- OFF BLOCK -->
                                        <td style="border-right: 2px solid var(--ofp-border); padding: 12px; text-align: center; vertical-align: middle; background-color: var(--ofp-header-bg);">
                                            <div style="font-weight: bold; font-size: 16px; color: var(--ofp-text); line-height: 1.2;">
                                                OFF<br>BLOCK
                                            </div>
                                        </td>
                                        <td style="border-right: 2px solid var(--ofp-border); padding: 12px; text-align: center; vertical-align: middle; position: relative;">
                                            <input type="text" name="off_block_time" value="<?php echo htmlspecialchars($block_off_time); ?>" placeholder="HH:MM" maxlength="5" style="width: 100%; height: 100%; border: none; background: transparent; color: var(--ofp-text); font-size: 18px; font-family: ui-monospace, monospace; text-align: center; outline: none;" />
                                            <div style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); pointer-events: none; width: 80%; border-top: 2px dotted var(--ofp-border); z-index: -1;">
                                                <span style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); background: var(--ofp-info-bg); padding: 0 4px; color: var(--ofp-text); font-size: 18px;">:</span>
                                            </div>
                                        </td>
                                        
                                        <!-- TAKE OFF -->
                                        <td style="border-right: 2px solid var(--ofp-border); padding: 12px; text-align: center; vertical-align: middle; background-color: var(--ofp-header-bg);">
                                            <div style="font-weight: bold; font-size: 16px; color: var(--ofp-text); line-height: 1.2;">
                                                TAKE<br>OFF
                                            </div>
                                        </td>
                                        <td style="border-right: 2px solid var(--ofp-border); padding: 12px; text-align: center; vertical-align: middle; position: relative;">
                                            <input type="text" name="take_off_time" value="<?php echo htmlspecialchars($take_off_time); ?>" placeholder="HH:MM" maxlength="5" style="width: 100%; height: 100%; border: none; background: transparent; color: var(--ofp-text); font-size: 18px; font-family: ui-monospace, monospace; text-align: center; outline: none;" />
                                            <div style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); pointer-events: none; width: 80%; border-top: 2px dotted var(--ofp-border); z-index: -1;">
                                                <span style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); background: var(--ofp-info-bg); padding: 0 4px; color: var(--ofp-text); font-size: 18px;">:</span>
                                            </div>
                                        </td>
                                        
                                        <!-- LAND -->
                                        <td style="border-right: 2px solid var(--ofp-border); padding: 12px; text-align: center; vertical-align: middle; background-color: var(--ofp-header-bg);">
                                            <div style="font-weight: bold; font-size: 16px; color: var(--ofp-text);">
                                                LAND
                                            </div>
                                        </td>
                                        <td style="border-right: 2px solid var(--ofp-border); padding: 12px; text-align: center; vertical-align: middle; position: relative;">
                                            <input type="text" name="landing_time" value="<?php echo htmlspecialchars($landing_time); ?>" placeholder="HH:MM" maxlength="5" style="width: 100%; height: 100%; border: none; background: transparent; color: var(--ofp-text); font-size: 18px; font-family: ui-monospace, monospace; text-align: center; outline: none;" />
                                            <div style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); pointer-events: none; width: 80%; border-top: 2px dotted var(--ofp-border); z-index: -1;">
                                                <span style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); background: var(--ofp-info-bg); padding: 0 4px; color: var(--ofp-text); font-size: 18px;">:</span>
                                            </div>
                                        </td>
                                        
                                        <!-- ON BLOCK -->
                                        <td style="border-right: 2px solid var(--ofp-border); padding: 12px; text-align: center; vertical-align: middle; background-color: var(--ofp-header-bg);">
                                            <div style="font-weight: bold; font-size: 16px; color: var(--ofp-text); line-height: 1.2;">
                                                ON<br>BLOCK
                                            </div>
                                        </td>
                                        <td style="border-right: 2px solid var(--ofp-border); padding: 12px; text-align: center; vertical-align: middle; position: relative;">
                                            <input type="text" name="on_block_time" value="<?php echo htmlspecialchars($block_on_time); ?>" placeholder="HH:MM" maxlength="5" style="width: 100%; height: 100%; border: none; background: transparent; color: var(--ofp-text); font-size: 18px; font-family: ui-monospace, monospace; text-align: center; outline: none;" />
                                            <div style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); pointer-events: none; width: 80%; border-top: 2px dotted var(--ofp-border); z-index: -1;">
                                                <span style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); background: var(--ofp-info-bg); padding: 0 4px; color: var(--ofp-text); font-size: 18px;">:</span>
                                            </div>
                                        </td>
                                        
                                        <!-- FLIGHT TIME -->
                                        <td style="border-right: 2px solid var(--ofp-border); padding: 12px; text-align: center; vertical-align: middle; background-color: var(--ofp-header-bg);">
                                            <div style="font-weight: bold; font-size: 16px; color: var(--ofp-text); line-height: 1.2;">
                                                FLIGHT<br>TIME
                                            </div>
                                        </td>
                                        <td style="border-right: 2px solid var(--ofp-border); padding: 12px; text-align: center; vertical-align: middle; position: relative;">
                                            <input type="text" name="flight_time" value="<?php echo htmlspecialchars($flight_time); ?>" placeholder="HH:MM" maxlength="5" style="width: 100%; height: 100%; border: none; background: transparent; color: var(--ofp-text); font-size: 18px; font-family: ui-monospace, monospace; text-align: center; outline: none;" />
                                            <div style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); pointer-events: none; width: 80%; border-top: 2px dotted var(--ofp-border); z-index: -1;">
                                                <span style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); background: var(--ofp-info-bg); padding: 0 4px; color: var(--ofp-text); font-size: 18px;">:</span>
                                            </div>
                                        </td>
                                        
                                        <!-- BLOCK TIME -->
                                        <td style="border-right: none; padding: 12px; text-align: center; vertical-align: middle; background-color: var(--ofp-header-bg);">
                                            <div style="font-weight: bold; font-size: 16px; color: var(--ofp-text); line-height: 1.2;">
                                                BLOCK<br>TIME
                                            </div>
                                        </td>
                                        <td style="padding: 12px; text-align: center; vertical-align: middle; position: relative;">
                                            <input type="text" name="block_time" value="<?php echo htmlspecialchars($block_time); ?>" placeholder="HH:MM" maxlength="5" style="width: 100%; height: 100%; border: none; background: transparent; color: var(--ofp-text); font-size: 18px; font-family: ui-monospace, monospace; text-align: center; outline: none;" />
                                            <div style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); pointer-events: none; width: 80%; border-top: 2px dotted var(--ofp-border); z-index: -1;">
                                                <span style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); background: var(--ofp-info-bg); padding: 0 4px; color: var(--ofp-text); font-size: 18px;">:</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php
                    // Primary Point-to-Point (mpln)
                    echo renderPointToPointTable($parsed_data, 'mpln', 'PRIMARY ROUTE PLAN', $field_labels, $table_descriptions);
                    
                    // Minimum Diversion Fuel Section (before 1st Alternate Point-to-Point)
                    if (!empty($moda) || !empty($modb)): ?>
                    <div class="ofp-section">
                        <div class="ofp-section-title">MINIMUM DIVERSION FUEL</div>
                        <div class="ofp-info-box">
                            <table class="ofp-table">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%;">
                                            <?php 
                                            if (!empty($moda)) {
                                                // Parse MODA: [OINZ, 3027] -> make OINZ and 3027 bold
                                                if (preg_match('/\[([^,]+),\s*([^\]]+)\]/', $moda, $matches)) {
                                                    $moda_part1 = trim($matches[1]);
                                                    $moda_part2 = trim($matches[2]);
                                                    echo '<strong>' . htmlspecialchars($moda_part1) . '</strong>, <strong>' . htmlspecialchars($moda_part2) . '</strong>';
                                                } else {
                                                    echo htmlspecialchars($moda);
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td style="width: 50%;">
                                            <?php 
                                            if (!empty($modb)) {
                                                // Parse MODB: [OIII, 2865] -> regular display
                                                if (preg_match('/\[([^,]+),\s*([^\]]+)\]/', $modb, $matches)) {
                                                    $modb_part1 = trim($matches[1]);
                                                    $modb_part2 = trim($matches[2]);
                                                    echo htmlspecialchars($modb_part1) . ', ' . htmlspecialchars($modb_part2);
                                                } else {
                                                    echo htmlspecialchars($modb);
                                                }
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif;
                    
                    // Wind Climb and Wind Descent side by side (before 1st Alternate Point-to-Point)
                    // Prepare Wind Climb data
                    $wdclbContent = '';
                    $wdclbRows = [];
                    if (isset($parsed_data['wdclb']) && is_array($parsed_data['wdclb']) && count($parsed_data['wdclb']) > 0) {
                        foreach ($parsed_data['wdclb'] as $item) {
                            if (is_array($item)) {
                                foreach ($item as $key => $value) {
                                    $wdclbContent .= $value;
                                }
                            } else {
                                $wdclbContent .= $item;
                            }
                        }
                        
                        if (!empty($wdclbContent)) {
                            preg_match_all('/FL(\d+):\s*(\d+)\/(\d+)/', $wdclbContent, $matches);
                            if (!empty($matches[1]) && !empty($matches[2]) && !empty($matches[3])) {
                                for ($i = 0; $i < count($matches[1]); $i++) {
                                    $wdclbRows[] = [
                                        'fl' => 'FL' . $matches[1][$i],
                                        'wind' => $matches[2][$i] . '/' . $matches[3][$i]
                                    ];
                                }
                            }
                        }
                    }
                    
                    // Prepare Wind Descent data
                    $wddesContent = '';
                    $wddesRows = [];
                    if (isset($parsed_data['wddes']) && is_array($parsed_data['wddes']) && count($parsed_data['wddes']) > 0) {
                        foreach ($parsed_data['wddes'] as $item) {
                            if (is_array($item)) {
                                foreach ($item as $key => $value) {
                                    $wddesContent .= $value;
                                }
                            } else {
                                $wddesContent .= $item;
                            }
                        }
                        
                        if (!empty($wddesContent)) {
                            preg_match_all('/FL(\d+):\s*(\d+)\/(\d+)/', $wddesContent, $matches);
                            if (!empty($matches[1]) && !empty($matches[2]) && !empty($matches[3])) {
                                for ($i = 0; $i < count($matches[1]); $i++) {
                                    $wddesRows[] = [
                                        'fl' => 'FL' . $matches[1][$i],
                                        'wind' => $matches[2][$i] . '/' . $matches[3][$i]
                                    ];
                                }
                            }
                        }
                    }
                    
                    // Display Wind Climb and Wind Descent side by side
                    if (!empty($wdclbRows) || !empty($wddesRows) || !empty($wdclbContent) || !empty($wddesContent)): ?>
                    <div class="ofp-section" style="display: flex; gap: 15px; align-items: flex-start;">
                        <?php if (!empty($wdclbRows) || !empty($wdclbContent)): ?>
                        <div style="flex: 1;">
                            <div class="ofp-section-title">WIND CLIMB</div>
                            <?php if (!empty($wdclbRows)): ?>
                                <table class="ofp-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50%;">FLIGHT LEVEL</th>
                                            <th style="width: 50%;">WIND / SPEED</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($wdclbRows as $row): ?>
                                        <tr>
                                            <td style="text-align: center; font-weight: bold;"><?php echo htmlspecialchars($row['fl']); ?></td>
                                            <td style="text-align: center;"><?php echo htmlspecialchars($row['wind']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="ofp-info-box">
                                    <pre style="font-size: 14px; margin: 0; white-space: pre-wrap;"><?php echo htmlspecialchars($wdclbContent); ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($wddesRows) || !empty($wddesContent)): ?>
                        <div style="flex: 1;">
                            <div class="ofp-section-title">WIND DESCENT</div>
                            <?php if (!empty($wddesRows)): ?>
                                <table class="ofp-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50%;">FLIGHT LEVEL</th>
                                            <th style="width: 50%;">WIND / SPEED</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($wddesRows as $row): ?>
                                        <tr>
                                            <td style="text-align: center; font-weight: bold;"><?php echo htmlspecialchars($row['fl']); ?></td>
                                            <td style="text-align: center;"><?php echo htmlspecialchars($row['wind']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="ofp-info-box">
                                    <pre style="font-size: 14px; margin: 0; white-space: pre-wrap;"><?php echo htmlspecialchars($wddesContent); ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif;
                    
                    // Winds/Temperatures Aloft Forecast Table (before 1st Alternate Point-to-Point)
                    $wdtmp_raw = '';
                    $wdtmp_legend = '';
                    $wdtmp_data = '';
                    $wdtmp_rows = [];
                    
                    if (!empty($record['raw_data']) && preg_match('/\|\|wdtmp:\|\s*(.*?)\|\|/s', $record['raw_data'], $matches)) {
                        $wdtmp_raw = trim($matches[1]);
                        
                        // Extract legend (before first |) and data (after first |)
                        // Format: "SBZ/ ODKOL/ ... |  FL260: 324/025 (-35) ..."
                        if (preg_match('/^(.+?)\|\s*(.+)$/s', $wdtmp_raw, $parts)) {
                            $wdtmp_legend = trim($parts[1]);
                            $wdtmp_data = trim($parts[2]);
                            
                            // Parse legend: "SBZ/ ODKOL/ PUSAL/ DNZ/ MODEK/ IMKER/ NSH/ ALKUP/"
                            $waypoints = [];
                            if (!empty($wdtmp_legend)) {
                                $waypoint_parts = preg_split('/\s*\/\s*/', trim($wdtmp_legend, '/'));
                                $waypoints = array_filter(array_map('trim', $waypoint_parts));
                                $waypoints = array_values($waypoints);
                            }
                            
                            // Parse data: "FL260: 324/025 (-35)  FL280: 321/026 (-40) ..."
                            if (!empty($wdtmp_data) && !empty($waypoints)) {
                                // Extract all FL data groups
                                preg_match_all('/FL(\d+):\s*(\d+)\/(\d+)\s*\((-?\d+)\)/', $wdtmp_data, $fl_matches, PREG_SET_ORDER);
                                
                                if (!empty($fl_matches)) {
                                    // Calculate how many FL entries per waypoint
                                    $fl_per_waypoint = count($fl_matches) / count($waypoints);
                                    
                                    // Group FL data by waypoint
                                    $waypoint_index = 0;
                                    $fl_index = 0;
                                    
                                    foreach ($waypoints as $waypoint) {
                                        $waypoint_rows = [];
                                        for ($i = 0; $i < $fl_per_waypoint && $fl_index < count($fl_matches); $i++) {
                                            $match = $fl_matches[$fl_index];
                                            $waypoint_rows[] = [
                                                'fl' => 'FL' . $match[1],
                                                'wind' => $match[2] . '/' . $match[3],
                                                'temp' => '(' . $match[4] . ')'
                                            ];
                                            $fl_index++;
                                        }
                                        $wdtmp_rows[] = [
                                            'waypoint' => $waypoint,
                                            'data' => $waypoint_rows
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    
                    // Display Winds/Temperatures Aloft Forecast Table
                    if (!empty($wdtmp_rows)): ?>
                    <div class="ofp-section">
                        <div class="ofp-section-title">WINDS/TEMPERATURES ALOFT FORECAST</div>
                        <div class="ofp-info-box">
                            <table class="ofp-table">
                                <thead>
                                    <tr>
                                        <th style="width: 10%;">FLIGHT LEVEL</th>
                                        <?php foreach ($wdtmp_rows as $row): ?>
                                        <th style="width: <?php echo 90 / count($wdtmp_rows); ?>%;"><?php echo htmlspecialchars($row['waypoint']); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Get max number of FL entries
                                    $max_fl_count = 0;
                                    foreach ($wdtmp_rows as $row) {
                                        $max_fl_count = max($max_fl_count, count($row['data']));
                                    }
                                    
                                    // Display rows
                                    for ($i = 0; $i < $max_fl_count; $i++): ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: bold;">
                                            <?php 
                                            // Get FL from first waypoint's data at this index
                                            if (isset($wdtmp_rows[0]['data'][$i]['fl'])) {
                                                echo htmlspecialchars($wdtmp_rows[0]['data'][$i]['fl']);
                                            }
                                            ?>
                                        </td>
                                        <?php foreach ($wdtmp_rows as $row): ?>
                                        <td style="text-align: center;">
                                            <?php 
                                            if (isset($row['data'][$i])) {
                                                echo htmlspecialchars($row['data'][$i]['wind'] . ' ' . $row['data'][$i]['temp']);
                                            } else {
                                                echo '&nbsp;';
                                            }
                                            ?>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- ENROUTE ATIS RECORDS & RVSM CHECK Section -->
                    <div class="ofp-section" style="display: flex; gap: 15px; align-items: flex-start;">
                        <!-- Left: ENROUTE ATIS RECORDS -->
                        <div style="flex: 1;">
                            <div class="ofp-section-title">ENROUTE ATIS RECORDS</div>
                            <div class="ofp-info-box" style="padding: 0;">
                                <table style="width: 100%; border-collapse: collapse; border: 2px solid var(--ofp-border);">
                                    <thead>
                                        
                                    </thead>
                                    <tbody>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <tr>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="atis_record_col1_<?php echo $i; ?>" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="atis_record_col2_<?php echo $i; ?>" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px;" />
                                            </td>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Right: RVSM CHECK -->
                        <div style="flex: 1;">
                            <div class="ofp-section-title">RVSM CHECK</div>
                            <div class="ofp-info-box" style="padding: 0;">
                                <table style="width: 100%; border-collapse: collapse; border: 2px solid var(--ofp-border);">
                                    <thead>
                                        <tr>
                                            <th style="width: 15%; border: 2px solid var(--ofp-border); padding: 8px; background-color: var(--ofp-header-bg); font-size: 13px;">FL</th>
                                            <th style="width: 25%; border: 2px solid var(--ofp-border); padding: 8px; background-color: var(--ofp-header-bg); font-size: 13px;">TIME</th>
                                            <th style="width: 20%; border: 2px solid var(--ofp-border); padding: 8px; background-color: var(--ofp-header-bg); font-size: 13px;">L ALT</th>
                                            <th style="width: 20%; border: 2px solid var(--ofp-border); padding: 8px; background-color: var(--ofp-header-bg); font-size: 13px;">R ALT</th>
                                            <th style="width: 20%; border: 2px solid var(--ofp-border); padding: 8px; background-color: var(--ofp-header-bg); font-size: 13px;">STBY ALT</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- PRE RVSM Row -->
                                        <tr>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; text-align: center; font-weight: bold; font-size: 13px; height: 40px;">PRE RVSM</td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_pre_time" placeholder="..... : ....." style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_pre_l_alt" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_pre_r_alt" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_pre_stby_alt" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                        </tr>
                                        
                                        <!-- 2 Blank Rows -->
                                        <?php for ($i = 1; $i <= 2; $i++): ?>
                                        <tr>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_fl_<?php echo $i; ?>" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_time_<?php echo $i; ?>" placeholder="..... : ....." style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_l_alt_<?php echo $i; ?>" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_r_alt_<?php echo $i; ?>" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_stby_alt_<?php echo $i; ?>" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                        </tr>
                                        <?php endfor; ?>
                                        
                                        <!-- Middle Row (3rd blank row) -->
                                        <tr>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_fl_3" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_time_3" placeholder="..... : ....." style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_l_alt_3" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_r_alt_3" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_stby_alt_3" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                        </tr>
                                        
                                        <!-- 4th Blank Row -->
                                        <tr>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_fl_4" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_time_4" placeholder="..... : ....." style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_l_alt_4" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_r_alt_4" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_stby_alt_4" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                        </tr>
                                        
                                        <!-- GND Row -->
                                        <tr>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; text-align: center; font-weight: bold; font-size: 13px; height: 40px;">GND</td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_gnd_time" placeholder="..... : ....." style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_gnd_l_alt" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_gnd_r_alt" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                            <td style="border: 2px solid var(--ofp-border); padding: 8px; height: 40px;">
                                                <input type="text" name="rvsm_gnd_stby_alt" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background: var(--ofp-info-bg); color: var(--ofp-text); font-size: 13px; text-align: center;" />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    // 1st Alternate Route (RTA) - before 1st Alternate Point-to-Point
                    if (!empty($rta)): ?>
                    <div class="ofp-section">
                        <div class="ofp-section-title">1st Alternate Route (RTA)</div>
                        <div class="ofp-info-box">
                            <table class="ofp-table">
                                <tr>
                                    <th style="width: 30%;">1st Alternate Route (RTA)</th>
                                    <td style="width: 70%;"><?php echo htmlspecialchars($rta); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <?php endif;
                    
                    // 1st Alternate Point-to-Point (apln)
                    echo renderPointToPointTable($parsed_data, 'apln', 'ALTERNATE 1 ROUTE PLAN', $field_labels, $table_descriptions);
                    
                    // 2nd Alternate Route (RTB) - before 2nd Alternate Point-to-Point
                    if (!empty($rtb)): ?>
                    <div class="ofp-section">
                        <div class="ofp-section-title">2nd Alternate Route (RTB)</div>
                        <div class="ofp-info-box">
                            <table class="ofp-table">
                                <tr>
                                    <th style="width: 30%;">2nd Alternate Route (RTB)</th>
                                    <td style="width: 70%;"><?php echo htmlspecialchars($rtb); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <?php endif;
                    
                    // 2nd Alternate Point-to-Point (bpln)
                    echo renderPointToPointTable($parsed_data, 'bpln', 'ALTERNATE 2 ROUTE PLAN', $field_labels, $table_descriptions);
                    
                    // Take-off Alternate Point-to-Point (tpln)
                    echo renderPointToPointTable($parsed_data, 'tpln', 'TAKE-OFF ALTERNATE ROUTE PLAN', $field_labels, $table_descriptions);
                    ?>

                    <?php
                    // Critical Fuel Scenario (cstbl)
                    if (isset($parsed_data['cstbl']) && is_array($parsed_data['cstbl']) && count($parsed_data['cstbl']) > 0):
                        // Extract ATI values from raw_data for each row
                        $cstbl_with_atis = [];
                        foreach ($parsed_data['cstbl'] as $index => $row) {
                            $ati_value = '';
                            
                            // Try to extract ATI from raw_data
                            // Format might be: ATI=value or in cstbl section
                            if (!empty($record['raw_data'])) {
                                // Try to find ATI in cstbl section
                                if (preg_match('/cstbl:\|(.*?)\|\|/s', $record['raw_data'], $cstbl_matches)) {
                                    $cstbl_raw = $cstbl_matches[1];
                                    // Split by | to get individual entries
                                    $entries = explode('|', $cstbl_raw);
                                    if (isset($entries[$index])) {
                                        $entry = $entries[$index];
                                        // Look for ATI=value in this entry
                                        if (preg_match('/ATI=([^;|\n]+)/', $entry, $ati_matches)) {
                                            $ati_value = trim($ati_matches[1]);
                                        }
                                    }
                                }
                                
                                // If not found in cstbl section, try general pattern
                                if (empty($ati_value)) {
                                    // Try to find all ATI values and match by index
                                    preg_match_all('/ATI=([^;|\n]+)/', $record['raw_data'], $all_ati_matches);
                                    if (isset($all_ati_matches[1][$index])) {
                                        $ati_value = trim($all_ati_matches[1][$index]);
                                    }
                                }
                            }
                            
                            // Add ATI to row
                            $row['ATIS'] = $ati_value;
                            $cstbl_with_atis[] = $row;
                        }
                    ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">CRITICAL FUEL SCENARIO</div>
                            <table class="ofp-table">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">ETP</th>
                                        <th style="width: 10%;">AIRPORT</th>
                                        <th style="width: 10%;">ATIS</th>
                                        <th style="width: 15%;">ETP</th>
                                        <th style="width: 10%;">FUEL REM</th>
                                        <th style="width: 10%;">REQ FUEL</th>
                                        <th style="width: 10%;">DIFF FUEL</th>
                                        <th style="width: 5%;">TIME</th>
                                        <th style="width: 5%;">DIST</th>
                                        <th style="width: 15%;">RUNWAY</th>
                                        <th style="width: 10%;">FREQ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cstbl_with_atis as $row): ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo htmlspecialchars($row['ETN'] ?? ''); ?></td>
                                        <td style="text-align: center; font-weight: bold;"><?php echo htmlspecialchars($row['APT'] ?? ''); ?></td>
                                        <td style="text-align: center;"><?php echo htmlspecialchars($row['ATIS'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['ETP'] ?? ''); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars(formatValue($row['FUR'] ?? '')); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars(formatValue($row['FUQ'] ?? '')); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars(formatValue($row['FUD'] ?? '')); ?></td>
                                        <td style="text-align: center;"><?php echo htmlspecialchars($row['TIM'] ?? ''); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars(formatDistance($row['DIS'] ?? '')); ?></td>
                                        <td style="font-size: 14px;"><?php echo htmlspecialchars($row['RWY'] ?? ''); ?></td>
                                        <td style="font-size: 14px;"><?php echo htmlspecialchars($row['FRQ'] ?? ''); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Parse pdptbl from raw_data if available
                    $pdptbl = [];
                    if (!empty($record['raw_data']) && preg_match('/pdptbl:\|(.*?)\|\|/', $record['raw_data'], $matches)) {
                        $pdptbl_raw = $matches[1];
                        $entries = explode('|', $pdptbl_raw);
                        foreach ($entries as $entry) {
                            if (empty($entry)) continue;
                            $fields = [];
                            $pairs = explode(';', $entry);
                            foreach ($pairs as $pair) {
                                if (strpos($pair, '=') !== false) {
                                    list($key, $value) = explode('=', $pair, 2);
                                    $fields[trim($key)] = trim($value);
                                }
                            }
                            if (!empty($fields)) {
                                $pdptbl[] = $fields;
                            }
                        }
                    }
                    
                    // Pre-determined Point Procedure (pdptbl)
                    if (!empty($pdptbl)):
                    ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">PRE-DETERMINED POINT PROCEDURE</div>
                            <table class="ofp-table">
                                <thead>
                                    <tr>
                                        <th style="width: 8%;">FLL</th>
                                        <th style="width: 10%;">AIRPORT</th>
                                        <th style="width: 15%;">PDP</th>
                                        <th style="width: 10%;">ATI</th>
                                        <th style="width: 15%;">FREQ</th>
                                        <th style="width: 15%;">RUNWAY</th>
                                        <th style="width: 12%;">PART ROUTE</th>
                                        <th style="width: 8%;">FUEL REM</th>
                                        <th style="width: 7%;">FUEL QTY</th>
                                        <th style="width: 8%;">FUEL USED</th>
                                        <th style="width: 5%;">TIME</th>
                                        <th style="width: 5%;">DIST</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pdptbl as $row): ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo htmlspecialchars($row['FLL'] ?? ''); ?></td>
                                        <td style="text-align: center; font-weight: bold;"><?php echo htmlspecialchars($row['APT'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['PDP'] ?? ''); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars(formatValue($row['ATI'] ?? '')); ?></td>
                                        <td style="font-size: 14px;"><?php echo htmlspecialchars($row['FRQ'] ?? ''); ?></td>
                                        <td style="font-size: 14px;"><?php echo htmlspecialchars($row['RWY'] ?? ''); ?></td>
                                        <td style="font-size: 14px;"><?php echo htmlspecialchars($row['PRT'] ?? ''); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars(formatValue($row['FUR'] ?? '')); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars(formatValue($row['FUQ'] ?? '')); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars(formatValue($row['FUD'] ?? '')); ?></td>
                                        <td style="text-align: center;"><?php echo htmlspecialchars($row['TIM'] ?? ''); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars(formatDistance($row['DIS'] ?? '')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Altitude Drift (aldrf)
                    if (isset($parsed_data['aldrf']) && is_array($parsed_data['aldrf']) && count($parsed_data['aldrf']) > 0):
                        $aldrfData = $parsed_data['aldrf'];
                        if (is_array($aldrfData) && isset($aldrfData[0]) && is_string($aldrfData[0])):
                    ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">ALTITUDE DRIFT</div>
                            <div class="ofp-info-box">
                                <pre style="font-size: 14px; margin: 0; white-space: pre-wrap;"><?php echo htmlspecialchars($aldrfData[0]); ?></pre>
                            </div>
                        </div>
                    <?php
                        endif;
                    endif;
                    ?>

                    <?php
                    // Wind & Temperature Aloft (wdtmp)
                    if (isset($parsed_data['wdtmp']) && is_array($parsed_data['wdtmp']) && count($parsed_data['wdtmp']) > 0):
                        $wdtmpData = $parsed_data['wdtmp'];
                        if (is_array($wdtmpData) && isset($wdtmpData[0]) && is_string($wdtmpData[0])):
                    ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">WINDS/TEMPERATURES ALOFT FORECAST</div>
                            <div class="ofp-info-box">
                                <pre style="font-size: 14px; margin: 0; white-space: pre-wrap;"><?php echo htmlspecialchars($wdtmpData[0]); ?></pre>
                            </div>
                        </div>
                    <?php
                        endif;
                    endif;
                    ?>


                    <?php
                    // ICAO ATC Format (icatc)
                    if (isset($parsed_data['icatc']) && is_array($parsed_data['icatc']) && count($parsed_data['icatc']) > 0):
                        $icatcContent = '';
                        foreach ($parsed_data['icatc'] as $item) {
                            if (is_array($item) && isset($item['DATA'])) {
                                $icatcContent .= $item['DATA'] . "\n";
                            } elseif (is_array($item)) {
                                foreach ($item as $key => $value) {
                                    $icatcContent .= $value . "\n";
                                }
                            } else {
                                $icatcContent .= $item . "\n";
                            }
                        }
                        
                        // Parse ICAO ATC Format: (FPL-RAI7521-IS /-E145/M-SDGRWY/S /-OIMM1605 ...)
                        // Format the content by splitting on " /" pattern
                        $formattedContent = '';
                        if (!empty($icatcContent)) {
                            $content = trim($icatcContent);
                            
                            // Split on " /" pattern (space followed by slash) to create new lines
                            // This handles patterns like: " /-E145", " /C/AYREMPOUR", " /-OIMM1605"
                            $formattedContent = preg_replace('/\s+\/(?=-|C\/|P\/|E\/|R\/|S\/|J\/|A\/)/', "\n", $content);
                            
                            // Clean up: remove leading "/" from lines that start with "/"
                            $lines = explode("\n", $formattedContent);
                            $formattedLines = [];
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (!empty($line)) {
                                    // Remove leading "/" if the line starts with "/" followed by "-" or letter
                                    $line = preg_replace('/^\/+(?=-|[A-Z])/', '', $line);
                                    $formattedLines[] = $line;
                                }
                            }
                            
                            $formattedContent = implode("\n", $formattedLines);
                        }
                    ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">SHORT ICAO ATC FLIGHT PLAN</div>
                            <div class="ofp-info-box">
                                <pre style="font-size: 14px; margin: 0; white-space: pre-wrap; font-family: monospace; line-height: 1.6;"><?php echo htmlspecialchars($formattedContent); ?></pre>
                            </div>
                            <div style="margin-top: 5px; font-size: 14px; text-align: center;">
                                <strong>End of Short ICAO ATC Flight Plan</strong>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- SUMMARY INFORMATION Section -->
                    <?php
                    // Extract data for summary
                    $summary_date = $record_date ?? ($record['parsed_data']['binfo']['DTE'] ?? '');
                    $summary_aircraft = $aircraft_reg ?? '';
                    $summary_flight_no = $flight_number ?? '';
                    $summary_route = $route ?? '';
                    $summary_std = $etd ?? '';
                    
                    // Extract TRIP TIME (TIM) from raw_data
                    $summary_trip_time = '';
                    if (!empty($record['raw_data'])) {
                        if (preg_match('/TIM=([^;|\n]+)/', $record['raw_data'], $tim_matches)) {
                            $summary_trip_time = trim($tim_matches[1]);
                        }
                    }
                    // Fallback to formatTime($trip_fuel) if not found
                    if (empty($summary_trip_time)) {
                        $summary_trip_time = formatTime($trip_fuel);
                    }
                    
                    $summary_altitude = $fll ?? '';
                    $summary_commander = $commander ?? '';
                    $summary_cabin_senior = $cabin_senior ?? '';
                    
                    // Parse route to get origin and destination names
                    $route_parts = [];
                    $origin_station_name = '';
                    $dest_station_name = '';
                    
                    if (!empty($summary_route)) {
                        // Try to extract airport codes and names from route
                        // Format might be: "OIMM - OIGG" or "MHD - RAS"
                        $route_codes = explode(' - ', $summary_route);
                        if (count($route_codes) >= 2) {
                            $origin_code = trim($route_codes[0]);
                            $dest_code = trim($route_codes[1]);
                            
                            $route_parts = [
                                'origin_code' => $origin_code,
                                'dest_code' => $dest_code
                            ];
                            
                            // Get station names from database
                            try {
                                $pdo = getDBConnection();
                                
                                // Get origin station name
                                if (!empty($origin_code)) {
                                    $stmt = $pdo->prepare("SELECT station_name FROM stations WHERE icao_code = ? LIMIT 1");
                                    $stmt->execute([$origin_code]);
                                    $origin_station = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($origin_station) {
                                        $origin_station_name = $origin_station['station_name'];
                                    }
                                }
                                
                                // Get destination station name
                                if (!empty($dest_code)) {
                                    $stmt = $pdo->prepare("SELECT station_name FROM stations WHERE icao_code = ? LIMIT 1");
                                    $stmt->execute([$dest_code]);
                                    $dest_station = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($dest_station) {
                                        $dest_station_name = $dest_station['station_name'];
                                    }
                                }
                            } catch (PDOException $e) {
                                // If database query fails, use empty names (fallback to codes only)
                                $origin_station_name = '';
                                $dest_station_name = '';
                            }
                        }
                    }
                    ?>
                    <div class="ofp-section" style="position: relative; overflow: hidden; min-height: 400px;">
                        
                    
                        
                       
                        
                        
                        <div style="display: flex; gap: 20px; position: relative; z-index: 1;">
                            <!-- Left Column: Summary Information -->
                            <div style="flex: 1;">
                                <div style="background-color: var(--ofp-header-bg); padding: 12px; margin-bottom: 15px; border: 1px solid var(--ofp-border);">
                                    <div style="font-weight: bold; font-size: 18px; color: var(--ofp-text);">SUMMARY INFORMATION</div>
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 12px;">
                                    <div style="display: flex; align-items: flex-start;">
                                        <div style="font-weight: bold; width: 120px; color: var(--ofp-text);">DATE:</div>
                                        <div style="flex: 1; color: var(--ofp-text);"><?php echo htmlspecialchars($summary_date); ?></div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: flex-start;">
                                        <div style="font-weight: bold; width: 120px; color: var(--ofp-text);">AIRCRAFT:</div>
                                        <div style="flex: 1; color: var(--ofp-text);"><?php echo htmlspecialchars($summary_aircraft); ?></div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: flex-start;">
                                        <div style="font-weight: bold; width: 120px; color: var(--ofp-text);">FLIGHT NO:</div>
                                        <div style="flex: 1; color: var(--ofp-text);"><?php echo htmlspecialchars($summary_flight_no); ?></div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: flex-start;">
                                        <div style="font-weight: bold; width: 120px; color: var(--ofp-text);">ROUTE:</div>
                                        <div style="flex: 1; color: var(--ofp-text);">
                                            <?php if (!empty($route_parts)): ?>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div style="border-left: 2px solid var(--ofp-border); padding-left: 8px;">
                                                        <div><?php echo htmlspecialchars($route_parts['origin_code']); ?><?php echo !empty($origin_station_name) ? ' - ' . htmlspecialchars($origin_station_name) : ''; ?></div>
                                                        <div><?php echo htmlspecialchars($route_parts['dest_code']); ?><?php echo !empty($dest_station_name) ? ' - ' . htmlspecialchars($dest_station_name) : ''; ?></div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($summary_route); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: flex-start;">
                                        <div style="font-weight: bold; width: 120px; color: var(--ofp-text);">STD:</div>
                                        <div style="flex: 1; color: var(--ofp-text);"><?php echo htmlspecialchars($summary_std); ?></div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: flex-start;">
                                        <div style="font-weight: bold; width: 120px; color: var(--ofp-text);">TRIP TIME:</div>
                                        <div style="flex: 1; color: var(--ofp-text);"><?php echo htmlspecialchars($summary_trip_time); ?></div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: flex-start;">
                                        <div style="font-weight: bold; width: 120px; color: var(--ofp-text);">ALTITUDE:</div>
                                        <div style="flex: 1; color: var(--ofp-text);">
                                            <?php 
                                            if (!empty($summary_altitude)) {
                                                $altitude_value = is_numeric($summary_altitude) ? intval($summary_altitude) * 100 : $summary_altitude;
                                                echo htmlspecialchars($altitude_value . ' FT');
                                            } else {
                                                echo '';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: flex-start;">
                                        <div style="font-weight: bold; width: 120px; color: var(--ofp-text);">COMMANDER:</div>
                                        <div style="flex: 1; color: var(--ofp-text);"><?php echo htmlspecialchars($summary_commander); ?></div>
                                    </div>
                                    
                                    <?php
                                    // Convert date from "NOV 11 2025" to "2025-11-11 00:00:00"
                                    $converted_date = '';
                                    if (!empty($summary_date)) {
                                        $date_obj = DateTime::createFromFormat('M d Y', $summary_date);
                                        if (!$date_obj) {
                                            $date_obj = DateTime::createFromFormat('M d, Y', $summary_date);
                                        }
                                        if (!$date_obj) {
                                            $date_obj = DateTime::createFromFormat('M j Y', $summary_date);
                                        }
                                        if (!$date_obj) {
                                            $date_obj = DateTime::createFromFormat('M j, Y', $summary_date);
                                        }
                                        if ($date_obj) {
                                            $converted_date = $date_obj->format('Y-m-d 00:00:00');
                                        }
                                    }
                                    
                                    // Extract number from flight number (e.g., "RAI7521" -> "7521")
                                    $extracted_flight_no = '';
                                    if (!empty($summary_flight_no)) {
                                        // Remove all non-numeric characters or extract only numbers
                                        $extracted_flight_no = preg_replace('/[^0-9]/', '', $summary_flight_no);
                                    }
                                    
                                    // Query database to find SCC crew member
                                    $cabin_senior_from_db = $summary_cabin_senior; // Default to existing value from raw_data
                                    $found_in_db = false; // Flag to track if we found the crew in database
                                    
                                    if (!empty($converted_date) && !empty($extracted_flight_no)) {
                                        try {
                                            $pdo = getDBConnection();
                                            
                                            // Query flights table
                                            // Extract just the date part for comparison
                                            $date_only = substr($converted_date, 0, 10); // Get "2025-11-11" from "2025-11-11 00:00:00"
                                            $stmt = $pdo->prepare("
                                                SELECT 
                                                    Crew1, Crew2, Crew3, Crew4, Crew5,
                                                    Crew6, Crew7, Crew8, Crew9, Crew10,
                                                    Crew1_role, Crew2_role, Crew3_role, Crew4_role, Crew5_role,
                                                    Crew6_role, Crew7_role, Crew8_role, Crew9_role, Crew10_role
                                                FROM flights 
                                                WHERE DATE(FltDate) = ?
                                                AND TaskName = ?
                                                LIMIT 1
                                            ");
                                            $stmt->execute([$date_only, $extracted_flight_no]);
                                            $flight_data = $stmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            if ($flight_data) {
                                                // Search for SCC in Crew1_role through Crew10_role
                                                for ($i = 1; $i <= 10; $i++) {
                                                    $crew_role = $flight_data["Crew{$i}_role"] ?? '';
                                                    if ($crew_role === 'SCC') {
                                                        $crew_member_id = $flight_data["Crew{$i}"] ?? '';
                                                        if (!empty($crew_member_id) && is_numeric($crew_member_id)) {
                                                            // Query users table in raimon_fleet database to get first_name and last_name
                                                            try {
                                                                $user_stmt = $pdo->prepare("
                                                                    SELECT first_name, last_name 
                                                                    FROM users 
                                                                    WHERE id = ?
                                                                    LIMIT 1
                                                                ");
                                                                $user_stmt->execute([$crew_member_id]);
                                                                $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                                                                
                                                                if ($user_data && !empty($user_data['first_name']) && !empty($user_data['last_name'])) {
                                                                    $cabin_senior_from_db = trim($user_data['first_name'] . ' ' . $user_data['last_name']);
                                                                    $found_in_db = true;
                                                                } else {
                                                                    // Fallback to ID if user not found
                                                                    $cabin_senior_from_db = $crew_member_id;
                                                                    $found_in_db = true; // We found the crew role, even if user not found
                                                                }
                                                            } catch (PDOException $e) {
                                                                // If user query fails, use the ID
                                                                error_log("Error querying users for cabin senior (ID: $crew_member_id): " . $e->getMessage());
                                                                $cabin_senior_from_db = $crew_member_id;
                                                                $found_in_db = true; // We found the crew role, even if user query failed
                                                            }
                                                            break;
                                                        }
                                                    }
                                                }
                                            } else {
                                                // Flight not found in database
                                                error_log("Flight not found in database - Date: $date_only, TaskName: $extracted_flight_no");
                                            }
                                        } catch (PDOException $e) {
                                            // If database query fails, keep existing value from raw_data
                                            error_log("Error querying flights for cabin senior: " . $e->getMessage());
                                        }
                                    } else {
                                        // Date or flight number conversion failed
                                        if (empty($converted_date)) {
                                            error_log("Failed to convert date: $summary_date");
                                        }
                                        if (empty($extracted_flight_no)) {
                                            error_log("Failed to extract flight number: $summary_flight_no");
                                        }
                                    }
                                    
                                    // If we didn't find in database, use the value from raw_data ($summary_cabin_senior)
                                    // $cabin_senior_from_db already has the default value, so no need to change it
                                    ?>
                                    
                                    <div style="display: flex; align-items: flex-start;">
                                        <div style="font-weight: bold; width: 120px; color: var(--ofp-text);">CABIN SENIOR:</div>
                                        <div style="flex: 1; color: var(--ofp-text); border-bottom: 1px dashed var(--ofp-border); min-height: 20px;">
                                            <?php echo htmlspecialchars($cabin_senior_from_db); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column: Note Section -->
                            <div style="width: 300px; position: relative;">
                                <div style="border: 2px dashed var(--ofp-border); padding: 15px; background-color: var(--ofp-info-bg); min-height: 200px;">
                                    <div style="font-weight: bold; font-size: 16px; color: var(--ofp-text); margin-bottom: 10px;">Note :</div>
                                    <textarea name="note" rows="8" style="width: 100%; padding: 4px; border: 1px solid var(--ofp-border); background-color: var(--ofp-info-bg); color: var(--ofp-text); font-size: 16px; resize: vertical; font-family: inherit; min-height: 150px;"></textarea>
                                </div>
                                
                                <!-- Location pin icon -->
                                
                            </div>
                        </div>
                    </div>

                    <?php
                    // Parse NOTAMs from raw_data
                    $notams = '';
                    if (!empty($record['raw_data'])) {
                        if (preg_match('/notams:\|(.*?)\|\|/', $record['raw_data'], $matches)) {
                            $notams = trim($matches[1]);
                        }
                    }
                    ?>

                    <?php if (!empty($notams) && $notams !== '-'): ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">NOTAMS</div>
                            <div class="ofp-info-box">
                                <pre style="font-size: 14px; margin: 0; white-space: pre-wrap;"><?php echo htmlspecialchars($notams); ?></pre>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Check for any parsed_data keys that might not be displayed
                    $displayed_keys = ['binfo', 'futbl', 'mpln', 'apln', 'bpln', 'tpln', 'cstbl', 'pdptbl', 'wtdrf', 'aldrf', 'wdtmp', 'wdclb', 'wddes', 'icatc'];
                    $all_keys = array_keys($parsed_data);
                    $missing_keys = array_diff($all_keys, $displayed_keys);
                    
                    if (!empty($missing_keys)):
                    ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">ADDITIONAL PARSED DATA</div>
                            <div class="ofp-info-box">
                                <details>
                                    <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px; color: var(--ofp-text); padding: 8px; background-color: var(--ofp-header-bg); border: 1px solid var(--ofp-border);">
                                        <i class="fas fa-info-circle mr-2"></i>Additional parsed data fields (<?php echo count($missing_keys); ?>)
                                    </summary>
                                    <table class="ofp-table" style="margin-top: 10px;">
                                        <thead>
                                            <tr>
                                                <th style="width: 30%;">Field Name</th>
                                                <th style="width: 70%;">Data</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($missing_keys as $key): ?>
                                                <tr>
                                                    <td style="font-weight: bold;"><?php echo htmlspecialchars($key); ?></td>
                                                    <td>
                                                        <?php if (is_array($parsed_data[$key])): ?>
                                                            <pre style="font-size: 9px; margin: 0; white-space: pre-wrap; font-family: monospace; max-height: 200px; overflow-y: auto; color: var(--ofp-text);"><?php echo htmlspecialchars(json_encode($parsed_data[$key], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars($parsed_data[$key]); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </details>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Check for any flight_info keys that might not be displayed
                    if (!empty($record['flight_info'])): 
                        $flight_info_displayed = ['flight_number', 'date', 'route', 'aircraft_reg', 'etd', 'eta', 'operator'];
                        $flight_info_keys = array_keys($record['flight_info']);
                        $missing_flight_info = array_diff($flight_info_keys, $flight_info_displayed);
                        
                        if (!empty($missing_flight_info)):
                    ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">ADDITIONAL FLIGHT INFO</div>
                            <div class="ofp-info-box">
                                <details>
                                    <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px; color: var(--ofp-text); padding: 8px; background-color: var(--ofp-header-bg); border: 1px solid var(--ofp-border);">
                                        <i class="fas fa-plane mr-2"></i>Additional flight info fields (<?php echo count($missing_flight_info); ?>)
                                    </summary>
                                    <table class="ofp-table" style="margin-top: 10px;">
                                        <thead>
                                            <tr>
                                                <th style="width: 30%;">Field Name</th>
                                                <th style="width: 70%;">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($missing_flight_info as $key): ?>
                                                <tr>
                                                    <td style="font-weight: bold;"><?php echo htmlspecialchars($key); ?></td>
                                                    <td>
                                                        <?php if (is_array($record['flight_info'][$key])): ?>
                                                            <pre style="font-size: 9px; margin: 0; white-space: pre-wrap; font-family: monospace; max-height: 200px; overflow-y: auto; color: var(--ofp-text);"><?php echo htmlspecialchars(json_encode($record['flight_info'][$key], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars($record['flight_info'][$key] ?? 'N/A'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </details>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endif; 
                    ?>

                    <!-- Raw Data Section -->
                    <?php if (!empty($record['raw_data'])): ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">RAW DATA</div>
                            <div class="ofp-info-box">
                                <details>
                                    <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px; color: var(--ofp-text); padding: 8px; background-color: var(--ofp-header-bg); border: 1px solid var(--ofp-border);">
                                        <i class="fas fa-code mr-2"></i>Click to view/hide raw data
                                    </summary>
                                    <pre style="font-size: 9px; margin: 0; white-space: pre-wrap; font-family: monospace; max-height: 500px; overflow-y: auto; padding: 10px; background-color: var(--ofp-info-bg); color: var(--ofp-text); border: 1px solid var(--ofp-border);"><?php echo htmlspecialchars($record['raw_data']); ?></pre>
                                </details>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Metadata Section -->
                    <?php if (!empty($record['timestamp_utc']) || !empty($record['timestamp_local']) || !empty($record['client_ip']) || !empty($record['format']) || !empty($record['request_id'])): ?>
                        <div class="ofp-section">
                            <div class="ofp-section-title">METADATA</div>
                            <div class="ofp-info-box">
                                <table class="ofp-table">
                                    <tbody>
                                        <?php if (!empty($record['request_id'])): ?>
                                            <tr>
                                                <td style="width: 30%; font-weight: bold;">Request ID</td>
                                                <td style="font-family: monospace; font-size: 14px;"><?php echo htmlspecialchars($record['request_id']); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($record['timestamp_utc'])): ?>
                                            <tr>
                                                <td style="width: 30%; font-weight: bold;">Timestamp (UTC)</td>
                                                <td><?php echo htmlspecialchars($record['timestamp_utc']); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($record['timestamp_local'])): ?>
                                            <tr>
                                                <td style="width: 30%; font-weight: bold;">Timestamp (Local)</td>
                                                <td><?php echo htmlspecialchars($record['timestamp_local']); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($record['client_ip'])): ?>
                                            <tr>
                                                <td style="width: 30%; font-weight: bold;">Client IP</td>
                                                <td><?php echo htmlspecialchars($record['client_ip']); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($record['format'])): ?>
                                            <tr>
                                                <td style="width: 30%; font-weight: bold;">Format</td>
                                                <td><?php echo htmlspecialchars($record['format']); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($file_name)): ?>
                                            <tr>
                                                <td style="width: 30%; font-weight: bold;">Source File</td>
                                                <td><?php echo htmlspecialchars($file_name); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Footer -->
                    <div class="ofp-footer" style="margin-top: 30px; padding-top: 10px; border-top: 1px solid var(--ofp-border); font-size: 14px; color: var(--ofp-text);">
                        <div style="display: flex; justify-content: space-between;">
                            <div>
                                <?php if ($vdt): ?>
                                    <div><?php echo htmlspecialchars($vdt); ?></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($did): ?>
                                    <div><?php echo htmlspecialchars($did); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function downloadWord() {
            const content = document.getElementById('ofpContent');
            const flightNumber = '<?php echo addslashes($flight_number); ?>';
            const route = '<?php echo addslashes($route); ?>';
            const date = '<?php echo addslashes($record_date); ?>';
            
            const htmlContent = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OFP - ${flightNumber}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px; }
        .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .subtitle { font-size: 16px; margin-bottom: 5px; }
        .section { margin-bottom: 30px; page-break-inside: avoid; }
        .section-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; page-break-inside: auto; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 16px; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
        .info-item { border: 1px solid #ccc; padding: 8px; }
        .info-label { font-weight: bold; font-size: 14px; color: #666; margin-bottom: 3px; }
        .info-value { font-size: 16px; }
        .footer { margin-top: 40px; border-top: 1px solid #000; padding-top: 20px; font-size: 14px; text-align: center; color: #666; }
        @media print {
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Operational Flight Plan (OFP)</div>
        <div class="subtitle">Flight: ${flightNumber}</div>
        <div class="subtitle">Route: ${route}</div>
        <div class="subtitle">Date: ${date}</div>
        <div class="subtitle">Generated: ${new Date().toLocaleString()}</div>
    </div>
    ${content.innerHTML.replace(/<style[^>]*>.*?<\/style>/gi, '').replace(/class="[^"]*"/g, '').replace(/dark:[^"]*/g, '').replace(/bg-gray-\d+/g, '').replace(/text-gray-\d+/g, '').replace(/hover:[^"]*/g, '')}
    <div class="footer">
        <p>Generated by <?php echo PROJECT_NAME; ?> on ${new Date().toLocaleString()}</p>
    </div>
</body>
</html>`;
            
            const blob = new Blob([htmlContent], { type: 'application/msword' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `OFP_${flightNumber}_${date || 'unknown'}.doc`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        // Update logo based on dark mode
        function updateLogoForDarkMode() {
            const logo = document.getElementById('raimonLogo');
            if (!logo) return;
            
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (prefersDark) {
                logo.src = '/assets/raimon-dark.png';
            } else {
                logo.src = '/assets/raimon.png';
            }
        }

        // Initialize logo on page load
        document.addEventListener('DOMContentLoaded', updateLogoForDarkMode);

        // Listen for dark mode changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateLogoForDarkMode);

        // Print only main content
        function printMainOnly() {
            // Add class to body to trigger print CSS
            document.body.classList.add('print-main-only');
            
            // Print
            window.print();
            
            // Remove class after print dialog closes
            setTimeout(() => {
                document.body.classList.remove('print-main-only');
            }, 100);
        }
    </script>
</body>
</html>

