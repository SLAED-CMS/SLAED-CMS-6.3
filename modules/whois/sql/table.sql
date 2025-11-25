# Author: Eduard Laas
# Copyright © 2005 - 2022 SLAED
# License: GNU GPL 3
# Website: slaed.net

CREATE TABLE `{prefix}_whois` (
  `id` int(11) NOT NULL auto_increment,
  `uid` int(11) NOT NULL default '0',
  `name` varchar(25) NOT NULL default '',
  `ip` varchar(60) default NULL,
  `time` datetime default NULL,
  `domain` varchar(255) NOT NULL default '',
  `host` varchar(255) NOT NULL default '',
  `dc` varchar(255) NOT NULL default '',
  `hometext` text,
  `st_domain` int(1) NOT NULL default '0',
  `st_host` int(1) NOT NULL default '0',
  `st_dc` int(1) NOT NULL default '0',
  `status` int(1) NOT NULL default '0',
  PRIMARY KEY (`id`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};