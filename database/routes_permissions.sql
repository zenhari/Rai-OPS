USE raimon_fleet;

INSERT INTO page_permissions (page_path, page_name, required_roles, description) VALUES 
('admin/fleet/routes/index.php', 'Route Management', '["admin","manager","pilot"]', 'Manage flight routes and stations'),
('admin/fleet/routes/stations.php', 'Station Management', '["admin","manager","pilot"]', 'Manage airports and stations');

