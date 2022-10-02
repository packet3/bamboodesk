CREATE TABLE `td_ticket_statuses` (
                                      `id` int(11) NOT NULL AUTO_INCREMENT,
                                      `status_name` varchar(255) NOT NULL,
                                      `type` tinyint(1) NOT NULL DEFAULT '0',
                                      `default` tinyint(1) NOT NULL DEFAULT '0',
                                      `position` int(11) NOT NULL DEFAULT '0',
                                      PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

INSERT INTO `bamboodesk`.`td_ticket_statuses`
(`status_name`,
 `type`,
 `default`,
 `position`)
VALUES
    ('New', 1, 1, 1), ('Open', 2, 1, 2), ('In Progress', 3, 1, 3), ('On Hold', 4, 1, 4), ('Awaiting User Action', 5, 1, 5), ('Closed', 6, 1, 6)