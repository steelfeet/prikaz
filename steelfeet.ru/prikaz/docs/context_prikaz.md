# Контекст проекта «Приказ»

## 1. Что это

Школьная LMS на PHP 8.2 + SQLite. Один каталог `prikaz/`, БД вне webroot
в `prikaz-data/`. Никаких фреймворков: чистый PHP + PDO + небольшой слой
хелперов. Сессии, CSRF, аудит — вручную. Раздаётся как есть, разворачивается
на shared-хостинге копированием файлов.

**PAMS — Project & Attestation Management System.** Приказ — не LMS
и не CRM, а система управления проектами и аттестацией для колледжа.
Ближе к PPM со спецификой учебного процесса.

**Текущая версия: v.0.1.10.26** — вес оценки в журнале.

---

## 2. Структура

```
prikaz/
  index.php              — роутер (?page=…), ob_start(), bootstrap, views
  config.php             — app_name, db_path, storage_path, admin_login, admin_password,
                           session_name, csrf_key, password_min,
                           gogs_base_url, gogs_token, gogs_owner,
                           default_grade_weight, auto_grade_weight, min_weight_for_final
  bootstrap.php          — конфиг, сессия, db(), csrf, auth, audit, require_once lib/*
  actions.php            — POST-диспетчер ($action → lib/actions/<domain>.php)
  data.php               — загрузка данных для рендера (учёт весов)
  nav.php                — меню (admin / teacher / parent / student)
  profile.php            — личный кабинет (все роли)
  student.php            — страница студента (проекты, журнал)
  help_widget.php        — плавающая «?»
  lib/
    utils.php            — e(), redirect(), json_response(), flash_*, wants_json(),
                           hash_password, prikaz_config, prikaz_is_admin_login,
                           prikaz_check_admin_password, parse_topic_lines, topic_key
    journal.php          — journal_lessons, journal_marks_map, journal_mark_apply
                           (с weight), journal_lesson_note_apply, aggregate_journal
                           (с весами), journal_render_grid, journal_render_student
    schedule.php         — schedule_*
    ktp.php              — ktp_*
    curriculum.php       — curriculum_*
    students.php         — students_*, parse_student_lines, student_*
    projects.php         — project_phases_meta, project_types_meta, project_phases_for,
                           project_current_phase_num, project_history_rows,
                           project_students_rows, project_change_phase
    project_types.php    — project_types_dir, project_types_all, project_type_by_id,
                           project_type_by_code, project_type_phases,
                           project_type_checks_recent, project_type_phase_palette,
                           project_type_next_phase_color, project_type_validate_code,
                           project_type_save, project_type_delete,
                           project_type_render_files, project_type_check
    actions/
      auth.php           — logout, change_own_password
      journal.php        — grade_set, journal_mark_set (с weight), journal_lesson_note_set
      projects.php       — project_move_phase
      project_types.php  — admin_project_type_* (save/delete/regen_folder/check)
                           + admin_project_type_phase_* (save/delete)
      academic.php       — admin_academic_year_*, admin_group_*, admin_semester_*,
                           admin_holiday_*
      subjects.php       — admin_subject_*, admin_curriculum_*, admin_ktp_*
      schedule.php       — admin_schedule_*
      users.php          — admin_user_* (без студентов; guard отклоняет студента)
      students.php       — admin_student_*, admin_parent_*, student_profile_save
      lessons.php        — admin_lesson_*
      misc.php           — help_feedback
  lib/views/
    admin.php            — диспетчер секций (?page=admin&section=…)
    admin/academic_years.php, groups.php, subjects.php, schedule.php,
          users.php (без студентов), students.php, parents.php, lessons.php,
          projects.php, project_types.php, audit.php
    admin/_topics_block.php, _ktp_block.php
    admin/_styles.php, _scripts.php
    _journal_grid.php    — сетка журнала (учитель), бейдж веса
    _journal_student.php — сетка журнала (студент), бейдж веса
    login.php, journal.php (модалки + веса), teacher.php, student.php,
    parent.php, profile.php, …
  db/init.php            — инициализация/сброс БД (в браузере), миграции
  projects/              — CI/CD-проверки (v.0.1.9.25)
    .htaccess            — закрывает прямой доступ
    _templates/
      check.php          — эталон-заглушка (большой комментарий сверху)
      config.json        — параметры проверки (справка)
      README.md          — инструкция: как отдать в ИИ, получить реальный check.php
    <code>/
      check.php          — рабочая копия; правится админом, при обновлении типа НЕ перезаписывается
  assets/                — стили, скрипты
  prikaz-data/           — SQLite + diag.log (вне webroot)
```

**Наследие CRM** (не нужно, удалить при рефакторинге):
`lib/workflow.php`, `lib/documents.php`, `lib/views/manager.php`,
`lib/views/university.php`, `lib/views/supervisor.php`, `lib/views/documents.php`,
`lib/views/document_card.php`, `assets/documents.css`, `assets/workflow_docs.js`,
`assets/supervisor.js`.

---

## 3. Роли

| Роль | Что делает |
|---|---|
| `admin` | Создаёт пользователей (преподавателей, родителей, администраторов) и студентов (отдельный раздел). Учебный процесс, уроки, **типы проектов и их фазы**, проекты, аудит. Пароль — из `config.php` |
| `teacher` | Журнал (оценки с весом, заметки), фазы проектов, сводка, активность родителей |
| `student` | Свои оценки с весами, заметки, проекты, фазы. Личный кабинет: email, VK, Telegram, пароль |
| `parent` | Журнал и проекты своего ребёнка. Факт захода фиксируется |

Самостоятельной регистрации нет.

---

## 4. Что работает

### Учебный процесс

Меню: «Учебный год», «Группы», «Предметы и темы».

- **Учебный год** (`academic_years`): `name`, `start_date`, `end_date`,
  `is_current` (UNIQUE INDEX). При создании — семестры «1 семестр» и
  «2 семестр».
- **Семестры** (`semesters`): `academic_year_id`, `name`, `is_practice`, `ord`.
- **Праздники** (`holidays`): `semester_id`, `date`, `name`.
  UNIQUE `(semester_id, date)`. Влияют на КТП.
- **Группы** (`groups`): `academic_year_id`, `name`, `curator`, `notes`.
  UNIQUE `(academic_year_id, name)`. Inline-редактирование.
- **Предметы** (`subjects`): `semester_id`, `group_id`, `short_name`,
  `full_name`, `hours`. Inline-редактирование.
- **Темы** (`curriculum`): `subject_id`, `ord`, `topic`. Inline,
  «+ Добавить ниже», всё AJAX.

### КТП

Одна тема = один блок. Групповой — пара (1,2), (3,4)… Подгрупповой — все
sub1/sub2 дня = один блок. Праздники пропускаются. Шапка: «Часов по
учебному плану / По тарификации / В КТП / Фактически часов» + праздники +
фильтр «Все / Лекция / Практикум». Столбец «№ по типу» — при фильтре.
Sticky-шапка и `thead th`. Всё AJAX.

**Функции** (`lib/ktp.php`): `ktp_lesson_blocks`, `ktp_holiday_losses`,
`ktp_layout`, `ktp_tariff_from_layout`, `ktp_slots_from_layout`, `ktp_generate`,
`ktp_add_more`, `ktp_delete`, `ktp_reindex`, `ktp_render_block`, `ktp_kind_label`.

### Журнал

Раздел `?page=journal`. Новая модель — привязка к предмету (`subjects`),
типу занятия (`group` / `sub`) и **дате** (`journal_marks`,
`journal_lesson_notes`).

**Таблицы:**

- `journal_marks`: `subject_id`, `student_id`, `date`, `kind`, `mark`,
  `note`, `weight`, `updated_by`, `updated_at`.
  UNIQUE `(subject_id, student_id, date, kind)`.
- `journal_lesson_notes`: `subject_id`, `date`, `kind`, `note`, `homework`,
  `updated_by`, `updated_at`. UNIQUE `(subject_id, date, kind)`.

**Оценки:** 2, 3, 4, 5. Минус (`3-`, `4-`, `5-`) — только отображение,
на среднее не влияет. `н` (отсутствовал), `ну` (неуважительная) — посещаемость,
в среднее не идут.

**Вес оценки** (`weight`): INTEGER NOT NULL DEFAULT 10.

| Вес | Что | Кто |
|---|---|---|
| 1 | Авто-проверка (новости, тесты) | Скрипт |
| 3 | Домашняя, самостоятельная | Преподаватель |
| 5 | Контрольная | Преподаватель |
| 10 | «Весомая» оценка (по умолчанию) | Преподаватель |

**Взвешенный средний:** `Σ(оценка × вес) / Σ(вес)`, только числовые оценки.
Пока `Σ(вес) < min_weight_for_final` — предварительный (`is_preliminary`).
**Пример:** 3×`5` (вес 1) + 1×`3` (вес 10) → `45/13 ≈ 3.46`, не `4.5`.

**Отображение:** в ячейке — оценка + бейдж веса. Класс: `pzk-weight--low`
(1), `--mid` (3–5), `--high` (10+).

**Примечания:** к оценке (видит студент и преподаватель), к уроку (общие,
видит вся группа) + ДЗ.

**Правая панель:** «Темы журнала» (уроки страницы), «Примечания» (по датам
страницы, с бейджами весов).

Студент видит только свои оценки с весами + общие примечания к уроку и ДЗ.

**Модалка оценки:** селектор веса (10/5/3/1) + выбор оценки + примечание.
`apply`-режим не нужен — сохранение через `journal_mark_set`.

**`aggregate_journal()`** поддерживает обе модели:

- `journal_marks` — `[sid][kind][date] => ['mark','weight','note']`;
- `grades` (старая) — `[sid][ord] => '5'` (вес считается равным 10).

Возвращает: `total`, `sum`, `count`, `raw_avg`, `weighted_sum`,
`weight_total`, `weighted_avg`, `is_preliminary`, `recommended` (2..5 по
взвешенному), `dist`, `lessons`.

### Студенты

Раздел `?page=admin&section=students`. **Отдельно от «👥 Пользователи»** —
студентов в «Пользователях» нет.

**Поля в БД** (`students` + `users`): `last_name`, `first_name`,
`middle_name`, `login` (= `students.alias` = `users.login`),
`email` (`users.email`), `password` (`users.password_hash`), `vk_url`,
`telegram`, `group_id`, `group_name` (совместимость), `full_name`
(совместимость).

**Фильтры:** учебный год, группа.

**Добавление:** Фамилия, Имя, Отчество, Логин, Пароль, Email, VK, Telegram,
год + группа.

**Inline-редактирование:** Фамилия, Имя, Отчество, Логин, Email, Пароль
(пустой — не менять), VK, Telegram, Группа. **Учебного года нет** — все
группы всех годов в селекте.

**Удаление** — каскад через `users`.

**Массовое добавление:** `Фамилия Имя Отчество, логин` по строке. Группа
обязательна (из групп текущего года). Пароль `1111`, email `<логин>@mail.ru`.

**Личный кабинет** (`?page=profile`): email, VK, Telegram, пароль.
`student.php` — шапка + вкладки «Проекты» / «Журнал», кнопка «⚙ Настройки
профиля».

**Функции** (`lib/students.php`): `students_create`, `students_update`
(опц. пароль), `students_delete`, `students_bulk_create`, `parse_student_lines`,
`student_split_full_name`, `student_join_full_name`, `student_validate_login`,
`student_validate_email`, `students_group_options`, `student_profile_save`.

**Обработчики** (`lib/actions/students.php`): `admin_student_create`,
`admin_student_update`, `admin_student_delete`, `admin_student_bulk_create`,
`student_profile_save`, `admin_parent_link`, `admin_parent_unlink`.

### Пользователи (без студентов)

Раздел `?page=admin&section=users`. Только преподаватели, родители,
администраторы.

`$adminUsers` в `data.php` фильтрует:

```sql
WHERE u.id NOT IN (SELECT user_id FROM students)
  AND NOT EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id
                  WHERE ur.user_id = u.id AND r.code = 'student')
```

`admin_user_create` — белый список `['admin', 'teacher', 'parent']`,
`role=student` — отказ с редиректом в «Студенты».

`admin_user_toggle`, `admin_user_reset_password` — guard `$__guardNotStudent`:
если целевой `user_id` есть в `students.user_id` **или** у него роль
`student` — отказ (защита от прямого POST).

### Аутентификация

- **Админ** — `admin_login` / `admin_password` из `config.php`.
  `prikaz_is_admin_login` + `prikaz_check_admin_password`.
- **Остальные** — `password_verify` по `users.password_hash`, авто-рехэш.
- `auth_attempt` пишет в `prikaz-data/diag.log`. Удалять после отладки.

### Проекты

**Старая модель.** `project_phases` (глобальные 4 фазы), `projects.type`
строкой, `project_history.phase_id`. Только для `project_type_id IS NULL`.

**Новая модель (v.0.1.9.25+).** См. подраздел «Типы проектов».

### Типы проектов (v.0.1.9.25+)

Раздел `?page=admin&section=project_types`. Пункт меню «🎓 Типы проектов».

**Таблицы:**

- `project_types`: `id`, `code` (UNIQUE, `[a-z][a-z0-9_-]{1,31}`), `name`,
  `description`, `color` (hex), `icon` (эмодзи), `gogs_owner`,
  `gogs_repo_prefix`, `created_at`, `updated_at`.
- `project_type_phases`: `id`, `project_type_id`, `ord`, `name`, `color`,
  `description`. UNIQUE `(project_type_id, ord)`.
- `project_type_checks`: `id`, `project_type_id`, `checked_by`, `ok`,
  `errors`/`warnings`/`details` (json), `checked_at`.

**`projects.project_type_id`** — nullable, FK.
**`project_history.type_phase_id`** — nullable, FK.

**Папка `projects/`** (`PRIKAZ_ROOT/projects/`):

- `.htaccess` — закрывает прямой доступ.
- `_templates/check.php` — эталон-заглушка. Большой комментарий сверху:
  структура `$ctx`, формат возврата, примеры Gogs API, TODO 1–5.
- `_templates/config.json` — параметры проверки (справка, не читается).
- `_templates/README.md` — инструкция: отдать `check.php` в чат ИИ,
  получить реальный скрипт, положить обратно.
- `projects/<code>/check.php` — рабочая копия. При редактировании типа
  **не перезаписывается**. Принудительно — «♻ Пересоздать папку».

**Действия на карточке типа:**

| Кнопка | Action | Что делает |
|---|---|---|
| Редактировать | `admin_project_type_save` (`id>0`) | обновляет, папку не трогает |
| ➕ Добавить | `admin_project_type_save` (`id=0`) | создаёт тип + `projects/<code>/check.php` из `_templates/` |
| ✅ Проверить | `admin_project_type_check` (`apply=0`) | dry-run: `include check.php` с `$ctx`, пишет в `project_type_checks` |
| 🚀 Проверить и применить | `admin_project_type_check` (`apply=1`) | то же + merge/close/comment в Gogs |
| ♻ Пересоздать папку | `admin_project_type_regen_folder` | `copy(_templates/check.php → projects/<code>/check.php)` |
| Удалить | `admin_project_type_delete` | отказ, если есть проекты; папку не трогает |

**Фазы типа:**

| Кнопка | Action |
|---|---|
| ➕ Добавить фазу | `admin_project_type_phase_save` (`id=0`) |
| Редактировать | `admin_project_type_phase_save` (`id>0`) |
| Удалить | `admin_project_type_phase_delete` |

**Цвета фаз (v.0.1.9.26).**

- `project_type_phase_palette()` — 10 hex-цветов в фиксированном порядке.
- `project_type_next_phase_color($phases)` — стартует с
  `count($phases) % 10`, идёт вперёд до первого свободного. Если заняты
  все 10 — возвращает цвет на стартовой позиции.
- В `lib/views/admin/project_types.php` — `$renderPhaseColor($name, $value)`:
  `<input type="color">` + hex-инпут + 10 свотчей, активный подсвечен
  `is-active`. `<script>` внизу страницы синхронизирует их через
  `data-color-picker`.
- В `lib/actions/project_types.php` — hex нормализуется
  (`!preg_match('/^#[0-9a-fA-F]{6}$/')` → `#8b6914`).

**`$ctx` для `check.php`:**

```
$ctx['type']     = project_types row + gogs_owner / gogs_repo_prefix
$ctx['gogs']     = ['base_url' => config, 'token' => config,
                    'owner'    => type.gogs_owner ?: config.gogs_owner]
$ctx['projects'] = [{id,title,repo_url}, ...]  // неархивные проекта этого типа
$ctx['dir']      = путь к projects/<code>/check.php
$ctx['apply']    = bool  // true → check.php может делать merge/close/comment
```

`include` делается в замыкании, `$ctx` в области видимости. Файл должен
**вернуть** массив `['ok','errors','warnings','details']`. Иначе
`project_type_check()` подставит `['ok'=>false, 'errors'=>['check.php
не вернул массив.'], …]`.

**Готовый `check.php` для «Новости ИТ и ИБ»** — 10 проверок:

1. Название PR или сообщение коммита содержит «Новости IT» / «Новости ИТ».
2. Ровно один новый `.md` в `Новости/`, без правок других файлов.
3. Путь `Новости/<уч.год>/<номер_группы>/<4–8 латинских букв>.md`.
4. Имя файла не похоже на фамилию (эвристика по окончаниям `-ov`,
   `-ev`, `-sky`, `-enko`, `-ich`, `-ovich`).
5. Заголовок H1 (`# …`).
6. URL-источник + дата `ДД.ММ.ГГГГ` на следующей строке.
7. Комментарий ≥ 500 символов (без заголовка, URL, даты).
8. Суждение от первого лица (`я считаю`, `мне кажется`, `по-моему`,
   `на мой взгляд`, `я думаю`).
9. Нет клише (`в современном мире`, `данная новость актуальна`,
   `нельзя не отметить`, `таким образом` в последнем абзаце).
10. Нет ПДн (ФИО с отчеством, e-mail, телефон) и новость не старше
    14 дней от `updated_at` PR.

**Режимы:**

- `apply=0` (dry-run) — только прогон и запись в `project_type_checks`.
- `apply=1` — пройденный PR → squash merge + комментарий «✅ Принято»;
  непройденный → комментарий со списком нарушений + закрытие.

### Админка

Пользователи (без студентов), студенты, родители, уроки, типы проектов,
проекты, аудит. Диспетчер `lib/views/admin.php`, секции
`lib/views/admin/*.php`, партиалы `_topics_block.php` / `_ktp_block.php`.
`_styles.php`, `_scripts.php` — общие.

`$allowedSections` включает `project_types`. `nav.php` — «🎓 Типы проектов».

### AJAX

`_scripts.php`, `ajaxSubmit(form, block)` — fetch с `ajax=1`. Сервер:
`wants_json()` → `json_response({ok, html})`. `redirect()` и
`json_response()` вызывают `ob_end_clean`. `index.php` открывает
`ob_start()` в начале.

Журнал — AJAX (`journal_mark_set`, `journal_lesson_note_set`).
Типы проектов — POST + `redirect()`.

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
| `projects/` | Закрыт `.htaccess` от прямого доступа |
| CI/CD `check.php` | `include`, код пишет админ; Gogs-токен — в `config.php` |
| Пользователи | Студенты отфильтрованы и в `data.php`, и в `actions/users.php` |

**Не сделано:** восстановление пароля по email, rate limiting, 2FA,
согласие на ПДн.

---

## 6. Известные ловушки

1. **SQLite.** `GROUP_CONCAT(..., ',')`, `COLLATE NOCASE`, `PRAGMA foreign_keys = ON`.
2. **`init.php` и `db.php` смотрят на один файл БД.** Абсолютный путь через `__DIR__`.
3. **Миграция `subjects`** сносит `curriculum` и `schedule_entries` при старой схеме.
4. **`DROP TABLE subjects`** при включённых FK — `PRAGMA foreign_keys = OFF` вне транзакции.
5. **`headers already sent`** — `if (!headers_sent()) http_response_code(...)`.
6. **BOM в PHP-файле** ломает `<?php`. UTF-8 без BOM.
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
17. **`students_*`, `parse_student_lines`, `student_profile_save`** — `lib/students.php` в `bootstrap.php`.
18. **JSON-ответ ломается от BOM/warning.** `ob_start()` в `index.php`, `ob_end_clean()` в `json_response()`/`redirect()`.
19. **`ktp_lesson_blocks`** — групповые пары `(1,2)`, `(3,4)`; подгрупповые — один блок на день.
20. **`ktp_holiday_losses`** — только дни, где у предмета есть занятия.
21. **Sticky КТП** — `.pzk-table-wrap--subjects { overflow: visible; }`. ≤1200px — `static`.
22. **`--ktp-head-h`** — вычисляется `ResizeObserver` в `_scripts.php`.
23. **AJAX-подмена блока** — `initKtpBlock(newBlock)` заново.
24. **Формы внутри `.pzk-subject-ktp` / `.pzk-subject-topics`** — AJAX по делегированию.
25. **Inline-редактирование** — `<details>` без видимого summary, открывается через `data-open-*-edit`.
26. **Пароль админа — только `config.php`.** `prikaz_is_admin_login` + `prikaz_check_admin_password`. `password_verify` для админа — не работает.
27. **`prikaz_config()` читает `$GLOBALS['PRIKAZ_CONFIG']`** или файл по путям. `PRIKAZ_CONFIG_PATH` — в `index.php`.
28. **`admin_student_bulk_create`** — группа обязательна.
29. **`students_update`** — если `password` непустой, обновляет `users.password_hash`; если пустой — не трогает.
30. **`admin_user_reset_password` / `admin_user_toggle`** для админа — отказ. `admin_user_create` с логином `admin` — отказ.
31. **`_student_profile_form.php`** — устаревший партиал. Удалить.
32. **`diag.php`** — одноразовый, удалять.
33. **`diag.log`** — пишется `auth_attempt` в `dirname(PRIKAZ_DB_PATH)`. Удалять после отладки.
34. **`profile.php`** — работает без `data.php`: если `$studentProfile` не передан, подгружает из БД для роли `student`.
35. **`data.php` для `$adminSubjects`** — фильтр по семестру **после** фильтра по году/группе.
36. **`project_types_dir()`** — от `PRIKAZ_ROOT`. Эталон — `projects/_templates/check.php`. При `id=0` — `project_type_render_files($newId, $pdo, true)`. При `id>0` — файл **не трогается**. Принудительно — `admin_project_type_regen_folder`.
37. **`actions.php`: `admin_project_type_` ДО `admin_project_`.** Иначе `str_starts_with` поймает `admin_project_`.
38. **`project_type_check()`** делает `include` через замыкание. Файл должен **вернуть** массив. Если нет — `['ok'=>false, …]`. Пишет в `project_type_checks` (`json_encode`, `JSON_UNESCAPED_UNICODE`).
39. **`project_type_render_files($typeId, $pdo, $overwrite=false)`** — если `$overwrite=false` и файл есть, не перезаписывает. Используется в `project_type_check()` для ленивого создания.
40. **`projects/`** закрыт `.htaccess`. Если папки нет — `mkdir(0775, true)`.
41. **`project_types_meta()`** — из БД, fallback на статические 5. `project_phases_meta()` — только старая модель.
42. **`project_phases_for($projectId)`** — если `project_type_id > 0`, фазы типа. Иначе — глобальные. `project_current_phase_num()`, `project_history_rows()` — `COALESCE(pph.num, ptph.ord)`.
43. **`project_change_phase()`** — для проекта с типом пишет `type_phase_id`, иначе `phase_id`. Оба поля nullable.
44. **Gogs в `config.php`:** `gogs_base_url`, `gogs_token`, `gogs_owner`. Без токена реальные запросы — 401.
45. **Студенты — только в «🎒 Студенты».** `data.php` фильтрует `$adminUsers` по `students.user_id` и роли `student`. `lib/actions/users.php` отклоняет toggle/reset для студента через `$__guardNotStudent`.
46. **`journal_marks.weight`** — INTEGER NOT NULL DEFAULT 10. Миграция `ALTER`-ом. Существующие оценки получают 10.
47. **`journal_mark_apply(... int $weight ...)`** — вес перед `$userId`. Валидация `$weight <= 0` → `default_grade_weight`.
48. **`aggregate_journal()`** — поддерживает обе модели (`grades` без веса, `journal_marks` с весом). `weighted_avg` — только для числовых.
49. **`data.php`: `perStudentSummary['avg']`** — взвешенный средний (fallback на сырой). `weighted_avg` и `raw_avg` — оба доступны.
50. **`min_weight_for_final`** — пока `Σ(вес) < этого` — средний предварительный (`is_preliminary`).
51. **Цвета фаз:** `project_type_phase_palette()` (10 hex), `project_type_next_phase_color($phases)` (по очереди, пропускает занятые, циклический fallback). Hex нормализуется в `admin_project_type_phase_save` и `admin_project_type_save`.
52. **`_templates/config.json`** — справка, не читается из `check.php`. Подключить, когда появится реальная проверка.

---

## 7. Конвенции

- Файлы целиком, без фрагментов «замените этот блок».
- SQLite, не MySQL. `GROUP_CONCAT` с одинарными кавычками.
- Стиль: славянский на входе, минимализм внутри. Serif в заголовках, sans в теле.
- «Критерии оценивания», не «рубрика».
- Тон прямой. К пользователю — мужской род, о себе — женский.
- **Перед кодом — краткий путь и имя файла** (например, `lib/data.php`),
  потому что в проекте много одноимённых файлов (`projects.php`,
  `project_types.php`, `journal.php`, `data.php`).
- Перед выдачей файла целиком — проверить сущности (семестры, праздники,
  расписание, предметы, подгруппы, КТП, студенты, **типы проектов,
  веса оценок**) и ловушки.
- Админка — `lib/views/admin/<section>.php`. Партиалы — `_<name>_block.php`.
  Общие — `_styles.php`, `_scripts.php`.
- `actions.php` — только диспетчер. Обработчики — в `lib/actions/<domain>.php`.
- CI/CD-эталон — `projects/_templates/check.php` + `config.json` + `README.md`.
  Новые типы получают копию при создании; правится копия, а не эталон.
- README и context_prikaz.md — в обёртке ```` (четыре обратные кавычки),
  иначе скачивание ломается.

---

## 8. Открытые задачи

1. **Экспорт журнала** — XLSX (с весами).
2. **Тесты** — не написаны.
3. **Нагрузочные прогоны** — до 100 параллельных.
4. **Ревизия наследия CRM** — удалить.
5. **Печать расписания** — не сделано.
6. **Кэш `ktp_lesson_blocks`** — при 50+ предметах.
7. **Удалить `_student_profile_form.php`**.
8. **Реальный `check.php`** для остальных типов (эталон уже стоит,
   «Новости ИТ и ИБ» — готов).
9. **`_templates/config.json`** — пока справка, не читается из `check.php`.
10. **AJAX для типов проектов** — сейчас POST + `redirect()`.
11. **Вес в старой модели `grades`** — `weight` принимается, но в
    `teacher.php` / старой модалке `lessons/grades` UI пока не выведен.
12. **Показ взвешенного среднего** в сводке учителя и личном кабинете
    студента — `data.php` уже отдаёт `weighted_avg`, но в `teacher.php`
    и `student.php` он не выводится. Только сырые оценки + бейджи.
13. **Бейдж веса в модалке** `jm-weights` — есть. **В сводке учителя** —
    нет.

---

## 9. Что было в этом чате

1. Разбиение `index.php` на модули.
2. LMS → Приказ.
3. БД вне webroot.
4. Страница входа в славянском стиле.
5. Сущности: год → семестр → группа → предмет → темы → расписание → праздники.
6. КТП: блоки, пары, подгруппы, tariff/slots, праздники, «Фактически часов».
7. Студенты: расширены, CRUD, массовое добавление, обязательная группа.
8. Личный кабинет студента (`profile.php`).
9. `actions.php` разбит на 10 файлов.
10. Пароль админа — только `config.php`.
11. `prikaz_config()` — `$GLOBALS['PRIKAZ_CONFIG']` с fallback.
12. **Типы проектов (`v.0.1.9.25`)** — `project_types` +
    `project_type_phases` + `project_type_checks`. CRUD, кнопки
    «Проверить» / «Пересоздать папку» / «Удалить».
13. **CI/CD-папка `projects/`** с `.htaccess`, `_templates/check.php`,
    `_templates/config.json`, `_templates/README.md`.
14. **`lib/project_types.php`** — `project_type_*()`, `project_type_check()`,
    `project_type_render_files()`, `project_type_validate_code()`,
    `project_type_phase_palette()`, `project_type_next_phase_color()`.
15. **`lib/projects.php`** — `project_types_meta()` из БД с fallback,
    `project_phases_for()`, `project_current_phase_num()`,
    `project_history_rows()` через `COALESCE`, `project_change_phase()`
    через `type_phase_id`.
16. **`lib/actions/project_types.php`** — `admin_project_type_*`,
    `admin_project_type_phase_*`. `actions.php` — `admin_project_type_`
    ДО `admin_project_`.
17. **`config.php`** — `gogs_base_url`, `gogs_token`, `gogs_owner`,
    `default_grade_weight`, `auto_grade_weight`, `min_weight_for_final`.
18. **Цвета фаз типа проекта (`v.0.1.9.26`)** — палитра, «следующий по
    очереди», `$renderPhaseColor()`, hex-нормализация.
19. **`check.php` для «Новости ИТ и ИБ»** — 10 проверок, dry-run/apply,
    squash merge / close / comment через Gogs API.
20. **Студенты убраны из «Пользователей»** — `data.php` (фильтр по
    `students.user_id` + роль `student`), `lib/actions/users.php`
    (guard `$__guardNotStudent`), `lib/views/admin/users.php`
    (селект без `student`, поля группы/алиаса убраны).
21. **Вес оценки (`v.0.1.10.26`)** — `journal_marks.weight`, миграция
    в `db/init.php`, `journal_mark_apply(... int $weight ...)`,
    `journal_marks_map()` с весом, `aggregate_journal()` с взвешенным
    средним и `is_preliminary`, `data.php` с `$journalMarkRowsByStudent`
    и `perStudentSummary['weighted_avg']`.
22. **UI веса** — модалка `#jm-weights` с кнопками 10/5/3/1,
    `syncWeightButtons()`, `fd.append('weight', …)`; бейджи
    `pzk-weight--low/mid/high` в `_journal_grid.php` и
    `_journal_student.php`; `data-weight` в ячейках сетки.
23. **`_journal_grid.php`** — `data-weight` в `td`, бейдж рядом с
    числовой оценкой, бейдж в правой панели (примечания к оценкам).
24. **`_journal_student.php`** — бейдж в полосе оценок и в карточках
    «Темы журнала».
25. **`lib/views/journal.php`** — блок `.journal-modal__weights` +
    `.journal-weight-btn`, `<style>` с `.pzk-weight*`.

---

## 10. Быстрый старт

CLI на shared-хостинге нет. Открывать `db/init.php` в браузере.

`https://<host>/prikaz/db/init.php` → «Готово». БД создастся в
`prikaz-data/`. Учётные данные админа — из `config.php`. После — удалить
`init.php`.

`?page=login` — вход.

**Папка `projects/`** должна быть записана для PHP-FPM. Если её нет —
создать вручную с `.htaccess` и `_templates/check.php` (эталон). При
первом «Проверить» тип создаст `projects/<code>/check.php` из шаблона.

**Gogs-ключи** (`gogs_base_url`, `gogs_token`, `gogs_owner`) — в `config.php`,
иначе реальные CI/CD-проверки не пойдут.

**Веса оценок** — `default_grade_weight` (по умолчанию 10),
`auto_grade_weight` (1), `min_weight_for_final` (10). Миграция
`journal_marks.weight` идёт автоматически через `db/init.php`.

---

## 11. Что делать в новом чате

**Если «поправить / добавить»:**

1. Проверить, что нужные файлы присланы целиком. Правки по админке —
   `lib/views/admin/<section>.php` или партиалы. Логика —
   `actions/<domain>.php`, `data.php`, `ktp.php`, `curriculum.php`,
   `students.php`, `projects.php`, `project_types.php`, `journal.php`.
   CI/CD-эталон — `projects/_templates/check.php`.
   Сетки журнала — `_journal_grid.php`, `_journal_student.php`.
2. Если чего-то не хватает — попросить.
3. Выдать файл целиком, **с кратким путём и именем файла перед кодом**.
4. Если правится одна секция — не трогать диспетчер и общие
   `_styles.php` / `_scripts.php`, если правка не касается их.

**Если «показать на демо»** — помнить:

- праздники в карточках семестров, вычитаются из «Фактически часов» КТП;
- расписание на семестр, одна запись на ячейку, подгруппы в названии группы;
- КТП с блоками, фильтром, столбцом «№ по типу»;
- студенты с обязательной группой при массовом добавлении, **и их нет
  в «Пользователях»**;
- пароль админа — только `config.php`;
- личный кабинет студента — `?page=profile`, не в `?page=student`;
- **типы проектов** — `?page=admin&section=project_types`, у каждого свои
  фазы (с цветами), своя папка `projects/<code>/` с `check.php`;
- **вес оценки** — в модалке журнала 10/5/3/1, взвешенный средний
  в агрегатах (`weighted_avg`), бейдж рядом с оценкой.

**Если «доработать»** — см. «Открытые задачи» (особенно п. 11–13: вес
в старой модели, показ взвешенного в `teacher.php`/`student.php`).

**Перед выдачей файла целиком** — пробежаться по ловушкам (52) и текущим
сущностям: `semesters`, `holidays`, `schedule_entries`, `subjects`,
`curriculum`, `ktp_entries`, `students`, `journal_marks`,
`journal_lesson_notes`, `project_types`, `project_type_phases`,
`project_type_checks`. Свериться, что хелперы секций не потерялись,
`bootstrap.php` требует `project_types.php`, `actions.php` держит
`admin_project_type_` **до** `admin_project_`, а `data.php` фильтрует
студентов из `$adminUsers`.

**Если «сгенерируй README / context»** — в обёртке ```` (четыре обратные
кавычки), иначе внутренние блоки кода ломают скачивание.