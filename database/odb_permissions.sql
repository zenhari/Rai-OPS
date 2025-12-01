-- ODB Notifications Permissions
INSERT INTO page_permissions (page_path, page_name, required_roles, description) VALUES 
('admin/odb/index.php', 'ODB Management', '["admin","manager"]', 'Manage ODB notifications and view acknowledgment statistics'),
('admin/odb/view.php', 'ODB Notification Details', '["admin","manager"]', 'View detailed ODB notification information and user acknowledgments'),
('admin/odb/list.php', 'ODB Notifications List', '["admin","manager","pilot","crew","employee","Dev","Dispatch","CEO","Cabin Crew"]', 'View and acknowledge ODB notifications');

