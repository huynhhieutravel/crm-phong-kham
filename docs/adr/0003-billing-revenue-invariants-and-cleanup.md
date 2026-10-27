# 3. Billing Revenue Invariants, Accounting Integrity, and Historical Remediation

Date: 2026-10-27  
Status: Accepted

## Context

A discrepancy was reported by clinic cashiers where zero-charge invoices (e.g., 100% staff/family discount, promotional vouchers, full coin/package redemption) recorded phantom cash receipts in the system. An investigation of the production database (`clinic_management`) identified 83 historical invoices with `total_amount = 0` but `cash_amount > 0`, generating 82 phantom income records in the `transactions` ledger totaling 77,049,910 VNĐ.

The root cause was two-fold:
1. **Frontend State Leaks**: In `includes/invoice_popup.php`, selecting a retail service auto-filled the cash input. When cashiers subsequently applied a 100% discount, the cash input remained populated with the old value and was submitted to the server.
2. **Missing Backend Invariants**: `modules/billing/create_invoice_api.php` blindly persisted client-submitted payment values without enforcing accounting invariants against the net payable balance.

## Decisions

### 1. Enforce Strict Server-Side Accounting Invariants
`create_invoice_api.php` acts as the definitive accounting boundary and enforces the following mathematical invariants:
- **Total Amount**: `total_amount = max(0, subtotal - discount_amount)`
- **Net Payable**: `net_payable = max(0, total_amount - package_deduct)`
- **Zero-Balance Invariant**: If `net_payable <= 0`, all monetary collection fields (`cash_amount`, `transfer_amount`, `transfer_personal_amount`, `transfer_company_amount`, `card_amount`, `debt_amount`) MUST be forced to `0.00`.
- **Payment Upper Bound**: Actual collected funds (`cash + transfer_personal + transfer_company + card`) cannot exceed `net_payable`. Any excess is clamped, and unpaid remainder is strictly assigned to `debt_amount`.

### 2. Dual-Entry Ledger Synchronization
Records in `transactions` are generated only for genuine positive payments (`amount > 0`). When invoices are cancelled or modified via `update_invoice_status_api.php`, associated transactions are atomically deleted or updated within the same database transaction.

### 3. Historical Data Remediation
1. Take a full database snapshot before mutation.
2. Execute an idempotent migration script setting `cash_amount = 0`, `transfer_* = 0`, and `card_amount = 0` on all invoices where `total_amount = 0`.
3. Purge corresponding phantom rows in `transactions` where `reference_id` points to zero-total invoices and description matches `Phiếu tính tiền %`.

### 4. UI Transparency in Billing Table
Enhance `modules/billing/index.php` to display payment composition badges (Cash, Transfer, Card, Coin Deduct, Discount) alongside the net payable amount in the table, giving cashiers immediate audit visibility.

## Consequences

- Daily, monthly, and KPI revenues in both `modules/billing` and `modules/reports` accurately reflect actual funds collected.
- Accidental UI misconfiguration can never corrupt backend financial reporting.
- Cashiers have immediate visual feedback on the breakdown of each invoice.
