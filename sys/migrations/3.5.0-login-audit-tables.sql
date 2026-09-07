-- Create the two tables every login writes to.
--
-- UsersTable::login() writes a row to user_login_history on every attempt, and
-- ActivityLogTable::addActivityLog() writes one to activity_log on success.
-- Neither table was created by a migration or by data/setup.sql. Long-running
-- installs have them because they predate the migrator. A database built from
-- this repository does not, so the first login returns a 500:
--
--   Table 'vldashboard.user_login_history' doesn't exist
--
-- The failure lands after the password is verified, so the credentials are
-- correct and the operator sees a server error instead of the dashboard. There
-- is no way past it from the browser, which makes it exactly the kind of
-- per-installation repair that has to be done over a shell.
--
-- The definitions are taken from a running install rather than reconstructed
-- from the INSERT statements, so an upgraded database and a fresh one end up
-- with the same columns and types. Two details are load-bearing and would not
-- survive being guessed:
--
--   user_login_history.user_id  is varchar, and UsersTable::login() never sets
--                               it. The column has to stay nullable.
--   activity_log.user_id        is varchar(256), not an int, even though it
--                               holds dash_users.user_id.
--
-- The collation is utf8mb4_general_ci rather than the utf8mb4_0900_ai_ci the
-- running install reports. The 0900 collations are MySQL 8 only, and README.md
-- offers MariaDB as a supported engine, where that name raises an unknown
-- collation error and takes the whole migration down with it. general_ci is
-- what the rest of sys/migrations uses and both engines accept it. Installs
-- that already have these tables keep whatever collation they were built with,
-- because the statement is skipped there.
--
-- CREATE TABLE IF NOT EXISTS keeps this harmless on the installs that already
-- have the tables. bin/migrate routes it through create_table_if_missing,
-- which checks information_schema first, so replaying skips it either way.

CREATE TABLE IF NOT EXISTS `activity_log` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `event_type` varchar(255) DEFAULT NULL,
  `action` mediumtext,
  `resource` varchar(255) DEFAULT NULL,
  `user_id` varchar(256) DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  `ip_address` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- The index carries the grid on UserLoginHistoryController, which filters by
-- login_status and orders by login_attempted_datetime.
CREATE TABLE IF NOT EXISTS `user_login_history` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(1000) DEFAULT NULL,
  `login_id` varchar(1000) NOT NULL,
  `login_attempted_datetime` datetime DEFAULT NULL,
  `login_status` varchar(256) DEFAULT NULL,
  `ip_address` varchar(256) DEFAULT NULL,
  `browser` varchar(1000) DEFAULT NULL,
  `operating_system` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `login_status_attempted_datetime_idx` (`login_status`,`login_attempted_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
