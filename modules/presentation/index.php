<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function presentation(): void {
    setHead([
        'title' => 'Простота Функциональность Эффективность Безопасность',
        'desc' => 'Система управления содержимым сайта, простая в использовании и настройке, имеющая при этом высокий уровень безопасности, высокую скорость работы, а также практически неограниченный потенциал в решении вопроса расширения функциональности.',
    ]);
    setFoot();
}

switch ($op) {
    default: presentation(); break;
}
