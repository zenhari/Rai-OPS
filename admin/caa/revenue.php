<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/caa/revenue.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get current year and month for default values
$currentYear = date('Y');
$currentMonth = date('n');

// Handle AJAX request for flight data
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'get_flight_data') {
    // Clear any previous output and disable error display
    // Check if output buffering is active before cleaning
    if (ob_get_level() > 0) {
        ob_clean();
    }
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json');
    
    try {
        // Get date range from POST data
        $fromDate = trim($_POST['from_date'] ?? '');
        $toDate = trim($_POST['to_date'] ?? '');
        
        // Validate input
        if (empty($fromDate)) {
            throw new Exception('From date is required');
        }
        
        if (empty($toDate)) {
            throw new Exception('To date is required');
        }
        
        // Validate date format
        $fromDateTime = DateTime::createFromFormat('Y-m-d', $fromDate);
        $toDateTime = DateTime::createFromFormat('Y-m-d', $toDate);
        
        if (!$fromDateTime || !$toDateTime) {
            throw new Exception('Invalid date format');
        }
        
        if ($fromDateTime > $toDateTime) {
            throw new Exception('From date cannot be after to date');
        }
    
        // Normalize dates to ensure proper format
        $fromDate = $fromDateTime->format('Y-m-d');
        $toDate = $toDateTime->format('Y-m-d');
    
        $db = getDBConnection();
        
        // Build the query
        $whereConditions = [];
        $params = [];
        
        // Add date range filter - only flights with valid FltDate
        // Use CAST to ensure proper date comparison
        $whereConditions[] = "FltDate IS NOT NULL";
        $whereConditions[] = "CAST(FltDate AS DATE) >= CAST(? AS DATE)";
        $params[] = $fromDate;
        
        $whereConditions[] = "CAST(FltDate AS DATE) <= CAST(? AS DATE)";
        $params[] = $toDate;
        
        // Exclude cancelled flights
        $whereConditions[] = "(ScheduledTaskStatus IS NULL OR ScheduledTaskStatus NOT LIKE 'Cancelled')";
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "SELECT 
                    id as flight_id,
                    Route,
                    off_block,
                    on_block,
                    Rego,
                    adult,
                    child,
                    infant,
                    total_pax,
                    FltDate,
                    TaskStart,
                    TaskEnd,
                    AircraftID,
                    CmdPilotID,
                    FlightNo,
                    LastName,
                    FirstName,
                    FlightHours,
                    CommandHours,
                    AllCrew,
                    ScheduledRoute,
                    ScheduledTaskType,
                    ScheduledTaskStatus,
                    boarding,
                    gate_closed,
                    landed,
                    ready,
                    start,
                    takeoff,
                    taxi,
                    pcs,
                    weight,
                    uplift_fuel,
                    uplft_lbs
                  FROM flights 
                  $whereClause
                  ORDER BY FltDate DESC, TaskStart ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process the data for the report
        // All flights in flights table are SCHEDULED REVENUE FLIGHTS
        $reportData = [];
        
        // CAA Standard Metrics
        $aircraftKilometres = 0; // مجموع (تعداد پرواز × مسافت)
        $aircraftDepartures = 0; // تعداد برخاست/نشست
        $aircraftHours = 0; // مجموع ساعات block-to-block
        $passengersCarried = 0; // مجموع مسافران (با توجه به منطق CAA)
        $freightTonnesCarried = 0; // تناژ بار حمل شده
        $mailTonnesCarried = 0; // تناژ پست حمل شده
        $passengerKilometresPerformed = 0; // مجموع (مسافران × مسافت)
        $seatKilometresAvailable = 0; // مجموع (صندلی‌ها × مسافت)
        $tonneKilometresPerformedPassengers = 0; // تن-کیلومتر مسافران (با بار)
        $tonneKilometresPerformedFreight = 0; // تن-کیلومتر بار
        $tonneKilometresPerformedMail = 0; // تن-کیلومتر پست
        $tonneKilometresAvailable = 0; // ظرفیت قابل درآمدزایی
        
        // Constants for calculations
        $averagePassengerWeight = 80; // وزن متوسط هر مسافر (کیلوگرم)
        $averageBaggagePerPassenger = 20; // وزن متوسط بار همراه هر مسافر (کیلوگرم)
        $averageExcessBaggagePerPassenger = 0; // اضافه بار (می‌توان از فیلد weight استفاده کرد)
        
        // Legacy totals for backward compatibility (initialize before loop)
        $totalFlights = 0;
        $totalPassengers = 0;
        $totalFlightHours = 0;
        $totalSeatsOffered = 0;
        $totalDistanceKm = 0;
        $totalPassengerKm = 0;
        $totalSeatKm = 0;
        $totalCargoWeight = 0;
        $totalMailWeight = 0;
        $totalCargoTonKm = 0;
        $totalOfferedTonKm = 0;
        
        // Aircraft capacity constants based on registration (CASE Rego logic)
        $aircraftCapacities = [
            'EP-NEB' => ['seats' => 45, 'cargo_capacity' => 2000], // EP-NEB = 45 seats
            'EP-NEA' => ['seats' => 30, 'cargo_capacity' => 1500], // EP-NEA = 30 seats
            'EP-NEC' => ['seats' => 45, 'cargo_capacity' => 2000], // EP-NEC = 45 seats
            'default' => ['seats' => 30, 'cargo_capacity' => 1500] // Default capacity = 30 seats
        ];
        
        // Fallback route distances in kilometers (used only if route not found in database)
        $fallbackRouteDistances = [
            'RAS-THR' => 120,
            'THR-RAS' => 120,
            'RAS-IFN' => 180,
            'IFN-RAS' => 180,
            'IFN-THR' => 300,
            'THR-IFN' => 300,
            'RAS-MHD' => 250,
            'MHD-RAS' => 250,
            'MHD-THR' => 400,
            'THR-MHD' => 400,
            'RAS-ABD' => 200,
            'ABD-RAS' => 200,
            'RAS-PGU' => 300,
            'PGU-RAS' => 300,
            'THR-PGU' => 350,
            'PGU-THR' => 350,
            'THR-AZD' => 450,
            'AZD-THR' => 450,
            'THR-KIH' => 500,
            'KIH-THR' => 500,
            'PGU-MHD' => 200,
            'MHD-PGU' => 200,
            'default' => 200 // Default distance
        ];
        
        foreach ($flights as $flight) {
            // Skip if FltDate is empty
            if (empty($flight['FltDate'])) {
                continue;
            }
            
            // Double check date range in PHP (in case of timezone or SQL issues)
            try {
                $flightDateTime = new DateTime($flight['FltDate']);
                $flightDate = $flightDateTime->format('Y-m-d');
                
                // Compare dates strictly
                if ($flightDate < $fromDate || $flightDate > $toDate) {
                    continue; // Skip flights outside date range
                }
            } catch (Exception $e) {
                // If date parsing fails, skip this flight
                continue;
            }
            
            $route = $flight['Route'] ?? '';
            $routeParts = explode('-', $route);
            $fromIata = $routeParts[0] ?? '';
            $toIata = $routeParts[1] ?? '';
            
            // Get passenger counts first
            $adultCount = intval($flight['adult'] ?? 0);
            $childCount = intval($flight['child'] ?? 0);
            $infantCount = intval($flight['infant'] ?? 0);
            $totalPax = intval($flight['total_pax'] ?? 0);
            
            // Calculate flight duration in hours
            // Use TaskStart and TaskEnd for duration calculation (block-to-block time)
            $chocksOut = $flight['off_block'] ?? '';
            $chocksIn = $flight['on_block'] ?? '';
            $duration = '';
            $flightHours = 0;
            
            // Try to calculate from TaskStart and TaskEnd first (most reliable)
            if (!empty($flight['TaskStart']) && !empty($flight['TaskEnd'])) {
                try {
                    $taskStart = new DateTime($flight['TaskStart']);
                    $taskEnd = new DateTime($flight['TaskEnd']);
                    $diff = $taskStart->diff($taskEnd);
                    $duration = $diff->format('%H:%I');
                    $flightHours = $diff->h + ($diff->i / 60) + ($diff->days * 24);
                    
                    // Format chocksOut and chocksIn for display (from TaskStart/TaskEnd)
                    if (empty($chocksOut)) {
                        $chocksOut = $taskStart->format('H:i');
                    }
                    if (empty($chocksIn)) {
                        $chocksIn = $taskEnd->format('H:i');
                    }
                } catch (Exception $e) {
                    // If TaskStart/TaskEnd parsing fails, try off_block/on_block
                    if (!empty($chocksOut) && !empty($chocksIn)) {
                        // Check if off_block/on_block are in HHMM format (4 digits)
                        if (strlen($chocksOut) == 4 && strlen($chocksIn) == 4 && 
                            ctype_digit($chocksOut) && ctype_digit($chocksIn)) {
                            // Parse HHMM format
                            $outHours = intval(substr($chocksOut, 0, 2));
                            $outMinutes = intval(substr($chocksOut, 2, 2));
                            $inHours = intval(substr($chocksIn, 0, 2));
                            $inMinutes = intval(substr($chocksIn, 2, 2));
                            
                            // Use flight date for time calculation
                            $flightDate = !empty($flight['FltDate']) ? date('Y-m-d', strtotime($flight['FltDate'])) : date('Y-m-d');
                            
                            try {
                                $outTime = new DateTime($flightDate . ' ' . sprintf('%02d:%02d:00', $outHours, $outMinutes));
                                $inTime = new DateTime($flightDate . ' ' . sprintf('%02d:%02d:00', $inHours, $inMinutes));
                                
                                // If inTime is before outTime, assume next day
                                if ($inTime < $outTime) {
                                    $inTime->modify('+1 day');
                                }
                                
                                $diff = $outTime->diff($inTime);
                                $duration = $diff->format('%H:%I');
                                $flightHours = $diff->h + ($diff->i / 60) + ($diff->days * 24);
                                
                                // Format for display
                                $chocksOut = sprintf('%02d:%02d', $outHours, $outMinutes);
                                $chocksIn = sprintf('%02d:%02d', $inHours, $inMinutes);
                            } catch (Exception $e2) {
                                $duration = 'N/A';
                                $flightHours = 0;
                            }
                        } else {
                            // Try parsing as datetime
                            try {
                                $outTime = new DateTime($chocksOut);
                                $inTime = new DateTime($chocksIn);
                                $diff = $outTime->diff($inTime);
                                $duration = $diff->format('%H:%I');
                                $flightHours = $diff->h + ($diff->i / 60) + ($diff->days * 24);
                            } catch (Exception $e3) {
                                $duration = 'N/A';
                                $flightHours = 0;
                            }
                        }
                    } else {
                        $duration = 'N/A';
                        $flightHours = 0;
                    }
                }
            } elseif (!empty($chocksOut) && !empty($chocksIn)) {
                // Fallback: Use off_block/on_block if TaskStart/TaskEnd not available
                // Check if off_block/on_block are in HHMM format (4 digits)
                if (strlen($chocksOut) == 4 && strlen($chocksIn) == 4 && 
                    ctype_digit($chocksOut) && ctype_digit($chocksIn)) {
                    // Parse HHMM format
                    $outHours = intval(substr($chocksOut, 0, 2));
                    $outMinutes = intval(substr($chocksOut, 2, 2));
                    $inHours = intval(substr($chocksIn, 0, 2));
                    $inMinutes = intval(substr($chocksIn, 2, 2));
                    
                    // Use flight date for time calculation
                    $flightDate = !empty($flight['FltDate']) ? date('Y-m-d', strtotime($flight['FltDate'])) : date('Y-m-d');
                    
                    try {
                        $outTime = new DateTime($flightDate . ' ' . sprintf('%02d:%02d:00', $outHours, $outMinutes));
                        $inTime = new DateTime($flightDate . ' ' . sprintf('%02d:%02d:00', $inHours, $inMinutes));
                        
                        // If inTime is before outTime, assume next day
                        if ($inTime < $outTime) {
                            $inTime->modify('+1 day');
                        }
                        
                        $diff = $outTime->diff($inTime);
                        $duration = $diff->format('%H:%I');
                        $flightHours = $diff->h + ($diff->i / 60) + ($diff->days * 24);
                        
                        // Format for display
                        $chocksOut = sprintf('%02d:%02d', $outHours, $outMinutes);
                        $chocksIn = sprintf('%02d:%02d', $inHours, $inMinutes);
                    } catch (Exception $e) {
                        $duration = 'N/A';
                        $flightHours = 0;
                    }
                } else {
                    // Try parsing as datetime
                    try {
                        $outTime = new DateTime($chocksOut);
                        $inTime = new DateTime($chocksIn);
                        $diff = $outTime->diff($inTime);
                        $duration = $diff->format('%H:%I');
                        $flightHours = $diff->h + ($diff->i / 60) + ($diff->days * 24);
                    } catch (Exception $e) {
                        $duration = 'N/A';
                        $flightHours = 0;
                    }
                }
            }
            
            // Check for empty data and same city pairs (after variables are defined)
            $hasEmptyData = empty($fromIata) || empty($toIata) || 
                           empty($chocksOut) || empty($chocksIn) || 
                           empty($flight['Rego']) || empty($flight['FltDate']) ||
                           ($adultCount == 0 && $childCount == 0 && $infantCount == 0) ||
                           empty($duration);
            
            $isSameCity = ($fromIata === $toIata);
            
            // Skip rows where From or To is empty
            if (empty($fromIata) || empty($toIata)) {
                continue;
            }
            
            // Get aircraft capacity based on registration (CASE Rego logic)
            $aircraftRego = $flight['Rego'] ?? '';
            $aircraftCapacity = $aircraftCapacities[$aircraftRego] ?? $aircraftCapacities['default'];
            $seatsOffered = $aircraftCapacity['seats']; // EP-NEB=45, EP-NEA=30, EP-NEC=45, else=30
            $cargoCapacity = $aircraftCapacity['cargo_capacity'];
            
            // Get route distance from database (or fallback to hard-coded values)
            $distanceKm = getRouteDistanceByIATA($fromIata, $toIata);
            
            // If not found in database, use fallback
            if ($distanceKm === null) {
            $routeKey = $fromIata . '-' . $toIata;
                $distanceKm = $fallbackRouteDistances[$routeKey] ?? $fallbackRouteDistances['default'];
            }
            
            // CAA Standard Calculations
            
            // 1. Aircraft kilometres: تعداد پرواز × مسافت (هر پرواز یک بار)
            $aircraftKilometres += $distanceKm;
            
            // 2. Aircraft departures: تعداد برخاست (هر پرواز یک برخاست)
            $aircraftDepartures += 1;
            
            // 3. Aircraft hours: ساعات block-to-block
            $aircraftHours += $flightHours;
            
            // 4. Passengers carried: مجموع مسافران (در این مرحله ساده - می‌توان بعداً منطق deduplication اضافه کرد)
            $passengersCarried += $totalPax;
            
            // 5. Freight tonnes: از فیلد weight (بار کارگو)
            // فیلد weight در جدول flights احتمالاً وزن بار کارگو است
            $freightWeightKg = floatval($flight['weight'] ?? 0);
            $freightTonnes = $freightWeightKg / 1000; // Convert kg to tonnes
            $freightTonnesCarried += $freightTonnes;
            
            // 6. Mail tonnes: معمولاً 0 یا از فیلد جداگانه (در حال حاضر 0)
            $mailWeightKg = 0; // Mail weight in kg (اگر فیلد جداگانه‌ای وجود داشته باشد، از آن استفاده می‌شود)
            $mailTonnes = $mailWeightKg / 1000;
            $mailTonnesCarried += $mailTonnes;
            
            // 7. Passenger-kilometres performed: مجموع (مسافران × مسافت)
            $passengerKm = $totalPax * $distanceKm;
            $passengerKilometresPerformed += $passengerKm;
            
            // 8. Seat-kilometres available: مجموع (صندلی‌ها × مسافت)
            $seatKm = $seatsOffered * $distanceKm;
            $seatKilometresAvailable += $seatKm;
            
            // 9. Tonne-kilometres performed:
            // a) Passengers (incl. baggage): وزن مسافران + بار همراه + اضافه بار
            // بر اساس استاندارد CAA: وزن متوسط مسافر 80 کیلو + بار همراه 20 کیلو
            // برای محاسبه دقیق‌تر، می‌توان از فیلد pcs (تعداد قطعات) یا weight استفاده کرد
            // اما در حال حاضر از وزن متوسط استفاده می‌کنیم
            $passengerWeightKg = ($totalPax * $averagePassengerWeight) + ($totalPax * $averageBaggagePerPassenger);
            // اضافه بار: اگر فیلد weight موجود باشد و بیشتر از بار همراه متوسط باشد، 
            // می‌توان بخشی از آن را اضافه بار در نظر گرفت (اما در حال حاضر weight را فقط freight در نظر می‌گیریم)
            // برای اضافه بار، می‌توان از فیلد pcs یا محاسبات دیگر استفاده کرد
            // در حال حاضر اضافه بار را 0 در نظر می‌گیریم مگر اینکه اطلاعات بیشتری داشته باشیم
            $excessBaggageKg = 0; // اضافه بار (می‌توان بعداً از فیلدهای دیگر محاسبه شود)
            $passengerWeightKg += $excessBaggageKg;
            $passengerTonKm = ($passengerWeightKg / 1000) * $distanceKm; // Convert to tonnes
            $tonneKilometresPerformedPassengers += $passengerTonKm;
            
            // b) Freight: تناژ بار کارگو × مسافت
            $freightTonKm = $freightTonnes * $distanceKm;
            $tonneKilometresPerformedFreight += $freightTonKm;
            
            // c) Mail: تناژ پست × مسافت
            $mailTonKm = $mailTonnes * $distanceKm;
            $tonneKilometresPerformedMail += $mailTonKm;
            
            // 10. Tonne-kilometres available: ظرفیت قابل درآمدزایی
            // ظرفیت کل = صندلی‌ها (با وزن مسافر) + ظرفیت بار
            $totalCapacityKg = ($seatsOffered * ($averagePassengerWeight + $averageBaggagePerPassenger)) + $cargoCapacity;
            $totalCapacityTonnes = $totalCapacityKg / 1000;
            $availableTonKm = $totalCapacityTonnes * $distanceKm;
            $tonneKilometresAvailable += $availableTonKm;
            
            // Legacy variables for backward compatibility
            $cargoWeight = $freightWeightKg;
            $mailWeight = $mailWeightKg;
            $cargoTonKm = $freightTonKm;
            $offeredTonKm = $availableTonKm;
            
            // Check for empty data in the actual displayed fields
            $displayedData = [
                'flight_id' => $flight['flight_id'],
                'from_iata' => $fromIata,
                'to_iata' => $toIata,
                'chocks_out' => $chocksOut,
                'chocks_in' => $chocksIn,
                'register' => $flight['Rego'] ?? '',
                'pax_adult' => $adultCount,
                'pax_child' => $childCount,
                'pax_infant' => $infantCount,
                'total_pax' => $totalPax,
                'duration' => $duration,
                'flight_date' => !empty($flight['FltDate']) ? date('Y-m-d', strtotime($flight['FltDate'])) : ''
            ];
            
            // Check if any displayed field is empty
            $hasEmptyDisplayData = false;
            foreach ($displayedData as $key => $value) {
                if (empty($value) && $value !== 0) {
                    $hasEmptyDisplayData = true;
                    break;
                }
            }
            
            $reportData[] = array_merge($displayedData, [
                'flight_hours' => $flightHours,
                'seats_offered' => $seatsOffered,
                'distance_km' => $distanceKm,
                'passenger_km' => $passengerKm,
                'seat_km' => $seatKm,
                'cargo_weight' => $cargoWeight,
                'mail_weight' => $mailWeight,
                'cargo_ton_km' => $cargoTonKm,
                'offered_ton_km' => $offeredTonKm,
                'task_start' => $flight['TaskStart'] ?? '',
                'task_end' => $flight['TaskEnd'] ?? '',
                'aircraft_id' => $flight['AircraftID'] ?? '',
                'aircraft_type' => $aircraftRego,
                'pilot_name' => trim(($flight['FirstName'] ?? '') . ' ' . ($flight['LastName'] ?? '')),
                'all_crew' => $flight['AllCrew'] ?? '',
                'scheduled_route' => $flight['ScheduledRoute'] ?? '',
                'task_type' => $flight['ScheduledTaskType'] ?? '',
                'task_status' => $flight['ScheduledTaskStatus'] ?? '',
                'boarding' => $flight['boarding'] ?? '',
                'gate_closed' => $flight['gate_closed'] ?? '',
                'landed' => $flight['landed'] ?? '',
                'ready' => $flight['ready'] ?? '',
                'start' => $flight['start'] ?? '',
                'takeoff' => $flight['takeoff'] ?? '',
                'taxi' => $flight['taxi'] ?? '',
                'pcs' => $flight['pcs'] ?? '',
                'weight' => $flight['weight'] ?? '',
                'uplift_fuel' => $flight['uplift_fuel'] ?? '',
                'uplft_lbs' => $flight['uplft_lbs'] ?? '',
                'has_empty_data' => $hasEmptyDisplayData,
                'is_same_city' => $isSameCity
            ]);
            
            // Legacy totals for backward compatibility
            $totalFlights++;
            $totalPassengers += $totalPax;
            $totalFlightHours += $flightHours;
            $totalSeatsOffered += $seatsOffered;
            $totalDistanceKm += $distanceKm;
            $totalPassengerKm += $passengerKm;
            $totalSeatKm += $seatKm;
            $totalCargoWeight += $cargoWeight;
            $totalMailWeight += $mailWeight;
            $totalCargoTonKm += $cargoTonKm;
            $totalOfferedTonKm += $offeredTonKm;
        }
        
        // CAA Standard Load Factors
        $passengerLoadFactor = $seatKilometresAvailable > 0 ? ($passengerKilometresPerformed / $seatKilometresAvailable) * 100 : 0;
        $weightLoadFactor = $tonneKilometresAvailable > 0 ? (($tonneKilometresPerformedPassengers + $tonneKilometresPerformedFreight + $tonneKilometresPerformedMail) / $tonneKilometresAvailable) * 100 : 0;
        
        // Legacy load factor for backward compatibility
        $cargoLoadFactor = $totalOfferedTonKm > 0 ? ($totalCargoTonKm / $totalOfferedTonKm) * 100 : 0;
        
        echo json_encode([
            'success' => true,
            'data' => $reportData,
            'summary' => [
                // CAA Standard Metrics
                'aircraft_kilometres' => $aircraftKilometres,
                'aircraft_departures' => $aircraftDepartures,
                'aircraft_hours' => $aircraftHours,
                'passengers_carried' => $passengersCarried,
                'freight_tonnes_carried' => $freightTonnesCarried,
                'mail_tonnes_carried' => $mailTonnesCarried,
                'passenger_kilometres_performed' => $passengerKilometresPerformed,
                'seat_kilometres_available' => $seatKilometresAvailable,
                'passenger_load_factor' => $passengerLoadFactor,
                'tonne_kilometres_performed_passengers' => $tonneKilometresPerformedPassengers,
                'tonne_kilometres_performed_freight' => $tonneKilometresPerformedFreight,
                'tonne_kilometres_performed_mail' => $tonneKilometresPerformedMail,
                'tonne_kilometres_performed_total' => $tonneKilometresPerformedPassengers + $tonneKilometresPerformedFreight + $tonneKilometresPerformedMail,
                'tonne_kilometres_available' => $tonneKilometresAvailable,
                'weight_load_factor' => $weightLoadFactor,
                
                // Legacy metrics for backward compatibility
                'total_flights' => $totalFlights,
                'total_flight_hours' => $totalFlightHours,
                'total_passengers' => $totalPassengers,
                'total_seats_offered' => $totalSeatsOffered,
                'total_distance_km' => $totalDistanceKm,
                'total_passenger_km' => $totalPassengerKm,
                'total_seat_km' => $totalSeatKm,
                'total_cargo_weight' => $totalCargoWeight,
                'total_mail_weight' => $totalMailWeight,
                'total_cargo_ton_km' => $totalCargoTonKm,
                'total_offered_ton_km' => $totalOfferedTonKm,
                'cargo_load_factor' => $cargoLoadFactor,
                'average_passengers_per_flight' => $totalFlights > 0 ? round($totalPassengers / $totalFlights, 2) : 0
            ]
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage(),
            'debug' => [
                'from_date' => $fromDate ?? 'not set',
                'to_date' => $toDate ?? 'not set'
            ]
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Operations Report - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Flight Operations Report</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Flight operations analysis and reporting</p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button onclick="toggleLanguage()" 
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-language mr-2"></i>
                                <span id="langToggle">فارسی</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Report Container -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <!-- Header Section -->
                    <div class="bg-blue-600 dark:bg-blue-900/20 text-white p-6 text-center">
                        <div class="text-2xl font-bold mb-2">CAOIRI</div>
                        <div class="text-lg mb-1">Civil Aviation Organization of IRAN</div>
                        <div class="text-base mb-2">Center for Statistics and Computing</div>
                        <div class="text-xl font-semibold">Flight Operations Report</div>
                    </div>

                    <!-- Form Controls -->
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col lg:flex-row lg:items-end gap-6">
                            <!-- Date Range Section -->
                            <div class="flex-1">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            <span data-en="From Date:" data-fa="از تاریخ:">From Date:</span>
                                        </label>
                                        <input type="date" id="fromDate" value="<?php echo date('Y-m-01'); ?>"
                                               class="w-full h-10 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white transition-colors duration-200">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            <span data-en="To Date:" data-fa="تا تاریخ:">To Date:</span>
                                        </label>
                                        <input type="date" id="toDate" value="<?php echo date('Y-m-t'); ?>"
                                               class="w-full h-10 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white transition-colors duration-200">
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons Section -->
                            <div class="flex flex-wrap gap-3 lg:flex-nowrap">
                                <button onclick="loadFlightData()" 
                                        class="inline-flex items-center justify-center h-10 px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200 shadow-sm hover:shadow-md">
                                    <i class="fas fa-download mr-2"></i>
                                    <span data-en="Load Flight Data" data-fa="بارگذاری داده های پرواز">Load Flight Data</span>
                                </button>
                                <button onclick="downloadExcel()" id="downloadBtn" disabled
                                        class="inline-flex items-center justify-center h-10 px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200 shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-green-600">
                                    <i class="fas fa-file-excel mr-2"></i>
                                    <span data-en="Download Excel" data-fa="دانلود اکسل">Download Excel</span>
                                </button>
                                <button onclick="downloadWord()" id="downloadWordBtn" disabled
                                        class="inline-flex items-center justify-center h-10 px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-blue-600">
                                    <i class="fas fa-file-word mr-2"></i>
                                    <span data-en="Download Word" data-fa="دانلود ورد">Download Word</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Comprehensive Statistics Table -->
                    <div id="summaryCards" class="hidden p-6 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    <span data-en="Flight Statistics Summary" data-fa="خلاصه آمار پروازها">Flight Statistics Summary</span>
                                </h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                                <span data-en="Metric" data-fa="معیار">Metric</span>
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                                <span data-en="Value" data-fa="مقدار">Value</span>
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                <span data-en="Unit" data-fa="واحد">Unit</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="statisticsBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <!-- Statistics will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div id="loading" class="hidden p-6 text-center">
                        <div class="flex items-center justify-center">
                            <i class="fas fa-spinner fa-spin text-2xl text-blue-600 mr-3"></i>
                            <span class="text-lg text-gray-600 dark:text-gray-400">
                                <span data-en="Loading flight data..." data-fa="در حال بارگذاری داده های پرواز...">Loading flight data...</span>
                            </span>
                        </div>
                    </div>

                    <!-- Error State -->
                    <div id="error" class="hidden p-6">
                        <div class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-red-800 dark:text-red-200" id="errorMessage"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Data State -->
                    <div id="noData" class="hidden p-6 text-center">
                        <div class="text-gray-500 dark:text-gray-400">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p class="text-lg">
                                <span data-en="No flight data available for the selected period" data-fa="هیچ داده پروازی برای دوره انتخابی موجود نیست">No flight data available for the selected period</span>
                            </p>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div id="tableContainer" class="hidden overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                        <span data-en="Flight ID" data-fa="شناسه پرواز">Flight ID</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                        <span data-en="From" data-fa="از">From</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                        <span data-en="To" data-fa="به">To</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                        <span data-en="Register" data-fa="ثبت">Register</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                        <span data-en="Chocks Out" data-fa="خروج">Chocks Out</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                        <span data-en="Chocks In" data-fa="ورود">Chocks In</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                        <span data-en="Duration" data-fa="مدت">Duration</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                        <span data-en="Adult" data-fa="بزرگسال">Adult</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                        <span data-en="Child" data-fa="کودک">Child</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                        <span data-en="Infant" data-fa="نوزاد">Infant</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                        <span data-en="Total Pax" data-fa="کل مسافر">Total Pax</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        <span data-en="Flight Date" data-fa="تاریخ پرواز">Flight Date</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Data will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentData = [];
        let currentLanguage = 'en';

        function toggleLanguage() {
            currentLanguage = currentLanguage === 'en' ? 'fa' : 'en';
            updateLanguage();
        }

        function updateLanguage() {
            const elements = document.querySelectorAll('[data-en][data-fa]');
            elements.forEach(element => {
                element.textContent = element.getAttribute(`data-${currentLanguage}`);
            });
            
            const langToggle = document.getElementById('langToggle');
            langToggle.textContent = currentLanguage === 'en' ? 'فارسی' : 'English';
            
            // Update table headers
            const tableHeaders = document.querySelectorAll('thead th span[data-en][data-fa]');
            tableHeaders.forEach(header => {
                header.textContent = header.getAttribute(`data-${currentLanguage}`);
            });
            
            // Refresh statistics if data is loaded
            if (currentData.length > 0 && window.lastSummary) {
                displaySummary(window.lastSummary);
            }
        }

        function loadFlightData() {
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;

            if (!fromDate) {
                alert(currentLanguage === 'en' ? 'Please select a from date.' : 'لطفاً تاریخ شروع را انتخاب کنید.');
                return;
            }

            if (!toDate) {
                alert(currentLanguage === 'en' ? 'Please select a to date.' : 'لطفاً تاریخ پایان را انتخاب کنید.');
                return;
            }

            if (new Date(fromDate) > new Date(toDate)) {
                alert(currentLanguage === 'en' ? 'From date cannot be after to date.' : 'تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.');
                return;
            }

            // Show loading state
            document.getElementById('loading').classList.remove('hidden');
            document.getElementById('error').classList.add('hidden');
            document.getElementById('noData').classList.add('hidden');
            document.getElementById('tableContainer').classList.add('hidden');
            document.getElementById('summaryCards').classList.add('hidden');

            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'get_flight_data');
            formData.append('from_date', fromDate);
            formData.append('to_date', toDate);

            // Make AJAX request
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    document.getElementById('loading').classList.add('hidden');
                    
                    if (data.success) {
                        currentData = data.data;
                        window.lastSummary = data.summary; // Store summary for language switching
                        displayData(data.data);
                        displaySummary(data.summary);
                        document.getElementById('downloadBtn').disabled = false;
                        document.getElementById('downloadWordBtn').disabled = false;
                    } else {
                        showError(data.error || 'Failed to load flight data');
                        if (data.debug) {
                            console.log('Debug info:', data.debug);
                        }
                    }
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response text:', text);
                    document.getElementById('loading').classList.add('hidden');
                    showError('Invalid response format: ' + text.substring(0, 100));
                }
            })
            .catch(error => {
                document.getElementById('loading').classList.add('hidden');
                showError('Network error: ' + error.message);
            });
        }

        function displayData(data) {
            if (data.length === 0) {
                document.getElementById('noData').classList.remove('hidden');
                return;
            }

            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '';

            data.forEach(row => {
                const tr = document.createElement('tr');
                
                // Apply conditional styling
                let rowClass = 'hover:bg-gray-50 dark:hover:bg-gray-700';
                if (row.is_same_city) {
                    rowClass = 'bg-red-100 dark:bg-red-900/20 hover:bg-red-200 dark:hover:bg-red-900/30';
                } else if (row.has_empty_data) {
                    rowClass = 'bg-yellow-100 dark:bg-yellow-900/20 hover:bg-yellow-200 dark:hover:bg-yellow-900/30';
                }
                
                tr.className = rowClass;
                tr.innerHTML = `
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.flight_id}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.from_iata}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.to_iata}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.register}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.chocks_out}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.chocks_in}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.duration}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.pax_adult}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.pax_child}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.pax_infant}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">${row.total_pax}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">${row.flight_date}</td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('tableContainer').classList.remove('hidden');
        }

        function displaySummary(summary) {
            const statisticsBody = document.getElementById('statisticsBody');
            statisticsBody.innerHTML = '';
            
            // Format flight hours
            const aircraftHours = summary.aircraft_hours || summary.total_flight_hours || 0;
            const totalHours = Math.floor(aircraftHours);
            const totalMinutes = Math.round((aircraftHours - totalHours) * 60);
            const flightHoursFormatted = `${totalHours}h ${totalMinutes}m`;
            
            // Format distances (in thousands)
            const aircraftKmFormatted = ((summary.aircraft_kilometres || 0) / 1000).toFixed(2);
            const passengerKmFormatted = ((summary.passenger_kilometres_performed || summary.total_passenger_km || 0) / 1000).toFixed(2);
            const seatKmFormatted = ((summary.seat_kilometres_available || summary.total_seat_km || 0) / 1000).toFixed(2);
            
            // Format tonne-kilometres (in thousands)
            const tonneKmPassengersFormatted = ((summary.tonne_kilometres_performed_passengers || 0) / 1000).toFixed(2);
            const tonneKmFreightFormatted = ((summary.tonne_kilometres_performed_freight || 0) / 1000).toFixed(2);
            const tonneKmMailFormatted = ((summary.tonne_kilometres_performed_mail || 0) / 1000).toFixed(2);
            const tonneKmTotalFormatted = ((summary.tonne_kilometres_performed_total || 0) / 1000).toFixed(2);
            const tonneKmAvailableFormatted = ((summary.tonne_kilometres_available || 0) / 1000).toFixed(2);
            
            // Format load factors
            const passengerLoadFactorFormatted = (summary.passenger_load_factor || 0).toFixed(2);
            const weightLoadFactorFormatted = (summary.weight_load_factor || 0).toFixed(2);
            
            // CAA Standard Statistics - SCHEDULED REVENUE FLIGHTS
            const statistics = [
                {
                    section: currentLanguage === 'en' ? 'SCHEDULED REVENUE FLIGHTS' : 'پروازهای برنامه‌ای درآمدزا',
                    metrics: [
                        {
                            metric: currentLanguage === 'en' ? 'Aircraft kilometres' : 'کیلومتر هواپیما',
                            value: aircraftKmFormatted,
                            unit: '×1000 km'
                        },
                        {
                            metric: currentLanguage === 'en' ? 'Aircraft departures' : 'تعداد برخاست',
                            value: (summary.aircraft_departures || summary.total_flights || 0).toLocaleString(),
                    unit: ''
                },
                {
                            metric: currentLanguage === 'en' ? 'Aircraft hours' : 'ساعات هواپیما',
                    value: flightHoursFormatted,
                    unit: ''
                },
                {
                            metric: currentLanguage === 'en' ? 'Passengers carried' : 'مسافران حمل‌شده',
                            value: (summary.passengers_carried || summary.total_passengers || 0).toLocaleString(),
                    unit: ''
                },
                {
                            metric: currentLanguage === 'en' ? 'Freight tonnes carried' : 'تناژ بار حمل‌شده',
                            value: (summary.freight_tonnes_carried || 0).toFixed(2),
                            unit: 'tonnes'
                },
                {
                            metric: currentLanguage === 'en' ? 'Mail tonnes carried' : 'تناژ پست حمل‌شده',
                            value: (summary.mail_tonnes_carried || 0).toFixed(2),
                            unit: 'tonnes'
                },
                {
                            metric: currentLanguage === 'en' ? 'Passenger-kilometres performed' : 'مسافر-کیلومتر انجام‌شده',
                    value: passengerKmFormatted,
                            unit: '×1000'
                },
                {
                            metric: currentLanguage === 'en' ? 'Seat-kilometres available' : 'صندلی-کیلومتر در دسترس',
                    value: seatKmFormatted,
                            unit: '×1000'
                },
                {
                            metric: currentLanguage === 'en' ? 'Passenger load factor' : 'ضریب ظرفیت مسافر',
                    value: passengerLoadFactorFormatted,
                    unit: '%'
                },
                {
                            metric: currentLanguage === 'en' ? 'Tonne-kilometres performed (passengers incl. baggage)' : 'تن-کیلومتر انجام‌شده (مسافران شامل بار)',
                            value: tonneKmPassengersFormatted,
                            unit: '×1000'
                        },
                        {
                            metric: currentLanguage === 'en' ? 'Tonne-kilometres performed (freight)' : 'تن-کیلومتر انجام‌شده (بار)',
                            value: tonneKmFreightFormatted,
                            unit: '×1000'
                },
                {
                            metric: currentLanguage === 'en' ? 'Tonne-kilometres performed (mail)' : 'تن-کیلومتر انجام‌شده (پست)',
                            value: tonneKmMailFormatted,
                            unit: '×1000'
                },
                {
                            metric: currentLanguage === 'en' ? 'Tonne-kilometres performed (total)' : 'تن-کیلومتر انجام‌شده (کل)',
                            value: tonneKmTotalFormatted,
                            unit: '×1000'
                },
                {
                            metric: currentLanguage === 'en' ? 'Tonne-kilometres available' : 'تن-کیلومتر در دسترس',
                            value: tonneKmAvailableFormatted,
                            unit: '×1000'
                },
                {
                            metric: currentLanguage === 'en' ? 'Weight load factor' : 'ضریب ظرفیت وزنی',
                            value: weightLoadFactorFormatted,
                    unit: '%'
                }
                    ]
                }
            ];
            
            statistics.forEach(section => {
                // Add section header
                const sectionTr = document.createElement('tr');
                sectionTr.className = 'bg-blue-50 dark:bg-blue-900/20';
                sectionTr.innerHTML = `
                    <td colspan="3" class="px-6 py-3 text-sm font-bold text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">
                        ${section.section}
                    </td>
                `;
                statisticsBody.appendChild(sectionTr);
                
                // Add metrics
                section.metrics.forEach(stat => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-700';
                tr.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">
                        ${stat.metric}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">
                        ${stat.value}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                        ${stat.unit}
                    </td>
                `;
                statisticsBody.appendChild(tr);
                });
            });
            
            document.getElementById('summaryCards').classList.remove('hidden');
        }

        function showError(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('error').classList.remove('hidden');
        }

        function downloadExcel() {
            if (currentData.length === 0) {
                alert(currentLanguage === 'en' ? 'No data to download. Please load flight data first.' : 'هیچ داده ای برای دانلود موجود نیست. لطفاً ابتدا داده های پرواز را بارگذاری کنید.');
                return;
            }
            
            // Create CSV content
            const headers = [
                'Flight ID',
                'From',
                'To', 
                'Register',
                'Chocks Out',
                'Chocks In',
                'Duration',
                'Adult',
                'Child',
                'Infant',
                'Total Pax',
                'Flight Date'
            ];
            
            const csvContent = [
                headers.join(','),
                ...currentData.map(row => [
                    row.flight_id,
                    `"${row.from_iata}"`,
                    `"${row.to_iata}"`,
                    `"${row.register}"`,
                    `"${row.chocks_out}"`,
                    `"${row.chocks_in}"`,
                    `"${row.duration}"`,
                    row.pax_adult,
                    row.pax_child,
                    row.pax_infant,
                    row.total_pax,
                    `"${row.flight_date}"`
                ].join(','))
            ].join('\n');
            
            // Create and download file
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `flight_operations_${document.getElementById('fromDate').value}_to_${document.getElementById('toDate').value}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function downloadWord() {
            if (currentData.length === 0) {
                alert(currentLanguage === 'en' ? 'No data to download. Please load flight data first.' : 'هیچ داده ای برای دانلود موجود نیست. لطفاً ابتدا داده های پرواز را بارگذاری کنید.');
                return;
            }
            
            // Create HTML content for Word document
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            
            const htmlContent = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Flight Operations Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .subtitle { font-size: 18px; margin-bottom: 5px; }
        .org { font-size: 16px; margin-bottom: 5px; }
        .center { font-size: 14px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .summary { margin-top: 30px; }
        .summary h3 { font-size: 18px; margin-bottom: 15px; }
        .summary table { width: 50%; }
        .footer { margin-top: 40px; border-top: 1px solid #000; padding-top: 20px; }
        .contact-info { display: flex; justify-content: space-between; }
        .contact-left, .contact-right { width: 45%; }
        .contact-row { margin-bottom: 10px; }
        .signature-line { border-bottom: 1px solid #000; width: 200px; display: inline-block; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">CAOIRI</div>
        <div class="subtitle">Civil Aviation Organization of IRAN</div>
        <div class="center">Center for Statistics and Computing</div>
        <div class="title">Flight Operations Report</div>
        <div>Period: ${fromDate} to ${toDate}</div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Flight ID</th>
                <th>From</th>
                <th>To</th>
                <th>Register</th>
                <th>Chocks Out</th>
                <th>Chocks In</th>
                <th>Duration</th>
                <th>Adult</th>
                <th>Child</th>
                <th>Infant</th>
                <th>Total Pax</th>
                <th>Flight Date</th>
            </tr>
        </thead>
        <tbody>
            ${currentData.map(row => `
                <tr>
                    <td>${row.flight_id}</td>
                    <td>${row.from_iata}</td>
                    <td>${row.to_iata}</td>
                    <td>${row.register}</td>
                    <td>${row.chocks_out}</td>
                    <td>${row.chocks_in}</td>
                    <td>${row.duration}</td>
                    <td>${row.pax_adult}</td>
                    <td>${row.pax_child}</td>
                    <td>${row.pax_infant}</td>
                    <td>${row.total_pax}</td>
                    <td>${row.flight_date}</td>
                </tr>
            `).join('')}
        </tbody>
    </table>
    
    <div class="summary">
        <h3>Flight Statistics Summary</h3>
        <table>
            <tr><td>Total Flights</td><td>${currentData.length}</td></tr>
            <tr><td>Total Passengers</td><td>${currentData.reduce((sum, row) => sum + row.total_pax, 0)}</td></tr>
            <tr><td>Report Generated</td><td>${new Date().toLocaleDateString()}</td></tr>
        </table>
    </div>
    
    <div class="footer">
        <div class="contact-info">
            <div class="contact-left">
                <div class="contact-row">
                    <strong>Contact Person:</strong> سمیه کاکاوند / Somayeh Kakavand
                </div>
                <div class="contact-row">
                    <strong>Tel:</strong> 09121471778
                </div>
                <div class="contact-row">
                    <strong>Email:</strong> kakavand.s@raimonairways.net
                </div>
            </div>
            <div class="contact-right">
                <div class="contact-row">
                    <strong>Signature:</strong> <span class="signature-line"></span>
                </div>
                <div class="contact-row" style="margin-top: 20px;">
                    <strong>Form No.1</strong>
                </div>
            </div>
        </div>
    </div>
</body>
</html>`;
            
            // Create and download file
            const blob = new Blob([htmlContent], { type: 'application/msword' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `flight_operations_${fromDate}_to_${toDate}.doc`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize language
            updateLanguage();
        });
    </script>
</body>
</html>
