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

-- Amit 18 June 2020

--
-- Table structure for table `r_eid_results`
--

CREATE TABLE `r_eid_results` (
  `result_id` varchar(255) NOT NULL,
  `result` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `r_eid_results`
--

INSERT INTO `r_eid_results` (`result_id`, `result`, `status`, `data_sync`) VALUES
('indeterminate', 'Indeterminate', 'active', 0),
('negative', 'Negative', 'active', 0),
('positive', 'Positive', 'active', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_eid_sample_rejection_reasons`
--

CREATE TABLE `r_eid_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `r_eid_sample_type`
--

CREATE TABLE `r_eid_sample_type` (
  `sample_id` int(11) NOT NULL,
  `sample_name` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `status` varchar(45) CHARACTER SET latin1 DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `r_eid_sample_type`
--

INSERT INTO `r_eid_sample_type` (`sample_id`, `sample_name`, `status`, `data_sync`) VALUES
(1, 'DBS', 'active', 0),
(2, 'Whole Blood', 'active', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_eid_test_reasons`
--

CREATE TABLE `r_eid_test_reasons` (
  `test_reason_id` int(11) NOT NULL,
  `test_reason_name` varchar(255) DEFAULT NULL,
  `test_reason_status` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `r_eid_results`
--
ALTER TABLE `r_eid_results`
  ADD PRIMARY KEY (`result_id`);

--
-- Indexes for table `r_eid_sample_rejection_reasons`
--
ALTER TABLE `r_eid_sample_rejection_reasons`
  ADD PRIMARY KEY (`rejection_reason_id`);

--
-- Indexes for table `r_eid_sample_type`
--
ALTER TABLE `r_eid_sample_type`
  ADD PRIMARY KEY (`sample_id`);

--
-- Indexes for table `r_eid_test_reasons`
--
ALTER TABLE `r_eid_test_reasons`
  ADD PRIMARY KEY (`test_reason_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `r_eid_sample_rejection_reasons`
--
ALTER TABLE `r_eid_sample_rejection_reasons`
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_eid_sample_type`
--
ALTER TABLE `r_eid_sample_type`
  MODIFY `sample_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `r_eid_test_reasons`
--
ALTER TABLE `r_eid_test_reasons`
  MODIFY `test_reason_id` int(11) NOT NULL AUTO_INCREMENT;

-- Amit 22 June 2020

ALTER TABLE `dash_eid_form` ADD `pcr_test_performed_before` VARCHAR(255) NULL DEFAULT NULL AFTER `reason_for_eid_test`;

-- Thana 17 Jul, 2020
INSERT INTO `resources` (`resource_id`, `resource_name`, `display_name`) VALUES (NULL, 'covid19-dashboard', 'Covid-19 Dashboard');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '19', 'dashboard', 'Dashboard');

-- Thana 31-Jul-2020
CREATE TABLE `dash_form_covid19` (
 `covid19_id` int NOT NULL AUTO_INCREMENT,
 `vlsm_instance_id` varchar(255) DEFAULT NULL,
 `vlsm_country_id` int NOT NULL,
 `sample_code_key` int DEFAULT NULL,
 `sample_code_format` varchar(255) DEFAULT NULL,
 `sample_code` varchar(255) DEFAULT NULL,
 `test_number` int DEFAULT NULL,
 `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
 `remote_sample_code_key` int DEFAULT NULL,
 `remote_sample_code_format` varchar(255) DEFAULT NULL,
 `remote_sample_code` varchar(255) DEFAULT NULL,
 `sample_collection_date` datetime NOT NULL,
 `sample_received_at_hub_datetime` datetime DEFAULT NULL,
 `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
 `sample_condition` varchar(255) DEFAULT NULL,
 `sample_tested_datetime` datetime DEFAULT NULL,
 `funding_source` int DEFAULT NULL,
 `implementing_partner` int DEFAULT NULL,
 `facility_id` int DEFAULT NULL,
 `province_id` int DEFAULT NULL,
 `patient_id` varchar(255) DEFAULT NULL,
 `patient_name` varchar(255) DEFAULT NULL,
 `patient_surname` varchar(255) DEFAULT NULL,
 `patient_dob` date DEFAULT NULL,
 `patient_age` varchar(255) DEFAULT NULL,
 `patient_gender` varchar(255) DEFAULT NULL,
 `is_patient_pregnant` varchar(255) DEFAULT NULL,
 `patient_phone_number` varchar(255) DEFAULT NULL,
 `patient_nationality` varchar(255) DEFAULT NULL,
 `patient_occupation` varchar(255) DEFAULT NULL,
 `patient_address` varchar(1000) DEFAULT NULL,
 `flight_airline` varchar(255) DEFAULT NULL,
 `flight_seat_no` varchar(255) DEFAULT NULL,
 `flight_arrival_datetime` datetime DEFAULT NULL,
 `flight_airport_of_departure` varchar(255) DEFAULT NULL,
 `flight_transit` varchar(255) DEFAULT NULL,
 `reason_of_visit` varchar(500) DEFAULT NULL,
 `is_sample_collected` varchar(255) DEFAULT NULL,
 `reason_for_covid19_test` int DEFAULT NULL,
 `patient_province` varchar(255) DEFAULT NULL,
 `patient_district` varchar(255) DEFAULT NULL,
 `specimen_type` varchar(255) DEFAULT NULL,
 `is_sample_post_mortem` varchar(255) DEFAULT NULL,
 `priority_status` varchar(255) DEFAULT NULL,
 `number_of_days_sick` int DEFAULT NULL,
 `date_of_symptom_onset` date DEFAULT NULL,
 `date_of_initial_consultation` date DEFAULT NULL,
 `medical_history` varchar(255) DEFAULT NULL,
 `recent_hospitalization` varchar(255) DEFAULT NULL,
 `patient_lives_with_children` varchar(255) DEFAULT NULL,
 `patient_cares_for_children` varchar(255) DEFAULT NULL,
 `fever_temp` varchar(255) DEFAULT NULL,
 `temperature_measurement_method` varchar(255) DEFAULT NULL,
 `respiratory_rate` int DEFAULT NULL,
 `oxygen_saturation` double DEFAULT NULL,
 `close_contacts` text,
 `contact_with_confirmed_case` varchar(255) DEFAULT NULL,
 `has_recent_travel_history` varchar(255) DEFAULT NULL,
 `travel_country_names` varchar(255) DEFAULT NULL,
 `travel_return_date` date DEFAULT NULL,
 `lab_id` int DEFAULT NULL,
 `lab_technician` varchar(255) DEFAULT NULL,
 `lab_reception_person` varchar(255) DEFAULT NULL,
 `covid19_test_platform` varchar(255) DEFAULT NULL,
 `result_status` int DEFAULT NULL,
 `is_sample_rejected` varchar(255) NOT NULL DEFAULT 'no',
 `reason_for_sample_rejection` varchar(500) DEFAULT NULL,
 `rejection_on` date DEFAULT NULL,
 `result` varchar(255) DEFAULT NULL,
 `other_diseases` text,
 `is_result_authorised` varchar(255) DEFAULT NULL,
 `authorized_by` varchar(255) DEFAULT NULL,
 `authorized_on` date DEFAULT NULL,
 `reason_for_changing` text,
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
 `sample_batch_id` int DEFAULT NULL,
 `sample_package_id` varchar(255) DEFAULT NULL,
 `sample_package_code` varchar(255) DEFAULT NULL,
 `positive_test_manifest_id` int DEFAULT NULL,
 `positive_test_manifest_code` varchar(255) DEFAULT NULL,
 `lot_number` varchar(255) DEFAULT NULL,
 `lot_expiration_date` date DEFAULT NULL,
 `suspected_case_drc` text,
 `probable_case_drc` text,
 `confirme_case_drc` text,
 `contact_case_drc` text,
 `respiratory_rate_option_drc` varchar(255) DEFAULT NULL,
 `respiratory_rate_drc` varchar(255) DEFAULT NULL,
 `oxygen_saturation_option_drc` varchar(255) DEFAULT NULL,
 `oxygen_saturation_drc` varchar(255) DEFAULT NULL,
 `sick_days_drc` varchar(255) DEFAULT NULL,
 `onset_illness_date_drc` date DEFAULT NULL,
 `medical_background_drc` varchar(255) DEFAULT NULL,
 `past3_weeks_mbg_drc` varchar(255) DEFAULT NULL,
 `take_crae_childrens_mbg_drc` varchar(255) DEFAULT NULL,
 `live_childrens_mbg_drc` varchar(255) DEFAULT NULL,
 `hospitalized_mbg_drc` varchar(255) DEFAULT NULL,
 `ancient_tuberculosis_mbg_drc` varchar(255) DEFAULT NULL,
 `active_tuberculosis_mbg_drc` varchar(255) DEFAULT NULL,
 `chronic_cough_mbg_drc` varchar(255) DEFAULT NULL,
 `cancer_mbg_drc` varchar(255) DEFAULT NULL,
 `asthma_mbg_drc` varchar(255) DEFAULT NULL,
 `recurrent_chest_pain_mbg_drc` varchar(255) DEFAULT NULL,
 `dyspnea_chronic_respiratory_mbg_drc` varchar(255) DEFAULT NULL,
 `heart_disease_mbg_drc` varchar(255) DEFAULT NULL,
 `conacted_14_days_drc` varchar(255) DEFAULT NULL,
 `smoke_drc` varchar(255) DEFAULT NULL,
 `profession_drc` varchar(255) DEFAULT NULL,
 `confirmation_lab_drc` varchar(255) DEFAULT NULL,
 `result_pscr_drc` date DEFAULT NULL,
 `is_result_mail_sent` varchar(255) DEFAULT 'no',
 `last_modified_datetime` datetime DEFAULT NULL,
 `last_modified_by` varchar(255) DEFAULT NULL,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`covid19_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Amit 13 Aug 2020

RENAME TABLE `r_sample_type` TO `r_vl_sample_type`;
ALTER TABLE `dash_vl_request_form` ADD `province_id` VARCHAR(255) NULL DEFAULT NULL AFTER `facility_id`;
ALTER TABLE `dash_vl_request_form` ADD `reason_for_vl_testing_other` VARCHAR(255) NULL DEFAULT NULL AFTER `reason_for_vl_testing`;
ALTER TABLE `province_details` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `province_code`;
ALTER TABLE `r_vl_sample_type` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `status`;
-- ALTER TABLE `r_eid_sample_type` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `status`;
-- ALTER TABLE `r_covid19_sample_type` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `status`;
-- ALTER TABLE `r_covid19_comorbidities` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `comorbidity_status`;
-- ALTER TABLE `r_covid19_results` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `status`;
-- ALTER TABLE `r_covid19_symptoms` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `symptom_status`;
-- ALTER TABLE `r_covid19_test_reasons` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `test_reason_status`;


-- Amit 24 August 2020
ALTER TABLE `dash_vl_request_form` ADD INDEX(`last_modified_datetime`);
ALTER TABLE `dash_eid_form` ADD INDEX(`last_modified_datetime`);
ALTER TABLE `dash_form_covid19` ADD INDEX(`last_modified_datetime`);

-- Thana 1 Sep 2020
ALTER TABLE `facility_details` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `facility_logo`;


CREATE TABLE `r_covid19_test_reasons` (
 `test_reason_id` int NOT NULL AUTO_INCREMENT,
 `test_reason_name` varchar(255) DEFAULT NULL,
 `parent_reason` int DEFAULT NULL,
 `test_reason_status` varchar(45) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE `r_covid19_symptoms` (
 `symptom_id` int NOT NULL AUTO_INCREMENT,
 `symptom_name` varchar(255) DEFAULT NULL,
 `parent_symptom` int DEFAULT NULL,
 `symptom_status` varchar(45) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 PRIMARY KEY (`symptom_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE `r_covid19_sample_type` (
 `sample_id` int NOT NULL AUTO_INCREMENT,
 `sample_name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
 `status` varchar(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE `r_covid19_sample_rejection_reasons` (
 `rejection_reason_id` int NOT NULL AUTO_INCREMENT,
 `rejection_reason_name` varchar(255) DEFAULT NULL,
 `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
 `rejection_reason_status` varchar(255) DEFAULT NULL,
 `rejection_reason_code` varchar(255) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

CREATE TABLE `r_covid19_comorbidities` (
 `comorbidity_id` int NOT NULL AUTO_INCREMENT,
 `comorbidity_name` varchar(255) DEFAULT NULL,
 `comorbidity_status` varchar(45) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 PRIMARY KEY (`comorbidity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

ALTER TABLE `facility_details` ADD `header_text` VARCHAR(255) NULL DEFAULT NULL AFTER `facility_type`;
ALTER TABLE `r_art_code_details` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `nation_identifier`;
ALTER TABLE `r_art_code_details` ADD `headings` VARCHAR(255) NULL DEFAULT NULL AFTER `updated_datetime`, ADD `art_status` VARCHAR(45) NULL DEFAULT NULL AFTER `headings`;

RENAME TABLE `r_sample_rejection_reasons` TO `r_vl_sample_rejection_reasons`;
ALTER TABLE `r_vl_sample_rejection_reasons` ADD `rejection_type` VARCHAR(255) NULL DEFAULT NULL AFTER `rejection_reason_id`, ADD `rejection_reason_code` VARCHAR(255) NULL DEFAULT NULL AFTER `rejection_type`;

/* Thana 17 Sep 2020 */
ALTER TABLE `dash_form_covid19` ADD `type_of_test_requested` VARCHAR(255) NULL DEFAULT NULL AFTER `reason_for_covid19_test`;
ALTER TABLE `dash_form_covid19` ADD `patient_city` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_district`;
/* Thana 05 Nov 2020 */
ALTER TABLE `r_vl_test_reasons` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `test_reason_status`;
/* Thana 06 Nov 2020 */
CREATE TABLE `import_config_machines` (
 `config_machine_id` int(11) NOT NULL AUTO_INCREMENT,
 `config_id` int(11) NOT NULL,
 `config_machine_name` varchar(255) NOT NULL,
 `poc_device` varchar(255) DEFAULT NULL,
 `latitude` varchar(255) DEFAULT NULL,
 `longitude` varchar(255) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 PRIMARY KEY (`config_machine_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

-- Sudarmathi 27 Nov 2020

DROP TABLE IF EXISTS r_vl_test_reasons;
CREATE TABLE `r_vl_test_reasons` (
 `test_reason_id` int(11) NOT NULL AUTO_INCREMENT,
 `test_reason_name` varchar(255) DEFAULT NULL,
 `parent_reason` int(11) DEFAULT '0',
 `test_reason_status` varchar(45) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 `data_sync` int(11) DEFAULT '0',
 PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS r_hepatitis_sample_rejection_reasons;
CREATE TABLE `r_hepatitis_sample_rejection_reasons` (
 `rejection_reason_id` int NOT NULL AUTO_INCREMENT,
 `rejection_reason_name` varchar(255) DEFAULT NULL,
 `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
 `rejection_reason_status` varchar(255) DEFAULT NULL,
 `rejection_reason_code` varchar(255) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS r_hepatitis_sample_type;
CREATE TABLE `r_hepatitis_sample_type` (
 `sample_id` int NOT NULL AUTO_INCREMENT,
 `sample_name` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
 `status` varchar(45) CHARACTER SET latin1 DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS r_hepatitis_test_reasons;
CREATE TABLE `r_hepatitis_test_reasons` (
 `test_reason_id` int NOT NULL AUTO_INCREMENT,
 `test_reason_name` varchar(255) DEFAULT NULL,
 `parent_reason` int DEFAULT NULL,
 `test_reason_status` varchar(45) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS r_hepatitis_results;
CREATE TABLE `r_hepatitis_results` (
 `result_id` varchar(255) NOT NULL,
 `result` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
 `status` varchar(255) NOT NULL DEFAULT 'active',
 `updated_datetime` datetime DEFAULT NULL,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`result_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS r_hepatitis_risk_factors;
CREATE TABLE `r_hepatitis_risk_factors` (
 `riskfactor_id` int(11) NOT NULL AUTO_INCREMENT,
 `riskfactor_name` varchar(255) DEFAULT NULL,
 `riskfactor_status` varchar(45) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 PRIMARY KEY (`riskfactor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

-- Thana 09-Dec-2020
ALTER TABLE `r_eid_test_reasons` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `test_reason_status`;
-- Thana 25-Feb-2021
RENAME TABLE  `dash_covid19_form` TO `dash_form_covid19`;
ALTER TABLE `dash_form_covid19` CHANGE `is_sample_rejected` `is_sample_rejected` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT 'no', CHANGE `data_sync` `data_sync` INT NULL DEFAULT '0';

-- Thana 02-Aug-2021
ALTER TABLE `location_details` ADD `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `longitude`; 
ALTER TABLE `facility_details` ADD `email` VARCHAR(256) NULL DEFAULT NULL AFTER `facility_code`;


-- Amit 24 Sep 2021
ALTER TABLE `facility_details` ADD `testing_points` JSON NULL DEFAULT NULL AFTER `facility_type`;
ALTER TABLE `facility_details` ADD `test_type` VARCHAR(256) NULL;
ALTER TABLE `facility_details` ADD `report_format` TEXT NULL DEFAULT NULL AFTER `test_type`; 


ALTER TABLE `dash_vl_request_form` ADD UNIQUE( `sample_code`, `lab_id`);
ALTER TABLE `dash_vl_request_form` ADD UNIQUE( `sample_code`, `remote_sample_code`);
ALTER TABLE `dash_eid_form` ADD UNIQUE( `sample_code`, `lab_id`);
ALTER TABLE `dash_eid_form` ADD UNIQUE( `sample_code`, `remote_sample_code`);
ALTER TABLE `dash_form_covid19` ADD UNIQUE( `sample_code`, `lab_id`);
ALTER TABLE `dash_form_covid19` ADD UNIQUE( `sample_code`, `remote_sample_code`);
ALTER TABLE `dash_vl_request_form` CHANGE `vldash_sync` `vldash_sync` INT(11) NULL DEFAULT '0';
ALTER TABLE `dash_vl_request_form` CHANGE `vlsm_country_id` `vlsm_country_id` INT(11) NULL DEFAULT NULL;


-- Amit 27 Jan 2022
ALTER TABLE `dash_form_covid19` ADD `source_of_request` VARCHAR(255) NULL DEFAULT NULL AFTER `lot_number`;
ALTER TABLE `dash_eid_form` ADD `source_of_request` VARCHAR(50) NULL DEFAULT NULL AFTER `lot_number`;
ALTER TABLE `dash_vl_request_form` ADD `source_of_request` VARCHAR(50) NULL DEFAULT NULL AFTER `vldash_sync`;




ALTER TABLE `dash_vl_request_form` ADD `revised_by` VARCHAR(500) NULL DEFAULT NULL AFTER `result_approved_datetime`, ADD `revised_on` DATETIME NULL DEFAULT NULL AFTER `revised_by`;
ALTER TABLE `dash_eid_form` ADD `revised_by` VARCHAR(500) NULL DEFAULT NULL AFTER `result_approved_datetime`, ADD `revised_on` DATETIME NULL DEFAULT NULL AFTER `revised_by`;
ALTER TABLE `dash_form_covid19` ADD `revised_by` VARCHAR(500) NULL DEFAULT NULL AFTER `authorized_on`, ADD `revised_on` DATETIME NULL DEFAULT NULL AFTER `revised_by`;



ALTER TABLE `dash_eid_form` CHANGE `mother_id` `mother_id` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `mother_name` `mother_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `mother_surname` `mother_surname` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `caretaker_contact_consent` `caretaker_contact_consent` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `caretaker_phone_number` `caretaker_phone_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `caretaker_address` `caretaker_address` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_id` `child_id` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_name` `child_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_surname` `child_surname` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `mother_vl_test_date` `mother_vl_test_date` DATE NULL DEFAULT NULL, CHANGE `sample_requestor_name` `sample_requestor_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lab_technician` `lab_technician` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lab_reception_person` `lab_reception_person` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `tested_by` `tested_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result_reviewed_by` `result_reviewed_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `revised_by` `revised_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result_approved_by` `result_approved_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `approver_comments` `approver_comments` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `import_machine_name` `import_machine_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `import_machine_file_name` `import_machine_file_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `request_created_by` `request_created_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_modified_by` `last_modified_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `sample_package_code` `sample_package_code` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lot_number` `lot_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `source_of_request` `source_of_request` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;




ALTER TABLE `dash_form_covid19` ADD `investigator_name` TEXT NULL DEFAULT NULL AFTER `lab_technician`, ADD `investigator_phone` TEXT NULL DEFAULT NULL AFTER `investigator_name`, ADD `investigator_email` TEXT NULL DEFAULT NULL AFTER `investigator_phone`, ADD `clinician_name` TEXT NULL DEFAULT NULL AFTER `investigator_email`, ADD `clinician_phone` TEXT NULL DEFAULT NULL AFTER `clinician_name`, ADD `clinician_email` TEXT NULL DEFAULT NULL AFTER `clinician_phone`, ADD `health_outcome` TEXT NULL DEFAULT NULL AFTER `clinician_email`, ADD `health_outcome_date` DATE NULL DEFAULT NULL AFTER `health_outcome`; 
ALTER TABLE `dash_form_covid19` ADD `patient_zone` TEXT NULL DEFAULT NULL AFTER `patient_district`;
ALTER TABLE `form_covid19` CHANGE `sample_code_format` `sample_code_format` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `tested_by` `tested_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `source_of_alert` `source_of_alert` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `source_of_alert_other` `source_of_alert_other` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_id` `patient_id` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_name` `patient_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_surname` `patient_surname` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_passport_number` `patient_passport_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_occupation` `patient_occupation` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_address` `patient_address` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `flight_airline` `flight_airline` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `flight_airport_of_departure` `flight_airport_of_departure` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `reason_of_visit` `reason_of_visit` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `type_of_test_requested` `type_of_test_requested` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `is_result_mail_sent` `is_result_mail_sent` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no', CHANGE `last_modified_by` `last_modified_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `dash_form_covid19` CHANGE `remote_sample_code` `remote_sample_code` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_name` `patient_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_surname` `patient_surname` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_phone_number` `patient_phone_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_passport_number` `patient_passport_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `does_patient_smoke` `does_patient_smoke` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `flight_airline` `flight_airline` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `flight_seat_no` `flight_seat_no` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `flight_airport_of_departure` `flight_airport_of_departure` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `flight_transit` `flight_transit` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `type_of_test_requested` `type_of_test_requested` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_province` `patient_province` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_district` `patient_district` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_zone` `patient_zone` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_city` `patient_city` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `medical_history` `medical_history` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `contact_with_confirmed_case` `contact_with_confirmed_case` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `has_recent_travel_history` `has_recent_travel_history` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `travel_country_names` `travel_country_names` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lab_technician` `lab_technician` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `investigator_name` `investigator_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `investigator_phone` `investigator_phone` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `investigator_email` `investigator_email` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `clinician_name` `clinician_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `reason_for_sample_rejection` `reason_for_sample_rejection` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result` `result` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `revised_by` `revised_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `approver_comments` `approver_comments` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `request_created_by` `request_created_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `source_of_request` `source_of_request` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;