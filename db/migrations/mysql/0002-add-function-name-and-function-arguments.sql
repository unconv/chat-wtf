ALTER TABLE `messages`
ADD COLUMN `function_name` varchar(64) NULL COLLATE utf8mb4_swedish_ci AFTER `content`,
ADD COLUMN `function_arguments` TEXT NULL COLLATE utf8mb4_swedish_ci AFTER `function_name`;
