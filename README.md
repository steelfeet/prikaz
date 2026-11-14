# Приказ

**PAMS — Project & Attestation Management System.**
Система управления проектами и аттестацией для колледжа.

Приказ — не LMS и не CRM. LMS управляет контентом (курсы, уроки, тесты) —
здесь контента нет, есть проекты и учебный процесс. CRM управляет отношениями
с клиентами — здесь не клиенты, а студенты и проекты. Приказ ближе к PPM
(Project Portfolio Management) в корпоративном мире, но со спецификой
учебного процесса: журнал, оценки, проектная работа, разграничение доступа
к персональным данным, расписание занятий, календарно-тематическое
планирование, личные кабинеты студентов.

Технически: PHP 8.2 + SQLite, один каталог `prikaz/`, база вне webroot
в `prikaz-data/`. Без фреймворков: чистый PHP, PDO, небольшой слой хелперов.
Сессии, CSRF, аудит — вручную. Разворачивается на shared-хостинге копированием
файлов.

**Текущая версия: v.0.1.9.25** — типы проектов, фазы типа, CI/CD-проверки
в папке `projects/`.

---

## Что нового в v.0.1.9.25

- Сущность **Тип проекта** (`project_types`). Редактируется администратором
  в разделе «🎓 Типы проектов».
- У каждого типа — **свои фазы** (`project_type_phases`), настраиваются там же.
- Проект привязывается к типу через `projects.project_type_id`. История
  переходов пишется в `project_history.type_phase_id`.
- Для каждого типа — **своя папка CI/CD** `projects/<code>/` с файлом
  `check.php`. При создании типа туда автоматически копируется эталон
  из `projects/_templates/check.php`.
- В админке у типа есть кнопки:
  - **✅ Проверить** — запускает `projects/<code>/check.php` с контекстом
    `$ctx` (тип, Gogs, проекты этого типа) и пишет результат в
    `project_type_checks`;
  - **♻ Пересоздать папку** — принудительно перезаписывает `check.php`
    из эталона (ручные правки теряются);
  - **Удалить** — отказ, если к типу привязаны проекты; папка остаётся
    на диске.
- Новые ключи в `config.php`: `gogs_base_url`, `gogs_token`, `gogs_owner`.
  Без них реальные запросы к Gogs не пройдут (эталон `check.php` вернёт
  ошибки в `errors`).
- Старая модель (глобальные `project_phases`, `projects.type` строкой,
  `project_history.phase_id`) сохранена как fallback для проектов
  с `project_type_id IS NULL`.

---

## Что входит в Приказ

| Блок | Что это |
|---|---|
| Учебный процесс | Учебные годы, семестры, группы, предметы, темы, расписание, праздники |
| КТП | Календарно-тематическое планирование: распределение тем по датам в соответствии с расписанием, с учётом праздников |
| Журнал | Сетка «студенты × занятия», оценки 2–5 (с минусом и примечаниями), «н»/«ну», обновление без перезагрузки |
| Студенты | Личные дела, массовое добавление, личный кабинет (email, VK, Telegram, пароль) |
| Проекты | Типы проектов, фазы типа, участники, история переходов, CI/CD-проверки по каждому типу |
| Аттестация | Фазы проектов и переходы между ними, сводка по студентам и группам |
| Родители | Привязка к студенту, просмотр журнала и проектов, фиксация визитов |
| Админка | Пользователи, студенты, родители, уроки, типы проектов, проекты, аудит |
| Аудит | Лог действий с IP, привязка к пользователю, типу и id цели |

---

## Требования

- PHP 8.2+ с расширениями `pdo_sqlite`, `curl`, `mbstring`, `json`.
- Web-сервер (Apache / Nginx + PHP-FPM).
- Права на запись:
  - `prikaz-data/` — для SQLite и `diag.log`;
  - `projects/` — для создания `projects/<code>/check.php` (если папки нет,
    создаётся при первом «Проверить»).
- Опционально: доступ к Gogs (для реальных CI/CD-проверок).

---

## Быстрый старт

1. Скопировать каталог `prikaz/` на хостинг.
2. Настроить `config.php` (см. ниже).
3. Открыть в браузере `https://<host>/prikaz/db/init.php` → «Готово».
   БД создастся в `prikaz-data/`. Учётные данные админа — из `config.php`.
4. Удалить `db/init.php`.
5. Войти: `?page=login`.
6. Создать учебный год, семестры, группы, предметы, темы, расписание, КТП.
7. Настроить **типы проектов** в `?page=admin&section=project_types`.

CLI на shared-хостинге обычно нет — `init.php` открывается в браузере.

---

## config.php

```php
return [
    'app_name'      => 'Приказ',
    'db_path'       => __DIR__ . '/prikaz-data/prikaz.sqlite',
    'storage_path'  => __DIR__ . '/prikaz-data',
    'password_min'  => 4,
    'csrf_key'      => '…',
    'session_name'  => 'prikaz_session',

    // Пароль администратора — только здесь, не в БД.
    'admin_login'    => 'admin',
    'admin_password' => '…',

    // Gogs — для CI/CD-проверок типов проектов.
    'gogs_base_url' => 'https://gogs.example',
    'gogs_token'    => '…',          // personal access token, scope repo (read)
    'gogs_owner'    => 'kolledzh',   // fallback, если у типа не задан gogs_owner
];
```

- Пароль администратора хранится **только** в `config.php`. Через веб
  сменить его нельзя. `admin_user_reset_password` / `admin_user_toggle`
  для админа — отказ. `admin_user_create` с логином `admin` — отказ.
- Пароли остальных пользователей — `password_hash(PASSWORD_DEFAULT)`
  в `users.password_hash`, авто-рехэш при входе.
- `gogs_*` нужны только если планируется запускать реальные CI/CD-проверки.
  Эталон `check.php` без них вернёт ошибки.

---

## Структура

```
prikaz/
  index.php              — роутер (?page=…), ob_start(), bootstrap, views
  config.php             — настройки (см. выше)
  bootstrap.php          — конфиг, сессия, db(), csrf, auth, audit, require_once lib/*
  actions.php            — POST-диспетчер ($action → lib/actions/<domain>.php)
  data.php               — загрузка данных для рендера
  nav.php                — меню (admin / teacher / parent / student)
  profile.php            — личный кабинет (все роли)
  student.php            — страница студента (проекты, журнал)
  help_widget.php        — плавающая «?»
  lib/
    utils.php            — e(), redirect(), json_response(), flash_*, wants_json(),
                           hash_password, prikaz_config, prikaz_is_admin_login,
                           prikaz_check_admin_password, parse_topic_lines, topic_key
    journal.php          — агрегаты журнала, сетка
    schedule.php         — schedule_*
    ktp.php              — ktp_* (блоки, праздники, layout, generate, add_more, delete)
    curriculum.php       — curriculum_*
    students.php         — students_*, parse_student_lines, student_*
    projects.php         — project_phases_meta, project_types_meta, project_phases_for,
                           project_current_phase_num, project_history_rows,
                           project_students_rows, project_change_phase
    project_types.php    — project_types_dir, project_types_templates_dir, project_type_dir,
                           project_types_all, project_type_by_id, project_type_by_code,
                           project_type_phases, project_type_checks_recent,
                           project_type_validate_code, project_type_save, project_type_delete,
                           project_type_render_files, project_type_check
    actions/
      auth.php           — logout, change_own_password
      journal.php        — grade_set, journal_mark_set, journal_lesson_note_set
      projects.php       — project_move_phase
      project_types.php  — admin_project_type_* (save/delete/regen_folder/check)
                           + admin_project_type_phase_* (save/delete)
      academic.php       — admin_academic_year_*, admin_group_*, admin_semester_*,
                           admin_holiday_*
      subjects.php       — admin_subject_*, admin_curriculum_*, admin_ktp_*
      schedule.php       — admin_schedule_*
      users.php          — admin_user_*
      students.php       — admin_student_*, admin_parent_*, student_profile_save
      lessons.php        — admin_lesson_*
      misc.php           — help_feedback
  lib/views/
    admin.php            — диспетчер секций (?page=admin&section=…)
    admin/academic_years.php, groups.php, subjects.php, schedule.php,
          users.php, students.php, parents.php, lessons.php,
          projects.php, project_types.php, audit.php
    admin/_topics_block.php, _ktp_block.php
    admin/_styles.php, _scripts.php
    login.php, journal.php, teacher.php, student.php, parent.php, profile.php, …
  db/init.php            — инициализация/сброс БД (в браузере)
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

---

## Роли

| Роль | Что делает |
|---|---|
| `admin` | Пользователи, студенты, родители. Учебный процесс (годы, семестры, группы, предметы, темы, расписание, КТП, праздники), уроки, **типы проектов и их фазы**, проекты, аудит. Пароль — из `config.php` |
| `teacher` | Журнал (оценки, заметки), фазы проектов, сводка, активность родителей |
| `student` | Свои оценки, заметки, проекты, фазы. Личный кабинет: email, VK, Telegram, пароль (не логин/ФИО) |
| `parent` | Журнал и проекты своего ребёнка. Факт захода фиксируется |

Самостоятельной регистрации нет.

---

## Учебный процесс

Меню: «📅 Учебный процесс» → «Учебный год», «Группы», «Предметы и темы».

- **Учебный год** (`academic_years`): `name`, `start_date`, `end_date`,
  `is_current` (UNIQUE INDEX). При создании — семестры «1 семестр»
  и «2 семестр».
- **Семестры** (`semesters`): `academic_year_id`, `name`, `is_practice`, `ord`.
- **Праздники** (`holidays`): `semester_id`, `date`, `name`.
  UNIQUE `(semester_id, date)`. Влияют на КТП (вычитаются из «Фактически часов»).
- **Группы** (`groups`): `academic_year_id`, `name`, `curator`, `notes`.
  UNIQUE `(academic_year_id, name)`. Inline-редактирование под строкой.
- **Предметы** (`subjects`): `semester_id`, `group_id`, `short_name`,
  `full_name`, `hours`. UNIQUE `(semester_id, group_id, short_name)`.
  Inline-редактирование под строкой.
- **Темы** (`curriculum`): `subject_id`, `ord`, `topic`.
  UNIQUE `(subject_id, ord)`. Inline, «+ Добавить ниже», всё AJAX.

### КТП

Одна тема = один блок. Групповой блок — пара (1,2), (3,4)… Подгрупповой
блок — все `sub1`/`sub2` дня = один блок. Праздники пропускаются.
Шапка: «Часов по учебному плану / По тарификации / В КТП / Фактически часов»
+ список праздников + фильтр «Все / Лекция / Практикум».
Столбец «№ по типу» появляется при фильтре. Sticky-шапка и `thead th`.
Все операции — AJAX.

---

## Журнал

Сетка «студенты × занятия». Оценки 2, 3, 4, 5 с примечаниями и минусом
(кроме 2), «н» / «ну». Примечание по текущему уроку, Д.3 в примечаниях.
Тема и ДЗ по клику на дату. Обновление без перезагрузки. Студент видит
только свой журнал с общими примечаниями и своими.

---

## Студенты

Раздел `?page=admin&section=students`.

- Обязательные поля: Фамилия, Имя, Логин (совпадает с логином в Gogs),
  Пароль, Email. Необязательные: Отчество, VK, Telegram.
- Фильтры: учебный год, группа.
- Inline-редактирование под строкой (включая опциональную смену пароля).
- Удаление — каскад через `users`.
- Массовое добавление: `Фамилия Имя Отчество, логин` по строке. Группа
  обязательна. Пароль по умолчанию `1111`, email `<логин>@mail.ru`.

Личный кабинет студента — `?page=profile`: email, VK, Telegram, пароль.

---

## Проекты и типы проектов

### Старая модель

Глобальные фазы (`project_phases`), `projects.type` строкой
(`original` / `fork` / …), `project_history.phase_id`. Используется только
для проектов, у которых `projects.project_type_id IS NULL`.

### Новая модель (v.0.1.9.25)

Раздел `?page=admin&section=project_types`, пункт меню «🎓 Типы проектов».

**Таблицы:**

- `project_types`: `id`, `code` (UNIQUE, `[a-z][a-z0-9_-]{1,31}`), `name`,
  `description`, `color`, `icon`, `gogs_owner`, `gogs_repo_prefix`,
  `created_at`, `updated_at`.
- `project_type_phases`: `id`, `project_type_id`, `ord`, `name`, `color`,
  `description`. UNIQUE `(project_type_id, ord)`.
- `project_type_checks`: `id`, `project_type_id`, `checked_by`, `ok`,
  `errors` (json), `warnings` (json), `details` (json), `checked_at`.
- `projects.project_type_id` — nullable, FK → `project_types.id`.
- `project_history.type_phase_id` — nullable, FK → `project_type_phases.id`.

**Действия на карточке типа:**

| Кнопка | Что делает |
|---|---|
| Редактировать | обновляет запись; папку не трогает |
| ➕ Добавить | создаёт тип + `projects/<code>/check.php` из `_templates/` |
| ✅ Проверить | `include projects/<code>/check.php` с `$ctx`, ждёт массив `['ok','errors','warnings','details']`, пишет в `project_type_checks`, показывает flash |
| ♻ Пересоздать папку | принудительно копирует `_templates/check.php` → `projects/<code>/check.php` (ручные правки теряются) |
| Удалить | отказ, если есть проекты этого типа; папку не трогает |

**Фазы типа:** добавить / редактировать / удалить.

### CI/CD-папка `projects/`

- Закрыта `.htaccess` от прямого доступа.
- `_templates/check.php` — эталон-заглушка с большим комментарием сверху:
  структура `$ctx`, формат возврата, примеры Gogs API, TODO 1–5.
- `_templates/config.json` — параметры проверки (справка, пока не читается).
- `_templates/README.md` — инструкция: открыть `check.php` в панели хостинга
  или скачать, скопировать в чат ИИ, описать, что проверять, получить
  новый `check.php`, положить обратно, нажать «Проверить».
- `projects/<code>/check.php` — рабочая копия. При редактировании типа
  **не перезаписывается**. Принудительно — кнопкой «♻ Пересоздать папку».

**`$ctx`, который получает `check.php`:**

```
$ctx['type']     = project_types row + gogs_owner / gogs_repo_prefix
$ctx['gogs']     = ['base_url' => config, 'token' => config,
                    'owner'    => type.gogs_owner ?: config.gogs_owner]
$ctx['projects'] = [{id,title,repo_url}, ...]  // неархивные проекта этого типа
$ctx['dir']      = путь к projects/<code>/check.php
```

Файл должен **вернуть** массив:
`['ok' => bool, 'errors' => [], 'warnings' => [], 'details' => []]`.
Если вернул не массив — `project_type_check()` подставит
`['ok'=>false, 'errors'=>['check.php не вернул массив.'], …]`.

**Gogs API** — в шапке `check.php`: получение открытых PR, изменённых файлов,
raw-содержимого, списка файлов ветки. Personal access token:
Settings → Applications → Generate New Token, scope `repo` (read).

---

## Админка

Разделы: «📖 Журнал», «📅 Учебный процесс» (годы, группы, предметы и темы),
«🎓 Типы проектов», «📆 Расписание», «👥 Пользователи», «🎒 Студенты»,
«👪 Родители», «📓 Уроки», «📋 Проекты», «📜 Аудит».

Диспетчер `lib/views/admin.php`, секции `lib/views/admin/*.php`, партиалы
`_topics_block.php` / `_ktp_block.php`. Общие `_styles.php`, `_scripts.php`.

---

## Безопасность

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
| `projects/` | Закрыт `.htaccess` от прямого доступа |
| CI/CD `check.php` | Выполняется через `include`; код пишет админ; Gogs-токен — в `config.php` |

**Не сделано:** восстановление пароля по email, rate limiting, 2FA,
согласие на ПДн.

---

## Известные ограничения и ловушки

- **SQLite**, не MySQL. `GROUP_CONCAT` с одинарными кавычками,
  `COLLATE NOCASE`, `PRAGMA foreign_keys = ON`.
- **BOM в PHP-файле** ломает `<?php`. UTF-8 без BOM.
- **Opcache** кэширует `actions.php` / `bootstrap.php` / `utils.php`.
  Перезапуск PHP-FPM или `diag.php?opcache_reset=1`.
- **`actions.php` не заканчивается на `?>`.** Перевод строки после — в буфер.
- **Пароль админа — только `config.php`.** `password_verify` для админа
  не работает.
- **`actions.php`: `admin_project_type_` должен идти ДО `admin_project_`.**
  Иначе `str_starts_with` поймает `admin_project_` первым.
- **`project_type_check()`** делает `include` через замыкание, `$ctx`
  в области видимости. Файл должен вернуть массив. Пишет в
  `project_type_checks` (`json_encode` для `errors`/`warnings`/`details`,
  `JSON_UNESCAPED_UNICODE`).
- **`project_type_render_files($typeId, $pdo, $overwrite=false)`** — если
  `$overwrite=false` и файл уже есть, не перезаписывает. Используется
  в `project_type_check()` для ленивого создания `check.php`.
- **`projects/`** должен быть записан для PHP-FPM. Если папки нет —
  `project_type_render_files` создаст её `mkdir(0775, true)`.
- **`project_types_meta()`** — из БД, fallback на статические 5. Если таблицы
  ещё нет — молча отдаст статический список.
- **`project_phases_for($projectId)`** — если `projects.project_type_id > 0`,
  берёт фазы типа (`project_type_phases.ord`). Иначе — глобальные
  (`project_phases.num`). `project_current_phase_num()` и
  `project_history_rows()` используют `COALESCE(pph.num, ptph.ord)`.
- **`project_change_phase()`** — для проекта с типом пишет `type_phase_id`,
  иначе `phase_id`. В `project_history` оба поля nullable.
- **`diag.log`** — пишется `auth_attempt` в `dirname(PRIKAZ_DB_PATH)`.
  Удалять после отладки.
- **`_student_profile_form.php`** — устаревший партиал, нигде не
  подключается. Удалить.

---

## Открытые задачи

1. Экспорт журнала — XLSX.
2. Тесты — не написаны.
3. Нагрузочные прогоны — до 100 параллельных.
4. Ревизия наследия CRM — удалить.
5. Печать расписания — не сделано.
6. Кэш `ktp_lesson_blocks` — при 50+ предметах.
7. Удалить `_student_profile_form.php`.
8. Реальный `check.php` для типов проектов — эталон-заглушка уже стоит
   в `_templates/check.php`, TODO 1–5 (PR, изменённые файлы, spec.md,
   уникальность, `$details`). Нужно заполнить хотя бы для одного типа
   и убедиться, что `gogs_base_url` / `gogs_token` / `gogs_owner` заполнены.
9. `_templates/config.json` — пока справка, не читается из `check.php`.
   Подключить, когда появится реальная проверка.
10. AJAX для типов проектов — сейчас POST + `redirect()`. Можно перевести
    на `ajaxSubmit()`, как темы и КТП.

---

## Лицензия

Внутренний проект колледжа. Условия использования — по договорённости
с правообладателем.