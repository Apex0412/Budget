# FINANSES — система заявок на закупки

Полноценное веб-приложение для муниципального учреждения: учёт заявок на закупку, контроль статусов, аудит действий и формирование официальных PDF‑документов. Основной стек — **PHP 8.2 + Apache + MySQL 8 + Composer + Bootstrap 5 + Vanilla JS + mPDF**. Проект запускается «из коробки» на Windows (включая XAMPP и WSL 2), Linux, macOS и в Docker.

> 💡 Скриншоты интерфейса, архитектурные схемы и API-спецификация находятся в папке [`docs/`](finanses/docs).

---

## 📚 Оглавление
- [О проекте](#-о-проекте)
- [Что понадобится](#-что-понадобится)
- [Быстрый старт](#-быстрый-старт)
- [Установка по платформам](#-установка-по-платформам)
  - [A. Windows (XAMPP)](#a-windows-xampp)
  - [B. Windows + WSL 2 (Ubuntu)](#b-windows--wsl-2-ubuntu)
  - [C. Linux (Ubuntu/Debian)](#c-linux-ubuntudebian)
  - [D. macOS (Homebrew)](#d-macos-homebrew)
  - [E. Docker](#e-docker)
  - [F. Nginx + PHP-FPM (опционально)](#f-nginx--php-fpm-опционально)
  - [G. HTTPS (self-signed, опционально)](#g-https-self-signed-опционально)
- [.env шаблоны](#-env-шаблоны)
- [Полезные команды](#-полезные-команды)
- [Частые ошибки и решения](#-частые-ошибки-и-решения)
- [Проверка после установки](#-проверка-после-установки)
- [Удаление или обновление](#-удаление-или-обновление)
- [Acceptance / Smoke checklist](#-acceptance--smoke-checklist)

---

## ℹ️ О проекте
- **Назначение:** фиксация служебных записок, согласование, печать и выгрузка закупочных заявок.
- **Основные модули:**
  - роли *admin / user / viewer*, смена темы (light/dark), смена пароля;
  - заявки с позициями, статусами, приоритетами, дедлайнами, комментариями и файлами;
  - каталоги материалов/категорий/единиц измерения, быстрый поиск;
  - генерация служебной записки в PDF (mPDF, кириллица, бренд МБУ);
  - аудит действий, отчёты (Chart.js), экспорт CSV/XLSX, healthcheck-эндпоинты;
  - API REST (JSON) + OpenAPI 3.0 (`docs/openapi.yaml`).
- **Стек:** PHP 8.2, Apache, MySQL 8 (utf8mb4), Composer, Bootstrap 5, Vanilla JS, mPDF, vlucas/phpdotenv.
- **Деплой:** Docker (php:8.2-apache + mysql:8), XAMPP, WSL 2/Ubuntu, Linux, macOS, Nginx + PHP-FPM.

## ✅ Что понадобится
- Свободный порт **8080** (Docker) или **80** (локальный Apache/Nginx), порт **3306** для MySQL.
- Права администратора / `sudo` для установки пакетов и служб.
- Интернет для загрузки пакетов и зависимостей Composer.
- В Docker Desktop включите WSL 2 backend (Windows) и Virtualization.
- Без Docker: PHP 8.2 с расширениями **pdo_mysql, mysqli, gd, intl, mbstring, bcmath, exif, zip, soap** (рекомендуются gmp, pcntl).

## ⚡ Быстрый старт

### 1. Windows + Docker Desktop (самый быстрый путь)
1. Скачайте проект (ZIP или Git) и распакуйте в `C:\finanses`.
2. Откройте **PowerShell от имени администратора**.
3. Разрешите временно выполнение скриптов:
   ```bash
   Set-ExecutionPolicy -Scope Process Bypass
   ```
4. Запустите автоматизацию:
   ```bash
   C:\finanses\finanses\scripts\windows\docker-up.ps1
   ```
5. Дождитесь сообщения `Готово!` и откройте http://localhost:8080.
6. Авторизуйтесь: **логин** `admin`, **пароль** `admin123` (при первом входе система попросит сменить пароль).

### 2. Docker (Linux/macOS/WSL)
```bash
cd /path/to/finanses/finanses
./scripts/unix/docker-up.sh
# После завершения откройте http://localhost:8080 (admin / admin123)
```

### 3. Минимум команд для WSL/Ubuntu
```bash
sudo apt update && sudo apt install -y apache2 mysql-server php8.2 php8.2-cli libapache2-mod-php8.2 \
    php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd \
    php8.2-intl php8.2-bcmath php8.2-exif php8.2-soap php8.2-gmp composer unzip git
cd /var/www/html
sudo git clone https://github.com/Apex0412/Budget.git finanses
cd finanses/finanses
composer install
php database/cli.php migrate
php database/cli.php seed
sudo systemctl restart apache2
```

---

## 🛠️ Установка по платформам

### A. Windows (XAMPP)
**Шаг 1.** Скачайте [XAMPP](https://www.apachefriends.org/ru/index.html) и установите (оставьте галочки Apache, MySQL, PHP, phpMyAdmin).

**Шаг 2.** Запустите *XAMPP Control Panel*, включите Apache и MySQL (зелёные индикаторы).

**Шаг 3.** Получите исходники:
- **Git:**
  ```bash
  cd C:\xampp\htdocs
  git clone https://github.com/Apex0412/Budget.git finanses
  ```
- **ZIP:**
  ```bash
  cd C:\xampp\htdocs
  curl -L -o finanses.zip https://github.com/Apex0412/Budget/archive/refs/heads/work.zip
  tar -xf finanses.zip
  move Budget-work\finanses finanses
  ```
  > Проверьте структуру: `C:\xampp\htdocs\finanses\public\index.php`.

**Шаг 4.** Установите [Composer for Windows](https://getcomposer.org/download/). Проверьте:
```bash
composer -V
```

**Шаг 5.** Подготовьте БД через phpMyAdmin:
```bash
# В браузере откройте http://localhost/phpmyadmin
# Создайте БД finanses (utf8mb4_unicode_ci)
# Вкладка «Импорт» → выберите finanses/database/schema.sql
```

**Шаг 6.** Создайте `.env`:
```bash
cd C:\xampp\htdocs\finanses
copy .env.example .env
# Отредактируйте блок Local (DB_USER=root, DB_PASS пустой)
```

**Шаг 7.** Установите зависимости и выполните миграции:
```bash
cd C:\xampp\htdocs\finanses
composer install
php database/cli.php migrate
php database/cli.php seed
```

**Шаг 8.** Проверьте запуск: откройте http://localhost/finanses/public.

**Мини-траблшутинг:**
- Порт 80 занят → в XAMPP настройте Apache на 8080 (Config → httpd.conf → `Listen 8080`).
- `Access denied for user 'root'@'localhost'` → в phpMyAdmin измените пароль root или используйте `mysql -u root`.
- Не работают ЧПУ → включите `mod_rewrite` (Apache → Config → httpd.conf → раскомментируйте `LoadModule rewrite_module`).

### B. Windows + WSL 2 (Ubuntu)
**Шаг 1.** Проверьте WSL 2 и виртуализацию:
```bash
wsl --status
```
Если виртуализация отключена — включите *Virtual Machine Platform* и *Windows Subsystem for Linux* (через «Включение компонентов Windows»), перезагрузитесь.

**Шаг 2.** Установите Ubuntu из Microsoft Store, запустите и обновите систему:
```bash
sudo apt update && sudo apt upgrade -y
```

**Шаг 3.** Поставьте стек LAMP и Composer:
```bash
sudo apt install -y apache2 mysql-server php8.2 php8.2-cli libapache2-mod-php8.2 \
    php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd \
    php8.2-intl php8.2-bcmath php8.2-exif php8.2-soap php8.2-gmp composer unzip git
```

**Шаг 4.** Скачайте проект:
```bash
cd /var/www/html
sudo git clone https://github.com/Apex0412/Budget.git finanses
sudo chown -R $USER:www-data finanses
```

**Шаг 5.** Права и каталоги:
```bash
cd /var/www/html/finanses
sudo chmod -R 775 storage
```

**Шаг 6.** Запустите MySQL и создайте БД:
```bash
sudo service mysql start
sudo mysql -e "CREATE DATABASE finanses CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
Если видите `Access denied` — войдите `sudo mysql` и выполните `ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';`.

**Шаг 7.** Импортируйте схему:
```bash
mysql -u root -p finanses < database/schema.sql
```

**Шаг 8.** Настройте `.env` (локальный блок):
```bash
cp .env.example .env
# DB_HOST=127.0.0.1, DB_USER=root, DB_PASS= (пусто)
```
Если база в XAMPP (Windows), используйте IP шлюза из `cat /etc/resolv.conf`.

**Шаг 9.** Миграции и сиды:
```bash
composer install
php database/cli.php migrate
php database/cli.php seed
```

**Шаг 10.** Виртуальный хост Apache (`/etc/apache2/sites-available/finanses.conf`):
```bash
sudo tee /etc/apache2/sites-available/finanses.conf <<'CONF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/finanses/public
    <Directory /var/www/html/finanses/public>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/finanses_error.log
    CustomLog ${APACHE_LOG_DIR}/finanses_access.log combined
</VirtualHost>
CONF
sudo a2ensite finanses.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

**Шаг 11.** Проверьте http://localhost/finanses/public.

**Мини-траблшутинг:**
- Ошибка `HCS_E_SERVICE_NOT_AVAILABLE` → проверьте включенные компоненты Windows (см. Шаг 1).
- Нет доступа к стилям → убедитесь в `AllowOverride All` и активном `mod_rewrite`.
- Нужно подключиться к MySQL в Windows → используйте адрес `host.docker.internal` или IP из `ipconfig` (Windows) / `cat /etc/resolv.conf` (WSL).

### C. Linux (Ubuntu/Debian)
Последовательность аналогична WSL, но без особенностей Windows:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mysql-server php8.2 php8.2-cli libapache2-mod-php8.2 \
    php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd \
    php8.2-intl php8.2-bcmath php8.2-exif php8.2-soap php8.2-gmp composer unzip git
cd /var/www/html
sudo git clone https://github.com/Apex0412/Budget.git finanses
sudo chown -R $USER:www-data finanses
cd finanses
composer install
php database/cli.php migrate
php database/cli.php seed
sudo tee /etc/apache2/sites-available/finanses.conf <<'CONF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/finanses/public
    <Directory /var/www/html/finanses/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
CONF
sudo a2ensite finanses.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```
Откройте http://localhost.

### D. macOS (Homebrew)
**Шаг 1.** Установите Homebrew (если ещё нет): [https://brew.sh](https://brew.sh).

**Шаг 2.** Установите пакеты:
```bash
brew install php apache2 mysql composer git
sudo apachectl start
brew services start mysql
```

**Шаг 3.** Скопируйте проект:
```bash
cd /usr/local/var/www
git clone https://github.com/Apex0412/Budget.git finanses
cd finanses/finanses
composer install
```

**Шаг 4.** Настройте `httpd.conf` (Apache):
- Включите `LoadModule rewrite_module modules/mod_rewrite.so`.
- Добавьте виртуальный хост:
```bash
sudo tee /usr/local/etc/httpd/vhosts/finanses.conf <<'CONF'
<VirtualHost *:80>
    DocumentRoot "/usr/local/var/www/finanses/finanses/public"
    ServerName localhost
    <Directory "/usr/local/var/www/finanses/finanses/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
CONF
sudo apachectl restart
```

**Шаг 5.** Создайте БД и выполните миграции (см. Linux-процедуру). Откройте http://localhost.

### E. Docker
**Шаг 1.** Убедитесь, что Docker Desktop (Windows/macOS) или Docker Engine (Linux) запущены.

**Шаг 2.** Запустите скрипт:
```bash
cd /path/to/finanses/finanses
./scripts/unix/docker-up.sh              # Linux/macOS/WSL
# или
powershell -ExecutionPolicy Bypass -File scripts/windows/docker-up.ps1   # Windows
```
Скрипт автоматически:
- создаёт `.env` (при необходимости),
- собирает образы (`php:8.2-apache`, `mysql:8`),
- ждёт готовности MySQL и приложения (healthcheck),
- выполняет `composer install`, миграции и сиды,
- сообщает ссылку http://localhost:8080.

**Шаг 3.** Логин `admin`, пароль `admin123`.

**Остановка и очистка:**
```bash
# Linux/macOS/WSL
./scripts/unix/docker-clean.sh            # --all для полной очистки
# Windows
powershell -ExecutionPolicy Bypass -File scripts/windows/docker-clean.ps1
```

### F. Nginx + PHP-FPM (опционально)
```bash
sudo apt install -y nginx php8.2-fpm
sudo tee /etc/nginx/sites-available/finanses <<'CONF'
server {
    listen 80;
    server_name localhost;
    root /var/www/finanses/finanses/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /storage/(uploads|pdf)/ {
        deny all;
    }
}
CONF
sudo ln -s /etc/nginx/sites-available/finanses /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### G. HTTPS (self-signed, опционально)
```bash
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/finanses.key \
    -out /etc/ssl/certs/finanses.crt \
    -subj "/C=RU/ST=Moscow/L=Serpukhov/O=MBU/OU=IT/CN=localhost"
# Apache (VirtualHost *:443) или Nginx (listen 443 ssl) добавьте пути к сертификатам.
```

---

## 🧾 .env шаблоны
**Docker (по умолчанию):**
```bash
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080
SESSION_NAME=finanses_session
SESSION_LIFETIME=1800
USE_JWT=false
JWT_SECRET=change_me_secret
DB_HOST=db
DB_PORT=3306
DB_NAME=finanses
DB_USER=finanses
DB_PASS=finanses
FILE_STORAGE_PATH=storage/uploads
PDF_STORAGE_PATH=storage/pdf
LOG_PATH=storage/logs/app.log
MAX_UPLOAD_SIZE=5242880
ALLOWED_UPLOAD_MIME=application/pdf,image/png,image/jpeg,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
PASSWORD_POLICY_MIN_LENGTH=8
PDF_ORG_NAME="Муниципальное бюджетное учреждение \"Комбинат благоустройства\""
PDF_DIRECTOR_NAME="Кравченко К.И."
PDF_DIRECTOR_POSITION="Директор"
PDF_SIGNATURE_TITLE="Заместитель директора"
I18N_DEFAULT=ru
```

**XAMPP / локальный Apache:**
```bash
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/finanses/public
SESSION_NAME=finanses_session
SESSION_LIFETIME=1800
USE_JWT=false
JWT_SECRET=change_me_secret
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=finanses
DB_USER=root
DB_PASS=
FILE_STORAGE_PATH=storage/uploads
PDF_STORAGE_PATH=storage/pdf
LOG_PATH=storage/logs/app.log
MAX_UPLOAD_SIZE=5242880
ALLOWED_UPLOAD_MIME=application/pdf,image/png,image/jpeg,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
PASSWORD_POLICY_MIN_LENGTH=8
PDF_ORG_NAME="Муниципальное бюджетное учреждение \"Комбинат благоустройства\""
PDF_DIRECTOR_NAME="Кравченко К.И."
PDF_DIRECTOR_POSITION="Директор"
PDF_SIGNATURE_TITLE="Заместитель директора"
I18N_DEFAULT=ru
```

**WSL (MySQL в Linux):** (аналогично XAMPP, DB_HOST=127.0.0.1)

**WSL + база в Windows/XAMPP:**
```bash
DB_HOST=172.28.48.1   # IP шлюза из `cat /etc/resolv.conf`
DB_USER=root
DB_PASS=
```

**Docker Compose (production-подобный):**
```bash
APP_ENV=prod
APP_DEBUG=false
APP_URL=https://finanses.example.com
DB_HOST=db
DB_USER=finanses
DB_PASS=finanses
LOG_PATH=storage/logs/app.log
```

---

## 🧰 Полезные команды
```bash
php -v                               # проверка версии PHP
composer install                     # установка зависимостей
php database/cli.php migrate         # миграции
php database/cli.php seed            # заполнение начальными данными
docker compose up -d                # запуск Docker-сервисов
docker compose logs -f app          # просмотр логов приложения
./scripts/unix/docker-up.sh         # автоматический запуск (Linux/macOS)
./scripts/unix/docker-clean.sh --all
powershell -File scripts/windows/docker-up.ps1     # автоматический запуск (Windows)
powershell -File scripts/windows/docker-clean.ps1 -All   # полная очистка Docker на Windows
powershell -File scripts/windows/db_dump.ps1             # бэкап БД (Windows)
powershell -File scripts/windows/db_restore.ps1          # восстановление БД (Windows)
make up                              # запуск через Makefile
make db-dump                         # дамп БД (Docker)
make db-restore FILE=backups/file.sql
```
Логи приложения: `finanses/storage/logs/app.log`. Логи Apache/Nginx/MySQL см. в каталогах `/var/log/` или панелях управления.

---

## 🚑 Частые ошибки и решения
| Проблема | Решение |
| --- | --- |
| `Port 80 in use` | Освободите порт или смените на 8080 (Apache/Nginx, Docker). |
| `Access denied for user 'root'@'localhost'` | Настройте пароль root в MySQL (`ALTER USER ... IDENTIFIED WITH mysql_native_password`). |
| `HCS_E_SERVICE_NOT_AVAILABLE` (WSL) | Включите компоненты Windows и перезагрузитесь. |
| Отсутствует `ext-gd`/`ext-intl` | Пересоберите Docker (`docker compose build --no-cache`) или установите пакеты PHP (`apt install php8.2-gd intl`). |
| Healthcheck не проходит | `docker compose logs -f app db` → проверьте подключение к БД и `.env`. |
| Не грузятся стили | Проверьте `AllowOverride All` и `mod_rewrite`. |
| 404 вместо стартовой страницы | Убедитесь, что `.htaccess` в корне проекта активен. Он перенаправляет в `public/`. При отключённом mod_rewrite откройте `http://localhost/finanses/public`. |
| Upload не работает | Убедитесь, что каталог `storage/uploads` имеет права 775 и `.htaccess` запрещает выполнение PHP. |

---

## ✅ Проверка после установки
1. Откройте http://localhost:8080 (или http://localhost/finanses/public).
2. Войдите как `admin` / `admin123` → система потребует сменить пароль.
3. Создайте заявку с несколькими позициями, прикрепите файл → статус «Черновик»/«Отправлена».
4. Скачайте PDF (кнопка «PDF»), экспортируйте CSV/XLSX.
5. Зайдите в «Пользователи» и убедитесь, что CRUD и назначение ролей работают.
6. Откройте «Отчёты» (Chart.js) и «Журнал действий» (audit_log).
7. Проверьте healthcheck: http://localhost:8080/api/health.php?type=app.

---

## ♻️ Удаление или обновление
```bash
# Остановить Docker
./scripts/unix/docker-clean.sh --all
# или на Windows
powershell -ExecutionPolicy Bypass -File scripts/windows/docker-clean.ps1 -All

# Бэкап перед обновлением
./scripts/unix/db-dump.sh

# Обновление исходников (Git)
cd finanses
git pull
composer install
php database/cli.php migrate
```

Удалить проект (Docker): `docker compose down --volumes`, удалить каталог `finanses`.

---

## 🧪 Acceptance / Smoke checklist
- `docker compose build && docker compose up -d` выполняется без ошибок.
- `http://localhost:8080/api/health.php?type=app` возвращает `{"ok":true}`.
- `scripts/windows/docker-up.ps1` на чистой Windows + Docker Desktop запускает проект до формы логина.
- Вход `admin/admin123`, смена пароля и выход работают.
- CRUD заявок, загрузка файлов, генерация PDF/CSV/XLSX функционируют.
- Управление пользователями и ролями доступно только администратору.
- `./scripts/unix/db-dump.sh` и `db-restore.sh` создают/восстанавливают бэкапы.
- В `storage/logs/app.log` фиксируются действия (create_request, update_status, export_report и др.).
- Health endpoints `/api/health.php?type=app|db` отвечают корректно.
- В репозитории нет `.env`, секретов и дампов.
- Интерфейс Bootstrap 5 отображается без артефактов в актуальных браузерах.

Следуя этому руководству, даже новичок сможет запустить FINANSES на любой поддерживаемой платформе и получить готовую к работе систему заявок.
