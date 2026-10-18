# TMHIS — Production Deployment & Operations Guide

**System:** Tupi Municipal Hospital Information Management System (TMHIS)  
**Target Environments:** Local (Laragon/PHP-CLI), Docker, Linux VPS (Nginx/Apache), and Vercel Serverless.

---

## 1. Prerequisites

- **PHP:** 8.2 or higher (Extensions required: `pdo`, `pdo_mysql`, `pdo_pgsql`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `curl`)
- **Database:** MySQL 8.0+ / MariaDB 10.5+ OR PostgreSQL 15+ (e.g. Supabase)
- **Composer:** 2.x
- **Node.js & NPM:** 18+ (for building assets if modifying frontend bundles)

---

## 2. Option A: Local Development Deployment (Laragon / Artisan)

### Step 1: Clone & Navigate
```bash
cd c:\laragon\www\TupiMunicipalHospitalInformationSystem-main-main\tmhis
```

### Step 2: Configure Environment
Copy `.env.example` to `.env` if not already present:
```bash
cp .env.example .env
```
Ensure your database credentials in `.env` match your local MySQL server:
```env
APP_NAME="Tupi Municipal Hospital"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=MedicalRegistrationDB
DB_USERNAME=root
DB_PASSWORD=
```

### Step 3: Install Dependencies
```bash
composer install
```

### Step 4: Run Database Migrations
```bash
php artisan migrate
```

### Step 5: Start Local Server
```bash
php artisan serve --host=127.0.0.1 --port=8000
```
Access the application at `http://127.0.0.1:8000`.

---

## 3. Option B: Docker Deployment

The repository includes a ready-to-run `docker-compose.yml` configuring Nginx, PHP 8.2-FPM, and MariaDB.

### Step 1: Start Containers
From the repository root:
```bash
docker compose up -d --build
```

### Step 2: Run Migrations inside Container
```bash
docker compose exec app php artisan migrate --force
```

### Step 3: Access Application
Access at `http://localhost:8080`.

---

## 4. Option C: Vercel Serverless Deployment

TMHIS is fully optimized for Vercel Serverless with zero-configuration deployments.

### Root Deployment
1. Import the repository into your Vercel Dashboard.
2. In Project Settings, set **Framework Preset** to `Other` and leave Root Directory as `./`.
3. Configure the following Environment Variables in Vercel:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:your-generated-app-key
   APP_URL=https://your-vercel-app.vercel.app

   DB_CONNECTION=pgsql
   DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
   DB_PORT=6543
   DB_DATABASE=postgres
   DB_USERNAME=postgres.your-project-ref
   DB_PASSWORD=your-secure-supabase-password
   DB_SSLMODE=require

   SESSION_DRIVER=cookie
   CACHE_STORE=array
   LOG_CHANNEL=stderr
   ```
4. Deploy. Vercel routes all requests through `api/index.php` which initializes writable `/tmp/storage` directories automatically.

---

## 5. Option D: Linux VPS (Ubuntu / Debian + Nginx + PHP-FPM)

### Step 1: Directory Setup
```bash
sudo mkdir -p /var/www/tmhis
sudo chown -R $USER:www-data /var/www/tmhis
git clone <repo-url> /var/www/tmhis
cd /var/www/tmhis/tmhis
composer install --no-dev --optimize-autoloader
```

### Step 2: Permissions
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Step 3: Environment Setup
```bash
cp .env.example .env
php artisan key:generate
# Configure production DB credentials in .env
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 4: Nginx Server Block
```nginx
server {
    listen 80;
    server_name tmhis.tupihospital.gov.ph;
    root /var/www/tmhis/tmhis/public;

    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 6. Pre-Flight Verification Checklist

Run these commands prior to declaring production readiness:

```bash
# 1. Run PHPUnit Feature & Unit Tests
php artisan test

# 2. Run Comprehensive End-to-End Hospital Workflow Simulation
php test_e2e.php

# 3. Verify Route Caching
php artisan route:list

# 4. Confirm Production Debug Status
php artisan tinker --execute="echo 'APP_DEBUG is ' . (config('app.debug') ? 'TRUE (UNSAFE)' : 'FALSE (SECURE)') . PHP_EOL;"
```

---

## 7. Default Hospital Roles & Credentials (Demo/Testing Only)

| Role | Username / Email | Default Password | Initial Route |
| :--- | :--- | :--- | :--- |
| **System Administrator** | `admin` | `password123` | `/admin` |
| **Medical Director / CMO** | `director` | `password123` | `/director` |
| **Doctor / Physician** | `dr.lewis` / `dr.chen` | `password123` | `/doctor` |
| **Nurse on Duty** | `staff.reyes` / `nurse` | `password123` | `/nurse` |
| **Medical Technologist**| `medtech` | `password123` | `/medtech` |
| **Pharmacist** | `pharmacist` | `password123` | `/pharmacy` |
| **Billing / Cashier** | `cashier` | `password123` | `/billing` |
| **Medical Records** | `records` | `password123` | `/records` |
| **Admitting / Registration** | `register` | `password123` | `/register` |

> [!IMPORTANT]
> Immediately change default passwords after deploying to an active public network.
