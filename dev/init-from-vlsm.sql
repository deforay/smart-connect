SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `vldashboard`.facility_details;
CREATE TABLE IF NOT EXISTS `vldashboard`.facility_details LIKE `vlsm`.facility_details;
INSERT INTO `vldashboard`.facility_details SELECT * FROM `vlsm`.facility_details;

DROP TABLE IF EXISTS `vldashboard`.instruments;
CREATE TABLE IF NOT EXISTS `vldashboard`.instruments LIKE `vlsm`.instruments;
INSERT INTO `vldashboard`.instruments SELECT * FROM `vlsm`.instruments;

DROP TABLE IF EXISTS `vldashboard`.geographical_divisions;
CREATE TABLE IF NOT EXISTS `vldashboard`.geographical_divisions LIKE `vlsm`.geographical_divisions;
INSERT INTO `vldashboard`.geographical_divisions SELECT * FROM `vlsm`.geographical_divisions;

DROP TABLE IF EXISTS `vldashboard`.r_vl_sample_type;
CREATE TABLE IF NOT EXISTS `vldashboard`.r_vl_sample_type LIKE `vlsm`.r_vl_sample_type;
INSERT INTO `vldashboard`.r_vl_sample_type SELECT * FROM `vlsm`.r_vl_sample_type;

DROP TABLE IF EXISTS `vldashboard`.r_vl_test_reasons;
CREATE TABLE IF NOT EXISTS `vldashboard`.r_vl_test_reasons LIKE `vlsm`.r_vl_test_reasons;
INSERT INTO `vldashboard`.r_vl_test_reasons SELECT * FROM `vlsm`.r_vl_test_reasons;

DROP TABLE IF EXISTS `vldashboard`.r_vl_sample_rejection_reasons;
CREATE TABLE IF NOT EXISTS `vldashboard`.r_vl_sample_rejection_reasons LIKE `vlsm`.r_vl_sample_rejection_reasons;
INSERT INTO `vldashboard`.r_vl_sample_rejection_reasons SELECT * FROM `vlsm`.r_vl_sample_rejection_reasons;

DROP TABLE IF EXISTS `vldashboard`.r_eid_sample_type;
CREATE TABLE IF NOT EXISTS `vldashboard`.r_eid_sample_type LIKE `vlsm`.r_eid_sample_type;
INSERT INTO `vldashboard`.r_eid_sample_type SELECT * FROM `vlsm`.r_eid_sample_type;

DROP TABLE IF EXISTS `vldashboard`.dash_form_vl;
CREATE TABLE IF NOT EXISTS `vldashboard`.dash_form_vl LIKE `vlsm`.form_vl;
INSERT INTO `vldashboard`.dash_form_vl SELECT * FROM `vlsm`.form_vl;

DROP TABLE IF EXISTS `vldashboard`.dash_form_eid;
CREATE TABLE IF NOT EXISTS `vldashboard`.dash_form_eid LIKE `vlsm`.form_eid;
INSERT INTO `vldashboard`.dash_form_eid SELECT * FROM `vlsm`.form_eid;

DROP TABLE IF EXISTS `vldashboard`.dash_form_covid19;
CREATE TABLE IF NOT EXISTS `vldashboard`.dash_form_covid19 LIKE `vlsm`.form_covid19;
INSERT INTO `vldashboard`.dash_form_covid19 SELECT * FROM `vlsm`.form_covid19;

SET FOREIGN_KEY_CHECKS = 1;


