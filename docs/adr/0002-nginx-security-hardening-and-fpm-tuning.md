# 2. Nginx Security Hardening and PHP-FPM Worker Pool Tuning

Date: 2026-10-22  
Status: Accepted

## Context
During deep QA auditing of Simon Center CRM following the PHP 8.4.27 upgrade, two critical operational risks were identified:
1. PHP-FPM worker pool reached capacity (`pm.max_children = 5`), causing request queuing during peak clinic operating hours.
2. Nginx allowed direct public HTTP downloads of sensitive files (`.env`, `.sql.gz`, `.bak`, `.exp`, `.sh`) and lacked defensive security headers.

## Decision
1. **PHP-FPM Worker Tuning**: Upgraded `/etc/php/8.4/fpm/pool.d/www.conf` from 5 to 15 max workers (`pm.max_children = 15`, `pm.start_servers = 3`, `pm.min_spare_servers = 2`, `pm.max_spare_servers = 5`, `pm.max_requests = 1000`) to handle peak traffic without memory leaks.
2. **Nginx Security Rules**: Configured Nginx to explicitly return `404 Not Found` for sensitive directories (`config`, `sessions`, `scripts`, `database`, `cron`, `Backup`) and sensitive file extensions (`.env`, `.sql`, `.sql.gz`, `.bak`, `.tar.gz`, `.sh`, `.exp`, `.git`).
3. **HTTP Header Hardening**: Disabled `server_tokens` (hiding server version) and injected standard defensive headers (`X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`).
4. **Codebase Cleanup**: Removed raw verbose `error_log()` array dump from `modules/billing/create_invoice_api.php` to prevent log pollution and I/O overhead.
