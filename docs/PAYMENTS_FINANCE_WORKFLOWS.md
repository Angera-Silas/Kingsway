# Payments and Finance Workflows

This document describes the production payment boundary for Kingsway Preparatory School. Learners do not authenticate or pay through a student portal; parents/guardians and authorized staff initiate the relevant actions.

## Fee collection

1. Fee structures generate `student_fee_obligations` for an active enrollment.
2. `vw_student_fee_balances` independently aggregates obligations, approved waivers, and confirmed payments at student/term grain.
3. A parent can initiate either:
   - Safaricom Daraja STK Push through `POST /api/parent-portal/initiate-mpesa-payment` with `provider=daraja`.
   - KCB Buni M-Pesa Express through the same endpoint with `provider=buni`.
4. The provider acknowledgement only means the request was accepted. The callback is the authority for payment completion.
5. The callback is deduplicated, stored in the provider callback ledger, and recorded in `mpesa_transactions` or `bank_transactions`.
6. `sp_process_student_payment` creates the confirmed `payments` row. The balance view then reflects the payment.
7. The receipt reference and parent-portal statement link are queued through the communications outbox. SMS and WhatsApp delivery are retried by the worker.

Cash and manually reconciled bank/cheque payments use the same confirmed `payments` table and must not be marked confirmed until staff have verified the receipt or bank evidence.

## Provider endpoints

Daraja uses the existing M-Pesa STK/C2B services and callbacks.

KCB Buni uses the downloaded Swagger/Postman contracts:

- Funds transfer: `POST /fundstransfer/1.0.0/api/v1/transfer`
- M-Pesa Express: `POST /mm/api/request/1.0.0/stkpush`
- Account/till IPN callbacks: the school endpoint `/api/payments/kcb-notification`
- Funds-transfer callback: `/api/payments/kcb-transfer-callback`

KCB IPN signatures are RSA/SHA256 signatures over the exact request body and are verified with `KCB_PUBLIC_KEY_PATH`. Production must set `KCB_VERIFY_CALLBACK_SIGNATURE=true`.

## Payroll

Payroll follows draft → calculate → verify → approve → disburse. Payslips store gross pay, PAYE, NSSF, SHIF/NHIF legacy field, housing levy, other deductions, and net pay. A bank salary payment is inserted into `disbursement_transactions` before calling Buni. The callback changes the disbursement and payslip state; a timeout is reconciled by status query before retrying.

P9 generation is reporting only. It does not remit PAYE, NSSF, SHIF, or housing levy.

## Statutory remittances

`statutory_remittances` stores the monthly liability and filing state. The normalized tables `statutory_agency_accounts` and `statutory_remittance_attempts` store the configured agency destination, idempotency key, provider, amount, and final payment status.

Authorized payroll staff submit a payment through the statutory-payment modal, which preloads active accounts for the selected agency:

`POST /api/staff/statutory-remittances/{id}/initiate-payment`

with `agency_account_id` selected from that list. The amount is the outstanding deducted amount unless explicitly supplied. A bank callback marks the attempt paid and updates `amount_remitted`; it does not mark a remittance paid merely because Buni accepted the request.

Agency account numbers and payment references must be verified with the relevant authority. KRA/NSSF/SHIF portals may require an official payment reference or channel that is not equivalent to a generic bank transfer.

## Supplier payments

Supplier bank accounts are stored separately in `supplier_bank_accounts`. An approved expense paid by bank transfer creates:

- one `supplier_payment_requests` row;
- one correlated `disbursement_transactions` row;
- one Buni Funds Transfer attempt.

The supplier expense remains `payment_pending` until the callback confirms it. A failed callback returns the expense to `approved`, allowing review and controlled retry.

Suppliers who use a phone instead of a bank account are stored in `supplier_mobile_accounts` with provider `mpesa`. An approved expense submitted with `channel=mpesa_b2c` uses M-Pesa B2C, creates the normal `disbursement_transactions` callback record, and completes the supplier payment only after the B2C callback confirms success. The phone number and account name must be verified before submission.

### Staff payment screen

Director, school administrator, and accountant roles use the `Supplier Payments` page. It loads approved expenses with calculated outstanding balances and verified supplier accounts. The operator can select one or many rows, enter a partial or full amount for each, choose Bank Transfer or M-Pesa B2C, and submit the batch. Each item is processed independently and returns its provider reference or failure reason.

Supplier account setup is separate from payout submission. The API lists all accounts, creates bank/M-Pesa accounts in `pending` verification state, and lets authorized finance users verify, deactivate, or mark an account primary. The payment queue deliberately excludes unverified accounts, making an unconfigured destination a visible blocked row rather than an unsafe payout.

## Required configuration

Do not place beneficiary accounts in source code or use one global credit account for every transaction. Configure only school/provider-owned values in `.env`:

```dotenv
KCB_COMPANY_CODE=issued_by_kcb
KCB_DEBIT_ACCOUNT=school_source_account
KCB_COLLECTION_ACCOUNT_IDENTIFIER=issued_by_kcb
KCB_PUBLIC_KEY_PATH=config/kcb_public_key.pem
KCB_VERIFY_CALLBACK_SIGNATURE=true
```

Supplier, staff, agency, and other beneficiary accounts belong in their normalized database tables and require verification before payment.

## Staff salaries and parent refunds

Payroll remains the authoritative source for staff amounts: the payroll screen
prefills calculated net salaries, supports selecting one or many eligible staff,
and sends the approved batch through the existing M-Pesa B2C or KCB bank
disbursement path. Staff payment status is completed only by the provider
callback.

An excess fee is handled differently from a ledger reversal. A reversal marks
the original payment reversed so it no longer contributes to the fee balance;
an overpayment refund creates a `parent_refund_requests` record. Staff first
select a verified parent bank/M-Pesa destination and submit the request for
approval. The approver then authorizes provider submission. The resulting
`disbursement_transactions` row is correlated to the refund request and the
M-Pesa/KCB callback changes it to `paid` or `failed`. Graduation or transfer
does not automatically trigger a refund: the outstanding credit must be
reviewed, the parent destination verified, and the request approved.

The `Parent Refunds` page preloads eligible credits and verified parent
destinations, supports selecting one or many credits, and permits a partial or
full amount per request. It submits requests only; approval and provider
submission remain separate controlled actions.

## Operational verification

Run migrations with the project’s migration command or, on the configured XAMPP database:

```bash
/opt/lampp/bin/mysql -u root -pYOUR_DB_PASSWORD KingsWayAcademy < database/migrations/*.sql
```

Run the worker timer and inspect `communication_delivery_attempts`, `payment_provider_attempts`, `payment_provider_callbacks`, `disbursement_transactions`, and the relevant business row before declaring a payment complete.
