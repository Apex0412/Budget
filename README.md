# FINANSES — система заявок на закупки

Добро пожаловать в **FINANSES** — современное PHP‑веб-приложение (PHP 8.1 + Apache + MySQL 8 + Composer), предназначенное для автоматизации служебных записок на закупку в муниципальном учреждении. Проект «запускается из коробки», сопровождается Docker‑окружением, миграциями, сидерами и подробными инструкциями по установке на Windows (XAMPP), Windows + WSL, Linux, macOS и через Docker.

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
  - [F. Nginx + PHP-FPM](#f-nginx--php-fpm)
  - [G. HTTPS (self-signed)](#g-https-self-signed)
- [.env примеры](#-env-примеры)
- [Полезные команды](#-полезные-команды)
- [Частые ошибки и решения](#-частые-ошибки-и-решения)
- [Проверка после установки](#-проверка-после-установки)
- [Удаление или обновление](#-удаление-или-обновление)

---

## 📌 О проекте
- **Назначение:** прием, согласование и ведение закупочных заявок.
- **Технологический стек:** PHP 8.1, Apache, MySQL 8, Composer, Bootstrap 5, Vanilla JS, mPDF, vlucas/phpdotenv.
- **Главные функции:**
  - ролевая модель (admin/user/viewer), сессии и смена темы интерфейса;
  - заявки с позициями, статусами, приоритетами, дедлайнами и файлами;
  - каталоги материалов, категорий и единиц измерения;
  - генерация PDF по шаблону служебной записки (mPDF);
  - аудит действий, отчётность (Chart.js), экспорт CSV;
  - API (REST, JSON) + OpenAPI документация.
- **Варианты развёртывания:** Windows (XAMPP), Windows + WSL 2, Linux, macOS (Homebrew), Docker, Nginx + PHP-FPM.

## ✅ Что понадобится
- Свободный порт **80** (или 8080, 8000) и порт **3306** для MySQL.
- Права администратора/`sudo` для установки пакетов и служб.
- Доступ к интернету (загрузка зависимостей Composer, пакетов ОС).

## ⚡ Быстрый старт
### Docker (рекомендуется)
```bash
bash <(curl -fsSL https://get.docker.com)
docker compose up -d
docker compose ps
docker exec -it $(docker compose ps -q app) bash -lc "composer install && php database/cli.php migrate && php database/cli.php seed"
# Сайт будет доступен: http://localhost:8080
```

### WSL/Ubuntu (root-права)
```bash
sudo apt update && sudo apt install -y apache2 mysql-server php8.1 php8.1-cli libapache2-mod-php8.1 \
    php8.1-mysql php8.1-xml php8.1-mbstring php8.1-curl php8.1-zip php8.1-gd composer unzip git
cd /var/www/html
git clone https://github.com/Apex0412/Budget.git finanses
cd finanses/finanses
composer install
php database/cli.php migrate
php database/cli.php seed
sudo systemctl restart apache2
```

---

## 🛠️ Установка по платформам

### A. Windows (XAMPP)
**Шаг 1.** Скачайте и установите [XAMPP](https://www.apachefriends.org/ru/index.html). В мастере оставьте компоненты Apache, MySQL, PHP, phpMyAdmin.

**Шаг 2.** Запустите **XAMPP Control Panel**, включите модули *Apache* и *MySQL*. Убедитесь, что индикаторы зелёные.

**Шаг 3.** Скачайте проект:
- **Git:**
  ```bash
  cd C:\xampp\htdocs
  git clone https://github.com/Apex0412/Budget.git finanses
  ```
- **ZIP-архив:**
  ```powershell
  Invoke-WebRequest https://github.com/Apex0412/Budget/archive/refs/heads/work.zip -OutFile work.zip
  Expand-Archive work.zip -DestinationPath C:\xampp\htdocs\Budget-work
  Move-Item C:\xampp\htdocs\Budget-work\Budget-work\finanses C:\xampp\htdocs\finanses -Force
  Remove-Item work.zip
  Remove-Item C:\xampp\htdocs\Budget-work -Recurse -Force
  ```
  *Подсказка:* архив распаковывается в подпапку `Budget-work`. После перемещения убедитесь, что структура `finanses/public`, `finanses/src`, `finanses/vendor` сохранена.

**Шаг 4.** Установите [Composer for Windows](https://getcomposer.org/download/). После установки проверьте:
```powershell
composer -V
```
Дополнительно убедитесь, что расширение **gd** активно (нужно для PDF):
```powershell
php -m | findstr /I gd
```
Если строка не появилась, откройте `C:\xampp\php\php.ini`, раскомментируйте `extension=gd` и перезапустите Apache.

**Шаг 5.** Откройте `http://localhost/phpmyadmin`, создайте БД `finanses`. Импортируйте `schema.sql` или выполните миграции:
```bash
cd C:\xampp\htdocs\finanses\finanses
php database/cli.php migrate
php database/cli.php seed
```

**Шаг 6.** Создайте `.env` по шаблону:
```bash
APP_ENV=local
APP_URL=http://localhost/finanses/public
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=finanses
DB_USER=root
DB_PASS=
```

**Шаг 7.** Установите зависимости:
```bash
composer install
```

**Шаг 8.** Проверьте сайт в браузере: `http://localhost/finanses/public`. Войдите логином `admin` / паролем `admin123` (при первом входе смените пароль).

**Траблшутинг:**
- Порт 80 занят → измените порт Apache в `httpd.conf` (Listen 8080) и `httpd-ssl.conf`.
- Ошибка доступа → смотрите логи `xampp\apache\logs\error.log`.
- Включите модуль `mod_rewrite` через XAMPP Control Panel → Config → Apache (httpd.conf) → раскомментируйте `LoadModule rewrite_module`.

---

### B. Windows + WSL 2 (Ubuntu)
**Шаг 1.** Проверьте WSL2:
```powershell
wsl --status
```
Если выключено, включите компоненты «Virtual Machine Platform» и «Windows Subsystem for Linux».

**Шаг 2.** Установите Ubuntu из Microsoft Store, задайте пользователя.

**Шаг 3.** В Ubuntu обновите систему и установите стек:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mysql-server php8.1 php8.1-cli libapache2-mod-php8.1 \
    php8.1-mysql php8.1-xml php8.1-mbstring php8.1-curl php8.1-zip php8.1-gd composer unzip git
sudo service mysql start
```
Проверьте, что модуль **gd** подключён:
```bash
php -m | grep -i gd
```
Если модуль не найден, выполните `sudo apt install php8.1-gd` и перезапустите Apache.

**Шаг 4.** Скачайте проект:
```bash
cd /var/www/html
sudo git clone https://github.com/Apex0412/Budget.git finanses
sudo chown -R $USER:www-data finanses
```

**Шаг 5.** Права и зависимости:
```bash
cd /var/www/html/finanses/finanses
sudo chmod -R 775 storage
composer install
```

**Шаг 6.** Создайте БД и пользователя:
```bash
sudo mysql -e "CREATE DATABASE finanses CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'finanses'@'%' IDENTIFIED BY 'finanses';"
sudo mysql -e "GRANT ALL PRIVILEGES ON finanses.* TO 'finanses'@'%';"
```
Если получаете `Access denied`, войдите `sudo mysql`, выполните `ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';`.

**Шаг 7.** Импорт схемы:
```bash
mysql -u root -p finanses < schema.sql
php database/cli.php seed
```

**Шаг 8.** Создайте `.env` (локальная БД WSL):
```bash
APP_ENV=local
APP_URL=http://localhost/finanses/public
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=finanses
DB_USER=root
DB_PASS=
```
Для подключения к MySQL в XAMPP укажите `DB_HOST=<адрес из cat /etc/resolv.conf (nameserver)>`.

**Шаг 9.** Включите `AllowOverride All` в `/etc/apache2/sites-available/000-default.conf`, перезапустите Apache:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**Ошибки:**
- `HCS_E_SERVICE_NOT_AVAILABLE` → включите виртуализацию в BIOS и службы Hyper-V/WSL.
- Логи Apache: `/var/log/apache2/error.log`; MySQL: `/var/log/mysql/error.log`.

---

### C. Linux (Ubuntu/Debian)
Повторите шаги WSL, исключая особенности Windows. Используйте `/var/www/html/finanses`, создайте `.env`, выполните миграции, настройте виртуальный хост Apache и перезапустите службу.
Проверьте расширение **gd** (`php -m | grep -i gd`) и при необходимости установите `sudo apt install php8.1-gd`.

---

### D. macOS (Homebrew)
**Шаг 1.** Установите [Homebrew](https://brew.sh).

**Шаг 2.** Пакеты:
```bash
brew install php apache2 mysql composer git
sudo apachectl start
brew services start mysql
```
Проверьте модуль **gd**:
```bash
php -m | grep -i gd
```
Если модуль не найден, выполните `brew reinstall php` — GD ставится вместе с PHP.

**Шаг 3.** Каталог проекта:
```bash
cd /usr/local/var/www
git clone https://github.com/Apex0412/Budget.git finanses
cd finanses/finanses
composer install
php database/cli.php migrate
php database/cli.php seed
```

**Шаг 4.** Настройте `/usr/local/etc/httpd/httpd.conf`:
- `DocumentRoot "/usr/local/var/www/finanses/public"`
- Разрешите `AllowOverride All`, активируйте `mod_rewrite`.

**Шаг 5.** Пример `.env`:
```bash
APP_ENV=local
APP_URL=http://localhost
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=finanses
DB_USER=root
DB_PASS=
```

**Шаг 6.** Перезапустите Apache: `sudo apachectl restart`. Откройте `http://localhost/finanses/public`.

---

### E. Docker
**Шаг 1.** Установите Docker Desktop (Windows/macOS) или Docker Engine (Linux).

**Шаг 2.** В каталоге проекта выполните (при первом запуске или после изменения `Dockerfile` всегда пересобирайте образ):
```bash
docker compose build
docker compose up -d
docker compose ps
docker exec -it $(docker compose ps -q app) bash -lc "composer install && php database/cli.php migrate && php database/cli.php seed"
```
Проверьте, что в контейнере активен модуль **gd** (используется генератором PDF):
```bash
docker compose exec app php -m | grep -i gd
```

**Шаг 3.** Сайт доступен на `http://localhost:8080`. Логи: `docker compose logs -f`.

**Шаг 4.** Импорт схемы вручную (если нужно):
```bash
docker exec -i $(docker compose ps -q db) mysql -u root -proot finanses < schema.sql
```

#### Docker на Windows (PowerShell, автоматический запуск)
Если не хотите вводить команды по одной, используйте готовый скрипт.

1. Откройте **PowerShell от имени администратора** и перейдите в корень репозитория (`C:\...\Budget`).
2. Временно разрешите выполнение скриптов только для этой сессии (без изменения глобальной политики):
   ```powershell
   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
   ```
   > Нужно постоянное разрешение? Используйте `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`.
3. Запустите сценарий автоматической сборки и запуска контейнеров:
   ```powershell
   .\finanses\scripts\windows\docker-up.ps1
   ```
   Скрипт сам определит, доступен ли `docker compose` или `docker-compose`, выполнит `down`, `build`, `up -d`, затем внутри контейнера запустит `composer install`, `php database/cli.php migrate`, `php database/cli.php seed` и покажет ссылку `http://localhost:8080`.
4. Нужно пересобрать образ без кеша? Добавьте флаг:
   ```powershell
   .\finanses\scripts\windows\docker-up.ps1 -NoCache
   ```
5. Остановка стека:
   ```powershell
   docker compose down
   ```

> 💡 Проект лежит в другом каталоге? Передайте путь до директории с `docker-compose.yml` (`...\Budget\finanses`):
> ```powershell
> .\finanses\scripts\windows\docker-up.ps1 -ProjectPath "D:\\projects\\Budget\\finanses"
> ```
> Скрипт проверит наличие Docker Desktop, сообщит, если команда compose недоступна, и не продолжит выполнение при ошибке.

---

### F. Nginx + PHP-FPM
**Шаг 1.** Установите nginx и php-fpm (`sudo apt install nginx php8.1-fpm`).

**Шаг 2.** Используйте `nginx.conf` из репозитория. Укажите `root /var/www/finanses/public;`.

**Шаг 3.** Перезапустите:
```bash
sudo systemctl enable php8.1-fpm nginx
sudo systemctl restart php8.1-fpm nginx
```

---

### G. HTTPS (self-signed)
```bash
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/finanses.key \
  -out /etc/ssl/certs/finanses.crt
```
Добавьте в Apache VirtualHost:
```
<VirtualHost *:443>
  SSLEngine on
  SSLCertificateFile /etc/ssl/certs/finanses.crt
  SSLCertificateKeyFile /etc/ssl/private/finanses.key
</VirtualHost>
```

---

## 🌱 .env примеры
**Docker:**
```
APP_ENV=local
APP_URL=http://localhost:8080
DB_HOST=db
DB_PORT=3306
DB_NAME=finanses
DB_USER=finanses
DB_PASS=finanses
```

**XAMPP / Linux / macOS:**
```
APP_ENV=local
APP_URL=http://localhost/finanses/public
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=finanses
DB_USER=root
DB_PASS=
```

**WSL + XAMPP MySQL:**
```
APP_ENV=local
APP_URL=http://localhost/finanses/public
DB_HOST=172.28.48.1   # адрес шлюза, смотрите `cat /etc/resolv.conf`
DB_PORT=3306
DB_NAME=finanses
DB_USER=root
DB_PASS=
```

---

## 🧰 Полезные команды
```bash
composer install            # установка зависимостей
php database/cli.php migrate  # миграции
php database/cli.php seed     # сиды
php database/cli.php rollback # откат
make up / make down         # запуск/остановка docker-compose
make db-dump / make db-restore
systemctl restart apache2   # перезапуск Apache (Linux)
php -v / mysql --version
```
Логи: `storage/logs/app.log`, Apache — `/var/log/apache2/error.log`, MySQL — `/var/log/mysql/error.log`.

---

## 🧯 Частые ошибки и решения
- **Порт 80 занят:** измените порт Apache/Nginx (Listen 8080). В XAMPP меняйте и `httpd-ssl.conf`.
- **`Access denied for user 'root'@'localhost'`:** выполните `sudo mysql`, затем `ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';`.
- **`HCS_E_SERVICE_NOT_AVAILABLE` (WSL):** включите виртуализацию в BIOS, службы Hyper-V, перезагрузите ПК.
- **`php8.1-json` отсутствует:** модуль JSON встроен в PHP 8, отдельный пакет не нужен.
- **Ошибка `TEXT/BLOB column can't have default`:** в MySQL нельзя задавать DEFAULT для TEXT/BLOB — используйте сиды/обновления.

---

## 🔎 Проверка после установки
1. Откройте `http://localhost/finanses/public` (или `:8080` в Docker).
2. Войдите `admin` / `admin123`, смените пароль.
3. Создайте заявку с позициями, скачайте PDF.
4. Убедитесь, что отчёты и журнал действий отображают данные.

Дополнительно можно создать `public/check.php` с `mysqli_connect` для диагностики БД (удалите файл после проверки).

---

## ♻️ Удаление или обновление
- **Остановить сервисы:** `systemctl stop apache2 mysql` или `docker compose down`.
- **Обновить проект:** `git pull`, затем `composer install`, миграции.
- **Бэкап:** используйте `scripts/linux/db_dump.sh` или `scripts/windows/db_dump.ps1`.
- **Удалить:** удалите каталог проекта и базу данных (`DROP DATABASE finanses;`).

---

Готово! FINANSES развернут и готов к эксплуатации. Ознакомьтесь с дополнительной документацией в директории `docs/` (архитектура, безопасность, деплой) и OpenAPI-спецификацией API.
