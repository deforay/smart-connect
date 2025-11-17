-- Amit 17 Nov 2025

CREATE TABLE IF NOT EXISTS `dash_locale_details` (
  `locale_id` int NOT NULL AUTO_INCREMENT,
  `locale` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `display_name` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `locale_status` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`locale_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT IGNORE INTO `dash_locale_details` (`locale_id`, `locale`, `display_name`, `locale_status`) VALUES
(1, 'en_US', 'english', 'active'),
(2, 'pt_BR', 'portuguese', 'active'),
(3, 'en_CD', 'DRC - English', 'active');

-- Amit 13 Sep 2018
CREATE TABLE IF NOT EXISTS `patients` (
  `patient_art_no` varchar(1000) NOT NULL,
  `first_name` varchar(1000) DEFAULT NULL,
  `middle_name` varchar(1000) DEFAULT NULL,
  `last_name` varchar(1000) DEFAULT NULL,
  `skey` text,
  PRIMARY KEY (`patient_art_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE IF NOT EXISTS `dash_user_roles` (
  `role_id` int NOT NULL,
  `role_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT IGNORE INTO `dash_user_roles` (`role_id`, `role_name`, `role_code`, `status`) VALUES
(1, 'admin', 'ad', 'active'),
(2, 'lab user', 'lu', 'active'),
(3, 'clinic user', 'cu', 'active'),
(4, 'hub user', 'hu', 'active'),
(5, 'management', 'mgmt', 'active'),
(6, 'API', 'api', 'active'),
(7, 'Data Integration', 'DATAINTEGRATION', 'active');

CREATE TABLE `dash_users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mobile` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` int NOT NULL,
  `otp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `api_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `generate_backups` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `start_date` date NOT NULL,
 `end_date` date NOT NULL,
 `requested_by` int(11) NOT NULL,
 `requested_on` datetime NOT NULL,
 `status` varchar(255) NOT NULL,
 `completed_on` datetime NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `dash_api_receiver_stats` (
  `api_id` int NOT NULL AUTO_INCREMENT,
  `tracking_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `received_on` datetime DEFAULT NULL,
  `number_of_records_received` int DEFAULT NULL,
  `number_of_records_processed` int DEFAULT NULL,
  `source` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `test_type` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lab_id` int DEFAULT NULL,
  `status` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`api_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `dash_form_vl` CHANGE `sample_code` `sample_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL, CHANGE `remote_sample_code` `remote_sample_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
ALTER TABLE `dash_form_vl` CHANGE `sample_code` `sample_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL, CHANGE `remote_sample_code` `remote_sample_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
ALTER TABLE `dash_form_covid19` CHANGE `sample_code` `sample_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL, CHANGE `remote_sample_code` `remote_sample_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;


ALTER TABLE `dash_form_vl` ADD UNIQUE KEY `uniq_dash_form_vl_sample_code_lab_id` (`sample_code`, `lab_id`);
ALTER TABLE `dash_form_vl` ADD UNIQUE KEY `uniq_dash_form_vl_sample_code_remote_sample_code` (`sample_code`, `remote_sample_code`);
ALTER TABLE `dash_form_eid` ADD UNIQUE KEY `uniq_dash_form_eid_sample_code_lab_id` (`sample_code`, `lab_id`);
ALTER TABLE `dash_form_eid` ADD UNIQUE KEY `uniq_dash_form_eid_sample_code_remote_sample_code` (`sample_code`, `remote_sample_code`);
ALTER TABLE `dash_form_covid19` ADD UNIQUE KEY `uniq_dash_form_covid19_sample_code_lab_id` (`sample_code`, `lab_id`);
ALTER TABLE `dash_form_covid19` ADD UNIQUE KEY `uniq_dash_form_covid19_sample_code_remote_sample_code` (`sample_code`, `remote_sample_code`);
ALTER TABLE `dash_form_vl` CHANGE `vldash_sync` `vldash_sync` INT(11) NULL DEFAULT '0';
ALTER TABLE `dash_form_vl` CHANGE `vlsm_country_id` `vlsm_country_id` INT(11) NULL DEFAULT NULL;


CREATE TABLE IF NOT EXISTS `dash_user_facility_map` (
  `map_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `facility_id` int NOT NULL,
  PRIMARY KEY (`map_id`),
  KEY `facility_id` (`facility_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `dash_global_config` (
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `status` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `dash_global_config` (`name`, `display_name`, `value`, `status`) VALUES
('announcement_msg', 'Announcement Message', '', 'active'),
('header', 'Header', 'MINISTRY OF HEALTH', 'active'),
('h_vl_msg', 'Result PDF High Viral Load Message', 'High Viral Load - need assessment for enhanced adherence or clinical assessment for possible switch to second line.', 'active'),
('language', 'Language', '3', 'active'),
('left_top_logo', 'Left Top Logo', 'logoz05b13.png', 'active'),
('logo', 'Logo', 'logoqtr6i3.jpg', 'active'),
('l_vl_msg', 'Result PDF Low Viral Load Message', 'Viral load adequately controlled : continue current regimen', 'active'),
('sample_waiting_month_range', 'Sample Waiting Month Range', '6', 'active'),
('show_smiley', 'Do you want to show smiley at result pdf?', 'yes', 'active'),
('watermark_text', 'Watermark Text', '', 'inactive');


CREATE TABLE IF NOT EXISTS `dash_resources` (
  `resource_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `dash_privileges` (
  `privilege_id` int NOT NULL AUTO_INCREMENT,
  `resource_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `privilege_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`resource_id`,`privilege_name`),
  UNIQUE KEY `resource_id_privilege_id` (`resource_id`,`privilege_name`),
  UNIQUE KEY `privilege_id` (`privilege_id`),
  KEY `resource_id` (`resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Application\\Controller\\SummaryController', 'Manage Vl Summary');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Application\\Controller\\LaboratoryController', 'Manage Vl Lab');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Application\\Controller\\ClinicController', 'Manage Vl Clinic');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Eid\\Controller\\SummaryController', 'Manage Eid Summary');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Eid\\Controller\\LabsController', 'Manage Eid Lab');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Eid\\Controller\\ClinicsController', 'Manage Eid Clinic');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Covid19\\Controller\\SummaryController', 'Manage Covid19 Summary');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Covid19\\Controller\\LabsController', 'Manage Covid19 Lab');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('DataManagement\\Controller\\DuplicateDataController', 'Manage Duplicate Data');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Application\\Controller\\SnapshotController', 'Manage Snapshot');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Application\\Controller\\ConfigController', 'Global Config');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Application\\Controller\\UsersController', 'Manage Users');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Application\\Controller\\FacilityController', 'Manage Facility');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Application\\Controller\\SyncStatusController', 'Manage Sync Status');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Application\\Controller\\ApiSyncHistoryController', 'Manage API History');
INSERT IGNORE INTO `dash_resources` (`resource_id`, `display_name`) VALUES
('Application\\Controller\\RolesController', 'Manage Roles');



CREATE TABLE IF NOT EXISTS `dash_privileges` (
  `privilege_id` int NOT NULL AUTO_INCREMENT,
  `resource_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `privilege_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`resource_id`,`privilege_name`),
  UNIQUE KEY `resource_id_2` (`resource_id`,`privilege_name`),
  UNIQUE KEY `privilege_id` (`privilege_id`),
  KEY `resource_id` (`resource_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\SummaryController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\LaboratoryController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\ClinicController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Eid\\Controller\\SummaryController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Eid\\Controller\\LabsController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Eid\\Controller\\LabsController', 'poc-labs-dashboard', 'POC Labs Dashboard');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Eid\\Controller\\ClinicsController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Covid19\\Controller\\SummaryController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Covid19\\Controller\\LabsController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('DataManagement\\Controller\\DuplicateDataController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('DataManagement\\Controller\\DuplicateDataController', 'edit', 'Edit');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\SnapshotController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\ConfigController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\ConfigController', 'edit', 'Edit');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\UsersController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\UsersController', 'add', 'Add');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\UsersController', 'edit', 'Edit');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\FacilityController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\FacilityController', 'add', 'Add');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\FacilityController', 'edit', 'Edit');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\SyncStatusController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\ApiSyncHistoryController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\ApiSyncHistoryController', 'show-params', 'Show Params');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\RolesController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\RolesController', 'add', 'Add');
INSERT IGNORE INTO `dash_privileges` (`resource_id`, `privilege_name`, `display_name`) VALUES
('Application\\Controller\\RolesController', 'edit', 'Edit');


CREATE TABLE IF NOT EXISTS `dash_roles_privileges_map` (
  `map_id` int NOT NULL AUTO_INCREMENT,
  `role_id` int NOT NULL,
  `privilege_id` int NOT NULL,
  PRIMARY KEY (`map_id`),
  KEY `role_id` (`role_id`),
  KEY `privilege_id` (`privilege_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `dash_roles_privileges_map` (`role_id`, `privilege_id`)
SELECT '1', `privilege_id`
FROM `dash_privileges`;

INSERT IGNORE  INTO `dash_user_roles` (`role_id`, `role_name`, `role_code`, `status`) VALUES
(NULL, 'Clinician', 'cli', 'active'), (NULL, 'Testing Lab Manager', 'tlm', 'active');

INSERT IGNORE INTO dash_roles_privileges_map (role_id, privilege_id)
SELECT r.role_id, p.privilege_id
FROM dash_user_roles r
JOIN dash_privileges p
ON p.resource_id in ('Application\\Controller\\SummaryController','Covid19\\Controller\\SummaryController','Eid\\Controller\\SummaryController','Application\\Controller\\ClinicController','Eid\\Controller\\ClinicsController','Application\\Controller\\SnapshotController')
WHERE r.role_name = 'Clinician';

INSERT IGNORE INTO dash_roles_privileges_map (role_id, privilege_id)
SELECT r.role_id, p.privilege_id
FROM dash_user_roles r
JOIN dash_privileges p
ON p.resource_id in ('Application\\Controller\\SummaryController','Covid19\\Controller\\SummaryController','Eid\\Controller\\SummaryController','Application\\Controller\\LaboratoryController','Eid\\Controller\\LabsController','Covid19\\Controller\\LabsController','Application\\Controller\\SnapshotController')
WHERE r.role_name = 'Testing Lab Manager';
