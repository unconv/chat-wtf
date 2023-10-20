ALTER TABLE `conversations`
ADD COLUMN `model` varchar(64) COLLATE utf8mb4_swedish_ci AFTER `title`,
ADD COLUMN `mode` varchar(16) COLLATE utf8mb4_swedish_ci AFTER `model`;
