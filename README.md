# SecurePass Revamp

Separate development application for the SecurePass gatepass workflow. The existing `gatepass` application is a read-only reference during this work.

## Runtime

- PHP 8.2 with `pdo_sqlsrv` and `sqlsrv`
- Apache from XAMPP
- Microsoft SQL Server test host `10.2.0.167`, database `LRNPH_OJT`

The application refuses any other database host or database name, including the live host and database. It does not connect to a database until `securepass_db()` is called.

## Local setup

1. Copy `config/local.example.php` to `config/local.php`.
2. Set the test database password in `local.php`. This file is excluded from Git. Never place live credentials here.
3. Serve `public/` as the document root. With the default XAMPP `htdocs` setup, visit `/gatepassrevamp/public/`. The root `.htaccess` denies HTTP access outside `public/`.
4. Run `C:\xampp\php\php.exe tests\config_test.php` to verify the database host guard.

The proposed new tables are in `database/001_initial.sql`. Every table uses the `dbo.acdsecurepass_` prefix. The migration refuses to run unless the active database is `LRNPH_OJT`. Review the schema before applying it to the test server.

This foundation provides an isolated entry point, employee sign-in, request creation, private attachments, and a request summary. Approval, return tracking, and administration are still to be built.

## Sign in

The revamp uses existing active accounts in `dbo.lrnph_users` for password verification and `dbo.lrn_master_list` for employee names and departments. Every account with an active employee master list record and department can create requests. Approval and administration require explicit entries in `dbo.acdsecurepass_user_roles`. The isolated seed accounts described below are kept in a separate test-only table.

The login page fits within a normal desktop viewport and uses locally hosted GSAP 3.15.0 for a quick entrance, focus, and springy button motion. Motion is skipped when the user prefers reduced motion. Small screens can scroll so the form remains usable with an on-screen keyboard.

The dashboard, request form, and request details use Central-inspired, quick staggered reveals with small vertical and scale changes. Form steps and items reveal the same way, and buttons respond with a short spring press. Navigation, exports, and downloads are immediate; no page-exit fade delays them. There is no horizontal page motion.

## Dashboard UI

After sign-in, `requests.php` is the main dashboard. Its sidebar, top bar, illustrated banner, four status totals, filters, and request table follow the supplied dashboard reference. The sidebar has only Dashboard and New request navigation, plus a real per-user request snapshot showing total, Pending, and Approved counts. Account details and sign out remain at the bottom. The banner and status cards are compact so the request summary has more vertical space. The cards use the request states that exist in the test schema: Approved, Rejected, Pending, and Draft. Search, created-date range, status, current approval step, page size, detail links, and CSV export work on the signed-in creator's requests. The Request Summary has a Search button and a Filters dropdown with an explicit Apply filters button, an active-filter count, and applied-filter chips. Search and Apply filters refresh results without a full page reload when JavaScript is available; the GET form, pagination, and CSV export remain usable through normal links. Searches treat wildcard characters literally and accept displayed category names. The CSV export includes up to 1,000 matching requests. `dashboard.php` redirects to this page for older links. The three-step request form keeps its existing fixed layout inside the same sidebar and top bar. Request details share that shell, with a request overview, approval timeline, and item attachment list. Approval workspace, activity logs, settings, and return tracking are still to be built.

## Request creation

The creator form writes only to `dbo.acdsecurepass_` tables in the test database. It creates one request with one or more items, private attachments, an initial department-head approval step, and an audit event in one database transaction. Attachments are stored under `storage/uploads/`, outside `public/`, with generated names. The root Apache rule denies direct HTTP access to `storage/`.

## Isolated test accounts

`database/002_test_users.sql` adds a dedicated `dbo.acdsecurepass_test_users` table. `database/seed_test_users.php` creates seven test personas for the creator and approval stages, then writes their generated passwords to `config/test-credentials.local.txt`. Both the table and credentials are for `LRNPH_OJT` only. The script does not alter `lrnph_users` or the employee master list, and it refuses to replace existing seed accounts.
