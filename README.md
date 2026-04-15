# JZ Sisters Trading OPC Management System

Simplified procedural PHP + MySQL system using Tailwind CSS with crimson-red and white theme.

## Tech Stack
- PHP (non-OOP / procedural style)
- MySQL (via PDO)
- Tailwind CSS (local build via Tailwind CLI)
- Chart.js (local vendor file)
- jsPDF (local vendor file)
- JavaScript (simple modal control)

## Flowchart-Based Modules
- Authentication: Sign Up / Sign In for Admin and Cashier
- Cashier Flow:
  - Dashboard -> Browse Products -> Choose Product -> Order Confirmation
  - Generate Sales Order -> Payment -> Update Stock Records -> End
- Admin Flow:
  - Dashboard
  - Inventory Department
  - Purchasing Department
  - Receiving Department
  - Storage Department
  - Accounting Department
- System Logic:
  - YES (approved/available): move to next process
  - NO (rejected/not available): return or notify previous/related department

## Folder Structure
- `includes/` shared db/auth/helpers bootstrap
- `partials/` layout header/footer/sidebar
- `admin/` admin department pages
- `cashier/` cashier flow pages
- `receipt.php` printable receipt page
- `assets/js/modal.js` modal open/close script
- `database.sql` schema and starter data

## Setup (XAMPP)
1. Place project in `C:\xampp\htdocs\client1`.
2. Start Apache and MySQL in XAMPP Control Panel.
3. Import `database.sql` into MySQL:
   - Open `http://localhost/phpmyadmin`
   - Create/import using the `database.sql` file
4. Configure `config.php`:
  - `app.base_url`: default URL path (example: `/client1`)
  - `database.host`
  - `database.name`
  - `database.username`
  - `database.password`
  - `database.charset`
  - Environment variable overrides are supported (`APP_URL`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`)
5. Install frontend build dependency and generate local assets:
  - Run `npm install`
  - Run `npm run build:ui`
  - Optional watch mode for CSS while developing: `npm run watch:css`
6. Open the app:
  - `http://localhost/client1/`

## URL Configuration
- The system now uses centralized URL helpers:
  - `app_url('path/to/page.php')` for app links and redirects
  - `asset_url('css/tailwind.css')` for local static assets
- Change `app.base_url` in `config.php` to move the app under a different subfolder.

## Offline UI Assets
- Tailwind CSS is loaded from `assets/css/tailwind.css` (compiled from `assets/css/tailwind.input.css`).
- Chart.js is loaded from `assets/vendor/chartjs/chart.umd.js`.
- jsPDF is loaded from `assets/vendor/jspdf/jspdf.umd.min.js`.
- Vendor JS files are synced from `node_modules` using `npm run sync:vendor`.
- After running setup, the UI does not require CDN access.

## Deployment Checklist
- Include `assets/css/tailwind.css` in the deployment package.
- Include the full `assets/vendor/` folder in the deployment package.
- If dependencies are updated, run `npm run build:ui` again before deployment.

## Default Accounts
- Admin: `admin`
- Cashier: `cashier`
- Password for both: `password`

## Notes
- CRUD actions are modal-based across major modules.
- Payment posting updates stock and creates inventory records.
- After successful payment, printable receipt opens automatically.
- Receiving inspection YES/NO path affects storage and purchasing flows.
- Inventory low stock can notify purchasing and trigger quick PO creation.

## New Quick Access
- Cashier and admin receipt view: `/client1/receipt.php?payment_id=PAYMENT_ID`

## Migration Note (Email to Username)
- New installs already use `username` in `users` table.
- For existing databases using `email`, run migration SQL once:
  - `migrations/2026_04_15_email_to_username.sql`
