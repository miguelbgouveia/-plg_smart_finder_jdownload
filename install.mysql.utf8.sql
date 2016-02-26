SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
ALTER TABLE `pagesp_jdownloads_files` ADD `id` INT(11) NOT NULL ;
UPDATE pagesp_jdownloads_files SET id=file_id;
CREATE TRIGGER `codigo` BEFORE UPDATE ON `pagesp_jdownloads_files` FOR EACH ROW SET new.id = new.file_id;

