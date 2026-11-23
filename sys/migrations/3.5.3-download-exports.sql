-- Download exports and result PDFs through a route that needs a login.
--
-- Excel exports and result PDFs used to be written to public/temporary and
-- opened from /temporary/<name>, so anyone with a file name could fetch them
-- without logging in. They hold sample and patient data. They are now written
-- to var/exports and served by /download/:file, which is
-- Application\Controller\DownloadController.
--
-- The browser opens that route with window.open, so it is a plain page request
-- and Module::preSetter() checks it against the ACL. Without these rows nobody,
-- including an administrator, could download an export.
--
-- Every statement is safe to replay.

INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`)
VALUES ('Application\\Controller\\DownloadController', 'Download Exports');

INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`)
VALUES ('Application\\Controller\\DownloadController', 'index', 'Access');

-- Every role that can open a page with an export or result-PDF button may
-- download what that button makes. Those pages belong to the controllers
-- listed below. NOT EXISTS rather than INSERT IGNORE because the map has no
-- unique key on (role_id, privilege_id).
INSERT INTO `dash_roles_privileges_map` (`role_id`, `privilege_id`)
SELECT DISTINCT m.`role_id`, dl.`privilege_id`
  FROM `dash_roles_privileges_map` m
  JOIN `dash_privileges` page
    ON page.`privilege_id` = m.`privilege_id`
   AND page.`resource_id` IN (
       'Application\\Controller\\SummaryController',
       'Application\\Controller\\LaboratoryController',
       'Application\\Controller\\ClinicController',
       'Application\\Controller\\SyncStatusController',
       'Eid\\Controller\\SummaryController',
       'Eid\\Controller\\LabsController',
       'Eid\\Controller\\ClinicsController')
  JOIN `dash_privileges` dl
    ON dl.`resource_id` = 'Application\\Controller\\DownloadController'
   AND dl.`privilege_name` = 'index'
 WHERE NOT EXISTS (
       SELECT 1 FROM `dash_roles_privileges_map` x
        WHERE x.`role_id` = m.`role_id` AND x.`privilege_id` = dl.`privilege_id`);
