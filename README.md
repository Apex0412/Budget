# Finanses — система заявок на закупку

Веб-приложение на PHP 8.1 с Apache и Composer для подачи и обработки заявок на закупку. Руководство ниже описывает установку на Ubuntu в среде WSL 2, чтобы можно было быстро развернуть проект локально.

## Требования к окружению

- WSL 2 с дистрибутивом Ubuntu 22.04 LTS или новее.
- Права sudo в системе.
- Подключение к интернету для установки пакетов.
- Apache 2.4+, PHP 8.1 с необходимыми модулями, Composer, MySQL или MariaDB.

## Пошаговая установка на Ubuntu / WSL 2

Следующие шаги выполняются в терминале Ubuntu (WSL 2). Каждая команда оформлена блоком `bash`, чтобы её можно было копировать целиком.

### 1. Обновите систему

```bash
sudo apt update
sudo apt upgrade -y
```

### 2. Установите и запустите Apache

```bash
sudo apt install -y apache2
sudo systemctl enable --now apache2
```

> В WSL 2 службы не запускаются автоматически при старте Windows. При каждом новом сеансе запускайте Apache командой `sudo service apache2 start`.

### 3. Установите PHP 8.1 и модули

Ubuntu 22.04 уже содержит PHP 8.1. Установите интерпретатор и популярные расширения:

```bash
sudo apt install -y \
  php8.1 php8.1-cli php8.1-common php8.1-mysql php8.1-mbstring \
  php8.1-xml php8.1-curl php8.1-zip php8.1-gd php8.1-intl php8.1-soap
```

Проверьте версию PHP:

```bash
php -v
```

### 4. Установите Composer

```bash
sudo apt install -y composer
composer -V
```

Если в репозитории доступна устаревшая версия Composer, воспользуйтесь официальным установщиком:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
composer -V
```

### 5. Подготовьте каталог `/var/www/html/finanses`

```bash
sudo mkdir -p /var/www/html/finanses
sudo chown -R $USER:www-data /var/www/html/finanses
```

### 6. Скопируйте проект

**Вариант с Git:**

```bash
cd /var/www/html
sudo git clone https://github.com/Apex0412/Budget.git
sudo cp -r Budget/finanses/* finanses/
sudo rm -rf Budget
```

**Вариант с ZIP-архивом:**

```bash
cd /tmp
wget https://github.com/Apex0412/Budget/archive/refs/heads/work.zip -O budget.zip
unzip budget.zip
sudo cp -r Budget-work/finanses/* /var/www/html/finanses/
rm -rf budget.zip Budget-work
```

После копирования убедитесь, что структура каталогов сохранена:

```bash
ls /var/www/html/finanses
```

### 7. Установите зависимости проекта

```bash
cd /var/www/html/finanses
composer install --no-dev --prefer-dist
```

### 8. Настройте права доступа

```bash
sudo chown -R $USER:www-data /var/www/html/finanses
sudo find /var/www/html/finanses -type f -exec chmod 664 {} \;
sudo find /var/www/html/finanses -type d -exec chmod 775 {} \;
```

Убедитесь, что папки `pdf/` и `uploads/` доступны для записи веб-сервером:

```bash
sudo chmod 775 /var/www/html/finanses/pdf /var/www/html/finanses/uploads
```

### 9. Создайте виртуальный хост Apache

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

sudo a2dissite 000-default.conf
sudo a2ensite finanses.conf
sudo a2enmod rewrite
```

> Если хотите оставить сайт `000-default.conf`, пропустите команду `a2dissite` и убедитесь, что конфигурации не конфликтуют.

### 10. Перезапустите Apache

```bash
sudo systemctl reload apache2
```

### 11. Настройте переменные окружения и базу данных

```bash
cd /var/www/html/finanses
cp .env.example .env
nano .env
```

Укажите параметры подключения к базе данных и URL вида `APP_BASE_URL=http://localhost/finanses`. Затем создайте базу и импортируйте схему:

```bash
mysql -u root -p -e "CREATE DATABASE finanses DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p finanses < schema.sql
```

## Проверка работы

Откройте браузер Windows и перейдите по адресу:

```
http://localhost/finanses
```

При первом запуске появится мастер создания учётной записи администратора. После заполнения формы откроется личный кабинет.

## (Опционально) Установка phpMyAdmin

```bash
sudo apt install -y phpmyadmin
sudo ln -s /usr/share/phpmyadmin /var/www/html/phpmyadmin
sudo systemctl reload apache2
```

Доступ в браузере: `http://localhost/phpmyadmin`.

## Полезные команды

```bash
# Проверить статус служб
systemctl status apache2
systemctl status mysql

# Перезапустить службы
sudo systemctl restart apache2
sudo systemctl restart mysql

# Проверить версии
php -v
composer -V
mysql --version

# Управление сайтом
sudo a2ensite finanses.conf
sudo a2dissite finanses.conf
sudo systemctl reload apache2

# Запустить Apache и MySQL в текущей сессии WSL
sudo service apache2 start
sudo service mysql start
```

Готово! Проект Finanses работает в среде Ubuntu (WSL 2) и доступен по адресу `http://localhost/finanses`.
