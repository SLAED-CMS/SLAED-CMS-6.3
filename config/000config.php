<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

$confdb = array();
$confdb['host'] = "slaed.loc";
$confdb['uname'] = "root";
$confdb['pass'] = "";
$confdb['name'] = "slaed";
$confdb['type'] = "mysqli";

# ADD NEW
# Механизм хранения
$confdb['engine'] = "InnoDB";
# Кодировка базы данных
$confdb['charset'] = "utf8mb4";
# Сравнение кодировки таблиц
$confdb['collate'] = "utf8mb4_unicode_ci";
# Префикс таблиц
$confdb['prefix'] = "sport";


# Синхронизация времени базы данных с PHP
$confdb['sync'] = "0";
# Деактивация строгого режима
$confdb['mode'] = "0";


# DELETE OLD
$conf['dbsync'] = "0";
$prefix = "sport";
$admin_file = "admin";

?>