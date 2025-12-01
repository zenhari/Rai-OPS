USE raimon_fleet;

INSERT INTO page_permissions (page_path, page_name, required_roles, description) VALUES 
('dashboard/', 'Dashboard', '["admin","manager","employee","pilot","crew"]', 'Main dashboard page - accessible to all users'),
('admin/profile/', 'My Profile', '["admin","manager","employee","pilot","crew"]', 'User profile page - accessible to all users'),
('admin/users/index.php', 'User List', '["admin","manager"]', 'List all users - admin and manager only'),
('admin/users/add.php', 'Add User', '["admin","manager"]', 'Add new user - admin and manager only'),
('admin/users/edit.php', 'Edit User', '["admin","manager"]', 'Edit user information - admin and manager only'),
('admin/roles/index.php', 'Role Manager', '["admin"]', 'Manage user roles - admin only'),
('admin/role_permission.php', 'Page Permissions', '["admin"]', 'Manage page permissions - admin only'),
('admin/fleet/aircraft/', 'Aircraft Management', '["admin","manager","pilot"]', 'Aircraft management - admin, manager, and pilot only'),
('admin/fleet/aircraft/edit.php', 'Edit Aircraft', '["admin","manager","pilot"]', 'Edit aircraft information - admin, manager, and pilot only'),
('admin/logout.php', 'Logout', '["admin","manager","employee","pilot","crew"]', 'Logout page - accessible to all users');
