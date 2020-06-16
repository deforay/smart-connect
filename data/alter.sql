-- Amit 21 August 2018

INSERT INTO `locale_details` (`locale_id`, `locale`, `display_name`, `locale_status`) VALUES
(3, 'en_CD', 'DRC - English', 'active');


-- Amit 13 Sep 2018
CREATE TABLE IF NOT EXISTS `patients` (
  `patient_art_no` varchar(1000) NOT NULL,
  `first_name` varchar(1000) DEFAULT NULL,
  `middle_name` varchar(1000) DEFAULT NULL,
  `last_name` varchar(1000) DEFAULT NULL,
  `skey` text,
  PRIMARY KEY (`patient_art_no`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


-- saravanan 18-feb-2019
INSERT INTO `dash_user_roles` (`role_id`, `role_name`, `role_code`, `status`) VALUES (5, 'management', 'mgmt', 'active');

-- saravanan 05-mar-2019
INSERT INTO `dash_user_roles` (`role_id`, `role_name`, `role_code`, `status`) VALUES (6, 'API', 'api', 'active');
ALTER TABLE `dash_users` ADD `api_token` VARCHAR(255) NULL DEFAULT NULL AFTER `role`;

INSERT INTO `dash_user_roles` (`role_id`, `role_name`, `role_code`, `status`) VALUES (7, 'Data Integration', 'DATAINTEGRATION', 'active');
ALTER TABLE `dash_users` ADD `otp` VARCHAR(255) NULL DEFAULT NULL AFTER `role`;

-- Amit 04-Apr-2019
ALTER TABLE `dash_users` DROP `login_id`;


-- Amit 09 April 2019

CREATE TABLE `generate_backups` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `start_date` date NOT NULL,
 `end_date` date NOT NULL,
 `requested_by` int(11) NOT NULL,
 `requested_on` datetime NOT NULL,
 `status` varchar(255) NOT NULL,
 `completed_on` datetime NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- 


-- Amit 16 June 2020
CREATE TABLE `dash_eid_form` (
 `eid_id` int(11) NOT NULL AUTO_INCREMENT,
 `vlsm_instance_id` varchar(255) DEFAULT NULL,
 `vlsm_country_id` int(11) NOT NULL,
 `sample_code_key` int(11) NOT NULL,
 `sample_code_format` varchar(255) DEFAULT NULL,
 `sample_code` varchar(255) DEFAULT NULL,
 `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
 `remote_sample_code_key` int(11) DEFAULT NULL,
 `remote_sample_code_format` varchar(255) DEFAULT NULL,
 `remote_sample_code` varchar(255) DEFAULT NULL,
 `sample_collection_date` datetime NOT NULL,
 `sample_received_at_hub_datetime` datetime DEFAULT NULL,
 `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
 `sample_tested_datetime` datetime DEFAULT NULL,
 `funding_source` int(11) DEFAULT NULL,
 `implementing_partner` int(11) DEFAULT NULL,
 `is_sample_rejected` varchar(255) NOT NULL DEFAULT 'no',
 `reason_for_sample_rejection` varchar(500) CHARACTER SET latin1 DEFAULT NULL,
 `facility_id` int(11) DEFAULT NULL,
 `province_id` int(11) DEFAULT NULL,
 `mother_id` varchar(255) DEFAULT NULL,
 `mother_name` varchar(500) DEFAULT NULL,
 `mother_surname` varchar(255) DEFAULT NULL,
 `caretaker_contact_consent` varchar(255) DEFAULT NULL,
 `caretaker_phone_number` varchar(255) DEFAULT NULL,
 `caretaker_address` varchar(1000) DEFAULT NULL,
 `mother_dob` date DEFAULT NULL,
 `mother_age_in_years` varchar(255) DEFAULT NULL,
 `mother_marital_status` varchar(255) DEFAULT NULL,
 `child_id` varchar(255) DEFAULT NULL,
 `child_name` varchar(255) DEFAULT NULL,
 `child_surname` varchar(255) DEFAULT NULL,
 `child_dob` date DEFAULT NULL,
 `child_age` varchar(255) DEFAULT NULL,
 `child_gender` varchar(255) DEFAULT NULL,
 `mother_hiv_status` varchar(255) DEFAULT NULL,
 `mode_of_delivery` varchar(255) DEFAULT NULL,
 `mother_treatment` varchar(255) DEFAULT NULL,
 `mother_treatment_other` varchar(1000) DEFAULT NULL,
 `mother_treatment_initiation_date` date DEFAULT NULL,
 `mother_cd4` varchar(255) DEFAULT NULL,
 `mother_cd4_test_date` date DEFAULT NULL,
 `mother_vl_result` varchar(255) DEFAULT NULL,
 `mother_vl_test_date` varchar(255) DEFAULT NULL,
 `child_treatment` varchar(255) DEFAULT NULL,
 `child_treatment_other` varchar(1000) DEFAULT NULL,
 `is_infant_receiving_treatment` varchar(255) DEFAULT NULL,
 `has_infant_stopped_breastfeeding` varchar(255) DEFAULT NULL,
 `age_breastfeeding_stopped_in_months` varchar(255) DEFAULT NULL,
 `choice_of_feeding` varchar(255) DEFAULT NULL,
 `is_cotrimoxazole_being_administered_to_the_infant` varchar(255) DEFAULT NULL,
 `sample_requestor_name` varchar(255) DEFAULT NULL,
 `sample_requestor_phone` varchar(255) DEFAULT NULL,
 `specimen_quality` varchar(255) DEFAULT NULL,
 `specimen_type` varchar(255) DEFAULT NULL,
 `reason_for_eid_test` int(11) DEFAULT NULL,
 `last_pcr_id` varchar(255) DEFAULT NULL,
 `last_pcr_date` date DEFAULT NULL,
 `reason_for_pcr` varchar(500) DEFAULT NULL,
 `rapid_test_performed` varchar(255) DEFAULT NULL,
 `rapid_test_date` date DEFAULT NULL,
 `rapid_test_result` varchar(255) DEFAULT NULL,
 `lab_id` int(11) DEFAULT NULL,
 `lab_technician` varchar(255) DEFAULT NULL,
 `lab_reception_person` varchar(255) DEFAULT NULL,
 `eid_test_platform` varchar(255) DEFAULT NULL,
 `result_status` int(11) DEFAULT NULL,
 `result` varchar(255) DEFAULT NULL,
 `result_reviewed_datetime` datetime DEFAULT NULL,
 `result_reviewed_by` varchar(255) DEFAULT NULL,
 `result_approved_datetime` datetime DEFAULT NULL,
 `result_approved_by` varchar(255) DEFAULT NULL,
 `approver_comments` varchar(1000) DEFAULT NULL,
 `result_dispatched_datetime` datetime DEFAULT NULL,
 `result_mail_datetime` datetime DEFAULT NULL,
 `manual_result_entry` varchar(255) DEFAULT 'no',
 `import_machine_name` varchar(255) DEFAULT NULL,
 `import_machine_file_name` varchar(255) DEFAULT NULL,
 `result_printed_datetime` datetime DEFAULT NULL,
 `request_created_datetime` datetime DEFAULT NULL,
 `request_created_by` varchar(255) DEFAULT NULL,
 `sample_registered_at_lab` datetime DEFAULT NULL,
 `last_modified_datetime` datetime DEFAULT NULL,
 `last_modified_by` varchar(255) DEFAULT NULL,
 `sample_batch_id` int(11) DEFAULT NULL,
 `sample_package_id` varchar(255) DEFAULT NULL,
 `sample_package_code` varchar(255) DEFAULT NULL,
 `lot_number` varchar(255) DEFAULT NULL,
 `lot_expiration_date` date DEFAULT NULL,
 `data_sync` int(11) NOT NULL DEFAULT '0',
 PRIMARY KEY (`eid_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;