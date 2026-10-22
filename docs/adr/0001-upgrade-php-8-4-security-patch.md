# 1. Upgrade PHP 8.4 to 8.4.27 Security Patch

Date: 2026-10-22  
Status: Accepted

Following the critical PHP security advisory published on 2026-10-21 (PHP2Shell / RCE core vulnerability affecting PHP <= 8.4.26), Simon Center CRM runtime on VPS `112.78.15.2` was upgraded from `PHP 8.4.19` to `PHP 8.4.27-1+ubuntu22.04.1+deb.sury.org+1`. We retained the PHP 8.4 branch rather than jumping to PHP 8.5 to preserve strict backward compatibility with existing PDO MySQL and session handlers, while ensuring complete immunity to the core RCE exploit.
