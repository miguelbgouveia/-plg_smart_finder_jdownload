SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

ALTER TABLE `#__jdownloads_files` DROP `id`;

DROP TRIGGER IF EXISTS `codigo`;
