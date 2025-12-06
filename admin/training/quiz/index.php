<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/training/quiz/index.php');

$current_user = getCurrentUser();
$db = getDBConnection();

// Get all quiz sets with statistics
$stmt = $db->query("SELECT qs.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                    (SELECT COUNT(*) FROM quiz_set_questions WHERE quiz_set_id = qs.id) as question_count,
                    (SELECT COUNT(*) FROM quiz_assignments WHERE quiz_set_id = qs.id) as assignment_count,
                    (SELECT COUNT(*) FROM quiz_attempts qa 
                     INNER JOIN quiz_assignments qa2 ON qa.quiz_assignment_id = qa2.id 
                     WHERE qa2.quiz_set_id = qs.id) as attempt_count,
                    (SELECT COUNT(*) FROM quiz_attempts qa 
                     INNER JOIN quiz_assignments qa2 ON qa.quiz_assignment_id = qa2.id 
                     WHERE qa2.quiz_set_id = qs.id AND qa.status = 'completed') as completed_count
                    FROM quiz_sets qs
                    LEFT JOIN users u ON qs.created_by = u.id
                    ORDER BY qs.created_at DESC");
$quizSets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Set List - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Quiz Set List</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View and manage all quiz sets</p>
                        </div>
                        <div>
                            <a href="create_set.php"
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-plus mr-2"></i>
                                Create New Quiz Set
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php if (empty($quizSets)): ?>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-12 text-center">
                        <i class="fas fa-clipboard-list text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Quiz Sets Found</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">Get started by creating your first quiz set.</p>
                        <a href="create_set.php"
                           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>
                            Create Quiz Set
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 rounded-md p-3">
                                    <i class="fas fa-clipboard-list text-blue-600 dark:text-blue-400 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Quiz Sets</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo count($quizSets); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 rounded-md p-3">
                                    <i class="fas fa-question-circle text-green-600 dark:text-green-400 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Questions</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        <?php echo array_sum(array_column($quizSets, 'question_count')); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 rounded-md p-3">
                                    <i class="fas fa-user-check text-purple-600 dark:text-purple-400 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Assignments</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        <?php echo array_sum(array_column($quizSets, 'assignment_count')); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-orange-100 dark:bg-orange-900 rounded-md p-3">
                                    <i class="fas fa-check-circle text-orange-600 dark:text-orange-400 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Completed Attempts</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        <?php echo array_sum(array_column($quizSets, 'completed_count')); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quiz Sets Table -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white">All Quiz Sets</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quiz Set Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Questions</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time Limit</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Passing Score</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Assignments</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Attempts</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created By</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created At</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php foreach ($quizSets as $quizSet): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($quizSet['name']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                                    <?php echo htmlspecialchars($quizSet['description'] ?? 'No description'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <i class="fas fa-question-circle mr-1"></i>
                                                    <?php echo $quizSet['question_count']; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <?php echo $quizSet['time_limit']; ?> min
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <i class="fas fa-trophy mr-1"></i>
                                                    <?php echo number_format($quizSet['passing_score'], 1); ?>%
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <i class="fas fa-user-check mr-1"></i>
                                                    <?php echo $quizSet['assignment_count']; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    <?php echo $quizSet['completed_count']; ?> / <?php echo $quizSet['attempt_count']; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($quizSet['created_by_name'] ?? 'Unknown'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo date('M j, Y', strtotime($quizSet['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex items-center space-x-2">
                                                    <a href="assign_quiz.php?quiz_set_id=<?php echo $quizSet['id']; ?>"
                                                       class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                       title="Assign Quiz">
                                                        <i class="fas fa-user-plus"></i>
                                                    </a>
                                                    <a href="results.php?quiz_set_id=<?php echo $quizSet['id']; ?>"
                                                       class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                                       title="View Results">
                                                        <i class="fas fa-chart-bar"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

