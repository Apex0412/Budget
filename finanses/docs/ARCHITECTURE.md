# Архитектура FINANSES

## Общая структура
```
finanses/
├─ public/           # публичные PHP-страницы и API эндпоинты
├─ src/              # бизнес-логика и контроллеры (PSR-4: Finanses\)
├─ views/            # шаблоны и partials (Bootstrap 5)
├─ database/         # миграции, сидеры, CLI-инструмент
├─ storage/          # логи, файлы, PDF (защищено .htaccess)
├─ scripts/          # утилиты для Linux/macOS (scripts/unix) и Windows (scripts/windows)
├─ docs/             # документация (архитектура, деплой, безопасность)
└─ config/, vendor/  # конфигурация, автозагрузка Composer
```

## Слои
- **Публичный слой (public/):** тонкие контроллеры/страницы, которые вызывают классы из `src/Controllers`. API возвращают JSON, страницы подключают `views/layout.php`.
- **Сервисный слой (src/Controllers):** бизнес-логика, работа с PDO, валидация, аудит, PDF, загрузка файлов.
- **Инфраструктура:** `Config` (env), `Database` (singleton PDO), `Helpers` (сессии, CSRF, логи), `PdfGenerator` (mPDF).
- **Данные:** MySQL 8 с таблицами `users`, `requests`, `request_items`, `materials`, `request_files`, `audit_log`, `categories`, `units`.
- **Фронтенд:** Bootstrap 5 + Vanilla JS. Общий модуль `public/js/app.js` (toasts, API helper), специализированные модули по страницам.

## Потоки
1. Пользователь → `public/login.php` → `public/api/auth.php?action=login` → `AuthController::login()` → сессия, аудит.
2. Создание заявки → `request-form.js` → `public/api/requests.php` (POST) → `RequestController::store()` → записи в `requests`, `request_items`, аудит, при необходимости PDF.
3. Админ-панель → `admin-reports.js` / `admin-users.js` / `admin-logs.js` → соответствующие API → контроллеры в `src/Controllers`.
4. Генерация PDF → `PdfGenerator` использует mPDF и шаблон, сохраняет файл в `storage/pdf`, отмечает `pdf_generated`.
5. Аудит → каждая ключевая операция вызывает `INSERT INTO audit_log` (action, meta, IP).

## Безопасность
- Сессии PHP (httponly, SameSite=Lax), защита CSRF (`Helpers::csrfToken`, проверка в контроллерах).
- Файлы хранятся вне публичного доступа (`storage/` + .htaccess), выдача через `FileController`.
- Роль admin/user/viewer проверяется `Middleware\AuthMiddleware`.
- Пароли bcrypt, при сбросе — обязательная смена.

## Масштабирование
- Stateless PHP + внешняя MySQL → горизонтальное масштабирование через контейнеризацию.
- Кеширование статики (Apache/Nginx), возможность вынести storage в общую файловую систему или объектное хранилище.

## Мониторинг
- Health endpoints: `/api/health.php?type=app|db`.
- Логи — `storage/logs/app.log` + системные логи веб-сервера/БД.
