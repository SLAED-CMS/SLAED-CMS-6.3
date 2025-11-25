# Author: Eduard Laas
# Copyright © 2005 - 2022 SLAED
# License: GNU GPL 3
# Website: slaed.net

ALTER TABLE `{prefix}_auto_links` CHANGE `description` `description` TEXT NOT NULL;

ALTER TABLE `{prefix}_categories` ADD `cstatus` INT(1) NOT NULL DEFAULT '0' AFTER `parentid`;
ALTER TABLE `{prefix}_categories` ADD `ordern` INT(11) NOT NULL DEFAULT '0' AFTER `cstatus`;
ALTER TABLE `{prefix}_categories` ADD `topics` INT(11) NOT NULL DEFAULT '0' AFTER `ordern`;
ALTER TABLE `{prefix}_categories` ADD `posts` INT(11) NOT NULL DEFAULT '0' AFTER `topics`;
ALTER TABLE `{prefix}_categories` ADD `lpost_id` INT(11) NOT NULL DEFAULT '0' AFTER `posts`;
ALTER TABLE `{prefix}_categories` ADD `auth_view` VARCHAR(100) NOT NULL AFTER `lpost_id`;
ALTER TABLE `{prefix}_categories` ADD `auth_read` VARCHAR(100) NOT NULL AFTER `auth_view`;
ALTER TABLE `{prefix}_categories` ADD `auth_post` VARCHAR(100) NOT NULL AFTER `auth_read`;
ALTER TABLE `{prefix}_categories` ADD `auth_reply` VARCHAR(100) NOT NULL AFTER `auth_post`;
ALTER TABLE `{prefix}_categories` ADD `auth_edit` VARCHAR(100) NOT NULL AFTER `auth_reply`;
ALTER TABLE `{prefix}_categories` ADD `auth_delete` VARCHAR(100) NOT NULL AFTER `auth_edit`;
ALTER TABLE `{prefix}_categories` ADD `auth_mod` VARCHAR(100) NOT NULL AFTER `auth_delete`;
UPDATE `{prefix}_categories` SET `cstatus` = '1', `auth_view` = '0|0', `auth_read` = '0|0', `auth_post` = '0|0', `auth_reply` = '0|0', `auth_edit` = '3|0', `auth_delete` = '3|0', `auth_mod` = '3|0';

ALTER TABLE `{prefix}_users` ADD `user_rank` VARCHAR(25) NOT NULL AFTER `user_name`;
ALTER TABLE `{prefix}_users` ADD `user_acess` INT(1) NOT NULL DEFAULT '0' AFTER `user_warnings`;
ALTER TABLE `{prefix}_groups` ADD `color` VARCHAR(7) NOT NULL DEFAULT '0' AFTER `rank`;

CREATE TABLE `{prefix}_forum` (
  `id` int(11) NOT NULL auto_increment,
  `pid` int(11) NOT NULL default '0',
  `catid` int(11) NOT NULL default '0',
  `uid` int(11) NOT NULL default '0',
  `name` varchar(25) NOT NULL default '',
  `title` varbinary(100) default NULL,
  `time` datetime default NULL,
  `hometext` text,
  `field` text NOT NULL,
  `comments` int(11) default '0',
  `counter` int(11) NOT NULL default '0',
  `score` int(11) NOT NULL default '0',
  `ratings` int(11) NOT NULL default '0',
  `ip_send` varchar(60) default NULL,
  `l_uid` int(11) NOT NULL default '0',
  `l_name` varchar(25) NOT NULL default '',
  `l_id` int(11) NOT NULL default '0',
  `l_time` datetime default NULL,
  `e_uid` int(11) NOT NULL default '0',
  `e_ip_send` varchar(60) default NULL,
  `e_time` datetime default NULL,
  `status` int(1) NOT NULL default '0',
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

ALTER TABLE `{prefix}_comment` ADD `status` INT(1) NOT NULL DEFAULT '0' AFTER `comment`;
UPDATE `{prefix}_comment` SET `status` = '1';