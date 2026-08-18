-- API failure tracking.
--
-- dash_track_api_requests recorded that a call happened and nothing about how it
-- went, so a failed call and a successful one were indistinguishable in the
-- listing. These columns are what /api-sync-history filters and sorts on.
--
-- error_message is kept on the row rather than only in the payload JSON because
-- housekeeping prunes those files at 120 days or 1000 MB, whichever comes first,
-- while the rows live a year. The reason a call failed outlives its payload.

ALTER TABLE `dash_track_api_requests` ADD `http_status` SMALLINT NULL DEFAULT NULL AFTER `data_format`;
ALTER TABLE `dash_track_api_requests` ADD `outcome` VARCHAR(20) NULL DEFAULT NULL AFTER `http_status`;
ALTER TABLE `dash_track_api_requests` ADD `error_message` TEXT NULL DEFAULT NULL AFTER `outcome`;

-- ApiErrorMiddleware already mints this id, returns it to the caller and writes
-- it to the PHP error log. Storing it turns an integrator quoting an id into one
-- query instead of a log trawl.
ALTER TABLE `dash_track_api_requests` ADD `error_id` VARCHAR(32) NULL DEFAULT NULL AFTER `error_message`;

ALTER TABLE `dash_track_api_requests` ADD `failed_records` INT NULL DEFAULT NULL AFTER `error_id`;
ALTER TABLE `dash_track_api_requests` ADD `duration_ms` INT NULL DEFAULT NULL AFTER `failed_records`;

ALTER TABLE `dash_track_api_requests` ADD INDEX `idx_dash_track_api_outcome` (`outcome`, `requested_on`);
ALTER TABLE `dash_track_api_requests` ADD INDEX `idx_dash_track_api_error_id` (`error_id`);

-- Rows written before this migration carry no outcome. Reading them as failures
-- would put every historical call into the failures-only view, so mark the ones
-- that recorded a response as successes and leave the rest null.
UPDATE `dash_track_api_requests`
   SET `outcome` = 'success', `http_status` = 200
 WHERE `outcome` IS NULL
   AND `response_data` IS NOT NULL;
