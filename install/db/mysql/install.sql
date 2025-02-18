-- Сущность options
CREATE TABLE IF NOT EXISTS `claramente_option_tabs`
(
    `ID` int        NOT NULL AUTO_INCREMENT,
    `NAME` varchar(255) NOT NULL,
    `CODE` varchar(255) NOT NULL,
    `SORT` int        NOT NULL DEFAULT '100',
    PRIMARY KEY (`ID`),
    UNIQUE KEY `CODE` (`CODE`)
);

-- Сущность tabs
CREATE TABLE IF NOT EXISTS `claramente_options` (
    `ID` int NOT NULL AUTO_INCREMENT,
    `NAME` varchar(255) NOT NULL,
    `CODE` varchar(255) NOT NULL,
    `VALUE` mediumtext,
    `SORT` int unsigned NOT NULL DEFAULT '100',
    `TAB_ID` int DEFAULT NULL,
    `TYPE` varchar(255) NOT NULL,
    `SITE_ID` varchar(12) DEFAULT NULL,
    `SETTINGS` text,
    `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `UPDATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `code_site_id` (`CODE`,`SITE_ID`),
    KEY `CODE` (`CODE`),
    KEY `TAB_ID` (`TAB_ID`),
    CONSTRAINT `TAB_ID` FOREIGN KEY (`TAB_ID`) REFERENCES `claramente_option_tabs` (`ID`) ON DELETE SET NULL
);

--  Обновление 1.0.1 от 2025-02-18
SET @update_1_0_1 = (
    SELECT IF(
        (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'claramente_options' AND COLUMN_NAME = 'IS_ADMIN_ONLY'
        ) > 0,
        'SELECT 1;',
        'ALTER TABLE claramente_options ADD COLUMN IS_ADMIN_ONLY TINYINT(1) NOT NULL DEFAULT 0;'
    )
);
PREPARE stmt FROM @update_1_0_1;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;