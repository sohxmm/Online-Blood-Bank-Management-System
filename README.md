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
- **Relational Database** — Normalised MySQL schema covering donors, blood banks, hospitals, donations, and requests — based on a full EER diagram
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

The schema is derived from the EER diagram and relational mapping submitted as part of the RDBMS assignment. It includes **17 tables**:

**Core entities:** `Donor`, `Blood_Bank`, `Hospital`, `Blood_Donations`, `Blood_Request`

**ISA subtypes:** `Regular_Donor`, `First_Time_Donor`, `Government_BloodBank`, `Private_BloodBank`

**Multi-valued attributes:** `Donor_Contact`, `BloodBank_Address`, `BloodBank_Contact`, `Hospital_Address`, `Hospital_Contact`

**Relationship tables:** `Donates`, `Registers_At`, `Supplies`

**View:** `vw_inventory` — aggregates total units per blood group

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
| Manage Requests | `/manage_requests.php` | Tabular blood-request CRUD demo with filter, update, and delete |
| Inventory | `/inventory.html` | Live bubble view of current blood stock |

---

## API Endpoints

| File | Method | Description |
|---|---|---|
| `register.php` | POST | Validates and inserts new donor into DB |
| `submit_request.php` | POST (JSON) | Creates a new blood request record |
| `update_request.php` | POST | Updates `Blood_Request` records by `ReqID` |
| `delete_request.php` | POST | Deletes `Blood_Request` records by `ReqID` |
| `get_inventory.php` | GET | Returns JSON of current blood unit counts per type |

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
