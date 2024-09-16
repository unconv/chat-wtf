ALTER TABLE `conversations` CHANGE `id` `id` VARCHAR(36) NOT NULL;
ALTER TABLE `conversations` ADD `created_time` DATETIME NULL DEFAULT NULL AFTER `mode`;
ALTER TABLE `messages` CHANGE `conversation` `conversation` VARCHAR(36) NOT NULL;
