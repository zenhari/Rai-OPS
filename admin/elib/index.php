<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/elib/index.php');

$current_user = getCurrentUser();

// Get documents from API
$apiResult = getApprovedDocuments();
$documents = [];
$departments = [];
$error = '';

if ($apiResult['success']) {
    $documents = $apiResult['data'];
    $departments = getDocumentDepartments();
} else {
    $error = $apiResult['error'] ?? 'Failed to fetch documents';
}

// Get selected department filter
$selectedDepartment = $_GET['department'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Filter documents based on department and search
$filteredDocuments = $documents;
if ($selectedDepartment) {
    $filteredDocuments = array_filter($filteredDocuments, function($doc) use ($selectedDepartment) {
        return $doc['department'] === $selectedDepartment;
    });
}

if ($searchQuery) {
    $searchQuery = strtolower($searchQuery);
    $filteredDocuments = array_filter($filteredDocuments, function($doc) use ($searchQuery) {
        return strpos(strtolower($doc['document_title']), $searchQuery) !== false ||
               strpos(strtolower($doc['document_code']), $searchQuery) !== false ||
               strpos(strtolower($doc['requester_name']), $searchQuery) !== false ||
               strpos(strtolower($doc['reason']), $searchQuery) !== false;
    });
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Lib - Electronic Library - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        
        .department-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            min-height: 280px;
        }
        .department-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.15), 0 10px 20px -5px rgba(0, 0, 0, 0.1);
        }
        
        /* Department-specific colors */
        .department-card.op {
            border-left-color: #3b82f6;
        }
        .department-card.mx {
            border-left-color: #10b981;
        }
        .department-card.hr {
            border-left-color: #f59e0b;
        }
        .department-card.finance {
            border-left-color: #ef4444;
        }
        .department-card.other {
            border-left-color: #8b5cf6;
        }
        
        /* Department header gradients */
        .department-card.op .department-header {
            background: linear-gradient(to right, #dbeafe, #e0e7ff) !important;
        }
        .dark .department-card.op .department-header {
            background: linear-gradient(to right, #1e3a8a, #312e81) !important;
        }
        
        .department-card.mx .department-header {
            background: linear-gradient(to right, #d1fae5, #dcfce7) !important;
        }
        .dark .department-card.mx .department-header {
            background: linear-gradient(to right, #064e3b, #065f46) !important;
        }
        
        .department-card.hr .department-header {
            background: linear-gradient(to right, #fef3c7, #fde68a) !important;
        }
        .dark .department-card.hr .department-header {
            background: linear-gradient(to right, #78350f, #92400e) !important;
        }
        
        .department-card.finance .department-header {
            background: linear-gradient(to right, #fee2e2, #fecaca) !important;
        }
        .dark .department-card.finance .department-header {
            background: linear-gradient(to right, #7f1d1d, #991b1b) !important;
        }
        
        .department-card.other .department-header {
            background: linear-gradient(to right, #e9d5ff, #ddd6fe) !important;
        }
        .dark .department-card.other .department-header {
            background: linear-gradient(to right, #4c1d95, #5b21b6) !important;
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <!-- Include Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Top Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-book-open mr-2"></i>E-Lib - Electronic Library
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Access approved documents and resources
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200">
                                <i class="fas fa-file-alt mr-1"></i>
                                <?php echo count($documents); ?> Documents
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                <!-- Search and Filter Bar -->
                <div class="mb-6">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input type="text" 
                                       id="searchInput"
                                       placeholder="Search documents..." 
                                       value="<?php echo htmlspecialchars($searchQuery); ?>"
                                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                        </div>
                        
                        <div class="sm:w-64">
                            <select id="departmentFilter" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $deptCode => $deptName): ?>
                                    <option value="<?php echo htmlspecialchars($deptCode); ?>" <?php echo $selectedDepartment === $deptCode ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($deptName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-md">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($filteredDocuments)): ?>
                    <div class="text-center py-12">
                        <div class="mx-auto w-24 h-24 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-folder-open text-gray-400 dark:text-gray-500 text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Documents Found</h3>
                        <p class="text-gray-500 dark:text-gray-400">
                            <?php if ($searchQuery || $selectedDepartment): ?>
                                No documents match your search criteria.
                            <?php else: ?>
                                No approved documents are available at the moment.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Group documents by department -->
                    <?php
                    $documentsByDeptAndType = [];
                    foreach ($filteredDocuments as $doc) {
                        $dept = $doc['department'];
                        $type = $doc['doc_type'] ?? 'Other';
                        if (!isset($documentsByDeptAndType[$dept])) {
                            $documentsByDeptAndType[$dept] = [];
                        }
                        if (!isset($documentsByDeptAndType[$dept][$type])) {
                            $documentsByDeptAndType[$dept][$type] = [];
                        }
                        $documentsByDeptAndType[$dept][$type][] = $doc;
                    }
                    ?>
                    
                    <!-- Department Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($documentsByDeptAndType as $dept => $types): ?>
                            <div class="department-card bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-shadow duration-300 <?php echo strtolower($dept); ?>">
                                <!-- Department Card Header -->
                                <div class="department-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 px-6 py-6 cursor-pointer" 
                                     onclick="viewDepartment('<?php echo htmlspecialchars($dept); ?>')">
                                    <div class="text-center">
                                        <div class="flex justify-center mb-4">
                                            <div class="p-4 bg-blue-100 dark:bg-blue-800 rounded-full">
                                                <i class="fas fa-building text-blue-600 dark:text-blue-300 text-2xl"></i>
                                            </div>
                                        </div>
                                        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-2">
                                            <?php echo htmlspecialchars($departments[$dept] ?? $dept); ?>
                                        </h2>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200 mb-3">
                                            <i class="fas fa-file-alt mr-1"></i>
                                            <?php echo array_sum(array_map('count', $types)); ?> Documents
                                        </span>
                                        <div class="flex justify-center">
                                            <i class="fas fa-chevron-right text-gray-400"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Department Content - Now redirects to separate page -->
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        // Live search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            filterDocuments();
        });

        document.getElementById('departmentFilter').addEventListener('change', function() {
            filterDocuments();
        });

        function viewDepartment(department) {
            // Redirect to department page
            window.location.href = 'department.php?dept=' + encodeURIComponent(department);
        }

        function filterDocuments() {
            const searchQuery = document.getElementById('searchInput').value.toLowerCase();
            const selectedDepartment = document.getElementById('departmentFilter').value;
            const departmentCards = document.querySelectorAll('.department-card');
            
            departmentCards.forEach(card => {
                const departmentHeader = card.querySelector('.department-header');
                const departmentName = departmentHeader.querySelector('h2').textContent.toLowerCase();
                const departmentCode = departmentHeader.querySelector('span').textContent.toLowerCase();
                
                // Check if department matches filter
                const matchesDepartment = !selectedDepartment || 
                    departmentCode.includes(selectedDepartment.toLowerCase()) ||
                    departmentName.includes(selectedDepartment.toLowerCase());
                
                if (!matchesDepartment) {
                    card.style.display = 'none';
                    return;
                }
                
                card.style.display = 'block';
            });
        }
    </script>
</body>
</html>
