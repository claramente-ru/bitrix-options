CREATE TABLE IF NOT EXISTS `claramente_option_tabs`
(
    `ID` int        NOT NULL AUTO_INCREMENT,
    `NAME` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
    `CODE` varchar(255) NOT NULL,
    `SORT` int        NOT NULL DEFAULT '100',
    PRIMARY KEY (`ID`),
    UNIQUE KEY `CODE` (`CODE`)
);
CREATE TABLE IF NOT EXISTS `claramente_options` (
    `ID` int NOT NULL AUTO_INCREMENT,
    `NAME` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
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