# Task Manager

A small Laravel app for managing prioritised tasks. Tasks can optionally belong to a project, priorities are numbered per project (and separately for tasks without a project), and the order is changed by drag and drop.

## Features

- Create, edit and delete tasks (name, priority, created/updated timestamps).
- Drag and drop reordering in the browser; priorities (#1 at the top, #2 next, ...) update automatically and stay contiguous, also after deletes.
- Projects: create projects and optionally assign a task to one.
- Project dropdown filter: All / No project / a specific project.

## Requirements

- PHP 8.3 or newer with the extensions: `pdo_mysql`, `mbstring`, `bcmath`, `intl`, `zip`, `xml`, `ctype`, `fileinfo`, `tokenizer`
- Composer 2
- MySQL 8
- Node.js 20+ and npm (to build the front-end assets)

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create an empty MySQL database and a user for it, then set the `DB_*` values in `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=your-password
```

Then run the migrations with the demo data, build the assets and start the server:

```bash
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Open http://localhost:8000.

## Docker setup

A ready-made environment (PHP 8.3 + Composer + Node 20, and MySQL 8) is defined in `Dockerfile` and `compose.yaml`. The only requirement on your machine is Docker with the Compose plugin.

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm install
docker compose exec app npm run build
```

Open http://localhost:8000.

Notes:

- Compose sets the `DB_*` variables for the `app` container itself (host `mysql`, database `app`, user `app`, password `secret`), so you do not need to edit them in `.env`.
- The `app` container runs as UID/GID 1000 by default. If your user differs, set `DEV_UID` and `DEV_GID` (e.g. `DEV_UID=$(id -u) DEV_GID=$(id -g) docker compose up -d --build`).
- `docker/mysql-init.sql` creates the `testing` database used by the tests on first start of the MySQL volume.
- Run the tests with `docker compose exec app php artisan test`.
- Stop with `docker compose down`; add `-v` to also delete the database volume.

## Running the tests

The feature tests use a separate MySQL database (`testing` by default, see `phpunit.xml`). Create it and grant your DB user access, then run:

```bash
php artisan test
```

## Production deployment

1. Get the code onto the server and install PHP dependencies without dev packages:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
2. Create the environment file and generate the key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Edit `.env`: set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to your public URL, and the production `DB_*` credentials.
3. Build the assets:
   ```bash
   npm ci
   npm run build
   ```
4. Run the migrations (do not use `--seed` in production unless you want the demo data):
   ```bash
   php artisan migrate --force
   ```
5. Cache the configuration, routes and views:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
   Re-run these after every deployment or `.env` change.
6. Make `storage/` and `bootstrap/cache/` writable by the web server user:
   ```bash
   chown -R www-data:www-data storage bootstrap/cache
   chmod -R ug+rwX storage bootstrap/cache
   ```
7. Point the web server document root at the `public/` directory. Example nginx server block:
   ```nginx
   server {
       listen 80;
       server_name tasks.example.com;
       root /var/www/task-manager/public;
       index index.php;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           include fastcgi_params;
           fastcgi_pass unix:/run/php/php8.3-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
       }

       location ~ /\.(?!well-known).* {
           deny all;
       }
   }
   ```
8. Serve the site over HTTPS (for example with Let's Encrypt / certbot).

Note: the application has no authentication. Restrict access at the web server level (HTTP basic auth, VPN or an IP allow-list) if it is exposed to the internet.
