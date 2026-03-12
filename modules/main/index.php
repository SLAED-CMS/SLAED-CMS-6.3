<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function main(): void {
    global $db;
     setHead(['title' => 'ÐŸÑ€Ð¾ÑÑ‚Ð¾Ñ‚Ð° Ð¤ÑƒÐ½ÐºÑ†Ð¸Ð¾Ð½Ð°Ð»ÑŒÐ½Ð¾ÑÑ‚ÑŒ Ð­Ñ„Ñ„ÐµÐºÑ‚Ð¸Ð²Ð½Ð¾ÑÑ‚ÑŒ Ð‘ÐµÐ·Ð¾Ð¿Ð°ÑÐ½Ð¾ÑÑ‚ÑŒ', 'desc' => 'Ð¡Ð¸ÑÑ‚ÐµÐ¼Ð° ÑƒÐ¿Ñ€Ð°Ð²Ð»ÐµÐ½Ð¸Ñ ÑÐ¾Ð´ÐµÑ€Ð¶Ð¸Ð¼Ñ‹Ð¼ ÑÐ°Ð¹Ñ‚Ð°, Ð¿Ñ€Ð¾ÑÑ‚Ð°Ñ Ð² Ð¸ÑÐ¿Ð¾Ð»ÑŒÐ·Ð¾Ð²Ð°Ð½Ð¸Ð¸ Ð¸ Ð½Ð°ÑÑ‚Ñ€Ð¾Ð¹ÐºÐµ, Ð¸Ð¼ÐµÑŽÑ‰Ð°Ñ Ð¿Ñ€Ð¸ ÑÑ‚Ð¾Ð¼ Ð²Ñ‹ÑÐ¾ÐºÐ¸Ð¹ ÑƒÑ€Ð¾Ð²ÐµÐ½ÑŒ Ð±ÐµÐ·Ð¾Ð¿Ð°ÑÐ½Ð¾ÑÑ‚Ð¸, Ð²Ñ‹ÑÐ¾ÐºÑƒÑŽ ÑÐºÐ¾Ñ€Ð¾ÑÑ‚ÑŒ Ñ€Ð°Ð±Ð¾Ñ‚Ñ‹, Ð° Ñ‚Ð°ÐºÐ¶Ðµ Ð¿Ñ€Ð°ÐºÑ‚Ð¸Ñ‡ÐµÑÐºÐ¸ Ð½ÐµÐ¾Ð³Ñ€Ð°Ð½Ð¸Ñ‡ÐµÐ½Ð½Ñ‹Ð¹ Ð¿Ð¾Ñ‚ÐµÐ½Ñ†Ð¸Ð°Ð» Ð² Ñ€ÐµÑˆÐµÐ½Ð¸Ð¸ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ° Ñ€Ð°ÑÑˆÐ¸Ñ€ÐµÐ½Ð¸Ñ Ñ„ÑƒÐ½ÐºÑ†Ð¸Ð¾Ð½Ð°Ð»ÑŒÐ½Ð¾ÑÑ‚Ð¸.']);
    $cont = '<div class="slider-head">
        <ul id="slider-head">
            <li>
                <div class="slide-cont">
                    <h1 class="font slide-title">SLAED CMS</h1>
                    <p style="text-align: justify;">Ð­Ñ‚Ð¾ ÑÐ¸ÑÑ‚ÐµÐ¼Ð° ÑƒÐ¿Ñ€Ð°Ð²Ð»ÐµÐ½Ð¸Ñ ÑÐ¾Ð´ÐµÑ€Ð¶Ð¸Ð¼Ñ‹Ð¼ ÑÐ°Ð¹Ñ‚Ð°, Ð¿Ñ€Ð¾ÑÑ‚Ð°Ñ Ð² Ð¸ÑÐ¿Ð¾Ð»ÑŒÐ·Ð¾Ð²Ð°Ð½Ð¸Ð¸ Ð¸ Ð½Ð°ÑÑ‚Ñ€Ð¾Ð¹ÐºÐµ, Ð¸Ð¼ÐµÑŽÑ‰Ð°Ñ Ð¿Ñ€Ð¸ ÑÑ‚Ð¾Ð¼ Ð²Ñ‹ÑÐ¾ÐºÐ¸Ð¹ ÑƒÑ€Ð¾Ð²ÐµÐ½ÑŒ Ð±ÐµÐ·Ð¾Ð¿Ð°ÑÐ½Ð¾ÑÑ‚Ð¸, Ð²Ñ‹ÑÐ¾ÐºÑƒÑŽ ÑÐºÐ¾Ñ€Ð¾ÑÑ‚ÑŒ Ñ€Ð°Ð±Ð¾Ñ‚Ñ‹, Ð° Ñ‚Ð°ÐºÐ¶Ðµ Ð¿Ñ€Ð°ÐºÑ‚Ð¸Ñ‡ÐµÑÐºÐ¸ Ð½ÐµÐ¾Ð³Ñ€Ð°Ð½Ð¸Ñ‡ÐµÐ½Ð½Ñ‹Ð¹ Ð¿Ð¾Ñ‚ÐµÐ½Ñ†Ð¸Ð°Ð» Ð² Ñ€ÐµÑˆÐµÐ½Ð¸Ð¸ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ° Ñ€Ð°ÑÑˆÐ¸Ñ€ÐµÐ½Ð¸Ñ Ñ„ÑƒÐ½ÐºÑ†Ð¸Ð¾Ð½Ð°Ð»ÑŒÐ½Ð¾ÑÑ‚Ð¸. ÐÐ° Ð¾ÑÐ½Ð¾Ð²Ðµ SLAED Ð»ÑŽÐ±Ð¾Ð¹ Ð¶ÐµÐ»Ð°ÑŽÑ‰Ð¸Ð¹, Ð´Ð°Ð¶Ðµ, Ð½Ðµ Ð¾Ð±Ð»Ð°Ð´Ð°ÑŽÑ‰Ð¸Ð¹ Ð±Ð¾Ð»ÑŒÑˆÐ¸Ð¼Ð¸ Ð·Ð½Ð°Ð½Ð¸ÑÐ¼Ð¸, Ð¼Ð¾Ð¶ÐµÑ‚ Ð¿Ð¾ÑÑ‚Ñ€Ð¾Ð¸Ñ‚ÑŒ ÑÐµÐ±Ðµ Ð½Ðµ Ñ‚Ð¾Ð»ÑŒÐºÐ¾ ÐºÐ°Ñ‡ÐµÑÑ‚Ð²ÐµÐ½Ð½Ñ‹Ð¹ ÑÐ°Ð¹Ñ‚, Ð½Ð¾ Ð¸ Ð¼Ð¾Ñ‰Ð½Ñ‹Ð¹ Ð¿Ð¾Ñ€Ñ‚Ð°Ð».</p>
                    <a class="sl_but_blue" href="index.php?name=faq&amp;cat=38#18" title="Ð¡Ð¸ÑÑ‚ÐµÐ¼Ð° ÑƒÐ¿Ñ€Ð°Ð²Ð»ÐµÐ½Ð¸Ñ ÑÐ¾Ð´ÐµÑ€Ð¶Ð¸Ð¼Ñ‹Ð¼ ÑÐ°Ð¹Ñ‚Ð° SLAED CMS"><b>ÐŸÐ¾Ð´Ñ€Ð¾Ð±Ð½ÐµÐµ</b></a>
                </div>
                <div class="slide-img"><span class="slide-season"></span></div>
            </li>
            <li>
                <div class="slide-cont">
                    <h2 class="font slide-title">ÐŸÑ€Ð¾ÑÑ‚Ð¾Ñ‚Ð°</h2>
                    <p style="text-align: justify;">Ð˜Ð½Ñ‚ÑƒÐ¸Ñ‚Ð¸Ð²Ð½Ð¾ Ð¿Ð¾Ð½ÑÑ‚Ð½Ñ‹Ð¹ Ð¸Ð½Ñ‚ÐµÑ€Ñ„ÐµÐ¹Ñ, Ð¿Ð¾Ð·Ð²Ð¾Ð»ÑÐµÑ‚ ÑƒÐ¿Ñ€Ð°Ð²Ð»ÑÑ‚ÑŒ ÑÐ°Ð¹Ñ‚Ð¾Ð¼ Ñ‚Ð°Ðº Ð¶Ðµ Ð¿Ñ€Ð¾ÑÑ‚Ð¾, ÐºÐ°Ðº Ñ€Ð°Ð±Ð¾Ñ‚Ð°Ñ‚ÑŒ Ñ Ð´Ð¾ÐºÑƒÐ¼ÐµÐ½Ñ‚Ð°Ð¼Ð¸ Ð¸Ð»Ð¸ Ð¿Ð¾Ñ‡Ñ‚Ð¾Ð¹. ÐŸÑ€Ð¸ Ð½Ð°Ð»Ð¸Ñ‡Ð¸Ðµ Ñ…Ð¾Ñ‚Ñ Ð±Ñ‹ Ð½ÐµÐ±Ð¾Ð»ÑŒÑˆÐ¾Ð³Ð¾ Ð¾Ð¿Ñ‹Ñ‚Ð° Ð¸ Ð·Ð½Ð°Ð½Ð¸Ñ HTML, ÑÐ¼Ð¾Ð¶ÐµÑ‚Ðµ ÑÑƒÑ‰ÐµÑÑ‚Ð²ÐµÐ½Ð½Ð¾ Ð¸Ð·Ð¼ÐµÐ½Ð¸Ñ‚ÑŒ Ð½Ðµ Ñ‚Ð¾Ð»ÑŒÐºÐ¾ Ð²Ð½ÐµÑˆÐ½Ð¸Ð¹ Ð²Ð¸Ð´, Ð½Ð¾ Ð¸ ÑÐ°Ð¼Ñƒ ÑÑ‚Ñ€ÑƒÐºÑ‚ÑƒÑ€Ñƒ ÑÐ°Ð¹Ñ‚Ð°. ÐœÐ¾Ð´ÑƒÐ»ÑŒÐ½Ð¾Ðµ Ð½Ð°Ñ€Ð°Ñ‰Ð¸Ð²Ð°Ð½Ð¸Ðµ Ð¿Ð¾Ð·Ð²Ð¾Ð»ÑÐµÑ‚ Ð¸Ð½ÑÑ‚Ð°Ð»Ð»Ð¸Ñ€Ð¾Ð²Ð°Ñ‚ÑŒ Ð½Ð° Ð²Ð°Ñˆ ÑÐ°Ð¹Ñ‚ Ñ€Ð°Ð·Ð½Ð¾Ð³Ð¾ Ñ€Ð¾Ð´Ð° Ð¼Ð¾Ð´ÑƒÐ»Ð¸, Ð±Ð»Ð¾ÐºÐ¸, Ñ‚ÐµÐ¼Ñ‹ Ð¾Ñ„Ð¾Ñ€Ð¼Ð»ÐµÐ½Ð¸Ñ Ð¸ Ñ€Ð°Ð·Ð½Ð¾Ð³Ð¾ Ñ€Ð¾Ð´Ð° Ð´Ð¾Ð¿Ð¾Ð»Ð½ÐµÐ½Ð¸Ñ.</p>
                    <a href="index.php?name=content&amp;op=view&amp;id=14" title="Ð£Ð¿Ñ€Ð°Ð²Ð»ÑÑ‚ÑŒ ÑÐ°Ð¹Ñ‚Ð¾Ð¼ Ñ‚Ð°ÐºÐ¶Ðµ Ð¿Ñ€Ð¾ÑÑ‚Ð¾, ÐºÐ°Ðº Ñ€Ð°Ð±Ð¾Ñ‚Ð°Ñ‚ÑŒ Ñ Ð´Ð¾ÐºÑƒÐ¼ÐµÐ½Ñ‚Ð°Ð¼Ð¸ Ð½Ð° ÐºÐ¾Ð¼Ð¿ÑŒÑŽÑ‚ÐµÑ€Ðµ" class="sl_but_blue"><b>ÐŸÐ¾Ð´Ñ€Ð¾Ð±Ð½ÐµÐµ</b></a>
                </div>
                <div class="slide-img"><img src="templates/lite/images/slide/slaed_1.jpg" alt="Ð£Ð¿Ñ€Ð°Ð²Ð»ÑÑ‚ÑŒ ÑÐ°Ð¹Ñ‚Ð¾Ð¼ Ñ‚Ð°ÐºÐ¶Ðµ Ð¿Ñ€Ð¾ÑÑ‚Ð¾, ÐºÐ°Ðº Ñ€Ð°Ð±Ð¾Ñ‚Ð°Ñ‚ÑŒ Ñ Ð´Ð¾ÐºÑƒÐ¼ÐµÐ½Ñ‚Ð°Ð¼Ð¸ Ð½Ð° ÐºÐ¾Ð¼Ð¿ÑŒÑŽÑ‚ÐµÑ€Ðµ"></div>
            </li>
            <li>
                <div class="slide-cont">
                    <h2 class="font slide-title">Ð¤ÑƒÐ½ÐºÑ†Ð¸Ð¾Ð½Ð°Ð»ÑŒÐ½Ð¾ÑÑ‚ÑŒ</h2>
                    <p style="text-align: justify;">Ðš Ð´Ð¾Ð¿Ð¾Ð»Ð½ÐµÐ½Ð¸ÑŽ Ð¸ Ð±ÐµÐ· Ñ‚Ð¾Ð³Ð¾ ÑˆÐ¸Ñ€Ð¾ÐºÐ¾Ð³Ð¾ Ñ„ÑƒÐ½ÐºÑ†Ð¸Ð¾Ð½Ð°Ð»Ð° ÑÐ¸ÑÑ‚ÐµÐ¼Ñ‹, ÑÑƒÑ‰ÐµÑÑ‚Ð²ÑƒÐµÑ‚ Ð¼Ð°ÑÑÐ° Ð´Ð¾Ð¿Ð¾Ð»Ð½Ð¸Ñ‚ÐµÐ»ÑŒÐ½Ñ‹Ñ… Ð¼Ð¾Ð´ÑƒÐ»ÐµÐ¹, Ð±Ð»Ð¾ÐºÐ¾Ð², Ñ€Ð°ÑÑˆÐ¸Ñ€ÐµÐ½Ð¸Ð¹ Ð¸ Ñ‚ÐµÐ¼ Ð¾Ñ„Ð¾Ñ€Ð¼Ð»ÐµÐ½Ð¸Ñ, ÐºÐ¾Ñ‚Ð¾Ñ€Ñ‹Ðµ Ð´Ð¾Ð¿Ð¾Ð»Ð½ÑÑ‚ ÑÐ¸ÑÑ‚ÐµÐ¼Ñƒ Ð¿Ñ€Ð°ÐºÑ‚Ð¸Ñ‡ÐµÑÐºÐ¸ Ð»ÑŽÐ±Ñ‹Ð¼ Ñ„ÑƒÐ½ÐºÑ†Ð¸Ð¾Ð½Ð°Ð»Ð¾Ð¼. Ð¤Ð°Ð¹Ð»Ð¾Ð²Ñ‹Ð¹ Ð°Ñ€Ñ…Ð¸Ð² Ð¿Ñ€Ð¾ÐµÐºÑ‚Ð° ÑÐ¾Ð´ÐµÑ€Ð¶Ð¸Ñ‚ Ð±Ð¾Ð»ÐµÐµ 685 Ð´Ð¾Ð¿Ð¾Ð»Ð½ÐµÐ½Ð¸Ð¹ Ð¾Ñ‚ ÑÑ‚Ð¾Ñ€Ð¾Ð½Ð½Ð¸Ñ… Ñ€Ð°Ð·Ñ€Ð°Ð±Ð¾Ñ‚Ñ‡Ð¸ÐºÐ¾Ð². Ð¡Ð¸ÑÑ‚ÐµÐ¼Ð° ÑƒÐ¿Ñ€Ð°Ð²Ð»ÐµÐ½Ð¸Ñ Ð¿Ð¾ÑÑ‚Ð°Ð²Ð»ÑÐµÑ‚ÑÑ Ñ Ð¾Ñ‚ÐºÑ€Ñ‹Ñ‚Ñ‹Ð¼ Ð¸ÑÑ…Ð¾Ð´Ð½Ñ‹Ð¼ ÐºÐ¾Ð´Ð¾Ð¼, Ñ‡Ñ‚Ð¾ Ð´Ð°Ñ‘Ñ‚ Ð½ÐµÐ¾Ð³Ñ€Ð°Ð½Ð¸Ñ‡ÐµÐ½Ð½Ñ‹Ð¹ Ð²Ð¾Ð·Ð¼Ð¾Ð¶Ð½Ð¾ÑÑ‚Ð¸ Ð¼Ð¾Ð´Ð¸Ñ„Ð¸ÐºÐ°Ñ†Ð¸Ð¸ Ð¾Ð¿Ñ‹Ñ‚Ð½Ñ‹Ð¼ Ð¿Ð¾Ð»ÑŒÐ·Ð¾Ð²Ð°Ñ‚ÐµÐ»ÑÐ¼.</p>
                    <a href="index.php?name=content&amp;op=view&amp;id=15" title="Ð¡ÐºÐ¾Ñ€ÐµÐ¹ Ð²ÑÐµÐ³Ð¾, Ñƒ Ð½Ð°Ñ Ð¿Ñ€ÐµÐ´ÑƒÑÐ¼Ð¾Ñ‚Ñ€ÐµÐ½Ñ‹ Ñ„ÑƒÐ½ÐºÑ†Ð¸Ð¸, ÐºÐ¾Ñ‚Ð¾Ñ€Ñ‹Ðµ Ð½ÑƒÐ¶Ð½Ñ‹ Ð’Ð°Ð¼" class="sl_but_blue"><b>ÐŸÐ¾Ð´Ñ€Ð¾Ð±Ð½ÐµÐµ</b></a>
                </div>
                <div class="slide-img"><img src="templates/lite/images/slide/slaed_2.jpg" alt="Ð¡ÐºÐ¾Ñ€ÐµÐ¹ Ð²ÑÐµÐ³Ð¾, Ñƒ Ð½Ð°Ñ Ð¿Ñ€ÐµÐ´ÑƒÑÐ¼Ð¾Ñ‚Ñ€ÐµÐ½Ñ‹ Ñ„ÑƒÐ½ÐºÑ†Ð¸Ð¸, ÐºÐ¾Ñ‚Ð¾Ñ€Ñ‹Ðµ Ð½ÑƒÐ¶Ð½Ñ‹ Ð’Ð°Ð¼"></div>
            </li>
            <li>
                <div class="slide-cont">
                    <h2 class="font slide-title">Ð­Ñ„Ñ„ÐµÐºÑ‚Ð¸Ð²Ð½Ð¾ÑÑ‚ÑŒ</h2>
                    <p style="text-align: justify;">Ð¡Ð¸ÑÑ‚ÐµÐ¼Ð° ÑÑƒÑ‰ÐµÑÑ‚Ð²ÐµÐ½Ð½Ð¾ Ð¾Ñ‚Ð»Ð¸Ñ‡Ð°ÐµÑ‚ÑÑ Ð¾Ñ‚ Ð°Ð½Ð°Ð»Ð¾Ð³Ð¾Ð², Ð½Ð¸Ð·ÐºÐ¾Ð¹ Ð¿Ð¾Ñ‚Ñ€ÐµÐ±Ð»ÑÐµÐ¼Ð¾ÑÑ‚ÑŒÑŽ ÑÐµÑ€Ð²ÐµÑ€Ð½Ñ‹Ñ… Ñ€ÐµÑÑƒÑ€ÑÐ¾Ð², Ñ‡Ñ‚Ð¾ Ð¿Ð¾Ð·Ð²Ð¾Ð»ÑÐµÑ‚ Ð¿Ð¾Ð»ÑƒÑ‡Ð¸Ñ‚ÑŒ Ð²Ñ‹ÑÐ¾ÐºÑƒÑŽ ÑÐºÐ¾Ñ€Ð¾ÑÑ‚ÑŒ Ñ€Ð°Ð±Ð¾Ñ‚Ñ‹ ÑÐ¸ÑÑ‚ÐµÐ¼Ñ‹. Ð¢ÐµÑ…Ð½Ð¸Ñ‡ÐµÑÐºÐ°Ñ Ð¿Ð¾Ð´Ð´ÐµÑ€Ð¶ÐºÐ° ÐºÐ»Ð¸ÐµÐ½Ñ‚Ð¾Ð² Ð¸ Ð²ÑÑ‚Ñ€Ð¾ÐµÐ½Ð½Ð°Ñ ÑÐ¸ÑÑ‚ÐµÐ¼Ð° Â«Ð”Ð¾ÐºÑƒÐ¼ÐµÐ½Ñ‚Ð°Ñ†Ð¸Ñ/Ð¡Ð¿Ñ€Ð°Ð²ÐºÐ°Â» Ð¿Ð¾ Ð¸ÑÐ¿Ð¾Ð»ÑŒÐ·Ð¾Ð²Ð°Ð½Ð¸ÑŽ Ð¸ Ð¿Ñ€Ð¸Ð¼ÐµÐ½ÐµÐ½Ð¸ÑŽ Ñ‚ÐµÑ… Ð¸Ð»Ð¸ Ð¸Ð½Ñ‹Ñ… Ð¼Ð¾Ð´ÑƒÐ»ÐµÐ¹ Ð½ÐµÐ¿Ð¾ÑÑ€ÐµÐ´ÑÑ‚Ð²ÐµÐ½Ð½Ð¾ Ð² Ð¿Ð°Ð½ÐµÐ»Ð¸ ÑƒÐ¿Ñ€Ð°Ð²Ð»ÐµÐ½Ð¸Ñ ÑÐ¸ÑÑ‚ÐµÐ¼Ð¾Ð¹, Ð¿Ð¾Ð¼Ð¾Ð³ÑƒÑ‚ Ð½Ð°Ð¹Ñ‚Ð¸ Ð¾Ñ‚Ð²ÐµÑ‚ Ð½Ð° Ð»ÑŽÐ±Ð¾Ð¹ Ð²Ð¾Ð·Ð½Ð¸ÐºÑˆÐ¸Ð¹ Ð²Ð¾Ð¿Ñ€Ð¾Ñ.</p>
                    <a href="index.php?name=content&amp;op=view&amp;id=16" title="ÐœÑ‹ Ð´ÐµÐ¹ÑÑ‚Ð²Ð¸Ñ‚ÐµÐ»ÑŒÐ½Ð¾ ÑƒÐ´ÐµÐ»ÑÐµÐ¼ ÐµÐ¹ Ð±Ð¾Ð»ÑŒÑˆÐ¾Ðµ Ð²Ð½Ð¸Ð¼Ð°Ð½Ð¸Ðµ" class="sl_but_blue"><b>ÐŸÐ¾Ð´Ñ€Ð¾Ð±Ð½ÐµÐµ</b></a>
                </div>
                <div class="slide-img"><img src="templates/lite/images/slide/slaed_3.jpg" alt="ÐœÑ‹ Ð´ÐµÐ¹ÑÑ‚Ð²Ð¸Ñ‚ÐµÐ»ÑŒÐ½Ð¾ ÑƒÐ´ÐµÐ»ÑÐµÐ¼ ÐµÐ¹ Ð±Ð¾Ð»ÑŒÑˆÐ¾Ðµ Ð²Ð½Ð¸Ð¼Ð°Ð½Ð¸Ðµ"></div>
            </li>
            <li>
                <div class="slide-cont">
                    <h2 class="font slide-title">Ð‘ÐµÐ·Ð¾Ð¿Ð°ÑÐ½Ð¾ÑÑ‚ÑŒ</h2>
                    <p style="text-align: justify;">ÐœÐ¾Ñ‰Ð½Ð°Ñ ÑÐ¸ÑÑ‚ÐµÐ¼Ð° Ð±ÐµÐ·Ð¾Ð¿Ð°ÑÐ½Ð¾ÑÑ‚Ð¸, Ð¸Ð¼ÐµÑŽÑ‰Ð°Ñ Ð³Ð¸Ð±ÐºÐ¸Ðµ Ð½Ð°ÑÑ‚Ñ€Ð¾Ð¹ÐºÐ¸, Ð¿Ð¾Ð·Ð²Ð¾Ð»ÑÐµÑ‚ Ð»Ð¾Ð³Ð¸Ñ€Ð¾Ð²Ð°Ð½Ð¸Ðµ Ð²ÑÐµÑ… Ð´ÐµÐ¹ÑÑ‚Ð²Ð¸Ð¹ Ð¿Ð¾ÑÐµÑ‚Ð¸Ñ‚ÐµÐ»ÐµÐ¹ ÑÐ°Ð¹Ñ‚Ð°. Ð¡ÑƒÑ‰ÐµÑÑ‚Ð²ÑƒÑŽÑ‚ Ð²Ð¾Ð·Ð¼Ð¾Ð¶Ð½Ð¾ÑÑ‚Ð¸ Ð°Ð²Ñ‚Ð¾Ð¼Ð°Ñ‚Ð¸Ñ‡ÐµÑÐºÐ¾Ð³Ð¾ Ð¾Ñ‚ÑÐ»ÐµÐ¶Ð¸Ð²Ð°Ð½Ð¸Ñ Ð²Ñ€ÐµÐ´Ð¾Ð½Ð¾ÑÐ½Ñ‹Ñ… Ð·Ð°Ð¿Ñ€Ð¾ÑÐ¾Ð², Ð¸Ð½ÑŠÐµÐºÑ†Ð¸Ð¹, Ð¿Ð¾Ð¿Ñ‹Ñ‚Ð¾Ðº Ð²Ð½ÐµÐ´Ñ€ÐµÐ½Ð¸Ð¹ Ð¸ Ð¿Ñ€Ð¾Ñ‡Ð¸Ñ… Ð½ÐµÑÐ°Ð½ÐºÑ†Ð¸Ð¾Ð½Ð¸Ñ€Ð¾Ð²Ð°Ð½Ð½Ñ‹Ñ… Ð´ÐµÐ¹ÑÑ‚Ð²Ð¸Ð¹. Ð’ Ð»ÑŽÐ±Ð¾Ð¼ ÑÐ»ÑƒÑ‡Ð°Ðµ ÑÐ¸ÑÑ‚ÐµÐ¼Ð° Ð¿Ñ€ÐµÐ´ÑƒÐ¿Ñ€ÐµÐ¶Ð´ÐµÐ½Ð¸Ð¹ Ð¸ Ð¾Ñ‚Ñ€Ð°Ð¶ÐµÐ½Ð¸Ð¹ Ð½Ð°Ð´Ñ‘Ð¶Ð½Ð¾ Ð¿Ñ€ÐµÐ´ÑƒÐ¿Ñ€ÐµÐ´Ð¸Ñ‚ Ð¸ Ð·Ð°Ñ‰Ð¸Ñ‚Ð¸Ñ‚ ÑÐ°Ð¹Ñ‚ Ð¾Ñ‚ Ð²ÑÐµÑ… Ð²Ð½ÐµÑˆÐ½Ð¸Ñ… ÑƒÐ³Ñ€Ð¾Ð·.</p>
                    <a href="index.php?name=content&amp;op=view&amp;id=17" title="Ð¡Ð¾Ð·Ð´Ð°Ð²Ð°Ð¹Ñ‚Ðµ ÑÐ°Ð¹Ñ‚Ñ‹, Ð½Ð°Ð´Ñ‘Ð¶Ð½Ð¾ Ð·Ð°ÐºÑ€Ñ‹Ñ‚Ñ‹Ðµ Ð¾Ñ‚ Ð·Ð»Ð¾ÑƒÐ¼Ñ‹ÑˆÐ»ÐµÐ½Ð½Ð¸ÐºÐ¾Ð²" class="sl_but_blue"><b>ÐŸÐ¾Ð´Ñ€Ð¾Ð±Ð½ÐµÐµ</b></a>
                </div>
                <div class="slide-img"><img src="templates/lite/images/slide/slaed_4.jpg" alt="Ð¡Ð¾Ð·Ð´Ð°Ð²Ð°Ð¹Ñ‚Ðµ ÑÐ°Ð¹Ñ‚Ñ‹, Ð½Ð°Ð´Ñ‘Ð¶Ð½Ð¾ Ð·Ð°ÐºÑ€Ñ‹Ñ‚Ñ‹Ðµ Ð¾Ñ‚ Ð·Ð»Ð¾ÑƒÐ¼Ñ‹ÑˆÐ»ÐµÐ½Ð½Ð¸ÐºÐ¾Ð²"></div>
            </li>
        </ul>
    </div>
    <div class="advantage">
        <div class="wrp clrfix">
            <ul class="big-icons">
                <li>
                    <b class="b_icon b_i_1"></b>
                    <h4 class="font"><a href="index.php?name=content&amp;op=view&amp;id=14" title="Ð£Ð¿Ñ€Ð°Ð²Ð»ÑÑ‚ÑŒ ÑÐ°Ð¹Ñ‚Ð¾Ð¼ Ñ‚Ð°ÐºÐ¶Ðµ Ð¿Ñ€Ð¾ÑÑ‚Ð¾, ÐºÐ°Ðº Ñ€Ð°Ð±Ð¾Ñ‚Ð°Ñ‚ÑŒ Ñ Ð´Ð¾ÐºÑƒÐ¼ÐµÐ½Ñ‚Ð°Ð¼Ð¸ Ð½Ð° ÐºÐ¾Ð¼Ð¿ÑŒÑŽÑ‚ÐµÑ€Ðµ">ÐŸÑ€Ð¾ÑÑ‚Ð¾Ñ‚Ð°</a></h4>
                    <p>Ð˜Ð½Ñ‚ÑƒÐ¸Ñ‚Ð¸Ð²Ð½Ð¾ Ð¿Ð¾Ð½ÑÑ‚Ð½Ñ‹Ð¹ Ð¸Ð½Ñ‚ÐµÑ€Ñ„ÐµÐ¹Ñ, Ð¿Ñ€Ð¾ÑÑ‚Ð¾Ðµ ÑƒÐ¿Ñ€Ð°Ð²Ð»ÐµÐ½Ð¸Ðµ ÑÐ°Ð¹Ñ‚Ð¾Ð¼, Ð»Ñ‘Ð³ÐºÐ°Ñ Ð¼Ð¾Ð´Ð¸Ñ„Ð¸ÐºÐ°Ñ†Ð¸Ñ.</p>
                    <a href="index.php?name=content&amp;op=view&amp;id=14" title="Ð£Ð¿Ñ€Ð°Ð²Ð»ÑÑ‚ÑŒ ÑÐ°Ð¹Ñ‚Ð¾Ð¼ Ñ‚Ð°ÐºÐ¶Ðµ Ð¿Ñ€Ð¾ÑÑ‚Ð¾, ÐºÐ°Ðº Ñ€Ð°Ð±Ð¾Ñ‚Ð°Ñ‚ÑŒ Ñ Ð´Ð¾ÐºÑƒÐ¼ÐµÐ½Ñ‚Ð°Ð¼Ð¸ Ð½Ð° ÐºÐ¾Ð¼Ð¿ÑŒÑŽÑ‚ÐµÑ€Ðµ" class="sl_but_read">Ð£Ð·Ð½Ð°Ñ‚ÑŒ Ð±Ð¾Ð»ÑŒÑˆÐµ</a>
                </li>
                <li>
                    <b class="b_icon b_i_2"></b>
                    <h4 class="font"><a href="index.php?name=content&amp;op=view&amp;id=15" title="Ð¡ÐºÐ¾Ñ€ÐµÐ¹ Ð²ÑÐµÐ³Ð¾, Ñƒ Ð½Ð°Ñ Ð¿Ñ€ÐµÐ´ÑƒÑÐ¼Ð¾Ñ‚Ñ€ÐµÐ½Ñ‹ Ñ„ÑƒÐ½ÐºÑ†Ð¸Ð¸, ÐºÐ¾Ñ‚Ð¾Ñ€Ñ‹Ðµ Ð½ÑƒÐ¶Ð½Ñ‹ Ð’Ð°Ð¼">Ð¤ÑƒÐ½ÐºÑ†Ð¸Ð¾Ð½Ð°Ð»ÑŒÐ½Ð¾ÑÑ‚ÑŒ</a></h4>
                    <p>ÐÐµÐ¾Ð³Ñ€Ð°Ð½Ð¸Ñ‡ÐµÐ½Ð½Ñ‹Ðµ Ð²Ð¾Ð·Ð¼Ð¾Ð¶Ð½Ð¾ÑÑ‚Ð¸, Ð¼Ð°ÑÑÐ° Ð´Ð¾Ð¿Ð¾Ð»Ð½ÐµÐ½Ð¸Ð¹, Ñ€Ð°ÑÑˆÐ¸Ñ€ÐµÐ½Ð¸Ðµ Ð»ÑŽÐ±Ñ‹Ð¼Ð¸ Ñ„ÑƒÐ½ÐºÑ†Ð¸ÑÐ¼Ð¸.</p>
                    <a href="index.php?name=content&amp;op=view&amp;id=15" title="Ð¡ÐºÐ¾Ñ€ÐµÐ¹ Ð²ÑÐµÐ³Ð¾, Ñƒ Ð½Ð°Ñ Ð¿Ñ€ÐµÐ´ÑƒÑÐ¼Ð¾Ñ‚Ñ€ÐµÐ½Ñ‹ Ñ„ÑƒÐ½ÐºÑ†Ð¸Ð¸, ÐºÐ¾Ñ‚Ð¾Ñ€Ñ‹Ðµ Ð½ÑƒÐ¶Ð½Ñ‹ Ð’Ð°Ð¼" class="sl_but_read">Ð£Ð·Ð½Ð°Ñ‚ÑŒ Ð±Ð¾Ð»ÑŒÑˆÐµ</a>
                </li>
                <li>
                    <b class="b_icon b_i_3"></b>
                    <h4 class="font"><a href="index.php?name=content&amp;op=view&amp;id=16" title="ÐœÑ‹ Ð´ÐµÐ¹ÑÑ‚Ð²Ð¸Ñ‚ÐµÐ»ÑŒÐ½Ð¾ ÑƒÐ´ÐµÐ»ÑÐµÐ¼ ÐµÐ¹ Ð±Ð¾Ð»ÑŒÑˆÐ¾Ðµ Ð²Ð½Ð¸Ð¼Ð°Ð½Ð¸Ðµ">Ð­Ñ„Ñ„ÐµÐºÑ‚Ð¸Ð²Ð½Ð¾ÑÑ‚ÑŒ</a></h4>
                    <p>ÐÐ¸Ð·ÐºÐ¾Ðµ Ð¿Ð¾Ñ‚Ñ€ÐµÐ±Ð»ÐµÐ½Ð¸Ðµ ÑÐµÑ€Ð²ÐµÑ€Ð½Ñ‹Ñ… Ñ€ÐµÑÑƒÑ€ÑÐ¾Ð², Ð²Ñ‹ÑÐ¾ÐºÐ°Ñ ÑÐºÐ¾Ñ€Ð¾ÑÑ‚ÑŒ Ñ€Ð°Ð±Ð¾Ñ‚Ñ‹ ÑÐ¸ÑÑ‚ÐµÐ¼Ñ‹.</p>
                    <a href="index.php?name=content&amp;op=view&amp;id=16" title="ÐœÑ‹ Ð´ÐµÐ¹ÑÑ‚Ð²Ð¸Ñ‚ÐµÐ»ÑŒÐ½Ð¾ ÑƒÐ´ÐµÐ»ÑÐµÐ¼ ÐµÐ¹ Ð±Ð¾Ð»ÑŒÑˆÐ¾Ðµ Ð²Ð½Ð¸Ð¼Ð°Ð½Ð¸Ðµ" class="sl_but_read">Ð£Ð·Ð½Ð°Ñ‚ÑŒ Ð±Ð¾Ð»ÑŒÑˆÐµ</a>
                </li>
                <li>
                    <b class="b_icon b_i_4"></b>
                    <h4 class="font"><a href="index.php?name=content&amp;op=view&amp;id=17" title="Ð¡Ð¾Ð·Ð´Ð°Ð²Ð°Ð¹Ñ‚Ðµ ÑÐ°Ð¹Ñ‚Ñ‹, Ð½Ð°Ð´Ñ‘Ð¶Ð½Ð¾ Ð·Ð°ÐºÑ€Ñ‹Ñ‚Ñ‹Ðµ Ð¾Ñ‚ Ð·Ð»Ð¾ÑƒÐ¼Ñ‹ÑˆÐ»ÐµÐ½Ð½Ð¸ÐºÐ¾Ð²">Ð‘ÐµÐ·Ð¾Ð¿Ð°ÑÐ½Ð¾ÑÑ‚ÑŒ</a></h4>
                    <p>ÐœÐ¾Ñ‰Ð½Ð°Ñ ÑÐ¸ÑÑ‚ÐµÐ¼Ð° Ð±ÐµÐ·Ð¾Ð¿Ð°ÑÐ½Ð¾ÑÑ‚Ð¸, Ð³Ð¸Ð±ÐºÐ¸Ðµ Ð½Ð°ÑÑ‚Ñ€Ð¾Ð¹ÐºÐ¸, Ð»Ð¾Ð³Ð¸Ñ€Ð¾Ð²Ð°Ð½Ð¸Ðµ Ð²ÑÐµÑ… Ð´ÐµÐ¹ÑÑ‚Ð²Ð¸Ð¹.</p>
                    <a href="index.php?name=content&amp;op=view&amp;id=17" title="Ð¡Ð¾Ð·Ð´Ð°Ð²Ð°Ð¹Ñ‚Ðµ ÑÐ°Ð¹Ñ‚Ñ‹, Ð½Ð°Ð´Ñ‘Ð¶Ð½Ð¾ Ð·Ð°ÐºÑ€Ñ‹Ñ‚Ñ‹Ðµ Ð¾Ñ‚ Ð·Ð»Ð¾ÑƒÐ¼Ñ‹ÑˆÐ»ÐµÐ½Ð½Ð¸ÐºÐ¾Ð²" class="sl_but_read">Ð£Ð·Ð½Ð°Ñ‚ÑŒ Ð±Ð¾Ð»ÑŒÑˆÐµ</a>
                </li>
            </ul>
        </div>
    </div>';
    $cont .= '<div id="site-carousel">
        <div class="wrp"><h3 class="font heading-1">ÐŸÑ€Ð¸Ð¼ÐµÑ€Ñ‹ Ð²Ð½ÐµÐ´Ñ€ÐµÐ½Ð¸Ð¹ ÑÐ¸ÑÑ‚ÐµÐ¼Ñ‹</h3></div>
        <div class="carousel">
            <ul id="slaed_sites">';
            $path = 'uploads/screens';
            $path2 = 'uploads/screens/thumb';
            $screens = [];
            if (is_dir($path2)) {
                $dir = opendir($path2);
                if ($dir !== false) {
                    while (false !== ($file = readdir($dir))) {
                        if ($file != '.' && $file != '..' && $file != 'index.html' && !is_dir($path2.'/'.$file)) $screens[] = $file;
                    }
                    closedir($dir);
                }
            }
            if ($screens) shuffle($screens);
            foreach ($screens as $val) {
                $sname = ucfirst(str_ireplace(['.gif', '.jpg', '.png', '.com', '.net', '.ru', '.ua', '.biz', '.info', '.su', '.in', '.org'], '', $val));
                $srat = round(filesize($path2.'/'.$val) / 20);
                $cont .= '<li>
                    <div class="site-item">
                        <a href="'.$path.'/'.$val.'" rel="alternate" title="'._SITE.': '.$sname.', '._RATING.': '.$srat.'" class="site-link">
                            <div class="site-img ico"><img src="'.$path2.'/'.$val.'" alt="Ð¡Ð°Ð¹Ñ‚: '.$sname.'" title="Ð¡Ð°Ð¹Ñ‚: '.$sname.'"></div>
                            <p class="site-title">'._SITE.': '.$sname.'</p>
                        </a>
                        <b class="ico rate_sites" title="'._RATING.'">'.$srat.'</b>
                    </div>
                </li>';
            }
            $cont .= '</ul>
            <span id="carousel-left"></span>
            <span id="carousel-right"></span>
        </div>
    </div>';
    $cont .= '<div class="wrp grid_1_2">
        <div class="grid col-block">
            <h3 title="'._NEWS.'" class="font heading-1">ÐÐ¾Ð²Ð¾ÑÑ‚Ð¸</h3>
            <ul class="ms-list">';
            $result = $db->getSqlQuery('SELECT s.id, s.cid, s.title, s.time, s.intro, c.title, c.intro FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB."_categories AS c ON (s.cid=c.id) WHERE s.time <= NOW() AND s.status != '0' ORDER BY s.time DESC LIMIT 3");
            while ([$id, $cid, $title, $time, $hometext, $ctitle, $cdesc] = $db->getSqlRow($result)) {
                $linkstrip = cutstr($title, 45);
                if (preg_match("#\[attach=(.*?)\s(.*?)\]#si", $hometext, $match)) {
                    $img = 'uploads/news/thumb/'.trim($match[1]);
                } else {
                    preg_match("#\[img=(.*?)\](.*)\[/img\]#si", $hometext, $match);
                    $img = isset($match[2]) ? trim($match[2]) : (isset($match[1]) ? trim($match[1]) : '');
                }
                $img = ($img) ? (file_exists($img) ? $img : img_find('logos/slaed_logo_60x60.png')) : img_find('logos/slaed_logo_60x60.png');
                $ntext = cutstr(htmlspecialchars(trim(strip_tags(filterReplaceText(filterMarkdown($hometext, 'news', false), 'news'))), ENT_QUOTES), 60);
                $cont .= '<li><a href="index.php?name=news&amp;op=view&amp;id='.$id.'" title="'.$title.'" class="ms-img" style="background-image: url('.$img.');"></a>
                    <b><a href="index.php?name=news&amp;op=view&amp;id='.$id.'" title="'.$title.'">'.$linkstrip.'</a></b>
                    <p>'.$ntext.'</p>
                    <ul class="grey">
                        <li><a href="index.php?name=news&amp;cat='.$cid.'" title="'.$cdesc.'" class="sl_cat"><b>'.$ctitle.'</b></a></li>
                        <li title="'._DATE.'" class="ico i_date">'.format_time($time, _TIMESTRING).'</li>
                    </ul>
                </li>';
            }
            $cont .= '</ul>
            <a href="index.php?name=news" title="Ð’ÑÐµ Ð½Ð¾Ð²Ð¾ÑÑ‚Ð¸" class="sl_but_read">Ð’ÑÐµ Ð½Ð¾Ð²Ð¾ÑÑ‚Ð¸</a>
        </div>
        <div class="grid col-block">
            <h3 title="'._FILES.'" class="font heading-1">Ð¤Ð°Ð¹Ð»Ñ‹</h3>
            <ul class="ms-list">';
            $result = $db->getSqlQuery('SELECT s.id, s.cid, s.title, s.intro, s.time, c.title, c.intro FROM '.PREFIX_DB.'_files AS s LEFT JOIN '.PREFIX_DB."_categories AS c ON (s.cid=c.id) WHERE s.time <= NOW() AND s.status != '0' ORDER BY s.time DESC LIMIT 3");
            while ([$id, $cid, $title, $hometext, $time, $ctitle, $cdesc] = $db->getSqlRow($result)) {
                $linkstrip = cutstr($title, 45);
                if (preg_match("#\[attach=(.*?)\s(.*?)\]#si", $hometext, $match)) {
                    $img = 'uploads/files/thumb/'.trim($match[1]);
                } else {
                    preg_match("#\[img=(.*?)\](.*)\[/img\]#si", $hometext, $match);
                    $img = isset($match[2]) ? trim($match[2]) : (isset($match[1]) ? trim($match[1]) : '');
                }
                $img = ($img) ? (file_exists($img) ? $img : img_find('logos/slaed_logo_60x60.png')) : img_find('logos/slaed_logo_60x60.png');
                $ntext = cutstr(htmlspecialchars(trim(strip_tags(filterReplaceText(filterMarkdown($hometext, 'files', false), 'files'))), ENT_QUOTES), 60);
                $cont .= '<li><a href="index.php?name=files&amp;op=view&amp;id='.$id.'" title="'.$title.'" class="ms-img" style="background-image: url('.$img.');"></a>
                    <b><a href="index.php?name=files&amp;op=view&amp;id='.$id.'" title="'.$title.'">'.$linkstrip.'</a></b>
                    <p>'.$ntext.'</p>
                    <ul class="grey">
                        <li><a href="index.php?name=files&amp;cat='.$cid.'" title="'.$cdesc.'" class="sl_cat"><b>'.$ctitle.'</b></a></li>
                        <li title="'._DATE.'" class="ico i_date">'.format_time($time, _TIMESTRING).'</li>
                    </ul>
                </li>';
            }
            $cont .= '</ul>
            <a href="index.php?name=files" title="Ð’ÑÐµ Ñ„Ð°Ð¹Ð»Ñ‹" class="sl_but_read">Ð’ÑÐµ Ñ„Ð°Ð¹Ð»Ñ‹</a>
        </div>
    </div>';
    echo $cont;
    setFoot();
}

switch($op) {
    default: main(); break;
}
