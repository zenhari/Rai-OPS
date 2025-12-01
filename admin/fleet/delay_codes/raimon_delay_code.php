<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/fleet/delay_codes/raimon_delay_code.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get selected filters
$selectedProccess = $_GET['proccess'] ?? '';
$selectedSubProccess = $_GET['sub_proccess'] ?? '';
$selectedReson = $_GET['reson'] ?? '';
$selectedStackHolder = $_GET['stackholder'] ?? '';

// Get unique values for each level
$db = getDBConnection();

// Get unique Proccess values
$proccessStmt = $db->query("SELECT DISTINCT `Proccess` FROM process_delay_code WHERE `Proccess` IS NOT NULL AND `Proccess` != '' ORDER BY `Proccess` ASC");
$proccessList = $proccessStmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique Sub-Proccess values based on selected Proccess
$subProccessList = [];
if (!empty($selectedProccess)) {
    $subProccessStmt = $db->prepare("SELECT DISTINCT `Sub-Proccess` FROM process_delay_code WHERE `Proccess` = ? AND `Sub-Proccess` IS NOT NULL AND `Sub-Proccess` != '' ORDER BY `Sub-Proccess` ASC");
    $subProccessStmt->execute([$selectedProccess]);
    $subProccessList = $subProccessStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get unique Reson values based on selected Proccess and Sub-Proccess
$resonList = [];
if (!empty($selectedProccess) && !empty($selectedSubProccess)) {
    $resonStmt = $db->prepare("SELECT DISTINCT `Reson` FROM process_delay_code WHERE `Proccess` = ? AND `Sub-Proccess` = ? AND `Reson` IS NOT NULL AND `Reson` != '' ORDER BY `Reson` ASC");
    $resonStmt->execute([$selectedProccess, $selectedSubProccess]);
    $resonList = $resonStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get unique StackHolder values based on selected Proccess, Sub-Proccess, and Reson
$stackHolderList = [];
if (!empty($selectedProccess) && !empty($selectedSubProccess) && !empty($selectedReson)) {
    $stackHolderStmt = $db->prepare("SELECT DISTINCT `StackHolder` FROM process_delay_code WHERE `Proccess` = ? AND `Sub-Proccess` = ? AND `Reson` = ? AND `StackHolder` IS NOT NULL AND `StackHolder` != '' ORDER BY `StackHolder` ASC");
    $stackHolderStmt->execute([$selectedProccess, $selectedSubProccess, $selectedReson]);
    $stackHolderList = $stackHolderStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get unique Result Code values based on all selections including StackHolder
$resultCodes = [];
$sampleResultCode = null;

// Try to get a sample result code based on current selections (even if not all steps are complete)
if (!empty($selectedProccess)) {
    $sampleStmt = null;
    $sampleParams = [];
    
    if (!empty($selectedProccess) && !empty($selectedSubProccess) && !empty($selectedReson) && !empty($selectedStackHolder)) {
        // All steps complete - get exact match
        $sampleStmt = $db->prepare("SELECT DISTINCT `Result Code` FROM process_delay_code WHERE `Proccess` = ? AND `Sub-Proccess` = ? AND `Reson` = ? AND `StackHolder` = ? AND `Result Code` IS NOT NULL AND `Result Code` != '' ORDER BY `Result Code` ASC LIMIT 1");
        $sampleParams = [$selectedProccess, $selectedSubProccess, $selectedReson, $selectedStackHolder];
    } elseif (!empty($selectedProccess) && !empty($selectedSubProccess) && !empty($selectedReson)) {
        // 3 steps complete
        $sampleStmt = $db->prepare("SELECT DISTINCT `Result Code` FROM process_delay_code WHERE `Proccess` = ? AND `Sub-Proccess` = ? AND `Reson` = ? AND `Result Code` IS NOT NULL AND `Result Code` != '' ORDER BY `Result Code` ASC LIMIT 1");
        $sampleParams = [$selectedProccess, $selectedSubProccess, $selectedReson];
    } elseif (!empty($selectedProccess) && !empty($selectedSubProccess)) {
        // 2 steps complete
        $sampleStmt = $db->prepare("SELECT DISTINCT `Result Code` FROM process_delay_code WHERE `Proccess` = ? AND `Sub-Proccess` = ? AND `Result Code` IS NOT NULL AND `Result Code` != '' ORDER BY `Result Code` ASC LIMIT 1");
        $sampleParams = [$selectedProccess, $selectedSubProccess];
    } elseif (!empty($selectedProccess)) {
        // 1 step complete - get code for Step 1
        $sampleStmt = $db->prepare("SELECT DISTINCT `Result Code` FROM process_delay_code WHERE `Proccess` = ? AND `Result Code` IS NOT NULL AND `Result Code` != '' ORDER BY `Result Code` ASC LIMIT 1");
        $sampleParams = [$selectedProccess];
    }
    
    if ($sampleStmt) {
        $sampleStmt->execute($sampleParams);
        $sampleResult = $sampleStmt->fetch(PDO::FETCH_COLUMN);
        if ($sampleResult) {
            $sampleResultCode = $sampleResult;
        }
    }
}

// Get all result codes when all steps are complete
if (!empty($selectedProccess) && !empty($selectedSubProccess) && !empty($selectedReson) && !empty($selectedStackHolder)) {
    $resultCodesStmt = $db->prepare("SELECT DISTINCT `Result Code` FROM process_delay_code WHERE `Proccess` = ? AND `Sub-Proccess` = ? AND `Reson` = ? AND `StackHolder` = ? AND `Result Code` IS NOT NULL AND `Result Code` != '' ORDER BY `Result Code` ASC");
    $resultCodesStmt->execute([$selectedProccess, $selectedSubProccess, $selectedReson, $selectedStackHolder]);
    $resultCodes = $resultCodesStmt->fetchAll(PDO::FETCH_COLUMN);
    // Update sample code if we have results
    if (!empty($resultCodes) && !$sampleResultCode) {
        $sampleResultCode = $resultCodes[0];
    }
}

// Function to build progressive code display
function buildProgressiveCode($selectedProccess, $selectedSubProccess, $selectedReson, $selectedStackHolder, $sampleResultCode = null, $db = null) {
    $progressiveCodes = [];
    $delimiter = '-'; // Default delimiter
    $codeParts = [null, null, null, null]; // Store actual code parts
    
    // Determine delimiter and get code parts from sample code or database
    if ($sampleResultCode) {
        // Try to split by dash first (most common format like "A-K-A")
        if (strpos($sampleResultCode, '-') !== false) {
            $parts = explode('-', $sampleResultCode);
            $delimiter = '-';
        } 
        // Try underscore
        elseif (strpos($sampleResultCode, '_') !== false) {
            $parts = explode('_', $sampleResultCode);
            $delimiter = '_';
        }
        // Try space
        elseif (strpos($sampleResultCode, ' ') !== false) {
            $parts = explode(' ', $sampleResultCode);
            $delimiter = ' ';
        }
        // If no delimiter, split by character
        else {
            $parts = str_split($sampleResultCode);
            $delimiter = '';
        }
        
        // Store code parts
        for ($i = 0; $i < 4 && $i < count($parts); $i++) {
            $codeParts[$i] = $parts[$i];
        }
    } else {
        // If no sample code, try to get actual codes from database
        if (!empty($selectedProccess) && $db) {
            // Get a sample code to determine structure
            $step1Stmt = $db->prepare("SELECT DISTINCT `Result Code` FROM process_delay_code WHERE `Proccess` = ? AND `Result Code` IS NOT NULL AND `Result Code` != '' ORDER BY `Result Code` ASC LIMIT 1");
            $step1Stmt->execute([$selectedProccess]);
            $step1Result = $step1Stmt->fetch(PDO::FETCH_COLUMN);
            if ($step1Result) {
                // Determine delimiter
                if (strpos($step1Result, '-') !== false) {
                    $parts = explode('-', $step1Result);
                    $delimiter = '-';
                } elseif (strpos($step1Result, '_') !== false) {
                    $parts = explode('_', $step1Result);
                    $delimiter = '_';
                } elseif (strpos($step1Result, ' ') !== false) {
                    $parts = explode(' ', $step1Result);
                    $delimiter = ' ';
                } else {
                    $parts = str_split($step1Result);
                    $delimiter = '';
                }
                
                // Store code parts
                for ($i = 0; $i < 4 && $i < count($parts); $i++) {
                    $codeParts[$i] = $parts[$i];
                }
            }
        }
    }
    
    // Build progressive codes with preview (using ? for unknown parts)
    // Determine total number of parts in the code structure (usually 3 or 4)
    $totalParts = 3; // Default to 3 parts
    if ($codeParts[3] !== null) {
        $totalParts = 4;
    } elseif ($codeParts[2] !== null) {
        $totalParts = 3;
    } elseif ($codeParts[1] !== null) {
        $totalParts = 2;
    } elseif ($codeParts[0] !== null) {
        $totalParts = 3; // Assume 3 parts if we only have first part
    }
    
    // Build preview for each step
    // Step 1: Always show preview with ? - ? - ? (or Y - ? - ? if we have first part)
    if (!empty($selectedProccess)) {
        $previewParts = [];
        // First part: use actual code if available, otherwise ?
        if ($codeParts[0] !== null) {
            $previewParts[] = $codeParts[0];
        } else {
            $previewParts[] = '?';
        }
        // Remaining parts: always ?
        for ($i = 1; $i < $totalParts; $i++) {
            $previewParts[] = '?';
        }
        $progressiveCodes[1] = implode($delimiter ? ' ' . $delimiter . ' ' : '', $previewParts);
    }
    
    // Step 2: Show Y - A - ? (first two parts if available, rest as ?)
    if (!empty($selectedSubProccess)) {
        $previewParts = [];
        // First part
        if ($codeParts[0] !== null) {
            $previewParts[] = $codeParts[0];
        } else {
            $previewParts[] = '?';
        }
        // Second part
        if ($codeParts[1] !== null) {
            $previewParts[] = $codeParts[1];
    } else {
            $previewParts[] = '?';
        }
        // Remaining parts: always ?
        for ($i = 2; $i < $totalParts; $i++) {
            $previewParts[] = '?';
        }
        $progressiveCodes[2] = implode($delimiter ? ' ' . $delimiter . ' ' : '', $previewParts);
    }
    
    // Step 3: Show Y - A - A - ? (first three parts if available, rest as ?)
        if (!empty($selectedReson)) {
        $previewParts = [];
        // First part
        if ($codeParts[0] !== null) {
            $previewParts[] = $codeParts[0];
        } else {
            $previewParts[] = '?';
        }
        // Second part
        if ($codeParts[1] !== null) {
            $previewParts[] = $codeParts[1];
        } else {
            $previewParts[] = '?';
        }
        // Third part
        if ($codeParts[2] !== null) {
            $previewParts[] = $codeParts[2];
        } else {
            $previewParts[] = '?';
        }
        // Remaining parts: always ?
        for ($i = 3; $i < $totalParts; $i++) {
            $previewParts[] = '?';
        }
        $progressiveCodes[3] = implode($delimiter ? ' ' . $delimiter . ' ' : '', $previewParts);
    }
    
    // Step 4: Show complete code (Y - A - A - A) or with ? for missing parts
        if (!empty($selectedStackHolder)) {
        $previewParts = [];
        for ($i = 0; $i < $totalParts; $i++) {
            if ($codeParts[$i] !== null) {
                $previewParts[] = $codeParts[$i];
            } else {
                $previewParts[] = '?';
            }
        }
        $progressiveCodes[4] = implode($delimiter ? ' ' . $delimiter . ' ' : '', $previewParts);
    }
    
    return $progressiveCodes;
}

$progressiveCodes = buildProgressiveCode($selectedProccess, $selectedSubProccess, $selectedReson, $selectedStackHolder, $sampleResultCode, $db);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raimon Delay Code - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        
        /* Minimal filter step design */
        .filter-step {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }
        
        .filter-step::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .filter-step.active::before {
            transform: scaleX(1);
        }
        
        .filter-step.completed::before {
            background: linear-gradient(90deg, #10b981, #059669);
            transform: scaleX(1);
        }
        
        .filter-step.active {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-color: #3b82f6;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .filter-step.active {
                background: linear-gradient(135deg, #1e3a8a, #1e40af);
                border-color: #60a5fa;
            }
        }
        
        .dark .filter-step.active {
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
            border-color: #60a5fa;
        }
        
        .filter-step.completed {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border-color: #10b981;
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .filter-step.completed {
                background: linear-gradient(135deg, #064e3b, #065f46);
                border-color: #34d399;
            }
        }
        
        .dark .filter-step.completed {
            background: linear-gradient(135deg, #064e3b, #065f46);
            border-color: #34d399;
        }
        
        /* Result code cards */
        .result-code-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .result-code-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        @media (prefers-color-scheme: dark) {
            html:not(.light) .result-code-card:hover {
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            }
        }
        
        .dark .result-code-card:hover {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }
        
        /* Smooth animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in {
            animation: fadeIn 0.4s ease-out;
        }
        
        /* Minimal select styling */
        select {
            transition: all 0.2s ease;
        }
        
        select:focus {
            ring: 2px;
            ring-color: #3b82f6;
        }
        
        /* Print styles */
        @media print {
            .no-print { display: none !important; }
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Raimon Delay Code</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Multi-step delay code selection and filtering</p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Delay Codes
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6 max-w-7xl mx-auto">
                <!-- Filter Steps - Minimal Design -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <!-- Step 1: Proccess -->
                        <div class="filter-step <?php echo empty($selectedProccess) ? 'active' : 'completed'; ?> p-5 border-2 <?php echo empty($selectedProccess) ? 'border-blue-500' : 'border-green-500'; ?> bg-white dark:bg-gray-800">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Step 1</span>
                                <?php if (!empty($selectedProccess)): ?>
                                    <i class="fas fa-check-circle text-green-500"></i>
                                <?php else: ?>
                                    <div class="w-5 h-5 rounded-full bg-blue-500 flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">1</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Proccess</h3>
                            <select id="proccessSelect" onchange="filterByProccess(this.value)" 
                                    class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                <option value="">Select Proccess</option>
                                <?php foreach ($proccessList as $proccess): ?>
                                    <option value="<?php echo htmlspecialchars($proccess); ?>" <?php echo $selectedProccess === $proccess ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($proccess); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($selectedProccess)): ?>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 truncate" title="<?php echo htmlspecialchars($selectedProccess); ?>">
                                    <i class="fas fa-check text-green-500 mr-1"></i><?php echo htmlspecialchars($selectedProccess); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Step 2: Sub-Proccess -->
                        <div class="filter-step <?php echo !empty($selectedProccess) && empty($selectedSubProccess) ? 'active' : (!empty($selectedSubProccess) ? 'completed' : ''); ?> p-5 border-2 <?php echo !empty($selectedProccess) ? ($selectedSubProccess ? 'border-green-500' : 'border-blue-500') : 'border-gray-300 dark:border-gray-600'; ?> bg-white dark:bg-gray-800 <?php echo empty($selectedProccess) ? 'opacity-40' : ''; ?>">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Step 2</span>
                                <?php if (!empty($selectedSubProccess)): ?>
                                    <i class="fas fa-check-circle text-green-500"></i>
                                <?php elseif (!empty($selectedProccess)): ?>
                                    <div class="w-5 h-5 rounded-full bg-blue-500 flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">2</span>
                                    </div>
                                <?php else: ?>
                                    <div class="w-5 h-5 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="text-gray-500 dark:text-gray-400 text-xs font-bold">2</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Sub-Proccess</h3>
                            <select id="subProccessSelect" onchange="filterBySubProccess(this.value)" 
                                    class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                    <?php echo empty($selectedProccess) ? 'disabled' : ''; ?>>
                                <option value="">Select Sub-Proccess</option>
                                <?php foreach ($subProccessList as $subProccess): ?>
                                    <option value="<?php echo htmlspecialchars($subProccess); ?>" <?php echo $selectedSubProccess === $subProccess ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subProccess); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($selectedSubProccess)): ?>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 truncate" title="<?php echo htmlspecialchars($selectedSubProccess); ?>">
                                    <i class="fas fa-check text-green-500 mr-1"></i><?php echo htmlspecialchars($selectedSubProccess); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Step 3: Reson -->
                        <div class="filter-step <?php echo !empty($selectedSubProccess) && empty($selectedReson) ? 'active' : (!empty($selectedReson) ? 'completed' : ''); ?> p-5 border-2 <?php echo !empty($selectedSubProccess) ? ($selectedReson ? 'border-green-500' : 'border-blue-500') : 'border-gray-300 dark:border-gray-600'; ?> bg-white dark:bg-gray-800 <?php echo empty($selectedSubProccess) ? 'opacity-40' : ''; ?>">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Step 3</span>
                                <?php if (!empty($selectedReson)): ?>
                                    <i class="fas fa-check-circle text-green-500"></i>
                                <?php elseif (!empty($selectedSubProccess)): ?>
                                    <div class="w-5 h-5 rounded-full bg-blue-500 flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">3</span>
                                    </div>
                                <?php else: ?>
                                    <div class="w-5 h-5 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="text-gray-500 dark:text-gray-400 text-xs font-bold">3</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Reson</h3>
                            <select id="resonSelect" onchange="filterByReson(this.value)" 
                                    class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                    <?php echo empty($selectedSubProccess) ? 'disabled' : ''; ?>>
                                <option value="">Select Reson</option>
                                <?php foreach ($resonList as $reson): ?>
                                    <option value="<?php echo htmlspecialchars($reson); ?>" <?php echo $selectedReson === $reson ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($reson); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($selectedReson)): ?>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 truncate" title="<?php echo htmlspecialchars($selectedReson); ?>">
                                    <i class="fas fa-check text-green-500 mr-1"></i><?php echo htmlspecialchars($selectedReson); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Step 4: StackHolder -->
                        <div class="filter-step <?php echo !empty($selectedReson) && empty($selectedStackHolder) ? 'active' : (!empty($selectedStackHolder) ? 'completed' : ''); ?> p-5 border-2 <?php echo !empty($selectedReson) ? ($selectedStackHolder ? 'border-green-500' : 'border-blue-500') : 'border-gray-300 dark:border-gray-600'; ?> bg-white dark:bg-gray-800 <?php echo empty($selectedReson) ? 'opacity-40' : ''; ?>">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Step 4</span>
                                <?php if (!empty($selectedStackHolder)): ?>
                                    <i class="fas fa-check-circle text-green-500"></i>
                                <?php elseif (!empty($selectedReson)): ?>
                                    <div class="w-5 h-5 rounded-full bg-blue-500 flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">4</span>
                                    </div>
                                <?php else: ?>
                                    <div class="w-5 h-5 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="text-gray-500 dark:text-gray-400 text-xs font-bold">4</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">StackHolder</h3>
                            <select id="stackHolderSelect" onchange="filterByStackHolder(this.value)" 
                                    class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                    <?php echo empty($selectedReson) ? 'disabled' : ''; ?>>
                                <option value="">Select StackHolder</option>
                                <?php foreach ($stackHolderList as $stackHolder): ?>
                                    <option value="<?php echo htmlspecialchars($stackHolder); ?>" <?php echo $selectedStackHolder === $stackHolder ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stackHolder); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($selectedStackHolder)): ?>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 truncate" title="<?php echo htmlspecialchars($selectedStackHolder); ?>">
                                    <i class="fas fa-check text-green-500 mr-1"></i><?php echo htmlspecialchars($selectedStackHolder); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Step 5: Results -->
                        <div class="filter-step <?php echo !empty($selectedStackHolder) ? 'active' : ''; ?> p-5 border-2 <?php echo !empty($selectedStackHolder) ? 'border-blue-500' : 'border-gray-300 dark:border-gray-600'; ?> bg-white dark:bg-gray-800 <?php echo empty($selectedStackHolder) ? 'opacity-40' : ''; ?>">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Step 5</span>
                                <?php if (!empty($resultCodes)): ?>
                                    <i class="fas fa-check-circle text-green-500"></i>
                                <?php elseif (!empty($selectedStackHolder)): ?>
                                    <div class="w-5 h-5 rounded-full bg-blue-500 flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">5</span>
                                    </div>
                                <?php else: ?>
                                    <div class="w-5 h-5 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="text-gray-500 dark:text-gray-400 text-xs font-bold">5</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Result Code</h3>
                            <div class="text-sm">
                                <?php if (!empty($resultCodes)): ?>
                                    <p class="font-semibold text-blue-600 dark:text-blue-400"><?php echo count($resultCodes); ?> code(s)</p>
                                <?php else: ?>
                                    <p class="text-gray-400 dark:text-gray-500">Complete steps</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    <?php if (!empty($selectedProccess) || !empty($selectedSubProccess) || !empty($selectedReson) || !empty($selectedStackHolder)): ?>
                        <div class="mt-6 flex justify-end">
                            <button onclick="resetFilters()" 
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition-colors duration-200 no-print">
                                <i class="fas fa-redo mr-2"></i>
                                Reset
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Results - Minimal Card Grid Design -->
                <?php if (!empty($resultCodes)): ?>
                    <div class="fade-in">
                        <!-- Results Header -->
                        <div class="mb-6 flex items-center justify-between no-print">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Result Codes</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Found <span class="font-semibold text-blue-600 dark:text-blue-400"><?php echo count($resultCodes); ?></span> unique code(s) for:
                                    <span class="font-medium"><?php echo htmlspecialchars($selectedProccess); ?></span> → 
                                    <span class="font-medium"><?php echo htmlspecialchars($selectedSubProccess); ?></span> → 
                                    <span class="font-medium"><?php echo htmlspecialchars($selectedReson); ?></span> → 
                                    <span class="font-medium"><?php echo htmlspecialchars($selectedStackHolder); ?></span>
                                </p>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="copyAllCodes()" 
                                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                                    <i class="fas fa-copy mr-2"></i>
                                    Copy All
                                </button>
                                <button onclick="exportToCSV()" 
                                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                                    <i class="fas fa-download mr-2"></i>
                                    Export
                                </button>
                                <button onclick="window.print()" 
                                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                                    <i class="fas fa-print mr-2"></i>
                                    Print
                                </button>
                            </div>
                        </div>
                        
                        <!-- Result Code Cards Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                            <?php foreach ($resultCodes as $index => $code): ?>
                                <div class="result-code-card bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6 text-center shadow-sm hover:shadow-md transition-all duration-200" 
                                     onclick="copyCode('<?php echo htmlspecialchars($code); ?>', this)">
                                    <div class="flex items-center justify-center mb-3">
                                        <div class="w-12 h-12 rounded-full bg-blue-500 dark:bg-blue-600 flex items-center justify-center">
                                            <i class="fas fa-code text-white text-lg"></i>
                                        </div>
                                    </div>
                                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mb-2 font-mono">
                                        <?php echo htmlspecialchars($code); ?>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Click to copy</p>
                                    <div class="mt-3 opacity-0 transition-opacity duration-200 copy-feedback">
                                        <i class="fas fa-check-circle text-green-500"></i>
                                        <span class="text-xs text-green-600 dark:text-green-400 ml-1">Copied!</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Results State - Minimal -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-16 text-center">
                        <div class="max-w-md mx-auto">
                            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                <i class="fas fa-filter text-gray-400 dark:text-gray-500 text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No Result Codes</h3>
                            <p class="text-gray-500 dark:text-gray-400">
                                <?php if (empty($selectedProccess)): ?>
                                    Select a <span class="font-medium text-blue-600 dark:text-blue-400">Proccess</span> to begin.
                                <?php elseif (empty($selectedSubProccess)): ?>
                                    Select a <span class="font-medium text-blue-600 dark:text-blue-400">Sub-Proccess</span> to continue.
                                <?php elseif (empty($selectedReson)): ?>
                                    Select a <span class="font-medium text-blue-600 dark:text-blue-400">Reson</span> to continue.
                                <?php elseif (empty($selectedStackHolder)): ?>
                                    Select a <span class="font-medium text-blue-600 dark:text-blue-400">StackHolder</span> to view codes.
                                <?php else: ?>
                                    No result codes found for the selected filters.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function filterByProccess(proccess) {
            if (proccess) {
                window.location.href = '?proccess=' + encodeURIComponent(proccess);
            } else {
                window.location.href = 'raimon_delay_code.php';
            }
        }

        function filterBySubProccess(subProccess) {
            const proccess = document.getElementById('proccessSelect').value;
            if (subProccess && proccess) {
                window.location.href = '?proccess=' + encodeURIComponent(proccess) + '&sub_proccess=' + encodeURIComponent(subProccess);
            } else if (proccess) {
                window.location.href = '?proccess=' + encodeURIComponent(proccess);
            }
        }

        function filterByReson(reson) {
            const proccess = document.getElementById('proccessSelect').value;
            const subProccess = document.getElementById('subProccessSelect').value;
            if (reson && proccess && subProccess) {
                window.location.href = '?proccess=' + encodeURIComponent(proccess) + '&sub_proccess=' + encodeURIComponent(subProccess) + '&reson=' + encodeURIComponent(reson);
            } else if (proccess && subProccess) {
                window.location.href = '?proccess=' + encodeURIComponent(proccess) + '&sub_proccess=' + encodeURIComponent(subProccess);
            }
        }

        function filterByStackHolder(stackHolder) {
            const proccess = document.getElementById('proccessSelect').value;
            const subProccess = document.getElementById('subProccessSelect').value;
            const reson = document.getElementById('resonSelect').value;
            if (stackHolder && proccess && subProccess && reson) {
                window.location.href = '?proccess=' + encodeURIComponent(proccess) + '&sub_proccess=' + encodeURIComponent(subProccess) + '&reson=' + encodeURIComponent(reson) + '&stackholder=' + encodeURIComponent(stackHolder);
            } else if (proccess && subProccess && reson) {
                window.location.href = '?proccess=' + encodeURIComponent(proccess) + '&sub_proccess=' + encodeURIComponent(subProccess) + '&reson=' + encodeURIComponent(reson);
            }
        }

        function resetFilters() {
            window.location.href = 'raimon_delay_code.php';
        }

        function copyCode(code, element) {
            // Copy to clipboard
            navigator.clipboard.writeText(code).then(() => {
                // Show feedback
                const feedback = element.querySelector('.copy-feedback');
                feedback.classList.remove('opacity-0');
                feedback.classList.add('opacity-100');
                
                // Reset after 2 seconds
                setTimeout(() => {
                    feedback.classList.remove('opacity-100');
                    feedback.classList.add('opacity-0');
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy code. Please try again.');
            });
        }

        function copyAllCodes() {
            const codes = <?php echo json_encode($resultCodes ?? []); ?>;
            if (codes.length === 0) return;
            
            const codesText = codes.join('\n');
            navigator.clipboard.writeText(codesText).then(() => {
                // Show temporary notification
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center space-x-2';
                notification.innerHTML = '<i class="fas fa-check-circle"></i><span>All codes copied to clipboard!</span>';
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy codes. Please try again.');
            });
        }

        function exportToCSV() {
            const codes = <?php echo json_encode($resultCodes ?? []); ?>;
            if (codes.length === 0) return;
            
            // Create CSV content
            let csv = ['Result Code'];
            codes.forEach(code => {
                csv.push('"' + code.replace(/"/g, '""') + '"');
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'raimon_delay_code_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>

