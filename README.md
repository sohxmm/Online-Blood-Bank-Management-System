# Bloodline - Online Blood Bank Management System

> A full-stack web application for managing blood bank inventory, donor registrations, and hospital blood requests, designed as a deployable blood inventory and request coordination service.

## Overview

Bloodline connects donors, blood banks, and hospitals through real-time inventory tracking, donor registration, urgent blood requests, secure sessions, CSRF protection, and transaction-aware request processing.

## Features

- **Live Inventory Dashboard** - Real-time unit counts and critical stock alerts for all 8 blood groups.
- **Donor Registration** - Medical history, eligibility inputs, contact details, and password-protected donor accounts.
- **Blood Request System** - Hospital and patient request intake with urgency, blood group, and unit requirements.
- **Transaction-Safe Request Processing** - Fulfillment, update, revert, and delete actions keep `Blood_Inventory` synchronized.
- **Operations Console** - Database-backed metrics, low-stock reporting, and request fulfillment routines.
- **Query Performance Console** - Managed indexes and EXPLAIN output for request dashboard queries.
- **Audit + Access Control** - Transaction events in `Request_Transaction_Log` and SQL roles in `sql/transaction_security.sql`.
- **Secure Sessions & Cookies** - Shared auth helper, remember-me tokens, CSRF validation, and site preference cookies.

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, Vanilla JS |
| Animations | GSAP 3 + ScrollTrigger |
| Backend | PHP 8.2 |
| Database | MySQL / MariaDB |
| Local Server | Apache via XAMPP |
| Configuration | Environment variables with local defaults |

## Project Structure

```text
bloodlines/
+-- index.html
+-- registration.php
+-- make-request.html
+-- inventory.html
+-- dashboard.php
+-- manage_requests.php
+-- operations_console.php
+-- query_performance.php
+-- auth.php
+-- db.php
+-- register.php
+-- submit_request.php
+-- update_request.php
+-- delete_request.php
+-- get_inventory.php
+-- get_requests_feed.php
+-- dashboard_stats.php
+-- assets/
+-- sql/
    +-- final_erd_hybrid_alignment.sql
    +-- database_routines.sql
    +-- performance_indexes.sql
    +-- transaction_security.sql
+-- docs/                    # Protected project documentation and screenshots
```

## Database Schema

The schema uses healthcare workflow entities plus operational tables for the running service.

**Core entities:** `Donor`, `Blood_Bank`, `Hospital`, `Blood_Donations`, `Blood_Request`

**Operational extensions:** `Blood_Inventory`, `Request_Transaction_Log`, `Donor_Remember_Token`, `Contact_Messages`

**View:** `vw_inventory` aggregates total units per blood group.

## Local Setup

1. Start Apache and MySQL from XAMPP.
2. Create `bloodline_db` in phpMyAdmin using `utf8mb4_unicode_ci`.
3. Import `sql/final_erd_hybrid_alignment.sql`.
4. Import `sql/database_routines.sql` for operational metrics and fulfillment helpers.
5. Import `sql/performance_indexes.sql` when managed dashboard indexes are needed.
6. Open `http://localhost/bloodlines/index.html`.

For non-local environments, set these variables instead of relying on XAMPP defaults:

```text
BLOODLINE_DB_HOST=localhost
BLOODLINE_DB_USER=bloodline_app
BLOODLINE_DB_PASS=change-this-password
BLOODLINE_DB_NAME=bloodline_db
```

## Pages

| Page | URL | Description |
|---|---|---|
| Home | `/index.html` | Public service overview with inventory preview |
| Register | `/registration.php` | Donor registration and eligibility form |
| Request Blood | `/make-request.html` | Blood request intake |
| Dashboard | `/dashboard.php` | Authenticated donor/operator dashboard |
| Manage Requests | `/manage_requests.php` | Transaction-aware request console |
| Inventory | `/inventory.html` | Live blood stock dashboard |
| Operations Console | `/operations_console.php` | Routine-backed metrics, stock alerts, and fulfillment |
| Query Performance | `/query_performance.php` | Managed index controls and EXPLAIN output |

## API Endpoints

| File | Method | Description |
|---|---|---|
| `register.php` | POST | Validates and inserts a donor |
| `submit_request.php` | POST JSON | Creates a blood request |
| `update_request.php` | POST | Updates a request and reconciles inventory |
| `delete_request.php` | POST | Deletes a request and restores fulfilled inventory |
| `get_inventory.php` | GET | Returns live inventory JSON |
| `dashboard_stats.php` | GET | Returns dashboard analytics JSON |
| `get_requests_feed.php` | GET | Returns recent request feed JSON |

## Deployment Readiness

This repository is now named and documented like a service, but it still needs hardening before public deployment or real patient/donor data handling:

- Move secrets to server environment variables and create a least-privilege MySQL user.
- Put Apache behind HTTPS and enforce secure, HttpOnly, SameSite session cookies.
- Replace public file-based navigation with role-based routing for donors, hospitals, operators, and admins.
- Add strict server-side validation for every form field and rate-limit login, contact, and request endpoints.
- Use migration files for schema changes instead of running compatibility changes during normal requests.
- Add automated tests for registration, login, CSRF failure, request creation, fulfillment, revert, delete, and inventory totals.
- Keep report documents, screenshots, and personal assets outside public routing; `docs/.htaccess` blocks direct Apache access locally.
- Add backups and restore drills for `Blood_Inventory`, `Blood_Request`, `Donor`, and `Request_Transaction_Log`.
- Add operational monitoring for PHP errors, failed logins, request failures, and low-stock events.

## Maintainers

Bloodline Service Team

## License

Internal demonstration software. Complete production hardening before using it with real healthcare data.
