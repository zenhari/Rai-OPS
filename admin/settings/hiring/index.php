<?php
require_once '../../../config.php';

// Handle AJAX request for details - MUST be before any HTML output or redirects
if (isset($_GET['action']) && $_GET['action'] === 'get_details' && isset($_GET['id'])) {
    // Check if user is logged in (but don't redirect for AJAX)
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit();
    }
    
    // Check access without redirect
    if (!checkPageAccessEnhanced('admin/settings/hiring/index.php')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    header('Content-Type: application/json');
    
    $db = getDBConnection();
    $id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT * FROM hiring WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        $html = '<div class="grid grid-cols-2 gap-4">';
        $html .= '<div><strong>Full Name:</strong> ' . htmlspecialchars($record['full_name'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>National ID:</strong> ' . htmlspecialchars($record['national_id'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Birth Date:</strong> ' . htmlspecialchars($record['birth_date'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Birth Place:</strong> ' . htmlspecialchars($record['birth_place'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Email:</strong> ' . htmlspecialchars($record['email'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Phone:</strong> ' . htmlspecialchars($record['phone_number'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>WhatsApp:</strong> ' . htmlspecialchars($record['whatsapp_number'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Telegram:</strong> ' . htmlspecialchars($record['telegram_number'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Marital Status:</strong> ' . htmlspecialchars($record['marital_status'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Job Type:</strong> ' . htmlspecialchars($record['job_type'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Category:</strong> ' . htmlspecialchars($record['category_name'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Status:</strong> ' . htmlspecialchars($record['status'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Travel Readiness:</strong> ' . htmlspecialchars($record['travel_readiness'] ?? 'N/A') . '</div>';
        $html .= '<div><strong>Address:</strong> ' . htmlspecialchars($record['address'] ?? 'N/A') . '</div>';
        if (!empty($record['special_conditions'])) {
            $html .= '<div class="col-span-2"><strong>Special Conditions:</strong> ' . htmlspecialchars($record['special_conditions']) . '</div>';
        }
        if (!empty($record['salary_expectations'])) {
            $html .= '<div class="col-span-2"><strong>Salary Expectations:</strong> ' . htmlspecialchars($record['salary_expectations']) . '</div>';
        }
        if (!empty($record['available_times'])) {
            $html .= '<div class="col-span-2"><strong>Available Times:</strong> ' . htmlspecialchars($record['available_times']) . '</div>';
        }
        if (!empty($record['interview_time'])) {
            $html .= '<div><strong>Interview Time:</strong> ' . htmlspecialchars($record['interview_time']) . '</div>';
        }
        if (!empty($record['personal_photo_1'])) {
            $html .= '<div class="col-span-2"><strong>Photo:</strong> <img src="' . htmlspecialchars($record['personal_photo_1']) . '" alt="Photo" class="max-w-xs mt-2"></div>';
        }
        $html .= '</div>';
        
        echo json_encode(['success' => true, 'html' => $html], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
    }
    exit();
}

// Check access
checkPageAccessWithRedirect('admin/settings/hiring/index.php');

$current_user = getCurrentUser();
$db = getDBConnection();
$message = '';
$error = '';

// Helper function to safely truncate strings for database
function safeTruncate($value, $maxLength = 255) {
    if (empty($value)) return null;
    // Handle both string and array/null values
    if (!is_string($value)) {
        return null;
    }
    // Use mb_substr for proper UTF-8 character handling
    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    return $value;
}

// API Configuration
$apiBaseUrl = 'https://api.bpm.raimonairways.net';
// Token from curl example - decode first, then we'll encode it properly
$apiTokenEncoded = '%26M8R28oTnS(6ausjX[Cb"q%26L~[vF[S£vND<c)Z]fI<A67<<pI';
$apiToken = urldecode($apiTokenEncoded); // Decode to get original token

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'sync_from_api') {
            // Sync data from API
            try {
                $page = 1;
                $count = 100;
                $totalSynced = 0;
                $totalUpdated = 0;
                $totalSkipped = 0;
                
                do {
                    // Build URL with token - properly encode the token
                    $apiUrl = $apiBaseUrl . "/api/v1/hiring/admin?page={$page}&count={$count}&tk=" . rawurlencode($apiToken);
                    
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $apiUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_CONNECTTIMEOUT => 10,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                        CURLOPT_HTTPHEADER => [
                            'Accept: application/json',
                            'User-Agent: RaiOPS/1.0'
                        ]
                    ]);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);
                    
                    if ($response === false) {
                        throw new Exception("cURL Error: " . ($curlError ?: 'Unknown error'));
                    }
                    
                    if ($httpCode !== 200) {
                        // Log the response for debugging
                        error_log("Hiring API Error - HTTP Code: $httpCode, URL: $apiUrl, Response: " . substr($response, 0, 500));
                        $errorMsg = "API Error: HTTP $httpCode";
                        if ($response) {
                            $errorData = json_decode($response, true);
                            if ($errorData && isset($errorData['message'])) {
                                $errorMsg .= " - " . $errorData['message'];
                            } else {
                                $errorMsg .= " - " . substr($response, 0, 200);
                            }
                        }
                        throw new Exception($errorMsg);
                    }
                    
                    $data = json_decode($response, true);
                    
                    if (!isset($data['status']) || $data['status'] !== 'ok' || !isset($data['data'])) {
                        break; // No more data or error
                    }
                    
                    $applications = $data['data'];
                    
                    if (empty($applications)) {
                        break; // No more data
                    }
                    
                    foreach ($applications as $app) {
                        $apiId = intval($app['id'] ?? 0);
                        if ($apiId <= 0) continue;
                        
                        // Check if record exists
                        $stmt = $db->prepare("SELECT id FROM hiring WHERE api_id = ?");
                        $stmt->execute([$apiId]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Prepare data
                        $categoryName = isset($app['category']['name']) ? $app['category']['name'] : null;
                        $apiCreatedAt = isset($app['created_at']) ? date('Y-m-d H:i:s', strtotime($app['created_at'])) : null;
                        $apiUpdatedAt = isset($app['updated_at']) ? date('Y-m-d H:i:s', strtotime($app['updated_at'])) : null;
                        $birthDate = !empty($app['birth_date']) ? date('Y-m-d', strtotime($app['birth_date'])) : null;
                        $interviewTime = !empty($app['interview_time']) ? date('Y-m-d H:i:s', strtotime($app['interview_time'])) : null;
                        
                        // Convert arrays to JSON
                        $jsonFields = [
                            'degree', 'major', 'university', 'graduation_year', 'job_title', 'company_name',
                            'duration', 'job_description', 'course_name', 'institution', 'course_year',
                            'software_name', 'skill_acquisition', 'level', 'language_name', 'writing',
                            'listening', 'speaking', 'translation', 'relationship', 'family_name',
                            'workplace', 'contact_number', 'related_documents', 'related_documents_files',
                            'flight_history', 'flight_history_time', 'flight_history_company'
                        ];
                        
                        $jsonData = [];
                        foreach ($jsonFields as $field) {
                            $jsonData[$field] = !empty($app[$field]) ? json_encode($app[$field], JSON_UNESCAPED_UNICODE) : null;
                        }
                        
                        if ($existing) {
                            // Update existing record
                            // Count: 23 basic fields + 27 JSON fields + 2 API timestamps + 1 WHERE = 53 placeholders
                            $sql = "UPDATE hiring SET
                                full_name = ?, birth_date = ?, national_id = ?, birth_place = ?,
                                marital_status = ?, job_type = ?, email = ?, phone_number = ?,
                                whatsapp_number = ?, telegram_number = ?, address = ?,
                                travel_readiness = ?, special_conditions = ?, salary_expectations = ?,
                                available_times = ?, interview_time = ?, status = ?,
                                category_hiring_id = ?, category_name = ?, personal_photo_1 = ?,
                                airline_applications_nira_id = ?, is_read = ?,
                                degree = ?, major = ?, university = ?, graduation_year = ?,
                                job_title = ?, company_name = ?, duration = ?, job_description = ?,
                                course_name = ?, institution = ?, course_year = ?,
                                software_name = ?, skill_acquisition = ?, level = ?,
                                language_name = ?, writing = ?, listening = ?, speaking = ?, translation = ?,
                                relationship = ?, family_name = ?, workplace = ?, contact_number = ?,
                                related_documents = ?, related_documents_files = ?,
                                flight_history = ?, flight_history_time = ?, flight_history_company = ?,
                                api_created_at = ?, api_updated_at = ?, updated_at = NOW()
                                WHERE api_id = ?";
                            
                            $stmt = $db->prepare($sql);
                            $updateParams = [
                                safeTruncate($app['full_name'] ?? null, 255),
                                $birthDate,
                                safeTruncate($app['national_id'] ?? null, 50),
                                safeTruncate($app['birth_place'] ?? null, 255),
                                safeTruncate($app['marital_status'] ?? null, 100),
                                safeTruncate($app['job_type'] ?? null, 100),
                                safeTruncate($app['email'] ?? null, 255),
                                safeTruncate($app['phone_number'] ?? null, 50),
                                safeTruncate($app['whatsapp_number'] ?? null, 50),
                                safeTruncate($app['telegram_number'] ?? null, 50),
                                $app['address'] ?? null, // TEXT field, no truncation needed
                                safeTruncate($app['travel_readiness'] ?? null, 50),
                                $app['special_conditions'] ?? null, // TEXT field
                                $app['salary_expectations'] ?? null, // TEXT field
                                $app['available_times'] ?? null, // TEXT field
                                $interviewTime,
                                safeTruncate($app['status'] ?? null, 255),
                                $app['category_hiring_id'] ?? null,
                                $categoryName,
                                $app['personal_photo_1'] ?? null,
                                $app['airline_applications_nira_id'] ?? null,
                                intval($app['is_read'] ?? 0),
                                $jsonData['degree'], $jsonData['major'], $jsonData['university'], $jsonData['graduation_year'],
                                $jsonData['job_title'], $jsonData['company_name'], $jsonData['duration'], $jsonData['job_description'],
                                $jsonData['course_name'], $jsonData['institution'], $jsonData['course_year'],
                                $jsonData['software_name'], $jsonData['skill_acquisition'], $jsonData['level'],
                                $jsonData['language_name'], $jsonData['writing'], $jsonData['listening'], $jsonData['speaking'], $jsonData['translation'],
                                $jsonData['relationship'], $jsonData['family_name'], $jsonData['workplace'], $jsonData['contact_number'],
                                $jsonData['related_documents'], $jsonData['related_documents_files'],
                                $jsonData['flight_history'], $jsonData['flight_history_time'], $jsonData['flight_history_company'],
                                $apiCreatedAt, $apiUpdatedAt,
                                $apiId
                            ];
                            $stmt->execute($updateParams);
                            $totalUpdated++;
                        } else {
                            // Insert new record
                            // Total: 52 fields (api_id + 23 basic + 27 JSON + 2 API timestamps)
                            $sql = "INSERT INTO hiring (
                                api_id, full_name, birth_date, national_id, birth_place,
                                marital_status, job_type, email, phone_number,
                                whatsapp_number, telegram_number, address,
                                travel_readiness, special_conditions, salary_expectations,
                                available_times, interview_time, status,
                                category_hiring_id, category_name, personal_photo_1,
                                airline_applications_nira_id, is_read,
                                degree, major, university, graduation_year,
                                job_title, company_name, duration, job_description,
                                course_name, institution, course_year,
                                software_name, skill_acquisition, level,
                                language_name, writing, listening, speaking, translation,
                                relationship, family_name, workplace, contact_number,
                                related_documents, related_documents_files,
                                flight_history, flight_history_time, flight_history_company,
                                api_created_at, api_updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            
                            $stmt = $db->prepare($sql);
                            $insertParams = [
                                $apiId,
                                safeTruncate($app['full_name'] ?? null, 255),
                                $birthDate,
                                safeTruncate($app['national_id'] ?? null, 50),
                                safeTruncate($app['birth_place'] ?? null, 255),
                                safeTruncate($app['marital_status'] ?? null, 100),
                                safeTruncate($app['job_type'] ?? null, 100),
                                safeTruncate($app['email'] ?? null, 255),
                                safeTruncate($app['phone_number'] ?? null, 50),
                                safeTruncate($app['whatsapp_number'] ?? null, 50),
                                safeTruncate($app['telegram_number'] ?? null, 50),
                                $app['address'] ?? null, // TEXT field, no truncation needed
                                safeTruncate($app['travel_readiness'] ?? null, 50),
                                $app['special_conditions'] ?? null, // TEXT field
                                $app['salary_expectations'] ?? null, // TEXT field
                                $app['available_times'] ?? null, // TEXT field
                                $interviewTime,
                                safeTruncate($app['status'] ?? null, 255),
                                $app['category_hiring_id'] ?? null,
                                $categoryName,
                                $app['personal_photo_1'] ?? null,
                                $app['airline_applications_nira_id'] ?? null,
                                intval($app['is_read'] ?? 0),
                                $jsonData['degree'], $jsonData['major'], $jsonData['university'], $jsonData['graduation_year'],
                                $jsonData['job_title'], $jsonData['company_name'], $jsonData['duration'], $jsonData['job_description'],
                                $jsonData['course_name'], $jsonData['institution'], $jsonData['course_year'],
                                $jsonData['software_name'], $jsonData['skill_acquisition'], $jsonData['level'],
                                $jsonData['language_name'], $jsonData['writing'], $jsonData['listening'], $jsonData['speaking'], $jsonData['translation'],
                                $jsonData['relationship'], $jsonData['family_name'], $jsonData['workplace'], $jsonData['contact_number'],
                                $jsonData['related_documents'], $jsonData['related_documents_files'],
                                $jsonData['flight_history'], $jsonData['flight_history_time'], $jsonData['flight_history_company'],
                                $apiCreatedAt, $apiUpdatedAt
                            ];
                            $stmt->execute($insertParams);
                            $totalSynced++;
                        }
                    }
                    
                    $page++;
                    
                    // Limit to prevent infinite loops
                    if ($page > 100) break;
                    
                } while (count($applications) >= $count);
                
                $message = "Sync completed: {$totalSynced} new records, {$totalUpdated} updated, {$totalSkipped} skipped.";
            } catch (Exception $e) {
                $error = "Error syncing from API: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'remove_duplicates') {
            // Remove duplicate records based on national_id, email, or phone_number
            try {
                $db->beginTransaction();
                
                // Find duplicates by national_id
                $stmt = $db->query("
                    SELECT national_id, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids
                    FROM hiring
                    WHERE national_id IS NOT NULL AND national_id != ''
                    GROUP BY national_id
                    HAVING cnt > 1
                ");
                $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $removed = 0;
                
                foreach ($duplicates as $dup) {
                    $ids = explode(',', $dup['ids']);
                    // Keep the first one, delete others
                    array_shift($ids);
                    if (!empty($ids)) {
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        $delStmt = $db->prepare("DELETE FROM hiring WHERE id IN ($placeholders)");
                        $delStmt->execute($ids);
                        $removed += $delStmt->rowCount();
                    }
                }
                
                // Find duplicates by email
                $stmt = $db->query("
                    SELECT email, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids
                    FROM hiring
                    WHERE email IS NOT NULL AND email != ''
                    GROUP BY email
                    HAVING cnt > 1
                ");
                $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($duplicates as $dup) {
                    $ids = explode(',', $dup['ids']);
                    array_shift($ids);
                    if (!empty($ids)) {
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        $delStmt = $db->prepare("DELETE FROM hiring WHERE id IN ($placeholders)");
                        $delStmt->execute($ids);
                        $removed += $delStmt->rowCount();
                    }
                }
                
                // Find duplicates by phone_number
                $stmt = $db->query("
                    SELECT phone_number, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids
                    FROM hiring
                    WHERE phone_number IS NOT NULL AND phone_number != ''
                    GROUP BY phone_number
                    HAVING cnt > 1
                ");
                $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($duplicates as $dup) {
                    $ids = explode(',', $dup['ids']);
                    array_shift($ids);
                    if (!empty($ids)) {
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        $delStmt = $db->prepare("DELETE FROM hiring WHERE id IN ($placeholders)");
                        $delStmt->execute($ids);
                        $removed += $delStmt->rowCount();
                    }
                }
                
                $db->commit();
                $message = "Removed {$removed} duplicate records.";
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Error removing duplicates: " . $e->getMessage();
            }
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(full_name LIKE ? OR email LIKE ? OR phone_number LIKE ? OR national_id LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($status)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
}

if (!empty($category)) {
    $whereConditions[] = "category_name = ?";
    $params[] = $category;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countSql = "SELECT COUNT(*) as total FROM hiring $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get records
$sql = "SELECT * FROM hiring $whereClause ORDER BY created_at DESC LIMIT " . intval($perPage) . " OFFSET " . intval($offset);
$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique statuses and categories for filters
$statusStmt = $db->query("SELECT DISTINCT status FROM hiring WHERE status IS NOT NULL ORDER BY status");
$statuses = $statusStmt->fetchAll(PDO::FETCH_COLUMN);

$categoryStmt = $db->query("SELECT DISTINCT category_name FROM hiring WHERE category_name IS NOT NULL ORDER BY category_name");
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hiring Management - <?php echo PROJECT_NAME; ?></title>
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
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <i class="fas fa-user-tie mr-2"></i>
                                Hiring Management
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Manage hiring applications from BPM API
                            </p>
                        </div>
                        <div class="flex space-x-3">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="sync_from_api">
                                <button type="submit" 
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-sync-alt mr-2"></i>
                                    Sync from API
                                </button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove all duplicate records?');">
                                <input type="hidden" name="action" value="remove_duplicates">
                                <button type="submit" 
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-trash-alt mr-2"></i>
                                    Remove Duplicates
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <?php include '../../../includes/permission_banner.php'; ?>
                
                <?php if ($message): ?>
                <div class="mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-md">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-md">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Name, Email, Phone, National ID..."
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category</label>
                            <select name="category" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $category === $c ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Records Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Applications (<?php echo number_format($totalRecords); ?>)
                        </h2>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Full Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">National ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Phone</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No records found.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($records as $record): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($record['api_id']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($record['full_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($record['national_id'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($record['email'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($record['phone_number'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($record['category_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            <?php echo htmlspecialchars($record['status'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo $record['created_at'] ? date('Y-m-d H:i', strtotime($record['created_at'])) : 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <button onclick="viewDetails(<?php echo $record['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo number_format($totalRecords); ?> results
                            </div>
                            <div class="flex items-center space-x-1">
                                <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                                <?php endif; ?>
                                
                                <?php
                                // Smart pagination: show first page, last page, current page, and pages around current
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                // Show first page if not in range
                                if ($startPage > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                                       class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium <?php echo 1 === $page ? 'bg-blue-600 text-white' : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600'; ?> transition-colors">
                                        1
                                    </a>
                                    <?php if ($startPage > 2): ?>
                                        <span class="px-2 py-2 text-gray-500 dark:text-gray-400">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600'; ?> transition-colors">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>
                                
                                <?php
                                // Show last page if not in range
                                if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <span class="px-2 py-2 text-gray-500 dark:text-gray-400">...</span>
                                    <?php endif; ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" 
                                       class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium <?php echo $totalPages === $page ? 'bg-blue-600 text-white' : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600'; ?> transition-colors">
                                        <?php echo $totalPages; ?>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detail Modal -->
    <div id="detailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Application Details</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="modalContent" class="text-sm text-gray-600 dark:text-gray-400">
                    Loading...
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function viewDetails(id) {
        document.getElementById('detailModal').classList.remove('hidden');
        document.getElementById('modalContent').innerHTML = 'Loading...';
        
        fetch('?action=get_details&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalContent').innerHTML = data.html;
                } else {
                    document.getElementById('modalContent').innerHTML = '<p class="text-red-600">Error loading details.</p>';
                }
            })
            .catch(error => {
                document.getElementById('modalContent').innerHTML = '<p class="text-red-600">Error: ' + error + '</p>';
            });
    }
    
    function closeModal() {
        document.getElementById('detailModal').classList.add('hidden');
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('detailModal');
        if (event.target === modal) {
            closeModal();
        }
    };
    </script>
    
</body>
</html>

