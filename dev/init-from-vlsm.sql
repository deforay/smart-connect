
SET GLOBAL innodb_lock_wait_timeout = 12000;

-- Temporarily disable foreign key checks
SET FOREIGN_KEY_CHECKS = 0;

START TRANSACTION;  -- Start transaction if using InnoDB

DROP TABLE IF EXISTS `vldashboard`.facility_details;
CREATE TABLE IF NOT EXISTS `vldashboard`.facility_details LIKE `vlsm`.facility_details;
INSERT IGNORE INTO `vldashboard`.facility_details SELECT * FROM `vlsm`.facility_details;

DROP TABLE IF EXISTS `vldashboard`.instruments;
CREATE TABLE IF NOT EXISTS `vldashboard`.instruments LIKE `vlsm`.instruments;
INSERT IGNORE INTO `vldashboard`.instruments SELECT * FROM `vlsm`.instruments;

DROP TABLE IF EXISTS `vldashboard`.geographical_divisions;
CREATE TABLE IF NOT EXISTS `vldashboard`.geographical_divisions LIKE `vlsm`.geographical_divisions;
INSERT IGNORE INTO `vldashboard`.geographical_divisions SELECT * FROM `vlsm`.geographical_divisions;

DROP TABLE IF EXISTS `vldashboard`.r_vl_sample_type;
CREATE TABLE IF NOT EXISTS `vldashboard`.r_vl_sample_type LIKE `vlsm`.r_vl_sample_type;
INSERT IGNORE INTO `vldashboard`.r_vl_sample_type SELECT * FROM `vlsm`.r_vl_sample_type;

DROP TABLE IF EXISTS `vldashboard`.r_vl_test_reasons;
CREATE TABLE IF NOT EXISTS `vldashboard`.r_vl_test_reasons LIKE `vlsm`.r_vl_test_reasons;
INSERT IGNORE INTO `vldashboard`.r_vl_test_reasons SELECT * FROM `vlsm`.r_vl_test_reasons;

DROP TABLE IF EXISTS `vldashboard`.r_vl_sample_rejection_reasons;
CREATE TABLE IF NOT EXISTS `vldashboard`.r_vl_sample_rejection_reasons LIKE `vlsm`.r_vl_sample_rejection_reasons;
INSERT IGNORE INTO `vldashboard`.r_vl_sample_rejection_reasons SELECT * FROM `vlsm`.r_vl_sample_rejection_reasons;

DROP TABLE IF EXISTS `vldashboard`.r_eid_sample_type;
CREATE TABLE IF NOT EXISTS `vldashboard`.r_eid_sample_type LIKE `vlsm`.r_eid_sample_type;
INSERT IGNORE INTO `vldashboard`.r_eid_sample_type SELECT * FROM `vlsm`.r_eid_sample_type;

COMMIT;  -- Commit the transaction

START TRANSACTION;  -- Start transaction if using InnoDB
DROP TABLE IF EXISTS `vldashboard`.dash_form_vl;
CREATE TABLE IF NOT EXISTS `vldashboard`.dash_form_vl LIKE `vlsm`.form_vl;
ALTER TABLE `vldashboard`.dash_form_vl DISABLE KEYS;
INSERT IGNORE INTO `vldashboard`.dash_form_vl SELECT * FROM `vlsm`.form_vl;
ALTER TABLE `vldashboard`.dash_form_vl ENABLE KEYS;
COMMIT;  -- Commit the transaction

START TRANSACTION;  -- Start transaction if using InnoDB
DROP TABLE IF EXISTS `vldashboard`.dash_form_eid;
CREATE TABLE IF NOT EXISTS `vldashboard`.dash_form_eid LIKE `vlsm`.form_eid;
ALTER TABLE `vldashboard`.dash_form_eid DISABLE KEYS;
INSERT IGNORE INTO `vldashboard`.dash_form_eid SELECT * FROM `vlsm`.form_eid;
ALTER TABLE `vldashboard`.dash_form_eid ENABLE KEYS;
COMMIT;  -- Commit the transaction


START TRANSACTION;  -- Start transaction if using InnoDB
DROP TABLE IF EXISTS `vldashboard`.dash_form_covid19;
CREATE TABLE IF NOT EXISTS `vldashboard`.dash_form_covid19 LIKE `vlsm`.form_covid19;
ALTER TABLE `vldashboard`.dash_form_eid DISABLE KEYS;
INSERT IGNORE INTO `vldashboard`.dash_form_covid19 SELECT * FROM `vlsm`.form_covid19;
ALTER TABLE `vldashboard`.dash_form_eid ENABLE KEYS;
COMMIT;  -- Commit the transaction


START TRANSACTION;  -- Start transaction if using InnoDB
DROP TABLE IF EXISTS `vldashboard`.dash_form_tb;
CREATE TABLE IF NOT EXISTS `vldashboard`.dash_form_tb LIKE `vlsm`.form_tb;
ALTER TABLE `vldashboard`.dash_form_eid DISABLE KEYS;
INSERT IGNORE INTO `vldashboard`.dash_form_tb SELECT * FROM `vlsm`.form_tb;
ALTER TABLE `vldashboard`.dash_form_eid ENABLE KEYS;
COMMIT;  -- Commit the transaction


START TRANSACTION;  -- Start transaction if using InnoDB
DROP TABLE IF EXISTS `vldashboard`.dash_form_hepatitis;
CREATE TABLE IF NOT EXISTS `vldashboard`.dash_form_hepatitis LIKE `vlsm`.form_hepatitis;
ALTER TABLE `vldashboard`.dash_form_eid DISABLE KEYS;
INSERT IGNORE INTO `vldashboard`.dash_form_hepatitis SELECT * FROM `vlsm`.form_hepatitis;
ALTER TABLE `vldashboard`.dash_form_eid ENABLE KEYS;
COMMIT;  -- Commit the transaction



SET FOREIGN_KEY_CHECKS = 1;


CREATE TABLE IF NOT EXISTS `dash_api_receiver_stats` (
  `api_id` INT NOT NULL AUTO_INCREMENT,
  `tracking_id` VARCHAR(255),
  `received_on` DATETIME,
  `number_of_records_received` INT,
  `number_of_records_processed` INT,
  `source` VARCHAR(256),
  `test_type` VARCHAR(256),
  `lab_id` INT,
  `status` MEDIUMTEXT,
  PRIMARY KEY (`api_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- dash_global_config
CREATE TABLE IF NOT EXISTS `dash_global_config` (
  `name` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(255),
  `value` LONGTEXT,
  `status` VARCHAR(45) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- dash_locale_details
CREATE TABLE IF NOT EXISTS `dash_locale_details` (
  `locale_id` INT NOT NULL AUTO_INCREMENT,
  `locale` VARCHAR(45) NOT NULL,
  `display_name` VARCHAR(45) NOT NULL,
  `locale_status` VARCHAR(45) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`locale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- dash_privileges
CREATE TABLE IF NOT EXISTS `dash_privileges` (
  `privilege_id` INT NOT NULL AUTO_INCREMENT,
  `resource_id` VARCHAR(255) NOT NULL,
  `privilege_name` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(255),
  PRIMARY KEY (`privilege_id`),
  UNIQUE KEY `resource_privilege_unique` (`resource_id`, `privilege_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- dash_resources
CREATE TABLE IF NOT EXISTS `dash_resources` (
  `resource_id` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(255),
  PRIMARY KEY (`resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- dash_roles_privileges_map
CREATE TABLE IF NOT EXISTS `dash_roles_privileges_map` (
  `map_id` INT NOT NULL AUTO_INCREMENT,
  `role_id` INT NOT NULL,
  `privilege_id` INT NOT NULL,
  PRIMARY KEY (`map_id`),
  KEY `role_id` (`role_id`),
  KEY `privilege_id` (`privilege_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- dash_track_api_requests
CREATE TABLE IF NOT EXISTS `dash_track_api_requests` (
  `api_track_id` INT NOT NULL AUTO_INCREMENT,
  `transaction_id` VARCHAR(256),
  `requested_by` VARCHAR(255),
  `requested_on` DATETIME,
  `number_of_records` VARCHAR(50),
  `request_type` VARCHAR(50),
  `test_type` VARCHAR(255),
  `api_url` MEDIUMTEXT,
  `api_params` TEXT,
  `request_data` TEXT,
  `response_data` TEXT,
  `facility_id` VARCHAR(256),
  `data_format` VARCHAR(255),
  PRIMARY KEY (`api_track_id`),
  KEY `requested_on` (`requested_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- dash_users
CREATE TABLE IF NOT EXISTS `dash_users` (
  `user_id` INT NOT NULL AUTO_INCREMENT,
  `user_name` VARCHAR(255),
  `email` VARCHAR(255),
  `mobile` VARCHAR(255),
  `password` VARCHAR(500),
  `role` INT NOT NULL,
  `otp` VARCHAR(255),
  `api_token` VARCHAR(255),
  `status` VARCHAR(255),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- dash_user_facility_map
CREATE TABLE IF NOT EXISTS `dash_user_facility_map` (
  `map_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `facility_id` INT NOT NULL,
  PRIMARY KEY (`map_id`),
  KEY `facility_id` (`facility_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- dash_user_roles
CREATE TABLE IF NOT EXISTS `dash_user_roles` (
  `role_id` INT NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(255),
  `role_code` VARCHAR(255),
  `status` VARCHAR(255),
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT IGNORE INTO dash_user_roles (role_id, role_name, role_code, status) VALUES
(1, 'admin', 'ad', 'active');
INSERT IGNORE INTO dash_user_roles (role_id, role_name, role_code, status) VALUES
(2, 'lab user', 'lu', 'active');
INSERT IGNORE INTO dash_user_roles (role_id, role_name, role_code, status) VALUES
(3, 'clinic user', 'cu', 'active');


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


INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\ApiSyncHistoryController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\ApiSyncHistoryController', 'show-params', 'Show Params');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\ClinicController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\ConfigController', 'edit', 'Edit');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\ConfigController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\FacilityController', 'add', 'Add');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\FacilityController', 'edit', 'Edit');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\FacilityController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\LaboratoryController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\RolesController', 'add', 'Add');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\RolesController', 'edit', 'Edit');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\RolesController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\SnapshotController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\SummaryController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\SyncStatusController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\UsersController', 'add', 'Add');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\UsersController', 'edit', 'Edit');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Application\\Controller\\UsersController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Covid19\\Controller\\LabsController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Covid19\\Controller\\SummaryController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'DataManagement\\Controller\\DuplicateDataController', 'edit', 'Edit');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'DataManagement\\Controller\\DuplicateDataController', 'index', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Eid\\Controller\\ClinicsController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Eid\\Controller\\LabsController', 'dashboard', 'Access');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Eid\\Controller\\LabsController', 'poc-labs-dashboard', 'POC Labs Dashboard');
INSERT IGNORE INTO `dash_privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'Eid\\Controller\\SummaryController', 'dashboard', 'Access');

DELETE FROM `dash_roles_privileges_map` WHERE role_id = 1;
INSERT IGNORE INTO dash_roles_privileges_map (role_id, privilege_id)
SELECT 1, privilege_id FROM dash_privileges;


