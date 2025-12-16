<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/pricing/seat_configuration/view.php');

$current_user = getCurrentUser();

// Get configuration ID
$configId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$configId) {
    header('Location: index.php');
    exit();
}

// Get configuration
$config = getAircraftSeatConfigurationById($configId);

if (!$config) {
    header('Location: index.php');
    exit();
}

// Get all flight classes for display
$flightClasses = getAllFlightClasses();
$flightClassMap = [];
foreach ($flightClasses as $fc) {
    $flightClassMap[$fc['id']] = $fc;
}

// Prepare seat configuration for display
$seatConfig = $config['seat_configuration'] ?? [];
$seatMap = [];
foreach ($seatConfig as $seat) {
    $seatId = $seat['row'] . $seat['position'];
    $seatMap[$seatId] = $seat;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Seat Configuration - <?php echo PROJECT_NAME; ?></title>
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
            padding: 20px;
            overflow-x: auto;
            overflow-y: visible;
        }
        
        .aircraft-body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 25px;
            padding: 40px 60px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
            position: relative;
            min-width: 1400px;
            min-height: 400px;
        }
        
        /* Aircraft Nose (Front) - More realistic nose shape */
        .aircraft-nose {
            position: absolute;
            left: -80px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-top: 60px solid transparent;
            border-bottom: 60px solid transparent;
            border-right: 80px solid #667eea;
            z-index: 10;
            filter: drop-shadow(2px 0 4px rgba(0,0,0,0.3));
        }
        
        .aircraft-nose::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-top: 45px solid transparent;
            border-bottom: 45px solid transparent;
            border-right: 60px solid #764ba2;
            z-index: -1;
        }
        
        /* Aircraft Nose (Front) */
        .aircraft-nose {
            position: absolute;
            left: -50px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-top: 50px solid transparent;
            border-bottom: 50px solid transparent;
            border-right: 50px solid #667eea;
            z-index: 10;
        }
        
        /* Aircraft Wings */
        .aircraft-wing {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 250px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
            z-index: 5;
        }
        
        .aircraft-wing.left {
            left: 25%;
        }
        
        .aircraft-wing.right {
            right: 25%;
        }
        
        /* Front Section - Cockpit and Galleys */
        .aircraft-front {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }
        
        .cockpit {
            flex: 1;
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: center;
            min-height: 80px;
        }
        
        .pilot-seat {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: white;
        }
        
        .galley {
            width: 120px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.25);
            border-radius: 10px;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.8);
            min-height: 80px;
        }
        
        /* Seat Rows Container */
        .seat-rows-container {
            position: relative;
            z-index: 2;
            margin: 15px 0;
        }
        
        .seat-row {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: nowrap;
            overflow-x: visible;
        }
        
        .row-label {
            width: 60px;
            text-align: center;
            font-weight: bold;
            color: white;
            font-size: 14px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            flex-shrink: 0;
        }
        
        .aisle-row {
            display: flex;
            align-items: center;
            margin: 8px 0;
            padding-left: 60px;
        }
        
        .aisle-label {
            width: 100%;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        
        .seat {
            width: 70px;
            height: 70px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            position: relative;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            flex-shrink: 0;
        }
        
        .seat-label {
            position: absolute;
            bottom: -18px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 9px;
            color: rgba(255, 255, 255, 0.9);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            white-space: nowrap;
        }
        
        /* Rear Section */
        .aircraft-rear {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            position: relative;
            z-index: 2;
            min-height: 20px;
        }
        
        .rear-space {
            flex: 1;
        }
        
        .lavatory {
            width: 100px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.25);
            border-radius: 10px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.8);
            min-height: 90px;
        }
        
        .lavatory-icon {
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        /* Direction Labels */
        .direction-label {
            position: absolute;
            color: rgba(255, 255, 255, 0.9);
            font-size: 12px;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            z-index: 3;
        }
        
        .direction-label.front {
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .direction-label.rear {
            bottom: 10px;
            right: 20px;
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">View Seat Configuration</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1"><?php echo htmlspecialchars($config['name']); ?></p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="edit.php?id=<?php echo $config['id']; ?>" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                <i class="fas fa-edit mr-2"></i>
                                Edit
                            </a>
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
                <!-- Configuration Info -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Configuration Details</h2>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Total Seats</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo $config['total_seats']; ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Layout</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo $config['rows']; ?> rows × <?php echo $config['seats_per_row']; ?> seats</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Created</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo date('M j, Y g:i A', strtotime($config['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flight Class Legend -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Flight Classes</h2>
                    </div>
                    <div class="px-6 py-4">
                        <div class="flex flex-wrap gap-4">
                            <?php 
                            $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4'];
                            $colorIndex = 0;
                            foreach ($flightClasses as $fc): 
                                $color = $colors[$colorIndex % count($colors)];
                                $colorIndex++;
                            ?>
                                <div class="flex items-center gap-2">
                                    <div style="width: 30px; height: 30px; background-color: <?php echo $color; ?>; border-radius: 6px; border: 2px solid #cbd5e0;"></div>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($fc['name']); ?> (<?php echo htmlspecialchars($fc['code']); ?>)
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Aircraft Visualization -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Aircraft Layout</h2>
                            <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                                <span><i class="fas fa-arrow-left mr-1"></i> Front (Nose)</span>
                                <span><i class="fas fa-arrow-right mr-1"></i> Rear (Tail)</span>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4">
                        <div class="aircraft-container">
                            <div class="aircraft-body">
                                <!-- Aircraft Nose -->
                                <div class="aircraft-nose"></div>
                                
                                <!-- Seat Rows (2 rows × 15 seats = 30 seats) - Transposed Layout -->
                                <div class="seat-rows-container">
                                    <?php 
                                    $seatLabels = ['A', 'B'];
                                    $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4'];
                                    $classColorMap = [];
                                    $colorIndex = 0;
                                    foreach ($flightClasses as $fc) {
                                        $classColorMap[$fc['id']] = $colors[$colorIndex % count($colors)];
                                        $colorIndex++;
                                    }
                                    
                                    // Row A (left side)
                                    ?>
                                    <div class="seat-row">
                                        <div class="row-label">Row A</div>
                                        <?php for ($col = 1; $col <= 15; $col++): 
                                            $seatId = $col . $seatLabels[0];
                                            $seatData = $seatMap[$seatId] ?? null;
                                            $bgColor = '';
                                            $borderColor = 'rgba(255, 255, 255, 0.4)';
                                            $textColor = '';
                                            $seatCode = '';
                                            
                                            if ($seatData && isset($seatData['flight_class_id'])) {
                                                $bgColor = $classColorMap[$seatData['flight_class_id']] ?? 'rgba(255, 255, 255, 0.15)';
                                                $borderColor = $bgColor;
                                                $textColor = '#FFFFFF';
                                                $seatCode = $seatData['flight_class_code'] ?? '';
                                            }
                                        ?>
                                            <div class="seat" 
                                                 style="<?php if ($bgColor): ?>background-color: <?php echo $bgColor; ?>; border-color: <?php echo $borderColor; ?>; color: <?php echo $textColor; ?>;<?php endif; ?>">
                                                <span class="seat-label"><?php echo $seatId; ?></span>
                                                <?php if ($seatCode): ?>
                                                    <br><small><?php echo htmlspecialchars($seatCode); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <div class="aisle-row">
                                        <div class="aisle-label">AISLE</div>
                                    </div>
                                    
                                    <?php 
                                    // Row B (right side)
                                    ?>
                                    <div class="seat-row">
                                        <div class="row-label">Row B</div>
                                        <?php for ($col = 1; $col <= 15; $col++): 
                                            $seatId = $col . $seatLabels[1];
                                            $seatData = $seatMap[$seatId] ?? null;
                                            $bgColor = '';
                                            $borderColor = 'rgba(255, 255, 255, 0.4)';
                                            $textColor = '';
                                            $seatCode = '';
                                            
                                            if ($seatData && isset($seatData['flight_class_id'])) {
                                                $bgColor = $classColorMap[$seatData['flight_class_id']] ?? 'rgba(255, 255, 255, 0.15)';
                                                $borderColor = $bgColor;
                                                $textColor = '#FFFFFF';
                                                $seatCode = $seatData['flight_class_code'] ?? '';
                                            }
                                        ?>
                                            <div class="seat" 
                                                 style="<?php if ($bgColor): ?>background-color: <?php echo $bgColor; ?>; border-color: <?php echo $borderColor; ?>; color: <?php echo $textColor; ?>;<?php endif; ?>">
                                                <span class="seat-label"><?php echo $seatId; ?></span>
                                                <?php if ($seatCode): ?>
                                                    <br><small><?php echo htmlspecialchars($seatCode); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

