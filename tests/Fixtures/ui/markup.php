<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# A fixture for the markup scan: one class attribute folded out of two tokens, one inline style,
# and three strings that only look like markup - a regular expression, a feed element, and a sentence

function getFixtureRow(string $name): string {
    $html = '<di'.'v class="sl-row">'.$name.'</div>';
    $find = '#<script\b[^>]*>#is';
    $feed = '<url><loc>'.$name.'</loc></url>';
    $word = 'a class of its own';
    return $html.$find.$feed.$word;
}

function getFixtureCell(string $name): string {
    return '<td style="width:40px">'.$name.'</td>';
}
