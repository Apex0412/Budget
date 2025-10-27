# Руководство по развёртыванию FINANSES

## Подготовка окружения
1. Выберите способ: Docker, Apache, Nginx + PHP-FPM.
2. Убедитесь в наличии PHP ≥ 8.1, MySQL ≥ 8.0, Composer ≥ 2.x.
3. Активируйте расширения PHP `bcmath`, `exif`, `gd`, `intl`, `mbstring`, `soap`, `zip` (рекомендуются также `gmp`, `pcntl`, `sodium`).
4. Настройте резервирование портов (HTTP 80/8080, MySQL 3306).

## Docker (production-ready)
```bash
git clone https://github.com/Apex0412/Budget.git finanses
cd finanses/finanses
cp .env.example .env  # при необходимости задайте PROD-параметры (APP_URL, креды и т.п.)
sed -i 's/APP_ENV=local/APP_ENV=prod/' .env
docker compose up --build -d
docker compose logs -f app
```
- entrypoint автоматически установит зависимости, выполнит миграции и сиды; после появления сообщения `Database already initialised` приложение готово.
- для production-оптимизации выполните: `docker compose exec app composer install --no-dev --optimize-autoloader`.
- Настройте обратный прокси (nginx/Traefik) для HTTPS.
- Логи Apache/PHP доступны через `docker compose logs app`, MySQL — `docker compose logs db`.

## Bare-metal Apache
1. Установите Apache + PHP модуль (`libapache2-mod-php8.1`) и включите расширения `bcmath`, `exif`, `gd`, `intl`, `mbstring`, `soap`, `zip` (пакеты `php8.1-<module>` для Debian/Ubuntu).
2. Разверните код в `/var/www/finanses`.
3. Создайте VirtualHost:
```
<VirtualHost *:80>
  ServerName finanses.example.com
  DocumentRoot /var/www/finanses/public
  <Directory /var/www/finanses/public>
    AllowOverride All
    Require all granted
  </Directory>
  ErrorLog ${APACHE_LOG_DIR}/finanses.error.log
  CustomLog ${APACHE_LOG_DIR}/finanses.access.log combined
</VirtualHost>
```
4. Включите `mod_rewrite`, перезапустите Apache.
5. Выполните `composer install`, миграции, сиды.

## Nginx + PHP-FPM
1. Скопируйте `nginx.conf` из репозитория в `/etc/nginx/sites-available/finanses`.
2. Создайте симлинк в `sites-enabled`, перезапустите nginx и php-fpm.
3. Убедитесь, что сокет php-fpm совпадает (`fastcgi_pass unix:/run/php/php8.1-fpm.sock`).
4. Ограничьте доступ к `/storage`, `/database`, `/config`, `/vendor` директивой `deny all`.

## Настройки .env (production)
```
APP_ENV=prod
APP_DEBUG=false
APP_URL=https://finanses.example.com
SESSION_NAME=finanses_session
SESSION_LIFETIME=1800
DB_HOST=db.internal
DB_NAME=finanses
DB_USER=finanses
DB_PASS=super_secret
FILE_STORAGE_PATH=storage/uploads
PDF_STORAGE_PATH=storage/pdf
LOG_PATH=storage/logs/app.log
MAX_UPLOAD_SIZE=5242880
ALLOWED_UPLOAD_MIME=application/pdf,image/png,image/jpeg,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
PDF_ORG_NAME="Муниципальное бюджетное учреждение \"Комбинат благоустройства\""
```
- Создайте отдельного пользователя MySQL с ограниченными правами.
- Настройте ротацию логов (`logrotate` для `storage/logs/app.log`).
- Включите HTTPS (Let's Encrypt / self-signed).

## Мониторинг и бэкапы
- Healthcheck: `GET /public/api/health.php?type=app` и `?type=db`.
- Бэкап БД: `mysqldump finanses > backup.sql` (или скрипты в `scripts/`).
- Бэкап файлов: `storage/uploads`, `storage/pdf`.

## Обновление релиза
```bash
git pull origin work
composer install --no-dev --optimize-autoloader
php database/cli.php migrate
php database/cli.php seed
```

## Безопасность в продакшене
- Используйте сложные пароли и ограничьте доступ к phpMyAdmin.
- Размещайте `/storage` вне web-root (Symbolic link) или защищайте `.htaccess`/nginx `deny all`.
- Настройте fail2ban / rate limiting на уровень веб-сервера (лимит запросов к `/api/auth.php?action=login`).

