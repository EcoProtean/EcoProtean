# EcoProtean - PHP Setup Guide

## Requirements
- XAMPP (Apache + MySQL + PHP 8+)
- A browser

---

## Step 1 — Set up the Database
1. Open **phpMyAdmin**: http://localhost/phpmyadmin
2. Click **"New"** in the left sidebar
3. Name the database: `ecoprotean` → click **Create**
4. Click the **SQL** tab
5. Paste the contents of your existing `database.sql` and click **Go**

---

## Step 2 — Copy project to XAMPP
1. Copy the entire `ecoprotean` folder into:
   - **Windows:** `C:\xampp\htdocs\ecoproteau`
   - **Mac/Linux:** `/opt/lampp/htdocs/ecoproteau`

---

## Step 3 — Configure database connection
Open `config.php` and confirm these match your setup:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');        // Leave empty for default XAMPP
define('DB_NAME', 'ecoprotean');
```

---

## Step 4 — Run the app
Open your browser and go to:
```
http://localhost/ecoprotean/
```

---

## Login Credentials (from sample data)
| Role    | Email                      | Password      |
|---------|----------------------------|---------------|
| Admin   | admin@ecoprotean.com       | password123   |
| Manager | manager@ecoprotean.com     | password123  |
| User    | user@ecoprotean.com        | password123 |

---

## File Structure
```
ecoproteau/
├── config.php              ← DB connection + helper functions
├── index.php               ← Home page
├── login.php               ← Login page
├── logout.php              ← Logout
├── style.css               ← Root styles
├── database.sql            ← Your original schema (unchanged)
├── api/
│   ├── locations.php       ← API: returns map markers from DB
│   └── recommendations.php ← API: returns tree recommendations
├── admin/
│   └── index.php           ← Admin/Manager dashboard
└── Web App/
    ├── Risk Map/
    │   ├── index.php       ← Risk Map page
    │   ├── services.js     ← Updated: fetches from DB via API
    │   └── style.css       ← (copy your original riskmap style.css here)
    └── About/
        ├── index.php       ← About page
        └── style.css       ← (copy your original about style.css here)
```

---

## What changed from the original HTML version
| Before (Electron/HTML)         | After (PHP + MySQL)                         |
|-------------------------------|---------------------------------------------|
| `main.js` + `preload.js`      | Removed — not needed for web                |
| Hardcoded risk areas in JS    | Loaded from `locations` table via API       |
| No login system               | Full login with session + role check        |
| No activity tracking          | Every page visit logged to `activity_logs`  |
| `.html` file links            | `.php` file links                           |
| Tree recommendations static   | Loaded live from `tree_recommendations` table |
