SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
ALTER TABLE `#__jdownloads_files` ADD `id` INT(11) NOT NULL ;
UPDATE #__jdownloads_files SET id=file_id;
CREATE TRIGGER `codigo` BEFORE UPDATE ON `#__jdownloads_files` FOR EACH ROW SET new.id = new.file_id;

