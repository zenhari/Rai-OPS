<?php
// Only show permission banner for admin users
if (hasRole('admin')) {
    $currentPagePath = getCurrentPagePath();
    $permission = getPagePermission($currentPagePath);
    
    if ($permission) {
        $requiredRoles = json_decode($permission['required_roles'], true);
        $roleNames = array_map('ucfirst', $requiredRoles);
        $roleList = implode(', ', $roleNames);
        
        // Check if current user has individual access
        $current_user = getCurrentUser();
        $hasIndividualAccess = hasIndividualAccess($currentPagePath, $current_user['id']);
        ?>
        <!-- Permission Banner for Admin -->
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700 dark:text-yellow-200">
                        <strong>Page Access Info:</strong> This page requires one of the following roles: 
                        <span class="font-semibold"><?php echo htmlspecialchars($roleList); ?></span>
                        <?php if ($hasIndividualAccess): ?>
                            <br><span class="text-xs text-green-600 dark:text-green-400 font-medium">
                                <i class="fas fa-user-check mr-1"></i>You have individual access to this page
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($permission['description'])): ?>
                            <br><span class="text-xs text-yellow-600 dark:text-yellow-300"><?php echo htmlspecialchars($permission['description']); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
}
?>
