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
