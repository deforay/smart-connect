-- Three fixes that a database built from sys/schema/init.sql needs, and that
-- older installs may lack too: date indexes on the sample tables, the name of
-- the hepatitis risk-factor table, and the duplicate-sample archive table.
--
-- Nearly every dashboard query bounds dash_form_vl or dash_form_eid by
-- sample_collection_date or sample_tested_datetime. Neither column was indexed
-- by any migration or by data/alter.sql. Some long-running installs carry
-- indexes added by hand. A database built from sys/schema/init.sql has none, so
-- each bounded query scans the whole table once laboratories have synced.
--
-- vlsm indexes the same two columns on form_vl, under these names.
--
-- Replaying this file is safe. bin/migrate routes ALTER TABLE ... ADD INDEX
-- through add_index_if_missing, which skips an index when one of the same name
-- exists, or when any index already covers the same columns in the same order.
-- An install that indexed a column by hand under another name keeps that index,
-- and gets no second copy.
--
-- On a large table, building an index takes minutes. MySQL builds it online, so
-- syncs keep working, but more slowly. Upgrade outside the hours labs sync most.

ALTER TABLE `dash_form_vl` ADD INDEX `sample_collection_date` (`sample_collection_date`);
ALTER TABLE `dash_form_vl` ADD INDEX `sample_tested_datetime` (`sample_tested_datetime`);
ALTER TABLE `dash_form_eid` ADD INDEX `sample_collection_date` (`sample_collection_date`);
ALTER TABLE `dash_form_eid` ADD INDEX `sample_tested_datetime` (`sample_tested_datetime`);

-- The hepatitis risk-factor table carries a historical misspelling on some
-- installs, r_hepatitis_rick_factors. HepatitisRiskFactorTable and the metadata
-- sync use vlsm's name, r_hepatitis_risk_factors. Rename the old table where it
-- exists. bin/migrate downgrades the rename to a warning when the old table is
-- missing (1146) or the new one already exists (1050), so replaying is safe.
RENAME TABLE `r_hepatitis_rick_factors` TO `r_hepatitis_risk_factors`;

-- SampleTable::removeDuplicateSampleRows() copies each removed duplicate's whole
-- dash_form_vl row into removed_samples before deleting it. No migration created
-- the table, so on an install without it the removal fails and deletes nothing.
-- CREATE TABLE ... LIKE gives it dash_form_vl's columns, and IF NOT EXISTS
-- leaves an existing archive alone.
CREATE TABLE IF NOT EXISTS `removed_samples` LIKE `dash_form_vl`;

-- LIKE also copies dash_form_vl's unique keys. Duplicates share sample_code and
-- lab_id by definition, so the archive must not enforce them, or archiving the
-- second copy of a sample fails. bin/migrate skips each drop when the index is
-- absent.
ALTER TABLE `removed_samples` DROP INDEX `uniq_dash_form_vl_sample_code_lab_id`;
ALTER TABLE `removed_samples` DROP INDEX `uniq_dash_form_vl_sample_code_remote_sample_code`;
ALTER TABLE `removed_samples` DROP INDEX `sample_code`;
ALTER TABLE `removed_samples` DROP INDEX `remote_sample_code`;
ALTER TABLE `removed_samples` DROP INDEX `unique_id`;
