USE raimon_fleet;

INSERT INTO page_permissions (page_path, page_name, required_roles, description) VALUES 
('admin/fleet/aircraft/add.php', 'Add Aircraft', '["admin","manager"]', 'Add new aircraft - admin and manager access only');
