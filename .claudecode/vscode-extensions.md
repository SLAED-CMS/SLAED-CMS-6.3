# Рекомендуемые расширения VSCode для SLAED CMS

## PHP Development

1. **PHP Intelephense** (bmewburn.vscode-intelephense-client)
   - Автодополнение кода
   - Навигация по классам и функциям
   - Рефакторинг
   - Проверка синтаксиса

2. **PHP Debug** (xdebug.php-debug)
   - Отладка PHP кода через Xdebug
   - Breakpoints, step debugging
   - Просмотр переменных

3. **PHP DocBlocker** (neilbrayfield.php-docblocker)
   - Автоматическая генерация PHPDoc комментариев

## Code Quality

4. **phpcs** (shevaua.phpcs)
   - PHP CodeSniffer интеграция
   - Проверка стандартов кодирования (PSR-12)

5. **EditorConfig** (editorconfig.editorconfig)
   - Единообразное форматирование
   - UTF-8, 4 пробела, max 120 символов

## Git & Version Control

6. **GitLens** (eamodio.gitlens)
   - Расширенная работа с Git
   - Blame annotations
   - История коммитов

## Database

7. **SQLTools** (mtxr.sqltools)
   - Работа с MySQL/MariaDB из VSCode
   - Запросы, просмотр таблиц

8. **SQLTools MySQL/MariaDB** (mtxr.sqltools-driver-mysql)
   - Драйвер для SQLTools

## Productivity

9. **Better Comments** (aaron-bond.better-comments)
   - Подсветка разных типов комментариев

10. **Path Intellisense** (christian-kohler.path-intellisense)
    - Автодополнение путей к файлам

## Установка:

Через VSCode:
1. Ctrl+Shift+X → Extensions
2. Поиск по имени
3. Install

Или через командную строку:
```bash
code --install-extension bmewburn.vscode-intelephense-client
code --install-extension xdebug.php-debug
code --install-extension neilbrayfield.php-docblocker
code --install-extension shevaua.phpcs
code --install-extension editorconfig.editorconfig
code --install-extension eamodio.gitlens
code --install-extension mtxr.sqltools
code --install-extension mtxr.sqltools-driver-mysql
code --install-extension aaron-bond.better-comments
code --install-extension christian-kohler.path-intellisense
```
