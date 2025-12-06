<?php
require_once '../../config.php';

// Check access
checkPageAccessWithRedirect('admin/profile/my_quiz.php');

$current_user = getCurrentUser();
$db = getDBConnection();

// Get current server time
$serverTimeStmt = $db->query("SELECT NOW() as server_time");
$serverTime = $serverTimeStmt->fetch(PDO::FETCH_ASSOC)['server_time'];

// Get all quiz assignments for current user
$stmt = $db->prepare("SELECT qa.*, 
                    qs.name as quiz_set_name,
                    qs.description,
                    qs.time_limit,
                    qs.passing_score,
                    (SELECT COUNT(*) FROM quiz_set_questions WHERE quiz_set_id = qs.id) as question_count,
                    CONCAT(assigned_by_user.first_name, ' ', assigned_by_user.last_name) as assigned_by_name,
                    (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_assignment_id = qa.id AND user_id = ?) as attempt_count,
                    (SELECT MAX(score) FROM quiz_attempts WHERE quiz_assignment_id = qa.id AND user_id = ? AND status = 'completed') as best_score
                    FROM quiz_assignments qa
                    LEFT JOIN quiz_sets qs ON qa.quiz_set_id = qs.id
                    LEFT JOIN users assigned_by_user ON qa.assigned_by = assigned_by_user.id
                    WHERE qa.user_id = ?
                    ORDER BY qa.assigned_at DESC");
$stmt->execute([$current_user['id'], $current_user['id'], $current_user['id']]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if quiz is available (start_date has passed)
foreach ($assignments as &$assignment) {
    $assignment['is_available'] = true;
    if ($assignment['due_date']) {
        $startDate = strtotime($assignment['due_date']);
        $currentTime = strtotime($serverTime);
        $assignment['is_available'] = ($currentTime >= $startDate);
    }
}
unset($assignment);

// Check for in-progress attempts and completed attempts
foreach ($assignments as &$assignment) {
    // Check for in-progress attempts
    $attemptStmt = $db->prepare("SELECT id, started_at, status FROM quiz_attempts 
                                 WHERE quiz_assignment_id = ? AND user_id = ? AND status = 'in_progress'
                                 ORDER BY started_at DESC LIMIT 1");
    $attemptStmt->execute([$assignment['id'], $current_user['id']]);
    $inProgressAttempt = $attemptStmt->fetch(PDO::FETCH_ASSOC);
    $assignment['in_progress_attempt'] = $inProgressAttempt;
    
    // Check if user has completed this quiz (only one completed attempt allowed)
    $completedStmt = $db->prepare("SELECT id, completed_at, score FROM quiz_attempts 
                                    WHERE quiz_assignment_id = ? AND user_id = ? 
                                    AND (status = 'completed' OR status = 'timeout')
                                    AND completed_at IS NOT NULL
                                    ORDER BY completed_at DESC LIMIT 1");
    $completedStmt->execute([$assignment['id'], $current_user['id']]);
    $completedAttempt = $completedStmt->fetch(PDO::FETCH_ASSOC);
    $assignment['completed_attempt'] = $completedAttempt;
    $assignment['has_completed'] = !empty($completedAttempt);
}
unset($assignment);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Quiz - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Quiz</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View and take your assigned quizzes</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php if (isset($_GET['error']) && $_GET['error'] === 'quiz_not_available'): ?>
                    <div class="mb-6 bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                    The quiz is not available yet. Please wait until the start date.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($assignments)): ?>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-12 text-center">
                        <i class="fas fa-clipboard-list text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Quizzes Assigned</h3>
                        <p class="text-gray-500 dark:text-gray-400">You don't have any quizzes assigned yet.</p>
                    </div>
                <?php else: ?>
                    <!-- Filters and Search -->
                    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="search_quiz" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fas fa-search mr-1"></i> Search
                                </label>
                                <input type="text" id="search_quiz" placeholder="Search by quiz name..."
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       oninput="filterQuizzes()">
                            </div>
                            <div>
                                <label for="filter_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fas fa-filter mr-1"></i> Status
                                </label>
                                <select id="filter_status" onchange="filterQuizzes()"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">All Status</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="expired">Expired</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button onclick="clearFilters()" 
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <i class="fas fa-times mr-1"></i> Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="quiz_count" class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        Showing <span id="visible_count"><?php echo count($assignments); ?></span> of <?php echo count($assignments); ?> quizzes
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6" id="quiz_cards">
                        <?php foreach ($assignments as $assignment): ?>
                            <div class="quiz-card bg-white dark:bg-gray-800 shadow rounded-lg p-6 hover:shadow-lg transition-shadow" 
                                 data-quiz-name="<?php echo htmlspecialchars(strtolower($assignment['quiz_set_name'])); ?>"
                                 data-status="<?php echo $assignment['status']; ?>">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                                            <?php echo htmlspecialchars($assignment['quiz_set_name']); ?>
                                        </h3>
                                        <?php if ($assignment['description']): ?>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                                <?php echo htmlspecialchars($assignment['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php 
                                        echo match($assignment['status']) {
                                            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                            'expired' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                        };
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $assignment['status'])); ?>
                                    </span>
                                </div>
                                
                                <div class="space-y-2 mb-4">
                                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                        <i class="fas fa-question-circle w-5 mr-2"></i>
                                        <span><?php echo $assignment['question_count']; ?> Questions</span>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                        <i class="fas fa-clock w-5 mr-2"></i>
                                        <span><?php echo $assignment['time_limit']; ?> Minutes</span>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                        <i class="fas fa-trophy w-5 mr-2"></i>
                                        <span>Passing Score: <?php echo $assignment['passing_score']; ?>%</span>
                                    </div>
                                    <?php if ($assignment['due_date']): ?>
                                        <div class="flex items-center text-sm <?php echo $assignment['is_available'] ? 'text-green-600 dark:text-green-400' : 'text-orange-600 dark:text-orange-400'; ?>">
                                            <i class="fas fa-calendar-alt w-5 mr-2"></i>
                                            <span>
                                                <?php if ($assignment['is_available']): ?>
                                                    Started: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?>
                                                <?php else: ?>
                                                    Starts: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($assignment['best_score'] !== null): ?>
                                        <div class="flex items-center text-sm text-green-600 dark:text-green-400">
                                            <i class="fas fa-star w-5 mr-2"></i>
                                            <span>Best Score: <?php echo number_format($assignment['best_score'], 2); ?>%</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($assignment['attempt_count'] > 0): ?>
                                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                            <i class="fas fa-redo w-5 mr-2"></i>
                                            <span><?php echo $assignment['attempt_count']; ?> Attempt(s)</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <?php if (!$assignment['is_available']): ?>
                                        <button disabled
                                                class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 cursor-not-allowed">
                                            <i class="fas fa-clock mr-2"></i>
                                            Quiz Not Available Yet
                                        </button>
                                    <?php elseif ($assignment['has_completed']): ?>
                                        <?php if ($assignment['completed_attempt']): ?>
                                            <a href="quiz_result.php?assignment_id=<?php echo $assignment['id']; ?>&attempt_id=<?php echo $assignment['completed_attempt']['id']; ?>"
                                               class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-chart-bar mr-2"></i>
                                                View Results
                                            </a>
                                        <?php else: ?>
                                            <button disabled
                                                    class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 cursor-not-allowed">
                                                <i class="fas fa-check-circle mr-2"></i>
                                                Quiz Completed
                                            </button>
                                        <?php endif; ?>
                                    <?php elseif ($assignment['in_progress_attempt']): ?>
                                        <a href="take_quiz.php?assignment_id=<?php echo $assignment['id']; ?>&attempt_id=<?php echo $assignment['in_progress_attempt']['id']; ?>"
                                           class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-play mr-2"></i>
                                            Continue Quiz
                                        </a>
                                    <?php elseif ($assignment['status'] === 'completed'): ?>
                                        <a href="quiz_result.php?assignment_id=<?php echo $assignment['id']; ?>"
                                           class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-chart-bar mr-2"></i>
                                            View Results
                                        </a>
                                    <?php elseif ($assignment['status'] === 'expired'): ?>
                                        <button disabled
                                                class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 cursor-not-allowed">
                                            <i class="fas fa-ban mr-2"></i>
                                            Expired
                                        </button>
                                    <?php else: ?>
                                        <a href="take_quiz.php?assignment_id=<?php echo $assignment['id']; ?>"
                                           class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-play mr-2"></i>
                                            Start Quiz
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Assigned by: <?php echo htmlspecialchars($assignment['assigned_by_name']); ?>
                                    on <?php echo date('M j, Y', strtotime($assignment['assigned_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function filterQuizzes() {
            const searchQuery = document.getElementById('search_quiz').value.toLowerCase().trim();
            const statusFilter = document.getElementById('filter_status').value;
            const cards = document.querySelectorAll('.quiz-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const quizName = card.getAttribute('data-quiz-name');
                const status = card.getAttribute('data-status');
                
                const matchesSearch = !searchQuery || quizName.includes(searchQuery);
                const matchesStatus = !statusFilter || status === statusFilter;
                
                if (matchesSearch && matchesStatus) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            document.getElementById('visible_count').textContent = visibleCount;
            
            // Show message if no results
            if (visibleCount === 0) {
                const grid = document.getElementById('quiz_cards');
                if (!document.getElementById('no_results_message')) {
                    const message = document.createElement('div');
                    message.id = 'no_results_message';
                    message.className = 'col-span-full text-center py-12';
                    message.innerHTML = `
                        <i class="fas fa-search text-gray-400 text-4xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400">No quizzes match your filters.</p>
                    `;
                    grid.appendChild(message);
                }
            } else {
                const message = document.getElementById('no_results_message');
                if (message) {
                    message.remove();
                }
            }
        }
        
        function clearFilters() {
            document.getElementById('search_quiz').value = '';
            document.getElementById('filter_status').value = '';
            filterQuizzes();
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            filterQuizzes();
        });
    </script>
</body>
</html>

