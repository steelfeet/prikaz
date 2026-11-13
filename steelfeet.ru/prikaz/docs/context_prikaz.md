**Наследие CRM** (не нужно, удалить при рефакторинге):
`lib/workflow.php`, `lib/documents.php`, `lib/views/manager.php`, `lib/views/university.php`, `lib/views/supervisor.php`, `lib/views/documents.php`, `lib/views/document_card.php`, `assets/documents.css`, `assets/workflow_docs.js`, `assets/supervisor.js`.

---

## 3. Роли

| Роль | Что делает |
|---|---|
| `admin` | Создаёт пользователей и студентов. Учебный процесс (годы, семестры, группы, предметы, темы, расписание, КТП, праздники), уроки, проекты, родители. Аудит. Пароль — из `config.php` |
| `teacher` | Журнал (оценки, заметки), фазы проектов, сводка, активность родителей |
| `student` | Свои оценки, заметки, проекты, фазы. Личный кабинет: email, VK, Telegram, пароль (не логин/ФИО) |
| `parent` | Журнал и проекты своего ребёнка. Факт захода фиксируется |

Самостоятельной регистрации нет.

---

## 4. Что работает

### Учебный процесс

Меню: «Учебный год», «Группы», «Предметы и темы».

**Учебный год** (`academic_years`): `name`, `start_date`, `end_date`, `is_current` (UNIQUE INDEX). При создании — семестры «1 семестр» и «2 семестр».

**Семестры** (`semesters`): `academic_year_id`, `name`, `is_practice`, `ord`. Карточки в «Учебном году». Удаление каскадом.

**Праздники** (`holidays`): `semester_id`, `date`, `name`. UNIQUE `(semester_id, date)`. Валидация по диапазону года. **Влияют на КТП** (вычитаются из «Фактически часов»).

**Группы** (`groups`): `academic_year_id`, `name`, `curator`, `notes`. UNIQUE `(academic_year_id, name)`. **Inline-редактирование** в отдельной строке под группой, кнопка «Редактировать» открывает `<details class="pzk-group-edit">`.

**Предметы** (`subjects`): `semester_id`, `group_id`, `short_name`, `full_name`, `hours`. UNIQUE `(semester_id, group_id, short_name)`. **Inline-редактирование** под строкой (`<details class="pzk-subject-edit">`). Селекты год → группа → семестр с JS.

**Темы** (`curriculum`): `subject_id`, `ord`, `topic`. UNIQUE `(subject_id, ord)`. **Inline**: `<details class="pzk-topic-edit">` в ячейке «Тема», `<textarea rows="3">` под текстом. «+ Добавить ниже» — маленькая ссылка под текстом, `curriculum_insert_below` (двухфазный сдвиг `ord`). Всё AJAX.

### КТП

Одна тема = один блок.

- **Групповой блок** — пара (1,2), (3,4)… 1 урок → «N», 2 → «N-M».
- **Подгрупповой блок** — все sub1/sub2 дня = один блок. Слоты = `max(sub1, sub2)`, tariff = `sub1 + sub2`.
- Порядок в дне — по `min lesson_num`.
- Праздники пропускаются.

**Функции** (`lib/ktp.php`): `ktp_lesson_blocks`, `ktp_holiday_losses`, `ktp_layout`, `ktp_tariff_from_layout`, `ktp_slots_from_layout`, `ktp_generate`, `ktp_add_more`, `ktp_delete`, `ktp_reindex`, `ktp_render_block`, `ktp_kind_label`.

**Шапка:** «Часов по учебному плану / По тарификации / В КТП / Фактически часов». Плюс список праздников. Плюс фильтр «Все / Лекция / Практикум» левее «Перегенерировать».

- **Совпадает (`✓`)** — `план == tariff`.
- **Зелёная подсветка** — `В КТП == Фактически часов` (`.pzk-ktp__match-value`).

**Столбец «№ по типу»** появляется при фильтре, содержит сквозную нумерацию видимых уроков этого типа (с учётом слотов).

**Sticky:** шапка (`top: 0`) и `thead th` (`top: var(--ktp-head-h)`), переменная от `ResizeObserver`. При ≤1200px — отключается, включается `overflow-x`.

**Даты через `rowspan`** при фильтре «Все».

**Все операции — AJAX** (`+ Ещё одно`, `Удалить`, `Перегенерировать`, `Сгенерировать`).

### Студенты

Раздел `admin/students.php`.

**Поля в БД** (`students` + `users`): `last_name`, `first_name`, `middle_name`, `login` (= `students.alias` = `users.login`), `email` (`users.email`), `password` (`users.password_hash`), `vk_url`, `telegram`, `group_id`, `group_name` (для совместимости), `full_name` (для совместимости).

**Фильтры:** учебный год (`student_year`, по умолчанию текущий), группа (`student_group`). SQL с `WHERE g.academic_year_id = :y AND s.group_id = :g`.

**Добавление** — форма с полями: Фамилия, Имя, Отчество, Логин, Пароль, Email, VK, Telegram, год + группа. Год → группа с JS.

**Inline-редактирование** под строкой: Фамилия, Имя, Отчество, Логин, Email, **Пароль (для восстановления доступа, пустой — не менять)**, VK, Telegram, Группа. **Учебного года нет**. Все группы всех годов в селекте.

**Удаление** — каскад через `users` (снесёт `students`, `grades`, `project_students`, `parent_student`).

**Массовое добавление** — `Фамилия Имя Отчество, логин` (по строке). Группа обязательна, из групп текущего года (селект `max-width:180px`). Пароль по умолчанию — `1111`, email — `<логин>@mail.ru`. Сервер: `groupId > 0` и группа из текущего года, иначе отказ.

**Функции** (`lib/students.php`): `students_create`, `students_update` (опц. пароль), `students_delete`, `students_bulk_create($parsed, $groupId)`, `parse_student_lines`, `student_split_full_name`, `student_join_full_name`, `student_validate_login`, `student_validate_email`, `students_group_options`, `student_profile_save`.

**Обработчики** (`lib/actions/students.php`): `admin_student_create`, `admin_student_update`, `admin_student_delete`, `admin_student_bulk_create`, `student_profile_save`, `admin_parent_link`, `admin_parent_unlink`.

### Личный кабинет

`profile.php` — работает для всех ролей.

- **Студент:** «Личный кабинет», поля Email, VK, Telegram, Текущий пароль, Новый пароль, Повтор. Поля VK/Telegram — только для студентов. Три поля пароля — на второй строке сетки (два `.profile-form__grid` подряд).
- **Админ:** «Профиль», только Email + пароль. `change_own_password` для админа — отказ.
- **`student.php`** — шапка с аватаром, ФИО, группой, логином, email, VK, Telegram, числом проектов, кнопкой «⚙ Настройки профиля» → `?page=profile`. Вкладки «Проекты» / «Журнал». Профильной формы в `student.php` больше нет.
- **`nav.php`** — для `student` сразу `return`, верхнего меню нет (разделы внутри вкладок).

### Аутентификация

- **Админ** — логин `admin_login` из `config.php`, пароль `admin_password`. Проверка в `prikaz_is_admin_login` + `prikaz_check_admin_password` (`lib/utils.php`). `prikaz_config()` читает `$GLOBALS['PRIKAZ_CONFIG']` (если есть `admin_login`) или файл напрямую по путям: `PRIKAZ_CONFIG_PATH` → рядом с БД → рядом с корнем → `/var/www/u3469397/data/www/prikaz-data/config.php` → env.
- **Все остальные** — `password_verify` по `users.password_hash`, авто-рехэш.
- **Сменить пароль админа** через веб нельзя — только `config.php`.
- **Сброс пароля** для админа (`admin_user_reset_password`) — отказ.
- **`admin_user_toggle`** для админа — отказ.
- **`admin_user_create`** с логином `admin` — отказ.

`auth_attempt` в `bootstrap.php` пишет лог в `prikaz-data/diag.log`: `login`, `is_admin`, `cfg.admin_login`, `cfg.pass_len`. Удалять `diag.log` после отладки.

### Журнал
Сетка «студенты × занятия», модалка с оценкой и заметкой, тема и ДЗ по клику на дату.

### Проекты
Фазы (Выбор темы → Работа → Предзащита → Защита), типы, участники, история, repo_url.

### Админка
Пользователи, студенты, родители, уроки, проекты, аудит. Диспетчер `lib/views/admin.php`, секции `lib/views/admin/*.php`, партиалы `_topics_block.php` / `_ktp_block.php`. `_styles.php`, `_scripts.php` — общие.

### Справка
Плавающая «?», null-guard на `fab`/`panel`/`closeBtn`.

### Вход
Славянский стиль, буквица, Ruslan Display.

### AJAX
`_scripts.php`, `ajaxSubmit(form, block)` — fetch с `ajax=1`. Сервер: `wants_json()` → `json_response({ok, html})`. `redirect()` и `json_response()` вызывают `ob_end_clean`. `index.php` открывает `ob_start()` в начале.

---

## 5. Безопасность

| Механизм | Реализация |
|---|---|
| Пароль админа | `config.php` + `hash_equals` |
| Пароли остальных | `password_hash(PASSWORD_DEFAULT)`, авторехэш |
| CSRF | Токен в сессии, `hash_equals` |
| Сессия | `session_regenerate_id`, `httponly`, `samesite=Lax`, `secure` при HTTPS |
| SQL | Подготовленные запросы |
| XSS | `e()` везде |
| Аудит | `audit_log()` с IP |
| БД | Вне webroot |
| Буферы | `ob_start` + `ob_end_clean` |
| `diag.php` | Одноразовый, удалять |

**Не сделано:** восстановление пароля по email, rate limiting, 2FA, согласие на ПДн.

---

## 6. Известные ловушки

1. **SQLite.** `GROUP_CONCAT(..., ',')`, `COLLATE NOCASE`, `PRAGMA foreign_keys = ON`.
2. **`init.php` и `db.php` смотрят на один файл БД.** Абсолютный путь через `__DIR__`.
3. **Миграция `subjects`** сносит `curriculum` и `schedule_entries` при старой схеме. `DROP` без переноса.
4. **`DROP TABLE subjects`** при включённых FK — `PRAGMA foreign_keys = OFF` вне транзакции.
5. **`headers already sent`** — `if (!headers_sent()) http_response_code(...)`.
6. **BOM в PHP-файле** ломает `<?php`. UTF-8 без BOM. `head -c 3 | xxd` → `3c 3f 70`.
7. **`actions.php` не заканчивается на `?>`.** Перевод строки после — в буфер.
8. **Opcache** кэширует `actions.php` / `bootstrap.php` / `utils.php`. Перезапуск PHP-FPM или `diag.php?opcache_reset=1`.
9. **Стили help widget** — внутри `help_widget.php`.
10. **CSRF** — проверить покрытие.
11. **`schedule_*()`, `parse_topic_lines()`, `topic_key()`** — в `lib/schedule.php` / `lib/utils.php`.
12. **`data.php` для `$adminSubjects`** — `ay.id AS academic_year_id` обязательно.
13. **Хелперы секций локальные** — `$holidayChip`, `$groupWithSubgroup`, `$typesMeta`.
14. **`nav.php` — `$academicSections`** включает `subjects`. И `student` → `return` в начале.
15. **`curriculum_render_block()`** — `lib/curriculum.php` в `bootstrap.php`.
16. **`ktp_render_block()`, `ktp_lesson_blocks()`, `ktp_holiday_losses()`** — `lib/ktp.php` в `bootstrap.php`.
17. **`students_*`, `parse_student_lines`, `student_profile_save`** — `lib/students.php` в `bootstrap.php`. Иначе `Call to undefined function`.
18. **JSON-ответ ломается от BOM/warning.** `index.php` — `ob_start()`. `json_response()` / `redirect()` — `ob_end_clean()`. `json_response()` пишет мусор в `error_log`.
19. **`ktp_lesson_blocks`** — групповые пары `(1,2)`, `(3,4)`; подгрупповые — один блок на день.
20. **`ktp_holiday_losses`** — только дни, где у предмета есть занятия.
21. **Sticky КТП** — `.pzk-table-wrap--subjects { overflow: visible; }`. ≤1200px — `static`.
22. **`--ktp-head-h`** — вычисляется `ResizeObserver` в `_scripts.php`.
23. **AJAX-подмена блока** — `initKtpBlock(newBlock)` заново.
24. **Формы внутри `.pzk-subject-ktp` / `.pzk-subject-topics`** — AJAX по делегированию.
25. **Inline-редактирование** — `<details>` без видимого summary, открывается через `data-open-*-edit`.
26. **Пароль админа — только `config.php`.** `prikaz_is_admin_login` + `prikaz_check_admin_password`. `password_verify` для админа — **не работает**.
27. **`prikaz_config()` читает `$GLOBALS['PRIKAZ_CONFIG']`** или файл по путям. Если `$GLOBALS` без `admin_login` — читает с диска. `PRIKAZ_CONFIG_PATH` в bootstrap из `$GLOBALS['PRIKAZ_CONFIG_PATH']` — добавить в `index.php` (`$GLOBALS['PRIKAZ_CONFIG_PATH'] = $configPath;`).
28. **`admin_student_bulk_create`** — группа обязательна, иначе отказ. `students_bulk_create($parsed, $groupId)`.
29. **`students_update`** — если `password` непустой, обновляет `users.password_hash`; если пустой — не трогает.
30. **`admin_user_reset_password` / `admin_user_toggle`** для админа — отказ. `admin_user_create` с логином `admin` — отказ.
31. **`_student_profile_form.php`** — устаревший партиал, нигде не подключается. Удалить.
32. **`diag.php`** — одноразовый, удалять. Показывает конфиг и БД.
33. **`diag.log`** — пишется `auth_attempt` в `dirname(PRIKAZ_DB_PATH)`. Удалять после отладки.
34. **`profile.php`** — работает без `data.php`: если `$studentProfile` не передан, подгружает из БД для роли `student` (в начале файла).

---

## 7. Конвенции

- Файлы целиком, без фрагментов «замените этот блок».
- SQLite, не MySQL. `GROUP_CONCAT` с одинарными кавычками.
- Стиль: славянский на входе, минимализм внутри. Serif в заголовках, sans в теле.
- «Критерии оценивания», не «рубрика».
- Тон прямой. К пользователю — мужской род, о себе — женский.
- Перед выдачей файла целиком — проверить сущности (семестры, праздники, расписание, предметы, подгруппы, КТП, студенты) и ловушки.
- Админка — `lib/views/admin/<section>.php`. Партиалы — `_<name>_block.php`. Общие — `_styles.php`, `_scripts.php`.
- `actions.php` — только диспетчер. Обработчики — в `lib/actions/<domain>.php`.
- README и context_prikaz.md — в обёртке ```` (четыре обратные кавычки), иначе скачивание ломается.

---

## 8. Открытые задачи

1. **Экспорт журнала** — XLSX.
2. **Тесты** — не написаны.
3. **Нагрузочные прогоны** — до 100 параллельных.
4. **Ревизия наследия CRM** — удалить.
5. **Печать расписания** — не сделано.
6. **Кэш `ktp_lesson_blocks`** — при 50+ предметах.
7. **`_student_profile_form.php`** — удалить.

---

## 9. Что было в этом чате

1. Разбиение `index.php` на модули.
2. LMS → Приказ.
3. БД вне webroot в `prikaz-data/`.
4. Убрана Keycloak.
5. Страница входа в славянском стиле.
6. Сущности: год → семестр → группа → предмет → темы → расписание → праздники.
7. Расписание — одно на семестр.
8. Праздники — в карточке семестра.
9. Заголовок семестра `2.1rem`.
10. Форма редактирования предмета — селект семестра.
11. Расписание: одна ячейка = одна запись.
12. Расписание: пустые ячейки — не ошибка.
13. Расписание: подгруппы в названии группы.
14. Рефакторинг `admin.php` — на секции.
15. README — v.0.1.4.0.
16. КТП (`ktp_entries`, `lib/ktp.php`, UI в `subjects.php`).
17. КТП: одна тема = блок; групповые пары, подгруппы; tariff/slots; диапазоны; rowspan; sticky.
18. КТП: шапка «план / тариф / КТП / факт» + праздники, зелёная подсветка.
19. AJAX для КТП и тем.
20. `lib/curriculum.php`, `_topics_block.php`, `_ktp_block.php`.
21. `admin_curriculum_insert_below`.
22. Inline-редактирование тем (`<details>` в ячейке).
23. Inline-редактирование предмета (отдельная строка).
24. `ob_start()` / `ob_end_clean()`.
25. `help_widget.php` null-guard.
26. README — v.0.1.5.0.
27. КТП: фильтр «Все / Лекция / Практикум», столбец «№ по типу» с диапазонами.
28. КТП: «Фактически часов» с вычетом праздников.
29. КТП: подгруппы в подсчёте номеров.
30. **Студенты: расширены** (`last_name`, `first_name`, `middle_name`, `vk_url`, `telegram`, `group_id`). CRUD, массовое добавление, обязательная группа, `1111`/`<login>@mail.ru`.
31. **Личный кабинет студента** (`profile.php` + `change_own_password` — email, VK, Telegram, пароль). `student.php` — без формы профиля, кнопка «⚙ Настройки профиля».
32. **`nav.php`** для студента — `return` (нет верхнего меню).
33. **`actions.php` разбит на 10 файлов** в `lib/actions/`.
34. **Фильтры студентов** по году/группе.
35. **Inline-редактирование групп** (аналогично темам).
36. **Поле «Пароль» в редактировании студента** (опционально). Учебный год из формы убран.
37. **Пароль админа — только `config.php`.** `prikaz_is_admin_login` / `prikaz_check_admin_password`. `change_own_password` / `admin_user_reset_password` / `admin_user_toggle` для админа — отказ. `admin_user_create` с логином `admin` — отказ.
38. **`auth_attempt`** — админ по конфигу, остальные по БД. Логи в `diag.log`.
39. **`prikaz_config()`** — читает `$GLOBALS['PRIKAZ_CONFIG']` с fallback на файл, `PRIKAZ_CONFIG_PATH` из `index.php`.
40. **README** — v.0.1.6.0.

---

## 10. Быстрый старт

CLI на shared-хостинге нет. Открывать `db/init.php` в браузере.

`https://<host>/prikaz/db/init.php` → «Готово». Учётные данные админа — в `config.php`. После — удалить `init.php`.

`?page=login` — вход.

---

## 11. Что делать в новом чате

**Если «поправить / добавить»:**
1. Проверить, что нужные файлы присланы целиком. Правки по админке — `lib/views/admin/<section>.php` или партиалы. Логика — `actions/<domain>.php`, `data.php`, `ktp.php`, `curriculum.php`, `students.php`.
2. Если чего-то не хватает — попросить.
3. Выдать файл целиком.
4. Если правится одна секция — не трогать диспетчер и общие `_styles.php` / `_scripts.php`, если правка не касается их.

**Если «показать на демо»** — помнить:
- праздники в карточках семестров, вычитаются из «Фактически часов» КТП;
- расписание на семестр, одна запись на ячейку, подгруппы в названии группы;
- КТП с блоками, фильтром, столбцом «№ по типу»;
- студенты с обязательной группой при массовом добавлении;
- пароль админа — только `config.php`;
- личный кабинет студента — `?page=profile`, не в `?page=student`.

**Если «доработать»** — см. «Открытые задачи».

**Перед выдачей файла целиком** — пробежаться по ловушкам (34) и текущим сущностям: `semesters`, `holidays`, `schedule_entries`, `subjects`, `curriculum`, `ktp_entries`, `students`. Свериться, что хелперы секций не потерялись.

**Если «сгенерируй README / context»** — в обёртке ```` (четыре обратные кавычки), иначе внутренние блоки кода ломают скачивание.