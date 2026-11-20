-- Download exports through a route that needs a login.
--
-- Excel exports and result PDFs used to be written to public/temporary and
-- opened from there, so anyone holding a file name could fetch sample and
-- patient data without logging in. They are now written to var/exports and
-- served by Application\Controller\DownloadController at /download/:file.
--
-- The ACL check in Module::preSetter() needs a resource and a privilege row for
-- that controller, or every download answers 403. Every role that may open a
-- page with an export button may download what that page exports.
--
-- Every statement is safe to replay.

INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`)
VALUES ('Application\\Controller\\DownloadController', 'Download Exports');

INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`)
VALUES ('Application\\Controller\\DownloadController', 'index', 'Download');

-- NOT EXISTS rather than INSERT IGNORE because the map has no unique key on
-- (role_id, privilege_id), so a replay would otherwise duplicate every row.
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
