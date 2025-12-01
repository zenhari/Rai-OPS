-- Page Permissions Table
CREATE TABLE IF NOT EXISTS `page_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_path` varchar(255) NOT NULL,
  `page_name` varchar(255) NOT NULL,
  `required_roles` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_path` (`page_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default page permissions
INSERT INTO `page_permissions` (`page_path`, `page_name`, `required_roles`, `description`) VALUES
('dashboard/', 'Dashboard', '["admin","manager","employee","pilot","crew"]', 'Main dashboard page - accessible to all users'),
('admin/profile/', 'My Profile', '["admin","manager","employee","pilot","crew"]', 'User profile page - accessible to all users'),
('admin/users/index.php', 'User List', '["admin","manager"]', 'List all users - admin and manager only'),
('admin/users/add.php', 'Add User', '["admin","manager"]', 'Add new user - admin and manager only'),
('admin/users/edit.php', 'Edit User', '["admin","manager"]', 'Edit user information - admin and manager only'),
('admin/roles/index.php', 'Role Manager', '["admin"]', 'Manage user roles - admin only'),
('admin/fleet/aircraft/', 'Aircraft Management', '["admin","manager","pilot"]', 'Aircraft management - admin, manager, and pilot only'),
('admin/fleet/aircraft/edit.php', 'Edit Aircraft', '["admin","manager","pilot"]', 'Edit aircraft information - admin, manager, and pilot only'),
('admin/logout.php', 'Logout', '["admin","manager","employee","pilot","crew"]', 'Logout page - accessible to all users'),
('admin/operations/payload_calculator.php', 'Payload Calculator', '["admin"]', 'Calculate payload weight for today\'s flights based on passenger count and accompanying load');
