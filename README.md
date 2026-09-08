# MediQueue – Hospital Appointment & Virtual Queue Management System

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![Supabase Auth](https://img.shields.io/badge/Supabase-Google%20Auth-3ECF8E?logo=supabase&logoColor=white)](https://supabase.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.3-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com)
[![Vercel Deployed](https://img.shields.io/badge/Vercel-Live%20Demo-black?logo=vercel&logoColor=white)](https://medi-queue-eta-orcin.vercel.app)

**MediQueue** is a modern, full-featured hospital management and outpatient workflow system engineered to solve waiting room congestion, optimize clinic queues, and streamline doctor consultation schedules. 

It empowers patients to find specialist physicians, book real-time appointments without double-booking collisions, join virtual queues from their devices, and track their turn live with automated audio-visual chime notifications when called.

🌐 **Live Demo:** [https://medi-queue-eta-orcin.vercel.app](https://medi-queue-eta-orcin.vercel.app)

---

## 🌟 Key Features

### 👤 Patient Portal
- **Fast 1-Click Google Sign-In / Sign-Up:** Instant authentication powered by Supabase Auth alongside traditional email/password credentials.
- **Indian Phone Validation:** Dedicated `🇮🇳 +91` format with strict digit-only enforcement preventing alphabetic characters.
- **Find Doctors & Specialties:** Search and filter physicians across departments (Cardiology, Dermatology, Orthopedics, Pediatrics, General Medicine, Neurology) with live availability status (*Available*, *Busy*, *Unavailable*).
- **Appointment Booking Engine:** Interactive 30-minute time-slot picker with double-booking prevention and automated virtual queue ticketing.
- **Flagship Virtual Queue Tracker:** Live screen with auto-syncing countdown (`#1`, `3 Patients Ahead`, `~30 Mins Wait`). Synthesized audio chime and visual modal alert when the physician calls the patient's ticket.
- **Appointment Lifecycle Management:** View upcoming, in-queue, and completed appointments with 1-click cancellation and details inspection.
- **Companion & Visitor Passes:** Pre-register visitors and family members accompanying the patient with check-in/out logs and printable visitor badges.
- **Notification Drawer:** Real-time in-app alerts for bookings, confirmations, and queue turn announcements.
- **Patient Profile:** Manage medical demographics (Blood group, DOB, gender, emergency contacts) and security credentials.

### 🩺 Doctor Console
- **Clinical Dashboard:** Real-time KPI summaries for today's appointment load, waiting patients, current consultation, and completed visits.
- **1-Click "Call Next Patient":** Instantly advances the virtual queue, marks the patient as `CALLED`, triggers audio-visual chime alerts to the patient's screen, and updates waiting positions for everyone behind.
- **Consultation State Transitions:** Seamless single-click actions for `Start Consultation`, `Complete`, and `No Show`.
- **Availability & Clinic Hours:** Configure working shift hours, consultation slot durations (15–60 mins), and toggle status between *Available* (Green), *Busy* (Yellow), and *Unavailable* (Red).
- **Patient Clinical Records:** Search assigned patients, review chief complaints, medical history, and contact details.

### 🏥 Hospital Administration & Staff Portal
- **Executive Control Center:** High-level metrics (Total Patients, Today's Visits, Available Doctors, In-Queue Patients, Avg Wait Time).
- **Interactive Chart.js Visualizations:** Department load breakdown (bar chart) and appointment status distribution (doughnut chart).
- **Hospital-Wide Queue Monitor:** Real-time oversight of all clinical suites with administrative queue overrides.
- **Doctor & Department Management:** Add new specialist accounts, configure consultation fees, assign clinic suites, and maintain clinical departments.
- **Visitor Registry:** Front-desk companion check-in/out desk with customizable, printable hospital visitor badges.
- **Printable Audit Reports:** Filterable date-range analytics covering scheduled visits, turnout rate %, cancellations, no-shows, and doctor performance.

---

## 💻 Technology Stack

- **Frontend:** HTML5, CSS3, JavaScript (ES6+ Vanilla), Bootstrap 5.3.3, Font Awesome 6.5.1, Chart.js.
- **Backend:** Native PHP 8+ (No heavy frameworks required; runs smoothly on serverless and traditional servers).
- **Authentication:** Dual-mode authentication:
  - Native PHP sessions with bcrypt password hashing (`password_hash`).
  - Google OAuth powered by **Supabase Auth** with secure server-side token validation.
- **Database:** Dual-Engine architecture:
  - **MySQL 5.7+ / 8.0+ / MariaDB** for production with PDO prepared statements and foreign key constraints.
  - **SQLite 3 fallback** (`database/mediqueue.sqlite`) for instant zero-configuration local runs and serverless test environments.
- **Audio:** Web Audio API synthesized alert chimes when patients are called.
- **Deployment:** Vercel serverless (`vercel-php@0.9.0`), Apache / XAMPP / MAMP / LAMP.

---

## 🔑 Demo Login Credentials

The database is pre-seeded with verified test accounts across all three user roles:

| Role | Email Address | Password | Description |
| :--- | :--- | :--- | :--- |
| **Admin / Staff** | `admin@mediqueue.com` | `admin123` | System Administrator & Hospital Front-Desk |
| **Doctor** | `doctor@mediqueue.com` | `doctor123` | Dr. Sarah Jenkins (Cardiology, Suite 201-A) |
| **Patient** | `patient@mediqueue.com` | `patient123` | John Doe (Registered Patient with active queue) |

> 💡 **Tip:** On the [Sign In page](login.php), you can click the **Quick 1-Click Demo Login** buttons (`Admin`, `Doctor`, `Patient`) to automatically fill in the credentials instantly.

---

## ⚡ Google Authentication with Supabase

MediQueue supports native Google OAuth login powered by **Supabase Auth**:

### Step 1: Configure Supabase
1. Create a free project at [supabase.com](https://supabase.com).
2. Under **Authentication &rarr; Providers &rarr; Google**, enable Google and input your Google Cloud OAuth Client ID & Secret.
3. In **Authentication &rarr; URL Configuration &rarr; Redirect URLs**, add your callback URLs:
   - For local development:
     ```
     http://localhost:8000/auth/callback.php
     ```
     *(or `http://localhost/MediQueue/auth/callback.php` if using XAMPP)*
   - For Vercel production:
     ```
     https://medi-queue-eta-orcin.vercel.app/auth/callback.php
     ```

### Step 2: Set Environment Variables
Copy `.env.example` to `.env` (or update `.env`):
```env
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_ANON_KEY=your-anon-key
```

Patients and staff can now click **Continue with Google** to sign in or register with 1 click! New accounts are automatically provisioned in MediQueue's local database.

---

## 🚀 Running Locally

### Option 1: PHP Built-in Server (Fastest)

1. Clone the repository:
   ```bash
   git clone https://github.com/SanjanaJ1611/MediQueue.git
   cd MediQueue
   ```
2. Start the built-in development server:
   ```bash
   php -S localhost:8000
   ```
3. Open **[http://localhost:8000](http://localhost:8000)** in your browser!

*(The built-in pre-seeded SQLite database works right out of the box without needing MySQL installation).*

---

### Option 2: Using XAMPP / MAMP / Apache

1. Copy or symlink the project folder into your web server's `htdocs` directory:
   ```
   C:\xampp\htdocs\MediQueue   (Windows)
   /Applications/XAMPP/xamppfiles/htdocs/MediQueue   (macOS)
   ```
2. Open phpMyAdmin (`http://localhost/phpmyadmin`) and import:
   ```
   database/mediqueue.sql
   ```
3. Open your browser and navigate to:
   ```
   http://localhost/MediQueue
   ```

---

## 📂 Project Structure

```
MediQueue/
│
├── index.php                  # Public hospital landing page with featured specialists
├── login.php                  # Sign In with role selection, Google OAuth & 1-click demo switcher
├── register.php               # Patient registration with Indian format (+91) & Google 1-click
├── forgot_password.php        # Password recovery UI
├── logout.php                 # Secure session destruction
├── vercel.json                # Vercel serverless deployment configuration
├── .env.example               # Environment variables template
├── README.md                  # Complete documentation & setup instructions
│
├── auth/
│   └── callback.php           # Supabase Google OAuth callback landing page
│
├── config/
│   ├── database.php           # PDO database connection, dual-engine MySQL/SQLite fallback & BASE_URL
│   └── supabase.php           # Supabase environment variables & credential helpers
│
├── includes/
│   ├── auth.php               # Role-based access guards (require_role), sessions, CSRF
│   ├── functions.php          # Badge helpers, notifications, wait calculations, sanitization
│   ├── header.php             # HTML head, Bootstrap 5 CDN, FontAwesome, custom CSS
│   ├── navbar.php             # Top navigation, user dropdown, live notification drawer
│   ├── sidebar.php            # Dynamic role-based responsive navigation sidebar
│   └── footer.php             # Layout close tags, Bootstrap bundle, Chart.js, scripts
│
├── patient/
│   ├── dashboard.php          # Patient hub with live queue card & today's appointment
│   ├── doctors.php            # Find doctors with search, filters, and availability badges
│   ├── book_appointment.php   # Booking wizard with real-time slot generation & locking
│   ├── appointments.php       # Patient's appointment history, details modal, cancel visit
│   ├── queue.php              # Real-time virtual queue tracker with audio chime alert
│   ├── visitors.php           # Patient companion & visitor pass management
│   ├── notifications.php      # Notification center with mark-all-as-read
│   └── profile.php            # Demographics, blood group, emergency contact & password
│
├── doctor/
│   ├── dashboard.php          # Doctor console: Call Next patient, active consult spotlight
│   ├── queue.php              # Dedicated full-screen virtual queue management table
│   ├── appointments.php       # Schedule overview, status updater, patient medical records
│   ├── patients.php           # Directory of assigned patients and consultation history
│   ├── availability.php       # Working hours configuration & Available/Busy/Unavailable toggle
│   └── profile.php            # Clinical credentials, room number, consultation fee
│
├── admin/
│   ├── dashboard.php          # Executive KPIs, Chart.js analytics, hospital-wide monitor
│   ├── patients.php           # Patient master registry, add/edit/delete records
│   ├── doctors.php            # Specialist onboarding, fee setup, department assignments
│   ├── departments.php        # Clinical departments CRUD with doctor counts
│   ├── appointments.php       # Hospital-wide appointment oversight and status overrides
│   ├── queues.php             # Master virtual queue control board
│   ├── visitors.php           # Front-desk companion check-in/out with printable passes
│   ├── reports.php            # Printable clinical audit reports with date range filters
│   └── settings.php           # Hospital profile, queue parameters, and admin security
│
├── ajax/
│   ├── supabase_auth.php      # Supabase OAuth token verification & automatic user provisioning
│   ├── get_slots.php          # Computes free time slots and prevents double bookings
│   ├── queue_status.php       # Polling endpoint for live queue position and wait times
│   ├── call_next.php          # Advances queue, calls next patient, dispatches notifications
│   ├── update_queue.php       # Transitions queue state (In Consultation, Complete, No Show)
│   ├── update_availability.php# Toggles doctor availability badge (Available, Busy, Unavailable)
│   └── notifications.php      # Marks notifications as read
│
├── assets/
│   ├── css/
│   │   └── style.css          # Healthcare design system, rounded cards, pulse badges
│   └── js/
│       └── script.js          # Real-time polling handler, slot loader, Web Audio chime
│
└── database/
    ├── mediqueue.sql          # Complete MySQL database schema and seed dataset
    └── mediqueue.sqlite       # Pre-seeded portable SQLite database
```

---

## 🚀 Deploying to Vercel

MediQueue is pre-configured with `vercel.json` and a serverless entrypoint in `api/index.php` using `vercel-php@0.9.0`.

1. Fork or push the repository to GitHub.
2. In your [Vercel Dashboard](https://vercel.com/dashboard), click **"Add New..."** &rarr; **"Project"** &rarr; Select `MediQueue`.
3. Keep default settings (Framework: **Other**, Root Directory: `./`).
4. In **Settings &rarr; Environment Variables**, add your cloud MySQL connection details (optional, for persistent production data):
   - `DB_HOST`: Hostname (e.g., TiDB Cloud, Aiven, Railway)
   - `DB_PORT`: `3306`
   - `DB_NAME`: `mediqueue`
   - `DB_USER`: Database username
   - `DB_PASS`: Database password
   - `DB_SSL`: `true`
5. Deploy!

---

&copy; MediQueue – Professional Hospital Appointment & Virtual Queue Management System.
