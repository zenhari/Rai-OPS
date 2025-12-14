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
        
        // Join with stations table to get location_type for both origin and destination
        $query = "SELECT 
                    f.id as flight_id,
                    f.Route,
                    f.off_block,
                    f.on_block,
                    f.Rego,
                    f.adult,
                    f.child,
                    f.infant,
                    f.total_pax,
                    f.FltDate,
                    f.TaskStart,
                    f.TaskEnd,
                    f.AircraftID,
                    f.CmdPilotID,
                    f.FlightNo,
                    f.LastName,
                    f.FirstName,
                    f.FlightHours,
                    f.CommandHours,
                    f.AllCrew,
                    f.ScheduledRoute,
                    f.ScheduledTaskType,
                    f.ScheduledTaskStatus,
                    f.boarding,
                    f.gate_closed,
                    f.landed,
                    f.ready,
                    f.start,
                    f.takeoff,
                    f.taxi,
                    f.pcs,
                    f.weight,
                    f.uplift_fuel,
                    f.uplft_lbs,
                    SUBSTRING_INDEX(f.Route, '-', 1) as from_code,
                    SUBSTRING_INDEX(f.Route, '-', -1) as to_code,
                    s_from.location_type as from_location_type,
                    s_to.location_type as to_location_type
                  FROM flights f
                  LEFT JOIN stations s_from ON s_from.iata_code = SUBSTRING_INDEX(f.Route, '-', 1)
                  LEFT JOIN stations s_to ON s_to.iata_code = SUBSTRING_INDEX(f.Route, '-', -1)
                  $whereClause
                  ORDER BY f.FltDate DESC, f.TaskStart ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process the data for the report
        // All flights in flights table are SCHEDULED REVENUE FLIGHTS
        $reportData = [];
        
        // CAA Standard Metrics - Total
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
        
        // CAA Standard Metrics - Domestic (both stations must be Domestic)
        $aircraftKilometresDomestic = 0;
        $aircraftDeparturesDomestic = 0;
        $aircraftHoursDomestic = 0;
        $passengersCarriedDomestic = 0;
        $freightTonnesCarriedDomestic = 0;
        $mailTonnesCarriedDomestic = 0;
        $passengerKilometresPerformedDomestic = 0;
        $seatKilometresAvailableDomestic = 0;
        $tonneKilometresPerformedPassengersDomestic = 0;
        $tonneKilometresPerformedFreightDomestic = 0;
        $tonneKilometresPerformedMailDomestic = 0;
        $tonneKilometresAvailableDomestic = 0;
        
        // CAA Standard Metrics - International (at least one station is International)
        $aircraftKilometresInternational = 0;
        $aircraftDeparturesInternational = 0;
        $aircraftHoursInternational = 0;
        $passengersCarriedInternational = 0;
        $freightTonnesCarriedInternational = 0;
        $mailTonnesCarriedInternational = 0;
        $passengerKilometresPerformedInternational = 0;
        $seatKilometresAvailableInternational = 0;
        $tonneKilometresPerformedPassengersInternational = 0;
        $tonneKilometresPerformedFreightInternational = 0;
        $tonneKilometresPerformedMailInternational = 0;
        $tonneKilometresAvailableInternational = 0;
        
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
            
            // Check location types from query result
            $fromLocationType = $flight['from_location_type'] ?? null;
            $toLocationType = $flight['to_location_type'] ?? null;
            
            // Determine if flight is Domestic (both stations must be Domestic)
            $isDomestic = ($fromLocationType === 'Domestic' && $toLocationType === 'Domestic');
            
            // Determine if flight is International (at least one station is International)
            $isInternational = ($fromLocationType === 'International' || $toLocationType === 'International');
            
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
            
            // Add to Domestic metrics if both stations are Domestic
            if ($isDomestic) {
                $aircraftKilometresDomestic += $distanceKm;
                $aircraftDeparturesDomestic += 1;
                $aircraftHoursDomestic += $flightHours;
                $passengersCarriedDomestic += $totalPax;
                $freightTonnesCarriedDomestic += $freightTonnes;
                $mailTonnesCarriedDomestic += $mailTonnes;
                $passengerKilometresPerformedDomestic += $passengerKm;
                $seatKilometresAvailableDomestic += $seatKm;
                $tonneKilometresPerformedPassengersDomestic += $passengerTonKm;
                $tonneKilometresPerformedFreightDomestic += $freightTonKm;
                $tonneKilometresPerformedMailDomestic += $mailTonKm;
                $tonneKilometresAvailableDomestic += $availableTonKm;
            }
            
            // Add to International metrics if at least one station is International
            if ($isInternational) {
                $aircraftKilometresInternational += $distanceKm;
                $aircraftDeparturesInternational += 1;
                $aircraftHoursInternational += $flightHours;
                $passengersCarriedInternational += $totalPax;
                $freightTonnesCarriedInternational += $freightTonnes;
                $mailTonnesCarriedInternational += $mailTonnes;
                $passengerKilometresPerformedInternational += $passengerKm;
                $seatKilometresAvailableInternational += $seatKm;
                $tonneKilometresPerformedPassengersInternational += $passengerTonKm;
                $tonneKilometresPerformedFreightInternational += $freightTonKm;
                $tonneKilometresPerformedMailInternational += $mailTonKm;
                $tonneKilometresAvailableInternational += $availableTonKm;
            }
            
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
        
        // Domestic Load Factors
        $passengerLoadFactorDomestic = $seatKilometresAvailableDomestic > 0 ? ($passengerKilometresPerformedDomestic / $seatKilometresAvailableDomestic) * 100 : 0;
        $weightLoadFactorDomestic = $tonneKilometresAvailableDomestic > 0 ? (($tonneKilometresPerformedPassengersDomestic + $tonneKilometresPerformedFreightDomestic + $tonneKilometresPerformedMailDomestic) / $tonneKilometresAvailableDomestic) * 100 : 0;
        
        // International Load Factors
        $passengerLoadFactorInternational = $seatKilometresAvailableInternational > 0 ? ($passengerKilometresPerformedInternational / $seatKilometresAvailableInternational) * 100 : 0;
        $weightLoadFactorInternational = $tonneKilometresAvailableInternational > 0 ? (($tonneKilometresPerformedPassengersInternational + $tonneKilometresPerformedFreightInternational + $tonneKilometresPerformedMailInternational) / $tonneKilometresAvailableInternational) * 100 : 0;
        
        // Legacy load factor for backward compatibility
        $cargoLoadFactor = $totalOfferedTonKm > 0 ? ($totalCargoTonKm / $totalOfferedTonKm) * 100 : 0;
        
        // Prepare detailed calculation data for each metric
        $calculationDetails = [
            'aircraft_kilometres' => array_column($reportData, 'distance_km'),
            'aircraft_departures' => array_fill(0, count($reportData), 1), // Each flight = 1 departure
            'aircraft_hours' => array_column($reportData, 'flight_hours'),
            'passengers_carried' => array_column($reportData, 'total_pax'),
            'freight_tonnes_carried' => array_map(function($row) {
                return floatval($row['cargo_weight'] ?? 0) / 1000;
            }, $reportData),
            'mail_tonnes_carried' => array_fill(0, count($reportData), 0), // Currently 0
            'passenger_kilometres_performed' => array_column($reportData, 'passenger_km'),
            'seat_kilometres_available' => array_column($reportData, 'seat_km'),
            'tonne_kilometres_performed_passengers' => array_map(function($row) {
                $totalPax = intval($row['total_pax'] ?? 0);
                $distanceKm = floatval($row['distance_km'] ?? 0);
                $passengerWeightKg = ($totalPax * 80) + ($totalPax * 20); // 80kg passenger + 20kg baggage
                return ($passengerWeightKg / 1000) * $distanceKm;
            }, $reportData),
            'tonne_kilometres_performed_freight' => array_column($reportData, 'cargo_ton_km'),
            'tonne_kilometres_performed_mail' => array_fill(0, count($reportData), 0), // Currently 0
            'tonne_kilometres_available' => array_column($reportData, 'offered_ton_km')
        ];
        
        // Calculation methods for auditor
        $calculationMethods = [
            'aircraft_kilometres' => [
                'en' => 'Sum of (distance in km) for each flight. Formula: Σ(distance_km) for all flights in date range.',
                'fa' => 'مجموع مسافت (کیلومتر) برای هر پرواز. فرمول: Σ(مسافت_کیلومتر) برای تمام پروازهای بازه انتخابی.'
            ],
            'aircraft_departures' => [
                'en' => 'Count of all flights (each flight = 1 departure). Formula: COUNT(flights) where FltDate is within date range.',
                'fa' => 'تعداد کل پروازها (هر پرواز = 1 برخاست). فرمول: COUNT(پروازها) که FltDate در بازه انتخابی باشد.'
            ],
            'aircraft_hours' => [
                'en' => 'Sum of block-to-block flight hours. Calculated from TaskStart and TaskEnd (or off_block/on_block). Formula: Σ(flight_hours) for all flights.',
                'fa' => 'مجموع ساعات پرواز block-to-block. محاسبه از TaskStart و TaskEnd (یا off_block/on_block). فرمول: Σ(ساعات_پرواز) برای تمام پروازها.'
            ],
            'passengers_carried' => [
                'en' => 'Sum of total passengers (adult + child + infant) for all flights. Formula: Σ(total_pax) for all flights.',
                'fa' => 'مجموع کل مسافران (بزرگسال + کودک + نوزاد) برای تمام پروازها. فرمول: Σ(total_pax) برای تمام پروازها.'
            ],
            'freight_tonnes_carried' => [
                'en' => 'Sum of freight weight converted from kg to tonnes. Formula: Σ(weight_kg / 1000) for all flights.',
                'fa' => 'مجموع وزن بار کارگو تبدیل شده از کیلوگرم به تن. فرمول: Σ(weight_kg / 1000) برای تمام پروازها.'
            ],
            'mail_tonnes_carried' => [
                'en' => 'Sum of mail weight (currently 0 as no mail field exists). Formula: Σ(mail_weight_kg / 1000) = 0.',
                'fa' => 'مجموع وزن پست (در حال حاضر 0 چون فیلد پست وجود ندارد). فرمول: Σ(mail_weight_kg / 1000) = 0.'
            ],
            'passenger_kilometres_performed' => [
                'en' => 'Sum of (passengers × distance) for each flight. Formula: Σ(total_pax × distance_km) for all flights.',
                'fa' => 'مجموع (مسافران × مسافت) برای هر پرواز. فرمول: Σ(total_pax × مسافت_کیلومتر) برای تمام پروازها.'
            ],
            'seat_kilometres_available' => [
                'en' => 'Sum of (seats × distance) for each flight. Seats based on aircraft registration: EP-NEB=45, EP-NEA=30, EP-NEC=45, default=30. Formula: Σ(seats × distance_km) for all flights.',
                'fa' => 'مجموع (صندلی‌ها × مسافت) برای هر پرواز. صندلی‌ها بر اساس ثبت هواپیما: EP-NEB=45، EP-NEA=30، EP-NEC=45، پیش‌فرض=30. فرمول: Σ(صندلی‌ها × مسافت_کیلومتر) برای تمام پروازها.'
            ],
            'passenger_load_factor' => [
                'en' => 'Percentage of seat capacity utilized. Formula: (passenger_kilometres_performed / seat_kilometres_available) × 100.',
                'fa' => 'درصد استفاده از ظرفیت صندلی. فرمول: (مسافر_کیلومتر_انجام_شده / صندلی_کیلومتر_در_دسترس) × 100.'
            ],
            'tonne_kilometres_performed_passengers' => [
                'en' => 'Sum of (passenger weight in tonnes × distance) for each flight. Passenger weight = (total_pax × 80kg) + (total_pax × 20kg baggage). Formula: Σ(((total_pax × 100kg) / 1000) × distance_km) for all flights.',
                'fa' => 'مجموع (وزن مسافران به تن × مسافت) برای هر پرواز. وزن مسافر = (total_pax × 80kg) + (total_pax × 20kg بار). فرمول: Σ(((total_pax × 100kg) / 1000) × مسافت_کیلومتر) برای تمام پروازها.'
            ],
            'tonne_kilometres_performed_freight' => [
                'en' => 'Sum of (freight tonnes × distance) for each flight. Formula: Σ(freight_tonnes × distance_km) for all flights.',
                'fa' => 'مجموع (تن بار × مسافت) برای هر پرواز. فرمول: Σ(تن_بار × مسافت_کیلومتر) برای تمام پروازها.'
            ],
            'tonne_kilometres_performed_mail' => [
                'en' => 'Sum of (mail tonnes × distance) for each flight (currently 0). Formula: Σ(mail_tonnes × distance_km) = 0.',
                'fa' => 'مجموع (تن پست × مسافت) برای هر پرواز (در حال حاضر 0). فرمول: Σ(تن_پست × مسافت_کیلومتر) = 0.'
            ],
            'tonne_kilometres_performed_total' => [
                'en' => 'Sum of all tonne-kilometres performed (passengers + freight + mail). Formula: tonne_km_passengers + tonne_km_freight + tonne_km_mail.',
                'fa' => 'مجموع تمام تن-کیلومتر انجام شده (مسافران + بار + پست). فرمول: تن_کیلومتر_مسافران + تن_کیلومتر_بار + تن_کیلومتر_پست.'
            ],
            'tonne_kilometres_available' => [
                'en' => 'Sum of (total capacity in tonnes × distance) for each flight. Total capacity = (seats × 100kg passenger+baggage) + cargo_capacity. Formula: Σ(((seats × 100kg + cargo_capacity_kg) / 1000) × distance_km) for all flights.',
                'fa' => 'مجموع (ظرفیت کل به تن × مسافت) برای هر پرواز. ظرفیت کل = (صندلی‌ها × 100kg مسافر+بار) + ظرفیت_بار. فرمول: Σ(((صندلی‌ها × 100kg + ظرفیت_بار_کیلوگرم) / 1000) × مسافت_کیلومتر) برای تمام پروازها.'
            ],
            'weight_load_factor' => [
                'en' => 'Percentage of weight capacity utilized. Formula: (tonne_km_performed_total / tonne_km_available) × 100.',
                'fa' => 'درصد استفاده از ظرفیت وزنی. فرمول: (تن_کیلومتر_انجام_شده_کل / تن_کیلومتر_در_دسترس) × 100.'
            ]
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $reportData,
            'calculation_methods' => $calculationMethods,
            'calculation_details' => $calculationDetails,
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
                
                // Domestic Metrics (both stations must be Domestic)
                'aircraft_kilometres_domestic' => $aircraftKilometresDomestic,
                'aircraft_departures_domestic' => $aircraftDeparturesDomestic,
                'aircraft_hours_domestic' => $aircraftHoursDomestic,
                'passengers_carried_domestic' => $passengersCarriedDomestic,
                'freight_tonnes_carried_domestic' => $freightTonnesCarriedDomestic,
                'mail_tonnes_carried_domestic' => $mailTonnesCarriedDomestic,
                'passenger_kilometres_performed_domestic' => $passengerKilometresPerformedDomestic,
                'seat_kilometres_available_domestic' => $seatKilometresAvailableDomestic,
                'passenger_load_factor_domestic' => $passengerLoadFactorDomestic,
                'tonne_kilometres_performed_passengers_domestic' => $tonneKilometresPerformedPassengersDomestic,
                'tonne_kilometres_performed_freight_domestic' => $tonneKilometresPerformedFreightDomestic,
                'tonne_kilometres_performed_mail_domestic' => $tonneKilometresPerformedMailDomestic,
                'tonne_kilometres_performed_total_domestic' => $tonneKilometresPerformedPassengersDomestic + $tonneKilometresPerformedFreightDomestic + $tonneKilometresPerformedMailDomestic,
                'tonne_kilometres_available_domestic' => $tonneKilometresAvailableDomestic,
                'weight_load_factor_domestic' => $weightLoadFactorDomestic,
                
                // International Metrics (at least one station is International)
                'aircraft_kilometres_international' => $aircraftKilometresInternational,
                'aircraft_departures_international' => $aircraftDeparturesInternational,
                'aircraft_hours_international' => $aircraftHoursInternational,
                'passengers_carried_international' => $passengersCarriedInternational,
                'freight_tonnes_carried_international' => $freightTonnesCarriedInternational,
                'mail_tonnes_carried_international' => $mailTonnesCarriedInternational,
                'passenger_kilometres_performed_international' => $passengerKilometresPerformedInternational,
                'seat_kilometres_available_international' => $seatKilometresAvailableInternational,
                'passenger_load_factor_international' => $passengerLoadFactorInternational,
                'tonne_kilometres_performed_passengers_international' => $tonneKilometresPerformedPassengersInternational,
                'tonne_kilometres_performed_freight_international' => $tonneKilometresPerformedFreightInternational,
                'tonne_kilometres_performed_mail_international' => $tonneKilometresPerformedMailInternational,
                'tonne_kilometres_performed_total_international' => $tonneKilometresPerformedPassengersInternational + $tonneKilometresPerformedFreightInternational + $tonneKilometresPerformedMailInternational,
                'tonne_kilometres_available_international' => $tonneKilometresAvailableInternational,
                'weight_load_factor_international' => $weightLoadFactorInternational,
                
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
                                                <span data-en="Domestic Value" data-fa="مقدار داخلی">Domestic Value</span>
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">
                                                <span data-en="International Value" data-fa="مقدار بین‌المللی">International Value</span>
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

                    <!-- Calculation Methods Box -->
                    <div id="calculationMethodsBox" class="hidden p-6 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    <span data-en="Calculation Methods" data-fa="روش محاسبه">Calculation Methods</span>
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    <span data-en="Detailed explanation of how each metric is calculated based on the selected date range" data-fa="توضیحات دقیق نحوه محاسبه هر معیار بر اساس بازه انتخابی">Detailed explanation of how each metric is calculated based on the selected date range</span>
                                </p>
                            </div>
                            <div class="p-6">
                                <div id="calculationMethodsContent" class="space-y-4">
                                    <!-- Calculation methods will be populated here -->
                                </div>
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
            
            // Refresh calculation methods if available
            if (window.calculationMethods && window.calculationDetails && window.lastSummary) {
                displayCalculationMethods(window.calculationMethods, window.calculationDetails, window.lastSummary, currentData);
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
                        window.calculationMethods = data.calculation_methods || {}; // Store calculation methods
                        window.calculationDetails = data.calculation_details || {}; // Store calculation details
                        displayData(data.data);
                        displaySummary(data.summary);
                        displayCalculationMethods(data.calculation_methods || {}, data.calculation_details || {}, data.summary || {}, data.data || []);
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
            
            // Helper function to format values for Domestic and International
            function formatDomesticValue(key, summary) {
                const domesticKey = key + '_domestic';
                const value = summary[domesticKey] || 0;
                
                if (key === 'aircraft_hours') {
                    const hours = Math.floor(value);
                    const minutes = Math.round((value - hours) * 60);
                    return `${hours}h ${minutes}m`;
                } else if (key === 'aircraft_kilometres' || key === 'passenger_kilometres_performed' || key === 'seat_kilometres_available' || 
                           key.startsWith('tonne_kilometres')) {
                    return (value / 1000).toFixed(2);
                } else if (key.includes('load_factor')) {
                    return value.toFixed(2);
                } else if (key.includes('tonnes')) {
                    return value.toFixed(2);
                } else {
                    return Math.round(value).toLocaleString();
                }
            }
            
            function formatInternationalValue(key, summary) {
                const internationalKey = key + '_international';
                const value = summary[internationalKey] || 0;
                
                if (key === 'aircraft_hours') {
                    const hours = Math.floor(value);
                    const minutes = Math.round((value - hours) * 60);
                    return `${hours}h ${minutes}m`;
                } else if (key === 'aircraft_kilometres' || key === 'passenger_kilometres_performed' || key === 'seat_kilometres_available' || 
                           key.startsWith('tonne_kilometres')) {
                    return (value / 1000).toFixed(2);
                } else if (key.includes('load_factor')) {
                    return value.toFixed(2);
                } else if (key.includes('tonnes')) {
                    return value.toFixed(2);
                } else {
                    return Math.round(value).toLocaleString();
                }
            }
            
            statistics.forEach(section => {
                // Add section header
                const sectionTr = document.createElement('tr');
                sectionTr.className = 'bg-blue-50 dark:bg-blue-900/20';
                sectionTr.innerHTML = `
                    <td colspan="4" class="px-6 py-3 text-sm font-bold text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">
                        ${section.section}
                    </td>
                `;
                statisticsBody.appendChild(sectionTr);
                
                // Add metrics
                section.metrics.forEach(stat => {
                    // Map metric keys to summary keys
                    const metricKeyMap = {
                        'Aircraft kilometres': 'aircraft_kilometres',
                        'Aircraft departures': 'aircraft_departures',
                        'Aircraft hours': 'aircraft_hours',
                        'Passengers carried': 'passengers_carried',
                        'Freight tonnes carried': 'freight_tonnes_carried',
                        'Mail tonnes carried': 'mail_tonnes_carried',
                        'Passenger-kilometres performed': 'passenger_kilometres_performed',
                        'Seat-kilometres available': 'seat_kilometres_available',
                        'Passenger load factor': 'passenger_load_factor',
                        'Tonne-kilometres performed (passengers incl. baggage)': 'tonne_kilometres_performed_passengers',
                        'Tonne-kilometres performed (freight)': 'tonne_kilometres_performed_freight',
                        'Tonne-kilometres performed (mail)': 'tonne_kilometres_performed_mail',
                        'Tonne-kilometres performed (total)': 'tonne_kilometres_performed_total',
                        'Tonne-kilometres available': 'tonne_kilometres_available',
                        'Weight load factor': 'weight_load_factor'
                    };
                    
                    const persianKeyMap = {
                        'کیلومتر هواپیما': 'aircraft_kilometres',
                        'تعداد برخاست': 'aircraft_departures',
                        'ساعات هواپیما': 'aircraft_hours',
                        'مسافران حمل‌شده': 'passengers_carried',
                        'تناژ بار حمل‌شده': 'freight_tonnes_carried',
                        'تناژ پست حمل‌شده': 'mail_tonnes_carried',
                        'مسافر-کیلومتر انجام‌شده': 'passenger_kilometres_performed',
                        'صندلی-کیلومتر در دسترس': 'seat_kilometres_available',
                        'ضریب ظرفیت مسافر': 'passenger_load_factor',
                        'تن-کیلومتر انجام‌شده (مسافران شامل بار)': 'tonne_kilometres_performed_passengers',
                        'تن-کیلومتر انجام‌شده (بار)': 'tonne_kilometres_performed_freight',
                        'تن-کیلومتر انجام‌شده (پست)': 'tonne_kilometres_performed_mail',
                        'تن-کیلومتر انجام‌شده (کل)': 'tonne_kilometres_performed_total',
                        'تن-کیلومتر در دسترس': 'tonne_kilometres_available',
                        'ضریب ظرفیت وزنی': 'weight_load_factor'
                    };
                    
                    const summaryKey = metricKeyMap[stat.metric] || persianKeyMap[stat.metric] || '';
                    const domesticValue = summaryKey ? formatDomesticValue(summaryKey, summary) : '-';
                    const internationalValue = summaryKey ? formatInternationalValue(summaryKey, summary) : '-';
                    
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-700';
                    tr.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">
                            ${stat.metric}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">
                            ${domesticValue}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-600">
                            ${internationalValue}
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

        function displayCalculationMethods(methods, details, summary, flightData = []) {
            const contentDiv = document.getElementById('calculationMethodsContent');
            contentDiv.innerHTML = '';
            
            if (!methods || Object.keys(methods).length === 0) {
                contentDiv.innerHTML = '<p class="text-gray-600 dark:text-gray-400">' + 
                    (currentLanguage === 'en' ? 'No calculation methods available.' : 'روش محاسبه در دسترس نیست.') + 
                    '</p>';
                document.getElementById('calculationMethodsBox').classList.remove('hidden');
                return;
            }
            
            // Map metric keys to display names and summary keys
            const metricInfo = {
                'aircraft_kilometres': {
                    name: currentLanguage === 'en' ? 'Aircraft kilometres' : 'کیلومتر هواپیما',
                    summaryKey: 'aircraft_kilometres',
                    unit: 'km',
                    formatValue: (val) => val.toFixed(2)
                },
                'aircraft_departures': {
                    name: currentLanguage === 'en' ? 'Aircraft departures' : 'تعداد برخاست',
                    summaryKey: 'aircraft_departures',
                    unit: '',
                    formatValue: (val) => Math.round(val).toLocaleString()
                },
                'aircraft_hours': {
                    name: currentLanguage === 'en' ? 'Aircraft hours' : 'ساعات هواپیما',
                    summaryKey: 'aircraft_hours',
                    unit: 'hours',
                    formatValue: (val) => {
                        const hours = Math.floor(val);
                        const minutes = Math.round((val - hours) * 60);
                        return `${hours}h ${minutes}m`;
                    }
                },
                'passengers_carried': {
                    name: currentLanguage === 'en' ? 'Passengers carried' : 'مسافران حمل‌شده',
                    summaryKey: 'passengers_carried',
                    unit: '',
                    formatValue: (val) => Math.round(val).toLocaleString()
                },
                'freight_tonnes_carried': {
                    name: currentLanguage === 'en' ? 'Freight tonnes carried' : 'تناژ بار حمل‌شده',
                    summaryKey: 'freight_tonnes_carried',
                    unit: 'tonnes',
                    formatValue: (val) => val.toFixed(2)
                },
                'mail_tonnes_carried': {
                    name: currentLanguage === 'en' ? 'Mail tonnes carried' : 'تناژ پست حمل‌شده',
                    summaryKey: 'mail_tonnes_carried',
                    unit: 'tonnes',
                    formatValue: (val) => val.toFixed(2)
                },
                'passenger_kilometres_performed': {
                    name: currentLanguage === 'en' ? 'Passenger-kilometres performed' : 'مسافر-کیلومتر انجام‌شده',
                    summaryKey: 'passenger_kilometres_performed',
                    unit: '',
                    formatValue: (val) => (val / 1000).toFixed(2) + ' ×1000'
                },
                'seat_kilometres_available': {
                    name: currentLanguage === 'en' ? 'Seat-kilometres available' : 'صندلی-کیلومتر در دسترس',
                    summaryKey: 'seat_kilometres_available',
                    unit: '',
                    formatValue: (val) => (val / 1000).toFixed(2) + ' ×1000'
                },
                'passenger_load_factor': {
                    name: currentLanguage === 'en' ? 'Passenger load factor' : 'ضریب ظرفیت مسافر',
                    summaryKey: 'passenger_load_factor',
                    unit: '%',
                    formatValue: (val) => val.toFixed(2)
                },
                'tonne_kilometres_performed_passengers': {
                    name: currentLanguage === 'en' ? 'Tonne-kilometres performed (passengers incl. baggage)' : 'تن-کیلومتر انجام‌شده (مسافران شامل بار)',
                    summaryKey: 'tonne_kilometres_performed_passengers',
                    unit: '',
                    formatValue: (val) => (val / 1000).toFixed(2) + ' ×1000'
                },
                'tonne_kilometres_performed_freight': {
                    name: currentLanguage === 'en' ? 'Tonne-kilometres performed (freight)' : 'تن-کیلومتر انجام‌شده (بار)',
                    summaryKey: 'tonne_kilometres_performed_freight',
                    unit: '',
                    formatValue: (val) => (val / 1000).toFixed(2) + ' ×1000'
                },
                'tonne_kilometres_performed_mail': {
                    name: currentLanguage === 'en' ? 'Tonne-kilometres performed (mail)' : 'تن-کیلومتر انجام‌شده (پست)',
                    summaryKey: 'tonne_kilometres_performed_mail',
                    unit: '',
                    formatValue: (val) => (val / 1000).toFixed(2) + ' ×1000'
                },
                'tonne_kilometres_performed_total': {
                    name: currentLanguage === 'en' ? 'Tonne-kilometres performed (total)' : 'تن-کیلومتر انجام‌شده (کل)',
                    summaryKey: 'tonne_kilometres_performed_total',
                    unit: '',
                    formatValue: (val) => (val / 1000).toFixed(2) + ' ×1000'
                },
                'tonne_kilometres_available': {
                    name: currentLanguage === 'en' ? 'Tonne-kilometres available' : 'تن-کیلومتر در دسترس',
                    summaryKey: 'tonne_kilometres_available',
                    unit: '',
                    formatValue: (val) => (val / 1000).toFixed(2) + ' ×1000'
                },
                'weight_load_factor': {
                    name: currentLanguage === 'en' ? 'Weight load factor' : 'ضریب ظرفیت وزنی',
                    summaryKey: 'weight_load_factor',
                    unit: '%',
                    formatValue: (val) => val.toFixed(2)
                }
            };
            
            // Helper function to format calculation with actual numbers
            function formatCalculation(key, values, summary, flightDataParam) {
                const flightDataToUse = flightDataParam || flightData || [];
                if (!values || values.length === 0) {
                    return '';
                }
                
                const info = metricInfo[key];
                if (!info) return '';
                
                // For load factors, show the formula with actual values
                if (key === 'passenger_load_factor') {
                    const passengerKm = summary.passenger_kilometres_performed || 0;
                    const seatKm = summary.seat_kilometres_available || 0;
                    const result = summary.passenger_load_factor || 0;
                    return `${(passengerKm / 1000).toFixed(2)} / ${(seatKm / 1000).toFixed(2)} × 100 = ${result.toFixed(2)}%`;
                }
                
                if (key === 'weight_load_factor') {
                    const performed = (summary.tonne_kilometres_performed_passengers || 0) + 
                                     (summary.tonne_kilometres_performed_freight || 0) + 
                                     (summary.tonne_kilometres_performed_mail || 0);
                    const available = summary.tonne_kilometres_available || 0;
                    const result = summary.weight_load_factor || 0;
                    return `${(performed / 1000).toFixed(2)} / ${(available / 1000).toFixed(2)} × 100 = ${result.toFixed(2)}%`;
                }
                
                if (key === 'tonne_kilometres_performed_total') {
                    const passengers = summary.tonne_kilometres_performed_passengers || 0;
                    const freight = summary.tonne_kilometres_performed_freight || 0;
                    const mail = summary.tonne_kilometres_performed_mail || 0;
                    const total = passengers + freight + mail;
                    return `${(passengers / 1000).toFixed(2)} + ${(freight / 1000).toFixed(2)} + ${(mail / 1000).toFixed(2)} = ${(total / 1000).toFixed(2)} ×1000`;
                }
                
                // For other metrics, show sum of values
                const sum = values.reduce((a, b) => a + (b || 0), 0);
                const formattedSum = info.formatValue(sum);
                
                // Special formatting for passenger_kilometres_performed
                if (key === 'passenger_kilometres_performed' && flightDataToUse && flightDataToUse.length > 0) {
                    const firstFew = flightDataToUse.slice(0, Math.min(5, flightDataToUse.length));
                    const parts = firstFew.map(row => {
                        const pax = row.total_pax || 0;
                        const dist = row.distance_km || 0;
                        return `(${pax} × ${dist.toFixed(2)})`;
                    });
                    const remaining = flightDataToUse.length > 5 ? ` + ... (${flightDataToUse.length - 5} ${currentLanguage === 'en' ? 'more flights' : 'پرواز دیگر'})` : '';
                    return `${parts.join(' + ')}${remaining} = ${formattedSum}${info.unit ? ' ' + info.unit : ''}`;
                }
                
                // Special formatting for seat_kilometres_available
                if (key === 'seat_kilometres_available' && flightDataToUse && flightDataToUse.length > 0) {
                    const firstFew = flightDataToUse.slice(0, Math.min(5, flightDataToUse.length));
                    const parts = firstFew.map(row => {
                        const seats = row.seats_offered || 0;
                        const dist = row.distance_km || 0;
                        return `(${seats} × ${dist.toFixed(2)})`;
                    });
                    const remaining = flightDataToUse.length > 5 ? ` + ... (${flightDataToUse.length - 5} ${currentLanguage === 'en' ? 'more flights' : 'پرواز دیگر'})` : '';
                    return `${parts.join(' + ')}${remaining} = ${formattedSum}${info.unit ? ' ' + info.unit : ''}`;
                }
                
                // Special formatting for tonne_kilometres_performed_passengers
                if (key === 'tonne_kilometres_performed_passengers' && flightDataToUse && flightDataToUse.length > 0) {
                    const firstFew = flightDataToUse.slice(0, Math.min(5, flightDataToUse.length));
                    const parts = firstFew.map(row => {
                        const pax = row.total_pax || 0;
                        const dist = row.distance_km || 0;
                        const weightKg = (pax * 100); // 80kg passenger + 20kg baggage
                        const weightTon = weightKg / 1000;
                        return `(${weightTon.toFixed(2)} × ${dist.toFixed(2)})`;
                    });
                    const remaining = flightDataToUse.length > 5 ? ` + ... (${flightDataToUse.length - 5} ${currentLanguage === 'en' ? 'more flights' : 'پرواز دیگر'})` : '';
                    return `${parts.join(' + ')}${remaining} = ${formattedSum}${info.unit ? ' ' + info.unit : ''}`;
                }
                
                // Special formatting for tonne_kilometres_performed_freight
                if (key === 'tonne_kilometres_performed_freight' && flightDataToUse && flightDataToUse.length > 0) {
                    const firstFew = flightDataToUse.slice(0, Math.min(5, flightDataToUse.length));
                    const parts = firstFew.map(row => {
                        const freightKg = row.cargo_weight || 0;
                        const freightTon = freightKg / 1000;
                        const dist = row.distance_km || 0;
                        return `(${freightTon.toFixed(2)} × ${dist.toFixed(2)})`;
                    });
                    const remaining = flightDataToUse.length > 5 ? ` + ... (${flightDataToUse.length - 5} ${currentLanguage === 'en' ? 'more flights' : 'پرواز دیگر'})` : '';
                    return `${parts.join(' + ')}${remaining} = ${formattedSum}${info.unit ? ' ' + info.unit : ''}`;
                }
                
                // Show first few values if not too many
                if (values.length <= 10) {
                    const formattedValues = values.map(v => {
                        const val = v || 0;
                        if (key === 'aircraft_kilometres' || key === 'aircraft_hours' || key === 'passengers_carried') {
                            return val.toFixed(2);
                        }
                        return info.formatValue(val);
                    }).join(' + ');
                    return `${formattedValues} = ${formattedSum}${info.unit ? ' ' + info.unit : ''}`;
                } else {
                    // Show first 5, then "...", then last 2
                    const first5 = values.slice(0, 5).map(v => {
                        const val = v || 0;
                        if (key === 'aircraft_kilometres' || key === 'aircraft_hours' || key === 'passengers_carried') {
                            return val.toFixed(2);
                        }
                        return info.formatValue(val);
                    }).join(' + ');
                    const last2 = values.slice(-2).map(v => {
                        const val = v || 0;
                        if (key === 'aircraft_kilometres' || key === 'aircraft_hours' || key === 'passengers_carried') {
                            return val.toFixed(2);
                        }
                        return info.formatValue(val);
                    }).join(' + ');
                    return `${first5} + ... + ${last2} = ${formattedSum}${info.unit ? ' ' + info.unit : ''} (${values.length} ${currentLanguage === 'en' ? 'flights' : 'پرواز'})`;
                }
            }
            
            // Create cards for each calculation method
            Object.keys(methods).forEach(key => {
                const method = methods[key];
                const methodText = method[currentLanguage] || method['en'] || '';
                const info = metricInfo[key];
                const metricName = info ? info.name : key;
                const detailValues = details[key] || [];
                const calculationFormula = formatCalculation(key, detailValues, summary, flightData);
                
                const cardDiv = document.createElement('div');
                cardDiv.className = 'bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600 mb-4';
                cardDiv.innerHTML = `
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-calculator text-blue-600 dark:text-blue-400 mt-1"></i>
                        </div>
                        <div class="ml-3 flex-1">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">
                                ${metricName}
                            </h4>
                            <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed mb-2">
                                ${methodText}
                            </p>
                            ${calculationFormula ? `
                                <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
                                    <div class="text-xs font-semibold text-blue-800 dark:text-blue-300 mb-1">
                                        ${currentLanguage === 'en' ? 'Actual Calculation:' : 'محاسبه واقعی:'}
                                    </div>
                                    <div class="text-sm font-mono text-blue-900 dark:text-blue-200">
                                        ${calculationFormula}
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                contentDiv.appendChild(cardDiv);
            });
            
            document.getElementById('calculationMethodsBox').classList.remove('hidden');
        }

        function showError(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('error').classList.remove('hidden');
        }

        function downloadExcel() {
            if (!window.lastSummary) {
                alert(currentLanguage === 'en' ? 'No data to download. Please load flight data first.' : 'هیچ داده ای برای دانلود موجود نیست. لطفاً ابتدا داده های پرواز را بارگذاری کنید.');
                return;
            }
            
            const summary = window.lastSummary;
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            
            // Helper function to format values
            function formatValue(key, value) {
                if (key === 'aircraft_hours') {
                    const hours = Math.floor(value);
                    const minutes = Math.round((value - hours) * 60);
                    return `${hours}h ${minutes}m`;
                } else if (key === 'aircraft_kilometres' || key === 'passenger_kilometres_performed' || key === 'seat_kilometres_available' || 
                           key.startsWith('tonne_kilometres')) {
                    return (value / 1000).toFixed(2);
                } else if (key.includes('load_factor')) {
                    return value.toFixed(2);
                } else if (key.includes('tonnes')) {
                    return value.toFixed(2);
                } else {
                    return Math.round(value).toLocaleString();
                }
            }
            
            // Create CSV content with statistics (Persian)
            const headers = [
                'معیار',
                'مقدار داخلی',
                'مقدار بین‌المللی',
                'واحد'
            ];
            
            const metrics = [
                { key: 'aircraft_kilometres', name: 'کیلومتر هواپیما', unit: '×1000 km' },
                { key: 'aircraft_departures', name: 'تعداد برخاست', unit: '' },
                { key: 'aircraft_hours', name: 'ساعات هواپیما', unit: '' },
                { key: 'passengers_carried', name: 'مسافران حمل‌شده', unit: '' },
                { key: 'freight_tonnes_carried', name: 'تناژ بار حمل‌شده', unit: 'تن' },
                { key: 'mail_tonnes_carried', name: 'تناژ پست حمل‌شده', unit: 'تن' },
                { key: 'passenger_kilometres_performed', name: 'مسافر-کیلومتر انجام‌شده', unit: '×1000' },
                { key: 'seat_kilometres_available', name: 'صندلی-کیلومتر در دسترس', unit: '×1000' },
                { key: 'passenger_load_factor', name: 'ضریب ظرفیت مسافر', unit: '%' },
                { key: 'tonne_kilometres_performed_passengers', name: 'تن-کیلومتر انجام‌شده (مسافران شامل بار)', unit: '×1000' },
                { key: 'tonne_kilometres_performed_freight', name: 'تن-کیلومتر انجام‌شده (بار)', unit: '×1000' },
                { key: 'tonne_kilometres_performed_mail', name: 'تن-کیلومتر انجام‌شده (پست)', unit: '×1000' },
                { key: 'tonne_kilometres_performed_total', name: 'تن-کیلومتر انجام‌شده (کل)', unit: '×1000' },
                { key: 'tonne_kilometres_available', name: 'تن-کیلومتر در دسترس', unit: '×1000' },
                { key: 'weight_load_factor', name: 'ضریب ظرفیت وزنی', unit: '%' }
            ];
            
            const rows = metrics.map(metric => {
                const domesticValue = formatValue(metric.key, summary[metric.key + '_domestic'] || 0);
                const internationalValue = formatValue(metric.key, summary[metric.key + '_international'] || 0);
                return [
                    `"${metric.name}"`,
                    domesticValue,
                    internationalValue,
                    `"${metric.unit}"`
                ].join(',');
            });
            
            const csvContent = [
                headers.join(','),
                ...rows
            ].join('\n');
            
            // Create and download file
            const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' }); // UTF-8 BOM for Excel
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `flight_operations_report_${fromDate}_to_${toDate}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function downloadWord() {
            if (!window.lastSummary) {
                alert(currentLanguage === 'en' ? 'No data to download. Please load flight data first.' : 'هیچ داده ای برای دانلود موجود نیست. لطفاً ابتدا داده های پرواز را بارگذاری کنید.');
                return;
            }
            
            const summary = window.lastSummary;
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            
            // Helper function to format values
            function formatValue(key, value) {
                if (key === 'aircraft_hours') {
                    const hours = Math.floor(value);
                    const minutes = Math.round((value - hours) * 60);
                    return `${hours}h ${minutes}m`;
                } else if (key === 'aircraft_kilometres' || key === 'passenger_kilometres_performed' || key === 'seat_kilometres_available' || 
                           key.startsWith('tonne_kilometres')) {
                    return (value / 1000).toFixed(2);
                } else if (key.includes('load_factor')) {
                    return value.toFixed(2);
                } else if (key.includes('tonnes')) {
                    return value.toFixed(2);
                } else {
                    return Math.round(value).toLocaleString();
                }
            }
            
            const metrics = [
                { key: 'aircraft_kilometres', name: 'کیلومتر هواپیما', unit: '×1000 km' },
                { key: 'aircraft_departures', name: 'تعداد برخاست', unit: '' },
                { key: 'aircraft_hours', name: 'ساعات هواپیما', unit: '' },
                { key: 'passengers_carried', name: 'مسافران حمل‌شده', unit: '' },
                { key: 'freight_tonnes_carried', name: 'تناژ بار حمل‌شده', unit: 'تن' },
                { key: 'mail_tonnes_carried', name: 'تناژ پست حمل‌شده', unit: 'تن' },
                { key: 'passenger_kilometres_performed', name: 'مسافر-کیلومتر انجام‌شده', unit: '×1000' },
                { key: 'seat_kilometres_available', name: 'صندلی-کیلومتر در دسترس', unit: '×1000' },
                { key: 'passenger_load_factor', name: 'ضریب ظرفیت مسافر', unit: '%' },
                { key: 'tonne_kilometres_performed_passengers', name: 'تن-کیلومتر انجام‌شده (مسافران شامل بار)', unit: '×1000' },
                { key: 'tonne_kilometres_performed_freight', name: 'تن-کیلومتر انجام‌شده (بار)', unit: '×1000' },
                { key: 'tonne_kilometres_performed_mail', name: 'تن-کیلومتر انجام‌شده (پست)', unit: '×1000' },
                { key: 'tonne_kilometres_performed_total', name: 'تن-کیلومتر انجام‌شده (کل)', unit: '×1000' },
                { key: 'tonne_kilometres_available', name: 'تن-کیلومتر در دسترس', unit: '×1000' },
                { key: 'weight_load_factor', name: 'ضریب ظرفیت وزنی', unit: '%' }
            ];
            
            const htmlContent = `
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>گزارش عملیات پرواز</title>
    <style>
        body { font-family: 'Tahoma', 'Arial', sans-serif; margin: 20px; direction: rtl; }
        .header { text-align: center; margin-bottom: 30px; }
        .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .subtitle { font-size: 18px; margin-bottom: 5px; }
        .org { font-size: 16px; margin-bottom: 5px; }
        .center { font-size: 14px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; direction: rtl; }
        th, td { border: 1px solid #000; padding: 8px; text-align: right; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .summary { margin-top: 30px; }
        .summary h3 { font-size: 18px; margin-bottom: 15px; text-align: right; }
        .summary table { width: 100%; }
        .footer { margin-top: 40px; border-top: 1px solid #000; padding-top: 20px; }
        .contact-info { display: flex; justify-content: space-between; direction: rtl; }
        .contact-left, .contact-right { width: 45%; }
        .contact-row { margin-bottom: 10px; }
        .signature-line { border-bottom: 1px solid #000; width: 200px; display: inline-block; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">سازمان هواپیمایی کشوری ایران</div>
        <div class="subtitle">CAOIRI - Civil Aviation Organization of IRAN</div>
        <div class="center">مرکز آمار و محاسبات</div>
        <div class="title">گزارش عملیات پرواز</div>
        <div>دوره: ${fromDate} تا ${toDate}</div>
    </div>
    
    <div class="summary">
        <h3>خلاصه آمار پروازها</h3>
        <table>
            <thead>
                <tr>
                    <th>معیار</th>
                    <th>مقدار داخلی</th>
                    <th>مقدار بین‌المللی</th>
                    <th>واحد</th>
                </tr>
            </thead>
            <tbody>
                ${metrics.map(metric => {
                    const domesticValue = formatValue(metric.key, summary[metric.key + '_domestic'] || 0);
                    const internationalValue = formatValue(metric.key, summary[metric.key + '_international'] || 0);
                    return `
                        <tr>
                            <td>${metric.name}</td>
                            <td>${domesticValue}</td>
                            <td>${internationalValue}</td>
                            <td>${metric.unit}</td>
                        </tr>
                    `;
                }).join('')}
            </tbody>
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
            link.setAttribute('download', `flight_operations_report_${fromDate}_to_${toDate}.doc`);
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
