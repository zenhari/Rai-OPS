<?php
require_once '../../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/flights/asr/add.php');

$current_user = getCurrentUser();
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_report') {
        $report_data = [
            'report_date' => $_POST['report_date'] ?? date('Y-m-d'),
            'status' => $_POST['status'] ?? 'draft',
            
            // Aircraft Information
            'aircraft_type' => trim($_POST['aircraft_type'] ?? ''),
            'aircraft_registration' => trim($_POST['aircraft_registration'] ?? ''),
            'operator' => trim($_POST['operator'] ?? ''),
            
            // Flight Information
            'flight_number' => trim($_POST['flight_number'] ?? ''),
            'departure_airport' => trim($_POST['departure_airport'] ?? ''),
            'destination_airport' => trim($_POST['destination_airport'] ?? ''),
            'diversion_airport' => trim($_POST['diversion_airport'] ?? ''),
            'place_of_occurrence' => trim($_POST['place_of_occurrence'] ?? ''),
            'occurrence_date' => !empty($_POST['occurrence_date']) ? $_POST['occurrence_date'] : null,
            'occurrence_time_utc' => !empty($_POST['occurrence_time_utc']) ? formatTimeForDatabase($_POST['occurrence_time_utc']) : null,
            'technical_log_seq_no' => trim($_POST['technical_log_seq_no'] ?? ''),
            
            // Purpose of Flight
            'purpose_flight' => $_POST['purpose_flight'] ?? null,
            'purpose_other' => trim($_POST['purpose_other'] ?? ''),
            
            // Bird Strike Information
            'bird_strike_type_of_birds' => trim($_POST['bird_strike_type_of_birds'] ?? ''),
            'bird_strike_nr_seen' => $_POST['bird_strike_nr_seen'] ?? null,
            'bird_strike_nr_struck' => $_POST['bird_strike_nr_struck'] ?? null,
            'bird_strike_damage_description' => trim($_POST['bird_strike_damage_description'] ?? ''),
            
            // Ground Found Information
            'ground_found_name' => trim($_POST['ground_found_name'] ?? ''),
            'ground_found_location' => trim($_POST['ground_found_location'] ?? ''),
            'ground_found_shift' => trim($_POST['ground_found_shift'] ?? ''),
            'ground_found_type' => !empty($_POST['ground_found_type']) ? implode(',', $_POST['ground_found_type']) : null,
            'ground_found_component_description' => trim($_POST['ground_found_component_description'] ?? ''),
            'ground_found_part_no' => trim($_POST['ground_found_part_no'] ?? ''),
            'ground_found_serial_no' => trim($_POST['ground_found_serial_no'] ?? ''),
            'ground_found_atc_chapter' => trim($_POST['ground_found_atc_chapter'] ?? ''),
            'ground_found_tag_no' => trim($_POST['ground_found_tag_no'] ?? ''),
            
            // Wake Turbulence Information
            'wake_turbulence_heading' => !empty($_POST['wake_turbulence_heading']) ? intval($_POST['wake_turbulence_heading']) : null,
            'wake_turbulence_turning' => $_POST['wake_turbulence_turning'] ?? null,
            'wake_turbulence_glide_slope' => $_POST['wake_turbulence_glide_slope'] ?? null,
            'wake_turbulence_pitch' => !empty($_POST['wake_turbulence_pitch']) ? floatval($_POST['wake_turbulence_pitch']) : null,
            'wake_turbulence_roll' => !empty($_POST['wake_turbulence_roll']) ? floatval($_POST['wake_turbulence_roll']) : null,
            'wake_turbulence_yaw' => !empty($_POST['wake_turbulence_yaw']) ? floatval($_POST['wake_turbulence_yaw']) : null,
            'wake_turbulence_attitude_change' => !empty($_POST['wake_turbulence_attitude_change']) ? floatval($_POST['wake_turbulence_attitude_change']) : null,
            'wake_turbulence_buffet' => $_POST['wake_turbulence_buffet'] ?? null,
            'wake_turbulence_stick_shake' => $_POST['wake_turbulence_stick_shake'] ?? null,
            'wake_turbulence_other_info' => trim($_POST['wake_turbulence_other_info'] ?? ''),
            'wake_turbulence_events_1' => trim($_POST['wake_turbulence_events_1'] ?? ''),
            'wake_turbulence_events_2' => trim($_POST['wake_turbulence_events_2'] ?? ''),
            'wake_turbulence_events_3' => trim($_POST['wake_turbulence_events_3'] ?? ''),
            
            
            // Airprox / ATC Procedural / TCAS RA
            'airprox_events' => !empty($_POST['airprox_events']) ? implode(',', $_POST['airprox_events']) : null,
            'reported_to_atc' => $_POST['reported_to_atc'] ?? null,
            'minimum_horizontal_separation' => trim($_POST['minimum_horizontal_separation'] ?? ''),
            'atc_instructions_issued' => $_POST['atc_instructions_issued'] ?? null,
            'frequency_in_use' => trim($_POST['frequency_in_use'] ?? ''),
            'airprox_heading' => !empty($_POST['airprox_heading']) ? intval($_POST['airprox_heading']) : null,
            'cleared_altitude_fl' => trim($_POST['cleared_altitude_fl'] ?? ''),
            'tcas_alert' => $_POST['tcas_alert'] ?? null,
            'types_of_ra' => !empty($_POST['types_of_ra']) ? implode(',', $_POST['types_of_ra']) : null,
            'ra_followed' => $_POST['ra_followed'] ?? null,
            'vertical_deviation' => trim($_POST['vertical_deviation'] ?? ''),
            'tcas_alert_was' => $_POST['tcas_alert_was'] ?? null,
            'airprox_signature_name' => trim($_POST['airprox_signature_name'] ?? ''),
            'airprox_signature' => trim($_POST['airprox_signature'] ?? ''),
            'airprox_signature_date' => !empty($_POST['airprox_signature_date']) ? $_POST['airprox_signature_date'] : null,
            
            'created_by' => $current_user['id']
        ];
        
        // Validate required fields
        if (empty($report_data['aircraft_type']) || empty($report_data['aircraft_registration']) || 
            empty($report_data['flight_number'])) {
            $message = 'Please fill in all required fields (Aircraft Type, Registration, and Flight Number).';
            $message_type = 'error';
        } else {
            if (createASRReport($report_data)) {
                $message = 'ASR report created successfully.';
                $message_type = 'success';
                // Redirect to index page after successful creation
                header('Location: index.php');
                exit();
            } else {
                $message = 'Failed to create ASR report.';
                $message_type = 'error';
            }
        }
    }
}

// Helper function to safely output values
function safeOutput($value) {
    return htmlspecialchars($value ?? '');
}

// Helper function to format time from HHMM to HH:MM:SS for database
function formatTimeForDatabase($timeInput) {
    if (empty($timeInput)) {
        return null;
    }
    
    // Remove any non-digit characters
    $timeInput = preg_replace('/\D/', '', $timeInput);
    
    // Ensure we have exactly 4 digits
    if (strlen($timeInput) != 4) {
        return null;
    }
    
    // Extract hours and minutes
    $hours = substr($timeInput, 0, 2);
    $minutes = substr($timeInput, 2, 2);
    
    // Validate hours (00-23)
    if ($hours > 23) {
        $hours = '23';
    }
    
    // Validate minutes (00-59)
    if ($minutes > 59) {
        $minutes = '59';
    }
    
    // Return formatted time for database (HH:MM:SS)
    return $hours . ':' . $minutes . ':00';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Air Safety Report (ASR)</title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { 
            font-family: 'Vazirmatn', 'IRANSansX', 'Roboto', sans-serif; 
            line-height: 1.5;
        }
        
        /* Dark Mode Styles */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #111827;
                color: #E5E7EB;
            }
            
            /* Input Fields */
            input, textarea, select {
                background-color: #1F2937 !important;
                border: 1px solid #374151 !important;
                color: #E5E7EB !important;
                border-radius: 8px !important;
            }
            
            input:focus, textarea:focus, select:focus {
                border-color: #3B82F6 !important;
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2) !important;
                outline: none !important;
            }
            
            input:hover, textarea:hover, select:hover {
                border-color: #4B5563 !important;
            }
            
            input::placeholder, textarea::placeholder {
                color: #9CA3AF !important;
            }
            
            /* Labels */
            label {
                color: #9CA3AF !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                margin-bottom: 8px !important;
            }
            
            /* Radio and Checkbox */
            input[type="radio"], input[type="checkbox"] {
                accent-color: #3B82F6 !important;
            }
            
            /* Buttons */
            .btn-primary {
                background-color: #3B82F6 !important;
                border-color: #3B82F6 !important;
                color: white !important;
                border-radius: 10px !important;
                font-weight: 500 !important;
                transition: all 150ms ease !important;
            }
            
            .btn-primary:hover {
                background-color: #2563EB !important;
                border-color: #2563EB !important;
            }
            
            .btn-primary:active {
                background-color: #1D4ED8 !important;
                border-color: #1D4ED8 !important;
            }
            
            .btn-secondary {
                background-color: transparent !important;
                border: 1px solid #374151 !important;
                color: #E5E7EB !important;
                border-radius: 8px !important;
                font-weight: 500 !important;
                transition: all 150ms ease !important;
            }
            
            .btn-secondary:hover {
                background-color: rgba(55, 65, 81, 0.1) !important;
                border-color: #4B5563 !important;
            }
            
            /* Cards and Sections */
            .bg-white {
                background-color: #1F2937 !important;
                border: 1px solid #374151 !important;
            }
            
            .bg-gray-50 {
                background-color: #111827 !important;
            }
            
            /* Text Colors */
            .text-gray-900 {
                color: #E5E7EB !important;
            }
            
            .text-gray-700 {
                color: #E5E7EB !important;
            }
            
            .text-gray-600 {
                color: #9CA3AF !important;
            }
            
            .text-gray-500 {
                color: #9CA3AF !important;
            }
            
            .text-gray-400 {
                color: #9CA3AF !important;
            }
            
            .text-gray-300 {
                color: #D1D5DB !important;
            }
            
            /* Borders */
            .border-gray-200 {
                border-color: #374151 !important;
            }
            
            .border-gray-300 {
                border-color: #374151 !important;
            }
            
            /* Shadows */
            .shadow-sm {
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.3) !important;
            }
            
            .shadow {
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2) !important;
            }
            
            /* Status Colors */
            .text-red-500 {
                color: #EF4444 !important;
            }
            
            .bg-green-50 {
                background-color: rgba(34, 197, 94, 0.1) !important;
                border-color: #22C55E !important;
                color: #22C55E !important;
            }
            
            .bg-red-50 {
                background-color: rgba(239, 68, 68, 0.1) !important;
                border-color: #EF4444 !important;
                color: #EF4444 !important;
            }
            
            /* Focus States */
            .focus\:ring-blue-500:focus {
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2) !important;
            }
            
            /* Hover States */
            .hover\:bg-gray-50:hover {
                background-color: rgba(55, 65, 81, 0.1) !important;
            }
            
            .hover\:bg-gray-100:hover {
                background-color: rgba(55, 65, 81, 0.2) !important;
            }
            
            /* Required Field Indicator */
            .text-red-500 {
                color: #EF4444 !important;
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 <?php echo $message_type === 'success' ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400'; ?> px-4 py-3 rounded-md">
                        <div class="flex">
                            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mt-0.5 mr-2"></i>
                            <span class="text-sm"><?php echo htmlspecialchars($message); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ASR Form -->
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="create_report">
                    
                    <!-- Type of Occurrence Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Type of Occurrence
                        </h2>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="occurrence_type[]" value="ground_found" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('ground_found', $_POST['occurrence_type'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Ground Found</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="occurrence_type[]" value="flight" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('flight', $_POST['occurrence_type'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Flight</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="occurrence_type[]" value="air_nav" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('air_nav', $_POST['occurrence_type'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Air Nav</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="occurrence_type[]" value="technical" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('technical', $_POST['occurrence_type'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Technical</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="occurrence_type[]" value="bird_strike" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('bird_strike', $_POST['occurrence_type'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Bird Strike</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="occurrence_type[]" value="load_control" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('load_control', $_POST['occurrence_type'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Load Control</span>
                            </label>
                        </div>
                    </div>

                    <!-- Aircraft Information Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-plane mr-2"></i>Aircraft Information
                        </h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Aircraft Type <span class="text-red-500">*</span>
                                    </label>
                                    <select name="aircraft_type" required
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="">-- Select Aircraft Type --</option>
                                        <?php
                                        // Get aircraft types from database
                                        $aircraft_types = [];
                                        try {
                                            $pdo = getDBConnection();
                                            $stmt = $pdo->prepare("SELECT DISTINCT aircraft_type FROM aircraft WHERE aircraft_type IS NOT NULL AND aircraft_type != '' AND enabled = 1 ORDER BY aircraft_type");
                                            $stmt->execute();
                                            $aircraft_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                        } catch (Exception $e) {
                                            // Handle error silently
                                        }
                                        
                                        $selected_type = $_POST['aircraft_type'] ?? '';
                                        foreach ($aircraft_types as $type) {
                                            $selected = ($selected_type === $type) ? 'selected' : '';
                                            echo "<option value=\"" . htmlspecialchars($type) . "\" $selected>" . htmlspecialchars($type) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Aircraft Registration <span class="text-red-500">*</span>
                                    </label>
                                    <select name="aircraft_registration" required id="aircraft_registration"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        <option value="">-- Select Aircraft Registration --</option>
                                        <?php
                                        // Get all aircraft registrations from database
                                        $aircraft_registrations = [];
                                        try {
                                            $pdo = getDBConnection();
                                            $stmt = $pdo->prepare("SELECT registration FROM aircraft WHERE enabled = 1 ORDER BY registration");
                                            $stmt->execute();
                                            $aircraft_registrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                        } catch (Exception $e) {
                                            // Handle error silently
                                        }
                                        
                                        $selected_registration = $_POST['aircraft_registration'] ?? '';
                                        foreach ($aircraft_registrations as $registration) {
                                            $selected = ($selected_registration === $registration) ? 'selected' : '';
                                            echo "<option value=\"" . htmlspecialchars($registration) . "\" $selected>" . htmlspecialchars($registration) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Operator
                                    </label>
                                    <input type="text" name="operator"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['operator'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Place of Occurrence
                                        </label>
                                        <input type="text" name="place_of_occurrence"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               value="<?php echo safeOutput($_POST['place_of_occurrence'] ?? ''); ?>">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Date and Time Occurrence UTC Time
                                        </label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="date" name="occurrence_date"
                                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                   value="<?php echo safeOutput($_POST['occurrence_date'] ?? ''); ?>">
                                            <input type="text" name="occurrence_time_utc" placeholder="HHMM" maxlength="4" pattern="[0-9]{4}"
                                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                   value="<?php echo safeOutput($_POST['occurrence_time_utc'] ?? ''); ?>" oninput="formatTimeInput(this)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </div>

                    <!-- Flight Information Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-route mr-2"></i>Flight Information
                        </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Flight Number <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="flight_number" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['flight_number'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Departure
                                    </label>
                                    <input type="text" name="departure_airport"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['departure_airport'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Destination
                                    </label>
                                    <input type="text" name="destination_airport"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['destination_airport'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Diversion to
                                    </label>
                                    <input type="text" name="diversion_airport"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['diversion_airport'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Technical Log Seq No.
                                        </label>
                                        <input type="text" name="technical_log_seq_no"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               value="<?php echo safeOutput($_POST['technical_log_seq_no'] ?? ''); ?>">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Report Date
                                        </label>
                                        <input type="date" name="report_date"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               value="<?php echo safeOutput($_POST['report_date'] ?? date('Y-m-d')); ?>">
                                    </div>
                                </div>
                            </div>
                    </div>

                    <!-- Purpose of Flight Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-flag mr-2"></i>Purpose of Flight
                        </h2>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                            <label class="flex items-center">
                                <input type="radio" name="purpose_flight" value="schedule" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo ($_POST['purpose_flight'] ?? '') === 'schedule' ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Schedule</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="radio" name="purpose_flight" value="non_schedule" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo ($_POST['purpose_flight'] ?? '') === 'non_schedule' ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Non-Schedule</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="radio" name="purpose_flight" value="charter" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo ($_POST['purpose_flight'] ?? '') === 'charter' ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Charter</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="radio" name="purpose_flight" value="cargo" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo ($_POST['purpose_flight'] ?? '') === 'cargo' ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Cargo</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="radio" name="purpose_flight" value="test_flight" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo ($_POST['purpose_flight'] ?? '') === 'test_flight' ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Test Flight</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="radio" name="purpose_flight" value="re_position" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo ($_POST['purpose_flight'] ?? '') === 're_position' ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Re-Position</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="radio" name="purpose_flight" value="vip" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo ($_POST['purpose_flight'] ?? '') === 'vip' ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">VIP</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="radio" name="purpose_flight" value="training" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo ($_POST['purpose_flight'] ?? '') === 'training' ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Training</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="radio" name="purpose_flight" value="ferry" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo ($_POST['purpose_flight'] ?? '') === 'ferry' ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Ferry</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="radio" name="purpose_flight" value="towing" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo ($_POST['purpose_flight'] ?? '') === 'towing' ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Towing</span>
                            </label>
                        </div>
                    </div>

                    <!-- Flight Phase Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-plane-departure mr-2"></i>Flight Phase
                        </h2>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="towing" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('towing', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Towing</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="parked" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('parked', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Parked</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="pushback" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('pushback', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Pushback</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="taxiing" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('taxiing', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Taxiing</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="take_off" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('take_off', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Take-off</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="initial_climb" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('initial_climb', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Initial Climb</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="climb" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('climb', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Climb</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="cruise" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('cruise', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Cruise</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="descent" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('descent', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Descent</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="holding" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('holding', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Holding</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="approach" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('approach', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Approach</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="landing" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('landing', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Landing</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="circuit" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('circuit', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Circuit</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="parking" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('parking', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Parking</span>
                            </label>
                        </div>
                        
                        <!-- Additional Flight Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Passenger / Crew
                                </label>
                                <input type="text" name="passenger_crew"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['passenger_crew'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Flight Rules (VFR / IFR)
                                </label>
                                <select name="flight_rules"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Flight Rules --</option>
                                    <option value="VFR" <?php echo ($_POST['flight_rules'] ?? '') === 'VFR' ? 'selected' : ''; ?>>VFR</option>
                                    <option value="IFR" <?php echo ($_POST['flight_rules'] ?? '') === 'IFR' ? 'selected' : ''; ?>>IFR</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Altitude / Flight Level
                                </label>
                                <input type="text" name="altitude_flight_level"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['altitude_flight_level'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Aircraft Speed (Kts)
                                </label>
                                <input type="number" name="aircraft_speed_kts"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['aircraft_speed_kts'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Aircraft T/O Weight
                                </label>
                                <input type="number" name="aircraft_takeoff_weight"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['aircraft_takeoff_weight'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Fault Report Code
                                </label>
                                <input type="text" name="fault_report_code"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['fault_report_code'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Consequence and Configuration Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-exclamation-circle mr-2"></i>Consequence & Configuration
                        </h2>
                        
                        <!-- Consequence -->
                        <div class="mb-6">
                            <h3 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-3">Consequence</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="no_consequences" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('no_consequences', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">No Consequences</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="diversion" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('diversion', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Diversion</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="rejected_takeoff" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('rejected_takeoff', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Rejected Take-Off</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="turn_back" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('turn_back', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Turn Back</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="flight_delayed_cancelled" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('flight_delayed_cancelled', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Flight Delayed / Cancelled</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="fuel_dump" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('fuel_dump', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Fuel Dump</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="engines_shutdown" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('engines_shutdown', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Engine(s) Shutdown</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="precautionary_landing" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('precautionary_landing', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Precautionary Landing</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Configuration at Event -->
                        <div>
                            <h3 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-3">Configuration at Event</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Auto Pilot
                                    </label>
                                    <input type="text" name="config_autopilot"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['config_autopilot'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Auto Thrust
                                    </label>
                                    <input type="text" name="config_autothrust"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['config_autothrust'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Gear
                                    </label>
                                    <input type="text" name="config_gear"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['config_gear'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Flaps
                                    </label>
                                    <input type="text" name="config_flaps"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['config_flaps'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Slats
                                    </label>
                                    <input type="text" name="config_slats"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['config_slats'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Spoilers
                                    </label>
                                    <input type="text" name="config_spoilers"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['config_spoilers'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Environmental Details Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-cloud-sun mr-2"></i>Environmental Details
                        </h2>
                        
                        <!-- Weather Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Wind Direction
                                </label>
                                <input type="text" name="wind_direction"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['wind_direction'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Wind Speed (Kts)
                                </label>
                                <input type="number" name="wind_speed_kts"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['wind_speed_kts'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Cloud Type
                                </label>
                                <input type="text" name="cloud_type"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['cloud_type'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Cloud Height (ft)
                                </label>
                                <input type="number" name="cloud_height_ft"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['cloud_height_ft'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Precipitation Type
                                </label>
                                <input type="text" name="precipitation_type"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['precipitation_type'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Precipitation Quantity
                                </label>
                                <input type="text" name="precipitation_quantity"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['precipitation_quantity'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Visibility
                                </label>
                                <input type="text" name="visibility"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['visibility'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Icing Severity
                                </label>
                                <input type="text" name="icing_severity"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['icing_severity'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Turbulence Severity
                                </label>
                                <input type="text" name="turbulence_severity"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['turbulence_severity'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    OAT (°C)
                                </label>
                                <input type="number" name="oat_c"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['oat_c'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Runway State
                                </label>
                                <input type="text" name="runway_state"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['runway_state'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Runway Category
                                </label>
                                <input type="text" name="runway_category"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['runway_category'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Additional Environmental Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    QNH (Hpa)
                                </label>
                                <input type="number" name="qnh_hpa"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['qnh_hpa'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Windshear Severity
                                </label>
                                <input type="text" name="windshear_severity"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['windshear_severity'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Light Conditions
                                </label>
                                <input type="text" name="light_conditions"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['light_conditions'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Runway Type
                                </label>
                                <input type="text" name="runway_type"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['runway_type'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Flight Phase Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-plane-departure mr-2"></i>Flight Phase
                        </h2>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="towing" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('towing', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Towing</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="parked" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('parked', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Parked</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="pushback" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('pushback', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Pushback</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="taxiing" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('taxiing', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Taxiing</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="take_off" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('take_off', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Take-off</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="initial_climb" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('initial_climb', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Initial Climb</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="climb" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('climb', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Climb</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="cruise" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('cruise', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Cruise</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="descent" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('descent', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Descent</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="holding" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('holding', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Holding</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="approach" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('approach', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Approach</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="landing" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('landing', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Landing</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="circuit" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('circuit', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Circuit</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="flight_phase[]" value="parking" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                       <?php echo in_array('parking', $_POST['flight_phase'] ?? []) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Parking</span>
                            </label>
                        </div>
                        
                        <!-- Additional Flight Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Passenger / Crew
                                </label>
                                <input type="text" name="passenger_crew"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['passenger_crew'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Flight Rules (VFR / IFR)
                                </label>
                                <select name="flight_rules"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Flight Rules --</option>
                                    <option value="VFR" <?php echo ($_POST['flight_rules'] ?? '') === 'VFR' ? 'selected' : ''; ?>>VFR</option>
                                    <option value="IFR" <?php echo ($_POST['flight_rules'] ?? '') === 'IFR' ? 'selected' : ''; ?>>IFR</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Altitude / Flight Level
                                </label>
                                <input type="text" name="altitude_flight_level"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['altitude_flight_level'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Aircraft Speed (Kts)
                                </label>
                                <input type="number" name="aircraft_speed_kts"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['aircraft_speed_kts'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Aircraft T/O Weight
                                </label>
                                <input type="number" name="aircraft_takeoff_weight"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['aircraft_takeoff_weight'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Fault Report Code
                                </label>
                                <input type="text" name="fault_report_code"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['fault_report_code'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Consequence and Configuration Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-exclamation-circle mr-2"></i>Consequence & Configuration
                        </h2>
                        
                        <!-- Consequence -->
                        <div class="mb-6">
                            <h3 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-3">Consequence</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="no_consequences" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('no_consequences', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">No Consequences</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="diversion" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('diversion', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Diversion</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="rejected_takeoff" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('rejected_takeoff', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Rejected Take-Off</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="turn_back" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('turn_back', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Turn Back</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="flight_delayed_cancelled" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('flight_delayed_cancelled', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Flight Delayed / Cancelled</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="fuel_dump" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('fuel_dump', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Fuel Dump</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="engines_shutdown" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('engines_shutdown', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Engine(s) Shutdown</span>
                                </label>
                                
                                <label class="flex items-center">
                                    <input type="checkbox" name="consequence[]" value="precautionary_landing" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                           <?php echo in_array('precautionary_landing', $_POST['consequence'] ?? []) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Precautionary Landing</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Configuration at Event -->
                        <div>
                            <h3 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-3">Configuration at Event</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Auto Pilot
                                    </label>
                                    <input type="text" name="config_autopilot"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['config_autopilot'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Auto Thrust
                                    </label>
                                    <input type="text" name="config_autothrust"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['config_autothrust'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Gear
                                    </label>
                                    <input type="text" name="config_gear"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['config_gear'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Flaps
                                    </label>
                                    <input type="text" name="config_flaps"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['config_flaps'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Slats
                                    </label>
                                    <input type="text" name="config_slats"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['config_slats'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Spoilers
                                    </label>
                                    <input type="text" name="config_spoilers"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['config_spoilers'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Environmental Details Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-cloud-sun mr-2"></i>Environmental Details
                        </h2>
                        
                        <!-- Weather Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Wind Direction
                                </label>
                                <input type="text" name="wind_direction"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['wind_direction'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Wind Speed (Kts)
                                </label>
                                <input type="number" name="wind_speed_kts"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['wind_speed_kts'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Cloud Type
                                </label>
                                <input type="text" name="cloud_type"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['cloud_type'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Cloud Height (ft)
                                </label>
                                <input type="number" name="cloud_height_ft"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['cloud_height_ft'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Precipitation Type
                                </label>
                                <input type="text" name="precipitation_type"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['precipitation_type'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Precipitation Quantity
                                </label>
                                <input type="text" name="precipitation_quantity"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['precipitation_quantity'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Visibility
                                </label>
                                <input type="text" name="visibility"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['visibility'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Icing Severity
                                </label>
                                <input type="text" name="icing_severity"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['icing_severity'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Turbulence Severity
                                </label>
                                <input type="text" name="turbulence_severity"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['turbulence_severity'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    OAT (°C)
                                </label>
                                <input type="number" name="oat_c"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['oat_c'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Runway State
                                </label>
                                <input type="text" name="runway_state"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['runway_state'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Runway Category
                                </label>
                                <input type="text" name="runway_category"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['runway_category'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Additional Environmental Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    QNH (Hpa)
                                </label>
                                <input type="number" name="qnh_hpa"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['qnh_hpa'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Windshear Severity
                                </label>
                                <input type="text" name="windshear_severity"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['windshear_severity'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Light Conditions
                                </label>
                                <input type="text" name="light_conditions"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['light_conditions'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Runway Type
                                </label>
                                <input type="text" name="runway_type"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['runway_type'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Bird Strike Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-dove mr-2"></i>Bird Strike
                        </h2>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Left Column - Bird Strike Details -->
                            <div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Type of Birds
                                    </label>
                                    <input type="text" name="bird_strike_type_of_birds"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['bird_strike_type_of_birds'] ?? ''); ?>">
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Number Seen
                                    </label>
                                    <div class="grid grid-cols-4 gap-2">
                                        <label class="flex items-center">
                                            <input type="radio" name="bird_strike_nr_seen" value="1" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['bird_strike_nr_seen'] ?? '') === '1' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">1</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="bird_strike_nr_seen" value="2-10" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['bird_strike_nr_seen'] ?? '') === '2-10' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">2-10</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="bird_strike_nr_seen" value="11-100" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['bird_strike_nr_seen'] ?? '') === '11-100' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">11-100</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="bird_strike_nr_seen" value="More" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['bird_strike_nr_seen'] ?? '') === 'More' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">More</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Number Struck
                                    </label>
                                    <div class="grid grid-cols-4 gap-2">
                                        <label class="flex items-center">
                                            <input type="radio" name="bird_strike_nr_struck" value="1" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['bird_strike_nr_struck'] ?? '') === '1' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">1</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="bird_strike_nr_struck" value="2-10" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['bird_strike_nr_struck'] ?? '') === '2-10' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">2-10</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="bird_strike_nr_struck" value="11-100" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['bird_strike_nr_struck'] ?? '') === '11-100' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">11-100</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="bird_strike_nr_struck" value="More" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['bird_strike_nr_struck'] ?? '') === 'More' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">More</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column - Damage Description -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Other Information e.g. Damage Caused by The Birds
                                </label>
                                <textarea name="bird_strike_damage_description" rows="8"
                                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                          placeholder="Describe any damage caused by the bird strike"><?php echo safeOutput($_POST['bird_strike_damage_description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Ground Found Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-wrench mr-2"></i>Ground Found (Failures Found During Pre-and Post-Flight Inspection)
                        </h2>
                        
                        <!-- Header Row -->
                        <div class="grid grid-cols-8 gap-2 mb-4">
                            <div class="col-span-3">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Name
                                </label>
                                <input type="text" name="ground_found_name"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['ground_found_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-span-3">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Location
                                </label>
                                <input type="text" name="ground_found_location"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['ground_found_location'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Shift
                                </label>
                                <input type="text" name="ground_found_shift"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo safeOutput($_POST['ground_found_shift'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Type of Occurrence / Finding -->
                        <div class="mb-4">
                            <h3 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-3">Type of Occurrence / Finding</h3>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <!-- Column 1 -->
                                <div>
                                    <div class="space-y-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ground_found_type[]" value="significant_system_failure" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo in_array('significant_system_failure', $_POST['ground_found_type'] ?? []) ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Significant System Failure</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ground_found_type[]" value="parts_missing_in_flight" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo in_array('parts_missing_in_flight', $_POST['ground_found_type'] ?? []) ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Parts Missing in Flight</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ground_found_type[]" value="transit_damage" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo in_array('transit_damage', $_POST['ground_found_type'] ?? []) ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Transit Damage</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Column 2 -->
                                <div>
                                    <div class="space-y-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ground_found_type[]" value="incorrect_parts_supplied" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo in_array('incorrect_parts_supplied', $_POST['ground_found_type'] ?? []) ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Incorrect Parts Supplied</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ground_found_type[]" value="incorrect_parts_fluids_used" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo in_array('incorrect_parts_fluids_used', $_POST['ground_found_type'] ?? []) ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Incorrect Parts / Fluids Used</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ground_found_type[]" value="incorrect_assembly_installation" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo in_array('incorrect_assembly_installation', $_POST['ground_found_type'] ?? []) ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Incorrect Assembly / Installation</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Column 3 -->
                                <div>
                                    <div class="space-y-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ground_found_type[]" value="us_on_fit" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo in_array('us_on_fit', $_POST['ground_found_type'] ?? []) ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">U/S on fit</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ground_found_type[]" value="other" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo in_array('other', $_POST['ground_found_type'] ?? []) ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Other</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Column 4 -->
                                <div>
                                    <div class="space-y-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ground_found_type[]" value="significant_damage_deterioration" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo in_array('significant_damage_deterioration', $_POST['ground_found_type'] ?? []) ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Significant Damage / Deterioration</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ground_found_type[]" value="ac_docs_out_of_compliance" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo in_array('ac_docs_out_of_compliance', $_POST['ground_found_type'] ?? []) ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">A/C Docs out of Compliance</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="checkbox" name="ground_found_type[]" value="spilling_causing_hazard_to_ac" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo in_array('spilling_causing_hazard_to_ac', $_POST['ground_found_type'] ?? []) ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Spilling Causing Hazard to A/C</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Component Details -->
                        <div>
                            <h3 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-3">Component Details</h3>
                            <div class="grid grid-cols-5 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Component(s) Description
                                    </label>
                                    <input type="text" name="ground_found_component_description"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['ground_found_component_description'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Part No.
                                    </label>
                                    <input type="text" name="ground_found_part_no"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['ground_found_part_no'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Serial No.
                                    </label>
                                    <input type="text" name="ground_found_serial_no"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['ground_found_serial_no'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        ATC Chapter
                                    </label>
                                    <input type="text" name="ground_found_atc_chapter"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['ground_found_atc_chapter'] ?? ''); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Tag No.
                                    </label>
                                    <input type="text" name="ground_found_tag_no"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['ground_found_tag_no'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Wake Turbulence Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-wind mr-2"></i>Wake Turbulence
                        </h2>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Left Column - Wake Turbulence Details -->
                            <div>
                                <!-- Heading -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Heading (Deg)
                                    </label>
                                    <input type="number" name="wake_turbulence_heading" min="0" max="360"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['wake_turbulence_heading'] ?? ''); ?>">
                                </div>
                                
                                <!-- Turning -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Turning
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="wake_turbulence_turning" value="left" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['wake_turbulence_turning'] ?? '') === 'left' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Left</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="wake_turbulence_turning" value="right" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['wake_turbulence_turning'] ?? '') === 'right' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Right</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="wake_turbulence_turning" value="no" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['wake_turbulence_turning'] ?? '') === 'no' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">No</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Position on Glide Slope -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Position on Glide Slope
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="wake_turbulence_glide_slope" value="high" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['wake_turbulence_glide_slope'] ?? '') === 'high' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">High</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="wake_turbulence_glide_slope" value="low" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['wake_turbulence_glide_slope'] ?? '') === 'low' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Low</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="wake_turbulence_glide_slope" value="on" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                   <?php echo ($_POST['wake_turbulence_glide_slope'] ?? '') === 'on' ? 'checked' : ''; ?>>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">On</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Change in Attitude -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Change in Attitude
                                    </label>
                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Pitch (Deg)</label>
                                            <input type="number" name="wake_turbulence_pitch" step="0.1"
                                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                   value="<?php echo safeOutput($_POST['wake_turbulence_pitch'] ?? ''); ?>">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Roll (Deg)</label>
                                            <input type="number" name="wake_turbulence_roll" step="0.1"
                                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                   value="<?php echo safeOutput($_POST['wake_turbulence_roll'] ?? ''); ?>">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Yaw (Deg)</label>
                                            <input type="number" name="wake_turbulence_yaw" step="0.1"
                                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                   value="<?php echo safeOutput($_POST['wake_turbulence_yaw'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Change Attitude (ft) -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Change Attitude (ft)
                                    </label>
                                    <input type="number" name="wake_turbulence_attitude_change" step="0.1"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           value="<?php echo safeOutput($_POST['wake_turbulence_attitude_change'] ?? ''); ?>">
                                </div>
                                
                                <!-- Buffet and Stick Shake -->
                                <div class="mb-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Buffet
                                            </label>
                                            <div class="flex space-x-4">
                                                <label class="flex items-center">
                                                    <input type="radio" name="wake_turbulence_buffet" value="yes" 
                                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                           <?php echo ($_POST['wake_turbulence_buffet'] ?? '') === 'yes' ? 'checked' : ''; ?>>
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                                </label>
                                                
                                                <label class="flex items-center">
                                                    <input type="radio" name="wake_turbulence_buffet" value="no" 
                                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                           <?php echo ($_POST['wake_turbulence_buffet'] ?? '') === 'no' ? 'checked' : ''; ?>>
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">No</span>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Stick Shake
                                            </label>
                                            <div class="flex space-x-4">
                                                <label class="flex items-center">
                                                    <input type="radio" name="wake_turbulence_stick_shake" value="yes" 
                                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                           <?php echo ($_POST['wake_turbulence_stick_shake'] ?? '') === 'yes' ? 'checked' : ''; ?>>
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                                </label>
                                                
                                                <label class="flex items-center">
                                                    <input type="radio" name="wake_turbulence_stick_shake" value="no" 
                                                           class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400"
                                                           <?php echo ($_POST['wake_turbulence_stick_shake'] ?? '') === 'no' ? 'checked' : ''; ?>>
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">No</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Other Info -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Other Info
                                    </label>
                                    <textarea name="wake_turbulence_other_info" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                              placeholder="Additional information about wake turbulence"><?php echo safeOutput($_POST['wake_turbulence_other_info'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Right Column - Short General Description -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Short General Description of Occurrences
                                </label>
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md mb-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                        <strong>Note:</strong> Please state only the simple facts known about the events in chronological order and relevant things that can not be covered by other boxes on this form.
                                    </p>
                                </div>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Events: 1.
                                        </label>
                                        <textarea name="wake_turbulence_events_1" rows="4"
                                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                  placeholder="Describe the first event"><?php echo safeOutput($_POST['wake_turbulence_events_1'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Events: 2.
                                        </label>
                                        <textarea name="wake_turbulence_events_2" rows="4"
                                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                  placeholder="Describe the second event"><?php echo safeOutput($_POST['wake_turbulence_events_2'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Events: 3.
                                        </label>
                                        <textarea name="wake_turbulence_events_3" rows="4"
                                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                  placeholder="Describe the third event"><?php echo safeOutput($_POST['wake_turbulence_events_3'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Airprox / ATC Procedural / TCAS RA Section -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-plane-collision mr-2"></i>Airprox / ATC Procedural / TCAS RA
                        </h2>
                        
                        <!-- Short General Description of Occurrence Continued -->
                        <div class="mb-6">
                            <h3 class="text-md font-medium text-gray-900 dark:text-white mb-2">
                                Short General Description of Occurrence Continued
                            </h3>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md mb-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    <strong>Note:</strong> Please state only the simple facts known about the events in chronological order and relevant things that can not be covered by other boxes on this form.
                                </p>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Events
                                </label>
                                <textarea name="airprox_events" rows="6"
                                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                          placeholder="Describe the events in chronological order"></textarea>
                            </div>
                        </div>

                        <!-- Airprox Grid Section -->
                        <div class="mb-6">
                            <h3 class="text-md font-medium text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-chart-line mr-2"></i>Airprox Grid
                            </h3>
                            
                            <!-- داخل فرم Tailwind خودت قرار بده -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-800 dark:text-gray-200">Airprox Grid</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Click on any cell in each view to select it. (Optional - you can select only one)
                                </p>

                                <!-- فیلد خروجی برای POST -->
                                <input type="hidden" name="selected_above" id="selected_above">
                                <input type="hidden" name="selected_astern" id="selected_astern">

                                <div class="grid gap-6 md:grid-cols-2">
                                    <!-- VIEW FROM ABOVE -->
                                    <div class="border-2 border-gray-300 dark:border-gray-600 rounded-md p-3 relative">

                                        <div id="gridAbove" class="relative mx-auto w-full max-w-[560px] aspect-[4/3] border-2 border-gray-300 dark:border-gray-600">
                                            <!-- پس‌زمینه‌ی گرید -->
                                            <div class="absolute inset-0"
                                                 style="background:
                                                   linear-gradient(90deg,#b7bec7 1px,transparent 1px) 0 0/100px 100px,
                                                   linear-gradient(#b7bec7 1px,transparent 1px) 0 0/100px 100px,
                                                   linear-gradient(90deg,#d9dde2 1px,transparent 1px) 0 0/20px 20px,
                                                   linear-gradient(#d9dde2 1px,transparent 1px) 0 0/20px 20px,#fff;">
                                </div>

                                            <!-- آیکون هواپیما (نمای بالا) -->
                                            <svg class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-16 h-16 opacity-90 fill-black"
                                                 viewBox="0 0 24 24">
                                                <!-- fuselage -->
                                                <rect x="10.5" y="2" width="3" height="13" rx="1.5"/>
                                                <!-- wings -->
                                                <path d="M2 14L12 10v4L2 20zM22 14L12 10v4l10 6z"/>
                                                <!-- tail (vertical + horizontal) -->
                                                <path d="M9 20l3-6 3 6H9z"/>
                                                <!-- small tail tip -->
                                                <path d="M11.25 21.5h1.5L12 23z"/>
                                            </svg>

                                            <!-- شماره‌های محور افقی بالا -->
                                            <div class="absolute -top-6 left-0 right-0 flex justify-between text-xs text-gray-800 dark:text-gray-200 font-mono">
                                                <span>14</span><span>13</span><span>12</span><span>11</span><span>10</span><span>9</span><span>8</span><span>7</span><span>6</span><span>5</span><span>4</span><span>3</span><span>2</span><span>1</span><span>0</span><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span><span>6</span><span>7</span><span>8</span><span>9</span><span>10</span><span>11</span><span>12</span><span>13</span><span>14</span>
                                </div>

                                            <!-- شماره‌های محور افقی پایین -->
                                            <div class="absolute -bottom-6 left-0 right-0 flex justify-between text-xs text-gray-800 dark:text-gray-200 font-mono">
                                                <span>14</span><span>13</span><span>12</span><span>11</span><span>10</span><span>9</span><span>8</span><span>7</span><span>6</span><span>5</span><span>4</span><span>3</span><span>2</span><span>1</span><span>0</span><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span><span>6</span><span>7</span><span>8</span><span>9</span><span>10</span><span>11</span><span>12</span><span>13</span><span>14</span>
                                            </div>

                                            <!-- لایه‌ی کلیک‌پذیر سلول‌ها -->
                                            <div data-view="ABOVE"
                                                 class="absolute inset-0 grid"
                                                 style="grid-template-columns:repeat(29,1fr);grid-template-rows:repeat(21,1fr)"></div>

                                            <!-- شماره‌های محور عمودی راست -->
                                            <div class="absolute -right-8 top-0 bottom-0 flex flex-col justify-between text-xs text-gray-800 dark:text-gray-200 font-mono">
                                                <span>300</span><span>270</span><span>240</span><span>210</span><span>180</span><span>150</span><span>120</span><span>90</span><span>60</span><span>30</span><span>0</span><span>30</span><span>60</span><span>90</span><span>120</span><span>150</span><span>180</span><span>210</span><span>240</span><span>270</span><span>300</span>
                                            </div>

                                            <!-- شماره‌های محور عمودی چپ -->
                                            <div class="absolute -left-8 top-0 bottom-0 flex flex-col justify-between text-xs text-gray-800 dark:text-gray-200 font-mono">
                                                <span>300</span><span>270</span><span>240</span><span>210</span><span>180</span><span>150</span><span>120</span><span>90</span><span>60</span><span>30</span><span>0</span><span>30</span><span>60</span><span>90</span><span>120</span><span>150</span><span>180</span><span>210</span><span>240</span><span>270</span><span>300</span>
                                            </div>

                                            <!-- برچسب عمودی -->
                                            <div class="absolute -right-24 top-1/2 -translate-y-1/2 -rotate-90 text-xs text-gray-800 dark:text-gray-200">Hundreds of FEET</div>
                                        </div>
                                    </div>

                                    <!-- VIEW FROM ASTERN -->
                                    <div class="border-2 border-gray-300 dark:border-gray-600 rounded-md p-3 relative">

                                        <div id="gridAstern" class="relative mx-auto w-full max-w-[560px] aspect-[4/3] border-2 border-gray-300 dark:border-gray-600">
                                            <div class="absolute inset-0"
                                                 style="background:
                                                   linear-gradient(90deg,#b7bec7 1px,transparent 1px) 0 0/100px 100px,
                                                   linear-gradient(#b7bec7 1px,transparent 1px) 0 0/100px 100px,
                                                   linear-gradient(90deg,#d9dde2 1px,transparent 1px) 0 0/20px 20px,
                                                   linear-gradient(#d9dde2 1px,transparent 1px) 0 0/20px 20px,#fff;">
                                            </div>

                                            <!-- آیکون هواپیما (نمای روبرو) -->
                                            <svg class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-16 h-16 opacity-90 fill-black"
                                                 viewBox="0 0 24 24">
                                                <!-- tailplane (T-tail) -->
                                                <rect x="6.5" y="3.5" width="11" height="1.5" rx="0.75" />
                                                <!-- vertical tail / tail post -->
                                                <rect x="11" y="5" width="2" height="4.5" rx="0.8" />
                                                <!-- fuselage (nose/cockpit bulge) -->
                                                <circle cx="12" cy="12" r="4.6" />
                                                <!-- wing (slightly thick bar across) -->
                                                <path d="M2.2 11.2h19.6c.7 0 1.2.5 1.2 1.2v.4c0 .7-.5 1.2-1.2 1.2H2.2c-.7 0-1.2-.5-1.2-1.2v-.4c0-.7.5-1.2 1.2-1.2z"/>
                                                <!-- gear/engine pods -->
                                                <circle cx="5" cy="16.6" r="2" />
                                                <circle cx="19" cy="16.6" r="2" />
                                            </svg>

                                            <!-- شماره‌های محور افقی بالا -->
                                            <div class="absolute -top-6 left-0 right-0 flex justify-between text-xs text-gray-800 dark:text-gray-200 font-mono">
                                                <span>14</span><span>13</span><span>12</span><span>11</span><span>10</span><span>9</span><span>8</span><span>7</span><span>6</span><span>5</span><span>4</span><span>3</span><span>2</span><span>1</span><span>0</span><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span><span>6</span><span>7</span><span>8</span><span>9</span><span>10</span><span>11</span><span>12</span><span>13</span><span>14</span>
                                            </div>

                                            <!-- شماره‌های محور افقی پایین -->
                                            <div class="absolute -bottom-6 left-0 right-0 flex justify-between text-xs text-gray-800 dark:text-gray-200 font-mono">
                                                <span>14</span><span>13</span><span>12</span><span>11</span><span>10</span><span>9</span><span>8</span><span>7</span><span>6</span><span>5</span><span>4</span><span>3</span><span>2</span><span>1</span><span>0</span><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span><span>6</span><span>7</span><span>8</span><span>9</span><span>10</span><span>11</span><span>12</span><span>13</span><span>14</span>
                                            </div>

                                            <div data-view="ASTERN"
                                                 class="absolute inset-0 grid"
                                                 style="grid-template-columns:repeat(29,1fr);grid-template-rows:repeat(21,1fr)"></div>

                                            <!-- شماره‌های محور عمودی راست -->
                                            <div class="absolute -right-8 top-0 bottom-0 flex flex-col justify-between text-xs text-gray-800 dark:text-gray-200 font-mono">
                                                <span>300</span><span>270</span><span>240</span><span>210</span><span>180</span><span>150</span><span>120</span><span>90</span><span>60</span><span>30</span><span>0</span><span>30</span><span>60</span><span>90</span><span>120</span><span>150</span><span>180</span><span>210</span><span>240</span><span>270</span><span>300</span>
                                            </div>

                                            <!-- شماره‌های محور عمودی چپ -->
                                            <div class="absolute -left-8 top-0 bottom-0 flex flex-col justify-between text-xs text-gray-800 dark:text-gray-200 font-mono">
                                                <span>300</span><span>270</span><span>240</span><span>210</span><span>180</span><span>150</span><span>120</span><span>90</span><span>60</span><span>30</span><span>0</span><span>30</span><span>60</span><span>90</span><span>120</span><span>150</span><span>180</span><span>210</span><span>240</span><span>270</span><span>300</span>
                                            </div>

                                            <div class="absolute -right-20 top-1/2 -translate-y-1/2 -rotate-90 text-xs text-gray-800 dark:text-gray-200">METRES</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Airprox / ATC Procedural / TCAS RA Details -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Left Column -->
                            <div>
                                <!-- Severity of Risk to A/C -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Severity of Risk to A/C
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="severity_risk" value="nil" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Nil</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="severity_risk" value="low" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Low</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="severity_risk" value="med" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Med</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="severity_risk" value="high" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">High</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Avoiding Action Taken -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Avoiding Action Taken
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="avoiding_action_taken" value="yes" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="avoiding_action_taken" value="no" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">No</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Minimum Vertical Separation -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Minimum Vertical Separation (Ft)
                                    </label>
                                    <input type="number" name="minimum_vertical_separation" step="0.1"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Enter vertical separation in feet">
                                </div>
                                
                                <!-- Reported to ATC -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Reported to ATC Unit
                                    </label>
                                    <input type="text" name="reported_to_atc"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Enter ATC unit name">
                                </div>
                                
                                <!-- Minimum Horizontal Separation -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Minimum Horizontal Separation (N/NM)
                                    </label>
                                    <input type="number" name="minimum_horizontal_separation" step="0.1"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Enter horizontal separation">
                                </div>
                                
                                <!-- ATC Instructions Issued -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        ATC Instructions Issued
                                    </label>
                                    <input type="text" name="atc_instructions_issued"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Enter ATC instructions">
                                </div>
                                
                                <!-- Frequency in Use -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Frequency in Use
                                    </label>
                                    <input type="text" name="frequency_in_use"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Enter frequency">
                                </div>
                                
                                <!-- Heading -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Heading (Deg)
                                    </label>
                                    <input type="number" name="airprox_heading" min="0" max="360"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Enter heading in degrees">
                                </div>
                                
                                <!-- Cleared Altitude / FL -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Cleared Altitude / FL
                                    </label>
                                    <input type="text" name="cleared_altitude_fl"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Enter cleared altitude or flight level">
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div>
                                <!-- TCAS Alert -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        TCAS Alert
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="tcas_alert" value="ra" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">RA</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="tcas_alert" value="ta" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">TA</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="tcas_alert" value="none" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">None</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Types of RA -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Types of RA
                                    </label>
                                    <input type="text" name="types_of_ra"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           placeholder="Enter types of RA">
                                </div>
                                
                                <!-- RA Followed -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        RA Followed
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="ra_followed" value="yes" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="ra_followed" value="no" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">No</span>
                                        </label>
                                    </div>
                                    
                                    <!-- Vertical Deviation -->
                                    <div class="mt-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Vertical Deviation (Ft)
                                        </label>
                                        <input type="number" name="vertical_deviation" step="0.1"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               placeholder="Enter vertical deviation">
                                    </div>
                                </div>
                                
                                <!-- TCAS Alert WAS -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        TCAS Alert WAS
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="tcas_alert_was" value="necessary" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Necessary</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="tcas_alert_was" value="useful" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Useful</span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <input type="radio" name="tcas_alert_was" value="nuisance" 
                                                   class="mr-2 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-400">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Nuisance</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Signature Section -->
                                <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-md">
                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Signature</h4>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Name
                                            </label>
                                            <input type="text" name="airprox_signature_name"
                                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="Enter name">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Signature
                                            </label>
                                            <input type="text" name="airprox_signature"
                                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="Enter signature">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Date
                                            </label>
                                            <input type="date" name="airprox_signature_date"
                                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-4 pt-6">
                        <button type="submit" class="btn-primary px-6 py-2 text-sm font-medium">
                            <i class="fas fa-save mr-2"></i>Create ASR Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Show/hide "Other" fields based on selection
        document.addEventListener('DOMContentLoaded', function() {
            // Note: Aircraft Registration is now independent of Aircraft Type selection
            // All registrations are loaded from the database on page load
        });
        
        // Function to format time input (HHMM format)
        function formatTimeInput(input) {
            let value = input.value.replace(/\D/g, ''); // Remove non-digits
            
            // Limit to 4 digits
            if (value.length > 4) {
                value = value.substring(0, 4);
            }
            
            // Validate hours (00-23)
            if (value.length >= 2) {
                const hours = parseInt(value.substring(0, 2));
                if (hours > 23) {
                    value = '23' + value.substring(2);
                }
            }
            
            // Validate minutes (00-59)
            if (value.length >= 4) {
                const minutes = parseInt(value.substring(2, 4));
                if (minutes > 59) {
                    value = value.substring(0, 2) + '59';
                }
            }
            
            input.value = value;
        }

        // Airprox Grid functionality
        const COL_MAX = 14;  // -14..+14 => 29
        const ROW_MAX = 10;  // -10..+10 => 21
        const state = { ABOVE: null, ASTERN: null };

        // بساز سلول‌ها برای هر گرید
        document.querySelectorAll('[data-view]').forEach(layer => {
            const view = layer.dataset.view;
            for (let r = ROW_MAX; r >= -ROW_MAX; r--) {
                for (let c = -COL_MAX; c <= COL_MAX; c++) {
                    const cell = document.createElement('div');
                    cell.dataset.name = `${view}_${c}_${r}`;
                    cell.className =
                        "cursor-pointer hover:outline hover:outline-2 hover:outline-dashed hover:outline-slate-500";
                    cell.addEventListener('click', () => pick(cell, view));
                    layer.appendChild(cell);
                }
            }
        });

        function pick(cell, view){
            // پاک‌کردن انتخاب قبلی
            if (state[view]) state[view].classList.remove('!bg-purple-100','!outline','!outline-2','!outline-purple-700');
            // اعمال انتخاب
            cell.classList.add('!bg-purple-100','!outline','!outline-2','!outline-purple-700');
            state[view] = cell;
            // مقدار POST
            const id = (view === 'ABOVE') ? 'selected_above' : 'selected_astern';
            document.getElementById(id).value = cell.dataset.name;
        }
    </script>
</body>
</html>
