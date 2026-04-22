# Bloodline — Online Blood Bank Management System

> A full-stack web application for managing blood bank inventory, donor registrations, and hospital blood requests — built as a Web Programming & RDBMS lab mini project.

---

## Overview

Bloodline is an online blood bank management platform that connects donors, blood banks, and hospitals. It provides real-time inventory tracking, robust donor registration, urgent blood requests, and a modern session/cookie layer that keeps donor preferences, remembered devices, and form state secure while maintaining the premium UI.

---

## Features

- **Live Inventory Dashboard** — Visual bubble-map of all 8 blood types with real-time unit counts and critical stock alerts
- **Donor Registration** — Full medical history form with Aadhaar verification, health questionnaire, allergy screening, and password-protected accounts
- **Blood Request System** — Hospitals can submit urgent/scheduled blood requests by type, with urgency levels and patient details
- **Transaction-Safe Request Processing** — Request fulfillment, revert, and delete actions keep inventory synchronized using commit/rollback logic
- **Relational Database** — Normalised MySQL schema covering donors, blood banks, hospitals, donations, and requests — based on a full EER diagram
- **Audit + Access Control** — Transaction events are captured in `Request_Transaction_Log`, and operator/auditor privileges are defined in `sql/transaction_security.sql`
- **Dark/Light Mode** — System-wide theme toggle across all pages
- **Custom Cursor + GSAP Animations** — Smooth entrance animations and floating bubble effects
- **Secure Sessions & Cookies** — Shared auth helper centralizes session boot, CSRF, and persistent remember-me cookies
- **Site Preference Utilities** — `assets/site-prefs.js` keeps theme and last-page cookies in sync across every page
- **CSRF Token Platform** — Login, registration, contact, and the AJAX blood request form all carry tokens managed by `auth.php` and `csrf_token.php`

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, Vanilla JS |
| Animations | GSAP 3 + ScrollTrigger |
| Typography | Cormorant Garamond, DM Sans (Google Fonts) |
| Backend | PHP 8.2 |
| Database | MySQL 8 via phpMyAdmin |
| Server | Apache (XAMPP) |

---

## Project Structure

```
bloodlines/
├── index.html               # Landing page
├── registration.php         # Donor registration form
├── make-request.html        # Blood request page
├── inventory.html           # Live inventory dashboard
├── db.php                   # Database connection
├── register.php             # Handles donor form POST → MySQL
├── submit_request.php       # Handles blood request POST → MySQL
├── get_inventory.php        # Returns live inventory as JSON
├── auth.php                 # Shared session, CSRF, remember-me helper
├── csrf_token.php           # Lightweight async CSRF endpoint
├── assets/site-prefs.js     # Theme + last-page cookie utilities
├── gsap.min.js              # GSAP core
├── ScrollTrigger.min.js     # GSAP ScrollTrigger plugin
└── assets/
    ├── logo.png
    ├── logo-light.png
    ├── logo-dark.png
    ├── logow.png
    ├── hero-img.png
    ├── feature1.jpg
    ├── feature2.jpg
    ├── img6.jpg – img8.jpg
    ├── risha2.png
    └── sk.png
```

---

## Database Schema

The schema follows the final ER diagram in a **hybrid app schema** form: core academic entities stay aligned with the ERD, while operational tables needed by the running site remain in place.

It currently contains **20 tables + 1 view**:

**Core entities:** `Donor`, `Blood_Bank`, `Hospital`, `Blood_Donations`, `Blood_Request`

**ISA subtypes:** `Regular_Donor`, `First_Time_Donor`, `Government_BloodBank`, `Private_BloodBank`

**Multi-valued attributes:** `Donor_Contact`, `BloodBank_Address`, `BloodBank_Contact`, `Hospital_Address`, `Hospital_Contact`

**Relationship tables:** `Donates`, `Registers_At`

**Operational extensions:** `Blood_Inventory`, `Request_Transaction_Log`, `Donor_Remember_Token`, `Contact_Messages`

**View:** `vw_inventory` — aggregates total units per blood group

`Supplies` has been removed from the final schema because the live request/inventory workflow now uses `Blood_Request` + `Blood_Inventory` transaction logic instead of a donation-to-request bridge table.

---

## Setup & Installation

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL)
- A browser

### Steps

**1. Clone the repository**
```bash
cd C:\xampp\htdocs
git clone https://github.com/risha2211/Online-Blood-Bank-Management-System bloodlines
```

**2. Start XAMPP**

Open XAMPP Control Panel → Start **Apache** and **MySQL**

**3. Create the database**

- Go to `http://localhost/phpmyadmin`
- Create a new database named `bloodline_db` with collation `utf8mb4_unicode_ci`
- Select the database → Import tab → upload `bloodline.sql` → Go
- If you are aligning an older copy of the schema to the final ERD, import `sql/final_erd_hybrid_alignment.sql`
  or simply load the app once; `db.php` applies the same compatibility pass automatically.

**4. Open the app**

```
http://localhost/bloodlines/index.html
```

---

## Pages

| Page | URL | Description |
|---|---|---|
| Home | `/index.html` | Landing page with features, stats, and team section |
| Register | `/registration.php` | Donor sign-up with full medical history |
| Request Blood | `/make-request.html` | Select blood type and submit a request |
| Manage Requests | `/manage_requests.php` | Transaction-aware request console with filter, update, delete, and inventory-safe fulfillment |
| Inventory | `/inventory.html` | Live bubble view of current blood stock |
| Experiment 6 Demo | `/exp6_routines_demo.php` | Stored procedure + function + cursor demo |

---

## API Endpoints

| File | Method | Description |
|---|---|---|
| `register.php` | POST | Validates and inserts new donor into DB |
| `submit_request.php` | POST (JSON) | Creates a new blood request record |
| `update_request.php` | POST | Transaction-aware request update that keeps `Blood_Inventory` in sync |
| `delete_request.php` | POST | Transaction-aware request delete that restores stock before removing fulfilled requests |
| `get_inventory.php` | GET | Returns JSON of current blood unit counts per type |

---

## TCL / DCL Integration

- `request_transaction.php` contains the transaction workflow for Bloodline request processing.
- `update_request.php` now uses a write transaction, a savepoint, row locks, and commit/rollback logic before changing inventory-backed requests.
- `delete_request.php` restores inventory inside the same transaction before deleting a fulfilled request.
- `Request_Transaction_Log` stores an audit trail for each update/delete event.
- `sql/transaction_security.sql` contains MySQL TCL and DCL commands for this project, including `START TRANSACTION`, `SAVEPOINT`, `COMMIT`, `CREATE ROLE`, `GRANT`, and `REVOKE`.

---

## Contributors

| Name | Roll No. |
|---|---|
| Risha Kanthe | 16010124129 |
| Soham Kanase | 16010124128 |

---

## Course

**Web Programming & RDBMS Laboratory**
Mini Project — Semester IV
K.J. Somaiya School of Engineering

---

## License

This project was built for academic purposes as part of a lab course.
