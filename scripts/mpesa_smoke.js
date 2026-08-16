#!/usr/bin/env node
/**
 * M-Pesa Daraja API smoke test (dev tool).
 * Drives the REAL authenticated JSON API surface exactly as the app does:
 *   1. POST /api/auth/login  ->  JWT + CSRF token
 *   2. POST/GET each /api/payments/mpesa-* endpoint with JSON body,
 *      Authorization: Bearer, and X-CSRF-Token headers
 * This guarantees the test exercises the same path as the production system
 * (router -> middleware -> controller -> PaymentsAPI -> service -> Daraja),
 * so a change cannot "work in the harness but fail over JSON".
 *
 * Usage:
 *   BASE_URL=http://localhost/Kingsway USERNAME=test_accountant PASSWORD=Pass123!@ \
 *     node scripts/mpesa_smoke.js <action> '<json>'
 *
 * Actions:
 *   stk-push   {"phone":"254797630228","amount":10,"admission":"KA-TEST-001"}
 *   stk-query  {"checkout_request_id":"ws_CO_0808...","phone":"254797630228"}
 *   c2b-register     {}
 *   c2b-simulate     {"amount":10,"phone":"0710398690","billref":"KA-TEST-001"}  (sandbox only)
 *   status     {"transaction_id":"SJLK3OJH1K"}
 *   balance    {}
 *   reversal   {"transaction_id":"SJLK3OJH1K","amount":10,"phone":"254710398690"}
 *   qr         {"amount":10,"ref":"KA-TEST-001","merchant":"Kingsway Academy"}
 *   b2b        {"amount":10,"receiver":"174379","ref":"KA-TEST-001"}
 *   b2c        {"phone":"254710398690","amount":10,"command":"BusinessPayment"}
 *   results    {"limit":10}   (GET /api/payments/mpesa-results)
 */
const BASE_URL = (process.env.BASE_URL || "http://localhost/Kingsway").replace(/\/+$/, "");
const USERNAME = process.env.USERNAME || "test_accountant";
const PASSWORD = process.env.PASSWORD || "Pass123!@";

const ACTION = process.argv[2];
let payload = {};
try {
  payload = process.argv[3] ? JSON.parse(process.argv[3]) : {};
} catch (e) {
  console.error("Invalid JSON argument:", process.argv[3]);
  process.exit(1);
}

const ENDPOINTS = {
  "stk-push": ["POST", "/api/payments/mpesa-stk-push"],
  "stk-query": ["POST", "/api/payments/mpesa-stk-query"],
  "c2b-register": ["POST", "/api/payments/mpesa-c2b-register"],
  "c2b-simulate": ["POST", "/api/payments/mpesa-c2b-simulate"],
  status: ["POST", "/api/payments/mpesa-transaction-status"],
  balance: ["POST", "/api/payments/mpesa-account-balance"],
  reversal: ["POST", "/api/payments/mpesa-reversal"],
  qr: ["POST", "/api/payments/mpesa-qr"],
  b2b: ["POST", "/api/payments/mpesa-b2b"],
  b2c: ["POST", "/api/payments/mpesa-b2c"],
  results: ["GET", "/api/payments/mpesa-results"],
};

async function main() {
  if (!ACTION || !ENDPOINTS[ACTION]) {
    console.error("Unknown action:", ACTION);
    console.error("Use one of:", Object.keys(ENDPOINTS).join(", "));
    process.exit(1);
  }

  // 1. Login exactly like the app does.
  const loginRes = await fetch(`${BASE_URL}/api/auth/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ username: USERNAME, password: PASSWORD, remember_me: false }),
  });
  const loginBody = await loginRes.json();
  const data = loginBody.data || loginBody;
  const token = data.token || data.access_token;
  const csrf = data.csrf_token;

  if (!loginRes.ok || !token) {
    console.error("Login failed", loginRes.status, JSON.stringify(loginBody, null, 2));
    process.exit(1);
  }

  // 2. Call the real endpoint over JSON with the same headers apiCall() uses.
  const [method, path] = ENDPOINTS[ACTION];
  const url =
    method === "GET"
      ? `${BASE_URL}${path}?limit=${encodeURIComponent(payload.limit || 10)}`
      : `${BASE_URL}${path}`;
  const res = await fetch(url, {
    method,
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`,
      ...(csrf ? { "X-CSRF-Token": csrf } : {}),
    },
    body: method === "POST" ? JSON.stringify(payload) : undefined,
  });
  const body = await res.json();

  console.log(JSON.stringify(
    {
      action: ACTION,
      endpoint: `${method} ${path}`,
      http_status: res.status,
      started_at: new Date().toISOString(),
      result: body,
    },
    null,
    2,
  ));
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
