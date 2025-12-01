-- Safety Reports Page Permissions
INSERT INTO page_permissions (page_path, required_roles, created_at, updated_at) VALUES
('admin/settings/safety_reports/index.php', '[]', NOW(), NOW()),
('admin/settings/safety_reports/add.php', '[]', NOW(), NOW()),
('admin/settings/safety_reports/edit.php', '[]', NOW(), NOW());

