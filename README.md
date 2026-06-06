# Princess Cruise Price Tracker, PHP Edition

Small Bluehost-friendly PHP app for checking Princess cruise cabin prices, storing history, graphing fare-per-person trends, and sending email alerts when a configured cabin type drops below a target price.

## Why PHP

Bluehost shared hosting usually supports PHP much more reliably than long-running Python/Flask apps. This version uses plain PHP, cURL, sessions, and PDO. No Composer or Node build is required.

## Structure

```text
princess-tracker-php/
  public/                      # deploy to public_html/princess-tracker
    index.php
    check.php
    history.php
    admin.php
    headers.php
    login.php
    logout.php
    health.php
    assets/style.css
  app/                         # deploy outside public_html
    bootstrap.php
    auth.php
    db.php
    graph.php
    mailer.php
    princess.php
  storage/                     # runtime only, private
  cron_check.php               # CLI cron entry point
  .env.example
  .github/workflows/deploy-bluehost-php.yml
```

Recommended production layout on Bluehost:

```text
/home/YOUR_BLUEHOST_USER/
  public_html/
    princess-tracker/          # public files from public/
  github-apps/
    princess-tracker-private/  # app/, .env, storage/, cron_check.php
      app/
      storage/
        headers.json
        tracker.sqlite
      logs/
      .env
      cron_check.php
```

## Local setup

```bash
cd princess-tracker-php
cp .env.example .env
mkdir -p storage
php cron_check.php --init-db
php -S 127.0.0.1:8080 -t public
```

Open:

```text
http://127.0.0.1:8080
```

Default login comes from `.env`:

```env
APP_USERNAME=admin
APP_PASSWORD=change-this-password
```

Change it before using the app.

## Required PHP extensions

You need:

```text
curl
pdo
pdo_sqlite OR pdo_mysql
json
session
```

Check locally:

```bash
php -m | grep -E 'curl|PDO|pdo_sqlite|pdo_mysql|json|session'
```

On Bluehost, use MultiPHP / PHP Extensions if available. If `pdo_sqlite` is unavailable, use MySQL instead.

## SQLite mode

This is the default:

```env
DB_DRIVER=sqlite
PRINCESS_DB_PATH=storage/tracker.sqlite
PRINCESS_HEADERS_PATH=storage/headers.json
```

## MySQL mode

Use this if Bluehost does not have PDO SQLite enabled:

```env
DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=your_db_name
DB_USER=your_db_user
DB_PASSWORD=your_db_password
```

Then run:

```bash
php cron_check.php --init-db
```

## Princess headers

The Princess API depends on your browser session cookies and request headers.

In browser DevTools:

1. Open the Princess page.
2. Trigger the cruise pricing request.
3. Open Network tab.
4. Find the POST request to:

```text
https://gw.api.princess.com/pcl-web/internal/caps/pc/pricing/v1/cruises
```

5. Right click it.
6. Copy as cURL.
7. Paste it into the app under `Headers`.

The app extracts and stores the usable headers in:

```text
storage/headers.json
```

If checks later fail with `401`, `403`, or HTML instead of JSON, refresh your browser session and paste a fresh cURL.

## GitHub deployment to Bluehost

The workflow is:

```text
.github/workflows/deploy-bluehost-php.yml
```

It deploys:

- `public/` to your public web path.
- `app/`, `cron_check.php`, `.env.example`, and `README.md` to your private path.

It does **not** deploy `.env`, `storage/headers.json`, or the database.

### GitHub Actions secrets

Create these repo secrets:

```text
BLUEHOST_HOST
BLUEHOST_USERNAME
BLUEHOST_SSH_KEY
BLUEHOST_PUBLIC_PATH
BLUEHOST_PRIVATE_PATH
```

Optional:

```text
BLUEHOST_PORT
```

Example values:

```text
BLUEHOST_HOST=yourdomain.com
BLUEHOST_USERNAME=your_cpanel_username
BLUEHOST_PUBLIC_PATH=/home/your_cpanel_username/public_html/princess-tracker
BLUEHOST_PRIVATE_PATH=/home/your_cpanel_username/apps/princess-tracker-private
BLUEHOST_PORT=22
```

### SSH key setup

Generate a deploy key locally:

```bash
ssh-keygen -t ed25519 -C "github-actions-princess-tracker" -f ~/.ssh/princess_tracker_bluehost
```

Add the public key to Bluehost/cPanel SSH authorized keys:

```bash
cat ~/.ssh/princess_tracker_bluehost.pub
```

Add the private key as GitHub secret `BLUEHOST_SSH_KEY`:

```bash
cat ~/.ssh/princess_tracker_bluehost
```

Paste the full private key, including BEGIN/END lines.

## Bluehost production setup

SSH into Bluehost:

```bash
ssh YOUR_BLUEHOST_USER@YOUR_BLUEHOST_HOST
mkdir -p /home/YOUR_BLUEHOST_USER/apps/princess-tracker-private/storage
mkdir -p /home/YOUR_BLUEHOST_USER/apps/princess-tracker-private/logs
cd /home/YOUR_BLUEHOST_USER/apps/princess-tracker-private
cp .env.example .env
nano .env
chmod 600 .env
chmod 700 storage logs
```

Set your production `.env` values.

Important values:

```env
APP_USERNAME=admin
APP_PASSWORD=your-strong-password
MAIL_FROM=your-real-email@yourdomain.com
DB_DRIVER=sqlite
PRINCESS_DB_PATH=storage/tracker.sqlite
PRINCESS_HEADERS_PATH=storage/headers.json
```

Then initialize DB:

```bash
php cron_check.php --init-db
```

Visit:

```text
https://yourdomain.com/princess-tracker/
```

## Deployment paths

Set the deployment path in GitHub secrets.

Public browser path:

```text
BLUEHOST_PUBLIC_PATH=/home/YOUR_BLUEHOST_USER/public_html/princess-tracker
```

Private app/runtime path:

```text
BLUEHOST_PRIVATE_PATH=/home/YOUR_BLUEHOST_USER/apps/princess-tracker-private
```

The public app auto-detects the private bootstrap at:

```text
/home/YOUR_BLUEHOST_USER/apps/princess-tracker-private/app/bootstrap.php
```

That works when public files are deployed to:

```text
/home/YOUR_BLUEHOST_USER/public_html/princess-tracker
```

If you use a different private path, set the web server environment variable `PRINCESS_BOOTSTRAP` to the full bootstrap path, or edit `public/_init.php`.

## Daily cron on Bluehost

In cPanel > Cron Jobs, run once per day:

```bash
cd /home/YOUR_BLUEHOST_USER/apps/princess-tracker-private && php cron_check.php >> logs/cron.log 2>&1
```

Example 9 AM daily:

```text
0 9 * * * cd /home/YOUR_BLUEHOST_USER/apps/princess-tracker-private && php cron_check.php >> logs/cron.log 2>&1
```

## What the graph shows

The History page graphs `fare_per_person` for a selected cruise code. Each cabin type gets one line. Sold-out cabins with no fare are omitted from the plotted line but remain in the history table.

## Security notes

- Keep `.env` outside `public_html`.
- Keep `storage/headers.json` outside `public_html`.
- Do not commit `.env`, `headers.json`, or the SQLite DB.
- The app uses a simple password gate. That is fine for a private tool, but do not expose it broadly.
