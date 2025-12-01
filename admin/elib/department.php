<?php
require_once '../../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/elib/index.php');

$current_user = getCurrentUser();

// Get department from URL parameter
$department = $_GET['dept'] ?? '';
if (empty($department)) {
    header('Location: index.php');
    exit();
}

// Get documents from API
$apiResult = getApprovedDocuments();
$documents = [];
$departments = [];
$error = '';

if ($apiResult['success']) {
    $documents = $apiResult['data'];
    $departments = getDocumentDepartments();
    
    // Filter documents for this department only
    $documents = array_filter($documents, function($doc) use ($department) {
        return $doc['department'] === $department;
    });
} else {
    $error = $apiResult['error'] ?? 'Failed to fetch documents';
}

// Get search query
$searchQuery = $_GET['search'] ?? '';

// Apply search filter
if ($searchQuery) {
    $searchQuery = strtolower($searchQuery);
    $documents = array_filter($documents, function($doc) use ($searchQuery) {
        return strpos(strtolower($doc['document_title']), $searchQuery) !== false ||
               strpos(strtolower($doc['document_code']), $searchQuery) !== false ||
               strpos(strtolower($doc['requester_name']), $searchQuery) !== false ||
               strpos(strtolower($doc['reason']), $searchQuery) !== false ||
               strpos(strtolower($doc['doc_type']), $searchQuery) !== false;
    });
}

// Group documents by document type
$documentsByType = [];
foreach ($documents as $doc) {
    $docType = $doc['doc_type'] ?? 'Other';
    if (!isset($documentsByType[$docType])) {
        $documentsByType[$docType] = [];
    }
    $documentsByType[$docType][] = $doc;
}

$departmentName = $departments[$department] ?? $department;
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($departmentName); ?> Documents - E-Lib - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        
        .doc-type-tab {
            transition: all 0.3s ease;
        }
        .doc-type-tab.active {
            border-bottom-color: #3b82f6;
            color: #3b82f6;
        }
        .doc-type-panel {
            display: none;
        }
        .doc-type-panel.active {
            display: block;
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
                        <div class="flex items-center">
                            <a href="index.php" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mr-4">
                                <i class="fas fa-arrow-left text-xl"></i>
                            </a>
                            <div>
                                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-building mr-2"></i><?php echo htmlspecialchars($departmentName); ?> Documents
                                </h1>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Electronic Library - Department Documents
                                </p>
                            </div>
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
                <!-- Search Bar -->
                <div class="mb-6">
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

                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-md">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($documents)): ?>
                    <div class="text-center py-12">
                        <div class="mx-auto w-24 h-24 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-folder-open text-gray-400 dark:text-gray-500 text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Documents Found</h3>
                        <p class="text-gray-500 dark:text-gray-400">
                            <?php if ($searchQuery): ?>
                                No documents match your search criteria.
                            <?php else: ?>
                                This department doesn't have any approved documents yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Document Type Tabs -->
                    <div class="mb-6">
                        <div class="border-b border-gray-200 dark:border-gray-700">
                            <nav class="-mb-px flex space-x-8">
                                <?php foreach (array_keys($documentsByType) as $index => $docType): ?>
                                    <button onclick="switchDocTypeTab('<?php echo htmlspecialchars($docType); ?>')" 
                                            class="doc-type-tab py-2 px-1 border-b-2 border-transparent font-medium text-sm <?php echo $index === 0 ? 'active' : ''; ?> text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300">
                                        <i class="fas fa-file-alt mr-2"></i>
                                        <?php echo htmlspecialchars($docType); ?>
                                        <span class="ml-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 py-0.5 px-2 rounded-full text-xs">
                                            <?php echo count($documentsByType[$docType]); ?>
                                        </span>
                                    </button>
                                <?php endforeach; ?>
                            </nav>
                        </div>
                    </div>

                    <!-- Document Panels -->
                    <?php foreach ($documentsByType as $docType => $typeDocuments): ?>
                        <div id="panel-<?php echo htmlspecialchars($docType); ?>" class="doc-type-panel <?php echo array_search($docType, array_keys($documentsByType)) === 0 ? 'active' : ''; ?>">
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        <i class="fas fa-file-alt mr-2"></i><?php echo htmlspecialchars($docType); ?> Documents
                                        <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
                                            (<?php echo count($typeDocuments); ?> documents)
                                        </span>
                                    </h3>
                                </div>
                                
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Document</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Code</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Requester</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Issue Date</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Revision</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <?php foreach ($typeDocuments as $doc): ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                            <?php echo htmlspecialchars($doc['document_title']); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                                            <?php echo htmlspecialchars($doc['reason']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($doc['document_code']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($doc['requester_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo date('M j, Y', strtotime($doc['issue_date'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo htmlspecialchars($doc['revision']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <a href="<?php echo htmlspecialchars($doc['download_url']); ?>" 
                                                           target="_blank"
                                                           class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                            <i class="fas fa-download mr-1"></i>Download
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        function switchDocTypeTab(docType) {
            // Hide all panels
            document.querySelectorAll('.doc-type-panel').forEach(panel => {
                panel.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.doc-type-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected panel
            document.getElementById('panel-' + docType).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchQuery = this.value;
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('search', searchQuery);
            window.location.href = currentUrl.toString();
        });
    </script>
</body>
</html>
