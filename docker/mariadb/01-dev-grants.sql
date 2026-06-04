-- Local Docker dev: struktur.sql creates views with DEFINER=`openxe`@`localhost`.
-- The default MYSQL_USER only creates openxe@'%'; SET USER is required for those views.

CREATE USER IF NOT EXISTS 'openxe'@'localhost' IDENTIFIED BY 'openxe';
GRANT ALL PRIVILEGES ON `openxe`.* TO 'openxe'@'localhost';
GRANT ALL PRIVILEGES ON `openxe`.* TO 'openxe'@'%';
GRANT SET USER ON *.* TO 'openxe'@'localhost';
GRANT SET USER ON *.* TO 'openxe'@'%';
FLUSH PRIVILEGES;
