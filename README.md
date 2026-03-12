# 🔋 MicroGrid Pro — Smart Hybrid Microgrid Monitoring, Governance & Optimization Platform

A comprehensive renewable energy management system for monitoring hybrid microgrids (solar + wind), managing battery storage, analyzing energy production, and providing financial and environmental insights — built with PHP, MySQL, and Chart.js.

---

## 📋 Table of Contents

- [Features](#-features)
- [Architecture](#-architecture)
- [Setup Instructions](#-setup-instructions)
- [Database Design](#-database-design)
- [API Documentation](#-api-documentation)
- [User Guide](#-user-guide)
- [Demo Credentials](#-demo-credentials)
- [Technology Stack](#-technology-stack)

---

## ✨ Features

### 1. User Management & RBAC
- Admin and User role-based access control
- Admin can manage families, microgrids, and users
- Users see only their own family's data (data isolation)

### 2. Microgrid Monitoring
- Real-time monitoring of voltage, current, power, and temperature
- Support for multiple microgrid types: **Solar** and **Wind**
- Live auto-refreshing charts (10-second polling)

### 3. Battery Monitoring
- State of Charge (SoC) visualization
- Voltage, temperature, and remaining energy tracking
- Charge/discharge status indicator
- 24-hour to 7-day history charts

### 4. Energy Analytics
- Daily energy generation (stacked bar chart — solar vs wind)
- Weekly trend analysis (line chart)
- Monthly reports (grouped bar chart)
- Period summaries: today, week, month, year

### 5. Source Contribution Analysis
- Pie/doughnut charts showing solar vs wind contribution
- Percentage breakdown with absolute kWh values
- Monthly and all-time comparisons

### 6. Financial Savings
- Configurable grid tariff rates
- Daily, monthly, and total savings calculation
- Formula: `Energy (kWh) × Tariff Rate = Savings`
- Environmental impact (CO₂ avoided, tree equivalents)

### 7. Fault Detection & Alerts
- Automatic alert generation for:
  - ⚡ Overvoltage / Undervoltage
  - 🔋 Battery Low / Overcharge
  - 🌡️ High Temperature
  - 🔧 Sensor Fault
- Alert management: Active → Acknowledged → Resolved
- Severity levels: Critical, Warning, Info

### 8. Multi-Family Platform
- Each family has their own microgrids, battery, and dashboard
- Admin can monitor all families from a centralized view
- Family-level energy comparison charts

---

## 🏗️ Architecture

```
microgrid-platform/
├── config/
│   └── database.php          # DB connection & app settings
├── includes/
│   ├── session.php            # Auth & session management
│   ├── functions.php          # All business logic functions
│   ├── header.php             # Page header & sidebar navigation
│   └── footer.php             # Page footer & JS includes
├── api/
│   ├── readings.php           # IoT API: energy readings (POST/GET)
│   ├── battery.php            # IoT API: battery status (POST/GET)
│   ├── analytics.php          # Internal AJAX API for charts
│   └── alerts.php             # Internal AJAX API for alert management
├── admin/
│   ├── families.php           # Admin: manage families
│   ├── microgrids.php         # Admin: manage microgrids
│   └── users.php              # Admin: manage users
├── assets/
│   ├── css/style.css          # Full dark-theme stylesheet
│   └── js/dashboard.js        # Sidebar toggle & Chart.js defaults
├── database/
│   ├── schema.sql             # Complete DB schema + seed data
│   ├── seed_demo_data.php     # Demo data generator (30 days)
│   └── generate_hash.php      # Password hash utility
├── index.php                  # Login page
├── dashboard.php              # Main dashboard (admin & user views)
├── monitor.php                # Live real-time monitoring
├── battery.php                # Battery storage page
├── analytics.php              # Energy analytics & reports
├── savings.php                # Financial savings & environmental impact
├── alerts.php                 # Alert management & fault detection
├── logout.php                 # Logout handler
├── .htaccess                  # Apache security & rewrite rules
└── README.md                  # This file
```

---

## 🚀 Setup Instructions

### Prerequisites
- **XAMPP** (Apache + MySQL + PHP 7.4+)
- A modern web browser

### Step-by-Step Setup

#### 1. Copy project to XAMPP
```
Copy the entire project folder to:
C:\xampp\htdocs\microgrid-platform\
```

#### 2. Start XAMPP
- Open XAMPP Control Panel
- Start **Apache** and **MySQL**

#### 3. Run the Installer (Recommended)
- Open your browser and go to:
```
http://localhost/microgrid-platform/install.php
```
- Click **"Run Installation"** — this creates the database, tables, and users with properly hashed passwords
- No manual SQL import needed!

#### Alternative: Manual SQL Import
- Open phpMyAdmin: http://localhost/phpmyadmin
- Click **"Import"** tab
- Select `database/schema.sql` and click **Go**
- ⚠️ Note: The SQL file contains placeholder password hashes. You may need to regenerate them:
  ```
  php database/generate_hash.php admin123
  php database/generate_hash.php user123
  ```
  Then update the hashes in phpMyAdmin, or simply use `install.php` instead.

#### 4. Generate Demo Data (Optional but Recommended)
- Open your browser and go to:
```
http://localhost/microgrid-platform/database/seed_demo_data.php
```
- This generates 30 days of realistic energy readings, battery data, and alerts

#### 5. Access the Platform
```
http://localhost/microgrid-platform/
```

---

## 🗄️ Database Design

### Entity-Relationship Overview

| Table | Description |
|-------|-------------|
| `families` | Family accounts (multi-tenant) |
| `users` | User accounts with RBAC (admin/user) |
| `microgrids` | Solar/wind microgrid units linked to families |
| `energy_readings` | IoT sensor data (voltage, current, power, temp) |
| `battery_status` | Battery SoC, voltage, charge status history |
| `alerts` | Fault detection alerts with severity & status |
| `energy_consumption` | Household energy consumption tracking |
| `tariff_settings` | Grid electricity rates for savings calculation |
| `api_keys` | API authentication for IoT devices |

### Key Relationships
- **Family** → has many **Users**, **Microgrids**, **Battery Records**, **Alerts**
- **Microgrid** → has many **Energy Readings**
- **Microgrid** → belongs to one **Family**
- All tables use foreign keys with appropriate CASCADE/SET NULL rules

---

## 📡 API Documentation

### IoT API — Energy Readings

**POST** `/api/readings.php`
```
Header: X-API-Key: <your_api_key>
Content-Type: application/json

Body:
{
    "microgrid_id": 1,
    "voltage": 325.5,
    "current_amp": 8.2,
    "power_kw": 2.67,
    "energy_kwh": 0.67,
    "temperature": 42.5
}

Response:
{ "success": true, "reading_id": 123, "message": "Reading recorded successfully" }
```

### IoT API — Battery Status

**POST** `/api/battery.php`
```
Header: X-API-Key: <your_api_key>
Content-Type: application/json

Body:
{
    "battery_level": 75.5,
    "voltage": 48.2,
    "remaining_kwh": 7.55,
    "charge_status": "charging",
    "temperature": 32.0
}
```

### Internal Analytics API

**GET** `/api/analytics.php?action=<action>&family_id=<id>`

| Action | Description |
|--------|-------------|
| `realtime` | Latest readings + battery + alerts |
| `daily_generation` | Daily kWh by source (param: `days`) |
| `source_contribution` | Solar vs wind totals (param: `period`) |
| `weekly_trends` | Weekly energy totals |
| `monthly_reports` | Monthly breakdown by source |
| `savings` | Financial savings calculation |
| `battery_history` | Battery SoC history (param: `hours`) |
| `platform_stats` | Admin-only platform overview |
| `all_families_energy` | Admin-only family comparison |

---

## 👤 User Guide

### Admin Dashboard
- View platform-wide statistics (families, microgrids, capacity, alerts)
- Bar chart comparing energy generation across all families
- Manage families, microgrids, and users via Administration menu
- Monitor any family's data by selecting from dropdown

### User Dashboard
- View today's energy, monthly energy, savings, and battery level
- See microgrid status cards with latest readings
- Source contribution doughnut chart
- Daily generation stacked bar chart
- Active alerts panel

### Navigation
| Page | Description |
|------|-------------|
| Dashboard | Overview & KPIs |
| Live Monitor | Real-time voltage/current/power with auto-refresh |
| Battery | Battery SoC, voltage history, temperature charts |
| Analytics | Daily, weekly, monthly energy analysis |
| Savings | Financial savings, tariff breakdown, environmental impact |
| Alerts | Fault detection, alert management |

---

## 🔑 Demo Credentials

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `admin123` |
| User | `sharma` | `user123` |
| User | `patel` | `user123` |
| User | `kumar` | `user123` |

### Demo API Keys
| Family | API Key |
|--------|---------|
| Sharma | `sk_sharma_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6` |
| Patel | `sk_patel_q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2` |
| Kumar | `sk_kumar_g3h4i5j6k7l8m9n0o1p2q3r4s5t6u7v8` |

---

## 🛠️ Technology Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 7.4+ |
| Database | MySQL 5.7+ (via XAMPP) |
| Frontend | HTML5, CSS3, Bootstrap 5.3 |
| Charts | Chart.js 4.4 |
| Icons | Bootstrap Icons |
| Fonts | Google Inter |
| Server | Apache (XAMPP) |
| Security | PDO prepared statements, password_hash, CSRF tokens, RBAC, .htaccess protection |

---

## 🔒 Security Features

- **SQL Injection Prevention**: All queries use PDO prepared statements
- **XSS Prevention**: All output escaped with `htmlspecialchars()`
- **CSRF Protection**: Token validation on all form submissions
- **Password Security**: `password_hash()` with bcrypt
- **Role-Based Access Control**: Admin/User separation at every endpoint
- **Data Isolation**: Users can only access their own family's data
- **API Authentication**: API key validation for IoT endpoints
- **Security Headers**: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection
- **Directory Protection**: .htaccess blocks access to config/includes/database

---

---

## 🚀 GitHub — Upload & Collaborate

### First-time push (your machine)

```bash
# 1. Open terminal and move to the project folder
cd "V:\Documents\VS CODE\DBMS AND BI"

# 2. Initialise Git (skip if already done)
git init

# 3. Stage everything  (config/database.php is excluded via .gitignore)
git add .
git commit -m "Initial commit: Smart Hybrid Microgrid Platform"

# 4. Create a repo on GitHub (github.com → New repository → name: microgrid-platform)
#    Then link it here:
git remote add origin https://github.com/<your-username>/microgrid-platform.git

# 5. Push
git branch -M main
git push -u origin main
```

### Add a collaborator (GitHub website)
Go to your repo → **Settings → Collaborators → Add people** → enter their GitHub username.

### Collaborator: clone & run

```bash
# Clone
git clone https://github.com/<your-username>/microgrid-platform.git

# Copy database config template and fill in local credentials
copy config\database.example.php config\database.php
# (then open config\database.php and adjust DB_HOST / DB_USER / DB_PASS if needed)
```

---

## ▶️ Run the Web App (XAMPP)

```
1. Install XAMPP  →  https://www.apachefriends.org/
2. Copy / clone the project into:
      C:\xampp\htdocs\microgrid-platform\
3. Open XAMPP Control Panel → Start Apache + MySQL
4. Open browser → http://localhost/microgrid-platform/install.php
      Click "Run Installation"  (creates DB, tables, seed users)
5. (Optional) Generate 30-day demo data:
      http://localhost/microgrid-platform/database/seed_demo_data.php
6. Open the app:
      http://localhost/microgrid-platform/
```

> **After every `git pull`** just refresh the browser — no extra steps needed unless the schema changed (re-run `install.php` if needed).

---

*Built as a DBMS & BI course project — Smart Hybrid Microgrid Monitoring, Governance & Optimization Platform*
