USE raimon_fleet;

INSERT INTO page_permissions (page_path, page_name, required_roles, description) VALUES 
('admin/flights/', 'Flight Manager', '["admin","manager","pilot"]', 'Flight management - admin, manager, and pilot access'),
('admin/flights/edit.php', 'Edit Flight', '["admin","manager","pilot"]', 'Edit flight information - admin, manager, and pilot access');
