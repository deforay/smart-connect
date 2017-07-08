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

--Pal 04-July-2017
update dash_vl_request_form set sample_code= null where sample_code = 'NULL'

update dash_vl_request_form set sample_collection_date= null where sample_collection_date = 'NULL'

update dash_vl_request_form set patient_art_no= null where patient_art_no = 'NULL'

update dash_vl_request_form set patient_first_name= null where patient_first_name = 'NULL'

update dash_vl_request_form set patient_last_name= null where patient_last_name = 'NULL'

update dash_vl_request_form set patient_mobile_number= null where patient_mobile_number = 'NULL'

update dash_vl_request_form set patient_gender= null where patient_gender = 'NULL'

update dash_vl_request_form set request_clinician_name= null where request_clinician_name = 'NULL'

update dash_vl_request_form set sample_received_at_vl_lab_datetime= null where sample_received_at_vl_lab_datetime = 'NULL'

update dash_vl_request_form set sample_tested_datetime= null where sample_tested_datetime = 'NULL'

update dash_vl_request_form set vl_test_platform= null where vl_test_platform = 'NULL'

update dash_vl_request_form set result= null where result = 'NULL'

update dash_vl_request_form set result_value_log= null where result_value_log = 'NULL'

update dash_vl_request_form set result_value_absolute= null where result_value_absolute = 'NULL'

update dash_vl_request_form set result_value_text= null where result_value_text = 'NULL'

update dash_vl_request_form set result_value_absolute_decimal= null where result_value_absolute_decimal = 'NULL'

update dash_vl_request_form set approver_comments= null where approver_comments = 'NULL'

update dash_vl_request_form set last_viral_load_date= null where last_viral_load_date = 'NULL'

update dash_vl_request_form set last_viral_load_result= null where last_viral_load_result = 'NULL'

INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('h_vl_msg', 'Result PDF High Viral Load Message', 'High Viral Load - need assessment for enhanced adherence or clinical assessment for possible switch to second line.'), ('l_vl_msg', 'Result PDF Low Viral Load Message', 'Viral load adequately controlled : continue current regimen');

INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('show_smiley', 'Do you want to show smiley at result pdf?', 'yes');

INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('header', 'Header', 'MINISTRY OF HEALTH');

INSERT INTO `dash_global_config` (`name`, `display_name`, `value`) VALUES ('logo', 'Logo', NULL);

--Pal 05-July-2017
CREATE TABLE `dash_user_facility_map` (
  `map_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL
);
ALTER TABLE `dash_user_facility_map` ADD PRIMARY KEY(`map_id`);

ALTER TABLE `dash_user_facility_map` CHANGE `map_id` `map_id` INT(11) NOT NULL AUTO_INCREMENT;

  
alter table dash_user_facility_map add FOREIGN KEY(facility_id) REFERENCES facility_details(facility_id);

alter table dash_user_facility_map add FOREIGN KEY(user_id) REFERENCES dash_users(user_id);


-- Amit 07 July 2017

ALTER TABLE `dash_vl_request_form` ADD `line_of_treatment` VARCHAR(255) NULL AFTER `treatment_initiation`;
