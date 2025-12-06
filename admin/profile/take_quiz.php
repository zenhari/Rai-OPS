<?php
require_once '../../config.php';

// Check access
checkPageAccessWithRedirect('admin/profile/my_quiz.php');

$current_user = getCurrentUser();
$db = getDBConnection();

$assignmentId = intval($_GET['assignment_id'] ?? 0);
$attemptId = intval($_GET['attempt_id'] ?? 0);

if (!$assignmentId) {
    header('Location: my_quiz.php');
    exit();
}

// Get current server time
$serverTimeStmt = $db->query("SELECT NOW() as server_time");
$serverTime = $serverTimeStmt->fetch(PDO::FETCH_ASSOC)['server_time'];

// Get assignment details
$stmt = $db->prepare("SELECT qa.*, qs.name as quiz_set_name, qs.time_limit, qs.passing_score
                      FROM quiz_assignments qa
                      LEFT JOIN quiz_sets qs ON qa.quiz_set_id = qs.id
                      WHERE qa.id = ? AND qa.user_id = ?");
$stmt->execute([$assignmentId, $current_user['id']]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    header('Location: my_quiz.php');
    exit();
}

// Check if quiz start date has passed (due_date is now used as start_date)
if ($assignment['due_date']) {
    $startDate = strtotime($assignment['due_date']);
    $currentTime = strtotime($serverTime);
    if ($currentTime < $startDate) {
        // Quiz is not available yet
        header('Location: my_quiz.php?error=quiz_not_available');
        exit();
    }
}

// Check if assignment is expired
if ($assignment['status'] === 'expired') {
    header('Location: my_quiz.php');
    exit();
}

// Check if user has already completed this quiz (only one attempt allowed)
$completedStmt = $db->prepare("SELECT id FROM quiz_attempts 
                               WHERE quiz_assignment_id = ? AND user_id = ? 
                               AND (status = 'completed' OR status = 'timeout')
                               AND completed_at IS NOT NULL
                               LIMIT 1");
$completedStmt->execute([$assignmentId, $current_user['id']]);
$completedAttempt = $completedStmt->fetch(PDO::FETCH_ASSOC);

if ($completedAttempt && !$attemptId) {
    // User has already completed this quiz, redirect to result page
    header('Location: quiz_result.php?assignment_id=' . $assignmentId . '&attempt_id=' . $completedAttempt['id']);
    exit();
}

// Get or create attempt
if ($attemptId) {
    // Check if attempt exists and belongs to user
    $stmt = $db->prepare("SELECT * FROM quiz_attempts WHERE id = ? AND user_id = ? AND quiz_assignment_id = ?");
    $stmt->execute([$attemptId, $current_user['id'], $assignmentId]);
    $existingAttempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingAttempt) {
        // If attempt is already completed or timeout, redirect to result page
        if ($existingAttempt['status'] === 'completed' || $existingAttempt['status'] === 'timeout') {
            header('Location: quiz_result.php?assignment_id=' . $assignmentId . '&attempt_id=' . $attemptId);
            exit();
        }
        
        // If attempt is in progress, continue with it
        if ($existingAttempt['status'] === 'in_progress') {
            $attempt = $existingAttempt;
            
            // Ensure started_at is set to current time when user actually opens the page
            // This ensures timer starts from when user actually starts, not when attempt was created
            // Only update if started_at is NULL or invalid (shouldn't happen with DEFAULT CURRENT_TIMESTAMP, but just in case)
            if (empty($existingAttempt['started_at']) || $existingAttempt['started_at'] === '0000-00-00 00:00:00' || $existingAttempt['started_at'] === null) {
                $db->prepare("UPDATE quiz_attempts SET started_at = NOW() WHERE id = ?")->execute([$attemptId]);
                // Reload attempt to get updated started_at
                $stmt = $db->prepare("SELECT * FROM quiz_attempts WHERE id = ?");
                $stmt->execute([$attemptId]);
                $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } else {
            $attemptId = 0; // Create new attempt
        }
    } else {
        $attemptId = 0; // Create new attempt
    }
}

if (!$attemptId) {
    // Check again if user has completed this quiz (double check before creating new attempt)
    $completedStmt = $db->prepare("SELECT id FROM quiz_attempts 
                                   WHERE quiz_assignment_id = ? AND user_id = ? 
                                   AND (status = 'completed' OR status = 'timeout')
                                   AND completed_at IS NOT NULL
                                   LIMIT 1");
    $completedStmt->execute([$assignmentId, $current_user['id']]);
    $completedAttempt = $completedStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($completedAttempt) {
        // User has already completed this quiz, redirect to result page
        header('Location: quiz_result.php?assignment_id=' . $assignmentId . '&attempt_id=' . $completedAttempt['id']);
        exit();
    }
    
    // Create new attempt
    $stmt = $db->prepare("SELECT q.id, qsq.order_number 
                          FROM quiz_set_questions qsq
                          LEFT JOIN questions q ON qsq.question_id = q.id
                          WHERE qsq.quiz_set_id = (SELECT quiz_set_id FROM quiz_assignments WHERE id = ?)
                          ORDER BY qsq.order_number");
    $stmt->execute([$assignmentId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($questions)) {
        header('Location: my_quiz.php');
        exit();
    }
    
    // Update assignment status
    $db->prepare("UPDATE quiz_assignments SET status = 'in_progress' WHERE id = ?")->execute([$assignmentId]);
    
    // Create attempt without started_at (we'll set it when user actually starts)
    // This ensures timer starts from when user opens the page, not from when attempt was created
    $stmt = $db->prepare("INSERT INTO quiz_attempts (quiz_assignment_id, user_id, total_questions, started_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$assignmentId, $current_user['id'], count($questions)]);
    $attemptId = $db->lastInsertId();
    
    // Set started_at to current time (this is when user actually starts the quiz by opening the page)
    // This ensures timer starts from when user opens the page, not from when attempt was created
    $db->prepare("UPDATE quiz_attempts SET started_at = NOW() WHERE id = ?")->execute([$attemptId]);
}

// Get attempt details
$stmt = $db->prepare("SELECT * FROM quiz_attempts WHERE id = ?");
$stmt->execute([$attemptId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all questions for this quiz set
$stmt = $db->prepare("SELECT q.*, qsq.order_number 
                      FROM quiz_set_questions qsq
                      LEFT JOIN questions q ON qsq.question_id = q.id
                      WHERE qsq.quiz_set_id = (SELECT quiz_set_id FROM quiz_assignments WHERE id = ?)
                      ORDER BY qsq.order_number");
$stmt->execute([$assignmentId]);
$allQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current answers
$stmt = $db->prepare("SELECT question_id, selected_option, is_marked FROM quiz_answers WHERE quiz_attempt_id = ?");
$stmt->execute([$attemptId]);
$answers = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $answer) {
    $answers[$answer['question_id']] = $answer;
}

// Calculate time remaining using database TIMESTAMPDIFF to avoid timezone issues
// This ensures we use server time consistently
$stmt = $db->prepare("SELECT TIMESTAMPDIFF(SECOND, started_at, NOW()) as elapsed_seconds FROM quiz_attempts WHERE id = ?");
$stmt->execute([$attemptId]);
$timeResult = $stmt->fetch(PDO::FETCH_ASSOC);
$elapsedSeconds = intval($timeResult['elapsed_seconds'] ?? 0);

$timeLimitSeconds = $assignment['time_limit'] * 60;
$remainingSeconds = max(0, $timeLimitSeconds - $elapsedSeconds);

// Get current question index
$currentQuestionIndex = intval($_GET['q'] ?? 0);
if ($currentQuestionIndex < 0 || $currentQuestionIndex >= count($allQuestions)) {
    $currentQuestionIndex = 0;
}

$currentQuestion = $allQuestions[$currentQuestionIndex] ?? null;
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Quiz - <?php echo htmlspecialchars($assignment['quiz_set_name']); ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        .question-number-cell {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 2px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .question-number-cell:hover {
            transform: scale(1.1);
        }
        .question-number-cell.answered {
            background-color: #10b981;
            color: white;
        }
        .question-number-cell.marked {
            background-color: #f59e0b;
            color: white;
        }
        .question-number-cell.unanswered {
            background-color: #9ca3af;
            color: white;
        }
        .question-number-cell.current {
            border: 3px solid #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
        }
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
                                <?php echo htmlspecialchars($assignment['quiz_set_name']); ?>
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Question <?php echo $currentQuestionIndex + 1; ?> of <?php echo count($allQuestions); ?>
                            </p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Time Remaining</div>
                                <div id="timer" class="text-2xl font-bold <?php echo $remainingSeconds < 300 ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400'; ?>">
                                    <?php
                                    $minutes = floor($remainingSeconds / 60);
                                    $seconds = $remainingSeconds % 60;
                                    echo sprintf('%02d:%02d', $minutes, $seconds);
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php if ($currentQuestion): ?>
                    <div class="max-w-4xl mx-auto">
                        <!-- Question Card -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Question <?php echo $currentQuestionIndex + 1; ?>
                                </h2>
                                <button type="button" onclick="toggleMark(<?php echo $currentQuestion['id']; ?>)" 
                                        id="mark-btn-<?php echo $currentQuestion['id']; ?>"
                                        class="px-4 py-2 text-sm font-medium rounded-md <?php echo isset($answers[$currentQuestion['id']]) && $answers[$currentQuestion['id']]['is_marked'] ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'; ?> hover:bg-orange-200 dark:hover:bg-orange-800">
                                    <i class="fas fa-bookmark mr-1"></i>
                                    <?php echo isset($answers[$currentQuestion['id']]) && $answers[$currentQuestion['id']]['is_marked'] ? 'Unmark' : 'Mark'; ?>
                                </button>
                            </div>
                            
                            <div class="mb-6">
                                <p class="text-gray-900 dark:text-white text-lg mb-6">
                                    <?php echo nl2br(htmlspecialchars($currentQuestion['question'])); ?>
                                </p>
                                
                                <div class="space-y-3">
                                    <?php foreach (['a', 'b', 'c', 'd'] as $option): ?>
                                        <?php if (!empty($currentQuestion['option_' . $option])): ?>
                                            <?php 
                                            $isSelected = isset($answers[$currentQuestion['id']]) && $answers[$currentQuestion['id']]['selected_option'] === $option;
                                            ?>
                                            <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer transition-colors <?php echo $isSelected ? 'border-blue-500 bg-blue-50 dark:bg-blue-900' : 'border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600'; ?>"
                                                   onmousedown="handleOptionMouseDown(event, <?php echo $currentQuestion['id']; ?>, '<?php echo $option; ?>')">
                                                <input type="radio" 
                                                       name="answer_<?php echo $currentQuestion['id']; ?>" 
                                                       value="<?php echo $option; ?>"
                                                       id="option_<?php echo $currentQuestion['id']; ?>_<?php echo $option; ?>"
                                                       class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500"
                                                       <?php echo $isSelected ? 'checked' : ''; ?>
                                                       onchange="handleRadioChange(<?php echo $currentQuestion['id']; ?>, '<?php echo $option; ?>')">
                                                <div class="ml-3 flex-1">
                                                    <span class="font-medium text-gray-900 dark:text-white mr-2"><?php echo strtoupper($option); ?>.</span>
                                                    <span class="text-gray-700 dark:text-gray-300">
                                                        <?php echo nl2br(htmlspecialchars($currentQuestion['option_' . $option])); ?>
                                                    </span>
                                                </div>
                                            </label>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Navigation -->
                        <div class="flex justify-between items-center mb-6">
                            <button type="button" 
                                    onclick="navigateQuestion(<?php echo $currentQuestionIndex - 1; ?>)"
                                    <?php echo $currentQuestionIndex === 0 ? 'disabled' : ''; ?>
                                    class="px-6 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium <?php echo $currentQuestionIndex === 0 ? 'text-gray-400 dark:text-gray-600 bg-gray-100 dark:bg-gray-800 cursor-not-allowed' : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Previous
                            </button>
                            
                            <?php if ($currentQuestionIndex === count($allQuestions) - 1): ?>
                                <button type="button" 
                                        onclick="finishQuiz()"
                                        class="px-6 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                                    <i class="fas fa-check mr-2"></i>
                                    Finish Quiz
                                </button>
                            <?php else: ?>
                                <button type="button" 
                                        onclick="navigateQuestion(<?php echo $currentQuestionIndex + 1; ?>)"
                                        class="px-6 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                    Next
                                    <i class="fas fa-arrow-right ml-2"></i>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Question Numbers Grid -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Question Navigation</h3>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($allQuestions as $index => $question): ?>
                                    <?php
                                    $isAnswered = isset($answers[$question['id']]) && !empty($answers[$question['id']]['selected_option']);
                                    $isMarked = isset($answers[$question['id']]) && $answers[$question['id']]['is_marked'];
                                    $isCurrent = $index === $currentQuestionIndex;
                                    
                                    $cellClass = 'question-number-cell ';
                                    if ($isCurrent) {
                                        $cellClass .= 'current ';
                                    }
                                    if ($isAnswered) {
                                        $cellClass .= 'answered';
                                    } elseif ($isMarked) {
                                        $cellClass .= 'marked';
                                    } else {
                                        $cellClass .= 'unanswered';
                                    }
                                    ?>
                                    <div class="<?php echo $cellClass; ?>" 
                                         onclick="navigateQuestion(<?php echo $index; ?>)"
                                         title="Question <?php echo $index + 1; ?>">
                                        <?php echo $index + 1; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4 flex items-center space-x-4 text-xs text-gray-600 dark:text-gray-400">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-green-500 rounded mr-2"></div>
                                    <span>Answered</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-orange-500 rounded mr-2"></div>
                                    <span>Marked</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-gray-400 rounded mr-2"></div>
                                    <span>Unanswered</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <p class="text-gray-500 dark:text-gray-400">No questions found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const attemptId = <?php echo $attemptId; ?>;
        const assignmentId = <?php echo $assignmentId; ?>;
        const totalQuestions = <?php echo count($allQuestions); ?>;
        let remainingSeconds = <?php echo $remainingSeconds; ?>;
        let timerInterval;

        // Timer
        function updateTimer() {
            if (remainingSeconds <= 0) {
                clearInterval(timerInterval);
                finishQuiz(true);
                return;
            }
            
            remainingSeconds--;
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            const timerElement = document.getElementById('timer');
            timerElement.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            
            if (remainingSeconds < 300) {
                timerElement.classList.remove('text-blue-600', 'dark:text-blue-400');
                timerElement.classList.add('text-red-600', 'dark:text-red-400');
            }
        }

        timerInterval = setInterval(updateTimer, 1000);

        // Handle option mousedown (on label) to allow deselect
        function handleOptionMouseDown(event, questionId, option) {
            const radio = document.getElementById(`option_${questionId}_${option}`);
            
            // If clicking on already selected option, deselect it
            if (radio.checked) {
                event.preventDefault();
                event.stopPropagation();
                // Deselect all options
                const allRadios = document.querySelectorAll(`input[name="answer_${questionId}"]`);
                allRadios.forEach(r => {
                    r.checked = false;
                });
                clearAnswer(questionId);
            }
            // If not checked, let the default behavior happen (radio will be checked and onchange will fire)
        }

        // Handle radio button change (when option is selected)
        function handleRadioChange(questionId, option) {
            const radio = document.getElementById(`option_${questionId}_${option}`);
            if (radio && radio.checked) {
                saveAnswer(questionId, option);
            }
        }

        // Save answer
        function saveAnswer(questionId, option) {
            fetch('take_quiz_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=save_answer&attempt_id=${attemptId}&question_id=${questionId}&option=${option}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update visual state
                    updateAnswerVisualState(questionId, option);
                    // Update question number cell without reloading
                    updateQuestionCellStatus(questionId, true);
                } else {
                    console.error('Error saving answer:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error saving answer:', error);
            });
        }

        // Clear answer
        function clearAnswer(questionId) {
            fetch('take_quiz_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=clear_answer&attempt_id=${attemptId}&question_id=${questionId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Uncheck all radio buttons for this question
                    const radios = document.querySelectorAll(`input[name="answer_${questionId}"]`);
                    radios.forEach(radio => {
                        radio.checked = false;
                    });
                    
                    // Update visual state
                    updateAnswerVisualState(questionId, null);
                    // Update question number cell without reloading
                    updateQuestionCellStatus(questionId, false);
                } else {
                    console.error('Error clearing answer:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error clearing answer:', error);
            });
        }

        // Update visual state of answer options
        function updateAnswerVisualState(questionId, selectedOption) {
            // Remove all selected styles
            const labels = document.querySelectorAll(`input[name="answer_${questionId}"]`);
            labels.forEach(radio => {
                const label = radio.closest('label');
                if (label) {
                    label.classList.remove('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900');
                    label.classList.add('border-gray-200', 'dark:border-gray-700');
                }
            });

            // Add selected style to current option
            if (selectedOption) {
                const selectedRadio = document.getElementById(`option_${questionId}_${selectedOption}`);
                if (selectedRadio) {
                    const label = selectedRadio.closest('label');
                    if (label) {
                        label.classList.remove('border-gray-200', 'dark:border-gray-700');
                        label.classList.add('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900');
                    }
                }
            }
        }

        // Toggle mark
        function toggleMark(questionId) {
            const isMarked = document.getElementById(`mark-btn-${questionId}`).classList.contains('bg-orange-100');
            
            fetch('take_quiz_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_mark&attempt_id=${attemptId}&question_id=${questionId}&is_marked=${isMarked ? 0 : 1}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const btn = document.getElementById(`mark-btn-${questionId}`);
                    if (data.is_marked) {
                        btn.classList.remove('bg-gray-100', 'text-gray-800', 'dark:bg-gray-700', 'dark:text-gray-300');
                        btn.classList.add('bg-orange-100', 'text-orange-800', 'dark:bg-orange-900', 'dark:text-orange-200');
                        btn.innerHTML = '<i class="fas fa-bookmark mr-1"></i>Unmark';
                    } else {
                        btn.classList.remove('bg-orange-100', 'text-orange-800', 'dark:bg-orange-900', 'dark:text-orange-200');
                        btn.classList.add('bg-gray-100', 'text-gray-800', 'dark:bg-gray-700', 'dark:text-gray-300');
                        btn.innerHTML = '<i class="fas fa-bookmark mr-1"></i>Mark';
                    }
                    updateQuestionCell(questionId);
                }
            })
            .catch(error => {
                console.error('Error toggling mark:', error);
            });
        }

        // Update question cell status without reloading
        function updateQuestionCellStatus(questionId, isAnswered) {
            // Find the question index
            const questionIndex = <?php echo json_encode(array_column($allQuestions, 'id')); ?>.indexOf(questionId);
            if (questionIndex === -1) return;
            
            // Find the cell element
            const cells = document.querySelectorAll('.question-number-cell');
            if (cells[questionIndex]) {
                const cell = cells[questionIndex];
                // Remove all status classes
                cell.classList.remove('answered', 'marked', 'unanswered');
                
                // Check if marked
                const markBtn = document.getElementById(`mark-btn-${questionId}`);
                const isMarked = markBtn && markBtn.classList.contains('bg-orange-100');
                
                if (isMarked) {
                    cell.classList.add('marked');
                } else if (isAnswered) {
                    cell.classList.add('answered');
                } else {
                    cell.classList.add('unanswered');
                }
            }
        }
        
        // Update question cell (kept for backward compatibility with mark function)
        function updateQuestionCell(questionId) {
            // Check if answered
            const radios = document.querySelectorAll(`input[name="answer_${questionId}"]`);
            let isAnswered = false;
            radios.forEach(radio => {
                if (radio.checked) {
                    isAnswered = true;
                }
            });
            
            updateQuestionCellStatus(questionId, isAnswered);
        }

        // Navigate to question
        function navigateQuestion(index) {
            if (index >= 0 && index < totalQuestions) {
                window.location.href = `take_quiz.php?assignment_id=${assignmentId}&attempt_id=${attemptId}&q=${index}`;
            }
        }

        // Finish quiz
        function finishQuiz(isTimeout = false) {
            if (isTimeout || confirm('Are you sure you want to finish the quiz? You cannot change your answers after submitting.')) {
                clearInterval(timerInterval);
                
                fetch('take_quiz_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=finish_quiz&attempt_id=${attemptId}&is_timeout=${isTimeout ? 1 : 0}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = `quiz_result.php?assignment_id=${assignmentId}&attempt_id=${attemptId}`;
                    } else {
                        alert('Error finishing quiz: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error finishing quiz:', error);
                    alert('Error finishing quiz. Please try again.');
                });
            }
        }

        // Note: Removed beforeunload warning as requested
    </script>
</body>
</html>

