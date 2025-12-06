<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/training/quiz/create_set.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

$db = getDBConnection();

// Get unique courses and aircrafts
$courses = [];
$aircrafts = [];
$questions = [];

$stmt = $db->query("SELECT DISTINCT course FROM questions ORDER BY course");
$courses = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $db->query("SELECT DISTINCT aircraft FROM questions ORDER BY aircraft");
$aircrafts = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $timeLimit = intval($_POST['time_limit'] ?? 0);
    $passingScore = floatval($_POST['passing_score'] ?? 0);
    $selectedCourses = $_POST['courses'] ?? [];
    $courseCounts = $_POST['course_counts'] ?? [];
    $selectedAircraft = trim($_POST['aircraft'] ?? '');
    
    if (empty($name)) {
        $error = 'Quiz set name is required.';
    } elseif ($timeLimit <= 0) {
        $error = 'Time limit must be greater than 0.';
    } elseif ($passingScore < 0 || $passingScore > 100) {
        $error = 'Passing score must be between 0 and 100.';
    } elseif (empty($selectedCourses)) {
        $error = 'Please select at least one course.';
    } else {
        // Validate course counts
        $totalQuestions = 0;
        $courseQuestionData = [];
        foreach ($selectedCourses as $course) {
            $count = intval($courseCounts[$course] ?? 0);
            if ($count > 0) {
                // Get available questions for this course
                $query = "SELECT id FROM questions WHERE course = ?";
                $params = [$course];
                if ($selectedAircraft) {
                    $query .= " AND aircraft = ?";
                    $params[] = $selectedAircraft;
                }
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $availableQuestions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (count($availableQuestions) < $count) {
                    $error = "Course '{$course}' has only " . count($availableQuestions) . " question(s), but you requested {$count}.";
                    break;
                }
                
                // Select random questions
                shuffle($availableQuestions);
                $selectedQuestions = array_slice($availableQuestions, 0, $count);
                $courseQuestionData[$course] = $selectedQuestions;
                $totalQuestions += count($selectedQuestions);
            }
        }
        
        if (empty($error) && $totalQuestions === 0) {
            $error = 'Please specify question counts for at least one course.';
        }
        
        if (empty($error)) {
            try {
                $db->beginTransaction();
                
                // Insert quiz set
                $stmt = $db->prepare("INSERT INTO quiz_sets (name, description, time_limit, passing_score, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $timeLimit, $passingScore, $current_user['id']]);
                $quizSetId = $db->lastInsertId();
                
                // Insert questions from all courses
                $orderNumber = 1;
                $stmt = $db->prepare("INSERT INTO quiz_set_questions (quiz_set_id, question_id, order_number) VALUES (?, ?, ?)");
                foreach ($courseQuestionData as $course => $questionIds) {
                    foreach ($questionIds as $questionId) {
                        $stmt->execute([$quizSetId, intval($questionId), $orderNumber]);
                        $orderNumber++;
                    }
                }
                
                $db->commit();
                $message = "Quiz set created successfully with {$totalQuestions} question(s)!";
                
                // Redirect after 2 seconds
                header('Refresh: 2; url=create_set.php');
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to create quiz set: ' . $e->getMessage();
            }
        }
    }
}

// Get selected courses and aircraft from GET or POST
$selectedCourses = [];
if (isset($_GET['course'])) {
    if (is_array($_GET['course'])) {
        $selectedCourses = $_GET['course'];
    } else {
        $selectedCourses = [$_GET['course']];
    }
}
$selectedAircraft = $_GET['aircraft'] ?? '';

// Get question counts per course (for all courses, filtered by aircraft if selected)
$courseQuestionCounts = [];
$countQuery = "SELECT course, COUNT(*) as count FROM questions WHERE 1=1";
$countParams = [];
if ($selectedAircraft) {
    $countQuery .= " AND aircraft = ?";
    $countParams[] = $selectedAircraft;
}
$countQuery .= " GROUP BY course";
$stmt = $db->prepare($countQuery);
$stmt->execute($countParams);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $courseQuestionCounts[$row['course']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz Set - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Quiz Set</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Create a new quiz set from questions</p>
                        </div>
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

                <form method="POST" action="" class="space-y-6">
                    <!-- Quiz Set Info -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quiz Set Information</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Quiz Set Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="name" name="name" required
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            </div>
                            
                            <div>
                                <label for="time_limit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Time Limit (minutes) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="time_limit" name="time_limit" required min="1"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo htmlspecialchars($_POST['time_limit'] ?? '60'); ?>">
                            </div>
                            
                            <div>
                                <label for="passing_score" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Passing Score (%) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="passing_score" name="passing_score" required min="0" max="100" step="0.01"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                       value="<?php echo htmlspecialchars($_POST['passing_score'] ?? '70'); ?>">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Description
                                </label>
                                <textarea id="description" name="description" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Filter Questions</h2>
                        
                        <div class="space-y-6">
                            <div>
                                <label for="aircraft" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Aircraft (Optional)
                                </label>
                                <select id="aircraft" name="aircraft"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                        onchange="updateCourseCounts()">
                                    <option value="">All Aircraft</option>
                                    <?php foreach ($aircrafts as $aircraft): ?>
                                        <option value="<?php echo htmlspecialchars($aircraft); ?>" <?php echo $selectedAircraft === $aircraft ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($aircraft); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Filter questions by aircraft type</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Course(s) <span class="text-xs text-gray-500">(Select multiple and specify question count for each)</span>
                                </label>
                                <div class="mb-3">
                                    <div class="relative">
                                        <input type="text" 
                                               id="course_search" 
                                               placeholder="Search courses..."
                                               class="w-full px-3 py-2 pl-10 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                               oninput="filterCourses()">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-search text-gray-400"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 max-h-80 overflow-y-auto p-3">
                                    <div class="space-y-2">
                                        <label class="flex items-center p-2 hover:bg-gray-50 dark:hover:bg-gray-600 rounded cursor-pointer bg-gray-50 dark:bg-gray-800">
                                            <input type="checkbox" id="select_all_courses" 
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                                   onchange="toggleAllCourses()">
                                            <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Select All Courses</span>
                                        </label>
                                        <div class="border-t border-gray-200 dark:border-gray-600 my-2"></div>
                                        <div id="courses_container">
                                        <?php foreach ($courses as $course): ?>
                                            <?php 
                                            $isSelected = in_array($course, $selectedCourses);
                                            $questionCount = $courseQuestionCounts[$course] ?? 0;
                                            ?>
                                            <div class="course-item p-3 rounded-lg border-2 transition-all <?php echo $isSelected ? 'border-blue-300 dark:border-blue-600 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'; ?>" 
                                                 data-course-name="<?php echo htmlspecialchars(strtolower($course)); ?>">
                                                <div class="flex items-center justify-between">
                                                    <label class="flex items-center flex-1 cursor-pointer">
                                                        <input type="checkbox" name="courses[]" value="<?php echo htmlspecialchars($course); ?>"
                                                               class="course-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                                               onchange="updateCourseCountInputs(); updateSelectAllState();"
                                                               data-course="<?php echo htmlspecialchars($course); ?>"
                                                               <?php echo $isSelected ? 'checked' : ''; ?>>
                                                        <div class="ml-3 flex-1">
                                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                                <?php echo htmlspecialchars($course); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                                <?php echo $questionCount; ?> question(s) available
                                                            </div>
                                                        </div>
                                                    </label>
                                                    <div class="ml-3 flex flex-col items-end space-y-1">
                                                        <div class="flex items-center space-x-2">
                                                            <input type="number" 
                                                                   name="course_counts[<?php echo htmlspecialchars($course); ?>]"
                                                                   id="course_count_<?php echo md5($course); ?>" 
                                                                   data-course="<?php echo htmlspecialchars($course); ?>"
                                                                   data-max="<?php echo $questionCount; ?>"
                                                                   min="0" 
                                                                   max="<?php echo $questionCount; ?>"
                                                                   placeholder="0"
                                                                   class="course-count-input w-16 px-2 py-1.5 text-sm border-2 border-blue-300 dark:border-blue-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-center font-medium"
                                                                   style="display: <?php echo $isSelected ? 'block' : 'none'; ?>;"
                                                                   onchange="updateCourseCountInputs(); validateCourseCount(this)"
                                                                   oninput="updateCourseCountInputs()">
                                                        </div>
                                                        <?php if ($isSelected && $questionCount > 0): ?>
                                                            <span class="text-xs text-blue-600 dark:text-blue-400 font-medium whitespace-nowrap">max: <?php echo $questionCount; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center justify-between">
                                    <p class="text-xs text-gray-500 dark:text-gray-400" id="selected_courses_count">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        <span id="selected_count"><?php echo count($selectedCourses); ?></span> course(s) selected
                                    </p>
                                    <p class="text-xs text-purple-600 dark:text-purple-400 font-medium" id="total_questions_preview" style="display: none;">
                                        <i class="fas fa-question-circle mr-1"></i>
                                        <span id="total_preview">0</span> questions will be selected
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Submit Button -->
                    <div class="flex justify-end space-x-4">
                        <button type="submit"
                                class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>
                            Create Quiz Set
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updateCourseCounts() {
            const aircraft = document.getElementById('aircraft').value;
            const courseCheckboxes = document.querySelectorAll('input[name="courses[]"]:checked');
            const selectedCourses = Array.from(courseCheckboxes).map(cb => cb.value);
            
            if (selectedCourses.length > 0 || aircraft) {
                // Reload page with filters to update question counts
                const params = new URLSearchParams();
                selectedCourses.forEach(course => {
                    params.append('course[]', course);
                });
                if (aircraft) params.append('aircraft', aircraft);
                
                window.location.href = 'create_set.php?' + params.toString();
            }
        }
        
        // Filter courses based on search
        function filterCourses() {
            const searchQuery = document.getElementById('course_search').value.toLowerCase().trim();
            const courseItems = document.querySelectorAll('.course-item');
            let visibleCount = 0;
            
            courseItems.forEach(item => {
                const courseName = item.getAttribute('data-course-name') || '';
                const matches = !searchQuery || courseName.includes(searchQuery);
                
                if (matches) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Update "Select All" to only affect visible courses
            updateSelectAllState();
        }
        
        // Update "Select All" checkbox state based on visible courses
        function updateSelectAllState() {
            // Get all visible checkboxes
            const allVisibleItems = Array.from(document.querySelectorAll('.course-item'))
                .filter(item => item.style.display !== 'none');
            const visibleCheckboxes = allVisibleItems
                .map(item => item.querySelector('.course-checkbox'))
                .filter(cb => cb !== null);
            
            const selectAll = document.getElementById('select_all_courses');
            if (visibleCheckboxes.length > 0 && selectAll) {
                const allChecked = visibleCheckboxes.every(cb => cb.checked);
                const someChecked = visibleCheckboxes.some(cb => cb.checked);
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            }
        }
        
        function toggleAllCourses() {
            const selectAll = document.getElementById('select_all_courses');
            // Only toggle visible courses
            const visibleItems = Array.from(document.querySelectorAll('.course-item'))
                .filter(item => item.style.display !== 'none');
            const visibleCheckboxes = visibleItems
                .map(item => item.querySelector('.course-checkbox'))
                .filter(cb => cb !== null);
            
            visibleCheckboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });
            
            updateSelectedCoursesCount();
            updateCourseCountInputs();
        }
        
        function updateSelectedCoursesCount() {
            const selectedCount = document.querySelectorAll('input[name="courses[]"]:checked').length;
            const countElement = document.getElementById('selected_count');
            if (countElement) {
                countElement.textContent = selectedCount;
            }
        }
        
        function updateCourseCountInputs() {
            const selectedCourses = document.querySelectorAll('input[name="courses[]"]:checked');
            let totalPreview = 0;
            
            selectedCourses.forEach(checkbox => {
                const course = checkbox.getAttribute('data-course');
                const courseItem = checkbox.closest('.course-item');
                const countInput = document.querySelector(`input.course-count-input[data-course="${course.replace(/"/g, '&quot;')}"]`);
                const maxSpan = courseItem ? courseItem.querySelector('.text-xs.text-blue-600, .text-xs.dark\\:text-blue-400') : null;
                
                if (countInput) {
                    countInput.style.display = 'block';
                    const count = parseInt(countInput.value) || 0;
                    totalPreview += count;
                    
                    // Show/hide max span
                    const max = parseInt(countInput.getAttribute('data-max')) || 0;
                    if (maxSpan) {
                        maxSpan.textContent = `max: ${max}`;
                        maxSpan.style.display = max > 0 ? 'block' : 'none';
                    } else if (max > 0) {
                        // Create max span if it doesn't exist
                        const maxSpanNew = document.createElement('span');
                        maxSpanNew.className = 'text-xs text-blue-600 dark:text-blue-400 font-medium whitespace-nowrap';
                        maxSpanNew.textContent = `max: ${max}`;
                        countInput.parentElement.appendChild(maxSpanNew);
                    }
                    
                    // Update course item styling
                    if (courseItem) {
                        courseItem.classList.add('border-blue-300', 'dark:border-blue-600', 'bg-blue-50', 'dark:bg-blue-900/20');
                        courseItem.classList.remove('border-gray-200', 'dark:border-gray-700');
                    }
                }
            });
            
            // Hide inputs for unselected courses and reset styling
            const unselectedCourses = document.querySelectorAll('input[name="courses[]"]:not(:checked)');
            unselectedCourses.forEach(checkbox => {
                const course = checkbox.getAttribute('data-course');
                const courseItem = checkbox.closest('.course-item');
                const countInput = document.querySelector(`input.course-count-input[data-course="${course.replace(/"/g, '&quot;')}"]`);
                const maxSpan = courseItem ? courseItem.querySelector('.text-xs.text-blue-600, .text-xs.dark\\:text-blue-400') : null;
                
                if (countInput) {
                    countInput.style.display = 'none';
                    countInput.value = '';
                }
                
                if (maxSpan) {
                    maxSpan.style.display = 'none';
                }
                
                if (courseItem) {
                    courseItem.classList.remove('border-blue-300', 'dark:border-blue-600', 'bg-blue-50', 'dark:bg-blue-900/20');
                    courseItem.classList.add('border-gray-200', 'dark:border-gray-700');
                }
            });
            
            // Update selected count
            updateSelectedCoursesCount();
            
            // Update total preview
            const previewDiv = document.getElementById('total_questions_preview');
            const previewSpan = document.getElementById('total_preview');
            if (previewDiv && previewSpan) {
                if (totalPreview > 0) {
                    previewSpan.textContent = totalPreview;
                    previewDiv.style.display = 'block';
                } else {
                    previewDiv.style.display = 'none';
                }
            }
        }
        
        function validateCourseCount(input) {
            const max = parseInt(input.getAttribute('data-max')) || 0;
            const value = parseInt(input.value) || 0;
            
            if (value > max) {
                input.value = max;
                alert(`Maximum ${max} questions available for this course.`);
            }
            if (value < 0) {
                input.value = 0;
            }
            
            updateCourseCountInputs();
        }
        
        // Initialize count on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCoursesCount();
            updateCourseCountInputs();
            updateSelectAllState();
            
            // Add event listener to course checkboxes to update select all state
            document.querySelectorAll('.course-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectAllState();
                });
            });
        });
    </script>
</body>
</html>

