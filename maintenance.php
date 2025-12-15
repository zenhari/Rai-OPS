<?php
// Include database configuration
require_once 'config.php';

// Check if this is an AJAX request to check maintenance status
if (isset($_GET['check_status']) && $_GET['check_status'] == '1') {
    header('Content-Type: application/json');
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT is_active FROM maintenance_mode ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $isActive = (bool)$result['is_active'];
            echo json_encode(['is_active' => $isActive ? 1 : 0]);
        } else {
            echo json_encode(['is_active' => 0]);
        }
    } catch (PDOException $e) {
        echo json_encode(['is_active' => 1]); // Fail-safe: assume maintenance is active
    }
    exit();
}

// Deactivate maintenance mode when countdown reaches zero
if (isset($_GET['deactivate']) && $_GET['deactivate'] == '1') {
    header('Content-Type: application/json');
    try {
        $pdo = getDBConnection();
        // First get the latest record ID
        $stmt = $pdo->query("SELECT id FROM maintenance_mode ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $id = $result['id'];
            // Update the record
            $updateStmt = $pdo->prepare("UPDATE maintenance_mode SET is_active = 0, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$id]);
            
            if ($updateStmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Maintenance mode deactivated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Update failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No record found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Get maintenance mode settings from database
$maintenanceActive = false;
$endDateTime = null;
$targetDate = null;

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT is_active, end_datetime FROM maintenance_mode ORDER BY id DESC LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $maintenanceActive = (bool)$result['is_active'];
        $endDateTime = $result['end_datetime'];
        
        // If maintenance is not active, redirect to login page
        if (!$maintenanceActive) {
            header('Location: login.php');
            exit();
        }
        
        // If maintenance is active and end_datetime is set, use it for countdown
        if ($maintenanceActive && $endDateTime) {
            $targetDate = new DateTime($endDateTime);
        } elseif ($maintenanceActive && !$endDateTime) {
            // If active but no end date, set default to 30 days from now
            $targetDate = new DateTime();
            $targetDate->modify('+30 days');
        }
    } else {
        // No record found, redirect to login
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    // If database error, show maintenance page anyway (fail-safe)
    $maintenanceActive = true;
    $targetDate = new DateTime();
    $targetDate->modify('+30 days');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon - Maintenance Mode</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0a0e27;
            background-image: 
                radial-gradient(at 0% 0%, rgba(20, 30, 60, 0.8) 0%, transparent 50%),
                radial-gradient(at 100% 100%, rgba(15, 25, 50, 0.6) 0%, transparent 50%),
                linear-gradient(135deg, #0a0e27 0%, #0f1629 50%, #0a0e27 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #e8eaf6;
            padding: 40px 20px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="%231a1f3a" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
            z-index: 0;
        }

        body::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0, 212, 255, 0.05) 0%, transparent 70%);
            animation: pulse 20s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }


        .container {
            text-align: center;
            max-width: 1000px;
            width: 100%;
            z-index: 1;
            animation: fadeIn 1.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2.5rem, 5vw, 4.5rem);
            font-weight: 700;
            margin-bottom: 30px;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        h1 .highlight {
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: inline-block;
        }

        h1 .normal {
            color: #ffffff;
            font-weight: 600;
        }

        .description {
            font-size: 1.125rem;
            color: #b0b8d1;
            margin-bottom: 60px;
            line-height: 1.8;
            max-width: 750px;
            margin-left: auto;
            margin-right: auto;
            font-weight: 400;
        }

        .description p {
            margin-bottom: 15px;
        }

        .description > p:first-child {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 20px;
            letter-spacing: 0.02em;
        }

        .description > p:nth-child(2) {
            font-size: 1.25rem;
            font-weight: 500;
            color: #c8d0e8;
            margin-bottom: 40px;
        }

        .description div {
            background: rgba(15, 20, 40, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 212, 255, 0.15);
            border-radius: 16px;
            padding: 35px;
            margin-top: 40px;
            max-width: 550px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        .description div p {
            margin-bottom: 18px;
            font-size: 0.95rem;
            color: #d0d8f0;
            line-height: 1.7;
        }

        .description div p:last-child {
            margin-bottom: 0;
        }

        .description div strong {
            color: #00d4ff;
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        .description a {
            color: #00d4ff;
            text-decoration: none;
            transition: all 0.3s ease;
            border-bottom: 1px solid transparent;
            padding-bottom: 2px;
        }

        .description a:hover {
            color: #00b8e6;
            border-bottom-color: #00b8e6;
        }

        .description.error {
            color: #ff6b6b;
        }

        .countdown {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-bottom: 60px;
            flex-wrap: wrap;
        }

        .countdown-item {
            background: rgba(15, 20, 40, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 16px;
            padding: 32px 28px;
            min-width: 140px;
            position: relative;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        .countdown-item:hover {
            transform: translateY(-4px);
            border-color: rgba(0, 212, 255, 0.4);
            box-shadow: 0 8px 32px rgba(0, 212, 255, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .countdown-item:not(:last-child)::after {
            content: '';
            position: absolute;
            right: -12px;
            top: 50%;
            transform: translateY(-50%);
            width: 2px;
            height: 50%;
            background: linear-gradient(180deg, transparent, rgba(0, 212, 255, 0.3), transparent);
        }

        .countdown-number {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 3.5rem;
            font-weight: 700;
            color: #00d4ff;
            margin-bottom: 12px;
            line-height: 1;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
            letter-spacing: -0.02em;
        }

        .countdown-label {
            font-size: 0.75rem;
            color: #8a94b8;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }


        @media (max-width: 768px) {
            body {
                padding: 30px 15px;
            }

            h1 {
                font-size: 2.25rem;
                margin-bottom: 25px;
            }

            .description {
                margin-bottom: 40px;
            }

            .description > p:first-child {
                font-size: 1.25rem;
            }

            .description > p:nth-child(2) {
                font-size: 1.1rem;
            }

            .description div {
                padding: 25px 20px;
            }

            .countdown {
                gap: 12px;
                margin-bottom: 40px;
            }

            .countdown-item {
                min-width: 100px;
                padding: 24px 16px;
            }

            .countdown-number {
                font-size: 2.5rem;
            }

            .countdown-label {
                font-size: 0.7rem;
            }

            .countdown-item:not(:last-child)::after {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <span class="highlight"><?php echo PROJECT_NAME; ?></span>
            <span class="normal"> IS COMING SOON</span>
        </h1>
        
        <div class="description">
            <p>We are currently under Maintenance!</p>
            <p>We'll be back soon!</p>
            <div>
                <p>
                    <strong>Developer:</strong> Mehdi Zenhari
                </p>
                <p>
                    <strong>Emergency Contact:</strong> 
                    <a href="tel:+989129382810">+989129382810</a>
                </p>
                <p>
                    <strong>Email:</strong> 
                    <a href="mailto:zenhari.m@raimonairways.net">zenhari.m@raimonairways.net</a>
                </p>
            </div>
        </div>

        <?php if ($maintenanceActive && $targetDate): ?>
        <div class="countdown" id="countdown">
            <div class="countdown-item">
                <div class="countdown-number" id="days">000</div>
                <div class="countdown-label">Days</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-number" id="hours">00</div>
                <div class="countdown-label">Hours</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-number" id="minutes">00</div>
                <div class="countdown-label">Minutes</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-number" id="seconds">00</div>
                <div class="countdown-label">Seconds</div>
            </div>
        </div>
        <?php elseif ($maintenanceActive && !$endDateTime): ?>
        <div class="countdown" id="countdown">
            <div class="countdown-item">
                <div class="countdown-number" id="days">000</div>
                <div class="countdown-label">Days</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-number" id="hours">00</div>
                <div class="countdown-label">Hours</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-number" id="minutes">00</div>
                <div class="countdown-label">Minutes</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-number" id="seconds">00</div>
                <div class="countdown-label">Seconds</div>
            </div>
        </div>
        <?php else: ?>
        <div class="countdown" style="display: none;" id="countdown"></div>
        <p class="description error" style="margin-top: 20px;">
            Maintenance mode is currently inactive.
        </p>
        <?php endif; ?>
    </div>

    <script>
        <?php if ($maintenanceActive && $targetDate): ?>
        // Set target date from database
        const targetDate = new Date('<?php echo $targetDate->format('Y-m-d H:i:s'); ?>');

        let countdownFinished = false;

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = targetDate - now;

            if (distance < 0 && !countdownFinished) {
                // Countdown finished - deactivate maintenance and redirect to login
                countdownFinished = true;
                document.getElementById('days').textContent = '0';
                document.getElementById('hours').textContent = '0';
                document.getElementById('minutes').textContent = '0';
                document.getElementById('seconds').textContent = '0';
                
                // Deactivate maintenance mode in database
                fetch('maintenance.php?deactivate=1')
                    .then(response => response.json())
                    .then(data => {
                        // Redirect to login page after deactivating
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 500);
                    })
                    .catch(error => {
                        console.error('Error deactivating maintenance:', error);
                        // Redirect anyway even if deactivation fails
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 500);
                    });
                return;
            }

            if (countdownFinished) {
                return; // Stop updating if countdown is finished
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById('days').textContent = days.toString().padStart(3, '0');
            document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
            document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
        }

        // Update countdown every second
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php elseif ($maintenanceActive && !$endDateTime): ?>
        // Maintenance is active but no end date set - use default 30 days
        const targetDate = new Date();
        targetDate.setDate(targetDate.getDate() + 30);

        let countdownFinished = false;

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = targetDate - now;

            if (distance < 0 && !countdownFinished) {
                // Countdown finished - deactivate maintenance and redirect to login
                countdownFinished = true;
                document.getElementById('days').textContent = '0';
                document.getElementById('hours').textContent = '0';
                document.getElementById('minutes').textContent = '0';
                document.getElementById('seconds').textContent = '0';
                
                // Deactivate maintenance mode in database
                fetch('maintenance.php?deactivate=1')
                    .then(response => response.json())
                    .then(data => {
                        // Redirect to login page after deactivating
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 500);
                    })
                    .catch(error => {
                        console.error('Error deactivating maintenance:', error);
                        // Redirect anyway even if deactivation fails
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 500);
                    });
                return;
            }

            if (countdownFinished) {
                return; // Stop updating if countdown is finished
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById('days').textContent = days.toString().padStart(3, '0');
            document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
            document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>

        // Check maintenance status every 10 seconds
        function checkMaintenanceStatus() {
            fetch('maintenance.php?check_status=1')
                .then(response => response.json())
                .then(data => {
                    if (data.is_active === 0) {
                        // Maintenance mode is deactivated, redirect to login
                        window.location.href = 'login.php';
                    }
                })
                .catch(error => {
                    console.error('Error checking maintenance status:', error);
                    // On error, continue showing maintenance page (fail-safe)
                });
        }

        // Start checking maintenance status every 10 seconds
        // First check after 10 seconds, then every 10 seconds
        setTimeout(() => {
            checkMaintenanceStatus();
            setInterval(checkMaintenanceStatus, 10000); // Check every 10 seconds
        }, 10000);

    </script>
</body>
</html>

