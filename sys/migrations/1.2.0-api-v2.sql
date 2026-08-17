-- API v2: lab client tokens.
--
-- One row per enrolled LIS instance. The token itself is never stored — only
-- its sha256 — so a dump of this table cannot be replayed against the API.
-- Revoking is a status change; the client re-enrolls to get a fresh token.

CREATE TABLE IF NOT EXISTS `dash_api_clients` (
  `client_id` int NOT NULL AUTO_INCREMENT,
  `instance_uuid` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `lab_id` int DEFAULT NULL,
  `facility_code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `status` enum('active','revoked') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `enrolled_on` datetime DEFAULT NULL,
  `enrolled_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL,
  PRIMARY KEY (`client_id`),
  UNIQUE KEY `uniq_dash_api_clients_instance` (`instance_uuid`),
  UNIQUE KEY `uniq_dash_api_clients_token` (`token_hash`),
  KEY `idx_dash_api_clients_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
