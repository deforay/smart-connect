-- Drop index definitions that duplicate another index on the same columns.
--
-- Eight indexes across dash_form_vl and dash_form_eid cover columns that another
-- index on the same table already covers, in the same order. MySQL maintains
-- every one of them on every insert. It reads only one. On the DRC data they
-- hold 215 MB, and dash_form_vl carries more index than data: 1.66 GB of index
-- against 1.2 GB of rows.
--
-- The cost lands on ingestion. INSERT INTO dash_form_vl is the single largest
-- consumer of server time in performance_schema, and each insert writes eight
-- index entries that no query needs.
--
-- Each drop leaves an identical surviving index, so no access path changes and
-- no constraint weakens. The pairs are matched by column list and by
-- uniqueness, not by name:
--
--   funding_source_2              duplicates funding_source
--   implementing_partner_2        duplicates implementing_partner
--   reason_for_sample_rejection_2 duplicates reason_for_sample_rejection
--   reason_for_vl_testing_2       duplicates reason_for_vl_testing
--   sample_batch_id_2             duplicates sample_batch_id
--   remote_sample_code_2          duplicates remote_sample_code, both UNIQUE
--
-- The `_2` names come from repeated ALTER TABLE ADD INDEX against live
-- databases over several years. MySQL appends a suffix rather than refusing a
-- second index on the same column. None of them appear in 3.0.0-baseline.sql,
-- so a fresh install never creates them and every drop here is skipped.
--
-- The legacy `sample_code` index on (sample_code, lab_id) is deliberately NOT
-- dropped, on either table, even though an upgraded install carries both it and
-- uniq_dash_form_vl_sample_code_lab_id. On a fresh install it is the only
-- constraint there is:
--
--   1. data/setup.sql runs ALTER TABLE ... ADD UNIQUE(`sample_code`, `lab_id`)
--      without naming it, and MySQL names it after its first column,
--      `sample_code`.
--   2. 3.0.0-baseline.sql then asks for the same constraint as
--      uniq_dash_form_vl_sample_code_lab_id. bin/migrate matches unique indexes
--      by column list rather than by name, exactly so an install does not end
--      up with two copies, so it skips that statement.
--   3. The uniq_* name therefore never exists on a fresh install.
--
-- Dropping `sample_code` here would leave (sample_code, lab_id) with no unique
-- index at all, which is the key CommonService::upsert relies on for
-- INSERT ... ON DUPLICATE KEY UPDATE. Ingestion would start storing duplicate
-- samples for one laboratory instead of updating them.
--
-- Renaming it to the uniq_* name would fix the naming on a fresh install, but
-- MySQL raises 1176 for a missing key on replay, and bin/migrate does not treat
-- 1176 as warnable, so the migration would fail the second time it ran. The
-- duplicate is left in place on upgraded installs until there is a helper that
-- can drop an index only when a named equivalent survives.
--
-- One deliberate exception: dash_form_vl.remote_sample_code stays. It is a
-- single column UNIQUE index, which is a stricter constraint than the composite
-- uniq_dash_form_vl_sample_code_remote_sample_code. Dropping both would relax
-- what the table enforces, so only the _2 copy goes.
--
-- bin/migrate routes ALTER TABLE ... DROP INDEX through drop_index_if_exists,
-- which checks information_schema first. Replaying this file is safe.

ALTER TABLE `dash_form_vl` DROP INDEX `funding_source_2`;
ALTER TABLE `dash_form_vl` DROP INDEX `implementing_partner_2`;
ALTER TABLE `dash_form_vl` DROP INDEX `reason_for_sample_rejection_2`;
ALTER TABLE `dash_form_vl` DROP INDEX `reason_for_vl_testing_2`;
ALTER TABLE `dash_form_vl` DROP INDEX `sample_batch_id_2`;
ALTER TABLE `dash_form_vl` DROP INDEX `remote_sample_code_2`;

-- dash_form_eid carries the same accretion on a smaller table.
--
--   remote_sample_code_key_2 duplicates remote_sample_code_key
--   sample_code_key_2        duplicates sample_code_key

ALTER TABLE `dash_form_eid` DROP INDEX `remote_sample_code_key_2`;
ALTER TABLE `dash_form_eid` DROP INDEX `sample_code_key_2`;
