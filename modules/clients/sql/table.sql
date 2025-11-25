# Author: Eduard Laas
# Copyright © 2005 - 2022 SLAED
# License: GNU GPL 3
# Website: slaed.net

CREATE TABLE `{prefix}_clients_down` (
  `id` int(11) NOT NULL auto_increment,
  `title` varchar(100) NOT NULL,
  `infotext` text NOT NULL,
  `url` varchar(100) NOT NULL,
  `num` varchar(10) NOT NULL,
  `code` varchar(100) NOT NULL,
  `hits` int(11) NOT NULL default '0',
  `status` int(1) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};