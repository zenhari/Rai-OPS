USE raimon_fleet;

INSERT INTO page_permissions (page_path, page_name, required_roles, description) VALUES 
('admin/crew/scheduling.php', 'Crew Scheduling', '["admin","manager","pilot"]', 'Schedule crew assignments for flights');

