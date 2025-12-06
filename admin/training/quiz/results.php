<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/training/quiz/results.php');

$current_user = getCurrentUser();
$db = getDBConnection();

// Get filters
$filterQuizSet = intval($_GET['quiz_set'] ?? 0);
$filterUser = intval($_GET['user'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

// Get all quiz sets for filter
$stmt = $db->query("SELECT id, name FROM quiz_sets ORDER BY name");
$allQuizSets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for filter
$stmt = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE status = 'active' ORDER BY first_name, last_name");
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build query for results
$query = "SELECT qa.*, 
          qs.name as quiz_set_name,
          CONCAT(u.first_name, ' ', u.last_name) as user_name,
          u.position as user_position,
          qa2.assigned_at,
          qa2.due_date
          FROM quiz_attempts qa
          LEFT JOIN quiz_assignments qa2 ON qa.quiz_assignment_id = qa2.id
          LEFT JOIN quiz_sets qs ON qa2.quiz_set_id = qs.id
          LEFT JOIN users u ON qa.user_id = u.id
          WHERE 1=1";
          
$params = [];

if ($filterQuizSet) {
    $query .= " AND qa2.quiz_set_id = ?";
    $params[] = $filterQuizSet;
}

if ($filterUser) {
    $query .= " AND qa.user_id = ?";
    $params[] = $filterUser;
}

if ($filterStatus) {
    $query .= " AND qa.status = ?";
    $params[] = $filterStatus;
}

if ($filterDateFrom) {
    $query .= " AND DATE(qa.started_at) >= ?";
    $params[] = $filterDateFrom;
}

if ($filterDateTo) {
    $query .= " AND DATE(qa.started_at) <= ?";
    $params[] = $filterDateTo;
}

$query .= " ORDER BY qa.started_at DESC LIMIT 100";

$stmt = $db->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalAttempts = count($results);
$completedAttempts = count(array_filter($results, fn($r) => $r['status'] === 'completed'));
$timeoutAttempts = count(array_filter($results, fn($r) => $r['status'] === 'timeout'));
$inProgressAttempts = count(array_filter($results, fn($r) => $r['status'] === 'in_progress'));

$avgScore = 0;
if ($completedAttempts > 0) {
    $scores = array_filter(array_column($results, 'score'), fn($s) => $s !== null);
    $avgScore = count($scores) > 0 ? array_sum($scores) / count($scores) : 0;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Quiz Results</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View all quiz attempt results</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-clipboard-list text-blue-600 dark:text-blue-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Attempts</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $totalAttempts; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Completed</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $completedAttempts; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-clock text-orange-600 dark:text-orange-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Timeout</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $timeoutAttempts; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-chart-line text-purple-600 dark:text-purple-400 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Score</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($avgScore, 1); ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Filters</h2>
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <label for="quiz_set" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Quiz Set
                            </label>
                            <select id="quiz_set" name="quiz_set"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Quiz Sets</option>
                                <?php foreach ($allQuizSets as $quizSet): ?>
                                    <option value="<?php echo $quizSet['id']; ?>" <?php echo $filterQuizSet == $quizSet['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($quizSet['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="user" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                User
                            </label>
                            <select id="user" name="user"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Users</option>
                                <?php foreach ($allUsers as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $filterUser == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Status
                            </label>
                            <select id="status" name="status"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Status</option>
                                <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="timeout" <?php echo $filterStatus === 'timeout' ? 'selected' : ''; ?>>Timeout</option>
                                <option value="in_progress" <?php echo $filterStatus === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Date From
                            </label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Date To
                            </label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div class="md:col-span-5 flex justify-end space-x-2">
                            <button type="submit"
                                    class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <i class="fas fa-filter mr-2"></i>Apply Filters
                            </button>
                            <a href="results.php"
                               class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Results Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Results (<?php echo $totalAttempts; ?>)
                        </h2>
                    </div>
                    
                    <?php if (empty($results)): ?>
                        <div class="p-12 text-center">
                            <i class="fas fa-clipboard-list text-gray-400 text-6xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Results Found</h3>
                            <p class="text-gray-500 dark:text-gray-400">Try adjusting your filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quiz Set</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Score</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Correct/Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time Spent</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Started</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php foreach ($results as $result): ?>
                                        <?php
                                        // Calculate time spent
                                        $timeSpent = intval($result['time_spent'] ?? 0);
                                        $hours = floor($timeSpent / 3600);
                                        $minutes = floor(($timeSpent % 3600) / 60);
                                        $seconds = $timeSpent % 60;
                                        $timeSpentFormatted = '';
                                        if ($hours > 0) $timeSpentFormatted .= $hours . 'h ';
                                        if ($minutes > 0) $timeSpentFormatted .= $minutes . 'm ';
                                        $timeSpentFormatted .= $seconds . 's';
                                        
                                        $score = floatval($result['score'] ?? 0);
                                        $isPassed = $score >= 70; // Assuming 70% is passing
                                        ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($result['user_name']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($result['user_position'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($result['quiz_set_name']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-bold <?php echo $isPassed ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">
                                                    <?php echo number_format($score, 2); ?>%
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo intval($result['correct_answers'] ?? 0); ?>/<?php echo intval($result['total_questions'] ?? 0); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo $timeSpentFormatted; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?php 
                                                    echo match($result['status']) {
                                                        'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                        'timeout' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                                        'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                                    };
                                                    ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $result['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <?php echo date('M j, Y g:i A', strtotime($result['started_at'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="/admin/profile/quiz_result.php?assignment_id=<?php echo $result['quiz_assignment_id']; ?>&attempt_id=<?php echo $result['id']; ?>"
                                                   class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                   target="_blank">
                                                    <i class="fas fa-eye mr-1"></i>View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

