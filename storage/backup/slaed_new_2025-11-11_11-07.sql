# DB: slaed_new
# Tables: 34
# Size: 0.55 MB
# Lines: 270
# Date: 2025.11.11 11:07:32

DROP TABLE IF EXISTS `benchmark_table`;
CREATE TABLE `benchmark_table` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=744 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_general_ci */;

INSERT INTO `benchmark_table` VALUES
(541, 'Wrapper_Mixed_187'),
(542, 'Wrapper_Mixed_188'),
(543, 'Wrapper_Mixed_189'),
(544, 'Wrapper_Mixed_190'),
(545, 'Wrapper_Mixed_192'),
(546, 'Wrapper_Mixed_197'),
(547, 'Wrapper_Mixed_198'),
(548, 'Wrapper_Mixed_200'),
(549, 'Wrapper_Mixed_209'),
(550, 'Wrapper_Mixed_211'),
(551, 'Wrapper_Mixed_214'),
(552, 'Wrapper_Mixed_217'),
(553, 'Wrapper_Mixed_220'),
(554, 'Wrapper_Mixed_221'),
(555, 'Wrapper_Mixed_225'),
(556, 'Wrapper_Mixed_226'),
(557, 'Wrapper_Mixed_233'),
(558, 'Wrapper_Mixed_240'),
(559, 'Wrapper_Mixed_242'),
(560, 'Wrapper_Mixed_245'),
(561, 'Wrapper_Mixed_246'),
(562, 'Wrapper_Mixed_251'),
(563, 'Wrapper_Mixed_254'),
(564, 'Wrapper_Mixed_255'),
(565, 'Wrapper_Mixed_256'),
(566, 'Wrapper_Mixed_259'),
(567, 'Wrapper_Mixed_260'),
(568, 'Wrapper_Mixed_269'),
(569, 'Wrapper_Mixed_278'),
(570, 'Wrapper_Mixed_279'),
(571, 'Wrapper_Mixed_284'),
(572, 'Wrapper_Mixed_287'),
(573, 'Wrapper_Mixed_288'),
(574, 'Wrapper_Mixed_291'),
(575, 'Wrapper_Mixed_292'),
(576, 'Wrapper_Mixed_296'),
(577, 'Wrapper_Mixed_309'),
(578, 'Wrapper_Mixed_310'),
(579, 'Wrapper_Mixed_317'),
(580, 'Wrapper_Mixed_322'),
(581, 'Wrapper_Mixed_325'),
(582, 'Wrapper_Mixed_331'),
(583, 'Wrapper_Mixed_335'),
(584, 'Wrapper_Mixed_336'),
(585, 'Wrapper_Mixed_345'),
(586, 'Wrapper_Mixed_351'),
(587, 'Wrapper_Mixed_352'),
(588, 'Wrapper_Mixed_357'),
(589, 'Wrapper_Mixed_369'),
(590, 'Wrapper_Mixed_370'),
(591, 'Wrapper_Mixed_372'),
(592, 'Wrapper_Mixed_377'),
(593, 'Wrapper_Mixed_379'),
(594, 'Wrapper_Mixed_383'),
(595, 'Wrapper_Mixed_384'),
(596, 'Wrapper_Mixed_391'),
(597, 'Wrapper_Mixed_395'),
(598, 'Wrapper_Mixed_397'),
(599, 'Wrapper_Mixed_399'),
(600, 'Wrapper_Mixed_403'),
(601, 'Wrapper_Mixed_404'),
(602, 'Wrapper_Mixed_407'),
(603, 'Wrapper_Mixed_409'),
(604, 'Wrapper_Mixed_415'),
(605, 'Wrapper_Mixed_422'),
(606, 'Wrapper_Mixed_425'),
(607, 'Wrapper_Mixed_430'),
(608, 'Wrapper_Mixed_437'),
(609, 'Wrapper_Mixed_448'),
(610, 'Wrapper_Mixed_462'),
(611, 'Wrapper_Mixed_471'),
(612, 'Wrapper_Mixed_486'),
(613, 'Wrapper_Mixed_487'),
(614, 'Wrapper_Mixed_491'),
(615, 'Wrapper_Mixed_493'),
(616, 'Wrapper_Mixed_494'),
(617, 'Wrapper_Mixed_497'),
(618, 'PDO_Mixed_7'),
(619, 'PDO_Mixed_11'),
(620, 'PDO_Mixed_15'),
(621, 'PDO_Mixed_17'),
(622, 'PDO_Mixed_18'),
(623, 'PDO_Mixed_30'),
(624, 'PDO_Mixed_34'),
(625, 'PDO_Mixed_35'),
(626, 'PDO_Mixed_40'),
(627, 'PDO_Mixed_45'),
(628, 'PDO_Mixed_47'),
(629, 'PDO_Mixed_58'),
(630, 'PDO_Mixed_59'),
(631, 'PDO_Mixed_62'),
(632, 'PDO_Mixed_64'),
(633, 'PDO_Mixed_69'),
(634, 'PDO_Mixed_70'),
(635, 'PDO_Mixed_78'),
(636, 'PDO_Mixed_80'),
(637, 'PDO_Mixed_84'),
(638, 'PDO_Mixed_85'),
(639, 'PDO_Mixed_91'),
(640, 'PDO_Mixed_98'),
(641, 'PDO_Mixed_106'),
(642, 'PDO_Mixed_107'),
(643, 'PDO_Mixed_112'),
(644, 'PDO_Mixed_117'),
(645, 'PDO_Mixed_123'),
(646, 'PDO_Mixed_124'),
(647, 'PDO_Mixed_129'),
(648, 'PDO_Mixed_131'),
(649, 'PDO_Mixed_143'),
(650, 'PDO_Mixed_148'),
(651, 'PDO_Mixed_149'),
(652, 'PDO_Mixed_150'),
(653, 'PDO_Mixed_153'),
(654, 'PDO_Mixed_157'),
(655, 'PDO_Mixed_159'),
(656, 'PDO_Mixed_162'),
(657, 'PDO_Mixed_170'),
(658, 'PDO_Mixed_184'),
(659, 'PDO_Mixed_189'),
(660, 'PDO_Mixed_190'),
(661, 'PDO_Mixed_199'),
(662, 'PDO_Mixed_203'),
(663, 'PDO_Mixed_208'),
(664, 'PDO_Mixed_209'),
(665, 'PDO_Mixed_210'),
(666, 'PDO_Mixed_211'),
(667, 'PDO_Mixed_212'),
(668, 'PDO_Mixed_219'),
(669, 'PDO_Mixed_220'),
(670, 'PDO_Mixed_223'),
(671, 'PDO_Mixed_225'),
(672, 'PDO_Mixed_226'),
(673, 'PDO_Mixed_230'),
(674, 'PDO_Mixed_233'),
(675, 'PDO_Mixed_234'),
(676, 'PDO_Mixed_236'),
(677, 'PDO_Mixed_243'),
(678, 'PDO_Mixed_248'),
(679, 'PDO_Mixed_249'),
(680, 'PDO_Mixed_252'),
(681, 'PDO_Mixed_254'),
(682, 'PDO_Mixed_256'),
(683, 'PDO_Mixed_258'),
(684, 'PDO_Mixed_270'),
(685, 'PDO_Mixed_276'),
(686, 'PDO_Mixed_279'),
(687, 'PDO_Mixed_287'),
(688, 'PDO_Mixed_293'),
(689, 'PDO_Mixed_301'),
(690, 'PDO_Mixed_304'),
(691, 'PDO_Mixed_309'),
(692, 'PDO_Mixed_312'),
(693, 'PDO_Mixed_313'),
(694, 'PDO_Mixed_315'),
(695, 'PDO_Mixed_325'),
(696, 'PDO_Mixed_338'),
(697, 'PDO_Mixed_340'),
(698, 'PDO_Mixed_343'),
(699, 'PDO_Mixed_345'),
(700, 'PDO_Mixed_355'),
(701, 'PDO_Mixed_356'),
(702, 'PDO_Mixed_358'),
(703, 'PDO_Mixed_360'),
(704, 'PDO_Mixed_361'),
(705, 'PDO_Mixed_363'),
(706, 'PDO_Mixed_367'),
(707, 'PDO_Mixed_376'),
(708, 'PDO_Mixed_378'),
(709, 'PDO_Mixed_384'),
(710, 'PDO_Mixed_392'),
(711, 'PDO_Mixed_396'),
(712, 'PDO_Mixed_397'),
(713, 'PDO_Mixed_400'),
(714, 'PDO_Mixed_401'),
(715, 'PDO_Mixed_402'),
(716, 'PDO_Mixed_410'),
(717, 'PDO_Mixed_411'),
(718, 'PDO_Mixed_416'),
(719, 'PDO_Mixed_417'),
(720, 'PDO_Mixed_424'),
(721, 'PDO_Mixed_426'),
(722, 'PDO_Mixed_432'),
(723, 'PDO_Mixed_434'),
(724, 'PDO_Mixed_435'),
(725, 'PDO_Mixed_443'),
(726, 'PDO_Mixed_445'),
(727, 'PDO_Mixed_446'),
(728, 'PDO_Mixed_450'),
(729, 'PDO_Mixed_452'),
(730, 'PDO_Mixed_453'),
(731, 'PDO_Mixed_455'),
(732, 'PDO_Mixed_458'),
(733, 'PDO_Mixed_462'),
(734, 'PDO_Mixed_463'),
(735, 'PDO_Mixed_472'),
(736, 'PDO_Mixed_480'),
(737, 'PDO_Mixed_482'),
(738, 'PDO_Mixed_484'),
(739, 'PDO_Mixed_485'),
(740, 'PDO_Mixed_489'),
(741, 'PDO_Mixed_491'),
(742, 'PDO_Mixed_493'),
(743, 'PDO_Mixed_496');

DROP TABLE IF EXISTS `sport_admins`;
CREATE TABLE `sport_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(25) NOT NULL,
  `title` varchar(50) DEFAULT NULL,
  `url` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `pwd` varchar(40) DEFAULT NULL,
  `super` tinyint(1) DEFAULT NULL,
  `editor` tinyint(1) DEFAULT NULL,
  `smail` tinyint(1) DEFAULT NULL,
  `modules` varchar(255) NOT NULL,
  `lang` varchar(30) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `regdate` datetime NOT NULL,
  `lastvisit` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_auto_links`;
CREATE TABLE `sport_auto_links` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sitename` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `link` varchar(100) NOT NULL,
  `mail` varchar(100) NOT NULL,
  `hits` int(11) NOT NULL DEFAULT 0,
  `outs` int(11) NOT NULL DEFAULT 0,
  `added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_blocks`;
CREATE TABLE `sport_blocks` (
  `bid` int(11) NOT NULL AUTO_INCREMENT,
  `bkey` varchar(15) NOT NULL,
  `title` varchar(60) NOT NULL,
  `content` text NOT NULL,
  `url` varchar(200) NOT NULL,
  `bposition` char(1) NOT NULL,
  `weight` int(10) NOT NULL DEFAULT 1,
  `active` int(1) NOT NULL DEFAULT 1,
  `refresh` int(10) NOT NULL DEFAULT 0,
  `time` varchar(14) NOT NULL DEFAULT '0',
  `blanguage` varchar(30) NOT NULL,
  `blockfile` varchar(255) NOT NULL,
  `view` int(1) NOT NULL DEFAULT 0,
  `expire` varchar(14) NOT NULL DEFAULT '0',
  `action` char(1) NOT NULL,
  `which` text NOT NULL,
  PRIMARY KEY (`bid`),
  KEY `title` (`title`)
) ENGINE=InnoDB AUTO_INCREMENT=9 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

INSERT INTO `sport_blocks` VALUES
(1, '', 'Навигация', '', '', 'r', 2, 1, 0, '', '', 'block-modules.php', 0, '0', 'd', 'all'),
(2, 'admin', 'Администрация', '<a href=\"javascript:OpenWindow(\'plugins/sxd/index.php\', \'DB Backup - Sypex Dumper\', \'600\', \'500\')\" title=\"DB Backup - Sypex Dumper\">DB Backup - Sypex Dumper</a>', '', 'r', 3, 1, 0, '0', '', '', 2, '0', 'd', 'all'),
(3, '', 'Выбор языка', '', '', 'r', 1, 1, 0, '', '', 'block-languages.php', 0, '0', 'd', 'all'),
(4, 'userbox', 'Блок пользователя', '', '', 'r', 4, 1, 0, '', '', '', 1, '0', 'd', 'all'),
(5, '', 'Информация пользователя', '', '', 'r', 5, 1, 0, '', '', 'block-user_info.php', 0, '0', 'd', 'all'),
(6, '', 'Счетчик посещений', '', '', 'r', 6, 1, 0, '', '', 'block-stat.php', 0, '0', 'd', 'all'),
(7, '', 'Реклама', '', '', 'd', 1, 1, 0, '', '', 'block-banner_random.php', 0, '0', 'd', 'all'),
(8, '', 'Форум внизу', '', '', 'r', 7, 1, 0, '', '', 'block-forum.php', 0, '0', 'd', 'infly,');

DROP TABLE IF EXISTS `sport_categories`;
CREATE TABLE `sport_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modul` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `img` varchar(100) NOT NULL,
  `language` varchar(30) NOT NULL,
  `parentid` int(11) NOT NULL DEFAULT 0,
  `cstatus` int(1) NOT NULL DEFAULT 0,
  `ordern` int(11) NOT NULL DEFAULT 0,
  `topics` int(11) NOT NULL DEFAULT 0,
  `posts` int(11) NOT NULL DEFAULT 0,
  `lpost_id` int(11) NOT NULL DEFAULT 0,
  `auth_view` varchar(100) NOT NULL,
  `auth_read` varchar(100) NOT NULL,
  `auth_post` varchar(100) NOT NULL,
  `auth_reply` varchar(100) NOT NULL,
  `auth_edit` varchar(100) NOT NULL,
  `auth_delete` varchar(100) NOT NULL,
  `auth_mod` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `modul` (`modul`),
  KEY `parentid` (`parentid`)
) ENGINE=InnoDB AUTO_INCREMENT=6 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

INSERT INTO `sport_categories` VALUES
(1, 'news', 'Internet', 'Internet news', 'network.png', '', 0, 1, 1, 0, 0, 0, '0|0', '0|0', '1|0', '1|0', '3|0', '3|0', '3|0'),
(2, 'news', 'Soft', 'Software', 'cup.png', '', 0, 1, 2, 0, 0, 0, '0|0', '0|0', '1|0', '1|0', '3|0', '3|0', '3|0'),
(3, 'forum', 'Категория форума', 'Описание категории', '', '', 0, 1, 1, 3, 0, 0, '0|0', '0|0', '1|0', '1|0', '1|0', '3|0', '3|0'),
(4, 'forum', 'Демонстрация форума', 'Описание демонстрации', '', '', 3, 1, 2, 3, 0, 3, '0|0', '0|0', '1|0', '1|0', '1|0', '3|0', '3|0'),
(5, 'forum', 'Устаревшие сообщения', 'Форум, используемый в качестве корзины', '', '', 3, 1, 3, 0, 0, 0, '0|0', '0|0', '1|0', '1|0', '1|0', '3|0', '3|0');

DROP TABLE IF EXISTS `sport_clients`;
CREATE TABLE `sport_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_product` int(11) NOT NULL DEFAULT 0,
  `id_partner` int(11) NOT NULL DEFAULT 0,
  `partner_proz` int(3) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL,
  `adres` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `website` varchar(255) NOT NULL,
  `regdate` int(10) NOT NULL DEFAULT 0,
  `enddate` int(10) NOT NULL DEFAULT 0,
  `info` varchar(255) NOT NULL,
  `active` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_comment`;
CREATE TABLE `sport_comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `modul` varchar(60) NOT NULL,
  `date` datetime DEFAULT NULL,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `host_name` varchar(15) NOT NULL,
  `comment` text NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cid` (`cid`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_content`;
CREATE TABLE `sport_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) DEFAULT NULL,
  `text` mediumtext NOT NULL,
  `field` text NOT NULL,
  `url` varchar(200) NOT NULL,
  `time` datetime DEFAULT NULL,
  `refresh` int(10) NOT NULL DEFAULT 0,
  `counter` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `counter` (`counter`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_faq`;
CREATE TABLE `sport_faq` (
  `fid` int(11) NOT NULL AUTO_INCREMENT,
  `catid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `title` varchar(100) NOT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` text DEFAULT NULL,
  `comments` int(11) NOT NULL DEFAULT 0,
  `counter` int(11) NOT NULL DEFAULT 0,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `ratings` int(11) NOT NULL DEFAULT 0,
  `ip_sender` varchar(15) NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`fid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_favorites`;
CREATE TABLE `sport_favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT 0,
  `fid` int(11) NOT NULL DEFAULT 0,
  `modul` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `fid` (`fid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_files`;
CREATE TABLE `sport_files` (
  `lid` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `bodytext` text NOT NULL,
  `url` varchar(100) NOT NULL,
  `date` datetime DEFAULT NULL,
  `filesize` int(11) NOT NULL DEFAULT 0,
  `version` varchar(10) NOT NULL,
  `email` varchar(100) NOT NULL,
  `homepage` varchar(200) NOT NULL,
  `ip_sender` varchar(15) NOT NULL,
  `counter` int(11) NOT NULL DEFAULT 0,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `votes` int(11) NOT NULL DEFAULT 0,
  `totalvotes` int(11) NOT NULL DEFAULT 0,
  `totalcomments` int(11) NOT NULL DEFAULT 0,
  `hits` int(11) NOT NULL DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`lid`),
  KEY `cid` (`cid`),
  KEY `title` (`title`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_forum`;
CREATE TABLE `sport_forum` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT 0,
  `catid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `title` varchar(100) NOT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` text DEFAULT NULL,
  `field` text NOT NULL,
  `comments` int(11) DEFAULT 0,
  `counter` int(11) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `ratings` int(11) NOT NULL DEFAULT 0,
  `ip_send` varchar(15) NOT NULL,
  `l_uid` int(11) NOT NULL DEFAULT 0,
  `l_name` varchar(25) NOT NULL,
  `l_id` int(11) NOT NULL DEFAULT 0,
  `l_time` datetime DEFAULT NULL,
  `e_uid` int(11) NOT NULL DEFAULT 0,
  `e_ip_send` varchar(15) NOT NULL,
  `e_time` datetime DEFAULT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`)
) ENGINE=InnoDB AUTO_INCREMENT=4 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

INSERT INTO `sport_forum` VALUES
(1, 0, 4, 0, 'SLAED', 'Защита и безопасность', '2021-02-10 19:58:00', 'Установка дополнительного пароля и логина значительным образом повышает уровень безопасности системы и практически исключает несанкционированный доступ к панели управления. Обратите внимание, «HTTP-аутентификация» возможна только при запуске РНР как Apache-модуля или в режиме FastCGI с установленным модулем Mod Rewrite. Заметьте, эта функция не работает на Microsoft IIS-сервере и с CGI-версией PHP. Во избежание проблем с доступом, рекомендуем проконсультироваться у Вашего хостинг провайдера.', '', 0, 1, 0, 0, '127.0.0.1', 0, 'SLAED', 0, '2021-02-10 19:58:00', 0, '', NULL, 3),
(2, 0, 4, 0, 'SLAED', 'Языковые версии статей', '2021-02-10 20:01:00', 'Чтобы создать статьи для какой-то языковой версии сайта (к примеру, для английской) необходимо создать категорию в разделе «Категории» и указать, в какой языковой версии она должна отображаться (категорию создавать соответственно на английском языке). Далее при создании англоязычной статьи закрепите её за предварительно созданной языковой категорией. Пользователь при переходе к английской версии будет видеть соответственно статьи, закрепленные за английской категорией.', '', 0, 2, 0, 0, '127.0.0.1', 0, 'SLAED', 0, '2021-02-10 20:01:00', 0, '', NULL, 3),
(3, 0, 4, 0, 'SLAED', 'Добавление под-категории', '2021-02-10 20:04:00', 'Для добавления новой под-категории выбранного модуля перейдите во вкладку «Добавить под-категорию» и заполните информацию о новой под-категории.<br>\r\nФорма и процесс добавления под-категории аналогичны форме и процессу добавления категории за исключением выбора категории, для которой добавляется под-категория. Выбор категории происходит во вкладке «Категория».<br>\r\nПод-категория может быть добавлена как для категории, так и для другой под-категории, что позволяет создавать иерархию с практически неограниченным уровнем вложенности.', '', 0, 5, 0, 0, '127.0.0.1', 0, 'SLAED', 0, '2021-02-10 20:04:00', 0, '', NULL, 3);

DROP TABLE IF EXISTS `sport_groups`;
CREATE TABLE `sport_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `points` int(10) NOT NULL DEFAULT 0,
  `extra` int(1) NOT NULL DEFAULT 0,
  `rank` varchar(255) NOT NULL,
  `color` varchar(7) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_help`;
CREATE TABLE `sport_help` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT 0,
  `catid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `aid` int(11) NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` text DEFAULT NULL,
  `field` text NOT NULL,
  `comments` int(11) DEFAULT 0,
  `counter` int(11) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `ratings` int(11) NOT NULL DEFAULT 0,
  `ip_sender` varchar(15) NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sid`),
  KEY `pid` (`pid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_jokes`;
CREATE TABLE `sport_jokes` (
  `jokeid` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `date` datetime DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `cat` int(11) NOT NULL DEFAULT 0,
  `joke` text NOT NULL,
  `rating` varchar(100) NOT NULL DEFAULT '0',
  `ratingtot` varchar(100) NOT NULL DEFAULT '0',
  `ip_sender` varchar(15) NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`jokeid`),
  KEY `cat` (`cat`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_links`;
CREATE TABLE `sport_links` (
  `lid` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `bodytext` text NOT NULL,
  `url` varchar(100) NOT NULL,
  `date` datetime DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `ip_sender` varchar(15) NOT NULL,
  `counter` int(11) NOT NULL DEFAULT 0,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `votes` int(11) NOT NULL DEFAULT 0,
  `totalvotes` int(11) NOT NULL DEFAULT 0,
  `totalcomments` int(11) NOT NULL DEFAULT 0,
  `hits` int(11) NOT NULL DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`lid`),
  KEY `cid` (`cid`),
  KEY `title` (`title`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_media`;
CREATE TABLE `sport_media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `title` varchar(100) NOT NULL,
  `subtitle` varchar(100) NOT NULL,
  `year` int(11) NOT NULL DEFAULT 0,
  `director` varchar(100) NOT NULL,
  `roles` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `createdby` varchar(100) NOT NULL,
  `duration` varchar(100) NOT NULL,
  `lang` varchar(100) NOT NULL,
  `note` text NOT NULL,
  `format` varchar(100) NOT NULL,
  `quality` varchar(100) NOT NULL,
  `size` varchar(100) NOT NULL,
  `released` varchar(100) NOT NULL,
  `links` text NOT NULL,
  `date` datetime DEFAULT NULL,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `votes` int(11) NOT NULL DEFAULT 0,
  `totalvotes` int(11) NOT NULL DEFAULT 0,
  `totalcom` int(11) NOT NULL DEFAULT 0,
  `hits` int(11) NOT NULL DEFAULT 0,
  `ip_sender` varchar(15) NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cid` (`cid`),
  KEY `title` (`title`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_message`;
CREATE TABLE `sport_message` (
  `mid` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `expire` int(7) NOT NULL DEFAULT 0,
  `active` int(1) NOT NULL DEFAULT 1,
  `view` int(1) NOT NULL DEFAULT 1,
  `mlanguage` varchar(30) NOT NULL,
  PRIMARY KEY (`mid`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_modules`;
CREATE TABLE `sport_modules` (
  `mid` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `active` int(1) NOT NULL DEFAULT 0,
  `view` int(1) NOT NULL DEFAULT 0,
  `inmenu` tinyint(1) NOT NULL DEFAULT 1,
  `mod_group` int(10) DEFAULT 0,
  `blocks` int(1) NOT NULL DEFAULT 0,
  `blocks_c` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`mid`),
  KEY `title` (`title`)
) ENGINE=InnoDB AUTO_INCREMENT=26 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

INSERT INTO `sport_modules` VALUES
(1, 'account', 1, 0, 1, 0, 0, 0),
(2, 'auto_links', 0, 0, 1, 0, 0, 0),
(3, 'clients', 0, 0, 1, 0, 0, 0),
(4, 'contact', 0, 0, 1, 0, 0, 0),
(5, 'content', 0, 0, 1, 0, 0, 0),
(6, 'faq', 0, 0, 1, 0, 0, 0),
(7, 'files', 0, 0, 1, 0, 0, 0),
(8, 'forum', 0, 0, 1, 0, 0, 0),
(9, 'help', 0, 0, 1, 0, 0, 0),
(10, 'image', 0, 0, 1, 0, 0, 0),
(11, 'jokes', 0, 0, 1, 0, 0, 0),
(12, 'links', 0, 0, 1, 0, 0, 0),
(13, 'main', 0, 0, 1, 0, 0, 0),
(14, 'media', 0, 0, 1, 0, 0, 0),
(15, 'news', 1, 0, 1, 0, 0, 0),
(16, 'order', 0, 0, 1, 0, 0, 0),
(17, 'pages', 0, 0, 1, 0, 0, 0),
(18, 'recommend', 0, 0, 1, 0, 0, 0),
(19, 'rss_info', 0, 0, 1, 0, 0, 0),
(20, 'search', 0, 0, 1, 0, 0, 0),
(21, 'shop', 0, 0, 1, 0, 0, 0),
(22, 'sitemap', 0, 0, 1, 0, 0, 0),
(23, 'users', 0, 0, 1, 0, 0, 0),
(24, 'voting', 0, 0, 1, 0, 0, 0),
(25, 'whois', 0, 0, 1, 0, 0, 0);

DROP TABLE IF EXISTS `sport_news`;
CREATE TABLE `sport_news` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `catid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `title` varchar(100) NOT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` text DEFAULT NULL,
  `bodytext` text NOT NULL,
  `field` text NOT NULL,
  `vote` int(11) NOT NULL DEFAULT 0,
  `comments` int(11) DEFAULT 0,
  `counter` int(11) NOT NULL DEFAULT 0,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `ratings` int(11) NOT NULL DEFAULT 0,
  `associated` text NOT NULL,
  `ip_sender` varchar(15) NOT NULL,
  `fix` int(1) NOT NULL DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`)
) ENGINE=InnoDB AUTO_INCREMENT=4 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

INSERT INTO `sport_news` VALUES
(1, 2, 0, 'SLAED', 'SLAED CMS 6.1 Pro – первая Open Source версия SLAED CMS', '2021-02-10 21:00:00', '[img=left alt=Современные тенденции в дизайне и технологиях]uploads/news/slaed_logo_design.png[/img] [justify][b][i]О завершении очередного этапа развития проекта: SLAED CMS адаптирована к современным тенденциям в дизайне и технологиях[/i][/b]<br><br>Компания SLAED рада представить версию 6.1 нашего флагманского продукта SLAED CMS Pro. Главное отличие новой версии от всех предыдущих заключается не в функциях, а в лицензии – распространяться версия 6.1 будет абсолютно бесплатно на базе лицензии GNU GPLv3. Более подробно о переходе на Open Source мы рассказали совсем недавно и теперь перешли от слов к действиям.<br><br>Постоянная аудитория и партнёры проекта уже заметили, что сайт проекта обновился, обновился кардинально. Новый сайт – это публичное отражение возможностей новой версии SLAED CMS 6.1.<br><br>Да! Проекту SLAED CMS вот-вот исполнится 17 лет - 30.04.2022 г. мы будем праздновать юбилей. Это серьёзный срок для CMS-системы, как и для любого ИТ-проекта. За 12 лет наш проект кардинально менялся, улучшался и постоянно внедрялся: именно в этот момент тысячи сайтов работают на базе SLAED CMS.[/justify]', '[justify]Отпуская в свободное плавание SLAED CMS, хочется вспомнить несколько интересных фактов о системе, накопившихся за 12 лет её существования.<br><br>[b]«Живучесть» при нагрузках[/b]<br><br>Никого не удивить термином «хабраэффект» (Слэшдот-эффект) – его ждут и боятся одновременно. Многие CMS к нему не готовы на уровне «коробка+стандартный хостинг», и владельцу сайта придётся хорошо попотеть, чтобы пережить хабраэффект у сайта, CMS которого может «положить» хостинг во время пиковых нагрузок. Многие наблюдения за сайтами, разработанными на базе SLAED CMS, говорят о том, что у CMS одни из самых низких требований к серверным ресурсам (хостингу).<br><br>Вот лишь один пример таких наблюдений:<br><br>На одном из сайтов для взрослых, работающем на базе SLAED CMS, была зафиксирована посещаемость порядка 38 000 уникальных посетителей единовременно, причём такое количество посетителей было не мгновенным явлением, а постоянным в течение суток. Сайт при такой посещаемости работал без фиксации каких-либо задержек, не смотря на то, что размещался на стандартной хостинг площадке и не имел кастомизаций на уровне CMS (базовая поставка).<br><br>За всю историю проекта не поступало жалоб о том, что SLAED CMS создаёт высокую нагрузку на сервер.<br><br>[b]7 лет без единой уязвимости[/b]<br><br>Последний раз уязвимость, причём пассивная, в системе была выявлена 27.02.2010 года (7 лет назад) в версии SLAED CMS 4.0 Pro. А начиная с версии SLAED CMS 4.1 Pro в системе уязвимостей больше не находили, но старались.<br><br>Некоторые хакеры охотно используют систему в виду стабильной работы и низкой нагрузки на сервер, а  в 2009 году появилась панель управления СПАМ/DDos ботами на базе ядра SLAED. Панель весьма популярна в определённых круга и используется до сих пор.<br><br>[b]Гибкая мультиязычность[/b]<br><br>Кто всерьёз занимается веб-разработкой, тот знает, что сделать по-настоящему мультиязычный сайт – это не самое простое занятие. Языковые версии могут иметь разную структуру (причём она может быть разной для всех версий), постоянно «вылезают» непереведённые слова в интерфейсе, могут быть абсолютно разные дизайн-блоки, да и множество других особенностей. Практически, все нюансы создания языковых версий учтены в SLAED CMS и за время существования системы на ней были созданы сайты на языках: русский, немецкий, английский, еврейский, чешский, арабский, индийский, болгарский  и множество других.<br><br>[b]Дополнения и популярность[/b]<br><br>Пик популярности SLAED CMS пришёлся на 2008 год, через 2 года после первого релиза Pro-версии. Тогда, согласно независимым рейтингам, система входила в пятёрку самых популярных CMS в российском сегменте интернета.<br><br>За период существования системы сторонними разработчиками создано множество бесплатных и платных дополнений: 695 бесплатных  дополнения доступны на нашем сайте и примерно столько же платных и бесплатных разбросано на просторах интернета.<br><br>За 12 лет команда проекта выпустила 37 полноценных версий системы, включая:<br><br>[u]Бесплатные[/u]<br>7 версий SLAED CMS<br>7 версий SLAED CMS Lite<br>4 версии Open SLAED<br><br>[u]Платные[/u]<br>19 версий SLAED CMS Pro<br><br>Было ещё множество промежуточных релизов с мелкими правками и устранением уязвимостей, которые остались вне версионного подсчета.<br><br>[b]Встречаем Open Source[/b]<br><br>Версия SLAED CMS Pro 6.1. – это отправная точка нашего Open Source решения, которую уже сейчас можно скачать для бесплатного использования в рамках лицензии GNU GPLv3. Команда компании SLAED продолжает работу над планомерным развитием CMS и приглашает отраслевых специалистов (дизайнеры, программисты, верстальщики) принять посильное участие в развитии SLAED CMS.[/justify]', '', 1, 0, 33, 1, 1, 5, 1, '2', '127.0.0.1', 0, 1),
(2, 1, 0, 'SLAED', 'SLAED CMS переходит к Open Source модели на базе GNU GPL 3', '2021-02-10 17:00:00', '[img=left alt=SLAED CMS переходит к Open Source модели на базе лицензии GNU GPL 3]uploads/news/news-slaed-open.png[/img] [justify]2017 год компания SLAED решила начать с отказа от проприетарной модели распространения SLAED CMS в пользу Open Source. Новая версия SLAED CMS переходит в общественную собственность и будет абсолютно бесплатно распространяться на базе лицензии GNU GPLv3.<br><br>«Столь кардинальный шаг в нашей лицензионной политике – это шаг вперёд, который позволит существенно расширить границы распространения SLAED CMS, количество новых модулей и изменений, профессионально вносимых в систему. Мне, как автору проекта, важно, чтобы система была максимально доступной и как можно больше современных сайтов делалось на базе SLAED CMS, поэтому я готов сделать этот большой шаг в сторону Open Source!» - прокомментировал изменения в лицензионной политике автор и идеолог проекта Eduard Laas.[/justify]', '[justify]Отметим, что лицензия GNU GPLv3 предоставляет пользователю права копировать, модифицировать и распространять (в том числе на коммерческой основе) SLAED CMS, с гарантированием, что и пользователи всех производных программ получат вышеперечисленные права.<br><br>Грамотный переход к Open Source – это не просто отмена прайса, присвоение лицензии и полное открытие исходных кодов, - это, прежде всего, формирование активного сообщества, которое будет вносить изменения в систему, а также регулировать процесс принятия вносимых правок. В настоящий момент компания SLAED прорабатывает организационные и правовые шаги на пути к полноценному переходу к модели Open Source. В ближайшее время планируется:<br><br>1. Сформировать  Сommunity (сообщество) и определить основные принципы его работы.<br><br>2. Выбрать среду для технического и организационного взаимодействия членов сообщества.<br><br>3. Внести изменения в информационное содержимое сайта slaed.net, который продолжит быть официальным сайтом SLAED CMS.<br><br>Компания SLAED уже сейчас приглашает всех неравнодушных пользователей и разработчиков высказать свои предложения и соображения по поводу формирования сообщества SLAED CMS. Комментарии к новости отслеживаются – будем рады любым конструктивным предложениям и мнениям.<br><br>Отвечая на вопрос о том, что подтолкнуло компанию SLAED к принятию решения о переводе SLAED CMS на Open Source, отметим,  что компании и её основателю важно, чтобы проект был «живым» и развивающимся, а в современных технологических и политических условиях этого можно достигнуть только посредством перехода к Open Source.<br><br>SLAED CMS и раньше имела бесплатную полностью открытую ветку – Open SLAED, которая не была в полной мере Open Source продуктом, но многие ключевые моменты совпадали.  Мы рассчитываем, что Open Source позволит привлечь к развитию проекта относительно широкую команду разработчиков (Community), которая будет включать в себя как специалистов-энтузиастов, так и веб-студии, которые будут готовы делать свои проекты на базе SLAED CMS и делиться наработками с сообществом.<br><br>Лицензия и политика компании не запрещают оказывать коммерческие услуги, используя SLAED CMS, в том числе разрабатывать платно (на заказ) новые модули и визуальные представления для системы или заниматься платным сопровождением сайтов, разработанных на базе SLAED CMS.<br><br>В самое ближайшее время тема перевода SLAED CMS на модель Open Source будет продолжена с учётом предложений и мнений, высказанных в комментариях.[/justify]', '', 0, 0, 12, 1, 1, 5, 1, '1', '127.0.0.1', 0, 1),
(3, 1, 0, 'SLAED', 'В ногу со временем или Web 2 как пройденный этап', '2021-02-10 15:00:00', '[justify]Не для кого не секрет что в последнее время компьютерные технологии начали развиваться с большой скоростью, ещё больше это развитие отобразилось на сферу Интернета и технологиях применяемых в ней. Появились новые, модные на сегодняшний день тенденции, такие как Web 2 и AJAX. Буквально 3-5 лет назад, основная масса сайтов общего направления сети Интернет состояла из HTML страниц, в лучшем случае не сложных скриптов которые их генерируют. Не говоря об использование возможностей и эффектов JavaScript, которые считались спецификой, и применялись весьма редко, можно сказать неохотно в виду слабой поддержки браузеров. На сегодняшний день JavaScript пережил второе рождение и появился снова, но уже под названием AJAX.[/justify]', '[justify]Нечто подобное мы наблюдаем с использованием CMS (Систем построения сайтов), получивших высокую популярность в последние годы по причине универсальности, возможностях внедрения, расширения, адоптации под свои нужды. Хочу, заметит, что в системах подобного рода, уже в то время, использовалась тенденция, ране не существовавшая, а ныне известная как Web 2, парадоксально, но факт. Что же представляет собой Web 2? Это ничто иное, как участие пользователей в жизни проекта, комментарии к статьям, рейтинги и прочие функции, которые с не за памятных времён применялись в портальных системах и были практически не доступны на HTML сайтах, за редким своим исключением. Именно данные возможности и применение Web 2 на портальных системах послужило сильному повышению их популярности.<br>\r\n<br>\r\nС выпуском новой версии мы переходим на более высокий уровень развития, а именно, использования системы, как портальной системы построения сайтов, на уровень выше, чем Web 2. Начиная с версии SLAED CMS 3.3 Pro, мы предоставляем возможность не просто пассивного прибывания пользователей, а активного участие всех посетителей в жизни сайта. Скажу больше, пользователи получают возможность оценки, комментариев, публикаций статей, материалов, участие в опросах, добавления файлов, графических элементов, объектов, оценки друг друга, рейтинга, комментариев и многого другого почти во всех основных отделах проекта в полном объеме. Пользователи и посетители при их желании смогут стать не только наблюдателями, но и принимать активное участие в развитии сайтов. Исходя из этого, система отвечает не только тенденции Web 2, но и её последующим модификациям как Web 3. Это значит что  любой посетитель, естественно при желании и одобрении администратора, может иметь неограниченную возможность участия в развитии и наполнении всего проекта, всех его отделов.<br>\r\n<br>\r\nИдя в ногу со временем наша задача не опережать его, как это было с JavaScrit который опередил его и не получил заслуженную популярность в своё время. Естественно, что мы за использование новых технологий, но только за проверенные, востребованные временем, а главное безопасные. Основные факторы, которые ставились и ставятся при разработке системы это простота в использовании, функциональность, универсальность скорость, а главное безопасность. Наверняка и в новой версии Вы сможете по достоинству не только оценить проделанную работу,  но и активно использовать новые возможности системы, с основными изменениями которой Вы будете ознакомлены в следующей статье.[/justify]', '', 0, 0, 8, 1, 1, 5, 1, '1', '127.0.0.1', 0, 1);

DROP TABLE IF EXISTS `sport_newsletter`;
CREATE TABLE `sport_newsletter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `content` text DEFAULT NULL,
  `mails` mediumtext DEFAULT NULL,
  `send` int(10) NOT NULL DEFAULT 0,
  `time` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_order`;
CREATE TABLE `sport_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mail` varchar(255) NOT NULL,
  `info` text NOT NULL,
  `com` text NOT NULL,
  `ip` varchar(15) NOT NULL,
  `agent` varchar(255) NOT NULL,
  `date` datetime DEFAULT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_pages`;
CREATE TABLE `sport_pages` (
  `pid` int(11) NOT NULL AUTO_INCREMENT,
  `catid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `title` varchar(100) NOT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` text DEFAULT NULL,
  `bodytext` mediumtext NOT NULL,
  `comments` int(11) NOT NULL DEFAULT 0,
  `counter` int(11) NOT NULL DEFAULT 0,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `ratings` int(11) NOT NULL DEFAULT 0,
  `ip_sender` varchar(15) NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`pid`),
  KEY `catid` (`catid`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_partners`;
CREATE TABLE `sport_partners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL,
  `adres` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `website` varchar(255) NOT NULL,
  `webmoney` varchar(255) NOT NULL,
  `paypal` varchar(255) NOT NULL,
  `regdate` int(10) NOT NULL DEFAULT 0,
  `rest` int(10) NOT NULL DEFAULT 0,
  `bek` int(10) NOT NULL DEFAULT 0,
  `active` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_privat`;
CREATE TABLE `sport_privat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uidin` int(11) NOT NULL DEFAULT 0,
  `uidout` int(11) NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `date` datetime DEFAULT NULL,
  `ip_sender` varchar(15) NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_products`;
CREATE TABLE `sport_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `time` datetime DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `text` text NOT NULL,
  `bodytext` text NOT NULL,
  `preis` int(11) NOT NULL DEFAULT 0,
  `vote` int(11) NOT NULL DEFAULT 0,
  `assoc` text NOT NULL,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `com` int(11) NOT NULL DEFAULT 0,
  `count` int(11) NOT NULL DEFAULT 0,
  `votes` int(11) NOT NULL DEFAULT 0,
  `totalvotes` int(11) NOT NULL DEFAULT 0,
  `fix` int(1) NOT NULL DEFAULT 0,
  `active` int(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_rating`;
CREATE TABLE `sport_rating` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mid` int(11) NOT NULL DEFAULT 0,
  `modul` varchar(50) NOT NULL,
  `time` varchar(14) NOT NULL,
  `uid` int(11) NOT NULL DEFAULT 0,
  `host` varchar(15) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `mid` (`mid`),
  KEY `modul` (`modul`)
) ENGINE=InnoDB AUTO_INCREMENT=2 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_referer`;
CREATE TABLE `sport_referer` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `name` varchar(40) NOT NULL,
  `ip` varchar(40) NOT NULL,
  `referer` varchar(2048) NOT NULL,
  `link` varchar(2048) NOT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lid` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_search`;
CREATE TABLE `sport_search` (
  `sl_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sl_word` varchar(255) NOT NULL,
  `sl_modul` varchar(50) NOT NULL,
  `sl_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `sl_score` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sl_id`),
  KEY `sl_word` (`sl_word`),
  KEY `sl_modul` (`sl_modul`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_seo`;
CREATE TABLE `sport_seo` (
  `sl_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sl_url` varchar(255) NOT NULL,
  `sl_link` varchar(255) NOT NULL,
  `sl_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `sl_mtime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `sl_title` varchar(255) DEFAULT NULL,
  `sl_desc` varchar(255) DEFAULT NULL,
  `sl_keys` varchar(255) DEFAULT NULL,
  `sl_img` varchar(255) DEFAULT NULL,
  `sl_ctitle` varchar(255) DEFAULT NULL,
  `sl_cdesc` varchar(255) DEFAULT NULL,
  `sl_cimg` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`sl_id`),
  KEY `sl_url` (`sl_url`),
  KEY `sl_link` (`sl_link`)
) ENGINE=InnoDB AUTO_INCREMENT=25 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

INSERT INTO `sport_seo` VALUES
(1, 'name%3Dnews%26op%3Dview%26id%3D1', 'name%3Dnews%26op%3Dview%26id%3D1', '2021-02-10 21:00:00', '0000-00-00 00:00:00', 'SLAED CMS 6.1 Pro – первая Open Source версия SLAED CMS', 'О завершении очередного этапа развития проекта: SLAED CMS адаптирована к современным тенденциям в дизайне и технологияхКомпания SLAED рада представить версию 6.', 'cms,slaed,версии,проекта,лет,версий,pro,базе,open,сайт,системы,множество,лицензии,вот,системе', '', 'Soft', 'Software', 'cup.png'),
(2, 'name%3Dnews%26cat%3D2', 'name%3Dnews%26cat%3D2', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', 'Soft', 'Software', 'cup.png'),
(3, 'name%3Dnews%26op%3Dview%26id%3D2', 'name%3Dnews%26op%3Dview%26id%3D2', '2021-02-10 17:00:00', '0000-00-00 00:00:00', 'SLAED CMS переходит к Open Source модели на базе GNU GPL 3', '2017 год компания SLAED решила начать с отказа от проприетарной модели распространения SLAED CMS в пользу Open Source. Новая версия SLAED CMS переходит в общест', 'slaed,cms,open,source,будет,базе,компания,шаг,проекта,изменения,том,сообщества,news,модели,распространения', '', 'Internet', 'Internet news', 'network.png'),
(4, 'name%3Dnews%26cat%3D1', 'name%3Dnews%26cat%3D1', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', 'Internet', 'Internet news', 'network.png'),
(5, 'name%3Dnews%26op%3Dview%26id%3D3', 'name%3Dnews%26op%3Dview%26id%3D3', '2021-02-10 15:00:00', '0000-00-00 00:00:00', 'В ногу со временем или Web 2 как пройденный этап', 'Не для кого не секрет что в последнее время компьютерные технологии начали развиваться с большой скоростью, ещё больше это развитие отобразилось на сферу Интерн', 'web,сайтов,которые,участие,системы,только,время,системах,проекта,версии,возможность,больше,новые,сегодняшний,день', '', 'Internet', 'Internet news', 'network.png'),
(6, 'name%3Dnews', 'name%3Dnews', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(7, 'name%3Dnews%26op%3Dbest', 'name%3Dnews%26op%3Dbest', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(8, 'name%3Dnews%26op%3Dpop', 'name%3Dnews%26op%3Dpop', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(9, 'name%3Dnews%26op%3Dliste', 'name%3Dnews%26op%3Dliste', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(10, 'name%3Dnews%26op%3Dadd', 'name%3Dnews%26op%3Dadd', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(11, 'name%3Dfaq', 'name%3Dfaq', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(12, 'name%3Dfaq%26op%3Dbest', 'name%3Dfaq%26op%3Dbest', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(13, 'name%3Dfaq%26op%3Dpop', 'name%3Dfaq%26op%3Dpop', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(14, 'name%3Dfaq%26op%3Dliste', 'name%3Dfaq%26op%3Dliste', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(15, 'name%3Dfaq%26op%3Dadd', 'name%3Dfaq%26op%3Dadd', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(16, 'name%3Dnews%26cat%3D2%26op%3Dbest', 'name%3Dnews%26cat%3D2%26op%3Dbest', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(17, 'name%3Dnews%26cat%3D2%26op%3Dpop', 'name%3Dnews%26cat%3D2%26op%3Dpop', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(18, 'news-view-1', 'name%3Dnews%26op%3Dview%26id%3D1', '2021-02-10 21:00:00', '0000-00-00 00:00:00', 'SLAED CMS 6.1 Pro – первая Open Source версия SLAED CMS', 'О завершении очередного этапа развития проекта: SLAED CMS адаптирована к современным тенденциям в дизайне и технологияхКомпания SLAED рада представить версию 6.', 'cms,slaed,версии,проекта,лет,версий,pro,базе,open,сайт,системы,множество,лицензии,вот,системе', '', 'Soft', 'Software', 'cup.png'),
(19, 'news-2', 'name%3Dnews%26cat%3D2', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', 'Soft', 'Software', 'cup.png'),
(20, 'news-view-2', 'name%3Dnews%26op%3Dview%26id%3D2', '2021-02-10 17:00:00', '0000-00-00 00:00:00', 'SLAED CMS переходит к Open Source модели на базе GNU GPL 3', '2017 год компания SLAED решила начать с отказа от проприетарной модели распространения SLAED CMS в пользу Open Source. Новая версия SLAED CMS переходит в общест', 'slaed,cms,open,source,будет,базе,компания,шаг,проекта,изменения,том,сообщества,news,модели,распространения', '', 'Internet', 'Internet news', 'network.png'),
(21, 'news-1', 'name%3Dnews%26cat%3D1', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', 'Internet', 'Internet news', 'network.png'),
(22, 'news-view-3', 'name%3Dnews%26op%3Dview%26id%3D3', '2021-02-10 15:00:00', '0000-00-00 00:00:00', 'В ногу со временем или Web 2 как пройденный этап', 'Не для кого не секрет что в последнее время компьютерные технологии начали развиваться с большой скоростью, ещё больше это развитие отобразилось на сферу Интерн', 'web,сайтов,которые,участие,системы,только,время,системах,проекта,версии,возможность,больше,новые,сегодняшний,день', '', 'Internet', 'Internet news', 'network.png'),
(23, 'name%3Dnews%26cat%3D1%26op%3Dbest', 'name%3Dnews%26cat%3D1%26op%3Dbest', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', ''),
(24, 'name%3Dnews%26cat%3D1%26op%3Dpop', 'name%3Dnews%26cat%3D1%26op%3Dpop', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '', '', '', '', '', '', '');

DROP TABLE IF EXISTS `sport_session`;
CREATE TABLE `sport_session` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uname` varchar(40) NOT NULL,
  `time` bigint(20) unsigned NOT NULL,
  `host_addr` varchar(40) NOT NULL,
  `guest` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `module` varchar(25) DEFAULT NULL,
  `url` varchar(2048) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uname` (`uname`),
  KEY `time` (`time`)
) ENGINE=InnoDB AUTO_INCREMENT=59 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

INSERT INTO `sport_session` VALUES
(58, 'slaed', 1762855595, '127.0.0.1', 3, 'files', '%2Findex.php%3Fname%3Dfiles');

DROP TABLE IF EXISTS `sport_users`;
CREATE TABLE `sport_users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(25) NOT NULL,
  `user_rank` varchar(25) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `user_website` varchar(255) NOT NULL,
  `user_avatar` varchar(255) NOT NULL,
  `user_regdate` datetime NOT NULL,
  `user_occ` varchar(100) DEFAULT NULL,
  `user_from` varchar(100) DEFAULT NULL,
  `user_interests` varchar(150) NOT NULL,
  `user_sig` varchar(255) DEFAULT NULL,
  `user_viewemail` tinyint(1) DEFAULT NULL,
  `user_password` varchar(32) NOT NULL,
  `user_storynum` tinyint(4) NOT NULL DEFAULT 10,
  `user_blockon` tinyint(1) NOT NULL DEFAULT 0,
  `user_block` text NOT NULL,
  `user_theme` varchar(255) NOT NULL,
  `user_newsletter` int(1) NOT NULL DEFAULT 1,
  `user_fsmail` int(1) NOT NULL DEFAULT 1,
  `user_psmail` int(1) NOT NULL DEFAULT 1,
  `user_lastvisit` datetime NOT NULL,
  `user_lang` varchar(255) NOT NULL DEFAULT 'russian',
  `user_points` int(10) DEFAULT 0,
  `user_last_ip` varchar(15) NOT NULL,
  `user_warnings` text NOT NULL,
  `user_acess` int(1) NOT NULL DEFAULT 0,
  `user_group` int(1) NOT NULL DEFAULT 0,
  `user_birthday` date DEFAULT NULL,
  `user_gender` int(1) NOT NULL DEFAULT 0,
  `user_votes` int(11) NOT NULL DEFAULT 0,
  `user_totalvotes` int(11) NOT NULL DEFAULT 0,
  `user_field` text NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `user_network` varchar(255) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_users_temp`;
CREATE TABLE `sport_users_temp` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(25) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `user_password` varchar(25) NOT NULL,
  `user_regdate` datetime NOT NULL,
  `check_num` varchar(50) NOT NULL,
  `time` varchar(14) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

DROP TABLE IF EXISTS `sport_voting`;
CREATE TABLE `sport_voting` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modul` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `questions` text NOT NULL,
  `answer` text NOT NULL,
  `date` datetime DEFAULT NULL,
  `enddate` datetime DEFAULT NULL,
  `multi` int(1) NOT NULL DEFAULT 0,
  `comments` int(11) NOT NULL DEFAULT 0,
  `language` varchar(30) NOT NULL,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `ip` varchar(15) NOT NULL,
  `typ` int(1) NOT NULL DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `modul` (`modul`)
) ENGINE=InnoDB AUTO_INCREMENT=2 /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

