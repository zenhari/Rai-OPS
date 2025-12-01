<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/pricing/ifso_costs/add.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $monthlyPrepayment = isset($_POST['monthly_prepayment']) && $_POST['monthly_prepayment'] !== '' ? floatval($_POST['monthly_prepayment']) : 500000000;
    $salaries = isset($_POST['salaries']) && $_POST['salaries'] !== '' ? floatval($_POST['salaries']) : null;
    $salariesCount = isset($_POST['salaries_count']) && $_POST['salaries_count'] !== '' ? intval($_POST['salaries_count']) : null;
    $training = isset($_POST['training']) && $_POST['training'] !== '' ? floatval($_POST['training']) : null;
    $trainingCount = isset($_POST['training_count']) && $_POST['training_count'] !== '' ? intval($_POST['training_count']) : null;
    $transport = isset($_POST['transport']) && $_POST['transport'] !== '' ? floatval($_POST['transport']) : null;
    $transportCount = isset($_POST['transport_count']) && $_POST['transport_count'] !== '' ? intval($_POST['transport_count']) : null;
    $ifsoPremium = isset($_POST['ifso_premium']) && $_POST['ifso_premium'] !== '' ? floatval($_POST['ifso_premium']) : null;
    $ifsoPremiumCount = isset($_POST['ifso_premium_count']) && $_POST['ifso_premium_count'] !== '' ? intval($_POST['ifso_premium_count']) : null;
    $monthlyAccommodation = isset($_POST['monthly_accommodation']) && $_POST['monthly_accommodation'] !== '' ? floatval($_POST['monthly_accommodation']) : null;
    $monthlyAccommodationCount = isset($_POST['monthly_accommodation_count']) && $_POST['monthly_accommodation_count'] !== '' ? intval($_POST['monthly_accommodation_count']) : null;
    
    if (addIFSOCost($monthlyPrepayment, $salaries, $salariesCount, $training, $trainingCount, $transport, $transportCount, $ifsoPremium, $ifsoPremiumCount, $monthlyAccommodation, $monthlyAccommodationCount)) {
        $message = 'IFSO Cost added successfully.';
        // Redirect after 1 second
        header('Refresh: 1; url=index.php');
    } else {
        $error = 'Failed to add IFSO Cost.';
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add IFSO Cost - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Add New IFSO Cost</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Create a new IFSO cost record</p>
                        </div>
                        <a href="index.php" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to List
                        </a>
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

                <!-- Form -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <form method="POST" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Monthly Prepayment -->
                            <div class="md:col-span-2">
                                <label for="monthly_prepayment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Monthly Prepayment (500,000,000)
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Monthly prepayment amount</p>
                                <input type="number" id="monthly_prepayment" step="0.01" min="0"
                                       value="500000000"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <input type="hidden" name="monthly_prepayment" value="500000000">
                            </div>

                            <!-- Salaries -->
                            <div>
                                <label for="salaries" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    IFSO Salaries
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Total salaries amount</p>
                                <input type="number" id="salaries" name="salaries" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Salaries Count -->
                            <div>
                                <label for="salaries_count" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Salaries Count
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Number of IFSO employees receiving salaries</p>
                                <input type="number" id="salaries_count" name="salaries_count" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Training -->
                            <div>
                                <label for="training" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    IFSO Training
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">IFSO training total cost</p>
                                <input type="number" id="training" name="training" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Training Count -->
                            <div>
                                <label for="training_count" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Training Count
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Number of employees receiving training</p>
                                <input type="number" id="training_count" name="training_count" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Transport -->
                            <div>
                                <label for="transport" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Transport
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">IFSO transport total cost</p>
                                <input type="number" id="transport" name="transport" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Transport Count -->
                            <div>
                                <label for="transport_count" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Transport Count
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Number of employees receiving transport</p>
                                <input type="number" id="transport_count" name="transport_count" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- IFSO Premium -->
                            <div>
                                <label for="ifso_premium" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    IFSO Premium
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">IFSO premium/benefits cost</p>
                                <input type="number" id="ifso_premium" name="ifso_premium" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- IFSO Premium Count -->
                            <div>
                                <label for="ifso_premium_count" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Premium Count
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Number of employees receiving premium</p>
                                <input type="number" id="ifso_premium_count" name="ifso_premium_count" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Monthly Accommodation -->
                            <div>
                                <label for="monthly_accommodation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Monthly Accommodation
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Monthly accommodation total cost</p>
                                <input type="number" id="monthly_accommodation" name="monthly_accommodation" step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Monthly Accommodation Count -->
                            <div>
                                <label for="monthly_accommodation_count" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Accommodation Count
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Number of employees receiving accommodation</p>
                                <input type="number" id="monthly_accommodation_count" name="monthly_accommodation_count" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <a href="index.php"
                               class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Add IFSO Cost
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

