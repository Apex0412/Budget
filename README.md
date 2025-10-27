# Finanses — система заявок на закупку

Простое веб-приложение для автоматизации заявок на закупку. Стек: **PHP 8.1**, **Apache 2.4**, **MySQL 8**, **Composer**, TailwindCSS и Vanilla JS.

---

## Оглавление
- [О проекте](#о-проекте)
- [Что понадобится](#что-понадобится)
- [Быстрый старт](#быстрый-старт)
- [Установка по платформам](#установка-по-платформам)
  - [A. Windows (XAMPP)](#a-windows-xampp)
  - [B. Windows + WSL 2 (Ubuntu)](#b-windows--wsl-2-ubuntu)
  - [C. Linux (Ubuntu/Debian)](#c-linux-ubuntudebian)
  - [D. macOS (Homebrew)](#d-macos-homebrew)
- [Docker-вариант](#docker-вариант)
- [Готовые шаблоны env](#готовые-шаблоны-env)
- [Полезные команды](#полезные-команды)
- [Частые ошибки и решения](#частые-ошибки-и-решения)
- [Проверка после установки](#проверка-после-установки)
- [Удаление и обновление](#удаление-и-обновление)

---

## О проекте
Finanses — это PHP-приложение для подачи, согласования и контроля заявок на закупку с генерацией PDF-обоснований, учётом материалов, аудитом действий и отчётами.

**Стек:** PHP 8.1+, Apache 2.4+, MySQL 8+, Composer, TailwindCSS, Vanilla JS, TCPDF.

**Варианты развёртывания:** Windows (XAMPP), Windows + WSL 2, Linux (Ubuntu/Debian), macOS (Homebrew), Docker.

---

## Что понадобится
- Свободный порт **80** (или запасной 8080) и доступ к интернету.
- Права администратора или `sudo` для установки пакетов.
- Возможность запускать службы Apache и MySQL (локально или в контейнере).

> ⚠️ Если порт 80 занят другим ПО (IIS, Skype, Docker Desktop), выберите альтернативный порт или остановите конфликтующий сервис.

---

## Быстрый старт

### Docker (5 команд)
```bash
# 1. Склонируйте репозиторий
git clone https://github.com/Apex0412/Budget.git
cd Budget

# 2. Запустите контейнеры
docker compose up -d

# 3. Инициализируйте базу
docker exec -i $(docker compose ps -q db) mysql -u root -proot finanses < finanses/schema.sql

# 4. Проверьте логи (опционально)
docker compose logs -f app

# 5. Откройте http://localhost:8080/finanses
```

### WSL/Ubuntu (7 команд)
```bash
# 1. Установите пакеты
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mysql-server php8.1 php8.1-cli libapache2-mod-php8.1 \
    php8.1-mysql php8.1-xml php8.1-mbstring php8.1-curl php8.1-zip php8.1-gd composer unzip git

# 2. Скачайте проект
cd /var/www/html
sudo git clone https://github.com/Apex0412/Budget.git
sudo cp -r Budget/finanses ./finanses && sudo rm -rf Budget

# 3. Выдайте права
sudo chown -R $USER:www-data finanses && sudo chmod -R 775 finanses

# 4. Создайте БД и импортируйте схему
sudo service mysql start
sudo mysql -e "CREATE DATABASE finanses CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p finanses < finanses/schema.sql

# 5. Установите зависимости
cd finanses && composer install

# 6. Скопируйте .env
cp .env.example .env

# 7. Проверьте http://localhost/finanses
```

> ✅ После быстрого старта рекомендуем пройти полный раздел для своей платформы, чтобы настроить виртуальный хост, права и дополнительные сервисы.

---

## Установка по платформам

### A. Windows (XAMPP)

**Шаг 1. Скачать и установить XAMPP**
1. Перейдите на <https://www.apachefriends.org/download.html>.
2. Скачайте XAMPP для PHP 8.1.
3. Запустите установщик, оставьте галочки Apache, MySQL, PHP, phpMyAdmin.
4. Установите в `C:\xampp`.

> Проверка: откройте «Пуск → XAMPP Control Panel». Если запускается без ошибок — всё ок.

**Шаг 2. Запустить Apache и MySQL**
1. Откройте XAMPP Control Panel.
2. Нажмите **Start** напротив Apache и MySQL.
3. Убедитесь, что индикаторы стали зелёными (скриншот-плейсхолдер: *[XAMPP Control Panel]*).

> Проверка: нажмите **Admin** рядом с Apache → откроется страница <http://localhost/dashboard/>.

**Шаг 3. Подготовить папку проекта**
1. Создайте `C:\xampp\htdocs\finanses` (через Проводник).
2. Скачайте код:
   - Через Git Bash: 
     ```bash
     cd /c/xampp/htdocs
     git clone https://github.com/Apex0412/Budget.git
     xcopy Budget\finanses finanses /E /I /Y
     rmdir /S /Q Budget
     ```
   - Или скачайте ZIP <https://github.com/Apex0412/Budget/archive/refs/heads/work.zip>, распакуйте и перенесите содержимое папки `Budget-work\finanses` в `C:\xampp\htdocs\finanses`.

> Проверка: убедитесь, что внутри `C:\xampp\htdocs\finanses` есть `index.html`, `api`, `app` и др.

**Шаг 4. Установить Composer**
1. Скачайте установщик Composer: <https://getcomposer.org/Composer-Setup.exe>.
2. Запустите, укажите путь до `php.exe` из `C:\xampp\php\php.exe`.
3. После установки откройте «Командную строку для разработчиков» или PowerShell.

```bash
composer -V
```

> Проверка: в ответ должна появиться версия Composer. Если нет — перезайдите в терминал.

**Шаг 5. Подготовить базу данных**
1. Откройте <http://localhost/phpmyadmin>.
2. Нажмите «Базы данных» → создайте `finanses` с кодировкой `utf8mb4_unicode_ci`.
3. На вкладке «Импорт» выберите файл `C:\xampp\htdocs\finanses\schema.sql` и нажмите «Вперёд».

> Проверка: после импорта появятся таблицы `users`, `requests`, `request_items`, …

**Шаг 6. Создать файл `.env`**
Создайте `C:\xampp\htdocs\finanses\.env` со следующим содержимым:

```bash
APP_ENV=local
APP_BASE_URL=http://localhost/finanses
DB_HOST=localhost
DB_PORT=3306
DB_NAME=finanses
DB_USER=root
DB_PASS=
SESSION_NAME=finanses_session
CSRF_SECRET=CHANGE_ME_32_CHARS
PDF_ORG_NAME="Муниципальное бюджетное учреждение «Комбинат благоустройства»"
PDF_CITY="г. Серпухов"
```

> Подсказка: `CSRF_SECRET` замените на случайную строку из 32 символов.

**Шаг 7. Установить зависимости проекта**
Откройте PowerShell от имени администратора и выполните:

```bash
cd C:\xampp\htdocs\finanses
composer install
```

> Проверка: в конце увидите `Generating autoload files`. При ошибках убедитесь, что PHP добавлен в PATH.

**Шаг 8. Проверка запуска**
1. В браузере откройте <http://localhost/finanses>.
2. Должна появиться страница авторизации/мастер настройки.

> Если видите ошибку 403/500, проверьте лог `C:\xampp\apache\logs\error.log`.

**Мини-траблшутинг**
- Порт 80 занят → в XAMPP Control Panel откройте `Config → Apache (httpd.conf)` и измените `Listen 80` на `Listen 8080`, затем перезапустите Apache. URL станет `http://localhost:8080/finanses`.
- Mod_rewrite отключён → `Config → Apache (httpd.conf)` → уберите `#` перед `LoadModule rewrite_module modules/mod_rewrite.so`.
- Логи Apache: `C:\xampp\apache\logs\error.log`, MySQL: `C:\xampp\mysql\data\mysql_error.log`.

**Опционально: установка phpMyAdmin (уже входит в XAMPP)**
- Откройте <http://localhost/phpmyadmin>. Если требуется обновление — используйте установщик с сайта проекта.

---

### B. Windows + WSL 2 (Ubuntu)

**Шаг 1. Включить WSL 2 и виртуализацию**
1. Откройте PowerShell от администратора и проверьте статус:
   ```bash
   wsl --status
   ```
2. Если WSL не установлен, выполните:
   ```bash
   wsl --install
   ```
3. Убедитесь, что в BIOS включена аппаратная виртуализация (VT-x/AMD-V).

> Если появилось `HCS_E_SERVICE_NOT_AVAILABLE`, включите компоненты «Платформа виртуальной машины» и «Подсистема Windows для Linux» через «Включение компонентов Windows», затем перезагрузите ПК.

**Шаг 2. Установить и обновить Ubuntu**
1. Откройте Microsoft Store → найдите **Ubuntu 22.04 LTS** → установите.
2. Запустите Ubuntu из меню Пуск, задайте имя пользователя и пароль.

```bash
sudo apt update && sudo apt upgrade -y
```

> Проверка: команда должна завершиться без ошибок. При «Temporary failure resolving» — проверьте интернет/прокси.

**Шаг 3. Установить Apache, PHP, MySQL, Composer**
```bash
sudo apt install -y apache2 mysql-server php8.1 php8.1-cli libapache2-mod-php8.1 \
    php8.1-mysql php8.1-xml php8.1-mbstring php8.1-curl php8.1-zip php8.1-gd composer unzip git
```

> Проверка: `php -v` и `composer -V` должны показывать версии.

**Шаг 4. Скопировать проект**
```bash
cd /var/www/html
sudo git clone https://github.com/Apex0412/Budget.git
sudo cp -r Budget/finanses ./finanses
sudo rm -rf Budget
```

> Если используете ZIP, скачайте `work.zip`, распакуйте `Budget-work/finanses` и скопируйте в `/var/www/html/finanses`.

**Шаг 5. Настроить права доступа**
```bash
sudo chown -R $USER:www-data /var/www/html/finanses
sudo chmod -R 775 /var/www/html/finanses
```

> Проверка: `ls -la /var/www/html | grep finanses` покажет владельца `$USER www-data`.

**Шаг 6. Запустить MySQL и создать БД**
```bash
sudo service mysql start
sudo mysql -e "CREATE DATABASE finanses CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

> Если получите `Access denied`, выполните `sudo mysql`, затем:
> ```sql
> ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';
> FLUSH PRIVILEGES;
> EXIT;
> ```

**Шаг 7. Импортировать схему**
```bash
mysql -u root -p finanses < /var/www/html/finanses/schema.sql
```

> Проверка: `mysql -u root -p -e "SHOW TABLES IN finanses;"` должен вывести список таблиц.

**Шаг 8. Настроить `.env`**
В каталоге `/var/www/html/finanses` создайте файл `.env`.

```bash
APP_ENV=local
APP_BASE_URL=http://localhost/finanses
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=finanses
DB_USER=root
DB_PASS=
SESSION_NAME=finanses_session
CSRF_SECRET=CHANGE_ME_32_CHARS
PDF_ORG_NAME="Муниципальное бюджетное учреждение «Комбинат благоустройства»"
PDF_CITY="г. Серпухов"
```

> Если MySQL работает в XAMPP (Windows), используйте IP-мост WSL:
> ```bash
> APP_ENV=local
> APP_BASE_URL=http://localhost/finanses
> DB_HOST=172.28.48.1  # замените на адрес из `cat /etc/resolv.conf`
> DB_PORT=3306
> DB_NAME=finanses
> DB_USER=root
> DB_PASS=
> ```

**Шаг 9. Настроить виртуальный хост Apache**
```bash
sudo tee /etc/apache2/sites-available/finanses.conf > /dev/null <<'VHOST'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html

    Alias /finanses /var/www/html/finanses

    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Directory /var/www/html/finanses>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/finanses-error.log
    CustomLog ${APACHE_LOG_DIR}/finanses-access.log combined
</VirtualHost>
VHOST

sudo a2ensite finanses.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

> Проверка: `curl -I http://localhost/finanses` должен вернуть `HTTP/1.1 200 OK`.

**Шаг 10. Установить зависимости**
```bash
cd /var/www/html/finanses
composer install
```

> Если видите предупреждение о правах, снова примените `sudo chown -R $USER:www-data /var/www/html/finanses`.

**Шаг 11. Проверка в браузере**
Откройте <http://localhost/finanses> в браузере Windows. WSL автоматически проксирует порт.

**Опционально: phpMyAdmin в WSL**
```bash
sudo apt install -y phpmyadmin
sudo ln -s /usr/share/phpmyadmin /var/www/html/phpmyadmin
sudo systemctl reload apache2
```

> Проверьте <http://localhost/phpmyadmin>. При ошибке 404 убедитесь, что симлинк активен.

**Мини-траблшутинг**
- `HCS_E_SERVICE_NOT_AVAILABLE` → включите компоненты Windows и перезагрузитесь.
- Нет доступа к MySQL → проверьте `sudo tail -f /var/log/mysql/error.log`.
- Apache не стартует → посмотрите `sudo journalctl -u apache2`.

---

### C. Linux (Ubuntu/Debian)

Шаги аналогичны WSL, но без специфики мостов.

**Шаг 1. Обновить систему**
```bash
sudo apt update && sudo apt upgrade -y
```

**Шаг 2. Установить зависимости**
```bash
sudo apt install -y apache2 mysql-server php8.1 php8.1-cli libapache2-mod-php8.1 \
    php8.1-mysql php8.1-xml php8.1-mbstring php8.1-curl php8.1-zip php8.1-gd composer unzip git
```

**Шаг 3. Скопировать проект**
```bash
cd /var/www/html
sudo git clone https://github.com/Apex0412/Budget.git
sudo mv Budget/finanses ./finanses
sudo rm -rf Budget
```

**Шаг 4. Настроить права и каталоги**
```bash
sudo chown -R www-data:www-data /var/www/html/finanses
sudo find /var/www/html/finanses -type d -exec chmod 775 {} \;
sudo find /var/www/html/finanses -type f -exec chmod 664 {} \;
```

**Шаг 5. Создать БД и импортировать**
```bash
sudo mysql -e "CREATE DATABASE finanses CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p finanses < /var/www/html/finanses/schema.sql
```

**Шаг 6. Настроить `.env`**
```bash
cat <<'ENV' > /var/www/html/finanses/.env
APP_ENV=prod
APP_BASE_URL=http://example.com/finanses
DB_HOST=localhost
DB_PORT=3306
DB_NAME=finanses
DB_USER=fin_user
DB_PASS=STRONG_PASSWORD
SESSION_NAME=finanses_session
CSRF_SECRET=CHANGE_ME_32_CHARS
PDF_ORG_NAME="Муниципальное бюджетное учреждение «Комбинат благоустройства»"
PDF_CITY="г. Серпухов"
ENV
```

**Шаг 7. Настроить виртуальный хост**
```bash
sudo tee /etc/apache2/sites-available/finanses.conf > /dev/null <<'VHOST'
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/html/finanses

    <Directory /var/www/html/finanses>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/finanses-error.log
    CustomLog ${APACHE_LOG_DIR}/finanses-access.log combined
</VirtualHost>
VHOST

sudo a2ensite finanses.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

**Шаг 8. Установить зависимости Composer**
```bash
cd /var/www/html/finanses
sudo -u www-data composer install --no-dev --optimize-autoloader
```

**Шаг 9. Проверить сайт**
Откройте `http://example.com/finanses` (замените домен). Логи: `/var/log/apache2/finanses-error.log`.

**Опционально: phpMyAdmin**
```bash
sudo apt install -y phpmyadmin
sudo ln -s /usr/share/phpmyadmin /var/www/html/phpmyadmin
```

---

### D. macOS (Homebrew)

**Шаг 1. Установить Homebrew (если нет)**
```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

> Проверка: `brew -v` должен вывести версию.

**Шаг 2. Установить Apache, PHP, MySQL, Composer**
```bash
brew install php apache2 mysql composer
```

**Шаг 3. Запустить службы**
```bash
sudo apachectl start
brew services start mysql
```

**Шаг 4. Разместить проект**
```bash
sudo mkdir -p /usr/local/var/www/finanses
cd /usr/local/var/www
sudo git clone https://github.com/Apex0412/Budget.git
sudo cp -R Budget/finanses ./finanses && sudo rm -rf Budget
sudo chown -R $(whoami):staff finanses
```

**Шаг 5. Настроить Apache**
1. Откройте `/opt/homebrew/etc/httpd/httpd.conf` (или `/usr/local/etc/httpd/httpd.conf`).
2. Убедитесь, что включены модули `php` и `rewrite`.
3. Добавьте виртуальный хост в `extra/httpd-vhosts.conf`:

```bash
sudo tee /opt/homebrew/etc/httpd/extra/httpd-vhosts.conf > /dev/null <<'VHOST'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /usr/local/var/www/finanses

    <Directory /usr/local/var/www/finanses>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
VHOST
```

```bash
sudo apachectl restart
```

**Шаг 6. Настроить MySQL и базу**
```bash
mysql -u root -e "CREATE DATABASE finanses CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root finanses < /usr/local/var/www/finanses/schema.sql
```

**Шаг 7. Создать `.env`**
```bash
cat <<'ENV' > /usr/local/var/www/finanses/.env
APP_ENV=local
APP_BASE_URL=http://localhost/finanses
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=finanses
DB_USER=root
DB_PASS=
SESSION_NAME=finanses_session
CSRF_SECRET=CHANGE_ME_32_CHARS
PDF_ORG_NAME="Муниципальное бюджетное учреждение «Комбинат благоустройства»"
PDF_CITY="г. Серпухов"
ENV
```

**Шаг 8. Установить зависимости**
```bash
cd /usr/local/var/www/finanses
composer install
```

**Шаг 9. Проверка**
Откройте <http://localhost/finanses>.

**Опционально: phpMyAdmin**
```bash
brew install phpmyadmin
sudo ln -s /opt/homebrew/share/phpmyadmin /usr/local/var/www/phpmyadmin
sudo apachectl restart
```

> Если путь `/opt/homebrew` отсутствует, замените на `/usr/local` в зависимости от архитектуры.

---

## Docker-вариант

**Dockerfile**
```bash
FROM php:8.1-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite
COPY . /var/www/html/
```

**docker-compose.yml**
```bash
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - db
  db:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: finanses
      MYSQL_USER: finanses
      MYSQL_PASSWORD: finanses
    ports:
      - "3306:3306"
```

**Шаги**
```bash
# Шаг 1. Собрать и запустить контейнеры
docker compose up -d

# Шаг 2. Импортировать схему
docker exec -i $(docker compose ps -q db) mysql -u root -proot finanses < finanses/schema.sql

# Шаг 3. Скопировать .env для Docker
cat <<'ENV' > .env
APP_ENV=local
APP_BASE_URL=http://localhost:8080/finanses
DB_HOST=db
DB_PORT=3306
DB_NAME=finanses
DB_USER=finanses
DB_PASS=finanses
SESSION_NAME=finanses_session
CSRF_SECRET=CHANGE_ME_32_CHARS
PDF_ORG_NAME="Муниципальное бюджетное учреждение «Комбинат благоустройства»"
PDF_CITY="г. Серпухов"
ENV

# Шаг 4. Проверить http://localhost:8080/finanses
```

> Остановка контейнеров: `docker compose down`. Для повторного запуска без пересборки — `docker compose up -d`.

---

## Готовые шаблоны env

**XAMPP (Windows)**
```bash
APP_ENV=local
APP_BASE_URL=http://localhost/finanses
DB_HOST=localhost
DB_PORT=3306
DB_NAME=finanses
DB_USER=root
DB_PASS=
SESSION_NAME=finanses_session
CSRF_SECRET=CHANGE_ME_32_CHARS
PDF_ORG_NAME="Муниципальное бюджетное учреждение «Комбинат благоустройства»"
PDF_CITY="г. Серпухов"
```

**WSL / Linux (локальная БД)**
```bash
APP_ENV=local
APP_BASE_URL=http://localhost/finanses
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=finanses
DB_USER=root
DB_PASS=
```

**WSL + XAMPP (БД в Windows)**
```bash
APP_ENV=local
APP_BASE_URL=http://localhost/finanses
DB_HOST=172.28.48.1   # замените на адрес шлюза из /etc/resolv.conf
DB_PORT=3306
DB_NAME=finanses
DB_USER=root
DB_PASS=
```

**Linux/macOS (продакшен)**
```bash
APP_ENV=prod
APP_BASE_URL=https://your-domain/finanses
DB_HOST=localhost
DB_PORT=3306
DB_NAME=finanses
DB_USER=fin_user
DB_PASS=STRONG_PASSWORD
```

**Docker**
```bash
APP_ENV=local
APP_BASE_URL=http://localhost:8080/finanses
DB_HOST=db
DB_PORT=3306
DB_NAME=finanses
DB_USER=finanses
DB_PASS=finanses
```

---

## Полезные команды
```bash
php -v                     # Проверить версию PHP
composer install           # Установить PHP-зависимости
systemctl status apache2   # Статус Apache (Linux/WSL)
sudo systemctl restart apache2
sudo service mysql start   # Запуск MySQL (WSL/Linux)
mysql -u root -p -e "SHOW DATABASES;"
ls -la                     # Проверить владельца файлов
sudo tail -f /var/log/apache2/error.log
```

Для Windows (PowerShell):
```bash
netstat -ano | findstr :80           # Узнать, кто занял порт 80
Get-Service wampapache64             # Проверить службу Apache, если используете WAMP
```

---

## Частые ошибки и решения
- **Порт 80 занят.** Измените `Listen` в конфигурации Apache (например, на 8080) и обновите `APP_BASE_URL`.
- **`Access denied for user 'root'@'localhost'`.** Выполните `sudo mysql`, задайте пароль и включите `mysql_native_password`, затем импортируйте схему заново.
- **`HCS_E_SERVICE_NOT_AVAILABLE` в WSL.** Включите компоненты Windows: `dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart` и `dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart`, перезагрузитесь.
- **Отсутствует модуль JSON для PHP.** В PHP 8.1 он включён по умолчанию, дополнительный пакет не нужен.
- **Ошибка `BLOB/TEXT column can't have a default value`.** Удалите `DEFAULT` для `TEXT`/`BLOB` полей в своих кастомных миграциях.

---

## Проверка после установки
1. Откройте URL проекта (`http://localhost/finanses` или ваш домен).
2. Если отображается мастер первой настройки, создайте администратора.
3. Сформируйте тестовую заявку и скачайте PDF — убедитесь, что файл создаётся.

**Проверка подключения к БД (при необходимости)**
```bash
cat <<'PHP' > /var/www/html/finanses/check.php
<?php
$mysqli = mysqli_connect(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
if (!$mysqli) {
    die('Ошибка подключения: ' . mysqli_connect_error());
}
echo 'Успешное подключение к базе данных!';
PHP
```
Откройте `http://localhost/finanses/check.php`, затем удалите файл.

---

## Удаление и обновление

**Обновление**
```bash
cd /var/www/html/finanses
git pull origin work
composer install --no-dev --prefer-dist
php tools/hash_passwords.php   # при необходимости перерасчёта паролей
```

**Остановка сервисов**
```bash
sudo systemctl stop apache2
sudo systemctl stop mysql
```

**Удаление проекта (Linux/WSL)**
```bash
sudo rm -rf /var/www/html/finanses
sudo mysql -e "DROP DATABASE finanses;"
```

**Docker**
```bash
docker compose down -v   # остановить и удалить контейнеры с томами
rm -rf finanses          # удалить каталог, если он отдельный
```

> Перед удалением сделайте резервную копию БД: `mysqldump -u root -p finanses > finanses_backup.sql`.

---
