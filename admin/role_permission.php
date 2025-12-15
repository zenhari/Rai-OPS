<?php
require_once '../config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('admin/role_permission.php');

$current_user = getCurrentUser();
$isSuperAdmin = ($current_user['role_name'] ?? '') === 'super_admin';
$message = '';
$error = '';

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/**
 * Render Individual Access cell HTML
 */
function renderIndividualAccessCell($pagePath, $pageName = null, $individualAccess = null) {
    // If pageName is not provided, get it from database
    if ($pageName === null) {
        $permission = getPagePermission($pagePath);
        $pageName = $permission['page_name'] ?? '';
    }
    
    // If individualAccess is not provided, get it from database
    if ($individualAccess === null) {
        $individualAccess = getIndividualAccessForPage($pagePath);
    }
    
    $html = '<div class="space-y-1">';
    if (empty($individualAccess)) {
        $html .= '<span class="text-xs text-gray-400 dark:text-gray-500">No individual access</span>';
    } else {
        foreach ($individualAccess as $access) {
            $html .= '<div class="flex items-center justify-between">';
            $html .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">';
            $html .= htmlspecialchars($access['first_name'] . ' ' . $access['last_name']);
            if ($access['expires_at']) {
                $html .= '<span class="ml-1 text-xs opacity-75">(exp: ' . date('M j', strtotime($access['expires_at'])) . ')</span>';
            }
            $html .= '</span>';
            $html .= '<button onclick="revokeIndividualAccess(\'' . htmlspecialchars($pagePath) . '\', ' . $access['user_id'] . ')" class="ml-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-xs">';
            $html .= '<i class="fas fa-times"></i>';
            $html .= '</button>';
            $html .= '</div>';
        }
    }
    $html .= '<button onclick="openIndividualAccessModal(\'' . htmlspecialchars($pagePath) . '\', \'' . htmlspecialchars($pageName) . '\')" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">';
    $html .= '<i class="fas fa-plus mr-1"></i>Add User';
    $html .= '</button>';
    $html .= '</div>';
    return $html;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_permission':
            $id = intval($_POST['id'] ?? 0);
            $requiredRoles = $_POST['required_roles'] ?? [];
            $description = trim($_POST['description'] ?? '');
            $pageName = trim($_POST['page_name'] ?? '');
            
            // For non-super-admins, prevent assigning admin/super_admin to pages
            if (!$isSuperAdmin && !empty($requiredRoles)) {
                $requiredRoles = array_values(array_filter($requiredRoles, function($role) {
                    $role = strtolower($role);
                    return $role !== 'super_admin' && $role !== 'admin';
                }));
            }
            
            if (empty($requiredRoles)) {
                $error = 'At least one role must be selected.';
            } elseif (empty($pageName)) {
                $error = 'Page name is required.';
            } else {
                $rolesJson = json_encode($requiredRoles);
                if (updatePagePermission($id, $rolesJson, $description, $pageName)) {
                    $message = 'Permission updated successfully.';
                } else {
                    $error = 'Failed to update permission.';
                }
            }
            
            // If AJAX request, return JSON response
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => empty($error),
                    'message' => $message ?? null,
                    'error' => $error ?? null
                ]);
                exit;
            }
            break;
            
        case 'add_new_pages':
            $defaultRoles = $_POST['default_roles'] ?? ['admin'];
            // For non-super-admins, strip admin/super_admin from defaults
            if (!$isSuperAdmin && !empty($defaultRoles)) {
                $defaultRoles = array_values(array_filter($defaultRoles, function($role) {
                    $role = strtolower($role);
                    return $role !== 'super_admin' && $role !== 'admin';
                }));
            }
            $added = addAllNewPages($defaultRoles);
            if ($added > 0) {
                $message = "Successfully added {$added} new page(s) to permissions.";
            } else {
                $message = 'No new pages found to add.';
            }
            break;
            
        case 'add_manual_page':
            $pagePath = trim($_POST['page_path'] ?? '');
            $pageName = trim($_POST['page_name'] ?? '');
            $requiredRoles = $_POST['required_roles'] ?? [];
            $description = trim($_POST['description'] ?? '');
            
            // For non-super-admins, prevent assigning admin/super_admin to pages
            if (!$isSuperAdmin && !empty($requiredRoles)) {
                $requiredRoles = array_values(array_filter($requiredRoles, function($role) {
                    $role = strtolower($role);
                    return $role !== 'super_admin' && $role !== 'admin';
                }));
            }
            
            if (empty($pagePath) || empty($pageName) || empty($requiredRoles)) {
                $error = 'All fields are required.';
            } else {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'New page permission added successfully.';
                } else {
                    $error = 'Failed to add page permission.';
                }
            }
            break;
            
        case 'grant_individual_access':
            $pagePath = trim($_POST['page_path'] ?? '');
            $userIds = $_POST['user_ids'] ?? [];
            $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($pagePath) || empty($userIds) || !is_array($userIds)) {
                $error = 'Page path and at least one user are required.';
            } else {
                $successCount = 0;
                $failCount = 0;
                
                foreach ($userIds as $userId) {
                    $userId = intval($userId);
                    if ($userId > 0) {
                if (grantIndividualAccess($pagePath, $userId, $current_user['id'], $expiresAt, $notes)) {
                            $successCount++;
                } else {
                            $failCount++;
                        }
                    }
                }
                
                if ($successCount > 0) {
                    $message = "Individual access granted successfully for {$successCount} user(s).";
                    if ($failCount > 0) {
                        $message .= " Failed for {$failCount} user(s).";
                    }
                } else {
                    $error = 'Failed to grant individual access for all selected users.';
                }
            }
            
            // If AJAX request, return JSON response
            if ($isAjax) {
                header('Content-Type: application/json');
                
                // Get updated individual access HTML
                $individualAccessHtml = null;
                if (empty($error) && !empty($pagePath)) {
                    $individualAccessHtml = renderIndividualAccessCell($pagePath);
                }
                
                echo json_encode([
                    'success' => empty($error),
                    'message' => $message ?? null,
                    'error' => $error ?? null,
                    'successCount' => $successCount ?? 0,
                    'failCount' => $failCount ?? 0,
                    'pagePath' => $pagePath ?? null,
                    'individualAccessHtml' => $individualAccessHtml
                ]);
                exit;
            }
            break;
            
        case 'revoke_individual_access':
            $pagePath = trim($_POST['page_path'] ?? '');
            $userId = intval($_POST['user_id'] ?? 0);
            
            if (empty($pagePath) || $userId <= 0) {
                $error = 'Page path and user are required.';
            } else {
                if (revokeIndividualAccess($pagePath, $userId)) {
                    $message = 'Individual access revoked successfully.';
                } else {
                    $error = 'Failed to revoke individual access.';
                }
            }
            break;
            
        case 'scan_new_pages':
            $newPages = scanForNewPages();
            if (!empty($newPages)) {
                $message = 'Found ' . count($newPages) . ' new page(s). Please use "Add Page" to add them manually.';
            } else {
                $message = 'No new pages found.';
            }
            break;
            
        case 'add_route_edit_page':
            $pagePath = 'admin/fleet/routes/edit.php';
            $pageName = 'Edit Route';
            $requiredRoles = ['admin'];
            $description = 'Edit existing flight routes and update route information';
            
            // Check if page already exists
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Route Edit page permission added successfully.';
                } else {
                    $error = 'Failed to add Route Edit page permission.';
                }
            } else {
                $message = 'Route Edit page permission already exists.';
            }
            break;
            
        case 'add_delay_codes_page':
            $pagePath = 'admin/fleet/delay_codes/index.php';
            $pageName = 'Delay Codes Management';
            $requiredRoles = ['admin'];
            $description = 'Manage delay and diversion codes for flight operations';
            
            // Check if page already exists
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Delay Codes page permission added successfully.';
                } else {
                    $error = 'Failed to add Delay Codes page permission.';
                }
            } else {
                $message = 'Delay Codes page permission already exists.';
            }
            break;

        case 'add_raimon_delay_code_page':
            $pagePath = 'admin/fleet/delay_codes/raimon_delay_code.php';
            $pageName = 'Raimon Delay Code';
            $requiredRoles = ['admin'];
            $description = 'Multi-step delay code selection and filtering (Proccess → Sub-Proccess → Reson → StackHolder & Result Code)';
            
            // Check if page already exists
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Raimon Delay Code page permission added successfully.';
                } else {
                    $error = 'Failed to add Raimon Delay Code page permission.';
                }
            } else {
                $message = 'Raimon Delay Code page permission already exists.';
            }
            break;
            
        case 'add_etl_report_page':
            $pagePath = 'admin/fleet/etl_report/index.php';
            $pageName = 'ETL Report';
            $requiredRoles = ['admin'];
            $description = 'Electronic Technical Log Report from external API';
            
            // Check if page already exists
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'ETL Report page permission added successfully.';
                } else {
                    $error = 'Failed to add ETL Report page permission.';
                }
            } else {
                $message = 'ETL Report page permission already exists.';
            }
            break;

        case 'add_airsar_report_page':
            $pagePath = 'admin/fleet/airsar_report/index.php';
            $pageName = 'Airsar Report (ETL)';
            $requiredRoles = ['admin'];
            $description = 'Airsar Report from ETL system - Operations data with flight details, crew, and task information';

            // Check if page already exists
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Airsar Report (ETL) page permission added successfully.';
                } else {
                    $error = 'Failed to add Airsar Report (ETL) page permission.';
                }
            } else {
                $message = 'Airsar Report (ETL) page permission already exists.';
            }
            break;
            
        case 'add_dispatch_handover_page':
            $pagePath = 'admin/dispatch/webform/index.php';
            $pageName = 'Dispatch Handover';
            $requiredRoles = ['admin'];
            $description = 'Dispatch handover form for flight operations - Pre-flight and post-flight checklist';

            // Check if page already exists
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Dispatch Handover page permission added successfully.';
                } else {
                    $error = 'Failed to add Dispatch Handover page permission.';
                }
            } else {
                $message = 'Dispatch Handover page permission already exists.';
            }
            break;
            
        case 'add_rlss_page':
            // Add main RLSS page
            $pagePath = 'admin/rlss/index.php';
            $pageName = 'RLSS';
            $requiredRoles = ['admin'];
            $description = 'RLSS - Main page for RLSS Management System';

            // Check if page already exists
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                addNewPagePermission($pagePath, $pageName, $requiredRoles, $description);
            }
            
            // Add Part Search page
            $partSearchPath = 'admin/rlss/part_search/index.php';
            $partSearchPermission = getPagePermission($partSearchPath);
            if (!$partSearchPermission) {
                addNewPagePermission($partSearchPath, 'RLSS - Part Search', $requiredRoles, 'RLSS - Parts Search using Locatory API - Search for aircraft parts by part number, condition, and quantity');
            }
            
            // Add Search MRO page
            $searchMROPath = 'admin/rlss/search_mro/index.php';
            $searchMROPermission = getPagePermission($searchMROPath);
            if (!$searchMROPermission) {
                addNewPagePermission($searchMROPath, 'RLSS - Search MRO', $requiredRoles, 'RLSS - Search MRO capabilities using Locatory API - Search for maintenance, repair, and overhaul services');
            }
            
            $message = 'RLSS pages permission added successfully.';
            break;

        case 'add_efb_page':
            $pagePath = 'admin/efb/index.php';
            $pageName = 'EFB - Electronic Flight Bag';
            $requiredRoles = ['admin'];
            $description = 'Electronic Flight Bag management (uploads and records)';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'EFB page permission added successfully.';
                } else {
                    $error = 'Failed to add EFB page permission.';
                }
            } else {
                $message = 'EFB page permission already exists.';
            }
            break;

        case 'add_efb_delete_page':
            $pagePath = 'admin/efb/delete';
            $pageName = 'EFB Delete';
            $requiredRoles = ['admin'];
            $description = 'Delete EFB records and associated files';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'EFB Delete page permission added successfully.';
                } else {
                    $error = 'Failed to add EFB Delete page permission.';
                }
            } else {
                $message = 'EFB Delete page permission already exists.';
            }
            break;

        case 'add_crew_location_page':
            $pagePath = 'admin/crew/location.php';
            $pageName = 'Crew Location';
            $requiredRoles = ['admin'];
            $description = 'Track crew member locations at end of day based on flight assignments';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Crew Location page permission added successfully.';
                } else {
                    $error = 'Failed to add Crew Location page permission.';
                }
            } else {
                $message = 'Crew Location page permission already exists.';
            }
            break;

        case 'add_metar_tafor_page':
            $pagePath = 'admin/operations/metar_tafor.php';
            $pageName = 'METAR/TAFOR';
            $requiredRoles = ['admin'];
            $description = 'Aviation weather information (METAR/TAFOR) from Aviation Weather API';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'METAR/TAFOR page permission added successfully.';
                } else {
                    $error = 'Failed to add METAR/TAFOR page permission.';
                }
            } else {
                $message = 'METAR/TAFOR page permission already exists.';
            }
            break;

        case 'add_certificate_page':
            $pagePath = 'admin/users/certificate/index.php';
            $pageName = 'Certificate Management';
            $requiredRoles = ['admin'];
            $description = 'Manage and view training certificates from external API';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Certificate page permission added successfully.';
                } else {
                    $error = 'Failed to add Certificate page permission.';
                }
            } else {
                $message = 'Certificate page permission already exists.';
            }
            break;

        case 'add_delete_certificate_page':
            $pagePath = 'admin/users/certificate/delete';
            $pageName = 'Delete Certificate';
            $requiredRoles = ['admin'];
            $description = 'Delete training certificates from the system';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Delete Certificate page permission added successfully.';
                } else {
                    $error = 'Failed to add Delete Certificate page permission.';
                }
            } else {
                $message = 'Delete Certificate page permission already exists.';
            }
            break;

        case 'add_call_center_page':
            $pagePath = 'admin/settings/call_center/index.php';
            $pageName = 'Call Center';
            $requiredRoles = ['admin'];
            $description = 'Monitor and view all call records from Asterisk system';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Call Center page permission added successfully.';
                } else {
                    $error = 'Failed to add Call Center page permission.';
                }
            } else {
                $message = 'Call Center page permission already exists.';
            }
            break;

        case 'add_hiring_page':
            $pagePath = 'admin/settings/hiring/index.php';
            $pageName = 'Hiring';
            $requiredRoles = ['admin'];
            $description = 'Manage hiring applications from BPM API';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Hiring page permission added successfully.';
                } else {
                    $error = 'Failed to add Hiring page permission.';
                }
            } else {
                $message = 'Hiring page permission already exists.';
            }
            break;
            
        case 'add_my_location_page':
            $pagePath = 'admin/profile/my_location.php';
            $pageName = 'Get My Location';
            $requiredRoles = ['admin', 'pilot', 'employee'];
            $description = 'Get and share your current location (mobile, tablet, or laptop)';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Get My Location page permission added successfully.';
                } else {
                    $error = 'Failed to add Get My Location page permission.';
                }
            } else {
                $message = 'Get My Location page permission already exists.';
            }
            break;
            
        case 'add_user_location_page':
            $pagePath = 'admin/users/location/index.php';
            $pageName = 'User Location';
            $requiredRoles = ['admin'];
            $description = 'View all user locations on map with filtering options';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'User Location page permission added successfully.';
                } else {
                    $error = 'Failed to add User Location page permission.';
                }
            } else {
                $message = 'User Location page permission already exists.';
            }
            break;
            
        case 'add_last_location_page':
            $pagePath = 'admin/settings/last_location/index.php';
            $pageName = 'Last Location';
            $requiredRoles = ['admin'];
            $description = 'View last known location of mobile users on map with profile pictures';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Last Location page permission added successfully.';
                } else {
                    $error = 'Failed to add Last Location page permission.';
                }
            } else {
                $message = 'Last Location page permission already exists.';
            }
            break;

        case 'add_recency_management_page':
            $pagePath = 'admin/recency_management/index.php';
            $pageName = 'Recency Management';
            $requiredRoles = ['admin'];
            $description = 'Manage recency items and configurations';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Recency Management page permission added successfully.';
                } else {
                    $error = 'Failed to add Recency Management page permission.';
                }
            } else {
                $message = 'Recency Management page permission already exists.';
            }
            break;

        case 'add_set_recency_page':
            $pagePath = 'admin/recency_management/set_recency.php';
            $pageName = 'Set Recency\'s';
            $requiredRoles = ['admin'];
            $description = 'View and manage all recency items with department assignments';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Set Recency\'s page permission added successfully.';
                } else {
                    $error = 'Failed to add Set Recency\'s page permission.';
                }
            } else {
                $message = 'Set Recency\'s page permission already exists.';
            }
            break;

        case 'add_handover_page':
            $pagePath = 'admin/fleet/handover/index.php';
            $pageName = 'HandOver Management';
            $requiredRoles = ['admin'];
            $description = 'Manage handover records and shift information from external API';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'HandOver page permission added successfully.';
                } else {
                    $error = 'Failed to add HandOver page permission.';
                }
            } else {
                $message = 'HandOver page permission already exists.';
            }
            break;

        case 'add_asr_pages':
            $asrPages = [
                ['path' => 'admin/flights/asr/index.php', 'name' => 'ASR - Air Safety Reports', 'description' => 'View and manage air safety incident reports'],
                ['path' => 'admin/flights/asr/add.php', 'name' => 'ASR - Add Report', 'description' => 'Create new air safety incident report'],
                ['path' => 'admin/flights/asr/edit.php', 'name' => 'ASR - Edit Report', 'description' => 'Edit existing air safety incident report'],
                ['path' => 'admin/flights/asr/view.php', 'name' => 'ASR - View Report', 'description' => 'View air safety incident report details']
            ];
            
            $added = 0;
            foreach ($asrPages as $page) {
                $existingPermission = getPagePermission($page['path']);
                if (!$existingPermission) {
                    if (addNewPagePermission($page['path'], $page['name'], ['admin'], $page['description'])) {
                        $added++;
                    }
                }
            }
            
            if ($added > 0) {
                $message = "Successfully added {$added} ASR page(s) to permissions.";
            } else {
                $message = 'All ASR pages already exist in permissions.';
            }
            break;

        case 'add_journey_log_list_page':
            $pagePath = 'admin/operations/journey_log_list.php';
            $pageName = 'Journey Log List';
            $requiredRoles = ['admin'];
            $description = 'View and manage all journey logs with detailed information';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Journey Log List page permission added successfully.';
                } else {
                    $error = 'Failed to add Journey Log List page permission.';
                }
            } else {
                $message = 'Journey Log List page permission already exists.';
            }
            break;

        case 'add_flight_roles_page':
            $pagePath = 'admin/operations/flight_roles.php';
            $pageName = 'Flight Roles';
            $requiredRoles = ['admin'];
            $description = 'Manage cockpit and cabin roles for flight operations';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Flight Roles page permission added successfully.';
                } else {
                    $error = 'Failed to add Flight Roles page permission.';
                }
            } else {
                $message = 'Flight Roles page permission already exists.';
            }
            break;

        case 'add_roster_index_page':
            $pagePath = 'admin/operations/roster/index.php';
            $pageName = 'Shift Code';
            $requiredRoles = ['admin', 'manager'];
            $description = 'List and manage shift codes for roster configuration';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Shift Code page permission added successfully.';
                } else {
                    $error = 'Failed to add Shift Code page permission.';
                }
            } else {
                $message = 'Shift Code page permission already exists.';
            }
            break;

        case 'add_roster_add_page':
            $pagePath = 'admin/operations/roster/add.php';
            $pageName = 'Add Shift Code';
            $requiredRoles = ['admin', 'manager'];
            $description = 'Add new shift code with shift details configuration';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Add Shift Code page permission added successfully.';
                } else {
                    $error = 'Failed to add Add Shift Code page permission.';
                }
            } else {
                $message = 'Add Shift Code page permission already exists.';
            }
            break;

        case 'add_roster_edit_page':
            $pagePath = 'admin/operations/roster/edit.php';
            $pageName = 'Edit Shift Code';
            $requiredRoles = ['admin', 'manager'];
            $description = 'Edit existing shift code and shift details configuration';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Edit Shift Code page permission added successfully.';
                } else {
                    $error = 'Failed to add Edit Shift Code page permission.';
                }
            } else {
                $message = 'Edit Shift Code page permission already exists.';
            }
            break;

        case 'add_roster_management_page':
            $pagePath = 'admin/operations/roster/roster_management.php';
            $pageName = 'Roster Management';
            $requiredRoles = ['admin', 'manager'];
            $description = 'Manage shift code assignments for crew members in calendar view';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Roster Management page permission added successfully.';
                } else {
                    $error = 'Failed to add Roster Management page permission.';
                }
            } else {
                $message = 'Roster Management page permission already exists.';
            }
            break;

        case 'add_caa_city_per_page':
            $pagePath = 'admin/caa/city_per.php';
            $pageName = 'CAA City-Pairs Domestic';
            $requiredRoles = ['admin'];
            $description = 'Generate monthly city-pairs and traffic reports for CAA (Domestic flights only)';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'CAA City-Pairs Domestic page permission added successfully.';
                } else {
                    $error = 'Failed to add CAA City-Pairs Domestic page permission.';
                }
            } else {
                $message = 'CAA City-Pairs Domestic page permission already exists.';
            }
            break;

        case 'add_caa_city_per_international_page':
            $pagePath = 'admin/caa/city_per_international.php';
            $pageName = 'CAA City-Pairs International';
            $requiredRoles = ['admin'];
            $description = 'Generate monthly city-pairs and traffic reports for CAA (International flights only)';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'CAA City-Pairs International page permission added successfully.';
                } else {
                    $error = 'Failed to add CAA City-Pairs International page permission.';
                }
            } else {
                $message = 'CAA City-Pairs International page permission already exists.';
            }
            break;

        case 'add_caa_divert_flight_page':
            $pagePath = 'admin/caa/divert_flight.php';
            $pageName = 'CAA Divert Flight';
            $requiredRoles = ['admin'];
            $description = 'View flights that diverted and returned to origin airport';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'CAA Divert Flight page permission added successfully.';
                } else {
                    $error = 'Failed to add CAA Divert Flight page permission.';
                }
            } else {
                $message = 'CAA Divert Flight page permission already exists.';
            }
            break;

        case 'add_caa_revenue_page':
            $pagePath = 'admin/caa/revenue.php';
            $pageName = 'CAA Revenue-generating Flights';
            $requiredRoles = ['admin'];
            $description = 'Generate revenue analysis reports for flights with passenger and financial data';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'CAA Revenue-generating Flights page permission added successfully.';
                } else {
                    $error = 'Failed to add CAA Revenue-generating Flights page permission.';
                }
            } else {
                $message = 'CAA Revenue-generating Flights page permission already exists.';
            }
            break;

        case 'add_caa_daily_report_page':
            $pagePath = 'admin/caa/daily_report.php';
            $pageName = 'CAA Daily Report';
            $requiredRoles = ['admin'];
            $description = 'Generate daily flight reports in Excel format with Persian headers';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'CAA Daily Report page permission added successfully.';
                } else {
                    $error = 'Failed to add CAA Daily Report page permission.';
                }
            } else {
                $message = 'CAA Daily Report page permission already exists.';
            }
            break;

        case 'add_notam_page':
            $pagePath = 'admin/notam.php';
            $pageName = 'NOTAM';
            $requiredRoles = ['admin'];
            $description = 'View NOTAMs for today\'s flight routes';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'NOTAM page permission added successfully.';
                } else {
                    $error = 'Failed to add NOTAM page permission.';
                }
            } else {
                $message = 'NOTAM page permission already exists.';
            }
            break;

        case 'add_route_fix_time_page':
            $pagePath = 'admin/fleet/routes/fix_time.php';
            $pageName = 'Route Fix Time Management';
            $requiredRoles = ['admin'];
            $description = 'Manage fix times for all flight routes based on route codes';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Route Fix Time Management page permission added successfully.';
                } else {
                    $error = 'Failed to add Route Fix Time Management page permission.';
                }
            } else {
                $message = 'Route Fix Time Management page permission already exists.';
            }
            break;

        case 'add_payload_data_page':
            $pagePath = 'admin/operations/payload_data.php';
            $pageName = 'Payload Data';
            $requiredRoles = ['admin'];
            $description = 'Manage payload weights for routes at different temperatures (20°C, 25°C, 35°C, 40°C)';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Payload Data page permission added successfully.';
                } else {
                    $error = 'Failed to add Payload Data page permission.';
                }
            } else {
                $message = 'Payload Data page permission already exists.';
            }
            break;

        case 'add_payload_calculator_page':
            $pagePath = 'admin/operations/payload_calculator.php';
            $pageName = 'Payload Calculator';
            $requiredRoles = ['admin'];
            $description = 'Calculate payload weight for today\'s flights based on passenger count and accompanying load';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Payload Calculator page permission added successfully.';
                } else {
                    $error = 'Failed to add Payload Calculator page permission.';
                }
            } else {
                $message = 'Payload Calculator page permission already exists.';
            }
            break;

        case 'add_metar_tafor_history_page':
            $pagePath = 'admin/operations/metar_tafor_history.php';
            $pageName = 'METAR/TAFOR History';
            $requiredRoles = ['admin'];
            $description = 'View historical METAR/TAFOR weather data for stations';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'METAR/TAFOR History page permission added successfully.';
                } else {
                    $error = 'Failed to add METAR/TAFOR History page permission.';
                }
            } else {
                $message = 'METAR/TAFOR History page permission already exists.';
            }
            break;

        case 'add_passenger_by_aircraft_page':
            $pagePath = 'admin/operations/passenger_by_aircraft.php';
            $pageName = 'Passenger By Aircraft';
            $requiredRoles = ['admin'];
            $description = 'Analyze passenger capacity utilization by aircraft registration';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Passenger By Aircraft page permission added successfully.';
                } else {
                    $error = 'Failed to add Passenger By Aircraft page permission.';
                }
            } else {
                $message = 'Passenger By Aircraft page permission already exists.';
            }
            break;

        case 'add_backup_db_page':
            $pagePath = 'admin/settings/backup_db.php';
            $pageName = 'Backup Database';
            $requiredRoles = ['admin'];
            $description = 'Create full database backup of all tables, data, routines, triggers, and events';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Backup Database page permission added successfully.';
                } else {
                    $error = 'Failed to add Backup Database page permission.';
                }
            } else {
                $message = 'Backup Database page permission already exists.';
            }
            break;

        case 'add_office_time_page':
            $pagePath = 'admin/users/office_time.php';
            $pageName = 'Office Time';
            $requiredRoles = ['admin'];
            $description = 'View employee office time and punch records from attendance system';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Office Time page permission added successfully.';
                } else {
                    $error = 'Failed to add Office Time page permission.';
                }
            } else {
                $message = 'Office Time page permission already exists.';
            }
            break;
            
        case 'add_maintenance_mode_page':
            $pagePath = 'admin/settings/maintenance_mode.php';
            $pageName = 'Maintenance Mode';
            $requiredRoles = ['super_admin'];
            $description = 'Configure global maintenance window and countdown (only for Super Admin)';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Maintenance Mode page permission added successfully.';
                } else {
                    $error = 'Failed to add Maintenance Mode page permission.';
                }
            } else {
                $message = 'Maintenance Mode page permission already exists.';
            }
            break;
            
        case 'add_notification_page':
            $pagePath = 'admin/settings/notification.php';
            $pageName = 'Notifications';
            $requiredRoles = ['admin'];
            $description = 'Manage system notifications for users based on roles';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Notifications page permission added successfully.';
                } else {
                    $error = 'Failed to add Notifications page permission.';
                }
            } else {
                $message = 'Notifications page permission already exists.';
            }
            break;

        case 'add_message_page':
            $pagePath = 'admin/messages/index.php';
            $pageName = 'Messages';
            $requiredRoles = ['admin', 'pilot', 'employee'];
            $description = 'Send and receive messages with team members';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Messages page permission added successfully.';
                } else {
                    $error = 'Failed to add Messages page permission.';
                }
            } else {
                $message = 'Messages page permission already exists.';
            }
            break;

        case 'add_trip_management_page':
            $pagePath = 'admin/transport/trip_management.php';
            $pageName = 'Trip Management';
            $requiredRoles = ['admin'];
            $description = 'Manage driver assignments for crew members on flights';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Trip Management page permission added successfully.';
                } else {
                    $error = 'Failed to add Trip Management page permission.';
                }
            } else {
                $message = 'Trip Management page permission already exists.';
            }
            break;
            
        case 'add_ofp_page':
            $pagePath = 'admin/operations/ofp.php';
            $pageName = 'OFP Viewer';
            $requiredRoles = ['admin'];
            $description = 'View Operational Flight Plan data from Skyputer API';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'OFP Viewer page permission added successfully.';
                } else {
                    $error = 'Failed to add OFP Viewer page permission.';
                }
            } else {
                $message = 'OFP Viewer page permission already exists.';
            }
            break;

        case 'add_sms_page':
            $pagePath = 'admin/settings/sms.php';
            $pageName = 'SMS Management';
            $requiredRoles = ['admin'];
            $description = 'Send SMS messages to users';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'SMS Management page permission added successfully.';
                } else {
                    $error = 'Failed to add SMS Management page permission.';
                }
            } else {
                $message = 'SMS Management page permission already exists.';
            }
            break;

        case 'add_about_page':
            $pagePath = 'about.php';
            $pageName = 'About Developer';
            $requiredRoles = ['admin', 'pilot', 'employee'];
            $description = 'Information about the project developer - Mehdi Zenhari';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'About page permission added successfully.';
                } else {
                    $error = 'Failed to add About page permission.';
                }
            } else {
                $message = 'About page permission already exists.';
            }
            break;

        case 'add_my_recency_page':
            $pagePath = 'admin/profile/my_recency.php';
            $pageName = 'My Recency';
            $requiredRoles = ['admin', 'pilot', 'employee'];
            $description = 'View personal recency records from recencypersonnel table';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'My Recency page permission added successfully.';
                } else {
                    $error = 'Failed to add My Recency page permission.';
                }
            } else {
                $message = 'My Recency page permission already exists.';
            }
            break;

        case 'add_my_certificate_page':
            $pagePath = 'admin/profile/my_certificate.php';
            $pageName = 'My Certificate';
            $requiredRoles = ['admin', 'pilot', 'employee'];
            $description = 'View personal certificate records from external API using National ID';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'My Certificate page permission added successfully.';
                } else {
                    $error = 'Failed to add My Certificate page permission.';
                }
            } else {
                $message = 'My Certificate page permission already exists.';
            }
            break;

        case 'add_route_price_page':
            $pagePath = 'admin/pricing/routes/index.php';
            $pageName = 'Route Price';
            $requiredRoles = ['admin', 'manager'];
            $description = 'Manage pricing for all active routes with cost breakdown and profit margin calculation';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Route Price page permission added successfully.';
                } else {
                    $error = 'Failed to add Route Price page permission.';
                }
            } else {
                $message = 'Route Price page permission already exists.';
            }
            break;

        case 'add_catering_page':
            $pagePath = 'admin/pricing/catering/index.php';
            $pageName = 'Catering Management';
            $requiredRoles = ['admin', 'manager'];
            $description = 'Manage catering cost configurations (Economy, VIP, CIP, Custom) with detailed cost breakdown';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Catering page permission added successfully.';
                } else {
                    $error = 'Failed to add Catering page permission.';
                }
            } else {
                $message = 'Catering page permission already exists.';
            }
            break;

        case 'add_ifso_costs_page':
            $pagePath = 'admin/pricing/ifso_costs/index.php';
            $pageName = 'IFSO Costs';
            $requiredRoles = ['admin', 'manager'];
            $description = 'Manage IFSO costs including monthly prepayment, salaries, training, transport, premium, and accommodation';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'IFSO Costs page permission added successfully.';
                } else {
                    $error = 'Failed to add IFSO Costs page permission.';
                }
            } else {
                $message = 'IFSO Costs page permission already exists.';
            }
            break;

        case 'add_flight_monitoring_dashboard_page':
            $pagePath = 'dashboard/flight_monitoring.php';
            $pageName = 'Flight Monitoring Dashboard';
            $requiredRoles = ['admin'];
            $description = 'View flight timeline and monitoring dashboard for today, yesterday, and tomorrow';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Flight Monitoring Dashboard page permission added successfully.';
                } else {
                    $error = 'Failed to add Flight Monitoring Dashboard page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Flight Monitoring Dashboard page permission already exists.';
            }
            break;

        case 'add_daily_crew_page':
            $pagePath = 'admin/operations/daily_crew.php';
            $pageName = 'Daily Crew';
            $requiredRoles = ['admin'];
            $description = 'View daily crew members assigned to flights with route and role information';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Daily Crew page permission added successfully.';
                } else {
                    $error = 'Failed to add Daily Crew page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Daily Crew page permission already exists.';
            }
            break;

        case 'add_mel_items_page':
            $pagePath = 'admin/fleet/mel_items/index.php';
            $pageName = 'MEL Items';
            $requiredRoles = ['admin'];
            $description = 'View Minimum Equipment List items from external API';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'MEL Items page permission added successfully.';
                } else {
                    $error = 'Failed to add MEL Items page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'MEL Items page permission already exists.';
            }
            break;

        case 'add_camo_report_page':
            $pagePath = 'admin/fleet/camo_report/index.php';
            $pageName = 'Camo Report';
            $requiredRoles = ['admin'];
            $description = 'Flight operations report with crew assignments and task details';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Camo Report page permission added successfully.';
                } else {
                    $error = 'Failed to add Camo Report page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Camo Report page permission already exists.';
            }
            break;
            
        case 'add_personnel_data_page':
            $pagePath = 'admin/users/personnel_data/index.php';
            $pageName = 'Personnel Data';
            $requiredRoles = ['admin'];
            $description = 'Manage personnel documents and information from personnel_docs table';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Personnel Data page permission added successfully.';
                } else {
                    $error = 'Failed to add Personnel Data page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Personnel Data page permission already exists.';
            }
            break;

        case 'add_flight_statistics_page':
            $pagePath = 'admin/statistics/flight_statistics.php';
            $pageName = 'Flight Statistics';
            $requiredRoles = ['admin', 'manager'];
            $description = 'Aircraft performance and profitability analysis based on fuel consumption, passenger count, and ticket prices';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Flight Statistics page permission added successfully.';
                } else {
                    $error = 'Failed to add Flight Statistics page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Flight Statistics page permission already exists.';
            }
            break;

        case 'add_flight_data_page':
            $pagePath = 'admin/flights/flight_data/index.php';
            $pageName = 'Flight Data';
            $requiredRoles = ['admin', 'manager', 'pilot'];
            $description = 'View today\'s flight timeline with all flight information displayed in a visual timeline format';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Flight Data page permission added successfully.';
                } else {
                    $error = 'Failed to add Flight Data page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Flight Data page permission already exists.';
            }
            break;

        case 'add_contacts_page':
            $pagePath = 'admin/users/contacts.php';
            $pageName = 'Contacts';
            $requiredRoles = ['admin', 'manager', 'pilot', 'employee'];
            $description = 'View user contact information with QR code generation for easy mobile contact saving';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Contacts page permission added successfully.';
                } else {
                    $error = 'Failed to add Contacts page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Contacts page permission already exists.';
            }
            break;

        case 'add_quiz_set_list_page':
            $pagePath = 'admin/training/quiz/index.php';
            $pageName = 'Quiz Set List';
            $requiredRoles = ['admin'];
            $description = 'View all quiz sets with statistics including question count, assignments, and attempts';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Quiz Set List page permission added successfully.';
                } else {
                    $error = 'Failed to add Quiz Set List page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Quiz Set List page permission already exists.';
            }
            break;

        case 'add_quiz_create_set_page':
            $pagePath = 'admin/training/quiz/create_set.php';
            $pageName = 'Create Quiz Set';
            $requiredRoles = ['admin'];
            $description = 'Create quiz sets by selecting questions from course and aircraft categories';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Create Quiz Set page permission added successfully.';
                } else {
                    $error = 'Failed to add Create Quiz Set page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Create Quiz Set page permission already exists.';
            }
            break;

        case 'add_quiz_assign_page':
            $pagePath = 'admin/training/quiz/assign_quiz.php';
            $pageName = 'Assign Quiz';
            $requiredRoles = ['admin'];
            $description = 'Assign quiz sets to specific users for training purposes';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Assign Quiz page permission added successfully.';
                } else {
                    $error = 'Failed to add Assign Quiz page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Assign Quiz page permission already exists.';
            }
            break;

        case 'add_quiz_results_page':
            $pagePath = 'admin/training/quiz/results.php';
            $pageName = 'Quiz Results';
            $requiredRoles = ['admin'];
            $description = 'View quiz attempt results and scores for all users';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Quiz Results page permission added successfully.';
                } else {
                    $error = 'Failed to add Quiz Results page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Quiz Results page permission already exists.';
            }
            break;

        case 'add_my_quiz_page':
            $pagePath = 'admin/profile/my_quiz.php';
            $pageName = 'My Quiz';
            $requiredRoles = ['admin', 'pilot', 'employee'];
            $description = 'View and take assigned quizzes with timer and question navigation';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'My Quiz page permission added successfully.';
                } else {
                    $error = 'Failed to add My Quiz page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'My Quiz page permission already exists.';
            }
            break;

        case 'add_issue_certificate_page':
            $pagePath = 'admin/training/certificate/issue_certificate.php';
            $pageName = 'Issue Certificate';
            $requiredRoles = ['admin'];
            $description = 'Issue training certificates and save to certificates table';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Issue Certificate page permission added successfully.';
                } else {
                    $error = 'Failed to add Issue Certificate page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Issue Certificate page permission already exists.';
            }
            break;

        case 'add_toolbox_page':
            $pagePath = 'admin/fleet/toolbox/index.php';
            $pageName = 'Toolbox';
            $requiredRoles = ['admin'];
            $description = 'Manage toolboxes and tools inventory';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Toolbox page permission added successfully.';
                } else {
                    $error = 'Failed to add Toolbox page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Toolbox page permission already exists.';
            }
            break;

        case 'add_toolbox_view_page':
            $pagePath = 'admin/fleet/toolbox/view_box.php';
            $pageName = 'View Box Contents';
            $requiredRoles = ['admin', 'pilot', 'employee'];
            $description = 'View box contents via QR code scan';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'View Box Contents page permission added successfully.';
                } else {
                    $error = 'Failed to add View Box Contents page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'View Box Contents page permission already exists.';
            }
            break;

        case 'add_activity_log_page':
            $pagePath = 'admin/full_log/activity_log.php';
            $pageName = 'Activity Log';
            $requiredRoles = ['admin'];
            $description = 'View all user activities, page views, and data changes';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'Activity Log page permission added successfully.';
                } else {
                    $error = 'Failed to add Activity Log page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'Activity Log page permission already exists.';
            }
            break;

        case 'add_class_management_page':
            $classPages = [
                ['path' => 'admin/training/class/index.php', 'name' => 'Class Management', 'description' => 'View and manage training classes, schedules, and assignments'],
                ['path' => 'admin/training/class/create.php', 'name' => 'Create Class', 'description' => 'Create new training class with schedules and assignments'],
                ['path' => 'admin/training/class/edit.php', 'name' => 'Edit Class', 'description' => 'Edit existing training class information'],
                ['path' => 'admin/training/class/view.php', 'name' => 'View Class Attendance', 'description' => 'View class details and attendance list']
            ];
            
            $added = 0;
            foreach ($classPages as $page) {
                $existingPermission = getPagePermission($page['path']);
                if (!$existingPermission) {
                    if (addNewPagePermission($page['path'], $page['name'], ['admin'], $page['description'])) {
                        $added++;
                    }
                }
            }
            
            if ($added > 0) {
                $message = "Successfully added {$added} Class Management page(s) to permissions.";
            } else {
                $message = 'All Class Management pages already exist in permissions.';
            }
            break;

        case 'add_my_class_page':
            $pagePath = 'admin/profile/my_class.php';
            $pageName = 'My Class';
            $requiredRoles = ['admin', 'pilot', 'employee'];
            $description = 'View assigned training classes';
            $existingPermission = getPagePermission($pagePath);
            if (!$existingPermission) {
                if (addNewPagePermission($pagePath, $pageName, $requiredRoles, $description)) {
                    $message = 'My Class page permission added successfully.';
                } else {
                    $error = 'Failed to add My Class page permission. Please check the database connection and try again.';
                }
            } else {
                $message = 'My Class page permission already exists.';
            }
            break;
            
    }
}

$permissions = getAllPagePermissions();

// For non-super-admins, hide pages that are exclusively for Super Admin (and not for admin)
if (!$isSuperAdmin) {
    $permissions = array_values(array_filter($permissions, function($perm) {
        $roles = json_decode($perm['required_roles'] ?? '[]', true) ?: [];
        $rolesLower = array_map('strtolower', $roles);
        // If super_admin is required but admin is NOT, hide from normal admins
        return !(in_array('super_admin', $rolesLower, true) && !in_array('admin', $rolesLower, true));
    }));
}

$available_roles = getAllRolesFromTable();

// For non-super-admins, remove super_admin and admin from role selection lists
if (!$isSuperAdmin) {
    $available_roles = array_values(array_filter($available_roles, function($role) {
        $name = strtolower($role['name'] ?? '');
        return $name !== 'super_admin' && $name !== 'admin';
    }));
}

// Function to find sidebar menu location for a page
function findSidebarLocation($pagePath) {
    $sidebarFile = __DIR__ . '/../includes/sidebar.php';
    if (!file_exists($sidebarFile)) {
        return null;
    }
    
    $content = file_get_contents($sidebarFile);
    
    // Check different patterns
    $patterns = [
        'Dashboard' => ['dashboard/'],
        'Fleet Management' => ['admin/fleet/', 'fleet/'],
        'Flight Management' => ['admin/flights/', 'admin/crew/', 'admin/operations/'],
        'User Management' => ['admin/users/', 'admin/roles/'],
        'Recency' => ['admin/users/personnel_recency/', 'admin/users/certificate/'],
        'Settings' => ['admin/settings/', 'admin/odb/', 'admin/role_permission.php', 'admin/settings/last_location/'],
        'Flight Load' => ['admin/flight_load/'],
        'CAA' => ['admin/caa/'],
        'E-Lib' => ['admin/elib/'],
        'EFB' => ['admin/efb/'],
        'ODB Notifications' => ['admin/odb/list.php'],
        'Profile' => ['admin/profile/'],
        'User Management' => ['admin/users/location/'],
        'Transport' => ['admin/transport/'],
        'Messages' => ['admin/messages/'],
        'Training' => ['admin/training/']
    ];
    
    foreach ($patterns as $menuName => $paths) {
        foreach ($paths as $path) {
            if (strpos($pagePath, $path) !== false) {
                return $menuName;
            }
        }
    }
    
    // Check if it's in sidebar file content
    $pagePathEscaped = preg_quote($pagePath, '/');
    if (preg_match('/' . $pagePathEscaped . '/', $content)) {
        // Try to find the menu section
        if (preg_match('/(Fleet Management|Flight Management|User Management|Recency|Settings|Flight Load|CAA|E-Lib|EFB|Transport|Messages).*?' . $pagePathEscaped . '/s', $content, $matches)) {
            return $matches[1] ?? 'Other';
        }
    }
    
    return 'Not in Sidebar';
}

// Function to build tree structure from permissions
function buildPermissionTree($permissions) {
    $tree = [];
    
    foreach ($permissions as $permission) {
        $path = $permission['page_path'];
        $parts = explode('/', $path);
        
        // Remove empty parts
        $parts = array_values(array_filter($parts, function($part) {
            return !empty($part);
        }));
        
        $current = &$tree;
        $fullPath = '';
        
        foreach ($parts as $index => $part) {
            $fullPath .= ($fullPath ? '/' : '') . $part;
            $isLast = ($index === count($parts) - 1);
            $isFile = $isLast && (strpos($part, '.php') !== false || strpos($part, '.') !== false);
            
            if ($isFile) {
                // It's a file - add to _files array
                if (!isset($current['_files'])) {
                    $current['_files'] = [];
                }
                $current['_files'][] = [
                    'permission' => $permission,
                    'path' => $path,
                    'name' => $part,
                    'full_path' => $fullPath
                ];
            } else {
                // It's a directory
                if (!isset($current[$part])) {
                    $current[$part] = [
                        'name' => $part,
                        'path' => $fullPath,
                        'children' => []
                    ];
                }
                $current = &$current[$part]['children'];
            }
        }
    }
    
    return $tree;
}

// Build tree structure
$permissionTree = buildPermissionTree($permissions);

// Function to render tree view recursively
function renderTreeView($tree, $level = 0, $parentPath = '', $parentFolderId = '') {
    $html = '';
    $indent = $level * 24;
    
    foreach ($tree as $key => $node) {
        if ($key === '_files') {
            // Render files
            foreach ($node as $file) {
                $permission = $file['permission'];
                $requiredRoles = json_decode($permission['required_roles'] ?? '[]', true) ?: [];
                $individualAccess = getIndividualAccessForPage($permission['page_path']);
                $sidebarLocation = findSidebarLocation($permission['page_path']);
                
                $fileClasses = 'permission-row hover:bg-gray-50 dark:hover:bg-gray-700';
                if (!empty($parentFolderId)) {
                    $fileClasses .= ' folder-child hidden';
                }
                $html .= '<tr class="' . $fileClasses . '" data-page-name="' . htmlspecialchars(strtolower($permission['page_name'])) . '" data-parent-folder="' . htmlspecialchars($parentFolderId) . '" data-page-path="' . htmlspecialchars($permission['page_path']) . '">';
                $html .= '<td class="px-6 py-4">';
                $html .= '<div class="flex items-center" style="padding-left: ' . $indent . 'px;">';
                $html .= '<i class="fas fa-file-code text-gray-400 mr-2"></i>';
                $html .= '<div class="text-sm font-medium text-gray-900 dark:text-white">';
                $html .= htmlspecialchars($permission['page_name']);
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap">';
                $html .= '<div class="text-sm text-gray-500 dark:text-gray-400">';
                $html .= htmlspecialchars($permission['page_path']);
                $html .= '</div>';
                $html .= '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap">';
                $html .= '<div class="flex flex-wrap gap-1">';
                foreach ($requiredRoles as $role) {
                    $html .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">';
                    $html .= htmlspecialchars(ucfirst($role));
                    $html .= '</span>';
                }
                $html .= '</div>';
                $html .= '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap individual-access-cell" data-page-path="' . htmlspecialchars($permission['page_path']) . '">';
                $html .= renderIndividualAccessCell($permission['page_path'], $permission['page_name'], $individualAccess);
                $html .= '</td>';
                $html .= '<td class="px-6 py-4">';
                $html .= '<div class="text-sm text-gray-500 dark:text-gray-400">';
                $html .= htmlspecialchars($permission['description'] ?? 'No description');
                $html .= '</div>';
                $html .= '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap">';
                $html .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . ($sidebarLocation && $sidebarLocation !== 'Not in Sidebar' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300') . '" title="Sidebar Location">';
                $html .= '<i class="fas fa-bars mr-1"></i>';
                $html .= htmlspecialchars($sidebarLocation ?? 'Not in Sidebar');
                $html .= '</span>';
                $html .= '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">';
                $html .= '<button onclick="openEditPermissionModal(' . $permission['id'] . ', \'' . htmlspecialchars($permission['page_name']) . '\', ' . htmlspecialchars(json_encode($requiredRoles)) . ', \'' . htmlspecialchars($permission['description'] ?? '') . '\')" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">';
                $html .= '<i class="fas fa-edit"></i>';
                $html .= '</button>';
                $html .= '</td>';
                $html .= '</tr>';
            }
        } else {
            // Render directory
            $nodeId = 'tree-' . md5($node['path']);
            $hasChildren = !empty($node['children']);
            
            $folderClasses = 'tree-folder hover:bg-gray-50 dark:hover:bg-gray-700';
            if (!empty($parentFolderId)) {
                $folderClasses .= ' folder-child hidden';
            }
            $html .= '<tr class="' . $folderClasses . '" data-folder-id="' . $nodeId . '" data-parent-folder="' . htmlspecialchars($parentFolderId) . '">';
            $html .= '<td class="px-6 py-3" colspan="7">';
            $html .= '<div class="flex items-center cursor-pointer" style="padding-left: ' . $indent . 'px;" onclick="toggleFolder(\'' . $nodeId . '\')">';
            $html .= '<i class="fas fa-chevron-right text-xs text-gray-400 mr-2 folder-icon transition-transform duration-200" id="icon-' . $nodeId . '"></i>';
            $html .= '<i class="fas fa-folder text-yellow-500 mr-2"></i>';
            $html .= '<span class="text-sm font-semibold text-gray-700 dark:text-gray-300">' . htmlspecialchars(ucfirst($node['name'])) . '</span>';
            $html .= '<span class="ml-2 text-xs text-gray-500 dark:text-gray-400">(' . $node['path'] . ')</span>';
            $html .= '</div>';
            $html .= '</td>';
            $html .= '</tr>';
            
            // Render children recursively - they will be rendered as separate rows
            if ($hasChildren) {
                $html .= renderTreeView($node['children'], $level + 1, $node['path'], $nodeId);
            }
        }
    }
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Permissions - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Page Permissions</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage access permissions for system pages</p>
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

                <!-- Permissions Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Page Access Permissions</h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Configure which roles can access each page</p>
                            </div>
                            <div>
                                <button onclick="openQuickAddModal()" 
                                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <i class="fas fa-plus mr-2"></i>
                                    Quick Add
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Box -->
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" id="pageNameSearch" 
                                   placeholder="Search by Page Name..." 
                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="permissionsTable">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Page Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Path</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Required Roles</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Individual Access</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sidebar Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if (empty($permissions)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No permissions found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php echo renderTreeView($permissionTree); ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Permission Modal -->
    <div id="editPermissionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Page Permission</h3>
                    <button onclick="closeEditPermissionModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4" id="editPermissionForm" onsubmit="return submitEditPermissionForm(event)">
                    <input type="hidden" name="action" value="update_permission">
                    <input type="hidden" id="edit_permission_id" name="id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Page Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_page_name" name="page_name" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Required Roles
                        </label>
                        <div class="space-y-2">
                            <?php foreach ($available_roles as $role): ?>
                                <label class="flex items-center">
                                    <input type="checkbox" name="required_roles[]" value="<?php echo htmlspecialchars($role['name']); ?>" 
                                           class="edit-role-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($role['display_name']); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div>
                        <label for="edit_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Description
                        </label>
                        <textarea id="edit_description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Enter description for this page permission"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditPermissionModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Update Permission
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Page Modal -->
    <div id="addPageModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add New Page Permission</h3>
                    <button onclick="closeAddPageModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_manual_page">
                    
                    <div>
                        <label for="page_path" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Page Path *
                        </label>
                        <input type="text" id="page_path" name="page_path" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="e.g., admin/new-page.php">
                    </div>
                    
                    <div>
                        <label for="page_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Page Name *
                        </label>
                        <input type="text" id="page_name" name="page_name" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="e.g., New Page">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Required Roles *
                        </label>
                        <div class="space-y-2">
                            <?php foreach ($available_roles as $role): ?>
                                <label class="flex items-center">
                                    <input type="checkbox" name="required_roles[]" value="<?php echo htmlspecialchars($role['name']); ?>" 
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($role['display_name']); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Description
                        </label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Enter description for this page"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddPageModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200">
                            Add Page
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scan New Pages Modal -->
    <div id="scanPagesModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Scan for New Pages</h3>
                    <button onclick="closeScanPagesModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        This will scan the system for new PHP pages and add them to permissions with default roles.
                    </p>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_new_pages">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Default Roles for New Pages
                        </label>
                        <div class="space-y-2">
                            <?php foreach ($available_roles as $role): ?>
                                <label class="flex items-center">
                                    <input type="checkbox" name="default_roles[]" value="<?php echo htmlspecialchars($role['name']); ?>" 
                                           <?php echo $role['name'] === 'admin' ? 'checked' : ''; ?>
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($role['display_name']); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeScanPagesModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                            Scan & Add Pages
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Individual Access Modal -->
    <div id="individualAccessModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-[500px] shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Grant Individual Access</h3>
                    <button onclick="closeIndividualAccessModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-medium">Page:</span> <span id="individual_page_name"></span>
                    </p>
                </div>
                
                <form method="POST" class="space-y-4" id="grantIndividualAccessForm" onsubmit="return submitGrantIndividualAccessForm(event)">
                    <input type="hidden" name="action" value="grant_individual_access">
                    <input type="hidden" id="individual_page_path" name="page_path">
                    
                    <div>
                        <label for="user_search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Search Users *
                        </label>
                        <div class="relative">
                            <input type="text" id="user_search" placeholder="Type to search users..."
                                   class="w-full px-3 py-2 pl-10 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                        <div id="user_search_results" class="mt-2 max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 hidden">
                            <!-- Search results will be populated here -->
                        </div>
                        <div id="selected_users_display" class="mt-3 space-y-2">
                            <!-- Selected users will be displayed here -->
                            </div>
                        <div id="selected_users_hidden" class="hidden">
                            <!-- Hidden inputs for selected user IDs -->
                        </div>
                    </div>
                    
                    <div>
                        <label for="expires_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Expires At (Optional)
                        </label>
                        <input type="datetime-local" id="expires_at" name="expires_at"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Leave empty for permanent access</p>
                    </div>
                    
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Notes (Optional)
                        </label>
                        <textarea id="notes" name="notes" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Reason for granting access..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeIndividualAccessModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200">
                            Grant Access
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Add Modal -->
    <div id="quickAddModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Quick Add Page Permissions</h3>
                    <button onclick="closeQuickAddModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Select a page to quickly add its permission to the system.
                    </p>
                </div>
                
                <div class="max-h-96 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <button onclick="openAddPageModal(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-plus mr-2"></i>Add Page
                        </button>
                        
                        <div class="border-t border-gray-200 dark:border-gray-700 my-2 md:col-span-2"></div>
                        
                        <button onclick="addRouteEditPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-route mr-2"></i>Route Edit
                        </button>
                        <button onclick="addDelayCodesPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-clock mr-2"></i>Delay Codes
                        </button>
                        <button onclick="addRaimonDelayCodePage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-code mr-2"></i>Raimon Delay Code
                        </button>
                        <button onclick="addETLReportPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-chart-line mr-2"></i>ETL Report
                        </button>
                        <button onclick="addAirsarReportPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-file-chart-line mr-2"></i>Airsar Report (ETL)
                        </button>
                        <button onclick="addDispatchHandoverPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-clipboard-check mr-2"></i>Dispatch Handover
                        </button>
                        <button onclick="addRLSSPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-search mr-2"></i>RLSS
                        </button>
                        <button onclick="addEFBPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-briefcase mr-2"></i>EFB
                        </button>
                        <button onclick="addEFBDeletePage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-trash mr-2"></i>EFB Delete
                        </button>
                        <button onclick="addCrewLocationPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-map-marker-alt mr-2"></i>Crew Location
                        </button>
                        <button onclick="addMetarTaforPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-cloud-sun mr-2"></i>METAR/TAFOR
                        </button>
                        <button onclick="addCertificatePage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-certificate mr-2"></i>Certificate
                        </button>
                        <button onclick="addDeleteCertificatePage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-trash mr-2"></i>Delete Certificate
                        </button>
                        <button onclick="addCallCenterPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-phone-alt mr-2"></i>Call Center
                        </button>
                        <button onclick="addHiringPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-user-tie mr-2"></i>Hiring
                        </button>
                        <button onclick="addMyLocationPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-map-marker-alt mr-2"></i>Get My Location
                        </button>
                        <button onclick="addUserLocationPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-map-marked-alt mr-2"></i>User Location
                        </button>
                        <button onclick="addLastLocationPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-map-marker-alt mr-2"></i>Last Location
                        </button>
                        <button onclick="addRecencyManagementPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-cog mr-2"></i>Recency Management
                        </button>
                        <button onclick="addSetRecencyPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-list-check mr-2"></i>Set Recency's
                        </button>
                        <button onclick="addHandoverPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-exchange-alt mr-2"></i>HandOver
                        </button>
                        <button onclick="addASRPages(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-shield-alt mr-2"></i>ASR Pages
                        </button>
                        <button onclick="addJourneyLogListPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-list-alt mr-2"></i>Journey Log List
                        </button>
                        <button onclick="addFlightRolesPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-user-tag mr-2"></i>Flight Roles
                        </button>
                        <button onclick="addRosterIndexPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-code mr-2"></i>Shift Code
                        </button>
                        <button onclick="addRosterAddPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-plus-circle mr-2"></i>Add Shift Code
                        </button>
                        <button onclick="addRosterEditPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-edit mr-2"></i>Edit Shift Code
                        </button>
                        <button onclick="addRosterManagementPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-calendar-check mr-2"></i>Roster Management
                        </button>
                        <button onclick="addCAACityPerPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-chart-bar mr-2"></i>CAA City-Pairs Domestic
                        </button>
                        <button onclick="addCAACityPerInternationalPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-globe mr-2"></i>CAA City-Pairs International
                        </button>
                        <button onclick="addCAARevenuePage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-dollar-sign mr-2"></i>CAA Revenue
                        </button>
                        <button onclick="addCAADivertFlightPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-exclamation-triangle mr-2"></i>CAA Divert Flight
                        </button>
                        <button onclick="addCAADailyReportPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-file-excel mr-2"></i>CAA Daily Report
                        </button>
                        <button onclick="addNotamPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-exclamation-triangle mr-2"></i>NOTAM
                        </button>
                        <button onclick="addRouteFixTimePage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-clock mr-2"></i>Route Fix Time
                        </button>
                        <button onclick="addPayloadDataPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-weight mr-2"></i>Payload Data
                        </button>
                        <button onclick="addPayloadCalculatorPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-calculator mr-2"></i>Payload Calculator
                        </button>
                        <button onclick="addMetarTaforHistoryPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-history mr-2"></i>METAR/TAFOR History
                        </button>
                        <button onclick="addPassengerByAircraftPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-users mr-2"></i>Passenger By Aircraft
                        </button>
                        <button onclick="addBackupDbPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-database mr-2"></i>Backup Database
                        </button>
                        <button onclick="addOfficeTimePage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-clock mr-2"></i>Office Time
                        </button>
                        <button onclick="addMaintenanceModePage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-tools mr-2"></i>Maintenance Mode
                        </button>
                        <button onclick="addNotificationPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-bell mr-2"></i>Notifications
                        </button>
                        <button onclick="addMessagePage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-comments mr-2"></i>Messages
                        </button>
                        <button onclick="addTripManagementPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-truck mr-2"></i>Trip Management
                        </button>
                        <button onclick="addOFPPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-file-alt mr-2"></i>OFP Viewer
                        </button>
                        <button onclick="addSMSPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-sms mr-2"></i>SMS Management
                        </button>
                        <button onclick="addAboutPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-info-circle mr-2"></i>About Page
                        </button>
                        <button onclick="addMyRecencyPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2 inline" fill="currentColor" viewBox="0 0 448 512">
                                <path d="M32 416c0 17.7 14.3 32 32 32l320 0c17.7 0 32-14.3 32-32l0-320c0-17.7-14.3-32-32-32L64 64C46.3 64 32 78.3 32 96l0 320zM0 96C0 60.7 28.7 32 64 32l320 0c35.3 0 64 28.7 64 64l0 320c0 35.3-28.7 64-64 64L64 480c-35.3 0-64-28.7-64-64L0 96zm96 80c0-8.8 7.2-16 16-16l48 0 0-24c0-8.8 7.2-16 16-16s16 7.2 16 16l0 24 144 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-144 0 0 24c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-24-48 0c-8.8 0-16-7.2-16-16zm0 160c0-8.8 7.2-16 16-16l144 0 0-24c0-8.8 7.2-16 16-16s16 7.2 16 16l0 24 48 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-48 0 0 24c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-24-144 0c-8.8 0-16-7.2-16-16z"/>
                            </svg>My Recency
                        </button>
                        <button onclick="addMyCertificatePage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-certificate mr-2"></i>My Certificate
                        </button>
                        <button onclick="addRoutePricePage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-route mr-2"></i>Route Price
                        </button>
                        <button onclick="addCateringPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-utensils mr-2"></i>Catering
                        </button>
                        <button onclick="addIFSOCostsPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-dollar-sign mr-2"></i>IFSO Costs
                        </button>
                        <button onclick="addFlightMonitoringDashboardPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-plane-departure mr-2"></i>Flight Monitoring Dashboard
                        </button>
                        <button onclick="addDailyCrewPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-calendar-day mr-2"></i>Daily Crew
                        </button>
                        <button onclick="addMELItemsPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-clipboard-list mr-2"></i>MEL Items
                        </button>
                        <button onclick="addCamoReportPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-file-alt mr-2"></i>Camo Report
                        </button>
                        <button onclick="addPersonnelDataPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-id-card mr-2"></i>Personnel Data
                        </button>
                        <button onclick="addFlightStatisticsPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-chart-line mr-2"></i>Flight Statistics
                        </button>
                        <button onclick="addFlightDataPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-database mr-2"></i>Flight Data
                        </button>
                        <button onclick="addContactsPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-address-book mr-2"></i>Contacts
                        </button>
                        <button onclick="addQuizSetListPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-list mr-2"></i>Quiz Set List
                        </button>
                        <button onclick="addQuizCreateSetPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-graduation-cap mr-2"></i>Create Quiz Set
                        </button>
                        <button onclick="addQuizAssignPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-user-check mr-2"></i>Assign Quiz
                        </button>
                        <button onclick="addQuizResultsPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-chart-bar mr-2"></i>Quiz Results
                        </button>
                        <button onclick="addMyQuizPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-question-circle mr-2"></i>My Quiz
                        </button>
                        <button onclick="addIssueCertificatePage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-certificate mr-2"></i>Issue Certificate
                        </button>
                        <button onclick="addToolboxPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-toolbox mr-2"></i>Toolbox
                        </button>
                        <button onclick="addToolboxViewPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-qrcode mr-2"></i>View Box Contents
                        </button>
                        <button onclick="addActivityLogPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-user-clock mr-2"></i>Activity Log
                        </button>
                        <button onclick="addClassManagementPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-chalkboard-teacher mr-2"></i>Class Management
                        </button>
                        <button onclick="addMyClassPage(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-chalkboard-teacher mr-2"></i>My Class
                        </button>
                        
                        <div class="border-t border-gray-200 dark:border-gray-700 my-2 md:col-span-2"></div>
                        
                        <button onclick="scanForNewPages(); closeQuickAddModal();" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-search mr-2"></i>Scan New Pages
                        </button>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button onclick="closeQuickAddModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search functionality with tree expansion
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('pageNameSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    
                    if (searchTerm === '') {
                        // Reset to default state - hide all children
                        const allRows = document.querySelectorAll('.permission-row, .tree-folder');
                        allRows.forEach(row => {
                            if (row.classList.contains('folder-child')) {
                                row.classList.add('hidden');
                            } else {
                                row.classList.remove('hidden');
                            }
                        });
                        
                        // Reset all folder icons
                        document.querySelectorAll('.folder-icon').forEach(icon => {
                            icon.classList.remove('fa-chevron-down', 'rotate-90');
                            icon.classList.add('fa-chevron-right');
                        });
                        return;
                    }
                    
                    // Find all matching rows
                    const allRows = document.querySelectorAll('.permission-row');
                    const matchingRows = [];
                    const matchingRowIds = new Set();
                    
                    allRows.forEach(row => {
                        const pageName = row.getAttribute('data-page-name') || '';
                        const pagePath = row.querySelector('td:nth-child(2)')?.textContent?.toLowerCase() || '';
                        
                        if (pageName.includes(searchTerm) || pagePath.includes(searchTerm)) {
                            matchingRows.push(row);
                            matchingRowIds.add(row);
                            row.classList.remove('hidden');
                        } else {
                            row.classList.add('hidden');
                        }
                    });
                    
                    // Expand all parent folders of matching rows
                    const foldersToExpand = new Set();
                    
                    matchingRows.forEach(row => {
                        let parentFolderId = row.getAttribute('data-parent-folder');
                        
                        // Traverse up the tree to find all parent folders
                        while (parentFolderId) {
                            foldersToExpand.add(parentFolderId);
                            
                            // Find the parent folder row
                            const parentFolder = document.querySelector('[data-folder-id="' + parentFolderId + '"]');
                            if (parentFolder) {
                                parentFolder.classList.remove('hidden');
                                
                                // Get the parent of this folder
                                parentFolderId = parentFolder.getAttribute('data-parent-folder');
                            } else {
                                break;
                            }
                        }
                    });
                    
                    // Expand all folders that contain matching results
                    foldersToExpand.forEach(folderId => {
                        const folderRow = document.querySelector('[data-folder-id="' + folderId + '"]');
                        const icon = document.getElementById('icon-' + folderId);
                        
                        if (folderRow && icon && !icon.classList.contains('fa-chevron-down')) {
                            // Expand the folder
                            const childRows = document.querySelectorAll('[data-parent-folder="' + folderId + '"]');
                            childRows.forEach(childRow => {
                                childRow.classList.remove('hidden');
                            });
                            
                            icon.classList.remove('fa-chevron-right');
                            icon.classList.add('fa-chevron-down');
                            icon.classList.add('rotate-90');
                        }
                    });
                    
                    // Hide folders that don't have any matching children
                    const allFolders = document.querySelectorAll('.tree-folder');
                    allFolders.forEach(folder => {
                        const folderId = folder.getAttribute('data-folder-id');
                        if (!folderId) return;
                        
                        const childRows = document.querySelectorAll('[data-parent-folder="' + folderId + '"]');
                        let hasVisibleChild = false;
                        
                        childRows.forEach(child => {
                            // Check if child is visible (not hidden)
                            if (!child.classList.contains('hidden')) {
                                hasVisibleChild = true;
                            }
                        });
                        
                        // If folder is in the path to a match, make sure it's visible
                        if (foldersToExpand.has(folderId)) {
                            folder.classList.remove('hidden');
                        } else {
                            // If folder has no visible children, hide it (unless it's a root folder with no parent)
                            const parentId = folder.getAttribute('data-parent-folder');
                            if (!hasVisibleChild && parentId) {
                                folder.classList.add('hidden');
                            } else if (hasVisibleChild) {
                                folder.classList.remove('hidden');
                            }
                        }
                    });
                });
            }
        });

        // Quick Add Modal Functions
        function openQuickAddModal() {
            document.getElementById('quickAddModal').classList.remove('hidden');
        }

        function closeQuickAddModal() {
            document.getElementById('quickAddModal').classList.add('hidden');
        }

        // Toggle folder in tree view
        function toggleFolder(folderId) {
            const folderRow = document.querySelector('[data-folder-id="' + folderId + '"]');
            const icon = document.getElementById('icon-' + folderId);
            
            if (folderRow && icon) {
                const isExpanded = icon.classList.contains('fa-chevron-down');
                
                // Find all child rows (files and subfolders) that belong to this folder
                const childRows = document.querySelectorAll('[data-parent-folder="' + folderId + '"]');
                
                // Toggle all direct children
                childRows.forEach(row => {
                    if (isExpanded) {
                        row.classList.add('hidden');
                        // If it's a folder and it's expanded, collapse it first
                        if (row.classList.contains('tree-folder')) {
                            const nestedFolderId = row.getAttribute('data-folder-id');
                            const nestedIcon = document.getElementById('icon-' + nestedFolderId);
                            if (nestedIcon && nestedIcon.classList.contains('fa-chevron-down')) {
                                toggleFolder(nestedFolderId);
                            }
                        }
                    } else {
                        row.classList.remove('hidden');
                    }
                });
                
                // Toggle icon
                if (isExpanded) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.remove('rotate-90');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-down');
                    icon.classList.add('rotate-90');
                }
            }
        }

        function openEditPermissionModal(id, pageName, requiredRoles, description) {
            document.getElementById('edit_permission_id').value = id;
            document.getElementById('edit_page_name').value = pageName;
            document.getElementById('edit_description').value = description;
            
            // Clear all checkboxes first
            document.querySelectorAll('.edit-role-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Check the required roles
            requiredRoles.forEach(role => {
                const checkbox = document.querySelector(`input[name="required_roles[]"][value="${role}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
            
            document.getElementById('editPermissionModal').classList.remove('hidden');
        }

        function closeEditPermissionModal() {
            document.getElementById('editPermissionModal').classList.add('hidden');
        }

        function openAddPageModal() {
            document.getElementById('addPageModal').classList.remove('hidden');
        }

        function closeAddPageModal() {
            document.getElementById('addPageModal').classList.add('hidden');
        }

        function openScanPagesModal() {
            document.getElementById('scanPagesModal').classList.remove('hidden');
        }

        function closeScanPagesModal() {
            document.getElementById('scanPagesModal').classList.add('hidden');
        }

        function openIndividualAccessModal(pagePath, pageName) {
            document.getElementById('individual_page_path').value = pagePath;
            document.getElementById('individual_page_name').textContent = pageName;
            
            // Load users without individual access for this page
            loadUsersForIndividualAccess(pagePath);
            
            document.getElementById('individualAccessModal').classList.remove('hidden');
        }

        function closeIndividualAccessModal() {
            document.getElementById('individualAccessModal').classList.add('hidden');
            clearSelectedUsers();
        }

        let allUsers = [];
        let currentPagePath = '';

        function loadUsersForIndividualAccess(pagePath) {
            currentPagePath = pagePath;
            const userSearch = document.getElementById('user_search');
            userSearch.value = '';
            clearSelectedUsers();
            
            // Make AJAX call to get users without individual access
            fetch(`api/get_users_without_access.php?page_path=${encodeURIComponent(pagePath)}`)
                .then(response => response.json())
                .then(data => {
                    allUsers = data.users || [];
                    // Initialize search functionality
                    setupUserSearch();
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                    allUsers = [];
                });
        }

        function setupUserSearch() {
            const userSearch = document.getElementById('user_search');
            const searchResults = document.getElementById('user_search_results');
            
            userSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                
                if (query.length < 2) {
                    searchResults.classList.add('hidden');
                    return;
                }
                
                const filteredUsers = allUsers.filter(user => {
                    const fullName = `${user.first_name} ${user.last_name}`.toLowerCase();
                    const position = (user.position || '').toLowerCase();
                    const role = (user.role || '').toLowerCase();
                    const email = (user.email || '').toLowerCase();
                    
                    return fullName.includes(query) || 
                           position.includes(query) || 
                           role.includes(query) ||
                           email.includes(query);
                });
                
                displaySearchResults(filteredUsers);
            });
            
            // Hide results when clicking outside
            document.addEventListener('click', function(e) {
                if (!userSearch.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.classList.add('hidden');
                }
            });
        }

        function displaySearchResults(users) {
            const searchResults = document.getElementById('user_search_results');
            
            if (users.length === 0) {
                searchResults.innerHTML = '<div class="p-3 text-sm text-gray-500 dark:text-gray-400">No users found</div>';
            } else {
                searchResults.innerHTML = users.map(user => {
                    const isSelected = selectedUsers.find(u => u.id === user.id);
                    const selectedClass = isSelected ? 'opacity-50 cursor-not-allowed bg-gray-100 dark:bg-gray-800' : '';
                    const selectedText = isSelected ? '<span class="text-xs text-green-600 dark:text-green-400 ml-2">(Selected)</span>' : '';
                    
                    const firstName = escapeHtml(user.first_name || '');
                    const lastName = escapeHtml(user.last_name || '');
                    const position = escapeHtml(user.position || 'N/A');
                    const role = escapeHtml(user.role || '');
                    const fullName = `${firstName} ${lastName}`;
                    
                    const onclickAttr = !isSelected ? `onclick="selectUser(${user.id}, '${fullName.replace(/'/g, "\\'")}', '${position.replace(/'/g, "\\'")}', '${role.replace(/'/g, "\\'")}')"` : '';
                    
                    return `
                        <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0 ${selectedClass}" 
                             ${onclickAttr}>
                            <div class="font-medium text-gray-900 dark:text-white">
                                ${fullName}${selectedText}
                    </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">${position} - ${role}</div>
                            ${user.email ? `<div class="text-xs text-gray-400 dark:text-gray-500">${escapeHtml(user.email)}</div>` : ''}
                        </div>
                    `;
                }).join('');
            }
            
            searchResults.classList.remove('hidden');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        let selectedUsers = [];

        function selectUser(userId, userName, position, role) {
            // Check if user is already selected
            if (selectedUsers.find(u => u.id === userId)) {
                return;
            }
            
            // Add user to selected list
            selectedUsers.push({
                id: userId,
                name: userName,
                position: position,
                role: role
            });
            
            // Update display
            updateSelectedUsersDisplay();
            
            // Clear search
            document.getElementById('user_search_results').classList.add('hidden');
            document.getElementById('user_search').value = '';
        }

        function removeSelectedUser(userId) {
            selectedUsers = selectedUsers.filter(u => u.id !== userId);
            updateSelectedUsersDisplay();
        }

        function clearSelectedUsers() {
            selectedUsers = [];
            updateSelectedUsersDisplay();
            document.getElementById('user_search').value = '';
            document.getElementById('user_search_results').classList.add('hidden');
        }

        function updateSelectedUsersDisplay() {
            const displayDiv = document.getElementById('selected_users_display');
            const hiddenDiv = document.getElementById('selected_users_hidden');
            
            if (selectedUsers.length === 0) {
                displayDiv.innerHTML = '';
                hiddenDiv.innerHTML = '';
                return;
            }
            
            // Update visible display
            displayDiv.innerHTML = selectedUsers.map(user => `
                <div class="flex items-center justify-between p-2 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-md">
                    <span class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        ${user.name} (${user.position} - ${user.role})
                    </span>
                    <button type="button" onclick="removeSelectedUser(${user.id})" 
                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 ml-2">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `).join('');
            
            // Update hidden inputs for form submission
            hiddenDiv.innerHTML = selectedUsers.map(user => 
                `<input type="hidden" name="user_ids[]" value="${user.id}">`
            ).join('');
        }

        function validateIndividualAccessForm(event) {
            if (selectedUsers.length === 0) {
                event.preventDefault();
                alert('Please select at least one user.');
                return false;
            }
            return true;
        }

        function submitEditPermissionForm(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Disable submit button
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
            
            fetch('', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
                
                if (data.success) {
                    // Show success message
                    showNotification(data.message, 'success');
                    // Close modal
                    closeEditPermissionModal();
                    // Don't reload - stay on the same page with filters
                } else {
                    // Show error message
                    showNotification(data.error || 'Failed to update permission', 'error');
                }
            })
            .catch(error => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
            
            return false;
        }

        function submitGrantIndividualAccessForm(event) {
            event.preventDefault();
            
            // Validate first
            if (selectedUsers.length === 0) {
                alert('Please select at least one user.');
                return false;
            }
            
            const form = event.target;
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Add selected user IDs to form data
            selectedUsers.forEach(user => {
                formData.append('user_ids[]', user.id);
            });
            
            // Disable submit button
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Granting...';
            
            fetch('', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
                
                if (data.success) {
                    // Show success message
                    showNotification(data.message, 'success');
                    // Close modal
                    closeIndividualAccessModal();
                    
                    // Update Individual Access cell if HTML is provided
                    if (data.individualAccessHtml && data.pagePath) {
                        updateIndividualAccessCell(data.pagePath, data.individualAccessHtml);
                    }
                } else {
                    // Show error message
                    showNotification(data.error || 'Failed to grant access', 'error');
                }
            })
            .catch(error => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
            
            return false;
        }

        function showNotification(message, type) {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.ajax-notification');
            existingNotifications.forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = `ajax-notification fixed top-4 right-4 z-50 px-6 py-4 rounded-md shadow-lg transition-all duration-300 ${
                type === 'success' 
                    ? 'bg-green-500 text-white' 
                    : 'bg-red-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-20px)';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }

        function updateIndividualAccessCell(pagePath, html) {
            // Find the cell with matching data-page-path
            // Escape special characters for CSS selector
            const escapedPath = pagePath.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, '\\$&');
            const cell = document.querySelector(`td.individual-access-cell[data-page-path="${escapedPath}"]`);
            if (cell) {
                cell.innerHTML = html;
            } else {
                // Fallback: try to find by attribute value (less strict)
                const allCells = document.querySelectorAll('td.individual-access-cell');
                allCells.forEach(c => {
                    if (c.getAttribute('data-page-path') === pagePath) {
                        c.innerHTML = html;
                    }
                });
            }
        }

        function revokeIndividualAccess(pagePath, userId) {
            if (confirm('Are you sure you want to revoke individual access for this user?')) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="revoke_individual_access">
                    <input type="hidden" name="page_path" value="${pagePath}">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function scanForNewPages() {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Scanning...';
            button.disabled = true;

            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="scan_new_pages">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function addRouteEditPage() {
            if (confirm('Add Route Edit page permission with admin role?')) {
                // Show loading state
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_route_edit_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addDelayCodesPage() {
            if (confirm('Add Delay Codes page permission with admin role?')) {
                // Show loading state
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_delay_codes_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addRaimonDelayCodePage() {
            if (confirm('Add Raimon Delay Code page permission with admin role?')) {
                // Show loading state
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_raimon_delay_code_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addETLReportPage() {
            if (confirm('Add ETL Report page permission with admin role?')) {
                // Show loading state
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_etl_report_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addAirsarReportPage() {
            if (confirm('Add Airsar Report (ETL) page permission with admin role?')) {
                // Show loading state
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_airsar_report_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addDispatchHandoverPage() {
            if (confirm('Add Dispatch Handover page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_dispatch_handover_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addRLSSPage() {
            if (confirm('Add RLSS page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_rlss_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addEFBPage() {
            if (confirm('Add EFB page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_efb_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addEFBDeletePage() {
            if (confirm('Add EFB Delete page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_efb_delete_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addCrewLocationPage() {
            if (confirm('Add Crew Location page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_crew_location_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addMetarTaforPage() {
            if (confirm('Add METAR/TAFOR page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_metar_tafor_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addCertificatePage() {
            if (confirm('Add Certificate page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_certificate_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addDeleteCertificatePage() {
            if (confirm('Add Delete Certificate page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_delete_certificate_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addCallCenterPage() {
            if (confirm('Add Call Center page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_call_center_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addHiringPage() {
            if (confirm('Add Hiring page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_hiring_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addMyLocationPage() {
            if (confirm('Add Get My Location page permission with admin, pilot, and employee roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_my_location_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addUserLocationPage() {
            if (confirm('Add User Location page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_user_location_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addLastLocationPage() {
            if (confirm('Add Last Location page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_last_location_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addRecencyManagementPage() {
            if (confirm('Add Recency Management page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_recency_management_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addSetRecencyPage() {
            if (confirm('Add Set Recency\'s page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_set_recency_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addHandoverPage() {
            if (confirm('Add HandOver page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_handover_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addASRPages() {
            if (confirm('Add ASR (Air Safety Reports) pages permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_asr_pages">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addJourneyLogListPage() {
            if (confirm('Add Journey Log List page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_journey_log_list_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addFlightRolesPage() {
            if (confirm('Add Flight Roles page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_flight_roles_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addRosterIndexPage() {
            if (confirm('Add Shift Code page permission with admin and manager roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_roster_index_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addRosterAddPage() {
            if (confirm('Add Add Shift Code page permission with admin and manager roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_roster_add_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addRosterEditPage() {
            if (confirm('Add Edit Shift Code page permission with admin and manager roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_roster_edit_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addRosterManagementPage() {
            if (confirm('Add Roster Management page permission with admin and manager roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_roster_management_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addCAACityPerPage() {
            if (confirm('Add CAA City-Pairs Domestic page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_caa_city_per_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addCAACityPerInternationalPage() {
            if (confirm('Add CAA City-Pairs International page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_caa_city_per_international_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addCAARevenuePage() {
            if (confirm('Add CAA Revenue-generating Flights page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_caa_revenue_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addCAADivertFlightPage() {
            if (confirm('Add CAA Divert Flight page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_caa_divert_flight_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addCAADailyReportPage() {
            if (confirm('Add CAA Daily Report page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_caa_daily_report_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addNotamPage() {
            if (confirm('Add NOTAM page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_notam_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addRouteFixTimePage() {
            if (confirm('Add Route Fix Time Management page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_route_fix_time_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addPayloadDataPage() {
            if (confirm('Add Payload Data page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_payload_data_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addPayloadCalculatorPage() {
            if (confirm('Add Payload Calculator page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_payload_calculator_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addMetarTaforHistoryPage() {
            if (confirm('Add METAR/TAFOR History page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_metar_tafor_history_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addPassengerByAircraftPage() {
            if (confirm('Add Passenger By Aircraft page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_passenger_by_aircraft_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addBackupDbPage() {
            if (confirm('Add Backup Database page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_backup_db_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addOfficeTimePage() {
            if (confirm('Add Office Time page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_office_time_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addMaintenanceModePage() {
            if (confirm('Add Maintenance Mode page permission with super_admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_maintenance_mode_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addNotificationPage() {
            if (confirm('Add Notifications page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_notification_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addMessagePage() {
            if (confirm('Add Messages page permission with admin, pilot, and employee roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_message_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addTripManagementPage() {
            if (confirm('Add Trip Management page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_trip_management_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addOFPPage() {
            if (confirm('Add OFP Viewer page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_ofp_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addSMSPage() {
            if (confirm('Add SMS Management page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_sms_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addAboutPage() {
            if (confirm('Add About Developer page permission with admin, pilot, and employee roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_about_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addMyRecencyPage() {
            if (confirm('Add My Recency page permission with admin, pilot, and employee roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_my_recency_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addMyCertificatePage() {
            if (confirm('Add My Certificate page permission with admin, pilot, and employee roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_my_certificate_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addCateringPage() {
            if (confirm('Add Catering page permission with admin and manager roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_catering_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addIFSOCostsPage() {
            if (confirm('Add IFSO Costs page permission with admin and manager roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_ifso_costs_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addRoutePricePage() {
            if (confirm('Add Route Price page permission with admin and manager roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_route_price_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addFlightMonitoringDashboardPage() {
            if (confirm('Add Flight Monitoring Dashboard page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_flight_monitoring_dashboard_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addDailyCrewPage() {
            if (confirm('Add Daily Crew page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_daily_crew_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addMELItemsPage() {
            if (confirm('Add MEL Items page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_mel_items_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addCamoReportPage() {
            if (confirm('Add Camo Report page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_camo_report_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addPersonnelDataPage() {
            if (confirm('Add Personnel Data page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_personnel_data_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addFlightStatisticsPage() {
            if (confirm('Add Flight Statistics page permission with admin and manager roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_flight_statistics_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addFlightDataPage() {
            if (confirm('Add Flight Data page permission with admin, manager, and pilot roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_flight_data_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addContactsPage() {
            if (confirm('Add Contacts page permission with admin, manager, pilot, and employee roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_contacts_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addQuizSetListPage() {
            if (confirm('Add Quiz Set List page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_quiz_set_list_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addQuizCreateSetPage() {
            if (confirm('Add Create Quiz Set page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_quiz_create_set_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addQuizAssignPage() {
            if (confirm('Add Assign Quiz page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_quiz_assign_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addQuizResultsPage() {
            if (confirm('Add Quiz Results page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_quiz_results_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addMyQuizPage() {
            if (confirm('Add My Quiz page permission with admin, pilot, and employee roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_my_quiz_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addIssueCertificatePage() {
            if (confirm('Add Issue Certificate page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_issue_certificate_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addToolboxPage() {
            if (confirm('Add Toolbox page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_toolbox_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addToolboxViewPage() {
            if (confirm('Add View Box Contents page permission with admin, pilot, and employee roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_toolbox_view_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addActivityLogPage() {
            if (confirm('Add Activity Log page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_activity_log_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addClassManagementPage() {
            if (confirm('Add Class Management page permission with admin role?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_class_management_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addMyClassPage() {
            if (confirm('Add My Class page permission with admin, pilot, and employee roles?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
                button.disabled = true;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_my_class_page">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editPermissionModal');
            const addModal = document.getElementById('addPageModal');
            const scanModal = document.getElementById('scanPagesModal');
            const individualModal = document.getElementById('individualAccessModal');
            const quickAddModal = document.getElementById('quickAddModal');
            
            if (event.target === editModal) {
                closeEditPermissionModal();
            } else if (event.target === addModal) {
                closeAddPageModal();
            } else if (event.target === scanModal) {
                closeScanPagesModal();
            } else if (event.target === individualModal) {
                closeIndividualAccessModal();
            } else if (event.target === quickAddModal) {
                closeQuickAddModal();
            }
        }
    </script>
</body>
</html>
