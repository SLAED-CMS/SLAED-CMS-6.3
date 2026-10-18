<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('_MASTER_RELEASES', 'Випуски');
define('_MASTER_SITES', 'Сайти');
define('_MASTER_NOTES', 'Сповіщення');
define('_MASTER_KEYS', 'Ключі');
define('_MASTER_INBOX', 'Чекає в inbox/');
define('_MASTER_ACCEPT', 'Прийняти');
define('_MASTER_ACCEPTED', 'Прийнято');
define('_MASTER_CHANGED', 'Змінено');
define('_MASTER_EXPIRES', 'Маніфест спливає');
define('_MASTER_EXPWARN', 'Маніфест %1$s спливає %2$s: перепідпишіть його (php tools/sign.php --refresh %1$s), опублікуйте й прийміть знову.');
define('_MASTER_WAIT', 'Чекає');
define('_MASTER_BYOWNER', 'поставив власник');
define('_MASTER_BYAUTO', 'автопауза: %d зривів, %d вдалих');
define('_MASTER_TOBETA', 'У beta');
define('_MASTER_TOSTABLE', 'У stable');
define('_MASTER_SHARE', 'Частка викатки');
define('_MASTER_SHAREDESC', 'Частка сайтів, яким пропонується випуск %s, від 0 до 100. Сайт потрапляє до неї за своїм ідентифікатором і версією, тож у кожного випуску свої перші сайти.');
define('_MASTER_DONE', 'Прийнято: %s');
define('_MASTER_REFUSED', 'Відхилено: %s');
define('_MASTER_RSIGN', 'підпис не збігається з чинним ключем');
define('_MASTER_REXP', 'термін маніфесту сплив');
define('_MASTER_RZIP', 'архів не збігається з підписаним розміром або SHA-256');
define('_MASTER_RMISS', 'файлів немає');
define('_MASTER_RWRITE', 'не вдалося записати файли');
define('_MASTER_RFORM', 'маніфест неповний або іншого формату, версії чи каналу');
define('_MASTER_SID', 'Ідентифікатор');
define('_MASTER_DOMAIN', 'Домен');
define('_MASTER_NOHOST', 'не повідомлено');
define('_MASTER_CHAN', 'Канал');
define('_MASTER_EXT', 'Розширення');
define('_MASTER_ERRS', 'Помилки PHP / бази');
define('_MASTER_FIRST', 'Перший візит');
define('_MASTER_LAST', 'Останній візит');
define('_MASTER_SILENT', 'Мовчать понад %d днів');
define('_MASTER_FAILS', 'Зірвані встановлення');
define('_MASTER_STEP', 'крок %d');
define('_MASTER_STEPS', 'Крок');
define('_MASTER_COUNT', 'Сайтів');
define('_MASTER_NOSOD', 'Без sodium');
define('_MASTER_NOZIP', 'Без zip');
define('_MASTER_LEVEL', 'Рівень');
define('_MASTER_TO', 'Адресат');
define('_MASTER_TOALL', 'усі сайти');
define('_MASTER_RANGE', 'Версії');
define('_MASTER_UNTIL', 'Термін');
define('_MASTER_NOEND', 'без терміну');
define('_MASTER_PRIMARY', 'Основний ключ');
define('_MASTER_RECOVERY', 'Ключ відновлення');
define('_MASTER_FACTORY', 'заводський');
define('_MASTER_INFORCE', 'чинний');
define('_MASTER_PRINT', 'Відбиток');
define('_MASTER_KEYERR', 'Заява про ключі в inbox/keys/ не пройшла перевірку (%s); чинні ключі до неї.');
define('_MASTER_KEEPSITE', 'Зберігати сайти, що мовчать, днів');
define('_MASTER_KEEPSTAT', 'Зберігати денні підсумки, днів');
define('_MASTER_LIMIT', 'Поріг автопаузи (частка зірваних встановлень у відсотках)');
define('_MASTER_JOBS', 'Збереження один раз додає до планувальника завдання masterclean (денні підсумки й очищення) і masterwatch (автопауза, щогодини).');
define('_MASTER', 'Майстер');
