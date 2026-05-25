## Top Link-Vote

A comprehensive web-based election management and real-time voting system. This platform streamlines the school election process—from the Registrar's master list import to automated tallying and PDF reporting—ensuring a secure, transparent, and efficient voting experience.

## 🚀 Features

### Student & Candidate Functions

- **🔐 Account Security** – Features secure login/logout, multi-stage account verification, and email-based password resets.
- **👤 Profile Management** – Allows students to update their profiles and securely change their passwords.
- **📢 Candidate Showcasing** – Candidates can manage their own profiles, platforms, and agendas.
- **🗳️ Secure Voting** – Enforces "one-vote-per-student" policies with real-time checks for student eligibility and active voting windows, followed by an automated email voting receipt.
- **📊 Live Dashboard** – Provides a personalized view of real-time election statistics and ongoing candidate standings.

### Admin & Registrar Functions

- **📥 Master List Integration** – Streamlines student data management via direct CSV file imports from the Registrar.
- **⚙️ Election Lifecycle Management** – Controls the entire election timeline, from application filing and campaigning to the final voting period.
- **👥 Candidate & Position Control** – Allows admins to import candidate profiles, define organizational positions, and approve or reject applications.
- **🛠️ System Governance** – Manages global configurations, including system maintenance toggles and visibility controls (Allow Voting, Show Candidate Profiles, Show Election Results, and Maintenance Mode).
- **📑 Automated Reporting** – Tracks and tallies votes in real time, with automated PDF generation and downloads unlocked once the official results date is reached.
- **📜 Activity Logging** – Maintains a comprehensive audit trail tracking all user and administrative actions to ensure total transparency.

## 🧱 Tech Stack

- **Frontend:** Tailwind CSS, Bootstrap 5, HTML5, CSS3, JavaScript (Vite)
- **Backend:** PHP 8.2+, Laravel 13 (Eloquent ORM)
- **Real-time:** Laravel Reverb (WebSockets)
- **Database:** MySQL / MariaDB (Complex Relational Schema)
- **Security:** CSRF Protection, Middleware Authorization, and Bcrypt Password Hashing

## 🗂️ Project Structure

```text
📁 Top-Link-Vote
├── app/
│   ├── Events/         # Real-time WebSocket broadcast events (Laravel Reverb)
│   ├── Http/           # App controllers, requests, and security middlewares
│   ├── Jobs/           # Asynchronous queued tasks (e.g., imports student)
│   ├── Livewire/       # Reactive UI components (e.g., forms)
│   ├── Mail/           # Transactional mailables (e.g., OTPs verification)
│   ├── Models/         # Database entities & relationships (User, Student, Candidate, Vote)
│   ├── Notifications/  # Multi-channel system alerts (Web & Email event triggers)
│   ├── Providers/      # Core application bootstrapping and system service bindings
│   └── View/           # Custom blade dynamic UI components and layouts
├── database/           # Schema migrations and seeders for master data setup
├── resources/
│   ├── css/            # Frontend styling configurations (Tailwind CSS / Bootstrap 5)
│   ├── js/             # Client-side JavaScript entry points and WebSocket listeners
│   └── views/          # Application UI templates
│       ├── components/ # Reusable Blade layout elements (buttons, inputs, cards)
│       ├── emails/     # Raw HTML/Blade layouts for email notifications and receipts
│       ├── layouts/    # Master structure files (e.g., Admin and Student base templates)
│       ├── livewire/   # Dynamic Livewire frontend UI components
│       │   ├── admin/  # Administrative interfaces (election controls, candidate manager)
│       │   ├── pages/  # Global application pages (login views, public landing)
│       │   └── students/ # Student dashboard widgets and secure voting screens
│       └── pdf/        # Specialized layout blueprints for downloadable election reports
├── routes/             # Web and API routing protected by Role Middleware
├── storage/            # System storage for Candidate Photos and generated PDF Reports
└── public/             # Publicly accessible compiled assets and application entry point
```

⚙️ Installation Steps

### A. Clone & Install

Open your terminal and run the following commands:

```cmd
git clone https://github.com/Chaelis24/Top-Link-Vote.git
cd Top-Link-Vote
```

### B. Install Dependencies

Run the all-in-one setup automation script to handle composer packages, npm packages, env creation, and key generation:

```cmd
composer run setup
```

### C. Environment Configuration

Open your newly generated `.env` file and configure your services:

#### Database Setup (Example on MySQL)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=top_link_vote
DB_USERNAME=root
DB_PASSWORD=
```

#### Queue and Broadcasting (Required for Horizon & Reverb)

```
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb
```

#### Redis Configuration (Make sure your Redis Server is running)

```
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

#### Laravel Reverb

```
REVERB_APP_ID=your-reverb-app-id
REVERB_APP_KEY=your-reverb-app-key
REVERB_APP_SECRET=your-reverb-app-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

#### Vite / Frontend Variables

```
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### D. Database & Assets

Seed the database with initial administrative data and link your storage directory:

```cmd
php artisan migrate --seed
php artisan storage:link
```

### E. Run the System

### Terminal 1: App Server

```cmd
composer run dev
```

### Terminal 2: Real-time Server

```cmd
php artisan reverb:start
```

### Terminal 3: Asynchronous Queue Service

```cmd
php artisan horizon
```

### 📊 Portals & Evaluation Access

Use the following default accounts and test environment configurations to access and evaluate the platform after initial migrations and seeds are executed.

#### 1. 🛡️ Admin Portal (Elections Management)

The centralized administrative hub where system managers and registrars control student rosters, moderate candidate submissions, and initialize lifecycle timelines.

**URL: http://localhost:8000/admin-login**

**Email Address: admin@gmail.com**
**Password: admin**

#### 2. 🗳️ Student Portal (Voting & Live Dashboard)

Student login identifiers are validated directly against rows imported from the Registrar's database ledger (students.csv). Use the target ranges below for sandbox execution:

**URL: http://localhost:8000/ (Main Login Portal)**

**Student ID: 23-0001 through 23-0099**
**Password: P@ssword**

#### 3. 🏎️ Laravel Horizon Monitoring Dashboard

Review active transactional operations, asynchronous jobs, mail processing performance metrics, and system worker distributions.

**URL: http://localhost:8000/horizon (Accessible in local development mode)**
