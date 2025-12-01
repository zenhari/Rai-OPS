USE raimon_fleet;

INSERT INTO page_permissions (page_path, page_name, required_roles, description) VALUES 
('admin/flights/add.php', 'Add Flight', '["admin","manager","pilot"]', 'Add new flight - admin, manager, and pilot access');
