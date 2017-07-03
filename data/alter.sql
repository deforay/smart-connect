ALTER TABLE `facility_details` CHANGE `vl_instance_id` `vl_instance_id` CHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;

ALTER TABLE samples
DROP FOREIGN KEY samples_ibfk_2
ALTER TABLE samples
DROP FOREIGN KEY samples_ibfk_3

ALTER TABLE `samples` CHANGE `test_reason` `test_reason` INT(11) NULL DEFAULT NULL;

ALTER TABLE `samples` CHANGE `lab_id` `lab_id` INT(11) NULL DEFAULT NULL;

ALTER TABLE `samples` CHANGE `clinic_id` `clinic_id` INT(11) NULL DEFAULT NULL;

ALTER TABLE `samples` CHANGE `sample_collection_date` `sample_collection_date` DATETIME NULL DEFAULT NULL;

ALTER TABLE `samples` CHANGE `lab_tested_date` `lab_tested_date` DATETIME NULL DEFAULT NULL;
--saravanan 18-apr-2017
INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('sample_waiting_month_range', 'Sample Waiting Month Range', '3');

--Pal 03-July-2017
INSERT INTO `dash_user_roles` (`role_id`, `role_name`, `role_code`, `status`) VALUES
(2, 'lab user', 'lu', 'active'),
(3, 'clinic user', 'cu', 'active'),
(4, 'hub user', 'hu', 'active');