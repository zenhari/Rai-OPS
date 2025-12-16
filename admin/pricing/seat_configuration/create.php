<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/pricing/seat_configuration/create.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get all flight classes
$flightClasses = getAllFlightClasses();

// Check if layout is configured
$layoutConfigured = false;
$rows = isset($_GET['rows']) ? intval($_GET['rows']) : (isset($_POST['rows']) ? intval($_POST['rows']) : 0);
$seatsPerRow = isset($_GET['seats_per_row']) ? intval($_GET['seats_per_row']) : (isset($_POST['seats_per_row']) ? intval($_POST['seats_per_row']) : 0);
$rowLayouts = [];
$rowLabels = [];

// Get row layouts from GET or POST
if (isset($_GET['row_layouts']) && is_array($_GET['row_layouts'])) {
    $rowLayouts = $_GET['row_layouts'];
} elseif (isset($_POST['row_layouts']) && is_array($_POST['row_layouts'])) {
    $rowLayouts = $_POST['row_layouts'];
}

// Get row labels from GET or POST
if (isset($_GET['row_labels']) && is_array($_GET['row_labels'])) {
    $rowLabels = $_GET['row_labels'];
} elseif (isset($_POST['row_labels']) && is_array($_POST['row_labels'])) {
    $rowLabels = $_POST['row_labels'];
} elseif ($rows > 0 && $seatsPerRow > 0) {
    // Generate default labels (A, B, C, ...)
    for ($i = 0; $i < $seatsPerRow; $i++) {
        $rowLabels[] = chr(65 + $i); // A=65, B=66, etc.
    }
}

if ($rows > 0 && $seatsPerRow > 0 && count($rowLabels) == $seatsPerRow && !empty($rowLayouts)) {
    $layoutConfigured = true;
    $totalSeats = $rows * $seatsPerRow;
}

// Handle initial layout setup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['setup_layout'])) {
    $rows = intval($_POST['rows'] ?? 0);
    $seatsPerRow = intval($_POST['seats_per_row'] ?? 0);
    $rowLabels = [];
    $rowLayouts = [];
    
    // Get row labels from form
    if (isset($_POST['row_labels']) && is_array($_POST['row_labels'])) {
        foreach ($_POST['row_labels'] as $label) {
            $label = trim($label);
            if (!empty($label)) {
                $rowLabels[] = $label;
            }
        }
    }
    
    // Get row layouts from form
    if (isset($_POST['row_layouts']) && is_array($_POST['row_layouts'])) {
        foreach ($_POST['row_layouts'] as $rowNum => $layout) {
            $layout = trim($layout);
            if ($layout === 'custom') {
                // Get custom layout for this row
                if (isset($_POST['row_custom_layouts'][$rowNum])) {
                    $layout = trim($_POST['row_custom_layouts'][$rowNum]);
                } else {
                    $layout = '';
                }
            }
            if (!empty($layout)) {
                $rowLayouts[$rowNum] = $layout;
            }
        }
    }
    
    if ($rows < 1 || $rows > 50) {
        $error = 'Number of rows must be between 1 and 50.';
    } elseif ($seatsPerRow < 1 || $seatsPerRow > 10) {
        $error = 'Number of seats per row must be between 1 and 10.';
    } elseif (count($rowLabels) != $seatsPerRow) {
        $error = 'Please provide a label for each seat position.';
    } elseif (empty($rowLayouts)) {
        $error = 'Please configure seat layout for at least one row.';
    } else {
        // Build URL with parameters
        $params = ['rows' => $rows, 'seats_per_row' => $seatsPerRow];
        foreach ($rowLabels as $index => $label) {
            $params['row_labels[' . $index . ']'] = urlencode($label);
        }
        foreach ($rowLayouts as $rowNum => $layout) {
            $params['row_layouts[' . $rowNum . ']'] = urlencode($layout);
        }
        $queryString = http_build_query($params);
        header('Location: create.php?' . $queryString);
        exit();
    }
}

// Handle form submission (final save)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_configuration'])) {
    $name = trim($_POST['name'] ?? '');
    $seatConfiguration = json_decode($_POST['seat_configuration'] ?? '[]', true);
    $rows = intval($_POST['rows'] ?? 0);
    $seatsPerRow = intval($_POST['seats_per_row'] ?? 0);
    $rowLabels = [];
    if (isset($_POST['row_labels']) && is_array($_POST['row_labels'])) {
        foreach ($_POST['row_labels'] as $label) {
            $rowLabels[] = trim($label);
        }
    }
    
    // Validation
    if (empty($name)) {
        $error = 'Configuration name is required.';
    } elseif ($rows < 1 || $seatsPerRow < 1) {
        $error = 'Invalid layout configuration.';
    } elseif (empty($seatConfiguration)) {
        $error = 'Please assign flight classes to at least one seat.';
    } else {
        $totalSeats = $rows * $seatsPerRow;
        
        if (addAircraftSeatConfiguration($name, $totalSeats, $rows, $seatsPerRow, $seatConfiguration, $current_user['id'])) {
            $message = 'Seat configuration saved successfully.';
            // Redirect after 1 second
            header('Refresh: 1; url=index.php');
        } else {
            $error = 'Failed to save seat configuration.';
        }
    }
}

// Prepare flight classes for JavaScript
$flightClassesJson = json_encode($flightClasses);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Seat Configuration - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        
        .aircraft-container {
            position: relative;
            width: 100%;
            max-width: 1800px;
            margin: 0 auto;
            padding: 30px;
            overflow-x: auto;
            overflow-y: visible;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        
        html.dark .aircraft-container,
        .dark .aircraft-container {
            background: #1a1d24 !important;
            border-color: #2d3139 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .aircraft-container {
                background: #1a1d24;
                border-color: #2d3139;
            }
        }
        
        /* Professional Aircraft Body Design */
        .aircraft-body {
            background: #ffffff;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 40px 50px;
            position: relative;
            min-height: 350px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .aircraft-body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #495057;
        }
        
        html.dark .aircraft-body,
        .dark .aircraft-body {
            background: #252932 !important;
            border-color: #3a3f47 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
        }
        
        html.dark .aircraft-body::before,
        .dark .aircraft-body::before {
            background: #6c757d !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .aircraft-body {
                background: #252932;
                border-color: #3a3f47;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            }
            html:not(.light) .aircraft-body::before {
                background: #6c757d;
            }
        }
        
        /* Professional Front Indicator */
        .aircraft-front-indicator {
            position: absolute;
            left: 15px;
            top: 25px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            z-index: 10;
        }
        
        .front-arrow-icon {
            font-size: 16px;
            color: #495057;
        }
        
        html.dark .front-arrow-icon,
        .dark .front-arrow-icon {
            color: #adb5bd !important;
        }
        
        .front-label {
            font-size: 10px;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        html.dark .front-label,
        .dark .front-label {
            color: #adb5bd !important;
            background: #343a40 !important;
            border-color: #495057 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .front-arrow-icon {
                color: #adb5bd;
            }
            html:not(.light) .front-label {
                color: #adb5bd;
                background: #343a40;
                border-color: #495057;
            }
        }
        
        /* Professional Rear Indicator */
        .aircraft-rear-indicator {
            position: absolute;
            right: 15px;
            top: 25px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            z-index: 10;
        }
        
        .rear-arrow-icon {
            font-size: 16px;
            color: #495057;
        }
        
        html.dark .rear-arrow-icon,
        .dark .rear-arrow-icon {
            color: #adb5bd !important;
        }
        
        .rear-label {
            font-size: 10px;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        html.dark .rear-label,
        .dark .rear-label {
            color: #adb5bd !important;
            background: #343a40 !important;
            border-color: #495057 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .rear-arrow-icon {
                color: #adb5bd;
            }
            html:not(.light) .rear-label {
                color: #adb5bd;
                background: #343a40;
                border-color: #495057;
            }
        }
        
        /* Professional Seat Rows Container */
        .seat-rows-container {
            position: relative;
            z-index: 2;
            margin: 20px 0;
            padding: 20px 0;
        }
        
        .seat-row {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 50px;
            flex-wrap: nowrap;
            overflow-x: visible;
            padding: 8px 0;
            position: relative;
        }
        
        .seat-row::after {
            content: '';
            position: absolute;
            bottom: -35px;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, #e5e7eb 20%, #e5e7eb 80%, transparent 100%);
        }
        
        .dark .seat-row::after {
            background: linear-gradient(90deg, transparent 0%, #4b5563 20%, #4b5563 80%, transparent 100%);
        }
        
        .seat-row:last-child::after {
            display: none;
        }
        
        .row-label {
            width: 50px;
            text-align: center;
            font-weight: 600;
            color: #212529;
            font-size: 13px;
            flex-shrink: 0;
            background: #f8f9fa;
            padding: 6px 4px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            position: relative;
        }
        
        .row-label::before {
            content: '\f236';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: -20px;
            transform: translateY(-50%);
            font-size: 12px;
            color: #6c757d;
        }
        
        html.dark .row-label,
        .dark .row-label {
            color: #e9ecef !important;
            background: #343a40 !important;
            border-color: #495057 !important;
        }
        
        html.dark .row-label::before,
        .dark .row-label::before {
            color: #adb5bd !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .row-label {
                color: #e9ecef;
                background: #343a40;
                border-color: #495057;
            }
            html:not(.light) .row-label::before {
                color: #adb5bd;
            }
        }
        
        .aisle-row {
            display: none;
        }
        
        .aisle-label {
            display: none;
        }
        
        .aisle-spacer {
            width: 25px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 12px;
            border-left: 2px dashed #ced4da;
            border-right: 2px dashed #ced4da;
            height: 70px;
            background: #f8f9fa;
            position: relative;
        }
        
        .aisle-spacer::before {
            content: '';
            position: absolute;
            width: 2px;
            height: 100%;
            background: #ced4da;
            left: 50%;
            transform: translateX(-50%);
        }
        
        html.dark .aisle-spacer,
        .dark .aisle-spacer {
            color: #adb5bd !important;
            border-color: #495057 !important;
            background: #343a40 !important;
        }
        
        html.dark .aisle-spacer::before,
        .dark .aisle-spacer::before {
            background: #495057 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .aisle-spacer {
                color: #adb5bd;
                border-color: #495057;
                background: #343a40;
            }
            html:not(.light) .aisle-spacer::before {
                background: #495057;
            }
        }
        
        /* Professional Seat Design */
        .seat {
            width: 65px;
            height: 65px;
            border: 2px solid #ced4da;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.2s ease;
            position: relative;
            background: #ffffff;
            color: #212529;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: visible;
        }
        
        .seat::before {
            content: '\f236';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 6px;
            font-size: 14px;
            color: #6c757d;
            opacity: 0.4;
        }
        
        html.dark .seat,
        .dark .seat {
            background: #343a40 !important;
            border-color: #495057 !important;
            color: #e9ecef !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3) !important;
        }
        
        html.dark .seat::before,
        .dark .seat::before {
            color: #adb5bd !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .seat {
                background: #343a40;
                border-color: #495057;
                color: #e9ecef;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            }
            html:not(.light) .seat::before {
                color: #adb5bd;
            }
        }
        
        .seat:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            border-color: #495057;
            background: #f8f9fa;
        }
        
        html.dark .seat:hover,
        .dark .seat:hover {
            background: #495057 !important;
            border-color: #6c757d !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .seat:hover {
                background: #495057;
                border-color: #6c757d;
            }
        }
        
        .seat.selected {
            border-width: 3px;
            border-color: #ffc107;
            box-shadow: 
                0 0 0 2px rgba(255, 193, 7, 0.2),
                0 4px 8px rgba(0, 0, 0, 0.15);
            background: #fff8e1;
            transform: scale(1.05);
        }
        
        html.dark .seat.selected,
        .dark .seat.selected {
            background: #664d03 !important;
            border-color: #ffc107 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .seat.selected {
                background: #664d03;
                border-color: #ffc107;
            }
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.6) 0%, rgba(0, 0, 0, 0.8) 100%);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease, backdrop-filter 0.3s ease;
        }
        
        .modal-overlay.active {
            display: flex;
            opacity: 1;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-content {
            background: #ffffff;
            border-radius: 8px;
            padding: 0;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid #e5e7eb;
            transform: scale(0.95);
            transition: transform 0.3s ease;
            animation: slideUp 0.3s ease;
        }
        
        .modal-overlay.active .modal-content {
            transform: scale(1);
        }
        
        html.dark .modal-content,
        .dark .modal-content {
            background: #1f2937 !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4) !important;
            border-color: #374151 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .modal-content {
                background: #1f2937;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
                border-color: #374151;
            }
        }
        
        .modal-header {
            padding: 1.25rem 1.5rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            color: #111827;
        }
        
        html.dark .modal-header,
        .dark .modal-header {
            background: #111827 !important;
            border-bottom-color: #374151 !important;
            color: #f9fafb !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .modal-header {
                background: #111827;
                border-bottom-color: #374151;
                color: #f9fafb;
            }
        }
        
        .modal-header-content {
            position: relative;
        }
        
        .modal-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #111827;
        }
        
        html.dark .modal-header h3,
        .dark .modal-header h3 {
            color: #f9fafb !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .modal-header h3 {
                color: #f9fafb;
            }
        }
        
        .modal-header h3 i {
            font-size: 1rem;
            color: #6b7280;
        }
        
        html.dark .modal-header h3 i,
        .dark .modal-header h3 i {
            color: #9ca3af !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .modal-header h3 i {
                color: #9ca3af;
            }
        }
        
        .modal-header p {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        html.dark .modal-header p,
        .dark .modal-header p {
            color: #9ca3af !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .modal-header p {
                color: #9ca3af;
            }
        }
        
        .modal-header .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            background: #e5e7eb;
            padding: 0.375rem 0.75rem;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            color: #374151;
        }
        
        html.dark .modal-header .badge,
        .dark .modal-header .badge {
            background: #374151 !important;
            color: #d1d5db !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .modal-header .badge {
                background: #374151;
                color: #d1d5db;
            }
        }
        
        .modal-header .badge i {
            font-size: 0.75rem;
        }
        
        .modal-body {
            padding: 1.5rem;
            background: white;
        }
        
        html.dark .modal-body,
        .dark .modal-body {
            background: #1f2937 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .modal-body {
                background: #1f2937;
            }
        }
        
        .modal-section {
            margin-bottom: 1.25rem;
        }
        
        .modal-section:last-child {
            margin-bottom: 0;
        }
        
        .modal-section-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        html.dark .modal-section-label,
        .dark .modal-section-label {
            color: #9ca3af !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .modal-section-label {
                color: #9ca3af;
            }
        }
        
        .modal-section-label i {
            font-size: 0.875rem;
        }
        
        .selected-seats-list {
            max-height: 150px;
            overflow-y: auto;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            min-height: 60px;
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
            align-items: flex-start;
        }
        
        html.dark .selected-seats-list,
        .dark .selected-seats-list {
            background: #111827 !important;
            border-color: #374151 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .selected-seats-list {
                background: #111827;
                border-color: #374151;
            }
        }
        
        .selected-seats-list.empty {
            align-items: center;
            justify-content: center;
        }
        
        .selected-seats-list.empty p {
            color: #9ca3af;
            font-size: 0.875rem;
            font-style: italic;
        }
        
        html.dark .selected-seats-list.empty p,
        .dark .selected-seats-list.empty p {
            color: #6b7280 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .selected-seats-list.empty p {
                color: #6b7280;
            }
        }
        
        .selected-seat-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            margin: 0;
            background: #e5e7eb;
            color: #374151;
            border-radius: 4px;
            font-size: 0.8125rem;
            font-weight: 500;
            border: 1px solid #d1d5db;
        }
        
        html.dark .selected-seat-tag,
        .dark .selected-seat-tag {
            background: #374151 !important;
            color: #d1d5db !important;
            border-color: #4b5563 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .selected-seat-tag {
                background: #374151;
                color: #d1d5db;
                border-color: #4b5563;
            }
        }
        
        .selected-seat-tag i {
            font-size: 0.6875rem;
        }
        
        .flight-class-select-wrapper {
            position: relative;
        }
        
        #flightClassSelect {
            width: 100%;
            padding: 0.625rem 0.875rem;
            padding-right: 2.5rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            font-size: 0.875rem;
            font-weight: 400;
            color: #111827;
            transition: all 0.2s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.625rem center;
            background-size: 1rem;
        }
        
        #flightClassSelect:focus {
            outline: none;
            border-color: #6b7280;
            box-shadow: 0 0 0 3px rgba(107, 114, 128, 0.1);
        }
        
        html.dark #flightClassSelect,
        .dark #flightClassSelect {
            background: #111827 !important;
            border-color: #4b5563 !important;
            color: #f9fafb !important;
        }
        
        html.dark #flightClassSelect:focus,
        .dark #flightClassSelect:focus {
            border-color: #6b7280 !important;
            box-shadow: 0 0 0 3px rgba(107, 114, 128, 0.2) !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) #flightClassSelect {
                background: #111827;
                border-color: #4b5563;
                color: #f9fafb;
            }
            html:not(.light) #flightClassSelect:focus {
                border-color: #6b7280;
                box-shadow: 0 0 0 3px rgba(107, 114, 128, 0.2);
            }
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }
        
        html.dark .modal-footer,
        .dark .modal-footer {
            background: #111827 !important;
            border-top-color: #374151 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .modal-footer {
                background: #111827;
                border-top-color: #374151;
            }
        }
        
        .modal-btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            border: 1px solid;
            cursor: pointer;
        }
        
        .modal-btn-cancel {
            background: white;
            color: #6b7280;
            border-color: #d1d5db;
        }
        
        .modal-btn-cancel:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }
        
        html.dark .modal-btn-cancel,
        .dark .modal-btn-cancel {
            background: #374151 !important;
            color: #d1d5db !important;
            border-color: #4b5563 !important;
        }
        
        html.dark .modal-btn-cancel:hover,
        .dark .modal-btn-cancel:hover {
            background: #4b5563 !important;
            border-color: #6b7280 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .modal-btn-cancel {
                background: #374151;
                color: #d1d5db;
                border-color: #4b5563;
            }
            html:not(.light) .modal-btn-cancel:hover {
                background: #4b5563;
                border-color: #6b7280;
            }
        }
        
        .modal-btn-primary {
            background: #374151;
            color: white;
            border-color: #374151;
        }
        
        .modal-btn-primary:hover {
            background: #4b5563;
            border-color: #4b5563;
        }
        
        html.dark .modal-btn-primary,
        .dark .modal-btn-primary {
            background: #4b5563 !important;
            border-color: #4b5563 !important;
        }
        
        html.dark .modal-btn-primary:hover,
        .dark .modal-btn-primary:hover {
            background: #6b7280 !important;
            border-color: #6b7280 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .modal-btn-primary {
                background: #4b5563;
                border-color: #4b5563;
            }
            html:not(.light) .modal-btn-primary:hover {
                background: #6b7280;
                border-color: #6b7280;
            }
        }
        
        .modal-btn-danger {
            background: #6b7280;
            color: white;
            border-color: #6b7280;
        }
        
        .modal-btn-danger:hover {
            background: #9ca3af;
            border-color: #9ca3af;
        }
        
        html.dark .modal-btn-danger,
        .dark .modal-btn-danger {
            background: #4b5563 !important;
            border-color: #4b5563 !important;
        }
        
        html.dark .modal-btn-danger:hover,
        .dark .modal-btn-danger:hover {
            background: #6b7280 !important;
            border-color: #6b7280 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .modal-btn-danger {
                background: #4b5563;
                border-color: #4b5563;
            }
            html:not(.light) .modal-btn-danger:hover {
                background: #6b7280;
                border-color: #6b7280;
            }
        }
        
        .seat-label {
            position: absolute;
            bottom: -22px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 9px;
            color: #495057;
            font-weight: 600;
            white-space: nowrap;
            background: #ffffff;
            padding: 2px 5px;
            border-radius: 3px;
            border: 1px solid #dee2e6;
            letter-spacing: 0.3px;
        }
        
        html.dark .seat-label,
        .dark .seat-label {
            color: #adb5bd !important;
            background: #343a40 !important;
            border-color: #495057 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .seat-label {
                color: #adb5bd;
                background: #343a40;
                border-color: #495057;
            }
        }
        
        /* When seat has flight class assigned */
        .seat[style*="background-color"] .seat-label {
            color: #212529;
            font-weight: 700;
            background: #ffffff;
            border-color: rgba(0, 0, 0, 0.15);
        }
        
        html.dark .seat[style*="background-color"] .seat-label,
        .dark .seat[style*="background-color"] .seat-label {
            color: #ffffff !important;
            background: rgba(0, 0, 0, 0.5) !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .seat[style*="background-color"] .seat-label {
                color: #ffffff;
                background: rgba(0, 0, 0, 0.5);
                border-color: rgba(255, 255, 255, 0.2);
            }
        }
        
        .flight-class-legend {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 20px 0;
            padding: 20px;
            background: #ffffff;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        html.dark .flight-class-legend,
        .dark .flight-class-legend {
            background: #1f2937 !important;
            border-color: #374151 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .flight-class-legend {
                background: #1f2937;
                border-color: #374151;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            }
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.2s ease;
            cursor: default;
        }
        
        .legend-item:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        html.dark .legend-item,
        .dark .legend-item {
            background: #374151 !important;
            border-color: #4b5563 !important;
        }
        
        html.dark .legend-item:hover,
        .dark .legend-item:hover {
            background: #4b5563 !important;
            border-color: #6b7280 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .legend-item {
                background: #374151;
                border-color: #4b5563;
            }
            html:not(.light) .legend-item:hover {
                background: #4b5563;
                border-color: #6b7280;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            }
        }
        
        .legend-color {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 3px solid #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15), 
                        inset 0 1px 2px rgba(255, 255, 255, 0.2);
            flex-shrink: 0;
            transition: all 0.2s ease;
        }
        
        .legend-item:hover .legend-color {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2), 
                        inset 0 1px 2px rgba(255, 255, 255, 0.3);
        }
        
        .dark .legend-color {
            border-color: #1f2937;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3), 
                        inset 0 1px 2px rgba(255, 255, 255, 0.1);
        }
        
        .legend-item span {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.875rem;
            letter-spacing: 0.025em;
        }
        
        html.dark .legend-item span,
        .dark .legend-item span {
            color: #f3f4f6 !important;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .legend-item span {
                color: #f3f4f6;
            }
        }
        
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Seat Configuration</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                <?php if ($layoutConfigured): ?>
                                    Configure seats: <?php echo $totalSeats; ?> seats (<?php echo $rows; ?> rows × <?php echo $seatsPerRow; ?> seats per row)
                                <?php else: ?>
                                    First, configure the seat layout dimensions
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <?php if ($layoutConfigured): ?>
                                <a href="create.php" 
                                   class="inline-flex items-center px-4 py-2 border border-orange-300 dark:border-orange-600 text-sm font-medium rounded-md text-orange-700 dark:text-orange-300 bg-orange-50 dark:bg-orange-900 hover:bg-orange-100 dark:hover:bg-orange-800">
                                    <i class="fas fa-edit mr-2"></i>
                                    Change Layout
                                </a>
                            <?php endif; ?>
                            <a href="index.php" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800 dark:text-green-200"><?php echo htmlspecialchars($message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800 dark:text-red-200"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$layoutConfigured): ?>
                    <!-- Layout Setup Form -->
                    <div class="max-w-2xl mx-auto">
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                                    <i class="fas fa-ruler-combined mr-2"></i>
                                    Configure Seat Layout
                                </h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Specify the number of rows and seats per row for your aircraft configuration
                                </p>
                            </div>
                            <form method="POST" class="px-6 py-6">
                                <input type="hidden" name="setup_layout" value="1">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label for="rows" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            <i class="fas fa-list-ol mr-2"></i>
                                            Number of Rows
                                        </label>
                                        <input type="number" 
                                               id="rows" 
                                               name="rows" 
                                               min="1" 
                                               max="50" 
                                               required
                                               value="<?php echo isset($_POST['rows']) ? htmlspecialchars($_POST['rows']) : '15'; ?>"
                                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-lg font-semibold">
                                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            Enter number of seat rows (1-50)
                                        </p>
                                    </div>
                                    <div>
                                        <label for="seats_per_row" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            <i class="fas fa-chair mr-2"></i>
                                            Seats Per Row
                                        </label>
                                        <input type="number" 
                                               id="seats_per_row" 
                                               name="seats_per_row" 
                                               min="1" 
                                               max="10" 
                                               required
                                               value="<?php echo isset($_POST['seats_per_row']) ? htmlspecialchars($_POST['seats_per_row']) : '2'; ?>"
                                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-lg font-semibold">
                                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            Enter seats per row (1-10)
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Seat Layout Configuration -->
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                        <i class="fas fa-th mr-2"></i>
                                        Seat Layout Configuration (for each row)
                                    </label>
                                    
                                    <!-- Default Layout (applies to all rows) -->
                                    <div class="mb-4">
                                        <label for="default_seat_layout" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">
                                            Default Layout (Apply to All Rows)
                                        </label>
                                        <select id="default_seat_layout" 
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm">
                                            <option value="">Select Default Layout</option>
                                            <option value="1">1 (Single)</option>
                                            <option value="2">2 (Two seats)</option>
                                            <option value="2-2">2-2 (Two seats, Aisle, Two seats)</option>
                                            <option value="3-3">3-3 (Three seats, Aisle, Three seats)</option>
                                            <option value="2-3-2">2-3-2 (Two, Aisle, Three, Aisle, Two)</option>
                                            <option value="2-4-2">2-4-2 (Two, Aisle, Four, Aisle, Two)</option>
                                            <option value="3-4-3">3-4-3 (Three, Aisle, Four, Aisle, Three)</option>
                                        </select>
                                        <button type="button" onclick="applyDefaultLayout()" class="mt-2 px-3 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600">
                                            <i class="fas fa-check mr-1"></i> Apply to All Rows
                                        </button>
                                    </div>
                                    
                                    <!-- Per-Row Layout Configuration -->
                                    <div id="rowLayoutsContainer" class="space-y-3 max-h-96 overflow-y-auto p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <!-- Dynamic row layout inputs will be added here -->
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Configure seat layout for each row. You can set a default layout and apply it to all rows, or configure each row individually.
                                    </p>
                                </div>
                                
                                <!-- Row Labels Section -->
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                        <i class="fas fa-tags mr-2"></i>
                                        Seat Position Labels (e.g., A, B, C or Window, Aisle)
                                    </label>
                                    <div id="rowLabelsContainer" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                                        <!-- Dynamic inputs will be added here -->
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Enter a label for each seat position in a row (e.g., A, B, C or Window, Aisle, Window)
                                    </p>
                                </div>
                                
                                <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6">
                                    <div class="flex items-start">
                                        <i class="fas fa-info-circle text-blue-400 mt-1 mr-3"></i>
                                        <div>
                                            <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                                Total Seats: <span id="totalSeatsPreview" class="font-bold text-lg">0</span>
                                            </p>
                                            <p class="text-xs text-blue-600 dark:text-blue-300 mt-1">
                                                This will create <span id="totalSeatsPreview2" class="font-semibold">0</span> seats for configuration
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-3">
                                    <a href="index.php"
                                       class="px-6 py-3 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <i class="fas fa-times mr-2"></i>
                                        Cancel
                                    </a>
                                    <button type="submit"
                                            class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-sm font-medium rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-lg">
                                        <i class="fas fa-arrow-right mr-2"></i>
                                        Continue to Seat Configuration
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <script>
                        document.getElementById('rows').addEventListener('input', function() {
                            updateTotalSeats();
                            updateRowLayouts();
                            updateRowLabels();
                        });
                        document.getElementById('seats_per_row').addEventListener('input', function() {
                            updateTotalSeats();
                            updateRowLayouts();
                            updateRowLabels();
                        });
                        
                        function updateTotalSeats() {
                            const rows = parseInt(document.getElementById('rows').value) || 0;
                            const seatsPerRow = parseInt(document.getElementById('seats_per_row').value) || 0;
                            const total = rows * seatsPerRow;
                            document.getElementById('totalSeatsPreview').textContent = total;
                            document.getElementById('totalSeatsPreview2').textContent = total;
                        }
                        
                        function updateRowLayouts() {
                            const rows = parseInt(document.getElementById('rows').value) || 0;
                            const container = document.getElementById('rowLayoutsContainer');
                            container.innerHTML = '';
                            
                            const layoutOptions = [
                                { value: '1', label: '1 (Single)' },
                                { value: '2', label: '2 (Two seats)' },
                                { value: '2-2', label: '2-2 (Two, Aisle, Two)' },
                                { value: '3-3', label: '3-3 (Three, Aisle, Three)' },
                                { value: '2-3-2', label: '2-3-2 (Two, Aisle, Three, Aisle, Two)' },
                                { value: '2-4-2', label: '2-4-2 (Two, Aisle, Four, Aisle, Two)' },
                                { value: '3-4-3', label: '3-4-3 (Three, Aisle, Four, Aisle, Three)' },
                                { value: 'custom', label: 'Custom' }
                            ];
                            
                            for (let rowNum = 1; rowNum <= rows; rowNum++) {
                                const rowDiv = document.createElement('div');
                                rowDiv.className = 'flex items-center gap-3 p-2 bg-white dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600';
                                rowDiv.innerHTML = `
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 w-20 flex-shrink-0">
                                        Row ${rowNum}:
                                    </label>
                                    <select name="row_layouts[${rowNum}]" 
                                            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white text-sm row-layout-select">
                                        ${layoutOptions.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('')}
                                    </select>
                                    <input type="text" 
                                           name="row_custom_layouts[${rowNum}]"
                                           placeholder="e.g., 2-3-2"
                                           pattern="[0-9]+(-[0-9]+)*"
                                           class="hidden px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:text-white text-sm w-32 row-custom-input">
                                `;
                                container.appendChild(rowDiv);
                                
                                // Add event listener for custom layout
                                const select = rowDiv.querySelector('.row-layout-select');
                                const customInput = rowDiv.querySelector('.row-custom-input');
                                
                                select.addEventListener('change', function() {
                                    if (this.value === 'custom') {
                                        customInput.classList.remove('hidden');
                                        customInput.required = true;
                                    } else {
                                        customInput.classList.add('hidden');
                                        customInput.required = false;
                                        customInput.value = '';
                                    }
                                    updateRowLabels();
                                });
                            }
                        }
                        
                        function applyDefaultLayout() {
                            const defaultLayout = document.getElementById('default_seat_layout').value;
                            if (!defaultLayout) {
                                alert('Please select a default layout first');
                                return;
                            }
                            
                            const selects = document.querySelectorAll('.row-layout-select');
                            selects.forEach(select => {
                                select.value = defaultLayout;
                                const customInput = select.parentElement.querySelector('.row-custom-input');
                                if (customInput) {
                                    customInput.classList.add('hidden');
                                    customInput.required = false;
                                    customInput.value = '';
                                }
                            });
                            updateRowLabels();
                        }
                        
                        function getAllRowLayouts() {
                            const layouts = {};
                            const rows = parseInt(document.getElementById('rows').value) || 0;
                            
                            for (let rowNum = 1; rowNum <= rows; rowNum++) {
                                const select = document.querySelector(`select[name="row_layouts[${rowNum}]"]`);
                                if (select) {
                                    if (select.value === 'custom') {
                                        const customInput = document.querySelector(`input[name="row_custom_layouts[${rowNum}]"]`);
                                        layouts[rowNum] = customInput ? customInput.value : '';
                                    } else {
                                        layouts[rowNum] = select.value;
                                    }
                                }
                            }
                            
                            return layouts;
                        }
                        
                        function updateRowLabels() {
                            const seatsPerRow = parseInt(document.getElementById('seats_per_row').value) || 0;
                            const rows = parseInt(document.getElementById('rows').value) || 0;
                            const container = document.getElementById('rowLabelsContainer');
                            container.innerHTML = '';
                            
                            // Get layout for first row (or use default)
                            const firstRowSelect = document.querySelector('select[name="row_layouts[1]"]');
                            let layout = firstRowSelect ? firstRowSelect.value : '';
                            
                            if (layout === 'custom') {
                                const customInput = document.querySelector('input[name="row_custom_layouts[1]"]');
                                layout = customInput ? customInput.value : '';
                            }
                            
                            // Parse layout to determine seat groups
                            let seatGroups = [];
                            if (layout) {
                                if (layout.includes('-')) {
                                    seatGroups = layout.split('-').map(x => parseInt(x) || 0);
                                } else {
                                    seatGroups = [parseInt(layout) || 0];
                                }
                            } else {
                                // Default: all seats in one group
                                seatGroups = [seatsPerRow];
                            }
                            
                            let labelIndex = 0;
                            seatGroups.forEach((groupSize, groupIndex) => {
                                for (let i = 0; i < groupSize; i++) {
                                    const label = String.fromCharCode(65 + labelIndex); // A, B, C, ...
                                    const div = document.createElement('div');
                                    div.innerHTML = `
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                            Position ${labelIndex + 1}
                                        </label>
                                        <input type="text" 
                                               name="row_labels[]" 
                                               value="${label}"
                                               maxlength="10"
                                               required
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm">
                                    `;
                                    container.appendChild(div);
                                    labelIndex++;
                                }
                                
                                // Add aisle indicator (except for last group)
                                if (groupIndex < seatGroups.length - 1 && seatsPerRow > 2) {
                                    const aisleDiv = document.createElement('div');
                                    aisleDiv.className = 'col-span-full flex items-center justify-center py-2';
                                    aisleDiv.innerHTML = `
                                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded">
                                            <i class="fas fa-arrows-alt-h mr-1"></i> Aisle
                                        </div>
                                    `;
                                    container.appendChild(aisleDiv);
                                }
                            });
                        }
                        
                        // Initial calculation and setup
                        updateTotalSeats();
                        updateRowLayouts();
                        updateRowLabels();
                    </script>
                <?php elseif (empty($flightClasses)): ?>
                    <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-md p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                    No flight classes found. Please <a href="../flight_class/add.php" class="underline">create flight classes</a> first.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" id="seatConfigForm">
                        <input type="hidden" name="save_configuration" value="1">
                        <input type="hidden" name="rows" value="<?php echo $rows; ?>">
                        <input type="hidden" name="seats_per_row" value="<?php echo $seatsPerRow; ?>">
                        <?php foreach ($rowLabels as $index => $label): ?>
                            <input type="hidden" name="row_labels[]" value="<?php echo htmlspecialchars($label); ?>">
                        <?php endforeach; ?>
                        <?php foreach ($rowLayouts as $rowNum => $layout): ?>
                            <input type="hidden" name="row_layouts[<?php echo $rowNum; ?>]" value="<?php echo htmlspecialchars($layout); ?>">
                        <?php endforeach; ?>
                        <!-- Configuration Name -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Configuration Name</h2>
                            </div>
                            <div class="px-6 py-4">
                                <input type="text" id="config_name" name="name" required
                                       placeholder="e.g., Standard 30-Seat Layout"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Enter a name for this seat configuration</p>
                            </div>
                        </div>

                        <!-- Flight Class Legend -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Flight Classes</h2>
                            </div>
                            <div class="px-6 py-4">
                                <div class="flight-class-legend">
                                    <?php 
                                    $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4'];
                                    $colorIndex = 0;
                                    foreach ($flightClasses as $fc): 
                                        $color = $colors[$colorIndex % count($colors)];
                                        $colorIndex++;
                                    ?>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: <?php echo $color; ?>;" data-class-id="<?php echo $fc['id']; ?>"></div>
                                            <span>
                                                <?php echo htmlspecialchars($fc['name']); ?> (<?php echo htmlspecialchars($fc['code']); ?>)
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Click on seats to select them (hold Ctrl/Cmd for multiple selection), then click "Assign Flight Class" button to open the modal.
                                </p>
                                <div class="mt-3 flex flex-wrap gap-3">
                                    <button type="button" onclick="selectAllSeats()" 
                                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <i class="fas fa-check-square mr-2"></i>
                                        Select All Seats
                                    </button>
                                    <button type="button" onclick="deselectAllSeats()" 
                                            class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                        <i class="fas fa-square mr-2"></i>
                                        Deselect All
                                    </button>
                                    <button type="button" onclick="openAssignModal()" 
                                            id="assignClassBtn"
                                            disabled
                                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed">
                                        <i class="fas fa-tags mr-2"></i>
                                        Assign Flight Class to Selected Seats
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Aircraft Visualization -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">Aircraft Layout</h2>
                                    <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                                        <span><i class="fas fa-arrow-left mr-1"></i> Front</span>
                                        <span><i class="fas fa-arrow-right mr-1"></i> Rear</span>
                                    </div>
                                </div>
                            </div>
                            <div class="px-6 py-4">
                                <div class="aircraft-container">
                                    <div class="aircraft-body" style="min-width: <?php 
                                        $needsAisle = $seatsPerRow > 2;
                                        $seatWidth = 70; // width of each seat
                                        $gapWidth = 12; // gap between seats
                                        $rowLabelWidth = 55; // row label width
                                        $aisleWidth = $needsAisle ? 30 : 0;
                                        $totalSeatsWidth = ($seatWidth * $seatsPerRow) + ($gapWidth * ($seatsPerRow - 1));
                                        $minWidth = $rowLabelWidth + $totalSeatsWidth + $aisleWidth; // +200 for padding and margins
                                        echo max(800, $minWidth);
                                    ?>px;">
                                        <!-- Front Indicator -->
                                        <div class="aircraft-front-indicator">
                                            <i class="fas fa-arrow-up front-arrow-icon"></i>
                                            <div class="front-label">Front</div>
                                        </div>
                                        
                                        <!-- Rear Indicator -->
                                        <div class="aircraft-rear-indicator">
                                            <i class="fas fa-arrow-down rear-arrow-icon"></i>
                                            <div class="rear-label">Rear</div>
                                        </div>
                                        
                                        <!-- Seat Rows - Dynamic Layout -->
                                        <div class="seat-rows-container" id="seatGrid">
                                            <?php 
                                            // Use provided row labels or generate default (A, B, C, D, etc.)
                                            $seatLabels = $rowLabels;
                                            if (empty($seatLabels)) {
                                                for ($i = 0; $i < $seatsPerRow; $i++) {
                                                    $seatLabels[] = chr(65 + $i); // A=65, B=66, etc.
                                                }
                                            }
                                            
                                            // Create seat rows with per-row layouts
                                            for ($rowNum = 1; $rowNum <= $rows; $rowNum++):
                                                // Get layout for this specific row
                                                $rowLayout = $rowLayouts[$rowNum] ?? '';
                                                
                                                // Parse seat layout for this row
                                                $seatGroups = [];
                                                if (!empty($rowLayout)) {
                                                    if (strpos($rowLayout, '-') !== false) {
                                                        $seatGroups = array_map('intval', explode('-', $rowLayout));
                                                    } else {
                                                        $seatGroups = [intval($rowLayout)];
                                                    }
                                                } else {
                                                    // Default: all seats in one group
                                                    $seatGroups = [$seatsPerRow];
                                                }
                                            ?>
                                                <div class="seat-row">
                                                    <div class="row-label"><?php echo $rowNum; ?></div>
                                                    
                                                    <?php 
                                                    $seatIndex = 0;
                                                    foreach ($seatGroups as $groupIndex => $groupSize):
                                                        // Seats in this group
                                                        for ($i = 0; $i < $groupSize && $seatIndex < $seatsPerRow; $i++): 
                                                            $seatId = $rowNum . $seatLabels[$seatIndex];
                                                    ?>
                                                            <div class="seat" 
                                                                 data-row="<?php echo $rowNum; ?>" 
                                                                 data-position="<?php echo $seatLabels[$seatIndex]; ?>"
                                                                 data-seat-id="<?php echo $seatId; ?>"
                                                                 onclick="selectSeat(this, event)">
                                                                <span class="seat-label"><?php echo $seatId; ?></span>
                                                            </div>
                                                        <?php 
                                                            $seatIndex++;
                                                        endfor; 
                                                        
                                                        // Add aisle spacer (except for last group)
                                                        if ($groupIndex < count($seatGroups) - 1 && $seatIndex < $seatsPerRow):
                                                    ?>
                                                            <div class="aisle-spacer">|</div>
                                                        <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Hidden input for seat configuration -->
                        <input type="hidden" name="seat_configuration" id="seatConfiguration" value="[]">

                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-3">
                            <a href="index.php"
                               class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-save mr-2"></i>
                                Save Configuration
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal for Seat Assignment -->
    <div id="seatAssignmentModal" class="modal-overlay" onclick="closeModalOnOverlay(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="modal-header-content">
                    <h3>Assign Flight Class</h3>
                    <div class="badge">
                        <span id="selectedSeatsCount">0</span> seat(s) selected
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-section">
                    <div class="modal-section-label">
                        Selected Seats
                    </div>
                    <div id="selectedSeatsList" class="selected-seats-list empty">
                        <p>No seats selected</p>
                    </div>
                </div>
                <div class="modal-section">
                    <div class="modal-section-label">
                        Flight Class
                    </div>
                    <div class="flight-class-select-wrapper">
                        <select id="flightClassSelect">
                            <option value="">Select Flight Class</option>
                            <option value="unavailable">Unavailable Seat</option>
                            <?php foreach ($flightClasses as $fc): ?>
                                <option value="<?php echo $fc['id']; ?>">
                                    <?php echo htmlspecialchars($fc['name']); ?> (<?php echo htmlspecialchars($fc['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="modal-btn modal-btn-cancel">
                    Cancel
                </button>
                <button type="button" onclick="clearSelectedSeats()" class="modal-btn modal-btn-danger">
                    Clear
                </button>
                <button type="button" onclick="assignFlightClass()" class="modal-btn modal-btn-primary">
                    Assign
                </button>
            </div>
        </div>
    </div>

    <script>
        const flightClasses = <?php echo $flightClassesJson; ?>;
        const colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4'];
        let seatConfig = {};
        let selectedSeats = new Set();
        const classColorMap = {};

        // Initialize color mapping
        flightClasses.forEach((fc, index) => {
            classColorMap[fc.id] = colors[index % colors.length];
        });

        function selectSeat(seatElement, event) {
            const seatId = seatElement.dataset.seatId;
            
            // Check if Ctrl/Cmd key is pressed for multi-select
            if (event && (event.ctrlKey || event.metaKey)) {
                // Toggle selection
                if (selectedSeats.has(seatId)) {
                    selectedSeats.delete(seatId);
                    seatElement.classList.remove('selected');
                } else {
                    selectedSeats.add(seatId);
                    seatElement.classList.add('selected');
                }
            } else {
                // Single select - clear all and select this one
                document.querySelectorAll('.seat.selected').forEach(s => {
                    s.classList.remove('selected');
                });
                selectedSeats.clear();
                selectedSeats.add(seatId);
                seatElement.classList.add('selected');
            }
            
            updateAssignButton();
        }

        function updateAssignButton() {
            const btn = document.getElementById('assignClassBtn');
            if (selectedSeats.size > 0) {
                btn.disabled = false;
            } else {
                btn.disabled = true;
            }
        }

        function selectAllSeats() {
            // Get all seat elements
            const allSeats = document.querySelectorAll('.seat');
            
            // Clear current selection
            selectedSeats.clear();
            document.querySelectorAll('.seat.selected').forEach(s => {
                s.classList.remove('selected');
            });
            
            // Select all seats
            allSeats.forEach(seatElement => {
                const seatId = seatElement.dataset.seatId;
                selectedSeats.add(seatId);
                seatElement.classList.add('selected');
            });
            
            updateAssignButton();
        }

        function deselectAllSeats() {
            // Clear all selections
            selectedSeats.clear();
            document.querySelectorAll('.seat.selected').forEach(s => {
                s.classList.remove('selected');
            });
            
            updateAssignButton();
        }

        function openAssignModal() {
            if (selectedSeats.size === 0) return;
            
            // Update selected seats display
            updateSelectedSeatsDisplay();
            
            // Show modal
            document.getElementById('seatAssignmentModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('seatAssignmentModal').classList.remove('active');
        }

        function closeModalOnOverlay(event) {
            if (event.target === event.currentTarget) {
                closeModal();
            }
        }

        function updateSelectedSeatsDisplay() {
            const count = selectedSeats.size;
            document.getElementById('selectedSeatsCount').textContent = count;
            
            const listContainer = document.getElementById('selectedSeatsList');
            if (count === 0) {
                listContainer.classList.add('empty');
                listContainer.innerHTML = '<p class="text-sm">No seats selected</p>';
            } else {
                listContainer.classList.remove('empty');
                const seatsArray = Array.from(selectedSeats).sort();
                listContainer.innerHTML = seatsArray.map(seatId => 
                    `<span class="selected-seat-tag">${seatId}</span>`
                ).join('');
            }
        }

        function assignFlightClass() {
            if (selectedSeats.size === 0) return;
            
            const flightClassId = document.getElementById('flightClassSelect').value;
            if (!flightClassId) {
                alert('Please select a flight class or unavailable seat option');
                return;
            }
            
            // Handle unavailable seats
            if (flightClassId === 'unavailable') {
                selectedSeats.forEach(seatId => {
                    const seatElement = document.querySelector(`[data-seat-id="${seatId}"]`);
                    if (!seatElement) return;
                    
                    const row = seatElement.dataset.row;
                    const position = seatElement.dataset.position;
                    
                    // Update seat configuration
                    seatConfig[seatId] = {
                        row: parseInt(row),
                        position: position,
                        flight_class_id: null,
                        flight_class_name: 'Unavailable',
                        flight_class_code: 'UNA',
                        unavailable: true
                    };
                    
                    // Update seat appearance - gray color for unavailable
                    seatElement.style.backgroundColor = '#6b7280';
                    seatElement.style.borderColor = '#4b5563';
                    seatElement.style.color = '#FFFFFF';
                    seatElement.innerHTML = `<span class="seat-label">${seatId}</span><br><small><i class="fas fa-ban"></i> UNA</small>`;
                });
            } else {
                // Handle regular flight classes
                const flightClass = flightClasses.find(fc => fc.id == flightClassId);
                if (!flightClass) return;
                
                const color = classColorMap[flightClassId];
                
                // Update all selected seats
                selectedSeats.forEach(seatId => {
                    const seatElement = document.querySelector(`[data-seat-id="${seatId}"]`);
                    if (!seatElement) return;
                    
                    const row = seatElement.dataset.row;
                    const position = seatElement.dataset.position;
                    
                    // Update seat configuration
                    seatConfig[seatId] = {
                        row: parseInt(row),
                        position: position,
                        flight_class_id: parseInt(flightClassId),
                        flight_class_name: flightClass.name,
                        flight_class_code: flightClass.code,
                        unavailable: false
                    };
                    
                    // Update seat appearance
                    seatElement.style.backgroundColor = color;
                    seatElement.style.borderColor = color;
                    seatElement.style.color = '#FFFFFF';
                    seatElement.innerHTML = `<span class="seat-label">${seatId}</span><br><small>${flightClass.code}</small>`;
                });
            }
            
            // Clear selection
            selectedSeats.clear();
            document.querySelectorAll('.seat.selected').forEach(s => {
                s.classList.remove('selected');
            });
            updateAssignButton();
            
            // Close modal
            closeModal();
            
            // Update hidden input
            updateHiddenInput();
        }

        function clearSelectedSeats() {
            selectedSeats.forEach(seatId => {
                const seatElement = document.querySelector(`[data-seat-id="${seatId}"]`);
                if (seatElement) {
                    seatElement.classList.remove('selected');
                    
                    // Remove from configuration
                    delete seatConfig[seatId];
                    
                    // Reset seat appearance
                    seatElement.style.backgroundColor = '';
                    seatElement.style.borderColor = '';
                    seatElement.style.color = '';
                    seatElement.innerHTML = `<span class="seat-label">${seatId}</span>`;
                }
            });
            
            selectedSeats.clear();
            updateAssignButton();
            updateSelectedSeatsDisplay();
            updateHiddenInput();
        }

        function updateHiddenInput() {
            const configArray = Object.values(seatConfig);
            document.getElementById('seatConfiguration').value = JSON.stringify(configArray);
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Form validation
        document.getElementById('seatConfigForm').addEventListener('submit', function(e) {
            if (Object.keys(seatConfig).length === 0) {
                e.preventDefault();
                alert('Please assign flight classes to at least one seat before saving.');
                return false;
            }
        });
    </script>
</body>
</html>

