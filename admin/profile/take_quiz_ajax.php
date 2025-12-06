<?php
require_once '../../config.php';

// Check access
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$current_user = getCurrentUser();
$db = getDBConnection();

$action = $_POST['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'save_answer':
            $attemptId = intval($_POST['attempt_id'] ?? 0);
            $questionId = intval($_POST['question_id'] ?? 0);
            $option = $_POST['option'] ?? '';
            
            if (!$attemptId || !$questionId || !$option) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                exit();
            }
            
            // Verify attempt belongs to user
            $stmt = $db->prepare("SELECT * FROM quiz_attempts WHERE id = ? AND user_id = ? AND status = 'in_progress'");
            $stmt->execute([$attemptId, $current_user['id']]);
            $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$attempt) {
                echo json_encode(['success' => false, 'message' => 'Invalid attempt']);
                exit();
            }
            
            // Get correct answer
            $stmt = $db->prepare("SELECT correct_option FROM questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);
            $isCorrect = ($question && $question['correct_option'] === $option) ? 1 : 0;
            
            // Insert or update answer
            $stmt = $db->prepare("INSERT INTO quiz_answers (quiz_attempt_id, question_id, selected_option, is_correct, answered_at) 
                                  VALUES (?, ?, ?, ?, NOW())
                                  ON DUPLICATE KEY UPDATE selected_option = ?, is_correct = ?, answered_at = NOW()");
            $stmt->execute([$attemptId, $questionId, $option, $isCorrect, $option, $isCorrect]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'clear_answer':
            $attemptId = intval($_POST['attempt_id'] ?? 0);
            $questionId = intval($_POST['question_id'] ?? 0);
            
            if (!$attemptId || !$questionId) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                exit();
            }
            
            // Verify attempt belongs to user
            $stmt = $db->prepare("SELECT * FROM quiz_attempts WHERE id = ? AND user_id = ? AND status = 'in_progress'");
            $stmt->execute([$attemptId, $current_user['id']]);
            $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$attempt) {
                echo json_encode(['success' => false, 'message' => 'Invalid attempt']);
                exit();
            }
            
            // Check if answer exists and if it's marked
            $stmt = $db->prepare("SELECT is_marked FROM quiz_answers WHERE quiz_attempt_id = ? AND question_id = ?");
            $stmt->execute([$attemptId, $questionId]);
            $existingAnswer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingAnswer) {
                // Update answer to clear selection (but keep the record if it's marked)
                $stmt = $db->prepare("UPDATE quiz_answers 
                                      SET selected_option = NULL, is_correct = 0, answered_at = NULL
                                      WHERE quiz_attempt_id = ? AND question_id = ?");
                $stmt->execute([$attemptId, $questionId]);
                
                // If no mark and no answer, delete the record completely
                if (!$existingAnswer['is_marked']) {
                    $stmt = $db->prepare("DELETE FROM quiz_answers 
                                          WHERE quiz_attempt_id = ? AND question_id = ? 
                                          AND (selected_option IS NULL OR selected_option = '') 
                                          AND is_marked = 0");
                    $stmt->execute([$attemptId, $questionId]);
                }
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'toggle_mark':
            $attemptId = intval($_POST['attempt_id'] ?? 0);
            $questionId = intval($_POST['question_id'] ?? 0);
            $isMarked = intval($_POST['is_marked'] ?? 0);
            
            if (!$attemptId || !$questionId) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                exit();
            }
            
            // Verify attempt belongs to user
            $stmt = $db->prepare("SELECT * FROM quiz_attempts WHERE id = ? AND user_id = ? AND status = 'in_progress'");
            $stmt->execute([$attemptId, $current_user['id']]);
            $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$attempt) {
                echo json_encode(['success' => false, 'message' => 'Invalid attempt']);
                exit();
            }
            
            // Insert or update mark
            $stmt = $db->prepare("INSERT INTO quiz_answers (quiz_attempt_id, question_id, is_marked) 
                                  VALUES (?, ?, ?)
                                  ON DUPLICATE KEY UPDATE is_marked = ?");
            $stmt->execute([$attemptId, $questionId, $isMarked, $isMarked]);
            
            echo json_encode(['success' => true, 'is_marked' => $isMarked]);
            break;
            
        case 'finish_quiz':
            $attemptId = intval($_POST['attempt_id'] ?? 0);
            $isTimeout = intval($_POST['is_timeout'] ?? 0);
            
            if (!$attemptId) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                exit();
            }
            
            // Verify attempt belongs to user
            $stmt = $db->prepare("SELECT * FROM quiz_attempts WHERE id = ? AND user_id = ? AND status = 'in_progress'");
            $stmt->execute([$attemptId, $current_user['id']]);
            $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$attempt) {
                echo json_encode(['success' => false, 'message' => 'Invalid attempt']);
                exit();
            }
            
            // Calculate score
            $stmt = $db->prepare("SELECT COUNT(*) as correct_count FROM quiz_answers 
                                  WHERE quiz_attempt_id = ? AND is_correct = 1");
            $stmt->execute([$attemptId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $correctAnswers = intval($result['correct_count']);
            
            $totalQuestions = $attempt['total_questions'];
            $score = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 100 : 0;
            
            // Update attempt with completed_at first
            $status = $isTimeout ? 'timeout' : 'completed';
            $stmt = $db->prepare("UPDATE quiz_attempts 
                                  SET status = ?, completed_at = NOW(), score = ?, correct_answers = ?
                                  WHERE id = ?");
            $stmt->execute([$status, $score, $correctAnswers, $attemptId]);
            
            // Calculate time spent using database TIMESTAMPDIFF to avoid timezone issues
            // This ensures we use server time for both started_at and completed_at
            $stmt = $db->prepare("UPDATE quiz_attempts 
                                  SET time_spent = TIMESTAMPDIFF(SECOND, started_at, completed_at)
                                  WHERE id = ?");
            $stmt->execute([$attemptId]);
            
            // Update assignment status
            $stmt = $db->prepare("UPDATE quiz_assignments SET status = 'completed' WHERE id = ?");
            $stmt->execute([$attempt['quiz_assignment_id']]);
            
            echo json_encode(['success' => true, 'score' => $score]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

