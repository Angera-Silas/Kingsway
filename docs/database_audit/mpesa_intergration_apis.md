# Daraja 3.0 APIS FOR MADARAKA SCHOOL MANAGEMENT SYSTEM

This document details all Safaricom M-Pesa APIs integrated into our school ERP system, their endpoints, parameters, and usage patterns.

## Table of Contents

1. [Authentication](#authentication--access-token)
2. [STK Push (Lipa Na M-Pesa Online)](# stk-push)
3. [C2B (Customer To Business) Paybill](#c2b-customer-to-business)
4. [B2C (Business To Customer) Bulk](#b2c-business-to-customer)
5. [Transaction Status Query](#transaction-status-query)
6. [Account Balance](#account-balance)
7. [Reversal](#reversal)
8. [Dynamic QR Code](#dynamic-qr-code)
9. [Withdraw Cash (Lipa Na Bonga)](#withdraw-cash)
10. [B2B Payment (Tax Remittance, Business Pay Bill)](#b2b-business-to-business)

---

## Authentication & Access Token

**All API calls require an `access_token` obtained via client credentials.**

### Endpoint
`GET https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials`

### Request Headers
```
Authorization: Basic base64_encode(consumer_key:consumer_secret)
```

### Response (200 OK)
```json
{
  "access_token": "jA32...tHfQ",
  "expires_in": "3600",
  "token_type": "Bearer"
}
```

### PHP Usage
```php
$tokenUrl = MPESA_BASE_URL . '/oauth/v1/generate?grant_type=client_credentials';
$credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = json_decode(curl_exec($ch), true);
$accessToken = $response['access_token'];
```

---

## STK Push (Lipa Na M-Pesa Online)

**Used for:** Student fee payments via smartphone USSD

### Endpoint
`POST https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest`

### Request Body
```json
{
  "BusinessShortCode": "174379",
  "Password": "base64_encode(ShortCode+PassKey+Timestamp)",
  "Timestamp": "20240415103022",
  "TransactionType": "CustomerPayBillOnline",
  "Amount": 1500,
  "Msisdn": "254708374149",
  "Remarks": "School fees for Grade 3",
  "AccountReference": "KA-2026-001",
  "TransactionDesc": "Term1 fees",
  "CallBackURL": "https://yourdomain.com/api/payments/mpesa-callback"
}
```

### Response (Initiation Success)
```json
{
  "ResponseCode": "0",
  "ResponseDescription": "Success. Transaction initiated.",
  "CheckoutURL": "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/invoke",
  "CheckoutRequestID": "ws.CO10925.20240415.103022.12345678",
  "ChannelID": "4"
}
```

### PHP Implementation (`MpesaPaymentService.php`)
- `initiateSTKPush($admission_number, $phone, $amount)`
- Returns: `['success' => true, 'checkout_url' => '...', 'request_id' => '...']`

---

## C2B (Customer To Business) Paybill

**Used for:** Parents paying via Paybill using their phone's M-Pesa menu

### Register Validation URLs
`POST https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl`

```json
{
  "ValidationURL": "https://yourdomain.com/api/payments/mpesa-validation",
  "ConfirmationURL": "https://yourdomain.com/api/payments/mpesa-confirmation",
  "Shortcode": "174379"
}
```

### Simulate Payment (Sandbox Testing)
`POST https://sandbox.safaricom.co.ke/mpesa/c2b/v1/simulate`

```json
{
  "Shortcode": "174379",
  "Msisdn": "254708374149",
  "CheckerID": "1",
  "Amount": 100
}
```

### Response
```json
{
  "ResultCode": 0,
  "ResultDesc": "Accept request and process payment",
  "OriginatorConversationID": "20240415103022.1"
}
```

### Callback Handling

**Validation URL** (fail fast if invalid):
```json
{
  "Body": {
    "Tipo": "BusinessPayment",
    "TransactionalID": "TESTXXXX",
    "TransmissionID": "2024415101025701",
    "PIS": "254708378001",
    "Business": "254708374149",
    "Cashier": "254708374149",
    "Amount": "1500.00",
    "WorkstationID": "REG001",
    "DeviceID": "DEMO1",
    "OrgaccountID": "174379",
    "TransactionType": "AccountPayment",
    "ReferenceID": "TESTAPI2",
    "TransactionTime": "20240415101025",
    "FirstName": "John",
    "MiddleName": "K",
    "LastName": "Doe",
    "PhoneNumber": "254708374149"
  }
}
```

**Confirmation URL**:
```json
{
  ".stkId": "1",
  "TxId": "TEST12345",
  "TransId": "TEST12345",
  "TransActionTime": "20240415101025",
  "Hist:StkId": "1",
  "PartyA": "600426",
  "PartyB": "174379",
  "TransAmount": "1500.00",
  "OranizationID": "4760945",
  "Value": "1500.00",
  "TransactionAmount": "1500.00",
  "ReferenceID": "TEST12345",
  "CheckedData": null,
  "Debtor": {
    "name": "John Doe",
    "msisdn": "254708374149"
  }
}
```

### PHP Implementation
- `processC2BConfirmation($callbackData)` - validates, updates `payments` and `student_fee_obligations`
- `validateAdmissionNumber($number)` - cross-checks student exists

---

## B2C (Business To Customer) Bulk

**Used for:** Staff salary disbursements, staff-child fee waivers

### Endpoint
`POST https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest`

### Request Body
```json
{
  "Initiator": "testapi",
  "SecurityCredential": "ENCRYPTED_BASE64_PASSWORD",
  "CommandID": "BusinessPayment",
  "Amount": 50000,
  "PartyA": "174379",
  "PartyB": "254708374149",
  "Remarks": "Salary payment",
  "QueueTimeOutURL": "https://yourdomain.com/api/payments/b2c-timeout",
  "ResultURL": "https://yourdomain.com/api/payments/b2c-callback",
  "ResultDesc": "Monthly salary"
}
```

### Response (Initiation)
```json
{
  "ResponseCode": "0",
  "ResponseDesc": "Success",
  "ConversationID": "20240415101025701",
  "OriginatorConversationID": "testapi.174379.20240415101025701",
  "ResultURL": "https://yourdomain.com/api/payments/b2c-callback"
}
```

### Cashout Command IDs
| CommandID | Description |
|-----------|-------------|
| `BusinessPayment` | Standard business payment |
| `SalaryPayment` | Employee salaries |
| `PromotionPayment` | Promotions/bonuses |
| `RandomPayment` | One-off payments |

### PHP Implementation (`MpesaB2CService.php`)
- `sendPayment($phone, $amount, $description)`
- Uses predefined credentials from `.env`

---

## Transaction Status Query

**Used for:** Verifying payment success when callbacks might be missed

### Endpoint
`POST https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query`

### Request Body
```json
{
  "BusinessShortCode": "174379",
  "Password": "base64_encoded",
  "Timestamp": "20240415103022",
  "CheckoutID": "ws.CO10925.20240415.103022.12345678",
  "IdentityID": "254708374149",
  "Initiator": "testapi",
  "SecurityCredential": "ENCRYPTED"
}
```

### Response (Valid CheckoutID)
```json
{
  "ResponseCode": "0",
  "ResponseDesc": "Success. Transaction found",
  "errorMessage": ""
}
```

### PHP Implementation
- `checkTransactionStatus($checkoutId, $phone)`
- Used to reconcile orphaned transactions

---

## Account Balance

**Used for:** Pre-checking wallet balance before bulk disbursement

### Endpoint
`POST https://sandbox.safaricom.co.ke/mpesa/accountbalance/v1/query`

### Request Body
```json
{
  "BusinessShortCode": "174379",
  "Password": "base64_encoded",
  "Timestamp": "20240415103022",
  "Initiator": "testapi",
  "SecurityCredential": "ENCRYPTED"
}
```

### Response
```json
{
  "ResponseCode": "0",
  "ResponseDesc": "Success. Account balance retrieved",
  "Currency": "KES",
  "Balance": "584234.00"
}
```

### PHP Implementation (`DisbursementManager.php`)
- `verifyAvailableBalance($requiredAmount)`
- Returns `true` if sufficient balance

---

## Reversal

**Used for:** Reversing erroneous transactions (must be within 24 hours of payment)

### Endpoint
`POST https://sandbox.safaricom.co.ke/mpesa/reversal/v1/request`

### Request Body
```json
{
  "BusinessShortCode": "174379",
  "Password": "base64_encoded",
  "Timestamp": "20240415103022",
  "TransactionID": "TEST12345",
  "PartyA": "174379",
  "IdentifierType": "4",
  "Remarks": "Incorrect amount charged",
  "QueueTimeOutURL": "https://yourdomain.com/api/payments/reversal-timeout",
  "ResultURL": "https://yourdomain.com/api/payments/reversal-callback"
}
```

### Response
```json
{
  "ResponseCode": "0",
  "ResponseDesc": "Success",
  "ConversationID": "...",
  "OriginatorConversationID": "..."
}
```

---

## Dynamic QR Code

**Used for:** School-based QR payments at gate/entrance

### Endpoint
`POST https://sandbox.safaricom.co.ke/mpesa/qrcode/v1/generate`

### Request Body
```json
{
  "MerchantName": "Kingsway Academy",
  "VirtualHostRef": "KA-2026-QR-TEST",
  "BusinessCode": "174379",
  "CurrencyCode": "KES",
  "CountryCode": "254",
  "UTMs": [{
    "UTM": [
      {"amount": 1500, "uom": "1", "description": "Term1 fees"},
      {"amount": 1500, "uom": "2", "description": "Term2 fees"}
    ]
  }]
}
```

### Response
```json
{
  "QRCodeResponse": {
    "QRCode": "0254174379102310A1B2C3D4E5F67890",
    "QRType": "Dynamic",
    "ExpirationTime": "2024-04-16T10:30:00"
  }
}
```

---

## Business To Business (B2B)

### Tax Remittance
`POST https://sandbox.safaricom.co.ke/mpesa/b2b/v1/remittax`

For remitting NSSF, NHIF, and other statutory deductions.

### Business Pay Bill
`POST https://sandbox.safaricom.co.ke/mpesa/b2b/v1/paymentrequest`

For school to pay suppliers/vendors.

---

## Withdraw Cash (Lipa Na Bonga)

**Used for:** Parents withdrawing money from M-Pesa

### Endpoint
`POST https://sandbox.safaricom.co.ke/v1/lipa/na/bonga/request`

### Request Body
```json
{
  "BusinessShortCode": "174379",
  "Position": "0",
  "IPAddress": "127.0.0.1",
  "Remarks": "ATM Cashout",
  "EncryptedSecurityCredential": "..."
}
```

---

## Configuration Constants

| Variable | Sandbox Value | Description |
|----------|---------------|-------------|
| `MPESA_BASE_URL` | `https://sandbox.safaricom.co.ke` | Base API URL |
| `MPESA_SHORTCODE` | `174379` | Business Paybill/Till shortcode |
| `MPESA_PASSKEY` | *hidden* | Passkey for STK Push |
| `TEST_PARTY_A` | `600426` | Sandbox Business Shortcode |
| `TEST_PARTY_B` | `600000` | Sandbox Receiver Account |
| `TEST_PHONE_NO` | `254708374149` | Sandbox test phone |

---

## Testing Workflow

1. **Get Access Token**: Call OAuth endpoint first
2. **Register URLs**: For C2B validation/confirmation
3. **Simulate**: Use `/simulate` for testing before going live
4. **STK Push**: Test `initiateSTKPush` with test phone
5. **C2B Callback**: Verify webhooks hit your endpoints
6. **B2C Disbursement**: Test salary bulk payments

---

## Outbound API Triggers (App-Endpoints)

Every Daraja API is also drivable from the app via authenticated, RBAC-gated
endpoints on `api/controllers/PaymentsController.php`. Client-side permission
gates live in `js/api.js` `ENDPOINT_PERMISSIONS` (`finance_create` /
`finance_view`); server-side enforcement is `authorizePaymentsAction()`, which
allows only the real roles: **Accountant**, **School Administrator**,
**Director**, **System Administrator** (role IDs 10/4/3/2) or the
`finance_manage` / `finance.create` / `payments.*` permission codes.

| Trigger endpoint | Maps to Daraja API | Params |
|---|---|---|
| `POST /api/payments/mpesa-stk-push` | STK Push | `admission_no`/`bill_ref_number`, `phone`, `amount`, `description` |
| `POST /api/payments/mpesa-stk-query` | STK Query | `checkout_request_id`, `phone` |
| `POST /api/payments/mpesa-c2b-register` | C2B Register URL | `response_type` (default `Completed`) |
| `POST /api/payments/mpesa-c2b-simulate` | C2B Simulate | `amount`, `phone`, `bill_ref_number`, `command_id` — **sandbox only**; production returns 400 |
| `POST /api/payments/mpesa-transaction-status` | Transaction Status | `transaction_id`, `remarks`, `occasion` |
| `POST /api/payments/mpesa-account-balance` | Account Balance | — |
| `POST /api/payments/mpesa-reversal` | Reversal | `transaction_id`, `amount`, `receiver_party` |
| `POST /api/payments/mpesa-qr` | Dynamic QR | `ref_no`, `amount`, `merchant_name`, `cpi`, `trx_code`, `size` |
| `POST /api/payments/mpesa-b2b` | B2B (Tax Remit) | `amount`, `receiver_shortcode`, `account_reference`, `remarks` |
| `POST /api/payments/mpesa-b2c` | B2C | `phone`, `amount`, `command_id`, `remarks` |
| `GET /api/payments/mpesa-results` | (async results) | `limit` (1–50) — reads back `payment_webhooks_log` (`mpesa_result`/`mpesa_b2c`), `disbursement_transactions`, `mpesa_transactions` |

Async APIs (transaction status, account balance, reversal, B2B, B2C) return an
acceptance synchronously; the real result arrives later on the webhook sinks
(`/api/payments/mpesa-result`, `/api/payments/mpesa-b2c-callback`,
`/api/payments/mpesa-b2c-timeout`) and is retrievable via
`GET /api/payments/mpesa-results`.

### Endpoint smoke test (dev)

`scripts/mpesa_smoke.js` drives the **real JSON API surface** exactly as the
app does (login -> JWT + CSRF -> JSON calls to the `/api/payments/mpesa-*`
endpoints above), so tests cannot diverge from production behavior.

```bash
BASE_URL=http://localhost/Kingsway USERNAME=test_accountant PASSWORD='Pass123!@' \
  node scripts/mpesa_smoke.js stk-push '{"phone":"254797630228","amount":10,"admission":"KA-TEST-001"}'
```

Run one action at a time to avoid sandbox token throttling. Other actions:
`stk-query`, `c2b-register`, `c2b-simulate` (sandbox only), `status`, `balance`,
`reversal`, `qr`, `b2b`, `b2c`, `results`.

---

## Error Handling

Common response codes:
- `0` - Success
- `1` - Botched
- `2` - Insufficient funds
- `3` - Invalid account
- `4` - Invalid transaction
- `1011` - Invalid security credential
- `1012` - Invalid shortcode