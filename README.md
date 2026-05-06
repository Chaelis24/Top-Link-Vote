# Top Link-Vote

A comprehensive web-based election management and real-time voting system. This platform streamlines the school election process—from the Registrar's master list import to automated tallying and PDF reporting—ensuring a secure, transparent, and efficient voting experience.

## 🚀 Features

### Student & Candidate Functions

- **🔐 Account Security** – Secure Login/Logout, Email Verification, and Password Reset.
- **👤 Profile Management** – Edit student profiles and manage personal settings.
- **📢 Candidate Showcasing** – Candidates can manage their own profiles, platforms, and agendas.
- **🗳️ Secure Voting** – Cast votes with system checks for eligibility, voting windows, and "one-vote-per-student" enforcement.
- **📊 Live Dashboard** – Access a personalized dashboard to view candidate profiles and election status.

### Admin & Registrar Functions

- **📥 Master List Integration** – Import student data directly from Registrar CSV files.
- **⚙️ Election Lifecycle Management** – Create and manage election cycles, including filing, campaigning, and voting periods.
- **👥 Candidate & Position Control** – Approve or reject candidate applications and define organizational positions.
- **🛠️ System Governance** – Global system settings management, including the ability to enable/disable voting.
- **📑 Automated Reporting** – Real-time vote tallying with the ability to generate and download official PDF election reports.
- **📜 Activity Logging** – Comprehensive audit trails tracking all user actions for transparency.

## 🧱 Tech Stack

- **Frontend:** Tailwind CSS, Bootstrap 5, HTML5, CSS3, JavaScript (Vite)
- **Backend:** PHP 8.2+, Laravel 13 (Eloquent ORM)
- **Real-time:** Laravel Reverb (WebSockets)
- **Database:** MySQL / MariaDB (Complex Relational Schema)
- **Security:** CSRF Protection, Middleware Authorization, and Bcrypt Password Hashing

## 🗂️ Project Structure

```text
📁 Top-Link-Vote
├── app/                # Models (User, Student, Candidate, etc.) & Controllers
├── database/           # Migrations (Schema for Users, Roles, Votes, etc)
├── resources/          # Views (Blade templates for Admin & Student portals)
├── routes/             # Web routes protected by Role Middleware
├── storage/            # Local storage for Photos and Generated PDF Reports
└── public/             # Compiled assets and entry point
```

⚙️ Installation Steps

1. Clone & Install

git clone [https://github.com/Chaelis24/Top-Link-Vote.git](https://github.com/Chaelis24/Top-Link-Vote.git)
cd Top-Link-Vote
composer install
npm install

2. Environment Configuration

cp .env.example .env
php artisan key:generate

Update your .env with your database credentials (DB_DATABASE=top_link_vote).

3. Database & Assets

php artisan migrate --seed
php artisan storage:link
npm run build

4. Run the System

# Terminal 1: App Server

php artisan serve

# Terminal 2: Real-time Server

php artisan reverb:start

🔑 System Access
Admin Access: Login via the main portal (requires Admin role assigned in USER_ROLES).

Student Access: Registration is validated against the imported Registrar Master List.
