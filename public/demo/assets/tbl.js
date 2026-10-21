/* Table series: the dress of the list table of admin.php?name=comments, and nothing else. Every variant draws the same
   screen with the theme's own markup — the filter in the head, the tabs, the pager, the view switch, the table
   .sl-table-list-sort with its tip, status button, dial and tick, the action row under it — and changes only the CSS
   of the table, in its own <style> under .tb-skin. "Как сейчас" on the stand desk takes .tb-skin off, so the theme of
   today stands in the same place for comparison. The rows are an invented corpus with the fields of the panel; the
   addresses come from the documentation ranges (RFC 5737, RFC 3849) */
const TBL_MODS = {
  news: 'Новости', files: 'Каталог файлов', voting: 'Опросы', docs: 'Документация', help: 'Помощь', faq: 'Вопросы и ответы', links: 'Каталог сайтов',
};
const TBL_COUNTRY = { de: 'Germany', fr: 'France', kz: 'Kazakhstan', pl: 'Poland', ua: 'Ukraine', us: 'United States', lv: 'Latvia', cz: 'Czechia' };
const TBL_ROWS = [
  { id: 20702, text: 'Где скачать бета-версию? На странице загрузок пока только 6.3.', mod: 'files', by: 'gleb_k', date: '2026-10-20T08:31:47+02:00', cid: 5072, ip: '198.51.100.207', cc: 'pl', on: 0 },
  { id: 20701, text: 'Обновил тему по инструкции, всё встало с первого раза. Тёмная схема особенно удалась.', mod: 'news', by: 'kostya_web', date: '2026-10-17T09:02:41+02:00', cid: 3920, ip: '198.51.100.23', cc: 'pl', on: 1 },
  { id: 20700, text: 'Отличная новость!', mod: 'news', by: 'Lena K.', date: '2026-10-16T18:40:12+02:00', cid: 3920, ip: '203.0.113.31', cc: 'ua', on: 1 },
  { id: 20699, text: 'mira.dev, финальная сборка выйдет после генеральной репетиции обновления, дату объявим в новостях.', mod: 'news', by: 'Администратор', date: '2026-10-15T12:25:30+02:00', cid: 3920, ip: '192.0.2.8', cc: 'de', on: 1 },
  { id: 20698, text: 'Подскажите, когда выйдет финальная сборка восьмой версии? Жду, чтобы перенести два сайта.', mod: 'news', by: 'mira.dev', date: '2026-10-14T21:14:08+02:00', cid: 3920, ip: '192.0.2.14', cc: 'de', on: 1 },
  { id: 20697, text: 'tim_ostrov, лимит задаёт nginx: директива client_max_body_size в конфигурации сайта. В панели его поменять нельзя.', mod: 'files', by: 'Администратор', date: '2026-10-13T08:10:44+02:00', cid: 5071, ip: '192.0.2.8', cc: 'de', on: 1 },
  { id: 20696, text: 'Не загружается архив больше 20 МБ — сервер отвечает ошибкой 413. Где поменять лимит?', mod: 'files', by: 'tim_ostrov', date: '2026-10-12T22:51:03+02:00', cid: 5071, ip: '2001:db8::5', cc: 'kz', on: 1 },
  { id: 20695, text: 'Голосую за каталог сайтов с превью — без картинок он выглядит пустым.', mod: 'voting', by: 'artem.pro', date: '2026-10-09T15:33:19+02:00', cid: 28, ip: '198.51.100.47', cc: 'lv', on: 1 },
  { id: 20694, text: 'Купите дешёвые ссылки для продвижения сайта, пишите в личку!!!', mod: 'links', by: 'seo-best-2026', date: '2026-10-08T03:12:55+02:00', cid: 4352, ip: '203.0.113.99', cc: 'us', on: 0 },
  { id: 20693, text: 'olga_reads, спасибо, пример добавили в раздел «Ленты и подписки».', mod: 'docs', by: 'Администратор', date: '2026-10-06T10:02:13+02:00', cid: 3981, ip: '192.0.2.8', cc: 'de', on: 1 },
  { id: 20692, text: 'В документации к RSS не хватает примера с несколькими лентами. Можно дополнить?', mod: 'docs', by: 'olga_reads', date: '2026-10-05T11:47:26+02:00', cid: 3981, ip: '192.0.2.62', cc: 'cz', on: 1 },
  { id: 20691, text: 'Тоже интересует перенос, у меня phpBB. Есть готовый скрипт или только через импорт CSV?', mod: 'help', by: 'denis_m', date: '2026-09-30T07:45:02+02:00', cid: 4190, ip: '203.0.113.12', cc: 'pl', on: 1 },
  { id: 20690, text: 'Как перенести пользователей со старого форума? Их около трёх тысяч, вручную не вариант.', mod: 'help', by: 'Nikolai', date: '2026-09-29T19:20:37+02:00', cid: 4190, ip: '198.51.100.71', cc: 'ua', on: 1 },
  { id: 20689, text: 'Спасибо, всё работает!', mod: 'files', by: 'yulia', date: '2026-09-24T14:18:50+02:00', cid: 5068, ip: '192.0.2.85', cc: 'fr', on: 1 },
  { id: 20688, text: 'vadim_s, удалите папку кэша ассетов в storage — значки собираются заново при первом заходе.', mod: 'faq', by: 'Администратор', date: '2026-09-20T18:03:57+02:00', cid: 4051, ip: '192.0.2.8', cc: 'de', on: 1 },
  { id: 20687, text: 'После обновления пропали значки у ссылок в меню. Кэш чистил, не помогло.', mod: 'faq', by: 'vadim_s', date: '2026-09-20T16:29:11+02:00', cid: 4051, ip: '198.51.100.90', cc: 'kz', on: 1 },
  { id: 20686, text: 'Не понял, зачем убрали старый редактор. Он был привычнее, а новый требует учиться заново...', mod: 'news', by: 'old_school', date: '2026-09-14T23:56:40+02:00', cid: 3919, ip: '203.0.113.140', cc: 'ua', on: 0 },
  { id: 20685, text: 'Markdown намного удобнее, особенно для длинных статей. Предпросмотр рядом — то, что нужно.', mod: 'news', by: 'mira.dev', date: '2026-09-13T10:12:08+02:00', cid: 3919, ip: '192.0.2.14', cc: 'de', on: 1 },
  { id: 20684, text: 'Есть ли ограничение на число вариантов в опросе? Хочу сделать двенадцать.', mod: 'voting', by: 'sergey_z', date: '2026-09-08T13:40:26+02:00', cid: 27, ip: '198.51.100.112', cc: 'lv', on: 1 },
  { id: 20683, text: 'Pavel, архив перезалит, проверили на трёх браузерах.', mod: 'files', by: 'Администратор', date: '2026-09-02T12:44:19+02:00', cid: 5066, ip: '192.0.2.8', cc: 'de', on: 1 },
  { id: 20682, text: 'Скачал модуль галереи, а архив пустой. Перезалейте, пожалуйста.', mod: 'files', by: 'Pavel', date: '2026-09-02T09:05:33+02:00', cid: 5066, ip: '203.0.113.168', cc: 'cz', on: 1 },
  { id: 20681, text: 'Хороший каталог, нашёл пару полезных сервисов.', mod: 'links', by: 'anna_l', date: '2026-08-28T17:21:45+02:00', cid: 4351, ip: '192.0.2.133', cc: 'pl', on: 1 },
  { id: 20680, text: 'test', mod: 'help', by: 'Администратор', date: '2026-08-21T20:00:02+02:00', cid: 4188, ip: '192.0.2.8', cc: 'de', on: 0 },
  { id: 20679, text: 'Почему в статистике переходов столько записей с пустым источником? Это боты?', mod: 'faq', by: 'roman.v', date: '2026-08-17T08:37:59+02:00', cid: 4049, ip: '198.51.100.154', cc: 'ua', on: 1 },
  { id: 20678, text: 'kostya_web, включите в «Конфигурациях» комментариев проверку перед публикацией — новые будут ждать на вкладке «Ждут проверки».', mod: 'help', by: 'Администратор', date: '2026-08-12T09:15:36+02:00', cid: 4187, ip: '192.0.2.8', cc: 'de', on: 1 },
  { id: 20677, text: 'Подскажите, как сделать, чтобы новые комментарии сначала проходили проверку? Спам замучил.', mod: 'help', by: 'kostya_web', date: '2026-08-11T21:49:14+02:00', cid: 4187, ip: '198.51.100.23', cc: 'pl', on: 1 },
  { id: 20676, text: 'Шаблон для портфолио очень нужен. Сейчас приходится собирать из новостей.', mod: 'voting', by: 'design_kate', date: '2026-08-04T15:02:47+02:00', cid: 28, ip: '203.0.113.201', cc: 'fr', on: 1 },
  { id: 20675, text: 'Где можно скачать языковой пакет на казахский? Хочу помочь с переводом.', mod: 'docs', by: 'aidar', date: '2026-07-22T11:33:28+02:00', cid: 3979, ip: '2001:db8::1c', cc: 'kz', on: 1 },
  { id: 20674, text: 'Сайт после переноса открывается, но админка выдаёт пустую страницу. PHP 8.4, логи пустые.', mod: 'help', by: 'igor_n', date: '2026-07-15T18:54:10+02:00', cid: 4186, ip: '198.51.100.199', cc: 'de', on: 0 },
  { id: 20673, text: 'Отличный релиз, спасибо команде!', mod: 'news', by: 'artem.pro', date: '2026-07-09T12:06:51+02:00', cid: 3918, ip: '198.51.100.47', cc: 'lv', on: 1 },
  { id: 20672, text: 'Ссылка на зеркало не открывается уже неделю, исправьте.', mod: 'links', by: 'anonym', date: '2026-07-01T06:41:22+02:00', cid: 4350, ip: '203.0.113.230', cc: 'us', on: 0 },
];

/* The menu of the panel as the stand draws it: the block "Администратор" and the block "Модули" */
const TBL_MENU = [
  ['admins', 'Администрация', 'person-gear'], ['blocks', 'Блоки и баннеры', 'grid-3x3-gap'], ['categories', 'Категории', 'folder'],
  ['comments', 'Комментарии', 'chat-dots'], ['config', 'Конфигурации', 'gear'], ['database', 'База данных', 'database'],
  ['editor', 'Редактор', 'pencil-square'], ['favorites', 'Фавориты', 'star'], ['fields', 'Дополнительные поля', 'input-cursor-text'],
  ['groups', 'Группы', 'people-fill'], ['lang', 'Редактор языков', 'translate'], ['messages', 'Сообщения', 'envelope'],
  ['modules', 'Модули', 'puzzle'], ['monitor', 'Мониторинг', 'display'], ['newsletter', 'Рассылка', 'send'],
  ['privat', 'Личные сообщения', 'incognito'], ['ratings', 'Рейтинги', 'star-half'], ['referers', 'Переходы', 'box-arrow-in-right'],
  ['replace', 'Замена слов', 'arrow-left-right'], ['scheduler', 'Планировщик', 'clock-history'], ['security', 'Безопасность', 'shield-lock'],
  ['statistic', 'Статистика', 'graph-up'], ['template', 'Шаблон', 'palette'], ['uploads', 'Загрузки', 'cloud-arrow-up'],
];
const TBL_MENU_MODS = [
  ['account', 'Пользователи', 'people'], ['changelog', 'Журнал изменений', 'journal-text'], ['contact', 'Обратная связь', 'envelope-open'],
  ['forum', 'Форум', 'chat-square-text'], ['node', 'Материалы', 'collection'], ['rss', 'RSS каналы', 'rss'], ['search', 'Поиск', 'search'],
  ['sitemap', 'Карта сайта', 'diagram-3'], ['voting', 'Опросы', 'bar-chart'],
];

const tblRel = new Intl.RelativeTimeFormat('ru', { numeric: 'auto' });

function getTblEsc(s) {
  return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

function getTblAgo(d) {
  let v = (new Date(d).getTime() - Date.now()) / 1000;
  for (const [n, unit] of [[60, 'second'], [60, 'minute'], [24, 'hour'], [30.44, 'day'], [12, 'month'], [Infinity, 'year']]) {
    if (Math.abs(v) < n) return tblRel.format(Math.round(v), unit);
    v /= n;
  }
  return '';
}

function getTblStamp(d) {
  const t = new Date(d);
  const p = (n) => String(n).padStart(2, '0');
  return `${p(t.getDate())}.${p(t.getMonth() + 1)}.${t.getFullYear()} ${p(t.getHours())}:${p(t.getMinutes())}:${p(t.getSeconds())}`;
}

/* One row as the panel prints it: the same cells, classes and labels, so a variant dresses exactly what the theme gets */
function getTblRow(r) {
  const mod = `${TBL_MODS[r.mod]} - ${r.mod}`;
  const text = getTblEsc(r.text);
  const land = TBL_COUNTRY[r.cc] || r.cc;
  const stat = r.on
    ? `<button type="button" class="sl-stat" title="Деактивировать" aria-label="Статус: Комментарий ${r.id}" aria-pressed="true"><span class="sl-status-active"></span></button>`
    : `<button type="button" class="sl-stat" title="Активировать" aria-label="Статус: Комментарий ${r.id}" aria-pressed="false"><span class="sl-status-inactive"></span></button>`;
  return `<tr data-sl-row="Комментарий ${r.id}">
<td class="sl-col-id" data-label="№">${r.id}</td>
<td class="sl-cell-ellipsis" title="${text}" data-label="Комментарий"><div class="sl-float sl-tip"><button type="button" aria-label="Подробности: Комментарий ${r.id}" aria-describedby="sl-tip-${r.id}"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></button><div class="sl-float-panel" role="tooltip" id="sl-tip-${r.id}"><dl><dt>Модуль</dt><dd>${mod}</dd><dt>CID</dt><dd>${r.cid}</dd><dt>Дата</dt><dd>${getTblStamp(r.date)}</dd><dt>IP Адрес</dt><dd><span><span title="${land}" class="sl-geo-flag"><img src="../templates/admin/images/flags/${r.cc}.svg" alt="${land}" title="${land}"></span><a href="https://whatismyipaddress.com/ip/${r.ip}" target="_blank" rel="noopener noreferrer">${r.ip}</a></span></dd></dl></div></div> <span class="sl-cell-text">${text}</span></td>
<td class="sl-col-module sl-cell-ellipsis" title="${mod}" data-label="Модуль">${mod}</td>
<td class="sl-col-author" data-label="Разместил"><a href="/account/view?uname=${encodeURIComponent(r.by)}" title="Персональная информация">${getTblEsc(r.by)}</a></td>
<td class="sl-col-date" data-label="Дата" data-sort="${r.date}"><time datetime="${r.date}" title="${getTblStamp(r.date)}">${getTblAgo(r.date)}</time></td>
<td class="sl-col-status" data-label="Статус">${stat}</td>
<td class="sl-col-actions" data-label="Опции"><span class="sl-dial"><button class="sl-dial-toggle sl-but-mini" type="button" title="Опции" aria-expanded="false"><i class="bi bi-three-dots"></i></button><a class="sl-dial-item sl-but-mini" href="#" title="Подробный просмотр"><i class="bi bi-eye"></i></a><a class="sl-dial-item sl-but-mini" href="#" title="Ответить на сайте"><i class="bi bi-reply"></i></a><a class="sl-dial-item sl-but-mini" href="#" title="Полное редактирование"><i class="bi bi-pencil"></i></a><button class="sl-dial-item sl-but-mini" type="button" title="Удалить"><i class="bi bi-trash"></i></button></span></td>
<td class="sl-col-check"><input type="checkbox" class="sl-check" data-sl-check-item value="${r.id}" aria-label="Выбрать комментарий ${r.id}"></td>
</tr>`;
}

function getTblPager(side) {
  const pages = [1, 2, 3, 4, 5, 6, 7, 8].map((n) => n === 1
    ? '<span title="1" aria-current="page" class="sl-seg-cell sl-is-active">1</span>'
    : `<a href="#" title="${n}" class="sl-seg-cell ${n === 2 ? 'sl-pager-near' : 'sl-pager-far'}">${n}</a>`).join('');
  const jump = side === 'top'
    ? '<a href="#sl-list-end" title="Вниз" aria-label="Вниз" class="sl-but-mini"><i class="bi bi-arrow-down" aria-hidden="true"></i></a>'
    : '<a href="#top" title="Наверх" aria-label="Наверх" class="sl-but-mini"><i class="bi bi-arrow-up" aria-hidden="true"></i></a>';
  return `<nav class="sl-pager" aria-label="Страницы"><div class="sl-pager-main"><div class="sl-pager-nav">
    <span title="Назад" class="sl-but-mini sl-is-muted"><i class="bi bi-chevron-left" aria-hidden="true"></i></span>
    <span class="sl-seg sl-seg-pager" data-sl-knob="pager"><span class="sl-seg-knob" data-sl-knob-mark="" aria-hidden="true"></span><span class="sl-seg-run sl-pager-pages">${pages}<span class="sl-seg-cell sl-is-muted" aria-hidden="true">…</span><a href="#" title="337" class="sl-seg-cell">337</a></span></span>
    <a href="#" title="Вперед" aria-label="Вперед" rel="next" class="sl-but-mini"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
  </div><div class="sl-pager-side"><span class="sl-pager-sep" aria-hidden="true"></span>${jump}</div></div>
  <p class="sl-pager-info">Страница <b>1 из 337</b><span class="sl-pager-range"> · Показаны 1–25</span> · Всего <b>8411</b></p></nav>`;
}

function getTblShell() {
  const rows = TBL_ROWS.slice(0, 25);
  const item = ([key, name, icon]) => `<div class="sl-menu-list-item"><a href="../admin.php?name=${key}" title="${name}"${key === 'comments' ? ' aria-current="page"' : ''}><i class="bi bi-${icon}"></i> ${name}</a></div>`;
  const block = (id, title, icon, items) => `<div class="sl-block-sidebar"><h3 title="Свёртывание / Развёртывание" data-sl-toggle-control="sl-block-${id}" data-sl-toggle-effect="slide" data-sl-toggle-duration="500"><b><i class="bi bi-${icon}"></i>${title}</b><i class="bi bi-arrows-collapse sl-panel-toggle sl-panel-toggle-open" aria-hidden="true"></i><i class="bi bi-arrows-expand sl-panel-toggle sl-panel-toggle-closed" aria-hidden="true"></i></h3>` +
    `<div id="sl-block-${id}" class="sl-block-sidebar-content" data-sl-toggle="sl-block-${id}">${items.map(item).join('')}</div></div>`;
  const tool = [['../admin.php', 'Главная', 'house-door'], ['../admin.php?name=blocks', 'Блоки и баннеры', 'grid-3x3-gap'], ['../admin.php?name=modules', 'Модули', 'gpu-card'],
    ['../admin.php?name=categories', 'Категории', 'folder'], ['/', 'Сайт', 'globe2', 1], ['../index.php?name=account', 'Профиль', 'person'], ['../admin.php?op=logout', 'Выход', 'box-arrow-right', 1]]
    .map(([href, name, icon, split]) => `<li${split ? ' class="sl-split-item"' : ''}><a href="${href}" title="${name}"><i class="bi bi-${icon}"></i> <b>${name}</b></a></li>`).join('');
  const tab = (name, href, on) => `<li class="sl-tabs-item"><a href="${href}" title="${name}" class="sl-tabs-link${on ? ' sl-is-active' : ''}">${name}</a></li>`;
  const mods = Object.keys(TBL_MODS).sort().map((k) => `<option value="${k}">${TBL_MODS[k]} - ${k}</option>`).join('');
  const seg = [['standard', 'Стандартный', 'table'], ['full', 'Полный', 'text-wrap'], ['compact', 'Компактный', 'list']]
    .map(([k, n, i], at) => `<button type="button" class="sl-seg-cell${at ? '' : ' sl-is-active'}" data-sl-density="${k}" title="${n}" aria-label="${n}" aria-pressed="${!at}"><i class="bi bi-${i}" aria-hidden="true"></i></button>`).join('');
  return `<div class="sl-wrapper sl-admin-shell" id="top"><div class="sl-wraptop"></div><div class="sl-wrapmid">
    <div class="sl-header sl-admin-header">
      <div class="sl-admin-brand"><h1><a href="//slaed.net" target="_blank" title="Название сайта"><img src="../templates/admin/images/logos/slaed-logo-wordmark-gradient-blue.svg" alt="Название сайта" title="Название сайта"></a></h1></div>
      <div class="sl-admin-toolbar"><div class="sl-toolbar"><div><ul class="sl-reset">${tool}</ul></div></div></div>
    </div>
    <div class="sl-admin-main">
      <aside class="sl-admin-sidebar">${block(1, 'Администратор', 'person-gear', TBL_MENU)}${block(2, 'Модули', 'people', TBL_MENU_MODS)}</aside>
      <main class="sl-admin-content"><div class="sl-admin-content-inner">
        <div class="sl-box">
          <div class="sl-module-head">
            <div class="sl-module-head-icon"><i class="bi bi-chat-dots" title="Комментарии"></i></div>
            <div class="sl-module-head-body">
              <h1 class="sl-module-head-title">Комментарии</h1>
              <div class="sl-module-head-subtitle"><form class="sl-searchbox sl-filter" action="#" role="search" aria-label="Поиск">
                <span class="sl-filter-field"><label for="tb-mod">Модуль</label><select id="tb-mod" class="sl-select"><option value="" selected>Все</option>${mods}</select></span>
                <span class="sl-filter-field"><label for="tb-in" id="tb-in-label">Поиск</label><select id="tb-in" class="sl-select"><option value="1">№</option><option value="2" selected>Комментарий</option><option value="3">Разместил</option><option value="4">Модуль</option><option value="5">IP Адрес</option></select><input type="search" id="tb-q" maxlength="60" aria-labelledby="tb-in-label tb-in"></span>
                <button type="submit" class="sl-but-blue">OK</button>
              </form></div>
            </div>
          </div>
          <ul class="sl-tabs-nav sl-tabs-nav-main">${tab('Главная', '#', 1)}${tab('Ждут проверки', '#')}${tab('Конфигурации', '../admin.php?name=comments&amp;op=config')}${tab('Справка', '../admin.php?name=comments&amp;op=info')}${tab('Журнал действий', '../admin.php?name=security&amp;op=logview&amp;file=admin&amp;modul=comments')}</ul>
        </div>
        <div class="sl-box tb-desk" role="group" aria-label="Пульт стенда">
          <span class="tb-desk-cap">Пульт стенда</span>
          <button type="button" class="sl-but" data-tb-skin="on" aria-pressed="true">Вариант</button>
          <button type="button" class="sl-but" data-tb-skin="off" aria-pressed="false">Как сейчас</button>
          <span class="tb-desk-hint">Меняется только оформление таблицы; разметка, колонки и действия — как на живой странице.</span>
        </div>
        <div class="sl-box sl-table-shell tb-skin" data-tb-shell>
          <div class="sl-list-head">
            <div class="sl-list-head-pager">${getTblPager('top')}</div>
            <div class="sl-list-view"><span class="sl-seg sl-seg-view" role="group" aria-label="Вид списка">${seg}</span></div>
          </div>
          <table class="sl-table-list-sort sl-table-fixed" data-sl-density="standard">
<thead><tr>
<th class="sl-col-id sl-sort">№</th>
<th class="sl-cell-ellipsis sl-sort">Комментарий</th>
<th class="sl-cell-ellipsis sl-sort">Модуль</th>
<th class="sl-col-author sl-sort">Разместил</th>
<th class="sl-col-date sl-sort">Дата</th>
<th class="sl-col-status">Статус</th>
<th class="sl-col-actions">Опции</th>
<th class="sl-col-check" data-sl-check-all><input type="checkbox" class="sl-check" data-sl-check-item aria-label="Отметить все" title="Отметить все"></th>
</tr></thead>
<tbody>${rows.map(getTblRow).join('')}</tbody>
          </table>
          <div class="sl-div-actions"><div class="sl-list-bottom" id="sl-list-end">
            <div class="sl-list-bottom-pager">${getTblPager('bottom')}</div>
            <div class="sl-list-bottom-actions">
              <span class="sl-list-picked"><span>Отмечено: <span data-tb-picked>0</span></span></span>
              <div class="sl-div-form"><label class="sl-visually-hidden" for="tb-typ">Действие с отмеченными</label><select id="tb-typ" class="sl-select"><option value="a1">Активировать</option><option value="a0">Деактивировать</option><option value="d">Удалить</option></select><button type="button" class="sl-but-blue" data-tb-go disabled>OK</button></div>
            </div>
          </div></div>
        </div>
        <div class="sl-box" data-demo-about></div>
      </div></main>
    </div>
    <div class="sl-footer sl-admin-footer"><div class="sl-copyright">Генерация: 0.251 сек. и 15 запросов к базе данных за 0.005 сек.<br><a href="https://slaed.net" title="SLAED CMS" target="_blank" rel="noopener noreferrer">SLAED CMS</a> © 2005-2026 Eduard Laas. Released under MIT License.</div></div>
  </div><div class="sl-wrapfoot"></div></div>`;
}

/* The tick count, the status press, the view switch without the server and the desk switch between variant and theme */
function setTblPicked() {
  const shell = document.querySelector('[data-tb-shell]');
  const all = shell.querySelectorAll('tbody [data-sl-check-item]');
  const hit = shell.querySelectorAll('tbody [data-sl-check-item]:checked').length;
  const head = shell.querySelector('thead [data-sl-check-item]');
  head.checked = hit > 0 && hit === all.length;
  head.indeterminate = hit > 0 && hit < all.length;
  shell.querySelector('[data-tb-picked]').textContent = hit;
  shell.querySelector('[data-tb-go]').disabled = hit === 0;
}

function initTbl() {
  document.body.insertAdjacentHTML('afterbegin', getTblShell());
  const shell = document.querySelector('[data-tb-shell]');
  const table = shell.querySelector('table');
  // The compare page opens a variant twice: ?skin=off stands for the theme of today, ?focus brings the table to the top
  const params = new URLSearchParams(location.search);
  if (params.get('skin') === 'off') {
    shell.classList.remove('tb-skin');
    document.querySelectorAll('[data-tb-skin]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.tbSkin === 'off')));
  }
  if (params.has('focus')) window.addEventListener('load', () => window.scrollTo(0, shell.getBoundingClientRect().top + window.scrollY - 8));

  shell.addEventListener('change', (e) => {
    const box = e.target.closest('[data-sl-check-item]');
    if (!box) return;
    if (box.closest('thead')) shell.querySelectorAll('tbody [data-sl-check-item]').forEach((one) => { one.checked = box.checked; });
    setTblPicked();
  });

  // Captured before the panel's own handler, which would store the view on a server the stand does not have
  document.addEventListener('click', (e) => {
    const view = e.target.closest('.sl-seg-view [data-sl-density]');
    if (!view) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    table.setAttribute('data-sl-density', view.dataset.slDensity);
    view.parentElement.querySelectorAll('[data-sl-density]').forEach((cell) => {
      const on = cell === view;
      cell.classList.toggle('sl-is-active', on);
      cell.setAttribute('aria-pressed', String(on));
    });
  }, true);

  document.addEventListener('click', (e) => {
    const stat = e.target.closest('.sl-stat');
    if (stat) {
      const on = stat.getAttribute('aria-pressed') !== 'true';
      stat.setAttribute('aria-pressed', String(on));
      stat.title = on ? 'Деактивировать' : 'Активировать';
      stat.firstElementChild.className = on ? 'sl-status-active' : 'sl-status-inactive';
      return;
    }
    const skin = e.target.closest('[data-tb-skin]');
    if (skin) {
      shell.classList.toggle('tb-skin', skin.dataset.tbSkin === 'on');
      document.querySelectorAll('[data-tb-skin]').forEach((b) => b.setAttribute('aria-pressed', String(b === skin)));
      return;
    }
    if (e.target.closest('[data-tb-shell] a[href="#"], .sl-filter [type="submit"], .sl-tabs-link[href="#"]')) e.preventDefault();
  });
}

initTbl();
