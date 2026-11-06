-- Open the VL turnaround-time page, and give every role an id of its own.
--
-- Every statement is safe to replay. Each one either checks before it acts
-- or leaves the data unchanged when its work is already done.

-- 1. The VL turnaround-time page.
--
-- /times/dashboard answers 403 to every user, including admins. The ACL check
-- in Module::preSetter() needs a resource and a privilege row for the
-- controller and action, and Application\Controller\TimeController never had
-- either. The Labs dashboard links to the page. Its AJAX actions, get-tat-*,
-- are not checked, so only the page itself needs a row.
--
-- Every role that may open the Labs dashboard may open the page it links to.

INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`)
VALUES ('Application\\Controller\\TimeController', 'Manage Turnaround Time');

INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`)
VALUES ('Application\\Controller\\TimeController', 'dashboard', 'Access');

INSERT INTO `dash_roles_privileges_map` (`role_id`, `privilege_id`)
SELECT DISTINCT m.`role_id`, tat.`privilege_id`
  FROM `dash_roles_privileges_map` m
  JOIN `dash_privileges` lab
    ON lab.`privilege_id` = m.`privilege_id`
   AND lab.`resource_id` = 'Application\\Controller\\LaboratoryController'
   AND lab.`privilege_name` = 'dashboard'
  JOIN `dash_privileges` tat
    ON tat.`resource_id` = 'Application\\Controller\\TimeController'
   AND tat.`privilege_name` = 'dashboard'
 WHERE NOT EXISTS (
       SELECT 1 FROM `dash_roles_privileges_map` x
        WHERE x.`role_id` = m.`role_id` AND x.`privilege_id` = tat.`privilege_id`);

-- 2. Role ids.
--
-- dash_user_roles.role_id has no primary key and no AUTO_INCREMENT. Two things
-- follow on installs that run with the app's empty sql_mode:
--
--   - data/alter.sql re-inserts the standard roles with INSERT IGNORE, which
--     ignores nothing without a key, so every replay added another copy.
--   - It inserted Clinician and Testing Lab Manager with a NULL role_id, and
--     RolesTable::addRolesDetails() inserts every new role without one. MySQL
--     stores each of them as 0.
--
-- Users store only the role id, so a user with role 0 cannot be traced to the
-- role meant for them. The ACL gives role 0's privileges to the first role
-- code it finds with id 0, and login hands every role-0 user that same code.
-- Role 0 therefore already behaves as one role holding every role-0 privilege.
-- This keeps exactly that, under one row, so no user gains or loses access.
-- An admin can then move users to separate roles.

-- The table has no key to tell copies apart, so number its rows in a scratch
-- column first. Each step below leaves the table usable if a later one fails,
-- and each is a no-op once the work is done: the scratch column comes and goes,
-- no role 0 is left to merge, and no copies are left to delete.

ALTER TABLE `dash_user_roles` ADD COLUMN `sc_row` int NULL;

SET @sc_row = 0;

-- Active rows are numbered first. The ACL and login read only active roles, so
-- the row each step below keeps is the one in effect today, even where an
-- inactive copy sits in front of an active one.
UPDATE `dash_user_roles` SET `sc_row` = (@sc_row := @sc_row + 1)
 ORDER BY (`status` = 'active') DESC;

-- The first role-0 row, preferring an active one, is the one whose code the
-- ACL and login use today. It keeps that code and takes every role-0 name.
UPDATE `dash_user_roles` r
  JOIN (SELECT MIN(`sc_row`) AS keep_row,
               GROUP_CONCAT(DISTINCT `role_name` ORDER BY `role_name` SEPARATOR ' / ') AS names
          FROM `dash_user_roles`
         WHERE `role_id` = 0) z
    ON r.`sc_row` = z.keep_row
   SET r.`role_name` = z.names;

-- Keep the first row of each role id, which is an active one where any is.
DELETE r FROM `dash_user_roles` r
  JOIN `dash_user_roles` k ON k.`role_id` = r.`role_id` AND k.`sc_row` < r.`sc_row`;

ALTER TABLE `dash_user_roles` DROP COLUMN `sc_row`;

-- Making role_id AUTO_INCREMENT would renumber a 0 silently, so move role 0 to
-- the next free id first, and its users and privileges with it. Each UPDATE
-- acts only while a role 0 exists. The role row moves last, so the first two
-- still see it.

SET @sc_new_role_id = (SELECT COALESCE(MAX(`role_id`), 0) + 1 FROM `dash_user_roles`);

UPDATE `dash_users` SET `role` = @sc_new_role_id
 WHERE `role` = 0 AND EXISTS (SELECT 1 FROM `dash_user_roles` WHERE `role_id` = 0);

UPDATE `dash_roles_privileges_map` SET `role_id` = @sc_new_role_id
 WHERE `role_id` = 0 AND EXISTS (SELECT 1 FROM `dash_user_roles` WHERE `role_id` = 0);

UPDATE `dash_user_roles` SET `role_id` = @sc_new_role_id WHERE `role_id` = 0;

ALTER TABLE `dash_user_roles` ADD PRIMARY KEY (`role_id`);

ALTER TABLE `dash_user_roles` MODIFY `role_id` int NOT NULL AUTO_INCREMENT;
