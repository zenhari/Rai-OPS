<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/pricing/routes/edit.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Get route ID
$routeId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$routeId) {
    header('Location: index.php');
    exit();
}

// Get route information
$route = getRouteById($routeId);
if (!$route) {
    header('Location: index.php');
    exit();
}

// Get existing price data if available
$db = getDBConnection();
$stmt = $db->prepare("SELECT * FROM route_prices WHERE route_id = ?");
$stmt->execute([$routeId]);
$priceData = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate average uplift_fuel for this route from flights table
$averageUpliftFuel = null;
$averageUpliftFuelCost = null;
if (!empty($route['route_code'])) {
    $stmt = $db->prepare("SELECT AVG(uplift_fuel) as avg_uplift_fuel 
                          FROM flights 
                          WHERE Route = ? AND uplift_fuel IS NOT NULL AND uplift_fuel > 0");
    $stmt->execute([$route['route_code']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['avg_uplift_fuel'] !== null) {
        $averageUpliftFuel = floatval($result['avg_uplift_fuel']);
        // Convert kg to liters (fuel density ~0.8 kg/L) and multiply by price per liter (14300 Toman)
        // Formula: (kg / 0.8) * 14300 = kg * 1.25 * 14300
        $averageUpliftFuelCost = $averageUpliftFuel / 0.8 * 14300;
    }
}

// If fueling_services_cost is not set and we have average uplift fuel cost, use it
if (empty($priceData['fueling_services_cost']) && $averageUpliftFuelCost !== null) {
    $priceData['fueling_services_cost'] = $averageUpliftFuelCost;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // All cost fields
    $handlingCost = isset($_POST['handling_cost']) ? floatval($_POST['handling_cost']) : null;
    $fuelingServicesCost = isset($_POST['fueling_services_cost']) ? floatval($_POST['fueling_services_cost']) : null;
    $deicingAntiicingCost = isset($_POST['deicing_antiicing_cost']) ? floatval($_POST['deicing_antiicing_cost']) : null;
    $cateringCost = isset($_POST['catering_cost']) ? floatval($_POST['catering_cost']) : null;
    $governmentalRegulatoryCosts = isset($_POST['governmental_regulatory_costs']) ? floatval($_POST['governmental_regulatory_costs']) : null;
    $taxAccountingCosts = isset($_POST['tax_accounting_costs']) ? floatval($_POST['tax_accounting_costs']) : null;
    $commercialSalesServicesCost = isset($_POST['commercial_sales_services_cost']) ? floatval($_POST['commercial_sales_services_cost']) : null;
    $documentationRecordsCost = isset($_POST['documentation_records_cost']) ? floatval($_POST['documentation_records_cost']) : null;
    $itServicesCost = isset($_POST['it_services_cost']) ? floatval($_POST['it_services_cost']) : null;
    $personnelHrCost = isset($_POST['personnel_hr_cost']) ? floatval($_POST['personnel_hr_cost']) : null;
    $miscellaneousIndirectCosts = isset($_POST['miscellaneous_indirect_costs']) ? floatval($_POST['miscellaneous_indirect_costs']) : null;
    $delayCost = isset($_POST['delay_cost']) ? floatval($_POST['delay_cost']) : null;
    $flightCancellationCost = isset($_POST['flight_cancellation_cost']) ? floatval($_POST['flight_cancellation_cost']) : null;
    $regulatoryPenalties = isset($_POST['regulatory_penalties']) ? floatval($_POST['regulatory_penalties']) : null;
    $passengerCompensationCost = isset($_POST['passenger_compensation_cost']) ? floatval($_POST['passenger_compensation_cost']) : null;
    $hotelCateringPassengersCost = isset($_POST['hotel_catering_passengers_cost']) ? floatval($_POST['hotel_catering_passengers_cost']) : null;
    $crewHotelAccommodationCost = isset($_POST['crew_hotel_accommodation_cost']) ? floatval($_POST['crew_hotel_accommodation_cost']) : null;
    $extendedParkingCost = isset($_POST['extended_parking_cost']) ? floatval($_POST['extended_parking_cost']) : null;
    $aircraftPositioningCost = isset($_POST['aircraft_positioning_cost']) ? floatval($_POST['aircraft_positioning_cost']) : null;
    $crewPositioningCost = isset($_POST['crew_positioning_cost']) ? floatval($_POST['crew_positioning_cost']) : null;
    $overflightCharges = isset($_POST['overflight_charges']) ? floatval($_POST['overflight_charges']) : null;
    $buildingsFacilitiesCost = isset($_POST['buildings_facilities_cost']) ? floatval($_POST['buildings_facilities_cost']) : null;
    $aircraftNightstopCost = isset($_POST['aircraft_nightstop_cost']) ? floatval($_POST['aircraft_nightstop_cost']) : null;
    
    $profitMarginPercent = isset($_POST['profit_margin_percent']) ? floatval($_POST['profit_margin_percent']) : null;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
    
    // Note: total_cost is now a generated column, so we don't need to calculate it manually
    // But we still need to calculate final_price
    $finalPrice = null;
    
    // Save or update price data
    if ($priceData) {
        // Update existing price
        $stmt = $db->prepare("
            UPDATE route_prices 
            SET handling_cost = ?, fueling_services_cost = ?, deicing_antiicing_cost = ?,
                catering_cost = ?, airport_fees = ?, governmental_regulatory_costs = ?,
                tax_accounting_costs = ?, commercial_sales_services_cost = ?,
                documentation_records_cost = ?, it_services_cost = ?, personnel_hr_cost = ?,
                miscellaneous_indirect_costs = ?, delay_cost = ?, flight_cancellation_cost = ?,
                regulatory_penalties = ?, passenger_compensation_cost = ?,
                hotel_catering_passengers_cost = ?, crew_hotel_accommodation_cost = ?,
                extended_parking_cost = ?, aircraft_positioning_cost = ?,
                crew_positioning_cost = ?, overflight_charges = ?,
                buildings_facilities_cost = ?, aircraft_nightstop_cost = ?,
                profit_margin_percent = ?, final_price = ?, notes = ?
            WHERE route_id = ?
        ");
        // Calculate final price after update (we'll get total_cost from DB)
        $result = $stmt->execute([
            $handlingCost, $fuelingServicesCost, $deicingAntiicingCost,
            $cateringCost, $airportFees, $governmentalRegulatoryCosts,
            $taxAccountingCosts, $commercialSalesServicesCost, $documentationRecordsCost,
            $itServicesCost, $personnelHrCost, $miscellaneousIndirectCosts,
            $delayCost, $flightCancellationCost, $regulatoryPenalties,
            $passengerCompensationCost, $hotelCateringPassengersCost,
            $crewHotelAccommodationCost, $extendedParkingCost,
            $aircraftPositioningCost, $crewPositioningCost, $overflightCharges,
            $buildingsFacilitiesCost, $aircraftNightstopCost,
            $profitMarginPercent, $finalPrice, $notes,
            $routeId
        ]);
    } else {
        // Insert new price
        $stmt = $db->prepare("
            INSERT INTO route_prices 
            (route_id, handling_cost, fueling_services_cost, deicing_antiicing_cost,
             catering_cost, airport_fees, governmental_regulatory_costs,
             tax_accounting_costs, commercial_sales_services_cost,
             documentation_records_cost, it_services_cost, personnel_hr_cost,
             miscellaneous_indirect_costs, delay_cost, flight_cancellation_cost,
             regulatory_penalties, passenger_compensation_cost,
             hotel_catering_passengers_cost, crew_hotel_accommodation_cost,
             extended_parking_cost, aircraft_positioning_cost,
             crew_positioning_cost, overflight_charges,
             buildings_facilities_cost, aircraft_nightstop_cost,
             profit_margin_percent, final_price, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $routeId, $handlingCost, $fuelingServicesCost, $deicingAntiicingCost,
            $cateringCost, $airportFees, $governmentalRegulatoryCosts,
            $taxAccountingCosts, $commercialSalesServicesCost, $documentationRecordsCost,
            $itServicesCost, $personnelHrCost, $miscellaneousIndirectCosts,
            $delayCost, $flightCancellationCost, $regulatoryPenalties,
            $passengerCompensationCost, $hotelCateringPassengersCost,
            $crewHotelAccommodationCost, $extendedParkingCost,
            $aircraftPositioningCost, $crewPositioningCost, $overflightCharges,
            $buildingsFacilitiesCost, $aircraftNightstopCost,
            $profitMarginPercent, $finalPrice, $notes
        ]);
    }
    
    if ($result) {
        // Reload price data to get calculated total_cost
        $stmt = $db->prepare("SELECT * FROM route_prices WHERE route_id = ?");
        $stmt->execute([$routeId]);
        $priceData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate final price with profit margin
        if ($priceData && $priceData['total_cost'] > 0 && $profitMarginPercent !== null && $profitMarginPercent >= 0) {
            $finalPrice = $priceData['total_cost'] * (1 + ($profitMarginPercent / 100));
            $updateFinalPriceStmt = $db->prepare("UPDATE route_prices SET final_price = ? WHERE route_id = ?");
            $updateFinalPriceStmt->execute([$finalPrice, $routeId]);
            $priceData['final_price'] = $finalPrice;
        }
        
        $message = 'Route price saved successfully.';
    } else {
        $error = 'Failed to save route price.';
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Route Price - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Route Price</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Route: <span class="font-medium"><?php echo htmlspecialchars($route['route_code']); ?></span> - 
                                <?php echo htmlspecialchars($route['route_name']); ?>
                            </p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Routes
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../../includes/permission_banner.php'; ?>
                
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

                <!-- Route Information -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Route Information</h2>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Route Code</p>
                                <p class="text-base font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($route['route_code']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Origin → Destination</p>
                                <p class="text-base font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($route['origin_name'] ?? 'N/A'); ?> → 
                                    <?php echo htmlspecialchars($route['destination_name'] ?? 'N/A'); ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Distance / Flight Time</p>
                                <p class="text-base font-medium text-gray-900 dark:text-white">
                                    <?php echo $route['distance_nm'] ? number_format($route['distance_nm'], 2) . ' NM' : 'N/A'; ?> / 
                                    <?php echo $route['flight_time_minutes'] ? $route['flight_time_minutes'] . ' min' : 'N/A'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Price Form -->
                <form method="POST" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Cost Breakdown (All amounts in Toman)</h2>
                    </div>
                    
                    <div class="px-6 py-6">
                        <!-- Section: Ground Services -->
                        <div class="mb-8">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-cogs mr-2"></i>Ground Services
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Handling -->
                                <div>
                                    <label for="handling_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Handling (Toman)
                                    </label>
                                    <input type="number" id="handling_cost" name="handling_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['handling_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Fueling Services -->
                                <div>
                                    <label for="fueling_services_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Fueling Services (Toman)
                                        <?php if ($averageUpliftFuel !== null): ?>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                                                (Avg: <?php echo number_format($averageUpliftFuel, 2); ?> kg = <?php echo number_format($averageUpliftFuelCost, 2); ?> Toman)
                                            </span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="number" id="fueling_services_cost" name="fueling_services_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['fueling_services_cost'] ?? ($averageUpliftFuelCost !== null ? $averageUpliftFuelCost : ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                    <?php if ($averageUpliftFuelCost !== null && empty($priceData['fueling_services_cost'])): ?>
                                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Auto-filled from average uplift_fuel (<?php echo number_format($averageUpliftFuel, 2); ?> kg) × 1.25 × 14,300 Toman/L for route <?php echo htmlspecialchars($route['route_code']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <!-- De-icing & Anti-icing -->
                                <div>
                                    <label for="deicing_antiicing_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        De-icing & Anti-icing (Toman)
                                    </label>
                                    <input type="number" id="deicing_antiicing_cost" name="deicing_antiicing_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['deicing_antiicing_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Catering -->
                                <div>
                                    <label for="catering_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Catering (Toman)
                                    </label>
                                    <input type="number" id="catering_cost" name="catering_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['catering_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>
                            </div>
                        </div>

                        <!-- Section: Fees and Charges -->
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-file-invoice-dollar mr-2"></i>Fees and Charges
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Airport Fees / Charges -->
                                <div>
                                    <label for="airport_fees" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Airport Fees / Charges (Toman)
                                    </label>
                                    <input type="number" id="airport_fees" name="airport_fees" step="0.01" min="0"
                                           value="<?php echo $priceData['airport_fees'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Governmental and Regulatory Costs -->
                                <div>
                                    <label for="governmental_regulatory_costs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Governmental and Regulatory Costs (Toman)
                                    </label>
                                    <input type="number" id="governmental_regulatory_costs" name="governmental_regulatory_costs" step="0.01" min="0"
                                           value="<?php echo $priceData['governmental_regulatory_costs'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Tax and Accounting Costs -->
                                <div>
                                    <label for="tax_accounting_costs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Tax and Accounting Costs (Toman)
                                    </label>
                                    <input type="number" id="tax_accounting_costs" name="tax_accounting_costs" step="0.01" min="0"
                                           value="<?php echo $priceData['tax_accounting_costs'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Overflight Charges -->
                                <div>
                                    <label for="overflight_charges" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Overflight Charges (Toman)
                                    </label>
                                    <input type="number" id="overflight_charges" name="overflight_charges" step="0.01" min="0"
                                           value="<?php echo $priceData['overflight_charges'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>
                            </div>
                        </div>

                        <!-- Section: Services -->
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-concierge-bell mr-2"></i>Services
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Commercial and Sales Services -->
                                <div>
                                    <label for="commercial_sales_services_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Commercial and Sales Services (Toman)
                                    </label>
                                    <input type="number" id="commercial_sales_services_cost" name="commercial_sales_services_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['commercial_sales_services_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Documentation and Records -->
                                <div>
                                    <label for="documentation_records_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Documentation and Records (Toman)
                                    </label>
                                    <input type="number" id="documentation_records_cost" name="documentation_records_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['documentation_records_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- IT Services, Systems and Equipment -->
                                <div>
                                    <label for="it_services_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        IT Services, Systems and Equipment (Toman)
                                    </label>
                                    <input type="number" id="it_services_cost" name="it_services_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['it_services_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Personnel and Human Resources -->
                                <div>
                                    <label for="personnel_hr_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Personnel and Human Resources (Toman)
                                    </label>
                                    <input type="number" id="personnel_hr_cost" name="personnel_hr_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['personnel_hr_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Miscellaneous / Indirect Costs -->
                                <div>
                                    <label for="miscellaneous_indirect_costs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Miscellaneous / Indirect Costs (Toman)
                                    </label>
                                    <input type="number" id="miscellaneous_indirect_costs" name="miscellaneous_indirect_costs" step="0.01" min="0"
                                           value="<?php echo $priceData['miscellaneous_indirect_costs'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>
                            </div>
                        </div>

                        <!-- Section: Delay and Cancellation Costs -->
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-exclamation-triangle mr-2"></i>Delay and Cancellation Costs
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Delay and Related Costs -->
                                <div>
                                    <label for="delay_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Delay and Related Costs (Delay Cost) (Toman)
                                    </label>
                                    <input type="number" id="delay_cost" name="delay_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['delay_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Flight Cancellation Cost -->
                                <div>
                                    <label for="flight_cancellation_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Flight Cancellation Cost (Toman)
                                    </label>
                                    <input type="number" id="flight_cancellation_cost" name="flight_cancellation_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['flight_cancellation_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Regulatory Penalties -->
                                <div>
                                    <label for="regulatory_penalties" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Regulatory Penalties (Toman)
                                    </label>
                                    <input type="number" id="regulatory_penalties" name="regulatory_penalties" step="0.01" min="0"
                                           value="<?php echo $priceData['regulatory_penalties'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Passenger Compensation for Long Delays -->
                                <div>
                                    <label for="passenger_compensation_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Passenger Compensation for Long Delays (Toman)
                                    </label>
                                    <input type="number" id="passenger_compensation_cost" name="passenger_compensation_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['passenger_compensation_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>
                            </div>
                        </div>

                        <!-- Section: Accommodation and Positioning -->
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-hotel mr-2"></i>Accommodation and Positioning
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Hotel and Catering for Passengers -->
                                <div>
                                    <label for="hotel_catering_passengers_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Hotel and Catering for Passengers (Toman)
                                    </label>
                                    <input type="number" id="hotel_catering_passengers_cost" name="hotel_catering_passengers_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['hotel_catering_passengers_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Crew Hotel Accommodation -->
                                <div>
                                    <label for="crew_hotel_accommodation_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Crew Hotel Accommodation (Toman)
                                    </label>
                                    <input type="number" id="crew_hotel_accommodation_cost" name="crew_hotel_accommodation_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['crew_hotel_accommodation_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Additional Cost for Extended Parking -->
                                <div>
                                    <label for="extended_parking_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Additional Cost for Extended Parking (Toman)
                                    </label>
                                    <input type="number" id="extended_parking_cost" name="extended_parking_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['extended_parking_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Aircraft Positioning / Ferry Cost -->
                                <div>
                                    <label for="aircraft_positioning_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Aircraft Positioning / Ferry Cost (Toman)
                                    </label>
                                    <input type="number" id="aircraft_positioning_cost" name="aircraft_positioning_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['aircraft_positioning_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Crew Positioning Cost -->
                                <div>
                                    <label for="crew_positioning_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Crew Positioning Cost (Toman)
                                    </label>
                                    <input type="number" id="crew_positioning_cost" name="crew_positioning_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['crew_positioning_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>
                            </div>
                        </div>

                        <!-- Section: Facilities -->
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-building mr-2"></i>Facilities
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Buildings and Facilities -->
                                <div>
                                    <label for="buildings_facilities_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Buildings and Facilities (Toman)
                                    </label>
                                    <input type="number" id="buildings_facilities_cost" name="buildings_facilities_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['buildings_facilities_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>

                                <!-- Aircraft Night-Stop Cost -->
                                <div>
                                    <label for="aircraft_nightstop_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Aircraft Night-Stop Cost (Toman)
                                    </label>
                                    <input type="number" id="aircraft_nightstop_cost" name="aircraft_nightstop_cost" step="0.01" min="0"
                                           value="<?php echo $priceData['aircraft_nightstop_cost'] ?? ''; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="calculateTotal()">
                                </div>
                            </div>
                        </div>

                        <!-- Total Cost Display -->
                        <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-lg font-semibold text-gray-900 dark:text-white">Total Cost:</span>
                                <span id="total_cost_display" class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                    <?php echo $priceData['total_cost'] ? number_format($priceData['total_cost'], 2) : '0.00'; ?> Toman
                                </span>
                            </div>
                        </div>

                        <!-- Profit Margin -->
                        <div class="mt-6">
                            <label for="profit_margin_percent" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Profit Margin (%)
                            </label>
                            <input type="number" 
                                   id="profit_margin_percent" 
                                   name="profit_margin_percent" 
                                   step="0.01" 
                                   min="0"
                                   value="<?php echo $priceData['profit_margin_percent'] ?? ''; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   oninput="calculateFinalPrice()">
                        </div>

                        <!-- Final Price Display -->
                        <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                            <div class="flex items-center justify-between">
                                <span class="text-lg font-semibold text-gray-900 dark:text-white">Final Price (with profit margin):</span>
                                <span id="final_price_display" class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    <?php echo $priceData['final_price'] ? number_format($priceData['final_price'], 2) : '0.00'; ?> Toman
                                </span>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mt-6">
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Notes
                            </label>
                            <textarea id="notes" 
                                      name="notes" 
                                      rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars($priceData['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600 flex justify-end space-x-3">
                        <a href="index.php" 
                           class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                            <i class="fas fa-save mr-2"></i>
                            Save Price
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function calculateTotal() {
            const costs = [
                parseFloat(document.getElementById('handling_cost').value) || 0,
                parseFloat(document.getElementById('fueling_services_cost').value) || 0,
                parseFloat(document.getElementById('deicing_antiicing_cost').value) || 0,
                parseFloat(document.getElementById('catering_cost').value) || 0,
                parseFloat(document.getElementById('airport_fees').value) || 0,
                parseFloat(document.getElementById('governmental_regulatory_costs').value) || 0,
                parseFloat(document.getElementById('tax_accounting_costs').value) || 0,
                parseFloat(document.getElementById('commercial_sales_services_cost').value) || 0,
                parseFloat(document.getElementById('documentation_records_cost').value) || 0,
                parseFloat(document.getElementById('it_services_cost').value) || 0,
                parseFloat(document.getElementById('personnel_hr_cost').value) || 0,
                parseFloat(document.getElementById('miscellaneous_indirect_costs').value) || 0,
                parseFloat(document.getElementById('delay_cost').value) || 0,
                parseFloat(document.getElementById('flight_cancellation_cost').value) || 0,
                parseFloat(document.getElementById('regulatory_penalties').value) || 0,
                parseFloat(document.getElementById('passenger_compensation_cost').value) || 0,
                parseFloat(document.getElementById('hotel_catering_passengers_cost').value) || 0,
                parseFloat(document.getElementById('crew_hotel_accommodation_cost').value) || 0,
                parseFloat(document.getElementById('extended_parking_cost').value) || 0,
                parseFloat(document.getElementById('aircraft_positioning_cost').value) || 0,
                parseFloat(document.getElementById('crew_positioning_cost').value) || 0,
                parseFloat(document.getElementById('overflight_charges').value) || 0,
                parseFloat(document.getElementById('buildings_facilities_cost').value) || 0,
                parseFloat(document.getElementById('aircraft_nightstop_cost').value) || 0
            ];
            
            const total = costs.reduce((sum, cost) => sum + cost, 0);
            document.getElementById('total_cost_display').textContent = total.toFixed(2) + ' Toman';
            
            calculateFinalPrice();
        }
        
        function calculateFinalPrice() {
            const totalCost = parseFloat(document.getElementById('total_cost_display').textContent.replace(' Toman', '').replace(/,/g, '')) || 0;
            const profitMargin = parseFloat(document.getElementById('profit_margin_percent').value) || 0;
            
            if (totalCost > 0 && profitMargin >= 0) {
                const finalPrice = totalCost * (1 + (profitMargin / 100));
                document.getElementById('final_price_display').textContent = finalPrice.toFixed(2) + ' Toman';
            } else {
                document.getElementById('final_price_display').textContent = '0.00 Toman';
            }
        }
        
        // Calculate on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });
    </script>
</body>
</html>

