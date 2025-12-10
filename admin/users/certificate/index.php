<?php
require_once '../../../config.php';

// Check access at the very top of the file
checkPageAccessWithRedirect('admin/users/certificate/index.php');

$current_user = getCurrentUser();
$message = '';
$error = '';
$certificateData = null;
$totalRecords = 0;
$currentPage = 1;
$recordsPerPage = 100;
$totalPages = 0;

// Check delete permission
$canDelete = hasPageAccess('admin/users/certificate/delete');

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_certificate') {
    if (!$canDelete) {
        $error = 'You do not have permission to delete certificates.';
    } else {
        $certificateId = intval($_POST['certificate_id'] ?? 0);
        if ($certificateId > 0) {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("DELETE FROM certificates WHERE id = ?");
                if ($stmt->execute([$certificateId])) {
                    // Redirect to preserve filters and show success message
                    $redirectParams = [];
                    if (!empty($_GET['nationalid'])) $redirectParams['nationalid'] = $_GET['nationalid'];
                    if (!empty($_GET['mobile'])) $redirectParams['mobile'] = $_GET['mobile'];
                    if (!empty($_GET['coursename'])) $redirectParams['coursename'] = $_GET['coursename'];
                    if (!empty($_GET['page'])) $redirectParams['page'] = $_GET['page'];
                    $redirectParams['deleted'] = '1';
                    
                    $redirectUrl = '?' . http_build_query($redirectParams);
                    header('Location: ' . $redirectUrl);
                    exit();
                } else {
                    $error = 'Failed to delete certificate.';
                }
            } catch (Exception $e) {
                error_log("Error deleting certificate: " . $e->getMessage());
                $error = 'An error occurred while deleting the certificate.';
            }
        } else {
            $error = 'Invalid certificate ID.';
        }
    }
}

// Show success message if redirected after delete
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $message = 'Certificate deleted successfully.';
}

// Handle form submission and pagination
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $currentPage = max(1, intval($_GET['page'] ?? 1));
    $nationalId = trim($_GET['nationalid'] ?? '');
    $mobile = trim($_GET['mobile'] ?? '');
    $courseName = trim($_GET['coursename'] ?? '');
    
    // Handle refresh request (no longer needed with database, but kept for compatibility)
    if (isset($_GET['refresh']) && $_GET['refresh'] == '1') {
        $message = 'Data refreshed successfully.';
    }
    
    // Fetch certificate data from database
    $db = getDBConnection();
    
    // Build WHERE clause for filters
    $whereConditions = [];
    $params = [];
    
    if (!empty($nationalId)) {
        $whereConditions[] = "nationalid LIKE :nationalid";
        $params[':nationalid'] = "%{$nationalId}%";
    }
    
    if (!empty($mobile)) {
        $whereConditions[] = "mobile LIKE :mobile";
        $params[':mobile'] = "%{$mobile}%";
    }
    
    if (!empty($courseName)) {
        $whereConditions[] = "coursename LIKE :coursename";
        $params[':coursename'] = "%{$courseName}%";
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM certificates {$whereClause}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalRecords = intval($countStmt->fetch(PDO::FETCH_ASSOC)['total']);
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    // Get paginated data
    $offset = ($currentPage - 1) * $recordsPerPage;
    $sql = "SELECT * FROM certificates {$whereClause} ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    
    // Bind filter parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    
    // Bind limit and offset
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($totalRecords == 0) {
        $message = 'No certificate data found for the selected filters.';
    } else {
        $message = "Found {$totalRecords} certificate(s). Showing page {$currentPage} of {$totalPages}.";
    }
    
    $certificateData = [
        'status' => 'ok',
        'count' => $totalRecords,
        'data' => $certificates
    ];
}

function getCourseTypes() {
    global $db;
    if (!isset($db)) {
        $db = getDBConnection();
    }
    
    try {
        $stmt = $db->query("SELECT DISTINCT coursename FROM certificates WHERE coursename IS NOT NULL AND coursename != '' ORDER BY coursename");
        $courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_combine($courses, $courses);
    } catch (PDOException $e) {
        error_log("Error fetching course types: " . $e->getMessage());
        return [];
    }
}

function safeOutput($value) {
    return htmlspecialchars($value ?? '');
}

function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('M j, Y', strtotime($date));
}

?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Management - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Certificate Management</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage and view training certificates from external API</p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="../" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Recency
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../../includes/permission_banner.php'; ?>
                
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

                <!-- Filter Form -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Filter Certificates</h2>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="nationalid" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">National ID</label>
                            <input type="text" id="nationalid" name="nationalid" value="<?php echo safeOutput($_GET['nationalid'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="Enter National ID">
                        </div>
                        <div>
                            <label for="mobile" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mobile Number</label>
                            <input type="text" id="mobile" name="mobile" value="<?php echo safeOutput($_GET['mobile'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="Enter Mobile Number">
                        </div>
                        <div>
                            <label for="coursename" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Course Name</label>
                            <select id="coursename" name="coursename" 
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">-- All Courses --</option>
                                <?php foreach (getCourseTypes() as $key => $value): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" 
                                            <?php echo (($_GET['coursename'] ?? '') === $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-3 flex justify-end space-x-3">
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-search mr-2"></i>
                                Search Certificates
                            </button>
                            <a href="?" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <i class="fas fa-refresh mr-2"></i>
                                Clear Filters
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Certificate Data Display -->
                <?php if ($certificateData): ?>
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-certificate text-blue-500 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Certificates</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo number_format($totalRecords); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-list text-green-500 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Page</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $currentPage; ?> of <?php echo $totalPages; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-users text-purple-500 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Records Per Page</p>
                                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $recordsPerPage; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Certificate Data Table -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">Certificate Details</h2>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Training certificates and course completion records</p>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="exportCertificateData()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                        <i class="fas fa-download mr-2"></i>
                                        Export
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Image</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Certificate No</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">National ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mobile</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Course</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Issue Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Expire Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (empty($certificateData['data'])): ?>
                                        <tr>
                                            <td colspan="9" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                No certificate data found for the selected filters.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($certificateData['data'] as $certificate): ?>
                                            <?php 
                                            $certificateImageUrl = base_url() . "admin/users/certificate/cer/" . safeOutput($certificate['certificateno']) . ".jpg";
                                            ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="flex justify-center">
                                                        <img src="<?php echo $certificateImageUrl; ?>" 
                                                             alt="Certificate <?php echo safeOutput($certificate['certificateno']); ?>"
                                                             class="w-12 h-12 object-cover rounded-lg border border-gray-200 dark:border-gray-600 cursor-pointer hover:shadow-md transition-shadow duration-200"
                                                             onclick="openImageModal('<?php echo $certificateImageUrl; ?>', '<?php echo safeOutput($certificate['certificateno']); ?>')"
                                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                        <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 flex items-center justify-center" style="display: none;">
                                                            <i class="fas fa-image text-gray-400 text-sm"></i>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo safeOutput($certificate['certificateno']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0">
                                                            <i class="fas fa-user text-gray-400"></i>
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                                <?php echo safeOutput($certificate['name']); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                <?php echo safeOutput($certificate['email']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo safeOutput($certificate['nationalid']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo safeOutput($certificate['mobile']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo safeOutput($certificate['coursename']); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                                        Duration: <?php echo safeOutput($certificate['courseduration']); ?> days
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo formatDate($certificate['issue_date']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo formatDate($certificate['expire_date']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button onclick="openCertificateDetailsModal('<?php echo htmlspecialchars(json_encode($certificate), ENT_QUOTES); ?>')" 
                                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button onclick="printCertificate('<?php echo htmlspecialchars(json_encode($certificate), ENT_QUOTES); ?>')" 
                                                            class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 mr-3" title="Print">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <?php if ($canDelete): ?>
                                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this certificate? This action cannot be undone.');">
                                                        <input type="hidden" name="action" value="delete_certificate">
                                                        <input type="hidden" name="certificate_id" value="<?php echo intval($certificate['id']); ?>">
                                                        <button type="submit" 
                                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" 
                                                                title="Delete Certificate">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 mt-6">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing page <?php echo $currentPage; ?> of <?php echo $totalPages; ?> 
                                    (<?php echo number_format($totalRecords); ?> total records)
                                </div>
                                <div class="flex space-x-2">
                                    <?php if ($currentPage > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>" 
                                           class="px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <i class="fas fa-chevron-left mr-1"></i>
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($totalPages, $currentPage + 2);
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                           class="px-3 py-2 text-sm font-medium <?php echo $i === $currentPage ? 'text-white bg-blue-600 border-blue-600' : 'text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600'; ?> rounded-md">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($currentPage < $totalPages): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>" 
                                           class="px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">
                                            Next
                                            <i class="fas fa-chevron-right ml-1"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- No Data State -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-12 text-center">
                        <i class="fas fa-certificate text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Certificate Data</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">Use the filters above to search for certificates or click "Search Certificates" to load all data.</p>
                        <div class="text-sm text-gray-400 dark:text-gray-500">
                            <p>Certificate data is loaded from the local database.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Certificate Details Modal -->
    <div id="certificateDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Certificate Details</h3>
                    <button onclick="closeCertificateDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div id="certificateDetailsContent" class="space-y-6">
                    <!-- Content will be populated by JavaScript -->
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button onclick="closeCertificateDetailsModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Close
                    </button>
                    <button onclick="printCertificateFromModal()"
                            class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200">
                        <i class="fas fa-print mr-2"></i>
                        Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentCertificate = null;

        function openCertificateDetailsModal(certificateData) {
            try {
                currentCertificate = JSON.parse(certificateData);
                populateCertificateDetailsModal(currentCertificate);
                document.getElementById('certificateDetailsModal').classList.remove('hidden');
            } catch (error) {
                console.error('Error opening certificate details modal:', error);
                alert('Error opening certificate details. Please try again.');
            }
        }

        function closeCertificateDetailsModal() {
            document.getElementById('certificateDetailsModal').classList.add('hidden');
            currentCertificate = null;
        }

        function populateCertificateDetailsModal(certificate) {
            const content = document.getElementById('certificateDetailsContent');
            
            // Generate certificate image URL
            const certificateImageUrl = `<?php echo base_url(); ?>admin/users/certificate/cer/${certificate.certificateno}.jpg`;
            
            const details = `
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Personal Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Full Name</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${(certificate.name || 'N/A').toUpperCase()}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">National ID</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.nationalid || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Email</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.email || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Mobile</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.mobile || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Birthday</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.birthday ? new Date(certificate.birthday).toLocaleDateString() : 'N/A'}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Certificate Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Certificate Number</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.certificateno || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Course Name</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.coursename || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Course Duration</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.courseduration || 'N/A'} days</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Start Date</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.start_date ? new Date(certificate.start_date).toLocaleDateString() : 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">End Date</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.end_date ? new Date(certificate.end_date).toLocaleDateString() : 'N/A'}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Dates & Authorization</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Issue Date</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.issue_date ? new Date(certificate.issue_date).toLocaleDateString() : 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Expire Date</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.expire_date ? new Date(certificate.expire_date).toLocaleDateString() : 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Certificate Type</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.certificate_type || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Issuance Authority</label>
                                    <p class="text-sm text-gray-900 dark:text-white">${certificate.issuance_auth || 'N/A'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="lg:col-span-1">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Certificate Image</h4>
                            <div class="text-center">
                                <div id="certificate-image-container" class="relative">
                                    <img id="certificate-image" 
                                         src="${certificateImageUrl}" 
                                         alt="Certificate ${certificate.certificateno || 'N/A'}"
                                         class="w-full h-auto rounded-lg shadow-lg border border-gray-200 dark:border-gray-600 cursor-pointer hover:shadow-xl transition-shadow duration-200"
                                         onclick="openImageModal('${certificateImageUrl}', '${certificate.certificateno || 'N/A'}')"
                                         onerror="handleImageError(this)">
                                    <div id="image-loading" class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-800 rounded-lg">
                                        <div class="text-center">
                                            <i class="fas fa-spinner fa-spin text-2xl text-gray-400 mb-2"></i>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Loading image...</p>
                                        </div>
                                    </div>
                                    <div id="image-error" class="hidden absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-800 rounded-lg">
                                        <div class="text-center">
                                            <i class="fas fa-image text-4xl text-gray-300 dark:text-gray-600 mb-2"></i>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Image not available</p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Certificate: ${certificate.certificateno || 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button onclick="openImageModal('${certificateImageUrl}', '${certificate.certificateno || 'N/A'}')" 
                                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                        <i class="fas fa-expand mr-2"></i>
                                        View Full Size
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            content.innerHTML = details;
            
            // Hide loading indicator when image loads
            const img = document.getElementById('certificate-image');
            if (img) {
                img.onload = function() {
                    const loading = document.getElementById('image-loading');
                    if (loading) loading.style.display = 'none';
                };
            }
        }

        function printCertificate(certificateData) {
            try {
                const certificate = JSON.parse(certificateData);
                printCertificateFromModal(certificate);
            } catch (error) {
                console.error('Error printing certificate:', error);
                alert('Error printing certificate. Please try again.');
            }
        }

        function printCertificateFromModal(certificate = null) {
            const certificateToPrint = certificate || currentCertificate;
            if (!certificateToPrint) return;
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Certificate - ${certificateToPrint.certificateno}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                        .section { margin-bottom: 20px; }
                        .section h3 { background-color: #f5f5f5; padding: 10px; margin: 0 0 10px 0; }
                        .field { margin-bottom: 10px; }
                        .field label { font-weight: bold; }
                        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Certificate Details</h1>
                        <p><strong>Certificate No:</strong> ${certificateToPrint.certificateno} | <strong>Name:</strong> ${(certificateToPrint.name || '').toUpperCase()} | <strong>Course:</strong> ${certificateToPrint.coursename}</p>
                    </div>
                    
                    <div class="section">
                        <h3>Personal Information</h3>
                        <div class="grid">
                            <div class="field">
                                <label>Full Name:</label> ${(certificateToPrint.name || 'N/A').toUpperCase()}
                            </div>
                            <div class="field">
                                <label>National ID:</label> ${certificateToPrint.nationalid || 'N/A'}
                            </div>
                            <div class="field">
                                <label>Email:</label> ${certificateToPrint.email || 'N/A'}
                            </div>
                            <div class="field">
                                <label>Mobile:</label> ${certificateToPrint.mobile || 'N/A'}
                            </div>
                            <div class="field">
                                <label>Birthday:</label> ${certificateToPrint.birthday || 'N/A'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h3>Certificate Information</h3>
                        <div class="grid">
                            <div class="field">
                                <label>Certificate Number:</label> ${certificateToPrint.certificateno || 'N/A'}
                            </div>
                            <div class="field">
                                <label>Course Name:</label> ${certificateToPrint.coursename || 'N/A'}
                            </div>
                            <div class="field">
                                <label>Course Duration:</label> ${certificateToPrint.courseduration || 'N/A'} days
                            </div>
                            <div class="field">
                                <label>Start Date:</label> ${certificateToPrint.start_date || 'N/A'}
                            </div>
                            <div class="field">
                                <label>End Date:</label> ${certificateToPrint.end_date || 'N/A'}
                            </div>
                            <div class="field">
                                <label>Issue Date:</label> ${certificateToPrint.issue_date || 'N/A'}
                            </div>
                            <div class="field">
                                <label>Expire Date:</label> ${certificateToPrint.expire_date || 'N/A'}
                            </div>
                            <div class="field">
                                <label>Certificate Type:</label> ${certificateToPrint.certificate_type || 'N/A'}
                            </div>
                            <div class="field">
                                <label>Issuance Authority:</label> ${certificateToPrint.issuance_auth || 'N/A'}
                            </div>
                        </div>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        function exportCertificateData() {
            // Simple CSV export functionality
            const table = document.querySelector('table');
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            rows.forEach(row => {
                const cols = row.querySelectorAll('td, th');
                const rowData = Array.from(cols).map(col => {
                    return '"' + col.textContent.replace(/"/g, '""') + '"';
                });
                csv.push(rowData.join(','));
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'certificates_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function handleImageError(img) {
            const loading = document.getElementById('image-loading');
            const error = document.getElementById('image-error');
            
            if (loading) loading.style.display = 'none';
            if (error) error.classList.remove('hidden');
            img.style.display = 'none';
        }

        function openImageModal(imageUrl, certificateNo) {
            // Create image modal
            const modal = document.createElement('div');
            modal.id = 'imageModal';
            modal.className = 'fixed inset-0 bg-black bg-opacity-75 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4';
            modal.innerHTML = `
                <div class="relative max-w-4xl max-h-full">
                    <button onclick="closeImageModal()" class="absolute top-4 right-4 z-10 text-white hover:text-gray-300 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                    <img src="${imageUrl}" 
                         alt="Certificate ${certificateNo}" 
                         class="max-w-full max-h-full rounded-lg shadow-2xl"
                         onerror="handleImageModalError(this)">
                    <div class="absolute bottom-4 left-4 right-4 text-center">
                        <p class="text-white bg-black bg-opacity-50 rounded px-3 py-1 text-sm">
                            Certificate: ${certificateNo}
                        </p>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeImageModal();
                }
            });
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            if (modal) {
                modal.remove();
            }
        }

        function handleImageModalError(img) {
            img.style.display = 'none';
            const modal = document.getElementById('imageModal');
            if (modal) {
                modal.innerHTML = `
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 text-center">
                        <i class="fas fa-image text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Image Not Available</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">The certificate image could not be loaded.</p>
                        <button onclick="closeImageModal()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Close
                        </button>
                    </div>
                `;
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('certificateDetailsModal');
            const imageModal = document.getElementById('imageModal');
            
            if (event.target === modal) {
                closeCertificateDetailsModal();
            } else if (event.target === imageModal) {
                closeImageModal();
            }
        }
    </script>
</body>
</html>
