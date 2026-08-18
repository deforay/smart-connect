-- Log viewer.
--
-- The application now writes its own log files through Monolog, and this
-- registers the page that reads them so the ACL can grant it. Without these
-- rows the route exists but nobody, including an administrator, is allowed to
-- reach it, and the menu entry never appears.
--
-- Downloading is a separate privilege from viewing: a whole log file is a
-- bigger disclosure than a page of entries, and some deployments will want to
-- keep that to administrators alone.

INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Application\\Controller\\LogsController', 'View Logs');

INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\LogsController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\LogsController', 'view', 'View Log File');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\LogsController', 'download', 'Download Log File');

-- Granted to the admin role, matching how the baseline seeds a new privilege.
-- NOT EXISTS rather than INSERT IGNORE because the map has no unique key on
-- (role_id, privilege_id), so a replayed migration would otherwise duplicate
-- every row it inserts here.
INSERT INTO `dash_roles_privileges_map` (`role_id`, `privilege_id`)
SELECT 1, p.`privilege_id`
FROM `dash_privileges` p
WHERE p.`resource_id` = 'Application\\Controller\\LogsController'
  AND NOT EXISTS (
    SELECT 1 FROM `dash_roles_privileges_map` m
    WHERE m.`role_id` = 1 AND m.`privilege_id` = p.`privilege_id`
  );
