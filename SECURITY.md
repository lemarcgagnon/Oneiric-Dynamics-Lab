# Security

This repository is a public research implementation. Never commit API keys, database passwords, production configuration files, or other secrets.

## BYOK policy

Oneiric Dynamics Lab uses a **bring-your-own-key (BYOK)** model. Provider API keys are supplied for the duration of a laboratory session only. They must remain transient and must not be written to MySQL, configuration files, logs, research results, reports, exports, or browser storage.

If you discover a security issue or any path by which a secret could be persisted or exposed, please report it privately to the repository owner before public disclosure.
