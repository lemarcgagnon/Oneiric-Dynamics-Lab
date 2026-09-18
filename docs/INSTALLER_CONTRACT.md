# Installer contract — v1.4.0

`install.php` is the only installation entry point.

Before mutation it checks PHP >= 8.1, PDO MySQL, cURL, JSON, `random_bytes`, and filesystem writability. It creates `storage/` defensively and performs a real write probe.

Installation then:

1. validates DB/admin fields and administrator password length;
2. connects to the supplied MySQL database with PDO exceptions and native prepares;
3. creates the schema;
4. writes `config.php` with database coordinates only;
5. begins a DB transaction;
6. refuses a database already containing OD Lab users;
7. creates the administrator with `password_hash()`;
8. creates the `app_settings` schema-version row;
9. creates `storage/.installed` before DB commit;
10. commits and reports success.

If installation fails, DB changes are rolled back, the lock is removed, and a newly generated config is removed.

**BYOK rule:** the installer never asks for, encrypts, stores, or writes any LLM API key. DeepSeek keys are supplied only when a mission is launched and are never persisted by OD Lab.

After success, delete `install.php` and open `public/health.php` after login to verify the actual deployed PDO/MySQL environment.
