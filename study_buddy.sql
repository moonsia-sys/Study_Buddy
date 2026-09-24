-- Study Buddy Finder — database setup for XAMPP (phpMyAdmin)
-- Import this file in phpMyAdmin, or run the ALTER TABLE below if the
-- `users` table already exists and you only need the new Password column.

CREATE DATABASE IF NOT EXISTS `study_buddy` DEFAULT CHARSET=utf8mb4;
USE `study_buddy`;

CREATE TABLE IF NOT EXISTS `users` (
  `Sno` int(30) NOT NULL AUTO_INCREMENT,
  `Name` text NOT NULL,
  `Age` int(3) NOT NULL,
  `Gender` varchar(10) NOT NULL,
  `Email` varchar(120) NOT NULL,
  `Phone` varchar(20) NOT NULL,
  `Other` text NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Sno`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- If your table already exists, run these instead of the CREATE above:
-- ALTER TABLE `users` ADD `Password` varchar(255) NOT NULL AFTER `Other`;
-- ALTER TABLE `users` MODIFY `Email` varchar(120) NOT NULL;
-- ALTER TABLE `users` ADD UNIQUE KEY `Email` (`Email`);
