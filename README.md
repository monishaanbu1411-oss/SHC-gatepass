# Sacred Heart College — Smart Student Movement & Digital Gate Pass System

A web application that replaces the printed pink **Shift-II Student Movement Pass**
with a digital one. Faculty approve a pass during class; the student, the Head of
Department and the gate are alerted in the same instant.

Built with PHP 8, MySQL/MariaDB, Bootstrap 5 and jQuery on an MVC structure.

---

## 1. Setting it up

### Requirements
- PHP 8.0 or later, with `pdo_mysql` and `mbstring`
- MySQL 8 or MariaDB 10.4+
- Apache with `mod_rewrite` (XAMPP, WAMP or LAMP all work as-is)

### Install

1. Copy the `shc-gatepass` folder into your web root
   (`C:\xampp\htdocs\` on XAMPP, `/var/www/html/` on Linux).

2. Create the database and load the demo data:

   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p < database/seed.sql
   ```

   Or import both files through phpMyAdmin, schema first.

3. Open `http://localhost/shc-gatepass/` and sign in.

The default settings in `config/config.php` match a stock XAMPP install
(`root`, no password). On a real server, set these environment variables
instead of editing the file:

```
SHC_DB_HOST   SHC_DB_NAME   SHC_DB_USER   SHC_DB_PASS   SHC_SECRET
```

`SHC_SECRET` signs every QR token. **Change it before going live** — anyone who
knows it can mint a valid pass.

### Demo accounts

| Role | Sign in with | Password |
|---|---|---|
| Student | `BU2610A01` | their date of birth (see the `students` table) |
| Faculty | `T261002` | `staff@123` |
| HOD | `T261003` | `hod@123` |
| Security | `gate01` | `gate@123` |
| Admin | `admin` | `admin@123` |

Student dates of birth are randomised in the seed data. To find one:

```sql
SELECT register_no, dob FROM students WHERE register_no = 'BU2610A01';
```

Change every password before the system is used for real.

---

## 2. How a pass moves through the system

```
FACULTY                    GATE                        RECORDS
   |                        |                             |
   | 1. pick course,        |                             |
   |    class & hour        |                             |
   | 2. tick students       |                             |
   | 3. set reason,         |                             |
   |    out time, validity  |                             |
   | 4. Approve ───────────────────────────────────────►  pass issued
   |         │              |                             |
   |         ├─► student notified                         |
   |         ├─► HOD notified                             |
   |         └─► gate alerted                             |
   |                        |                             |
   |                        | 5. scan QR ──────────────►  scan logged
   |                        |    verdict shown            |
   |                        | 6. Allow exit ───────────►  exit_time set
   |                        |                             status: left campus
   |                        |                             |
   |                        | 7. scan again on return ─►  return_time set
   |                        |                             status: returned
```

Every one of those steps writes a row to `pass_events`, so any pass can be
reconstructed exactly.

---

## 3. What each role sees

**Student** — dashboard, the live pass with a countdown and QR code, full
history, notifications, profile. Students cannot request a pass; only faculty
issue them.

**Faculty** — dashboard, the two-step issue form, the students they sent out,
everything they have issued, their department roster.

**HOD** — everything faculty see, plus a live monitor of the whole department:
who is out, who approved it, how long they have left, who is overdue.

**Security** — a live alert feed, the QR/barcode scanner, a full-screen verdict,
`Allow exit` / `Record return`, students currently outside, today's passes.

**Admin** — live analytics with department drill-downs, student/staff/security
account management, CSV bulk import, emergency pass cancellation, CSV export,
and three audit log views.

---

## 4. Design decisions worth knowing

**Overdue students keep being tracked.** The specification says a pass becomes
*Expired* when its time runs out. That is applied only to passes where the
student never actually left. If a student *is* outside and the clock runs out,
the pass stays `left_campus` and is flagged **overdue** — it stays in the
"students outside" count and turns red everywhere. Losing sight of someone who
is genuinely off campus would be worse than showing a stale label.

**One live pass per student.** A student who already holds an unfinished pass is
shown as unavailable on the roster and cannot be issued a second one.

**Faculty can only issue to their own classes.** The class list on the form comes
from `staff_courses`, and the server re-checks that assignment on submission, so
tampering with the form does not help.

**QR codes are signed.** Each pass carries an HMAC token derived from
`APP_SECRET`. The gate compares both the pass ID and the token, so a QR code
with an edited pass ID is rejected as invalid rather than looked up.

**Validity is bounded in three places** — the dropdown, the controller, and the
model clamp it to 45–120 minutes.

**Deletes are soft.** Removing a student sets `is_active = 0`; their pass history
survives for auditing.

---

## 5. Security

- Passwords hashed with `password_hash()` (bcrypt); students authenticate on
  register number + date of birth, as required by the specification
- Every query is a prepared statement
- CSRF token on every POST, including AJAX
- Account lockout after 5 failed attempts in 15 minutes
- Session regenerated on sign-in, 60-minute idle timeout
- All output escaped through `e()`
- Role checks in every controller constructor, plus per-record ownership checks
  on pass viewing
- `login_logs`, `activity_logs` and `pass_events` record who did what, when,
  and from which IP address

---

## 6. Front-end libraries

Bootstrap 5, jQuery, SweetAlert2, DataTables, Chart.js, Font Awesome, qrcodejs
and html5-qrcode are loaded from cdnjs.

**If the college network blocks external CDNs**, download those files into
`assets/vendor/` and change the URLs in `views/layouts/head.php`,
`views/layouts/foot.php`, `views/auth/login.php` and `views/pass/document.php`.
The application already degrades sensibly: if the QR library is missing, the
pass shows its ID in large monospace type so the gate can key it in by hand.

The Code 128 barcode is generated server-side in `core/Barcode.php` with no
external library, so it always renders.

---

## 7. PDF output

`Print outpass` and `Save as PDF` open the browser's print dialogue against a
print stylesheet that hides the interface and keeps the pass on one page. This
needs no extra library.

To generate PDF files on the server instead, drop TCPDF into a `vendor/` folder
and render `views/pass/document.php` through it from `PassController::print()`.

---

## 8. Project layout

```
shc-gatepass/
├── index.php              front controller — every request enters here
├── .htaccess              rewrites to index.php
├── config/                configuration and the PDO connection
├── core/                  Auth, Router, Controller, Model, CSRF, Logger, Barcode
├── controllers/           one per role, plus PassController and ApiController
├── models/                Pass, Student, Staff, Lookup, Notification, Log
├── views/
│   ├── layouts/           masthead, sidebar, shared tables
│   ├── auth/ student/ staff/ security/ admin/
│   ├── pass/document.php  the pass itself
│   └── shared/ errors/
├── assets/css/app.css     design system
├── assets/js/app.js       countdowns, notification polling, theme
├── database/              schema.sql and seed.sql
├── uploads/photos/        student photographs
└── logs/                  PHP error log
```

To show real student photographs, save image files into `uploads/photos/` and
put the filename in the `students.photo` column. Where no photo exists the
system falls back to the student's initials.

---

## 9. About the interface

The palette comes from the college's own materials: the navy and olive of the
existing LEAP portal, the crimson of its sign-in page, and the violet ink on
pink card of the paper movement pass.

The portal chrome is deliberately quiet. Colour is spent in one place — the pass
document, which keeps the paper slip's masthead, its `Shift – II | STUDENT
MOVEMENT PASS` band, its field order, its reason checkboxes and its signature
line, and adds what paper could not: the student's photograph, a live countdown,
a QR code and a scannable barcode.

Step 1 of the issue form is laid out like the attendance-entry screen faculty
already use daily — same date and course pickers, same hour checkboxes, same
register-number grid with Refresh and View names — so the only new thing to
learn is the approval modal.

Dark mode is available from the sidebar and is remembered per browser.
