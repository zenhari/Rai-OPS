-- Home Base Page Permissions
INSERT INTO page_permissions (page_path, required_roles, created_at, updated_at) VALUES
('admin/settings/home_base/index.php', '["admin", "manager"]', NOW(), NOW()),
('admin/settings/home_base/add.php', '["admin", "manager"]', NOW(), NOW()),
('admin/settings/home_base/edit.php', '["admin", "manager"]', NOW(), NOW());

