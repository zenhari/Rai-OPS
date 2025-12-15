<?php
require_once '../../config.php';

// Check access
checkPageAccessWithRedirect('admin/profile/my_quiz.php');

$current_user = getCurrentUser();
$db = getDBConnection();

$assignmentId = intval($_GET['assignment_id'] ?? 0);
$attemptId = intval($_GET['attempt_id'] ?? 0);

if (!$assignmentId || !$attemptId) {
    header('Location: my_quiz.php');
    exit();
}

// Get attempt details with assignment and quiz set info
$stmt = $db->prepare("SELECT qa.*, qa2.id as assignment_id_from_attempt, qa2.quiz_set_id, qa2.user_id as assignment_user_id, qs.name as quiz_set_name, qs.passing_score
                      FROM quiz_attempts qa
                      LEFT JOIN quiz_assignments qa2 ON qa.quiz_assignment_id = qa2.id
                      LEFT JOIN quiz_sets qs ON qa2.quiz_set_id = qs.id
                      WHERE qa.id = ?");
$stmt->execute([$attemptId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if attempt exists
if (!$attempt) {
    header('Location: my_quiz.php');
    exit();
}

// Check if attempt belongs to current user
if ($attempt['user_id'] != $current_user['id'] || $attempt['assignment_user_id'] != $current_user['id']) {
    header('Location: my_quiz.php');
    exit();
}

// Use assignment_id from attempt if provided assignment_id doesn't match
if ($assignmentId != $attempt['assignment_id_from_attempt']) {
    $assignmentId = $attempt['assignment_id_from_attempt'];
}

// Check if attempt is completed or timeout - if not, redirect to take quiz
// Also check if attempt has completed_at timestamp to ensure it's really finished
if ($attempt['status'] !== 'completed' && $attempt['status'] !== 'timeout') {
    // If attempt is in_progress, redirect to take quiz
    header('Location: take_quiz.php?assignment_id=' . $assignmentId . '&attempt_id=' . $attemptId);
    exit();
}

// Additional check: if attempt doesn't have completed_at, it's not really finished
if (empty($attempt['completed_at'])) {
    // Attempt might be marked as completed but not actually finished
    // This can happen due to timezone issues or incomplete finish process
    // Redirect to take quiz to continue or finish properly
    header('Location: take_quiz.php?assignment_id=' . $assignmentId . '&attempt_id=' . $attemptId);
    exit();
}

// Final check: verify that attempt has score and correct_answers calculated
// If not, it means the quiz wasn't properly finished
if ($attempt['score'] === null || $attempt['correct_answers'] === null) {
    // Quiz wasn't properly finished, redirect to take quiz
    header('Location: take_quiz.php?assignment_id=' . $assignmentId . '&attempt_id=' . $attemptId);
    exit();
}

// Get assignment details
$stmt = $db->prepare("SELECT * FROM quiz_assignments WHERE id = ?");
$stmt->execute([$assignmentId]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all questions for this quiz set
$stmt = $db->prepare("SELECT q.*, qsq.order_number 
                      FROM quiz_set_questions qsq
                      LEFT JOIN questions q ON qsq.question_id = q.id
                      WHERE qsq.quiz_set_id = ?
                      ORDER BY qsq.order_number");
$stmt->execute([$attempt['quiz_set_id']]);
$allQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all answers
$stmt = $db->prepare("SELECT question_id, selected_option, is_correct, is_marked FROM quiz_answers WHERE quiz_attempt_id = ?");
$stmt->execute([$attemptId]);
$answers = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $answer) {
    $answers[$answer['question_id']] = $answer;
}

// Calculate statistics
$totalQuestions = count($allQuestions);
$correctAnswers = intval($attempt['correct_answers']);
$wrongAnswers = $totalQuestions - $correctAnswers;
$score = floatval($attempt['score']);
$isPassed = $score >= floatval($attempt['passing_score']);

// Calculate time spent using database TIMESTAMPDIFF to avoid timezone issues
// This ensures we use server time for both started_at and completed_at
if (!empty($attempt['completed_at'])) {
    $stmt = $db->prepare("SELECT TIMESTAMPDIFF(SECOND, started_at, completed_at) as time_spent_calc FROM quiz_attempts WHERE id = ?");
    $stmt->execute([$attemptId]);
    $timeResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $timeSpent = intval($timeResult['time_spent_calc'] ?? $attempt['time_spent'] ?? 0);
    
    // Update time_spent in database if it's different (fix for existing records)
    if ($timeSpent != intval($attempt['time_spent'] ?? 0)) {
        $stmt = $db->prepare("UPDATE quiz_attempts SET time_spent = ? WHERE id = ?");
        $stmt->execute([$timeSpent, $attemptId]);
    }
} else {
    $timeSpent = intval($attempt['time_spent'] ?? 0);
}

$hours = floor($timeSpent / 3600);
$minutes = floor(($timeSpent % 3600) / 60);
$seconds = $timeSpent % 60;
$timeSpentFormatted = '';
if ($hours > 0) {
    $timeSpentFormatted .= $hours . 'h ';
}
if ($minutes > 0) {
    $timeSpentFormatted .= $minutes . 'm ';
}
$timeSpentFormatted .= $seconds . 's';

// Format dates
$startedAt = date('M j, Y g:i A', strtotime($attempt['started_at']));
$completedAt = $attempt['completed_at'] ? date('M j, Y g:i A', strtotime($attempt['completed_at'])) : 'N/A';
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Result - <?php echo htmlspecialchars($attempt['quiz_set_name']); ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                Quiz Result: <?php echo htmlspecialchars($attempt['quiz_set_name']); ?>
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                <?php echo $attempt['status'] === 'timeout' ? 'Quiz completed due to timeout' : 'Quiz completed successfully'; ?>
                            </p>
                        </div>
                        <a href="my_quiz.php" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to My Quiz
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <div class="max-w-5xl mx-auto space-y-6">
                    <!-- Summary Card -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Score -->
                            <div class="text-center">
                                <div class="text-4xl font-bold <?php echo $isPassed ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?> mb-2">
                                    <?php echo number_format($score, 2); ?>%
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Final Score</div>
                                <div class="mt-2">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $isPassed ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; ?>">
                                        <?php echo $isPassed ? '<i class="fas fa-check-circle mr-1"></i>Passed' : '<i class="fas fa-times-circle mr-1"></i>Failed'; ?>
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                    Passing Score: <?php echo $attempt['passing_score']; ?>%
                                </div>
                            </div>
                            
                            <!-- Correct Answers -->
                            <div class="text-center">
                                <div class="text-4xl font-bold text-green-600 dark:text-green-400 mb-2">
                                    <?php echo $correctAnswers; ?>/<?php echo $totalQuestions; ?>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Correct Answers</div>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                                    <?php echo $wrongAnswers; ?> incorrect
                                </div>
                            </div>
                            
                            <!-- Time Spent -->
                            <div class="text-center">
                                <div class="text-4xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                                    <?php echo $timeSpentFormatted; ?>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Time Spent</div>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                                    Started: <?php echo $startedAt; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-center space-x-4">
                        <a href="my_quiz.php" 
                           class="px-6 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <i class="fas fa-list mr-2"></i>
                            Back to My Quiz
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

