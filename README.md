# SmartFix AI - Campus Complaint Management System

A modern, AI-powered campus complaint & maintenance ticketing system built with PHP, MySQL, and a premium Glassmorphism UI.

---

## Features

- **Role-based access**: Student, Staff, Admin portals with distinct dashboards
- **AI Triage**: Google Gemini API integration for automatic complaint categorization, priority assignment, and suggested solutions (with offline keyword-based fallback)
- **Real-time Notifications**: In-app notification system with unread counts
- **SLA Tracking**: Priority-based SLA hours; automated overdue alerts via cron
- **Reports & Analytics**: Visual charts (Chart.js), staff performance metrics, CSV export
- **Security**: CSRF protection, rate limiting, PDO prepared statements, CSP headers, session hardening
- **Dark Mode**: Toggle between light and dark themes with localStorage persistence

---

## Project Structure

```
project 2.0/
├── admin/              # Admin portal pages
│   ├── dashboard.php   # Main admin workboard with ticket assignment
│   ├── users.php       # User management (CRUD)
│   ├── categories.php  # Categories & SLA management
│   ├── settings.php    # System settings
│   └── reports.php     # Analytics & CSV export
├── staff/              # Staff portal pages
│   ├── dashboard.php   # Staff ticket queue
│   └── view-complaint.php  # Ticket detail & status update
├── student/            # Student portal pages
│   ├── dashboard.php   # Student ticket list
│   ├── create-complaint.php  # File new complaint (AI-assisted)
│   └── view-complaint.php   # Track complaint progress
├── api/
│   └── notifications.php    # JSON API for notifications
├── cron/
│   └── notify_overdue.php   # SLA breach notifier (CLI cron job)
├── includes/           # Shared PHP classes & templates
│   ├── Database.php    # PDO singleton
│   ├── Session.php     # Session & RBAC management
│   ├── Security.php    # CSRF, rate limiting, sanitization
│   ├── AIHelper.php    # Gemini API wrapper + local fallback
│   ├── NotificationHelper.php  # In-app notification CRUD
│   ├── header.php      # Page header template
│   ├── sidebar.php     # Navigation sidebar
│   └── footer.php      # Page footer template
├── config/
│   ├── config.php      # All constants (DB, API keys, SMTP)
│   └── bootstrap.php   # Auto-loader and session start
├── assets/
│   ├── css/main.css    # Full design system (glassmorphism)
│   └── js/main.js      # Theme toggle, AJAX helpers, toast notifications
├── database/
│   └── schema.sql      # Full DB schema + seed data
├── login.php           # Login page with rate limiting & CSRF
├── register.php        # Student self-registration
├── logout.php          # Session destroy & redirect
├── forgot-password.php # Password reset request
└── reset-password.php  # Password reset handler
```

---

## Setup Instructions

### Prerequisites
- PHP 7.4+ with PDO, PDO_MySQL, cURL, openssl extensions
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server (or PHP built-in server for dev)
- Composer (for PHPMailer if using SMTP)

### 1. Clone/Copy Project
```bash
cp -r "project 2.0" /var/www/html/smartfix-ai
```

### 2. Database Setup
```bash
mysql -u root -p
CREATE DATABASE smartfix_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
mysql -u root -p smartfix_db < database/schema.sql
```

### 3. Configure
Edit `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'smartfix_db');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('SITE_URL', 'http://localhost/smartfix-ai');
define('GEMINI_API_KEY', 'YOUR_GEMINI_KEY');  // Optional: AI features
define('SMTP_USER', 'mailtrap_user');          // Optional: Email
define('SMTP_PASS', 'mailtrap_pass');
```

### 4. Run Dev Server
```bash
cd "project 2.0"
php -S localhost:8000
```
Visit `http://localhost:8000/login.php`

### 5. Default Credentials
| Role    | Email                        | Password    |
|---------|------------------------------|-------------|
| Admin   | admin@smartfixai.edu         | Admin@123   |
| Staff   | it_staff@smartfixai.edu      | Staff@123   |
| Student | student@smartfixai.edu       | Student@123 |

### 6. Cron Setup (Linux/Mac)
```bash
# Add to crontab (runs daily at 8am)
0 8 * * * /usr/bin/php /var/www/html/smartfix-ai/cron/notify_overdue.php >> /var/log/smartfix_cron.log 2>&1
```

---

## Security Notes

- All DB queries use PDO prepared statements
- CSRF tokens on all POST forms (2-hour expiry)
- Login rate limiting: 5 attempts per 15 minutes per IP
- Passwords hashed with `PASSWORD_BCRYPT`
- Session regeneration on login
- Security headers: X-Frame-Options, X-Content-Type-Options, Referrer-Policy

---

## AI Integration

The system uses Google Gemini 1.5 Flash for:
- Auto-categorizing complaints
- Predicting priority level
- Suggesting resolution steps
- Estimating confidence score

Set `GEMINI_API_KEY` in `config.php` to enable live AI. Without an API key, the system uses a keyword-based offline analyzer automatically.

---

## License
MIT License - SmartFix AI (2026)
