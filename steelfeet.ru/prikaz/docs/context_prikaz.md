**Наследие CRM** (осталось от выделения, для Приказа не нужно, подлежит удалению при рефакторинге):
`lib/workflow.php`, `lib/documents.php`, `lib/views/manager.php`, `lib/views/university.php`, `lib/views/supervisor.php`, `lib/views/documents.php`, `lib/views/document_card.php`, `assets/documents.css`, `assets/workflow_docs.js`, `assets/supervisor.js`.

---

## 3. Роли

| Роль | Что делает |
|---|---|
| `admin` | Единственный, кто создаёт пользователей. Управляет учебным процессом (годы, семестры, группы, предметы, план, расписание, КТП, праздники), уроками, проектами, привязкой родителей. Видит аудит |
| `teacher` | Ведёт журнал (оценки, заметки), меняет фазы проектов, видит сводку и активность родителей |
| `student` | Видит свои оценки, заметки преподавателя, свои проекты и фазы |
| `parent` | Видит журнал и проекты только своего ребёнка. Факт захода фиксируется |

Самостоятельной регистрации нет.

---

## 4. Что работает (актуальное состояние)

### Учебный процесс

Раздел админки «Учебный процесс» с подменю: «Учебный год», «Группы», «Предметы и темы».

**Учебный год** (`academic_years`):
- Название (`2025/2026`), даты начала и окончания
- Флаг `is_current` — только один текущий, UNIQUE INDEX
- При создании автоматически создаются семестры «1 семестр» и «2 семестр»

**Семестры** (`semesters`):
- Привязаны к учебному году
- Поля: `name`, `is_practice` (0/1), `ord`
- UNIQUE `(academic_year_id, name)`
- В разделе «Учебный год» под таблицей годов — блок **«Семестры и праздничные дни»**
- В карточке семестра: заголовок `2.1rem`, метка «практика», подпись «праздников: N»
- CRUD через `admin_semester_save` / `admin_semester_delete`

**Праздничные дни** (`holidays`):
- Привязаны к семестру
- Поля: `date` (ISO `YYYY-MM-DD`), `name` (опционально)
- UNIQUE `(semester_id, date)`
- Управление внутри карточки семестра
- Валидация: дата в диапазоне учебного года
- **Влияют на КТП**: см. `ktp_holiday_losses` в `lib/ktp.php`. Часы, попавшие на праздник, вычитаются из «Фактически часов» КТП

**Группы** (`groups`):
- Привязаны к учебному году
- Поля: `name`, `curator`, `notes`
- UNIQUE `(academic_year_id, name)`
- Название с суффиксом: «46 гр.»

**Предметы** (`subjects`):
- Привязаны к семестру и группе
- Поля: `short_name`, `full_name`, `hours`
- UNIQUE `(semester_id, group_id, short_name)`
- Группа и семестр должны быть из одного учебного года
- Форма редактирования — **inline в отдельной строке под предметом** (как у тем), открывается кнопкой «Редактировать», селект года → группы → семестра, JS фильтрует по году

**Темы** (`curriculum`):
- Привязаны к предмету
- Поля: `ord`, `topic`
- UNIQUE `(subject_id, ord)`
- **Inline-редактирование в ячейке «Тема»**: `<details class="pzk-topic-edit">`, summary = сам текст темы, при раскрытии — форма с `<textarea rows="3">` во всю ширину
- **«+ Добавить ниже»** — маленькая ссылка под текстом, тоже `<details>` с формой insert_below
- Все операции — AJAX (`admin_curriculum_save`, `admin_curriculum_delete`, `admin_curriculum_insert_below`, `admin_curriculum_bulk_add`)
- `curriculum_insert_below` в `lib/curriculum.php` — двухфазный сдвиг `ord`, вставляет тему после выбранной

**КТП** (`ktp_entries`):
- **Одна тема = один блок**
- Блок = все занятия одного вида в один день
- **Групповые** — разбиваются на пары (1,2), (3,4)… Каждая пара = отдельный блок, занимает столько номеров п/п, сколько уроков в паре. 1 урок → «N», 2 урока → «N-M»
- **Подгрупповые** — все sub1 и sub2 одного дня = один блок. `slots = max(sub1, sub2)`. `tariff = sub1 + sub2`
- Номера п/п сквозные по семестру. Праздники пропускаются

**Шапка КТП:**
- **Часов по учебному плану** — `subjects.hours`
- **По тарификации** — сумма `tariff` по занятым блокам (аудиторные часы с учётом подгрупп: два урока подгрупп = 2 часа)
- **В КТП** — сумма `slots` по занятым блокам (количество учебных пар)
- **Фактически часов** — план минус часы, попавшие на праздники
- **Праздники:** список `04.11 — 2 ч., 08.11 — 4 ч.`
- **✓ совпадает** — при `план == tariff`
- **Подсветка зелёным** — `.pzk-ktp__match-value` при совпадении «В КТП» и «Фактически часов»

**<summary> КТП** — `📅 КТП (<?= $sKtpSlots ?> ч.)`. Это количество учебных пар (а не аудиторных часов).

**Sticky:**
- `.pzk-ktp__head` — `position: sticky; top: 0; z-index: 3`
- `.pzk-ktp-table thead th` — `position: sticky; top: var(--ktp-head-h, 42px); z-index: 2`
- `--ktp-head-h` вычисляется в `_scripts.php` через `ResizeObserver` на `.pzk-ktp__head`
- На экранах ≤1200px sticky отключается (position: static), включается `overflow-x: auto`

**Дата через rowspan** — блоки одного дня сливаются в одну ячейку `rowspan=N`.

**Функции KTP** (`lib/ktp.php`):
- `ktp_lesson_blocks($subjectId)` — список блоков семестра
- `ktp_holiday_losses($subjectId)` — `['2025-11-04' => 2, ...]`
- `ktp_layout($subjectId, $pdo, $blocks)` — привязка тем к блокам
- `ktp_tariff_from_layout($layout)` — sum(tariff) по занятым
- `ktp_slots_from_layout($layout)` — sum(slots) по занятым
- `ktp_generate`, `ktp_add_more`, `ktp_delete`, `ktp_reindex`
- `ktp_render_block($subjectId)` — HTML-блок
- `ktp_kind_label($kind)` — «Лекция» / «Практикум»

### AJAX

Все операции с КТП и темами — без перезагрузки.

- Клиент: `_scripts.php`, `ajaxSubmit(form, block)` — `fetch` с `FormData`, `ajax=1`, `Accept: application/json`. Ответ `{ok, html}` — клиент заменяет блок по `outerHTML`.
- Сервер: `actions.php` при `wants_json()` возвращает `json_response(['ok' => true, 'html' => ...])` или `['ok' => false, 'error' => ...]`.
- `json_response()` в `utils.php` — очищает все `ob_*`-буферы перед `header()`, пишет мусор из буфера в `error_log`.
- `redirect()` в `utils.php` — тоже очищает буферы перед `header('Location')`.
- `index.php` в начале открывает `ob_start()`.

### Журнал
Сетка «студенты × занятия», клик по ячейке → модалка с оценкой и заметкой.

### Проекты
Реестр проектов с фазами (Выбор темы → Работа → Предзащита → Защита), типы, участники, история фаз, ссылка на репозиторий.

### Админка
Пользователи, студенты, родители, уроки, проекты, аудит.

Разбита на файлы: диспетчер `lib/views/admin.php`, секции в `lib/views/admin/*.php`, общий CSS в `admin/_styles.php`, общий JS в `admin/_scripts.php`. Партиалы: `_topics_block.php`, `_ktp_block.php`.

### Встроенная справка
Плавающая «?» в правом нижнем углу. В скрипте — null-guard на `fab`/`panel`/`closeBtn`, иначе `Cannot read properties of null`.

### Страница входа
Славянская стилистика: буквица «П», Ruslan Display, золото и тёмно-красный.

---

## 5. Безопасность

| Механизм | Реализация |
|---|---|
| Пароли | `password_hash(PASSWORD_DEFAULT)`, авторехэш |
| CSRF | Токен в сессии на всех POST, `hash_equals` |
| Сессия | `session_regenerate_id`, `httponly`, `samesite=Lax`, `secure` при HTTPS |
| SQL | Только подготовленные запросы |
| XSS | Все выводы через `e()` |
| Аудит | Все действия с IP |
| БД | Вне webroot; `.htaccess` блокирует `lib/`, `db/` |
| Буферы | `redirect()` и `json_response()` вызывают `ob_end_clean()` перед `header`. `index.php` открывает `ob_start()` в начале |

**Не сделано:** восстановление пароля по email, rate limiting, 2FA, формальное согласие на ПДн.

---

## 6. Известные ловушки

1. **SQLite, а не MySQL/Postgres.** `GROUP_CONCAT` с **одинарными** кавычками. `COLLATE NOCASE`. `PRAGMA foreign_keys = ON`.
2. **`init.php` и `db.php` смотрят на один файл.** Абсолютный путь через `__DIR__`.
3. **Миграция `subjects` сносит `curriculum` и `schedule_entries`.** При обнаружении старой схемы — `DROP TABLE` без переноса.
4. **`DROP TABLE subjects` при включённых FK даёт `database table is locked`.** `PRAGMA foreign_keys = OFF` **вне** транзакции → `DROP` → `CREATE` → `PRAGMA foreign_keys = ON` → `PRAGMA foreign_key_check`.
5. **`headers already sent` при `http_response_code()`.** Решение: `if (!headers_sent()) http_response_code(500)`.
6. **BOM в PHP-файле ломает `<?php`.** UTF-8 без BOM. Проверка: `head -c 20 lib/curriculum.php | xxd` — первые 3 байта `3c 3f 70`.
7. **`actions.php` не должен заканчиваться на `?>`.** Перевод строки после `?>` попадает в буфер.
8. **Opcache на shared-хостинге.** После правки `actions.php` / `data.php` — перезапуск PHP-FPM или временное переименование.
9. **Стили виджета справки** — внутри `help_widget.php`.
10. **Не все POST-формы защищены CSRF.** Проверить покрытие.
11. **`schedule_*()`, `parse_topic_lines()`, `topic_key()`** — в `lib/schedule.php` / `lib/utils.php`.
12. **`data.php` для `$adminSubjects` обязан выбирать `ay.id AS academic_year_id`.**
13. **Хелперы секций локальные.** `$holidayChip`, `$groupWithSubgroup`, `$typesMeta`.
14. **`nav.php` — `$academicSections` включает `subjects`.**
15. **`curriculum_render_block()` должна быть подключена.** `lib/curriculum.php` в `bootstrap.php`. Иначе `admin_curriculum_*` и `data.php` (при рендере `_topics_block.php`) падают с `Call to undefined function`.
16. **`ktp_render_block()`, `ktp_lesson_blocks()`, `ktp_holiday_losses()`** — в `lib/ktp.php`. Должны быть подключены в `bootstrap.php`.
17. **JSON-ответ ломается от warning/notice/BOM.** В `index.php` — `ob_start()` в начале. `json_response()` и `redirect()` вызывают `ob_end_clean()`. Если после сохранения темы в ответе приходит `---` или `<br><b>Fatal error</b>...` — искать незакрытый `require`, BOM или `?>` в конце `actions.php`.
18. **`json_response()` пишет мусор в error_log.** Строка `[Prikaz json_response] garbage in buffer: ...` покажет источник (BOM, warning, перевод строки).
19. **`ktp_lesson_blocks` строит блоки по расписанию.** Групповые — пары (1,2), (3,4)…; подгрупповые — один блок на весь день (slots = max(sub1, sub2), tariff = sub1 + sub2).
20. **`ktp_holiday_losses` считает часы, попавшие на праздники.** Если праздник в семестре есть, но у предмета в этот день занятий нет — день не попадает в losses.
21. **Sticky-шапка КТП работает только при `overflow: visible` на предках.** `.pzk-table-wrap--subjects { overflow: visible; }`. На экранах ≤1200px — sticky отключается, включается `overflow-x: auto`.
22. **`--ktp-head-h`** — CSS-переменная, вычисляется `_scripts.php` через `ResizeObserver`. Если изменить высоту `.pzk-ktp__head`, JS её пересчитает сам.
23. **`ktp_render_block()` и `curriculum_render_block()` вызывают `require __DIR__ . '/views/admin/_ktp_block.php'` и `_topics_block.php` соответственно.** Партиалы используют `e()`, `csrf_field()`, `ktp_kind_label()`, `date()` — все должны быть загружены до вызова.
24. **Формы внутри `.pzk-subject-ktp` и `.pzk-subject-topics` обрабатываются AJAX.** Новые формы в этих блоках не требуют собственного JS: `ajaxSubmit` в `_scripts.php` подхватывает `submit` через делегирование.
25. **Inline-редактирование — `<details>` без видимого summary.** `summary { display: none; }`. Открывается только кнопкой «Редактировать» через `data-open-subject-edit` / `data-open-topic-edit`.
26. **При AJAX-замене блока** (`block.replaceWith(newBlock)`) нужно заново вызывать `initKtpBlock(newBlock)` для КТП — иначе sticky-шапка перестанет измеряться. `_scripts.php` это уже делает.

---

## 7. Конвенции работы

- **Все файлы — целиком**, без фрагментов «замените этот блок».
- **SQLite, а не MySQL/Postgres.** `GROUP_CONCAT` с одинарными кавычками, `COLLATE NOCASE`.
- **Стиль:** славянский на странице входа, минимализм и пастельные приглушённые цвета внутри.
- **Терминология:** «критерии оценивания», не «рубрика».
- **Тон ответов:** прямой, без подыгрывания. Обращение к пользователю — мужской род, о себе — женский.
- **Перед выдачей файла целиком — проверить, что не забыл текущие сущности** (семестры, праздники, расписание, предметы, подгруппы, КТП) и список ловушек.
- **Админка разбита на файлы.** Секции — `lib/views/admin/<section>.php`. Общие стили — `_styles.php`, общий JS — `_scripts.php`. Партиалы с сущностями — `_<name>_block.php`.
- **README и context_prikaz.md выдаются с обёрткой из четырёх обратных кавычек**, чтобы внутренние ` ```bash ` / ` ```php ` не ломали скачивание.

---

## 8. Открытые задачи

1. **Связь студентов с группами** — FK `group_id` вместо свободной строки.
2. **Экспорт журнала** — XLSX.
3. **Тесты** — не написаны.
4. **Нагрузочные прогоны** — не проводились.
5. **Ревизия наследия CRM** — удалить/переосмыслить.
6. **Печать расписания** — не сделано.
7. **Кэш `ktp_lesson_blocks`** — при 50+ предметах на странице.

---

## 9. Что было в этом чате (для контекста эволюции)

1. **Разбиение `index.php`** на модули.
2. **LMS → Приказ.**
3. **БД вынесена вне webroot** в `prikaz-data/`.
4. **Убрана авторизация через Keycloak.**
5. **Страница входа в славянском стиле.**
6. **Сущности:** учебный год → семестр → группа → предмет → учебный план → расписание → праздники.
7. **Расписание** — одно на семестр, в ячейке предмет + тип.
8. **Праздники** — в карточке семестра.
9. **Заголовок семестра** `2.1rem`.
10. **Форма редактирования предмета** — добавлен селект семестра.
11. **Расписание: одна ячейка = одна запись.**
12. **Расписание: пустые ячейки — не ошибка.**
13. **Расписание: подгруппы в названии группы.**
14. **Рефакторинг `admin.php`** — на секции.
15. **Актуализация README** — v.0.1.4.0.
16. **Добавлено КТП** — таблица `ktp_entries`, `lib/ktp.php`, UI в `admin/subjects.php`.
17. **КТП переработано**: одна тема = один блок; групповые — пары, подгруппы — один блок; tariff/slots; номера п/п диапазоном; дата rowspan; sticky-шапка.
18. **Шапка КТП**: «Часов по учебному плану / По тарификации / В КТП / Фактически часов» + список праздников; подсветка зелёным при совпадении «В КТП» и «Фактически».
19. **AJAX для КТП и тем**: `wants_json`/`json_response`, `ajaxSubmit` в `_scripts.php`, `admin_ktp_*` и `admin_curriculum_*` возвращают `{ok, html}`.
20. **`lib/curriculum.php`** (новый), `_topics_block.php`, `_ktp_block.php` (партиалы).
21. **`admin_curriculum_insert_below`** — «Добавить ниже» с двухфазным сдвигом `ord`.
22. **Inline-редактирование тем**: `<details>` в ячейке «Тема», `<textarea rows="3">` под текстом, «+ Добавить ниже» — ссылка под текстом.
23. **Inline-редактирование предмета**: `<details>` в отдельной строке под предметом, форма с полями в сетке.
24. **`ob_start()` в `index.php`**, `ob_end_clean()` в `redirect` и `json_response` — фикс «Unexpected token ---» и «Call to undefined function curriculum_render_block».
25. **`help_widget.php`**: null-guard на `fab`/`panel`/`closeBtn` — фикс «Cannot read properties of null».
26. **Актуализация README** — v.0.1.5.0.

---

## 10. Быстрый старт

```bash
# Инициализация БД
php prikaz/db/init.php

# Сброс
php prikaz/db/init.php --reset

# Если config.php не находится
PRIKAZ_CONFIG=/полный/путь/prikaz-data/config.php php prikaz/db/init.php
```

Учётные данные администратора — в `prikaz-data/config.php`.

Страница входа: `https://steelfeet.ru/prikaz/index.php?page=login`

---

## 11. Что делать в новом чате

Если задача «поправить / добавить что-то»:

1. Проверить, что нужные файлы присланы целиком. Правки по админке — конкретная секция `lib/views/admin/<section>.php` или партиал `_topics_block.php` / `_ktp_block.php`. Общая логика — `actions.php`, `data.php`, `ktp.php`, `curriculum.php`.
2. Если чего-то не хватает — попросить фрагмент.
3. Выдать файл целиком с правками.
4. Если правится одна секция — не трогать диспетчер и `_styles.php` / `_scripts.php`, если правка не касается именно их.

Если задача «показать что-то на демо» — помнить:
- праздники в учебном году,
- семестры в карточках,
- расписание на семестр,
- одну запись на ячейку,
- подгруппы в названии группы,
- КТП с блоками и tariff/slots,
- «Фактически часов» с вычетом праздников.

Если задача «доработать функционал» — см. раздел «Открытые задачи».

Перед выдачей файла целиком — пробежаться по разделу «Известные ловушки» (все 26) и по текущему состоянию сущностей: `semesters`, `holidays`, `schedule_entries`, `subjects`, `curriculum`, `ktp_entries`. Свериться, что хелперы секций не потерялись при правках.

Если задача «сгенерируй README / context» — выдавать в обёртке из **четырёх** обратных кавычек (````), чтобы внутренние ` ```bash ` / ` ```php ` не ломали скачивание файла.