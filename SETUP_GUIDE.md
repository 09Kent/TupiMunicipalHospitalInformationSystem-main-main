# TMHIS — New Developer / New Device Setup Guide

> **Tupi Municipal Hospital Information Management System**
> Production-quality onboarding document — generated from actual repository analysis.

---

## Repository Analysis Summary

| Property | Value |
|---|---|
| **Project Name** | Tupi Municipal Hospital Information Management System (TMHIS) |
| **Laravel Version** | 13.x (framework `^13.17`) |
| **PHP Version** | `^8.3` (Dockerfile uses `php:8.3-fpm-alpine`) |
| **Database Engine** | MySQL 8.4 |
| **Database Name** | `MedicalRegistrationDB` |
| **Docker DB Service Name** | `db` (container: `tmhis_db`) |
| **Docker App Service Name** | `app` (container: `tmhis_app`) |
| **Docker phpMyAdmin Service** | `phpmyadmin` (container: `tmhis_phpmyadmin`) |
| **Laravel Sail** | ❌ **NOT used** |
| **App Port (Docker)** | `8000` → container port `80` |
| **DB Port (Docker, host)** | `3307` → container port `3306` |
| **phpMyAdmin Port** | `8080` |
| **Redis Required** | No (configured in `.env.example` but not used by Docker services) |
| **Node/Vite Required** | Yes — Vite + Tailwind CSS v4 |
| **Frontend Build Tool** | Vite 8 + `laravel-vite-plugin` + `@tailwindcss/vite` |
| **DB Username** | `root` |
| **DB Password** | `root_password` |
| **DB Initialization** | SQL files auto-imported via Docker `initdb.d` or `install.sql` |
| **Session/Cache Driver** | `file` (Docker override) / `file` + `database` (`.env.example`) |
| **Entrypoint Script** | `tmhis/docker/entrypoint.sh` (auto-creates `.env`, generates key, imports SQL) |
| **Root Landing Page** | `index.php` at repo root (static PHP landing page) |
| **Laravel App Directory** | `tmhis/` (the Laravel project lives inside this subdirectory) |

> **⚠️ IMPORTANT**
>
> This project has a **two-tier structure**:
> - **Root directory** (`/`) — Contains `index.php` (static landing page), `assets/`, and the root `docker-compose.yml`
> - **`tmhis/` subdirectory** — Contains the full Laravel 13 application with its own `Dockerfile`, `docker-compose.yml`, `composer.json`, `package.json`, etc.
>
> The **root `docker-compose.yml`** references `./tmhis/Dockerfile` as its build context. The **`tmhis/docker-compose.yml`** uses `.` as its build context. Both files are functionally equivalent — **use the root one** for typical development.

---

# ⚡ CHOOSE YOUR SETUP PATH

> **💡 TIP:** You only need **ONE** of these paths. Pick whichever suits your environment.

| | PATH A — Docker | PATH B — Native (No Docker) |
|---|---|---|
| **Best for** | Consistent, reproducible environment | Lightweight, fast startup |
| **Requires** | Docker Desktop + WSL 2 | Laragon or Laravel Herd |
| **DB runs in** | Container | Native MySQL on Windows |
| **PHP runs in** | Container | Native PHP on Windows |

---

# PATH A — DOCKER ROUTE

---

## A1. Install Git

1. **Verify Git is installed:**

```powershell
git --version
```

If Git is not recognized, download and install from: **https://git-scm.com/download/win**

During installation:
- ✅ Select "Git from the command line and also from 3rd-party software"
- ✅ Select "Checkout Windows-style, commit Unix-style line endings"
- ✅ Select "Use Windows' default console window"

2. **Configure Git identity** (required to push/pull):

```powershell
git config --global user.name "Your Full Name"
git config --global user.email "your.email@example.com"
```

3. **Verify configuration:**

```powershell
git config --global --list
```

---

## A2. Install Docker on a New Windows Machine

> **⚠️ IMPORTANT:** Docker Desktop requires **WSL 2** on Windows. Follow these steps in order.

### Step 1 — Install WSL 2

```powershell
wsl --install
```

This installs WSL 2 and Ubuntu by default. **Restart your computer** after this completes.

### Step 2 — Verify WSL after restart

```powershell
wsl --version
```

If WSL needs updating:

```powershell
wsl --update
```

### Step 3 — Install Docker Desktop

1. Download Docker Desktop from: **https://www.docker.com/products/docker-desktop/**
2. Run the installer
3. ✅ Ensure **"Use WSL 2 instead of Hyper-V"** is checked during installation
4. **Restart your computer** if prompted

### Step 4 — Start Docker Desktop

1. Launch **Docker Desktop** from the Start Menu
2. Wait for the Docker engine to fully start (the whale icon in system tray stops animating)

### Step 5 — Verify Docker installation

```powershell
# Check Docker CLI version
docker --version

# Check Docker Compose version
docker compose version

# Test Docker is working correctly
docker run --rm hello-world
```

If `docker run --rm hello-world` prints "Hello from Docker!", your installation is working.

---

## A3. Clone the Git Repository

```powershell
cd C:\Projects
git clone <REPOSITORY_URL>
cd TupiMunicipalHospitalInformationSystem-main
```

### Set up Git remote for push/pull

If you cloned via HTTPS and need to push, you may need to configure credentials:

```powershell
# Verify remote is set
git remote -v

# If using HTTPS, enable credential caching
git config --global credential.helper manager

# If you need to change the remote URL (e.g., to SSH)
git remote set-url origin git@github.com:<USERNAME>/<REPO>.git
```

**For SSH authentication:**

```powershell
# Generate SSH key (if you don't have one)
ssh-keygen -t ed25519 -C "your.email@example.com"

# Start the SSH agent
Get-Service ssh-agent | Set-Service -StartupType Automatic
Start-Service ssh-agent

# Add your key
ssh-add ~\.ssh\id_ed25519

# Copy the public key to clipboard — add this to your GitHub/GitLab account
Get-Content ~\.ssh\id_ed25519.pub | Set-Clipboard
```

### Verify repository state

```powershell
git status
```

> **📝 NOTE:** After cloning, `.env` and `vendor/` will be **missing** — this is expected because they are in `.gitignore`. The Docker setup handles this automatically via the entrypoint script.

---

## A4. Inspect the Existing Docker Configuration

The repository contains **two** `docker-compose.yml` files:

| File | Build Context | Use Case |
|---|---|---|
| `./docker-compose.yml` (root) | `./tmhis` | ✅ **Use this one** — runs from the repo root |
| `./tmhis/docker-compose.yml` | `.` (tmhis dir) | Alternative — runs from inside `tmhis/` |

### Docker services defined (from root `docker-compose.yml`):

| Service | Container Name | Image/Build | Ports |
|---|---|---|---|
| `app` | `tmhis_app` | Built from `tmhis/Dockerfile` (PHP 8.3 + Nginx + Supervisor) | `8000:80` |
| `db` | `tmhis_db` | `mysql:8.4` | `3307:3306` |
| `phpmyadmin` | `tmhis_phpmyadmin` | `phpmyadmin/phpmyadmin:latest` | `8080:80` |

### Key Docker facts:
- **PHP version**: 8.3 (FPM on Alpine Linux)
- **Web server**: Nginx (inside the `app` container, managed by Supervisor)
- **Database**: MySQL 8.4, service name `db`
- **Database is auto-initialized**: SQL schema files are mounted into `/docker-entrypoint-initdb.d/` and imported on first run
- **Entrypoint script** (`tmhis/docker/entrypoint.sh`) auto-creates `.env`, generates `APP_KEY`, and imports SQL data
- **No Laravel Sail** — this project uses a custom Docker setup

---

## A5. Create `.env`

> **📝 NOTE:** The Docker `entrypoint.sh` script will auto-create `.env` from `.env.example` if it doesn't exist. However, creating it manually first allows you to customize values.

```powershell
Copy-Item tmhis\.env.example tmhis\.env
```

Or in Git Bash:

```bash
cp tmhis/.env.example tmhis/.env
```

> **⚠️ WARNING:** `.env` must remain **local only**. It is in `.gitignore` and must **never** be committed to version control.

---

## A6. Configure the Docker Database

When Laravel runs **inside** the Docker container, it communicates with MySQL over the **Docker network** using the Compose service name, **not** `localhost`.

```
┌────────────────────┐
│   app container    │
│  (tmhis_app)       │
│   Laravel/PHP      │
│      ↓             │
│  DB_HOST = db      │
└────────┬───────────┘
         │ Docker internal network
┌────────┴───────────┐
│   db container     │
│  (tmhis_db)        │
│   MySQL 8.4        │
│   port 3306        │
└────────────────────┘
```

The **root `docker-compose.yml`** already sets these environment variables directly on the `app` service, so the `.env` file values are **overridden** by Docker Compose:

```yaml
environment:
  DB_CONNECTION: mysql
  DB_HOST: db              # ← Docker service name, NOT localhost
  DB_PORT: 3306
  DB_DATABASE: MedicalRegistrationDB
  DB_USERNAME: root
  DB_PASSWORD: root_password
```

> **⚠️ IMPORTANT:** Inside Docker, `DB_HOST` **must be `db`** (the Compose service name). Using `127.0.0.1` or `localhost` would fail because the database is in a separate container.

**Accessing MySQL from your host machine** (e.g., with HeidiSQL, TablePlus, DBeaver):

```
Host:     127.0.0.1
Port:     3307          ← mapped port on host
Database: MedicalRegistrationDB
Username: root
Password: root_password
```

---

## A7. Build and Start the Docker Containers

From the **repository root** directory:

```powershell
docker compose up -d --build
```

This will:
1. **Build** the `app` image from `tmhis/Dockerfile` (installs PHP 8.3, extensions, Composer dependencies, Nginx, Supervisor)
2. **Pull** `mysql:8.4` and `phpmyadmin/phpmyadmin:latest`
3. **Start** all three containers in detached mode
4. **Auto-initialize** the database — the SQL schema files are mounted as Docker `initdb.d` scripts and run automatically on first container creation
5. **Run the entrypoint** — creates `.env` (if missing), generates `APP_KEY`, and starts Nginx + PHP-FPM via Supervisor

> **📝 NOTE:** The Dockerfile runs `composer install` during the build phase. You do **NOT** need to run it separately for Docker. The entrypoint script also handles `APP_KEY` generation automatically.

---

## A8. Verify Containers

```powershell
docker compose ps
```

You should see **three containers** all in a `running` (healthy) state:

```
NAME              SERVICE       STATUS
tmhis_app         app           Up (running)
tmhis_db          db            Up (healthy)
tmhis_phpmyadmin  phpmyadmin    Up (running)
```

If `db` shows `(health: starting)`, wait 15-30 seconds and check again — MySQL needs time to initialize.

### Check container logs

```powershell
# All services
docker compose logs

# Specific service
docker compose logs app
docker compose logs db

# Follow logs in real time
docker compose logs -f app
```

---

## A9. Verify the Application

The Docker entrypoint script handles database initialization automatically using the SQL files mounted to `/docker-entrypoint-initdb.d/`. **No manual migration or seeding is required.**

### If you need to run Laravel Artisan commands inside the container:

```powershell
# Run migrations (if needed for Laravel's internal tables)
docker compose exec app php artisan migrate

# Run migrations with seed data
docker compose exec app php artisan migrate --seed

# Clear caches
docker compose exec app php artisan optimize:clear

# Open a shell inside the app container
docker compose exec app sh
```

---

## A10. Install Frontend Dependencies (Inside Docker)

The Dockerfile does **not** install Node.js or run `npm`. If you need to build the Vite/Tailwind CSS frontend assets:

**Option 1 — Run npm on your host machine** (requires Node.js installed locally):

```powershell
cd tmhis
npm install
npm run build
```

**Option 2 — Use a temporary Node container:**

```powershell
docker run --rm -v "${PWD}/tmhis:/app" -w /app node:20-alpine sh -c "npm install && npm run build"
```

For live development with hot-reload:

```powershell
cd tmhis
npm install
npm run dev
```

> **📝 NOTE:** The Vite dev server runs on `localhost:5173` by default. The built assets (`public/build/`) are what the production container serves.

---

## A11. Access the Application

| Service | URL | Description |
|---|---|---|
| **Laravel App** | **http://localhost:8000** | Main TMHIS application |
| **phpMyAdmin** | **http://localhost:8080** | Database management UI |
| **Root Landing Page** | N/A (served by the landing `index.php` in non-Docker native setups) | Static hospital portal page |

**phpMyAdmin credentials:**
- Server: `db` (auto-configured)
- Username: `root`
- Password: `root_password`

---

## A12. Useful Docker Commands

```powershell
# Stop all containers
docker compose down

# Stop and remove volumes (WARNING: destroys database data)
docker compose down -v

# Restart containers
docker compose restart

# Rebuild without cache
docker compose build --no-cache

# View running containers
docker ps

# Execute a command in the app container
docker compose exec app php artisan <command>

# Access the app container shell
docker compose exec app sh

# Access the MySQL CLI
docker compose exec db mysql -u root -proot_password MedicalRegistrationDB
```

---

## A13. Git Push & Pull Workflow

```powershell
# Pull latest changes
git pull origin main

# After pulling, rebuild if Docker files changed
docker compose up -d --build

# Stage, commit, and push your changes
git add .
git commit -m "Your commit message"
git push origin main

# Create and switch to a new branch
git checkout -b feature/your-feature-name

# Push a new branch
git push -u origin feature/your-feature-name
```

---

## A14. Docker Troubleshooting

### ❌ `vendor/autoload.php not found`

The Dockerfile runs `composer install` during build. If this error appears:

```powershell
docker compose build --no-cache
docker compose up -d
```

### ❌ Database connection refused / `SQLSTATE[HY000] [2002]`

1. Check that the `db` container is healthy:
   ```powershell
   docker compose ps
   ```
2. Check DB logs:
   ```powershell
   docker compose logs db
   ```
3. Ensure `DB_HOST=db` (not `localhost`) inside the container environment.

### ❌ Port already in use

If port `8000`, `3307`, or `8080` is taken:

```powershell
# Find what's using the port
netstat -ano | findstr :8000
```

Edit the root `docker-compose.yml` port mapping temporarily, e.g., change `"8000:80"` to `"8001:80"`.

### ❌ Docker Desktop not running

Ensure Docker Desktop is running (whale icon in system tray). If `docker` commands fail:

```powershell
# Restart Docker Desktop from command line
& "C:\Program Files\Docker\Docker\Docker Desktop.exe"
```

### ❌ WSL problems

```powershell
wsl --status
wsl --update
wsl --shutdown
```

Then restart Docker Desktop.

### ❌ Missing `.env` or `APP_KEY`

The entrypoint script handles this automatically. If issues persist:

```powershell
docker compose exec app php artisan key:generate --force
```

### ❌ Database not initialized (empty tables)

The SQL files are imported only on **first** `db` container creation. To re-initialize:

```powershell
# WARNING: This destroys all database data
docker compose down -v
docker compose up -d --build
```

### ❌ Cached configuration issues

```powershell
docker compose exec app php artisan optimize:clear
```

---

# PATH B — NATIVE / NON-DOCKER ROUTE

> **⚠️ IMPORTANT:** In this path, Docker is **completely ignored**. The `docker-compose.yml`, `Dockerfile`, and `docker/` directory remain in the repository but are not used. **Do NOT delete them.**

---

## B1. Choose a Native Development Environment

### Option 1 — Laragon (Recommended for this project)

**Laragon** provides an all-in-one local development environment:
- ✅ Apache / Nginx
- ✅ PHP (multiple versions)
- ✅ MySQL / MariaDB
- ✅ Node.js
- ✅ Composer
- ✅ HeidiSQL (database manager)
- ✅ Auto virtual hosts (e.g., `http://project.test`)

Download: **https://laragon.org/download/**

Best for: Projects that need a full LAMP/LEMP stack with easy MySQL management.

### Option 2 — Laravel Herd

**Laravel Herd** is a lightweight, Laravel-focused development environment:
- ✅ PHP (managed versions)
- ✅ Nginx (built-in)
- ✅ Composer (bundled)
- ✅ Node.js (bundled)
- ❌ Does **not** include MySQL — you'll need to install MySQL separately

Download: **https://herd.laravel.com/windows**

Best for: Developers who prefer a minimal PHP environment and manage their database separately.

> **💡 TIP:** **Laragon** is recommended for this project because it includes MySQL out of the box, which this project requires.

---

## B2. Install and Verify Native Tools

After installing your chosen environment, verify all tools:

```powershell
# PHP — must be 8.3 or higher
php -v

# Composer
composer --version

# Node.js — required for Vite/Tailwind CSS frontend build
node -v

# NPM
npm -v

# MySQL
mysql --version

# Git
git --version
```

**Required versions:**
| Tool | Minimum Version | Notes |
|---|---|---|
| PHP | 8.3 | Required by `composer.json` (`^8.3`) |
| Composer | 2.x | Any recent Composer 2 |
| Node.js | 18+ | Required for Vite 8 |
| MySQL | 8.0+ | Project uses MySQL 8.4 in Docker |

**Required PHP extensions** (from the Dockerfile):
- `pdo_mysql`
- `bcmath`
- `mbstring`
- `gd`
- `zip`
- `opcache`
- `intl`

In Laragon, most of these are enabled by default. Verify with:

```powershell
php -m
```

---

## B3. Clone the Repository

### If using Laragon:

```powershell
cd C:\laragon\www
git clone <REPOSITORY_URL>
cd TupiMunicipalHospitalInformationSystem-main
```

The project will be available via Laragon's auto virtual host at:
```
http://TupiMunicipalHospitalInformationSystem-main.test
```

Or directly via the `tmhis/public` folder.

### If using Herd:

```powershell
cd C:\Users\<YourUser>\Herd
git clone <REPOSITORY_URL>
cd TupiMunicipalHospitalInformationSystem-main
```

Then link the Laravel site:

```powershell
cd tmhis
herd link tmhis
```

The application will be available at: `http://tmhis.test`

### Configure Git for push/pull:

```powershell
git config --global user.name "Your Full Name"
git config --global user.email "your.email@example.com"

# Verify remote
git remote -v

# For HTTPS — enable credential caching
git config --global credential.helper manager

# For SSH — generate and add key
ssh-keygen -t ed25519 -C "your.email@example.com"
Get-Content ~\.ssh\id_ed25519.pub | Set-Clipboard
# Add the copied public key to your GitHub/GitLab account

git remote set-url origin git@github.com:<USERNAME>/<REPO>.git
```

---

## B4. Ignore Docker

> **⚠️ WARNING:**
> **Do NOT** run any of these commands in the native path:
> ```powershell
> docker compose up          # ← NO
> docker compose build       # ← NO
> ```
>
> The Docker files (`docker-compose.yml`, `Dockerfile`, `docker/`) are part of the repository for developers who choose the Docker route. **Leave them untouched. Do NOT delete them.**

---

## B5. Install Composer Dependencies

```powershell
cd tmhis
composer install
```

This reads `composer.json` and `composer.lock` and creates the `vendor/` directory.

> **⚠️ IMPORTANT:** Use `composer install` (not `composer update`). The `composer.lock` file ensures everyone gets exactly the same dependency versions.

---

## B6. Create `.env`

```powershell
Copy-Item .env.example .env
```

Or in Git Bash:

```bash
cp .env.example .env
```

---

## B7. Configure Native Database Connection

> **🔴 CAUTION:** This is the **most critical** difference between Docker and native setups. The `.env.example` file already has `DB_HOST=127.0.0.1`, which is correct for native. However, verify all values match your local MySQL setup.

Edit `tmhis/.env` and set:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=MedicalRegistrationDB
DB_USERNAME=root
DB_PASSWORD=
```

> **⚠️ IMPORTANT:**
> **`DB_HOST` must be `127.0.0.1`** (or `localhost`) for native setups. In the Docker path, it's `db` (the Compose service name). This is the key difference.
>
> **`DB_PASSWORD`**: The `.env.example` has `root_password` which is the Docker MySQL password. For Laragon's default MySQL, the root password is typically **empty** (blank). For other MySQL installations, use your actual root password.

**Native explanation:**

```
┌────────────────────┐
│  PHP (native)      │
│  Laravel App       │
│      ↓             │
│ DB_HOST=127.0.0.1  │
│ connects via TCP   │
└────────┬───────────┘
         │ localhost:3306
┌────────┴───────────┐
│ MySQL (native)     │
│ Running on Windows │
│ port 3306          │
└────────────────────┘
```

---

## B8. Start Native MySQL

### Laragon

1. Open **Laragon**
2. Click **"Start All"** — this starts both Apache and MySQL
3. MySQL will be running on `localhost:3306`

### Standalone MySQL

If you installed MySQL separately, ensure the service is running:

```powershell
# Check if MySQL service is running
Get-Service -Name "MySQL*"

# Start it if needed
Start-Service -Name "MySQL80"
```

---

## B9. Create the Database

Open a MySQL client (HeidiSQL in Laragon, MySQL CLI, phpMyAdmin, or any client) and run:

```sql
CREATE DATABASE IF NOT EXISTS `MedicalRegistrationDB` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Using MySQL CLI:**

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS MedicalRegistrationDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

> For Laragon's default MySQL (no root password):
> ```powershell
> mysql -u root -e "CREATE DATABASE IF NOT EXISTS MedicalRegistrationDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
> ```

### Import the Database Schema and Seed Data

The project includes a unified SQL installation script. From the `tmhis/` directory:

```powershell
mysql -u root MedicalRegistrationDB < database\install.sql
```

> For MySQL with a root password:
> ```powershell
> mysql -u root -p MedicalRegistrationDB < database\install.sql
> ```

**Alternative — import individual SQL files in order:**

```powershell
mysql -u root MedicalRegistrationDB < database\sql\register_schema.sql
mysql -u root MedicalRegistrationDB < database\sql\doctor_schema.sql
mysql -u root MedicalRegistrationDB < database\sql\tmhis_master_schema.sql
mysql -u root MedicalRegistrationDB < database\sql\comprehensive_seed.sql
```

The database name **must** match the `DB_DATABASE` value in your `.env`:

```env
DB_DATABASE=MedicalRegistrationDB
```

---

## B10. Generate Laravel Application Key

```powershell
php artisan key:generate
```

This generates a unique `APP_KEY` in your `.env` file, used for encryption.

---

## B11. Clear Laravel Configuration Cache

```powershell
php artisan optimize:clear
```

> **⚠️ IMPORTANT:** This is especially important when switching between Docker and native configurations. Cached Docker settings (like `DB_HOST=db`) will cause connection failures in native mode.

---

## B12. Run Migrations

```powershell
php artisan migrate
```

> **📝 NOTE:** The main database schema is loaded via the SQL import in step B9. Laravel migrations handle the framework's internal tables (`users` for Laravel auth, `cache`, `jobs`, etc.). If seeders are needed:
> ```powershell
> php artisan migrate --seed
> ```

---

## B13. Install Frontend Dependencies

The project uses **Vite 8** with **Tailwind CSS v4** and the **Laravel Vite Plugin**.

```powershell
npm install
```

### Build for production:

```powershell
npm run build
```

### Run Vite dev server (hot-reload during development):

```powershell
npm run dev
```

> Vite dev server runs at `http://localhost:5173` and proxies asset requests.

---

## B14. Start the Laravel Application

### Option A — PHP built-in server

```powershell
php artisan serve
```

Application available at: **http://127.0.0.1:8000**

### Option B — Laragon's Apache/Nginx

If you placed the project in `C:\laragon\www\`:

1. Start Laragon services
2. Access the **root landing page** at:
   ```
   http://TupiMunicipalHospitalInformationSystem-main.test
   ```
3. Access the **Laravel app** at:
   ```
   http://TupiMunicipalHospitalInformationSystem-main.test/tmhis/public
   ```

> **💡 TIP:** For a cleaner URL with Laragon, you can create a virtual host pointing directly to `tmhis/public/`.

### Option C — Laravel Herd

If you linked the site with `herd link tmhis`:

Application available at: **http://tmhis.test**

---

## B15. Git Push & Pull Workflow

```powershell
# Pull latest changes
git pull origin main

# After pulling, install any new dependencies
cd tmhis
composer install
npm install
npm run build
php artisan migrate
php artisan optimize:clear

# Stage, commit, and push
git add .
git commit -m "Your commit message"
git push origin main

# Create and work on a branch
git checkout -b feature/your-feature-name
git push -u origin feature/your-feature-name

# Switch back to main
git checkout main
git pull origin main
```

---

## B16. Native Troubleshooting

### ❌ `php` is not recognized

- **Laragon**: Ensure Laragon is running and PHP is in your PATH. Go to Laragon → Menu → Tools → PATH → Add Laragon to PATH.
- **Herd**: Restart your terminal after Herd installation.
- **Manual**: Add PHP to your system PATH: `C:\laragon\bin\php\php-8.3.x` (adjust version).

### ❌ `composer` is not recognized

- **Laragon**: Composer is bundled. Ensure Laragon's PATH is set.
- **Manual**: Download from https://getcomposer.org/download/ and install globally.

### ❌ `node` / `npm` is not recognized

- **Laragon**: Enable Node.js in Laragon → Menu → Tools → Quick Add → Node.js.
- **Manual**: Download from https://nodejs.org/en/download/ (LTS version).

### ❌ MySQL not running / connection refused

```powershell
# Check MySQL service
Get-Service -Name "MySQL*"

# Laragon: Ensure "Start All" is clicked and MySQL shows as green
```

### ❌ `SQLSTATE[HY000] [1049] Unknown database 'MedicalRegistrationDB'`

Create the database first:

```sql
CREATE DATABASE IF NOT EXISTS `MedicalRegistrationDB` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### ❌ `SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'`

Your MySQL credentials in `.env` don't match your local MySQL. For Laragon's default:

```env
DB_USERNAME=root
DB_PASSWORD=
```

### ❌ `SQLSTATE[HY000] [2002] Connection refused` or `No connection could be made`

MySQL is not running. Start it via Laragon or:

```powershell
Start-Service -Name "MySQL80"
```

### ❌ `vendor/autoload.php` not found

Run from the `tmhis/` directory:

```powershell
composer install
```

### ❌ `No application encryption key has been specified`

```powershell
php artisan key:generate
```

### ❌ Cached Docker database settings (`DB_HOST=db`)

```powershell
php artisan optimize:clear
php artisan config:clear
```

Then verify `tmhis/.env` has `DB_HOST=127.0.0.1`.

### ❌ Port 8000 already in use

```powershell
php artisan serve --port=8001
```

### ❌ Missing PHP extensions

Check installed extensions:

```powershell
php -m
```

Required: `pdo_mysql`, `bcmath`, `mbstring`, `gd`, `zip`, `intl`, `opcache`.

In Laragon, enable missing extensions via: Laragon → Menu → PHP → Extensions → check the required ones.

### ❌ NPM dependency errors

```powershell
# Clear NPM cache and reinstall
Remove-Item -Recurse -Force node_modules
Remove-Item package-lock.json
npm install
```

### ❌ Wrong PHP version

This project requires PHP `^8.3`. Check your version:

```powershell
php -v
```

In Laragon: Menu → PHP → Version → select PHP 8.3.x.

---

# Docker vs Native Comparison

| Component | Docker (Path A) | Native (Path B) |
|---|---|---|
| **PHP** | Container (`php:8.3-fpm-alpine`) | Windows (Laragon/Herd) — PHP 8.3+ |
| **Web Server** | Nginx (inside container, via Supervisor) | Apache/Nginx (Laragon/Herd) or `php artisan serve` |
| **MySQL** | Container (`mysql:8.4`, service `db`) | Native MySQL (Laragon or standalone) |
| **`DB_HOST`** | `db` (Docker Compose service name) | `127.0.0.1` |
| **`DB_PASSWORD`** | `root_password` | Depends on local MySQL (Laragon default: empty) |
| **DB Port (host access)** | `3307` | `3306` |
| **Composer** | Runs inside container during build | Local `composer install` |
| **Artisan** | `docker compose exec app php artisan ...` | `php artisan ...` |
| **NPM/Vite** | Local or via `node:20-alpine` container | Local `npm install && npm run build` |
| **Application URL** | `http://localhost:8000` | `http://127.0.0.1:8000` or `http://project.test` |
| **phpMyAdmin** | `http://localhost:8080` (container) | HeidiSQL (Laragon) or standalone |
| **Docker required** | ✅ Yes | ❌ No |
| **DB Auto-initialization** | ✅ Yes (via `initdb.d` SQL mounts) | ❌ Manual SQL import required |

---

# QUICK START — DOCKER

```powershell
# 1. Clone the repository
git clone <REPOSITORY_URL>
cd TupiMunicipalHospitalInformationSystem-main

# 2. Configure Git identity
git config --global user.name "Your Full Name"
git config --global user.email "your.email@example.com"

# 3. Create .env (optional — entrypoint does this automatically)
Copy-Item tmhis\.env.example tmhis\.env

# 4. Build and start all containers
docker compose up -d --build

# 5. Wait for db to be healthy, then verify
docker compose ps

# 6. (Optional) Build frontend assets locally
cd tmhis
npm install
npm run build
cd ..

# 7. Access the application
# App:        http://localhost:8000
# phpMyAdmin: http://localhost:8080
```

> Database is auto-initialized. `composer install` runs inside the Docker build. `APP_KEY` is auto-generated by the entrypoint.

---

# QUICK START — NATIVE (Laragon)

```powershell
# 1. Clone into Laragon's www directory
cd C:\laragon\www
git clone <REPOSITORY_URL>
cd TupiMunicipalHospitalInformationSystem-main

# 2. Configure Git identity
git config --global user.name "Your Full Name"
git config --global user.email "your.email@example.com"

# 3. Start Laragon (Apache + MySQL)
# → Open Laragon and click "Start All"

# 4. Create the database
mysql -u root -e "CREATE DATABASE IF NOT EXISTS MedicalRegistrationDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 5. Enter the Laravel directory
cd tmhis

# 6. Import the database schema and seed data
mysql -u root MedicalRegistrationDB < database\install.sql

# 7. Install Composer dependencies
composer install

# 8. Create .env and configure
Copy-Item .env.example .env
# Edit .env → set DB_PASSWORD= (empty for Laragon default)

# 9. Generate application key
php artisan key:generate

# 10. Clear any cached config
php artisan optimize:clear

# 11. Run Laravel migrations
php artisan migrate

# 12. Install frontend dependencies and build
npm install
npm run build

# 13. Start the application
php artisan serve

# 14. Access the application
# → http://127.0.0.1:8000
```

---

# NEW DEVICE CHECKLIST

## ✅ Docker Checklist

```
[ ] Git installed and configured (user.name, user.email)
[ ] Git remote configured for push/pull (HTTPS credentials or SSH key)
[ ] WSL 2 installed (wsl --install, restart)
[ ] Docker Desktop installed
[ ] Docker Desktop running (whale icon in system tray)
[ ] Docker verified (docker run --rm hello-world)
[ ] Repository cloned
[ ] .env created in tmhis/ (optional — entrypoint auto-creates)
[ ] Docker DB_HOST = db (set via docker-compose.yml environment)
[ ] Containers built and started (docker compose up -d --build)
[ ] All containers running (docker compose ps)
[ ] Database container healthy
[ ] Database auto-initialized (SQL files imported)
[ ] APP_KEY generated (auto by entrypoint)
[ ] Frontend dependencies installed (npm install in tmhis/)
[ ] Frontend built (npm run build in tmhis/)
[ ] Application accessible at http://localhost:8000
[ ] phpMyAdmin accessible at http://localhost:8080
```

## ✅ Native Checklist

```
[ ] Git installed and configured (user.name, user.email)
[ ] Git remote configured for push/pull (HTTPS credentials or SSH key)
[ ] Laragon (or Herd) installed
[ ] PHP 8.3+ installed and in PATH
[ ] Composer installed and in PATH
[ ] Node.js 18+ / NPM installed and in PATH
[ ] MySQL running (Laragon → Start All)
[ ] Repository cloned
[ ] .env created in tmhis/ from .env.example
[ ] DB_HOST set to 127.0.0.1 in .env
[ ] DB_PASSWORD set correctly for local MySQL (Laragon default: empty)
[ ] Database MedicalRegistrationDB created
[ ] Database schema imported (install.sql)
[ ] Composer dependencies installed (composer install in tmhis/)
[ ] APP_KEY generated (php artisan key:generate)
[ ] Laravel cache cleared (php artisan optimize:clear)
[ ] Migrations run (php artisan migrate)
[ ] Frontend dependencies installed (npm install in tmhis/)
[ ] Frontend built (npm run build in tmhis/)
[ ] Application accessible at http://127.0.0.1:8000 (or .test domain)
```
