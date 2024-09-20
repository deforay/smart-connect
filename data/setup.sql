ALTER TABLE vlsm.vl_contact_notes drop FOREIGN KEY vl_contact_notes_ibfk_1;


DROP TABLE IF EXISTS `dash_form_vl`;
DROP TABLE IF EXISTS `dash_form_eid`;
DROP TABLE IF EXISTS `covid19_tests`;
DROP TABLE IF EXISTS `dash_form_covid19`;
DROP TABLE IF EXISTS `dash_form_hepatitis`;
DROP TABLE IF EXISTS `dash_form_tb`;

CREATE TABLE `dash_form_vl` SELECT * FROM vlsm.form_vl WHERE 1=0;
CREATE TABLE `dash_form_eid` SELECT * FROM vlsm.form_eid WHERE 1=0;
CREATE TABLE `covid19_tests` SELECT * FROM vlsm.covid19_tests WHERE 1=0;
CREATE TABLE `dash_form_covid19` SELECT * FROM vlsm.form_covid19 WHERE 1=0;
CREATE TABLE `dash_form_hepatitis` SELECT * FROM vlsm.form_hepatitis WHERE 1=0;
CREATE TABLE `dash_form_tb` SELECT * FROM vlsm.form_tb WHERE 1=0;

ALTER TABLE `dash_form_vl` CHANGE `vl_sample_id` `vl_sample_id` INT NOT NULL;
ALTER TABLE `dash_form_vl` ADD PRIMARY KEY(`vl_sample_id`);
ALTER TABLE `dash_form_vl` CHANGE `vl_sample_id` `vl_sample_id` INT NOT NULL AUTO_INCREMENT;

ALTER TABLE `dash_form_eid` CHANGE `eid_id` `eid_id` INT NOT NULL;
ALTER TABLE `dash_form_eid` ADD PRIMARY KEY(`eid_id`);
ALTER TABLE `dash_form_eid` CHANGE `eid_id` `eid_id` INT NOT NULL AUTO_INCREMENT;

ALTER TABLE `covid19_tests` CHANGE `test_id` `test_id` INT NOT NULL;
ALTER TABLE `covid19_tests` ADD PRIMARY KEY(`test_id`);
ALTER TABLE `covid19_tests` CHANGE `test_id` `test_id` INT NOT NULL AUTO_INCREMENT;

ALTER TABLE `dash_form_covid19` CHANGE `covid19_id` `covid19_id` INT NOT NULL;
ALTER TABLE `dash_form_covid19` ADD PRIMARY KEY(`covid19_id`);
ALTER TABLE `dash_form_covid19` CHANGE `covid19_id` `covid19_id` INT NOT NULL AUTO_INCREMENT;

ALTER TABLE `dash_form_hepatitis` CHANGE `hepatitis_id` `hepatitis_id` INT NOT NULL;
ALTER TABLE `dash_form_hepatitis` ADD PRIMARY KEY(`hepatitis_id`);
ALTER TABLE `dash_form_hepatitis` CHANGE `hepatitis_id` `hepatitis_id` INT NOT NULL AUTO_INCREMENT;

ALTER TABLE `dash_form_tb` CHANGE `tb_id` `tb_id` INT NOT NULL;
ALTER TABLE `dash_form_tb` ADD PRIMARY KEY(`tb_id`);
ALTER TABLE `dash_form_tb` CHANGE `tb_id` `tb_id` INT NOT NULL AUTO_INCREMENT;

ALTER TABLE `dash_form_vl` ADD UNIQUE( `sample_code`, `lab_id`);
ALTER TABLE `dash_form_eid` ADD UNIQUE( `sample_code`, `lab_id`);
ALTER TABLE `dash_form_covid19` ADD UNIQUE( `sample_code`, `lab_id`);
ALTER TABLE `dash_form_hepatitis` ADD UNIQUE( `sample_code`, `lab_id`);
ALTER TABLE `dash_form_tb` ADD UNIQUE( `sample_code`, `lab_id`);
ALTER TABLE `dash_form_vl` ADD UNIQUE(`remote_sample_code`);
ALTER TABLE `dash_form_eid` ADD UNIQUE(`remote_sample_code`);
ALTER TABLE `dash_form_covid19` ADD UNIQUE(`remote_sample_code`);
ALTER TABLE `dash_form_hepatitis` ADD UNIQUE(`remote_sample_code`);
ALTER TABLE `dash_form_tb` ADD UNIQUE(`remote_sample_code`);
ALTER TABLE `dash_form_vl` ADD UNIQUE(`unique_id`);
ALTER TABLE `dash_form_eid` ADD UNIQUE(`unique_id`);
ALTER TABLE `dash_form_covid19` ADD UNIQUE(`unique_id`);
ALTER TABLE `dash_form_hepatitis` ADD UNIQUE(`unique_id`);
ALTER TABLE `dash_form_tb` ADD UNIQUE(`unique_id`);

-- TRUNCATE TABLE dash_form_vl;
-- TRUNCATE TABLE dash_form_eid;
-- TRUNCATE TABLE dash_form_covid19;
-- TRUNCATE TABLE dash_form_hepatitis;
-- TRUNCATE TABLE dash_form_tb;

INSERT IGNORE INTO dash_form_vl SELECT * FROM vlsm.form_vl;
INSERT IGNORE INTO dash_form_eid SELECT * FROM vlsm.form_eid;
INSERT IGNORE INTO covid19_tests SELECT * FROM vlsm.covid19_tests;
INSERT IGNORE INTO dash_form_covid19 SELECT * FROM vlsm.form_covid19;
INSERT IGNORE INTO dash_form_hepatitis SELECT * FROM vlsm.form_hepatitis;
INSERT IGNORE INTO dash_form_tb SELECT * FROM vlsm.form_tb;


UPDATE facility_details SET facility_attributes = JSON_SET(COALESCE(facility_attributes, '{}'), '$.lastDashboardHeartBeat', (
        SELECT MAX(last_modified_datetime)
        FROM dash_form_vl
        WHERE facility_details.facility_id = dash_form_vl.lab_id
    ));
