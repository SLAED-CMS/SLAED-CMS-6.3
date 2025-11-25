<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined("MODULE_FILE")) {
	header("Location: ../../index.php");
	exit;
}

head();
$frame = "<iframe src=\"plugins/image/start.php\" style=\"width: 100%; height: 500px; text-align: center;\" scrolling=\"0\" marginheight=\"0\" marginwidth=\"0\" frameborder=\"0\"></iframe>";
$cont = tpl_eval("title", _ALBUM);
$cont .= tpl_eval("open").$frame.tpl_eval("close");
echo $cont;
foot();
?>