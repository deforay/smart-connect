
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
