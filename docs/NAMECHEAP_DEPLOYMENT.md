# Namecheap / cPanel deployment — v1.4.0

1. cPanel -> **MySQL Databases**: create an empty DB and user; grant **ALL PRIVILEGES** on that DB.
2. Upload and extract the clean ZIP.
3. Select PHP 8.1+ and enable `pdo_mysql`, `curl`, `json`, and sessions.
4. Browse to `/install.php` and enter only MySQL coordinates plus the OD Lab administrator credentials.
5. On success, delete `install.php` immediately.
6. Prefer a domain/subdomain document root pointed at `/public`; otherwise use `/public/login.php`.
7. Login and open **Diagnostic / Health**. Require PASS for PDO MySQL connection, required tables, transactional write+rollback, cURL, and “no API-key persistence column”.
8. Enter the DeepSeek BYOK key in Mission Control only for the current page/run. The key is not saved.
9. Launch `smoke` with one seed. Inspect the HTML report and JSON research export before larger suites.
10. For research comparisons, use at least three seeds and the relevant suite; `all` gives the complete H1–H5 analysis surface.

## Shared-hosting constraint

The runner is intentionally synchronous so one orchestration path owns scientific state and evidence. A large `all` run may hit shared-hosting request limits. Do not create a second scientific engine in a cron path; any future queue/cron adapter must call the same `LabService`.

## BYOK privacy

OD Lab has no key column in MySQL and no key in `config.php`. Mission Control does not use browser `localStorage`/`sessionStorage` for the key. It is transmitted only with the run request over HTTPS and held in request memory while the run executes. Reloading/closing the page forgets it.
