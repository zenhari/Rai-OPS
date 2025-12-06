<?php
require_once '../../../config.php';

// Check access
checkPageAccessWithRedirect('admin/training/quiz/assign_quiz.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

$db = getDBConnection();

// Get all quiz sets
$stmt = $db->query("SELECT qs.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                    (SELECT COUNT(*) FROM quiz_set_questions WHERE quiz_set_id = qs.id) as question_count
                    FROM quiz_sets qs
                    LEFT JOIN users u ON qs.created_by = u.id
                    ORDER BY qs.created_at DESC");
$quizSets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active users with role information
$stmt = $db->query("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name, u.position, u.email, u.role_id,
                    r.display_name as role_display_name, r.name as role_name
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.status = 'active' 
                    ORDER BY u.first_name, u.last_name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active roles
$stmt = $db->query("SELECT id, name, display_name FROM roles WHERE is_active = 1 ORDER BY display_name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $quizSetId = intval($_POST['quiz_set_id'] ?? 0);
    $userIds = $_POST['user_ids'] ?? [];
    $roleId = !empty($_POST['role_id']) ? intval($_POST['role_id']) : null;
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    
    if (empty($quizSetId)) {
        $error = 'Please select a quiz set.';
    } elseif (empty($startDate)) {
        $error = 'Please select a start date.';
    } elseif (empty($userIds) && empty($roleId)) {
        $error = 'Please select at least one user or a role.';
    } else {
        try {
            $db->beginTransaction();
            
            // If role is selected, get all users with that role
            if ($roleId) {
                $stmt = $db->prepare("SELECT id FROM users WHERE role_id = ? AND status = 'active'");
                $stmt->execute([$roleId]);
                $roleUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
                // Merge with manually selected users (avoid duplicates)
                $userIds = array_unique(array_merge($userIds, $roleUsers));
            }
            
            if (empty($userIds)) {
                $error = 'No users found for the selected role.';
                $db->rollBack();
            } else {
                $stmt = $db->prepare("INSERT INTO quiz_assignments (quiz_set_id, user_id, assigned_by, due_date) VALUES (?, ?, ?, ?)");
                
                $assignedCount = 0;
                foreach ($userIds as $userId) {
                    $userId = intval($userId);
                    // Check if assignment already exists
                    $checkStmt = $db->prepare("SELECT id FROM quiz_assignments WHERE quiz_set_id = ? AND user_id = ? AND status != 'completed'");
                    $checkStmt->execute([$quizSetId, $userId]);
                    
                    if (!$checkStmt->fetch()) {
                        $stmt->execute([$quizSetId, $userId, $current_user['id'], $startDate]);
                        $assignedCount++;
                    }
                }
                
                $db->commit();
                
                if ($assignedCount > 0) {
                    $message = "Quiz assigned successfully to {$assignedCount} user(s)!";
                } else {
                    $error = 'All selected users already have this quiz assigned.';
                }
            }
            
            // Redirect after 2 seconds
            header('Refresh: 2; url=assign_quiz.php');
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to assign quiz: ' . $e->getMessage();
        }
    }
}

// Get existing assignments
$stmt = $db->query("SELECT qa.*, 
                    qs.name as quiz_set_name,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    CONCAT(assigned_by_user.first_name, ' ', assigned_by_user.last_name) as assigned_by_name
                    FROM quiz_assignments qa
                    LEFT JOIN quiz_sets qs ON qa.quiz_set_id = qs.id
                    LEFT JOIN users u ON qa.user_id = u.id
                    LEFT JOIN users assigned_by_user ON qa.assigned_by = assigned_by_user.id
                    ORDER BY qa.assigned_at DESC
                    LIMIT 50");
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Quiz - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Assign Quiz</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Assign quiz sets to users</p>
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

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Assign Form -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Assign Quiz to Users</h2>
                        
                        <form method="POST" action="" class="space-y-6">
                            <div>
                                <label for="quiz_set_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Quiz Set <span class="text-red-500">*</span>
                                </label>
                                <select id="quiz_set_id" name="quiz_set_id" required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">-- Select Quiz Set --</option>
                                    <?php foreach ($quizSets as $quizSet): ?>
                                        <option value="<?php echo $quizSet['id']; ?>">
                                            <?php echo htmlspecialchars($quizSet['name']); ?> 
                                            (<?php echo $quizSet['question_count']; ?> questions, 
                                            <?php echo $quizSet['time_limit']; ?> min, 
                                            Pass: <?php echo $quizSet['passing_score']; ?>%)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Assign to Role (Optional)
                                </label>
                                <select id="role_id" name="role_id"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                        onchange="handleRoleSelection()">
                                    <option value="">-- Select Role (Optional) --</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" data-display-name="<?php echo htmlspecialchars($role['display_name']); ?>">
                                            <?php echo htmlspecialchars($role['display_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Selecting a role will assign the quiz to all users with that role.
                                </p>
                            </div>
                            
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <label for="user_search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Or Select Individual Users
                                    <span id="selected_count" class="text-xs text-blue-600 dark:text-blue-400 ml-2">(0 selected)</span>
                                </label>
                                <div class="relative">
                                    <input type="text" id="user_search" placeholder="Type to search users..."
                                           class="w-full px-3 py-2 pl-10 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                           oninput="searchUsers()"
                                           onfocus="showSearchResults()">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-search text-gray-400"></i>
                                    </div>
                                </div>
                                <div id="user_search_results" class="mt-2 max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 hidden shadow-lg">
                                    <div id="search_results_header" class="sticky top-0 bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600 p-2 flex items-center justify-between z-10">
                                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300" id="results_count">0 results</span>
                                        <button type="button" onclick="selectAllVisible()" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">
                                            <i class="fas fa-check-double mr-1"></i>Select All
                                        </button>
                                    </div>
                                    <div id="search_results_content">
                                        <!-- Search results will be populated here -->
                                    </div>
                                </div>
                                <div id="selected_users" class="mt-3 space-y-2 max-h-48 overflow-y-auto">
                                    <!-- Selected users will be displayed here -->
                                </div>
                            </div>
                            
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Start Date <span class="text-red-500">*</span>
                                </label>
                                <input type="datetime-local" id="start_date" name="start_date" required
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Users will not be able to see or start the quiz before this date.
                                </p>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-user-check mr-2"></i>
                                    Assign Quiz
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Recent Assignments -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Assignments</h2>
                        
                        <?php if (empty($assignments)): ?>
                            <p class="text-gray-500 dark:text-gray-400 text-center py-8">
                                No assignments found.
                            </p>
                        <?php else: ?>
                            <div class="space-y-4 max-h-96 overflow-y-auto">
                                <?php foreach ($assignments as $assignment): ?>
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <h3 class="font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($assignment['quiz_set_name']); ?>
                                                </h3>
                                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                    <i class="fas fa-user mr-1"></i>
                                                    <?php echo htmlspecialchars($assignment['user_name']); ?>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                                    Assigned by: <?php echo htmlspecialchars($assignment['assigned_by_name']); ?>
                                                    on <?php echo date('M j, Y g:i A', strtotime($assignment['assigned_at'])); ?>
                                                </p>
                                                <?php if ($assignment['due_date']): ?>
                                                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                        <i class="fas fa-calendar-alt mr-1"></i>
                                                        Start: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?>
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
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const allUsers = <?php echo json_encode($users); ?>;
        const selectedUsers = [];
        let currentFilteredUsers = [];
        let selectedRoleId = null;
        
        function handleRoleSelection() {
            const roleSelect = document.getElementById('role_id');
            const roleId = roleSelect.value;
            selectedRoleId = roleId;
            
            if (roleId) {
                const displayName = roleSelect.options[roleSelect.selectedIndex].dataset.displayName;
                // Get all users with this role
                const roleUsers = allUsers.filter(user => user.role_id == roleId);
                
                // Add all role users to selected list (avoid duplicates)
                roleUsers.forEach(user => {
                    const exists = selectedUsers.some(u => u.id === user.id);
                    if (!exists) {
                        selectedUsers.push({
                            id: user.id,
                            name: user.name,
                            position: user.position || 'N/A'
                        });
                    }
                });
                
                updateSelectedUsersDisplay();
                searchUsers();
                
                // Show notification
                if (roleUsers.length > 0) {
                    const notification = document.createElement('div');
                    notification.className = 'mt-2 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-md p-3';
                    notification.innerHTML = `
                        <div class="flex items-center">
                            <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mr-2"></i>
                            <span class="text-sm text-blue-800 dark:text-blue-200">
                                Added ${roleUsers.length} user(s) from role: <strong>${displayName}</strong>
                            </span>
                        </div>
                    `;
                    
                    // Remove existing notification if any
                    const existingNotification = document.querySelector('.role-selection-notification');
                    if (existingNotification) {
                        existingNotification.remove();
                    }
                    
                    notification.classList.add('role-selection-notification');
                    roleSelect.parentElement.appendChild(notification);
                    
                    // Remove notification after 5 seconds
                    setTimeout(() => {
                        notification.remove();
                    }, 5000);
                }
            } else {
                // Remove notification if role is deselected
                const notification = document.querySelector('.role-selection-notification');
                if (notification) {
                    notification.remove();
                }
            }
        }
        
        function showSearchResults() {
            const query = document.getElementById('user_search').value.toLowerCase().trim();
            if (query.length >= 1 || allUsers.length <= 20) {
                searchUsers();
            }
        }
        
        function searchUsers() {
            const query = document.getElementById('user_search').value.toLowerCase().trim();
            const resultsDiv = document.getElementById('user_search_results');
            const resultsContent = document.getElementById('search_results_content');
            const resultsCount = document.getElementById('results_count');
            
            // Filter users
            if (query.length === 0) {
                // Show all users if search is empty (limit to 50 for performance)
                currentFilteredUsers = allUsers.slice(0, 50);
            } else {
                currentFilteredUsers = allUsers.filter(user => {
                    const name = user.name.toLowerCase();
                    const position = (user.position || '').toLowerCase();
                    const email = (user.email || '').toLowerCase();
                    
                    return name.includes(query) || position.includes(query) || email.includes(query);
                });
            }
            
            if (currentFilteredUsers.length === 0) {
                resultsContent.innerHTML = '<div class="p-3 text-sm text-gray-500 dark:text-gray-400 text-center">No users found</div>';
                resultsCount.textContent = '0 results';
            } else {
                resultsContent.innerHTML = currentFilteredUsers.map(user => {
                    const isSelected = selectedUsers.some(u => u.id === user.id);
                    return `
                        <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0 ${isSelected ? 'bg-blue-50 dark:bg-blue-900' : ''}" 
                             onclick="toggleUserSelection(${user.id}, '${user.name.replace(/'/g, "\\'")}', '${(user.position || 'N/A').replace(/'/g, "\\'")}')">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 mr-3">
                                    <input type="checkbox" 
                                           ${isSelected ? 'checked' : ''}
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer"
                                           onclick="event.stopPropagation(); toggleUserSelection(${user.id}, '${user.name.replace(/'/g, "\\'")}', '${(user.position || 'N/A').replace(/'/g, "\\'")}')">
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900 dark:text-white flex items-center">
                                        ${user.name}
                                        ${isSelected ? '<i class="fas fa-check-circle text-green-600 ml-2 text-sm"></i>' : ''}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">${user.position || 'N/A'}</div>
                                    ${user.email ? `<div class="text-xs text-gray-400 dark:text-gray-500">${user.email}</div>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
                resultsCount.textContent = `${currentFilteredUsers.length} result${currentFilteredUsers.length !== 1 ? 's' : ''}`;
            }
            
            resultsDiv.classList.remove('hidden');
        }
        
        function toggleUserSelection(userId, userName, position) {
            const index = selectedUsers.findIndex(u => u.id === userId);
            
            if (index > -1) {
                // Remove if already selected
                selectedUsers.splice(index, 1);
            } else {
                // Add if not selected
                selectedUsers.push({ id: userId, name: userName, position: position });
            }
            
            updateSelectedUsersDisplay();
            // Refresh search results to update checkboxes
            searchUsers();
        }
        
        function selectAllVisible() {
            currentFilteredUsers.forEach(user => {
                const index = selectedUsers.findIndex(u => u.id === user.id);
                if (index === -1) {
                    selectedUsers.push({ 
                        id: user.id, 
                        name: user.name, 
                        position: user.position || 'N/A' 
                    });
                }
            });
            
            updateSelectedUsersDisplay();
            searchUsers();
        }
        
        function removeUser(userId) {
            const index = selectedUsers.findIndex(u => u.id === userId);
            if (index > -1) {
                selectedUsers.splice(index, 1);
                updateSelectedUsersDisplay();
                // Refresh search results to update checkboxes
                searchUsers();
            }
        }
        
        function updateSelectedUsersDisplay() {
            const displayDiv = document.getElementById('selected_users');
            const countSpan = document.getElementById('selected_count');
            
            // Update count
            countSpan.textContent = `(${selectedUsers.length} selected)`;
            
            // Add hidden inputs for form submission
            const form = document.querySelector('form');
            // Remove old hidden inputs
            document.querySelectorAll('input[name="user_ids[]"]').forEach(input => input.remove());
            
            // Add new hidden inputs
            selectedUsers.forEach(user => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = user.id;
                form.appendChild(input);
            });
            
            if (selectedUsers.length === 0) {
                displayDiv.innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400 italic">No users selected. Search and select users above.</p>';
            } else {
                displayDiv.innerHTML = `
                    <div class="mb-2">
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Selected Users (${selectedUsers.length}):</p>
                    </div>
                    ${selectedUsers.map(user => `
                        <div class="flex items-center justify-between p-2 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-md">
                            <div class="flex items-center">
                                <i class="fas fa-user-check text-blue-600 dark:text-blue-400 mr-2"></i>
                                <div>
                                    <span class="text-sm font-medium text-blue-900 dark:text-blue-200">${user.name}</span>
                                    <span class="text-xs text-blue-700 dark:text-blue-300 ml-2">${user.position}</span>
                                </div>
                            </div>
                            <button type="button" onclick="removeUser(${user.id})" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 ml-2" title="Remove">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `).join('')}
                `;
            }
        }
        
        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            const searchInput = document.getElementById('user_search');
            const resultsDiv = document.getElementById('user_search_results');
            
            if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
                // Don't hide if clicking on selected users area
                const selectedDiv = document.getElementById('selected_users');
                if (!selectedDiv.contains(e.target)) {
                    resultsDiv.classList.add('hidden');
                }
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const roleId = document.getElementById('role_id').value;
            const hasSelectedUsers = selectedUsers.length > 0;
            
            if (!roleId && !hasSelectedUsers) {
                e.preventDefault();
                alert('Please select either a role or at least one individual user.');
                return false;
            }
        });
        
        // Initialize display
        updateSelectedUsersDisplay();
        
        // Show all users on page load if there are not too many
        if (allUsers.length <= 20) {
            setTimeout(() => {
                showSearchResults();
            }, 100);
        }
    </script>
</body>
</html>

