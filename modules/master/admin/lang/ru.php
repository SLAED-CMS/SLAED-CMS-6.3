<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('_MASTER_RELEASES', 'Выпуски');
define('_MASTER_SITES', 'Сайты');
define('_MASTER_NOTES', 'Оповещения');
define('_MASTER_KEYS', 'Ключи');
define('_MASTER_INBOX', 'Ждёт в inbox/');
define('_MASTER_ACCEPT', 'Принять');
define('_MASTER_ACCEPTED', 'Принят');
define('_MASTER_CHANGED', 'Изменён');
define('_MASTER_EXPIRES', 'Манифест истекает');
define('_MASTER_EXPWARN', 'Манифест %1$s истекает %2$s: переподпишите его (php tools/sign.php --refresh %1$s), опубликуйте и примите заново.');
define('_MASTER_WAIT', 'Ждёт');
define('_MASTER_BYOWNER', 'поставил владелец');
define('_MASTER_BYAUTO', 'автопауза: %d срывов, %d удачных');
define('_MASTER_TOBETA', 'В beta');
define('_MASTER_TOSTABLE', 'В stable');
define('_MASTER_SHARE', 'Доля выкатки');
define('_MASTER_SHAREDESC', 'Доля сайтов, которым предлагается выпуск %s, от 0 до 100. Сайт попадает в неё по своему идентификатору и версии, так что у каждого выпуска свои первые сайты.');
define('_MASTER_DONE', 'Принято: %s');
define('_MASTER_REFUSED', 'Отклонено: %s');
define('_MASTER_RSIGN', 'подпись не сходится с ключом в силе');
define('_MASTER_REXP', 'срок манифеста истёк');
define('_MASTER_RZIP', 'архив не совпадает с подписанным размером или SHA-256');
define('_MASTER_RMISS', 'файлов нет');
define('_MASTER_RWRITE', 'не удалось записать файлы');
define('_MASTER_RFORM', 'манифест неполон или другого формата, версии или канала');
define('_MASTER_SID', 'Идентификатор');
define('_MASTER_DOMAIN', 'Домен');
define('_MASTER_NOHOST', 'не сообщён');
define('_MASTER_CHAN', 'Канал');
define('_MASTER_EXT', 'Расширения');
define('_MASTER_ERRS', 'Ошибки PHP / базы');
define('_MASTER_FIRST', 'Первый визит');
define('_MASTER_LAST', 'Последний визит');
define('_MASTER_SILENT', 'Молчат больше %d дней');
define('_MASTER_FAILS', 'Сорванные установки');
define('_MASTER_STEP', 'шаг %d');
define('_MASTER_STEPS', 'Шаг');
define('_MASTER_COUNT', 'Сайтов');
define('_MASTER_NOSOD', 'Без sodium');
define('_MASTER_NOZIP', 'Без zip');
define('_MASTER_LEVEL', 'Уровень');
define('_MASTER_TO', 'Адресат');
define('_MASTER_TOALL', 'все сайты');
define('_MASTER_RANGE', 'Версии');
define('_MASTER_UNTIL', 'Срок');
define('_MASTER_NOEND', 'без срока');
define('_MASTER_PRIMARY', 'Основной ключ');
define('_MASTER_RECOVERY', 'Ключ восстановления');
define('_MASTER_FACTORY', 'заводской');
define('_MASTER_INFORCE', 'в силе');
define('_MASTER_PRINT', 'Отпечаток');
define('_MASTER_KEYERR', 'Заявление о ключах в inbox/keys/ не прошло проверку (%s); в силе ключи до него.');
define('_MASTER_KEEPSITE', 'Хранить молчащие сайты, дней');
define('_MASTER_KEEPSTAT', 'Хранить дневные итоги, дней');
define('_MASTER_LIMIT', 'Порог автопаузы (доля сорванных установок в процентах)');
define('_MASTER_JOBS', 'Сохранение один раз добавляет в планировщик задачи masterclean (дневные итоги и очистка) и masterwatch (автопауза, раз в час).');
define('_MASTER', 'Мастер');
