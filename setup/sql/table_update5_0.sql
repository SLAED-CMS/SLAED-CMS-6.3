# Author: Eduard Laas
# Copyright © 2005 - 2022 SLAED
# License: GNU GPL 3
# Website: slaed.net

ALTER TABLE `{prefix}_session` CHANGE `module` `module` VARCHAR(100) NOT NULL;
ALTER TABLE `{prefix}_users` CHANGE `user_password` `user_password` VARCHAR(32) NOT NULL;
ALTER TABLE `{prefix}_users_temp` CHANGE `user_password` `user_password` VARCHAR(25) NOT NULL;
ALTER TABLE `{prefix}_admins` CHANGE `pwd` `pwd` VARCHAR(32) NOT NULL;
ALTER TABLE `{prefix}_admins` CHANGE `ip` `ip` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_comment` CHANGE `host_name` `host_name` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_faq` CHANGE `ip_sender` `ip_sender` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_files` CHANGE `ip_sender` `ip_sender` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_forum` CHANGE `ip_send` `ip_send` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_forum` CHANGE `e_ip_send` `e_ip_send` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_help` CHANGE `ip_sender` `ip_sender` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_jokes` CHANGE `ip_sender` `ip_sender` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_links` CHANGE `ip_sender` `ip_sender` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_media` CHANGE `ip_sender` `ip_sender` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_page` CHANGE `ip_sender` `ip_sender` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_rating` CHANGE `host` `host` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_session` CHANGE `host_addr` `host_addr` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_stories` CHANGE `ip_sender` `ip_sender` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_users` CHANGE `user_last_ip` `user_last_ip` VARCHAR(15) NOT NULL;
ALTER TABLE `{prefix}_products` ADD `ihome` INT(1) NOT NULL DEFAULT '0' AFTER `product_assoc`;
ALTER TABLE `{prefix}_products` ADD `acomm` INT(1) NOT NULL DEFAULT '0' AFTER `ihome`;
ALTER TABLE `{prefix}_products` CHANGE `product_active` `product_active` INT(1) NOT NULL DEFAULT '0';
UPDATE `{prefix}_products` SET `ihome` = '1';
UPDATE `{prefix}_products` SET `acomm` = '1';
UPDATE `{prefix}_stories` SET `ihome` = '2' WHERE `ihome` = '0';
UPDATE `{prefix}_stories` SET `ihome` = '0' WHERE `ihome` = '1';
UPDATE `{prefix}_stories` SET `ihome` = '1' WHERE `ihome` = '2';
UPDATE `{prefix}_stories` SET `acomm` = '2' WHERE `acomm` = '0';
UPDATE `{prefix}_stories` SET `acomm` = '0' WHERE `acomm` = '1';
UPDATE `{prefix}_stories` SET `acomm` = '1' WHERE `acomm` = '2';
ALTER TABLE `{prefix}_files` ADD `counter` INT(11) NOT NULL DEFAULT '0' AFTER `ip_sender`;
ALTER TABLE `{prefix}_files` ADD `ihome` INT(1) NOT NULL DEFAULT '0' AFTER `counter`;
ALTER TABLE `{prefix}_files` ADD `acomm` INT(1) NOT NULL DEFAULT '0' AFTER `ihome`;
UPDATE `{prefix}_files` SET `ihome` = '1';
UPDATE `{prefix}_files` SET `acomm` = '1';
ALTER TABLE `{prefix}_faq` ADD `ihome` INT(1) NOT NULL DEFAULT '0' AFTER `counter`;
UPDATE `{prefix}_faq` SET `ihome` = '1';

CREATE TABLE `{prefix}_favorites` (
  `id` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL default '0',
  `fid` int(11) NOT NULL default '0',
  `modul` varchar(50) NOT NULL default '',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `fid` (`fid`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_privat` (
  `id` int(11) NOT NULL auto_increment,
  `uidin` int(11) NOT NULL default '0',
  `uidout` int(11) NOT NULL default '0',
  `title` varchar(100) NOT NULL default '',
  `content` text NOT NULL,
  `date` datetime default NULL,
  `ip_sender` varchar(15) NOT NULL,
  `status` int(1) NOT NULL default '0',
  PRIMARY KEY (`id`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

ALTER TABLE `{prefix}_help` ADD `score` INT(11) NOT NULL DEFAULT '0' AFTER `counter`;
ALTER TABLE `{prefix}_help` ADD `ratings` INT(11) NOT NULL DEFAULT '0' AFTER `score`;
ALTER TABLE `{prefix}_users` ADD `user_fsmail` INT(1) NOT NULL DEFAULT '1' AFTER `user_newsletter`;
ALTER TABLE `{prefix}_users` ADD `user_psmail` INT(1) NOT NULL DEFAULT '1' AFTER `user_fsmail`;
ALTER TABLE `{prefix}_modules` DROP `custom_title`;
ALTER TABLE `{prefix}_links` ADD `counter` INT(11) NOT NULL DEFAULT '0' AFTER `ip_sender`;
ALTER TABLE `{prefix}_links` ADD `ihome` INT(1) NOT NULL DEFAULT '0' AFTER `counter`;
ALTER TABLE `{prefix}_links` ADD `acomm` INT(1) NOT NULL DEFAULT '0' AFTER `ihome`;
UPDATE `{prefix}_links` SET `ihome` = '1';
UPDATE `{prefix}_links` SET `acomm` = '1';
ALTER TABLE `{prefix}_media` ADD `ihome` INT(1) NOT NULL DEFAULT '0' AFTER `date`;
ALTER TABLE `{prefix}_media` ADD `acomm` INT(1) NOT NULL DEFAULT '0' AFTER `ihome`;
UPDATE `{prefix}_media` SET `ihome` = '1';
UPDATE `{prefix}_media` SET `acomm` = '1';
ALTER TABLE `{prefix}_page` ADD `ihome` INT(1) NOT NULL DEFAULT '0' AFTER `counter`;
UPDATE `{prefix}_page` SET `ihome` = '1';
RENAME TABLE `{prefix}_survey` TO `{prefix}_voting`;
RENAME TABLE `{prefix}_stories` TO `{prefix}_news`;
RENAME TABLE `{prefix}_page` TO `{prefix}_pages`;
UPDATE `{prefix}_voting` SET `acomm` = '1';