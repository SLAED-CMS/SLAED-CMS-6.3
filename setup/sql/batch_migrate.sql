# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net
# Compatible: MySQL 8.0+ & MariaDB 10.5+
#
# batch_migrate.sql — Column & index renames for SLAED schema modernization
#
# Run this script ONCE on an existing installation to rename legacy columns
# to the new normalized schema. Replace {prefix} with your table prefix.
#
# Batches A–K: all tables except _users / _users_temp
# Batch L:     _users and _users_temp
#
# IMPORTANT: Back up your database before running this script.

# =============================================================================
# Batch A — _admins
# lastvisit → lvisit
# =============================================================================

ALTER TABLE `{prefix}_admins`
  RENAME COLUMN `lastvisit` TO `lvisit`;

ALTER TABLE `{prefix}_admins`
  RENAME INDEX `lastvisit` TO `lvisit`;

# =============================================================================
# Batch B — _auto_links
# sitename → title
# =============================================================================

ALTER TABLE `{prefix}_auto_links`
  RENAME COLUMN `sitename` TO `title`;

# =============================================================================
# Batch C — _blocks
# bid → id, bposition → bpos, active → status, blanguage → blang, blockfile → bfile
# =============================================================================

ALTER TABLE `{prefix}_blocks`
  RENAME COLUMN `bid`       TO `id`,
  RENAME COLUMN `bposition` TO `bpos`,
  RENAME COLUMN `active`    TO `status`,
  RENAME COLUMN `blanguage` TO `blang`,
  RENAME COLUMN `blockfile` TO `bfile`;

ALTER TABLE `{prefix}_blocks`
  RENAME INDEX `active_position` TO `status_position`,
  RENAME INDEX `blanguage`       TO `blang`;

# =============================================================================
# Batch D — _categories
# parentid → parent, cstatus → status, lpost_id → lpost
# =============================================================================

ALTER TABLE `{prefix}_categories`
  RENAME COLUMN `parentid` TO `parent`,
  RENAME COLUMN `cstatus`  TO `status`,
  RENAME COLUMN `lpost_id` TO `lpost`;

ALTER TABLE `{prefix}_categories`
  RENAME INDEX `parentid` TO `parent`;

# =============================================================================
# Batch E — _clients
# id_user → uid, id_product → prod, id_partner → part,
# partner_proz → proz, adres → addr, active → status
# =============================================================================

ALTER TABLE `{prefix}_clients`
  RENAME COLUMN `id_user`      TO `uid`,
  RENAME COLUMN `id_product`   TO `prod`,
  RENAME COLUMN `id_partner`   TO `part`,
  RENAME COLUMN `partner_proz` TO `proz`,
  RENAME COLUMN `adres`        TO `addr`,
  RENAME COLUMN `active`       TO `status`;

ALTER TABLE `{prefix}_clients`
  RENAME INDEX `id_user`    TO `uid`,
  RENAME INDEX `id_product` TO `prod`,
  RENAME INDEX `id_partner` TO `part`,
  RENAME INDEX `active`     TO `status`;

# =============================================================================
# Batch F — _comment
# date → time, host_name → ip
# =============================================================================

ALTER TABLE `{prefix}_comment`
  RENAME COLUMN `date`      TO `time`,
  RENAME COLUMN `host_name` TO `ip`;

ALTER TABLE `{prefix}_comment`
  RENAME INDEX `date` TO `time`;

# =============================================================================
# Batch G — _faq
# fid → id, catid → cid, ip_sender → ip
# =============================================================================

ALTER TABLE `{prefix}_faq`
  RENAME COLUMN `fid`       TO `id`,
  RENAME COLUMN `catid`     TO `cid`,
  RENAME COLUMN `ip_sender` TO `ip`;

ALTER TABLE `{prefix}_faq`
  RENAME INDEX `catid` TO `cid`;

# =============================================================================
# Batch G — _files
# lid → id, date → time, ip_sender → ip, totalvotes → tvotes, totalcomments → tcom
# =============================================================================

ALTER TABLE `{prefix}_files`
  RENAME COLUMN `lid`           TO `id`,
  RENAME COLUMN `date`          TO `time`,
  RENAME COLUMN `ip_sender`     TO `ip`,
  RENAME COLUMN `totalvotes`    TO `tvotes`,
  RENAME COLUMN `totalcomments` TO `tcom`;

# =============================================================================
# Batch G — _forum
# catid → cid, ip_send → ip, l_uid → luid, l_name → lname,
# l_id → lpost, l_time → ltime, e_uid → euid, e_ip_send → eip, e_time → etime
# =============================================================================

ALTER TABLE `{prefix}_forum`
  RENAME COLUMN `catid`    TO `cid`,
  RENAME COLUMN `ip_send`  TO `ip`,
  RENAME COLUMN `l_uid`    TO `luid`,
  RENAME COLUMN `l_name`   TO `lname`,
  RENAME COLUMN `l_id`     TO `lpost`,
  RENAME COLUMN `l_time`   TO `ltime`,
  RENAME COLUMN `e_uid`    TO `euid`,
  RENAME COLUMN `e_ip_send` TO `eip`,
  RENAME COLUMN `e_time`   TO `etime`;

ALTER TABLE `{prefix}_forum`
  RENAME INDEX `catid`        TO `cid`,
  RENAME INDEX `catid_status` TO `cid_status`;

# =============================================================================
# Batch H — _help
# sid → id, catid → cid, ip_sender → ip
# =============================================================================

ALTER TABLE `{prefix}_help`
  RENAME COLUMN `sid`       TO `id`,
  RENAME COLUMN `catid`     TO `cid`,
  RENAME COLUMN `ip_sender` TO `ip`;

ALTER TABLE `{prefix}_help`
  RENAME INDEX `catid` TO `cid`;

# =============================================================================
# Batch H — _jokes
# jokeid → id, date → time, cat → cid, joke → hometext,
# ratingtot → ratetot, ip_sender → ip
# =============================================================================

ALTER TABLE `{prefix}_jokes`
  RENAME COLUMN `jokeid`    TO `id`,
  RENAME COLUMN `date`      TO `time`,
  RENAME COLUMN `cat`       TO `cid`,
  RENAME COLUMN `joke`      TO `hometext`,
  RENAME COLUMN `ratingtot` TO `ratetot`,
  RENAME COLUMN `ip_sender` TO `ip`;

ALTER TABLE `{prefix}_jokes`
  RENAME INDEX `cat` TO `cid`;

# =============================================================================
# Batch I — _links
# lid → id, date → time, ip_sender → ip, totalvotes → tvotes, totalcomments → tcom
# =============================================================================

ALTER TABLE `{prefix}_links`
  RENAME COLUMN `lid`           TO `id`,
  RENAME COLUMN `date`          TO `time`,
  RENAME COLUMN `ip_sender`     TO `ip`,
  RENAME COLUMN `totalvotes`    TO `tvotes`,
  RENAME COLUMN `totalcomments` TO `tcom`;

# =============================================================================
# Batch J — _media
# createdby → author, date → time, totalvotes → tvotes, totalcom → tcom, ip_sender → ip
# =============================================================================

ALTER TABLE `{prefix}_media`
  RENAME COLUMN `createdby`  TO `author`,
  RENAME COLUMN `date`       TO `time`,
  RENAME COLUMN `totalvotes` TO `tvotes`,
  RENAME COLUMN `totalcom`   TO `tcom`,
  RENAME COLUMN `ip_sender`  TO `ip`;

# =============================================================================
# Batch J — _message
# mid → id, active → status, mlanguage → lang
# =============================================================================

ALTER TABLE `{prefix}_message`
  RENAME COLUMN `mid`        TO `id`,
  RENAME COLUMN `active`     TO `status`,
  RENAME COLUMN `mlanguage`  TO `lang`;

ALTER TABLE `{prefix}_message`
  RENAME INDEX `active`     TO `status`,
  RENAME INDEX `mlanguage`  TO `lang`;

# =============================================================================
# Batch J — _news
# sid → id, catid → cid, ip_sender → ip
# =============================================================================

ALTER TABLE `{prefix}_news`
  RENAME COLUMN `sid`       TO `id`,
  RENAME COLUMN `catid`     TO `cid`,
  RENAME COLUMN `ip_sender` TO `ip`;

ALTER TABLE `{prefix}_news`
  RENAME INDEX `catid` TO `cid`;

# =============================================================================
# Batch J — _order
# date → time
# =============================================================================

ALTER TABLE `{prefix}_order`
  RENAME COLUMN `date` TO `time`;

ALTER TABLE `{prefix}_order`
  RENAME INDEX `date` TO `time`;

# =============================================================================
# Batch K — _pages
# pid → id, catid → cid, ip_sender → ip
# =============================================================================

ALTER TABLE `{prefix}_pages`
  RENAME COLUMN `pid`       TO `id`,
  RENAME COLUMN `catid`     TO `cid`,
  RENAME COLUMN `ip_sender` TO `ip`;

ALTER TABLE `{prefix}_pages`
  RENAME INDEX `catid` TO `cid`;

# =============================================================================
# Batch K — _partners
# id_user → uid, adres → addr, active → status
# =============================================================================

ALTER TABLE `{prefix}_partners`
  RENAME COLUMN `id_user` TO `uid`,
  RENAME COLUMN `adres`   TO `addr`,
  RENAME COLUMN `active`  TO `status`;

ALTER TABLE `{prefix}_partners`
  RENAME INDEX `id_user` TO `uid`,
  RENAME INDEX `active`  TO `status`;

# =============================================================================
# Batch K — _privat
# date → time, ip_sender → ip
# =============================================================================

ALTER TABLE `{prefix}_privat`
  RENAME COLUMN `date`      TO `time`,
  RENAME COLUMN `ip_sender` TO `ip`;

ALTER TABLE `{prefix}_privat`
  RENAME INDEX `date` TO `time`;

# =============================================================================
# Batch K — _products
# preis → price, totalvotes → tvotes, active → status
# =============================================================================

ALTER TABLE `{prefix}_products`
  RENAME COLUMN `preis`      TO `price`,
  RENAME COLUMN `totalvotes` TO `tvotes`,
  RENAME COLUMN `active`     TO `status`;

ALTER TABLE `{prefix}_products`
  RENAME INDEX `active` TO `status`;

# =============================================================================
# Batch K — _rating
# host → ip
# The UNIQUE KEY composition changes (uid → ip), so DROP + ADD is required.
# =============================================================================

ALTER TABLE `{prefix}_rating`
  RENAME COLUMN `host` TO `ip`,
  DROP INDEX `mid_modul_uid`,
  ADD UNIQUE KEY `mid_modul_ip` (`mid`, `modul`, `ip`);

# =============================================================================
# Batch K — _referer
# date → time
# =============================================================================

ALTER TABLE `{prefix}_referer`
  RENAME COLUMN `date` TO `time`;

ALTER TABLE `{prefix}_referer`
  RENAME INDEX `date` TO `time`;

# =============================================================================
# Batch K — _search
# sl_id → id, sl_word → word, sl_modul → modul, sl_time → time, sl_score → score
# =============================================================================

ALTER TABLE `{prefix}_search`
  RENAME COLUMN `sl_id`    TO `id`,
  RENAME COLUMN `sl_word`  TO `word`,
  RENAME COLUMN `sl_modul` TO `modul`,
  RENAME COLUMN `sl_time`  TO `time`,
  RENAME COLUMN `sl_score` TO `score`;

ALTER TABLE `{prefix}_search`
  RENAME INDEX `sl_word`       TO `word`,
  RENAME INDEX `sl_modul`      TO `modul`,
  RENAME INDEX `sl_time`       TO `time`,
  RENAME INDEX `sl_word_modul` TO `word_modul`;

# =============================================================================
# Batch K — _session
# host_addr → ip
# =============================================================================

ALTER TABLE `{prefix}_session`
  RENAME COLUMN `host_addr` TO `ip`;

ALTER TABLE `{prefix}_session`
  RENAME INDEX `host_addr` TO `ip`;

# =============================================================================
# Batch K — _voting
# date → time
# =============================================================================

ALTER TABLE `{prefix}_voting`
  RENAME COLUMN `date` TO `time`;

# =============================================================================
# Batch L — _users
# All user_* columns renamed to normalized names
# =============================================================================

ALTER TABLE `{prefix}_users`
  RENAME COLUMN `user_id`        TO `id`,
  RENAME COLUMN `user_name`      TO `name`,
  RENAME COLUMN `user_rank`      TO `rank`,
  RENAME COLUMN `user_email`     TO `email`,
  RENAME COLUMN `user_website`   TO `website`,
  RENAME COLUMN `user_avatar`    TO `avatar`,
  RENAME COLUMN `user_regdate`   TO `regdate`,
  RENAME COLUMN `user_occ`       TO `occ`,
  RENAME COLUMN `user_from`      TO `origin`,
  RENAME COLUMN `user_interests` TO `interest`,
  RENAME COLUMN `user_sig`       TO `sig`,
  RENAME COLUMN `user_viewemail` TO `viewmail`,
  RENAME COLUMN `user_password`  TO `password`,
  RENAME COLUMN `user_storynum`  TO `storynum`,
  RENAME COLUMN `user_blockon`   TO `blockon`,
  RENAME COLUMN `user_block`     TO `block`,
  RENAME COLUMN `user_theme`     TO `theme`,
  RENAME COLUMN `user_newsletter` TO `newslet`,
  RENAME COLUMN `user_fsmail`    TO `fsmail`,
  RENAME COLUMN `user_psmail`    TO `psmail`,
  RENAME COLUMN `user_lastvisit` TO `lastvis`,
  RENAME COLUMN `user_lang`      TO `lang`,
  RENAME COLUMN `user_points`    TO `points`,
  RENAME COLUMN `user_last_ip`   TO `lastip`,
  RENAME COLUMN `user_warnings`  TO `warnings`,
  RENAME COLUMN `user_acess`     TO `access`,
  RENAME COLUMN `user_group`     TO `grp`,
  RENAME COLUMN `user_birthday`  TO `birthday`,
  RENAME COLUMN `user_gender`    TO `gender`,
  RENAME COLUMN `user_votes`     TO `votes`,
  RENAME COLUMN `user_totalvotes` TO `tvotes`,
  RENAME COLUMN `user_field`     TO `field`,
  RENAME COLUMN `user_agent`     TO `agent`,
  RENAME COLUMN `user_network`   TO `network`;

ALTER TABLE `{prefix}_users`
  RENAME INDEX `user_name`      TO `name`,
  RENAME INDEX `user_email`     TO `email`,
  RENAME INDEX `user_group`     TO `grp`,
  RENAME INDEX `user_points`    TO `points`,
  RENAME INDEX `user_lastvisit` TO `lastvis`;

# =============================================================================
# Batch L — _users_temp
# user_id → id, user_name → name, user_email → email,
# user_password → password, user_regdate → regdate, check_num → code
# =============================================================================

ALTER TABLE `{prefix}_users_temp`
  RENAME COLUMN `user_id`       TO `id`,
  RENAME COLUMN `user_name`     TO `name`,
  RENAME COLUMN `user_email`    TO `email`,
  RENAME COLUMN `user_password` TO `password`,
  RENAME COLUMN `user_regdate`  TO `regdate`,
  RENAME COLUMN `check_num`     TO `code`;

ALTER TABLE `{prefix}_users_temp`
  RENAME INDEX `user_name`  TO `name`,
  RENAME INDEX `check_num`  TO `code`;
