# MediQueue – Hospital Appointment & Virtual Queue Management System

**MediQueue** is a modern, real-world hospital management web application engineered to solve waiting room congestion, optimize outpatient workflows, and streamline doctor consultation schedules. It empowers patients to browse specialist doctors, book real-time appointments without double-booking collisions, join virtual queues from their devices, and track their live queue positions and estimated wait times with automated chime notifications when called.

---

## 🌟 Key Features

### 👤 Patient Portal
- **Secure Registration & Login:** Clean registration with validation, password hashing (`password_hash` BCRYPT), and session guards.
- **Find Doctors & Specialties:** Search and filter physicians across departments (Cardiology, Dermatology, Orthopedics, Pediatrics, General Medicine, Neurology) with real-time availability badges (*Available*, *Busy*, *Unavailable*).
- **Appointment Booking Engine:** Interactive 30-minute time-slot picker with double-booking collision prevention and automated virtual queue ticketing.
- **Flagship Virtual Queue Tracker:** Live screen with auto-syncing countdown (`#1`, `3 Patients Ahead`, `~30 Mins Wait`). Synthesized audio chime and visual modal alert when the physician calls the patient's ticket.
- **Appointment Lifecycle Management:** View upcoming, in-queue, and completed appointments with 1-click cancellation and details inspection.
- **Companion & Visitor Passes:** Pre-register visitors and family members accompanying the patient with check-in/out logs and printable visitor badges.
- **Notification Drawer:** Real-time in-app alerts for bookings, confirmations, and queue turn announcements.
- **Patient Profile:** Manage medical demographics (Blood group, DOB, gender, emergency contacts) and security credentials.

### 🩺 Doctor Console
- **Clinical Dashboard:** Real-time KPI summaries for today's appointment load, waiting patients, current consultation, and completed visits.
- **1-Click "Call Next Patient":** Instantly advances the virtual queue, marks the patient as `CALLED`, triggers audio-visual chime alerts to the patient's screen, and updates waiting positions for everyone behind.
- **Consultation State Transitions:** Seamless single-click actions for `Start Consultation`, `Complete`, and `No Show`.
- **Availability & Clinic Hours:** Configure working shift hours (start/end times), consultation slot durations (15–60 mins), and toggle real-time status between *Available* (Green), *Busy* (Yellow), and *Unavailable* (Red).
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
- **Backend:** Native PHP 8+ (No heavy frameworks required; works seamlessly out of the box).
- **Database:** MySQL 5.7+ / 8.0+ / MariaDB with PDO prepared statements and foreign key constraints.
- **Architecture:** Role-Based Access Control (RBAC), RESTful AJAX endpoints, and Web Audio API synthesized alert chimes.
- **Server:** Apache / XAMPP / WampServer / LAMP.

---

## 🔑 Demo Login Credentials

The database script is pre-seeded with verified test accounts across all three user roles:

| Role | Email Address | Password | Description |
| :--- | :--- | :--- | :--- |
| **Admin / Staff** | `admin@mediqueue.com` | `admin123` | System Administrator & Hospital Front-Desk |
| **Doctor** | `doctor@mediqueue.com` | `doctor123` | Dr. Sarah Jenkins (Cardiology, Suite 201-A) |
| **Patient** | `patient@mediqueue.com` | `patient123` | John Doe (Registered Patient with active queue) |

> 💡 **Tip:** On the [Sign In page](login.php), you can click the **Quick 1-Click Demo Login** buttons (`Admin`, `Doctor`, `Patient`) to automatically fill in the credentials instantly.

---

## 🚀 Quick Setup & Installation on XAMPP

### Step 1: Install XAMPP
Ensure you have **XAMPP** installed with **Apache** and **MySQL** services running.

### Step 2: Place Code in `htdocs`
Copy the entire `MediQueue` project folder into your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\MediQueue
```

### Step 3: Import Database in phpMyAdmin
1. Open your browser and navigate to: `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar to create a database, or click on the **Import** tab directly.
3. Choose the file located at:
   ```
   C:\xampp\htdocs\MediQueue\database\mediqueue.sql
   ```
4. Click **Import** (or **Go**). The script will automatically create the database `mediqueue`, all 9 tables, indexes, and comprehensive sample data.

### Step 4: Launch MediQueue
Open your browser and navigate to:
```
http://localhost/MediQueue
```
(or `http://localhost/mediqueue`)

---

## 📂 Project Structure

```
MediQueue/
│
├── index.php                  # Public hospital landing page with featured specialists
├── login.php                  # Sign In with role selection and 1-click demo switcher
├── register.php               # Patient registration with validations & password hashing
├── forgot_password.php        # Password recovery UI
├── logout.php                 # Secure session destruction
├── README.md                  # Complete documentation & setup instructions
│
├── config/
│   └── database.php           # PDO database connection & dynamic BASE_URL resolver
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
    └── mediqueue.sql          # Complete MySQL database schema and seed dataset
```

---

## 🧪 Verification & Functional Testing Checklist

- [x] **Registration:** New patient registration inserts into `users` and `patients`, hashes password, creates welcome notification, and logs user in.
- [x] **Authentication & Role Guards:** Unauthorized URL access to `/doctor/` or `/admin/` redirects to appropriate dashboards or login with flash alert.
- [x] **Doctor Availability:** Changing availability status between *Available*, *Busy*, and *Unavailable* reflects instantly in patient directory and disables booking when unavailable.
- [x] **Appointment Booking:**
  - Selecting a doctor and date dynamically loads free 30-minute slots.
  - Slots already booked are grayed out and cannot be selected.
  - Prevents double booking server-side with database transaction locking.
- [x] **Virtual Queue Flow:**
  - Joining queue generates sequential tickets (`CAR-101`, `GEN-201`, etc.).
  - Doctor clicks `CALL NEXT PATIENT` &rarr; patient's queue tracker updates to **CALLED** via AJAX polling without page reload and plays an audible synthesized chime.
  - Doctor clicks `Start Consultation` &rarr; status updates to **In Consultation**.
  - Doctor clicks `Complete` &rarr; status updates to **Completed** and advances waiting queue.
- [x] **Visitor Management:** Add visitor passes, check-in, check-out, and print formatted hospital visitor badges.
- [x] **Reports & Analytics:** Filter appointments by date range, doctor, or status, view doctor turnout rates, and print formatted hospital audit reports.
- [x] **Mobile Responsiveness:** Collapsible mobile sidebar with backdrop, horizontally scrollable data tables, and fluid card grids.

---

## 🚀 Deploying to Vercel

MediQueue is pre-configured with `vercel.json` and a serverless entrypoint in `api/index.php` using the community `vercel-php@0.9.0` runtime.

### Step 1: Push Code to GitHub
Your repository is already linked:
```bash
git push -u origin main
```

### Step 2: Import Project in Vercel
1. Go to [Vercel Dashboard](https://vercel.com/dashboard) and click **"Add New..."** &rarr; **"Project"**.
2. Select your GitHub repository: `SanjanaJ1611/MediQueue`.
3. Keep the default settings (Framework Preset: **Other**, Root Directory: `./`).
4. Click **Deploy**.

### Step 3: Configure Database (For Production Persistence)
Because Vercel serverless functions have an ephemeral filesystem, we recommend connecting a free cloud MySQL database (e.g. from **[TiDB Cloud](https://tidbcloud.com)**, **[Aiven](https://aiven.io)**, or **[Railway](https://railway.app)**):

1. Create a free MySQL database on your chosen provider.
2. Import the database schema from `database/mediqueue.sql`.
3. In your Vercel Project Dashboard, navigate to **Settings** &rarr; **Environment Variables** and add:
   - `DB_HOST`: Your cloud database hostname
   - `DB_PORT`: `3306` (or provider port)
   - `DB_NAME`: Your database name
   - `DB_USER`: Your database username
   - `DB_PASS`: Your database password
   - `DB_SSL`: `true` (if SSL connection is required by provider)
4. Redeploy project — MediQueue will automatically connect directly to your cloud MySQL!

*(Note: If no database environment variables are set, MediQueue automatically boots with the built-in SQLite database in `/tmp` for instant testing).*

---

## 🔮 Future Enhancements

1. **SMS / WhatsApp Gateway Integration:** Twilio / WhatsApp Business API integration to dispatch queue SMS alerts directly to patients' mobile phones.
2. **Telehealth / Video Consultations:** WebRTC integration for remote consultations directly within the doctor console.
3. **Multi-Hospital / Branch Support:** Organization hierarchy allowing multi-facility hospital chains to manage different locations on a single instance.
4. **Automated Prescription & Billing Generator:** Direct generation of PDF medical prescriptions and payment gateway integration (Stripe / Razorpay).

---

&copy; MediQueue – Professional Hospital Appointment & Virtual Queue Management System.

