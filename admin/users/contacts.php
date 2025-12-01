<?php
require_once '../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/users/contacts.php');

$current_user = getCurrentUser();
$message = '';
$error = '';

$db = getDBConnection();

// Handle search/filter
$searchFirstName = $_GET['first_name'] ?? '';
$searchLastName = $_GET['last_name'] ?? '';
$searchNationalId = $_GET['national_id'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if (!empty($searchFirstName)) {
    $whereConditions[] = "u.first_name LIKE ?";
    $params[] = '%' . $searchFirstName . '%';
}

if (!empty($searchLastName)) {
    $whereConditions[] = "u.last_name LIKE ?";
    $params[] = '%' . $searchLastName . '%';
}

if (!empty($searchNationalId)) {
    $whereConditions[] = "u.national_id LIKE ?";
    $params[] = '%' . $searchNationalId . '%';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get users with their data
$query = "
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.position,
        u.asic_number,
        u.national_id,
        u.date_of_birth,
        u.address_line_1,
        u.address_line_2,
        u.phone,
        u.mobile,
        u.alternative_mobile,
        u.email,
        u.passport_number,
        u.passport_nationality,
        u.passport_expiry_date,
        u.picture
    FROM users u
    $whereClause
    ORDER BY u.last_name ASC, u.first_name ASC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get personnel_docs data for each user
$personnelDocsMap = [];
if (!empty($users)) {
    $nationalIds = array_filter(array_column($users, 'national_id'));
    $nationalIds = array_values($nationalIds); // Reindex array to ensure sequential keys
    if (!empty($nationalIds)) {
        $placeholders = str_repeat('?,', count($nationalIds) - 1) . '?';
        $docsQuery = "SELECT * FROM personnel_docs WHERE national_id IN ($placeholders)";
        $docsStmt = $db->prepare($docsQuery);
        $docsStmt->execute($nationalIds);
        $personnelDocs = $docsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($personnelDocs as $doc) {
            $personnelDocsMap[$doc['national_id']] = $doc;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Contacts</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View and search user contact information</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <!-- Filter Section -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Search & Filter</h2>
                    </div>
                    <div class="px-6 py-4">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    First Name
                                </label>
                                <input type="text" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($searchFirstName); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Last Name
                                </label>
                                <input type="text" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($searchLastName); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label for="national_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    National ID
                                </label>
                                <input type="text" id="national_id" name="national_id" 
                                       value="<?php echo htmlspecialchars($searchNationalId); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" 
                                        class="w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                                    <i class="fas fa-search mr-2"></i>Search
                                </button>
                                <a href="contacts.php" 
                                   class="ml-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors duration-200">
                                    <i class="fas fa-redo"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Cards -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Contacts (<?php echo count($users); ?>)
                        </h2>
                    </div>
                    <div class="p-6">
                        <?php if (empty($users)): ?>
                            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                                <i class="fas fa-users text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg">No contacts found</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                                <?php foreach ($users as $user): 
                                    $personnelDoc = $personnelDocsMap[$user['national_id']] ?? null;
                                    $profileImageUrl = !empty($user['picture']) ? getProfileImageUrl($user['picture']) : null;
                                    $fullName = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                                    $position = htmlspecialchars($user['position'] ?? 'N/A');
                                    $mobile = htmlspecialchars($user['mobile'] ?? '-');
                                    $email = htmlspecialchars($user['email'] ?? '-');
                                    
                                    // Add profile image URL to user data for JavaScript
                                    $userForJS = $user;
                                    $userForJS['profile_image_url'] = $profileImageUrl;
                                ?>
                                    <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-700 dark:to-gray-800 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 border border-gray-200 dark:border-gray-600 overflow-hidden group">
                                        <!-- Card Header with Image -->
                                        <div class="relative bg-transparent dark:bg-gray-800 h-24 flex items-center justify-center">
                                            <?php if ($profileImageUrl): ?>
                                                <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" 
                                                     alt="<?php echo $fullName; ?>"
                                                     class="h-20 w-20 rounded-full object-cover border-4 border-white dark:border-gray-700 shadow-lg mt-8">
                                            <?php else: ?>
                                                <div class="h-20 w-20 rounded-full bg-white dark:bg-gray-700 border-4 border-white dark:border-gray-700 shadow-lg mt-8 flex items-center justify-center">
                                                    <i class="fas fa-user text-blue-600 dark:text-blue-400 text-3xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Card Body -->
                                        <div class="pt-12 pb-4 px-4">
                                            <h3 class="text-lg font-bold text-gray-900 dark:text-white text-center mb-1 truncate" title="<?php echo $fullName; ?>">
                                                <?php echo $fullName; ?>
                                            </h3>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-4 truncate" title="<?php echo $position; ?>">
                                                <i class="fas fa-briefcase mr-1"></i><?php echo $position; ?>
                                            </p>
                                            
                                            <!-- Quick Info -->
                                            <div class="space-y-2 mb-4">
                                                <?php if ($mobile && $mobile !== '-'): ?>
                                                    <div class="flex items-center text-sm text-gray-700 dark:text-gray-300">
                                                        <i class="fas fa-mobile-alt w-4 mr-2 text-blue-600 dark:text-blue-400"></i>
                                                        <span class="truncate"><?php echo $mobile; ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($email && $email !== '-'): ?>
                                                    <div class="flex items-center text-sm text-gray-700 dark:text-gray-300">
                                                        <i class="fas fa-envelope w-4 mr-2 text-blue-600 dark:text-blue-400"></i>
                                                        <span class="truncate text-xs"><?php echo $email; ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Action Buttons -->
                                            <div class="flex gap-2">
                                                <button onclick="viewUserDetails(<?php echo htmlspecialchars(json_encode($userForJS), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($personnelDoc), ENT_QUOTES); ?>)" 
                                                        class="w-full px-3 py-2 text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md">
                                                    <i class="fas fa-eye mr-1"></i>View More
                                                </button>
                                            </div>
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

    <!-- User Details Modal -->
    <div id="userDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-4xl shadow-xl rounded-lg bg-white dark:bg-gray-800 mb-10">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Contact Details</h3>
                    <button onclick="closeUserDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div id="userDetailsContent" class="space-y-6">
                    <!-- Content will be populated by JavaScript -->
                </div>
                
                <div class="flex justify-end mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <button onclick="closeUserDetailsModal()"
                            class="px-6 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewUserDetails(user, personnelDoc) {
            const content = document.getElementById('userDetailsContent');
            const profileImageUrl = user.profile_image_url || null;
            
            let html = `
                <!-- Profile Header -->
                <div class="flex items-center space-x-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex-shrink-0">
                        ${profileImageUrl ? 
                            `<img src="${profileImageUrl}" alt="${user.first_name} ${user.last_name}" class="h-24 w-24 rounded-full object-cover border-4 border-blue-500 shadow-lg">` :
                            `<div class="h-24 w-24 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 border-4 border-blue-500 shadow-lg flex items-center justify-center">
                                <i class="fas fa-user text-white text-4xl"></i>
                            </div>`
                        }
                    </div>
                    <div class="flex-1">
                        <h4 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                            ${user.first_name} ${user.last_name}
                        </h4>
                        <p class="text-lg text-gray-600 dark:text-gray-400 mb-2">
                            <i class="fas fa-briefcase mr-2"></i>${user.position || 'N/A'}
                        </p>
                        <div class="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400">
                            ${user.mobile && user.mobile !== '-' ? `<span><i class="fas fa-mobile-alt mr-1"></i>${user.mobile}</span>` : ''}
                            ${user.email && user.email !== '-' ? `<span><i class="fas fa-envelope mr-1"></i>${user.email}</span>` : ''}
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Personal Information -->
                    <div class="space-y-4">
                        <h5 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                            <i class="fas fa-user-circle mr-2 text-blue-600 dark:text-blue-400"></i>Personal Information
                        </h5>
                        <div class="space-y-3">
                            ${user.national_id ? `
                                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">National ID:</span>
                                    <span class="text-sm text-gray-900 dark:text-white">${user.national_id}</span>
                                </div>
                            ` : ''}
                            ${user.asic_number ? `
                                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">ASIC Number:</span>
                                    <span class="text-sm text-gray-900 dark:text-white">${user.asic_number}</span>
                                </div>
                            ` : ''}
                            ${user.date_of_birth ? `
                                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Date of Birth:</span>
                                    <span class="text-sm text-gray-900 dark:text-white">${new Date(user.date_of_birth).toLocaleDateString()}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- Contact Details -->
                    <div class="space-y-4">
                        <h5 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                            <i class="fas fa-address-book mr-2 text-blue-600 dark:text-blue-400"></i>Contact Details
                        </h5>
                        <div class="space-y-3">
                            ${user.phone && user.phone !== '-' ? `
                                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Phone:</span>
                                    <span class="text-sm text-gray-900 dark:text-white">${user.phone}</span>
                                </div>
                            ` : ''}
                            ${user.mobile && user.mobile !== '-' ? `
                                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Mobile:</span>
                                    <span class="text-sm text-gray-900 dark:text-white">${user.mobile}</span>
                                </div>
                            ` : ''}
                            ${user.alternative_mobile && user.alternative_mobile !== '-' ? `
                                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Alternative Mobile:</span>
                                    <span class="text-sm text-gray-900 dark:text-white">${user.alternative_mobile}</span>
                                </div>
                            ` : ''}
                            ${user.email && user.email !== '-' ? `
                                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Email:</span>
                                    <span class="text-sm text-gray-900 dark:text-white break-all">${user.email}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
                
                <!-- Address Information -->
                ${(user.address_line_1 || user.address_line_2) ? `
                    <div class="space-y-4">
                        <h5 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                            <i class="fas fa-map-marker-alt mr-2 text-blue-600 dark:text-blue-400"></i>Address
                        </h5>
                        <div class="space-y-3">
                            ${user.address_line_1 ? `
                                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Address Line 1:</span>
                                    <span class="text-sm text-gray-900 dark:text-white">${user.address_line_1}</span>
                                </div>
                            ` : ''}
                            ${user.address_line_2 ? `
                                <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Address Line 2:</span>
                                    <span class="text-sm text-gray-900 dark:text-white">${user.address_line_2}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                ` : ''}
                
                <!-- Passport Information -->
                ${(user.passport_number || user.passport_nationality || user.passport_expiry_date) ? `
                    <div class="space-y-4">
                        <h5 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                            <i class="fas fa-passport mr-2 text-blue-600 dark:text-blue-400"></i>Passport Information
                        </h5>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            ${user.passport_number ? `
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Passport Number</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">${user.passport_number}</p>
                                </div>
                            ` : ''}
                            ${user.passport_nationality ? `
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Nationality</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">${user.passport_nationality}</p>
                                </div>
                            ` : ''}
                            ${user.passport_expiry_date ? `
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Expiry Date</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">${new Date(user.passport_expiry_date).toLocaleDateString()}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                ` : ''}
            `;
            
            content.innerHTML = html;
            document.getElementById('userDetailsModal').classList.remove('hidden');
        }
        
        function closeUserDetailsModal() {
            document.getElementById('userDetailsModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const detailsModal = document.getElementById('userDetailsModal');
            if (event.target === detailsModal) {
                closeUserDetailsModal();
            }
        }
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeUserDetailsModal();
            }
        });
    </script>
</body>
</html>

