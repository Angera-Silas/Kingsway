# Transport payments

Transport revenue is separate from school fees. A learner receives a date-bounded
transport entitlement (`day`, `week`, `month`, `term`, `year`, or `custom`) and
the scanner checks both the route assignment and the entitlement balance.

The transport fees page creates coverage first, then uses the payment channel:

- Cash is recorded and confirmed by authorized finance staff.
- Bank transfer and cheque create `manual_review` intents. Finance confirms them
  after the statement or cheque has cleared.
- Daraja M-Pesa creates a pending STK intent. It is allocated only after the
  verified STK callback confirms the checkout and amount.
- KCB Buni M-Pesa Express creates a pending intent and uses
  `/api/payments/kcb-mpesa-express-callback`; production callbacks require the
  configured KCB signature verification key.
- Bursaries and waivers create coverage without a cash payment.

Confirmed transport payments are allocated to
`transport_entitlement_payment_allocations`, never to the school-fee ledger.
The service then queues SMS, WhatsApp, and email confirmation through the
communications outbox. A provider acceptance response is not a confirmation.

## Internal student fund transfers

Finance staff may submit a request to move an available confirmed credit from
one student account to another, or between that student's fee and transport
accounts. The request records the parent ID, parent request reference, reason,
source account, destination account, and amount. An authorized finance
approver must approve it before posting.

Only unallocated credit can move:

- Fee source: an available/partially-applied `fee_credit_notes` balance.
- Transport source: confirmed entitlement allocations less prior transfer
  debits.

Posting debits the source and creates either a fee credit note or a confirmed
internal transport payment/allocation at the destination. Applied tuition or
transport charges cannot be silently moved, and the transfer is fully auditable
through `student_fund_transfers` and `student_fund_transfer_postings`.

Migrations: `066_transport_payment_intents.sql` and
`067_student_fund_transfers.sql`, `068_payment_routing_and_unmatched_cases.sql`,
and `069_transport_c2b_collection_channel.sql`.

Incoming routing uses `payment_collection_routes` and
`payment_routing_references`. The account destination and FEE/TRN reference
must agree. Conflicts, missing references, and unknown accounts are recorded
in `payment_unmatched_cases` and are resolved from the Payment Reconciliation
screen by an authorized finance user.

For a shared Lipa na M-Pesa/PayBill account, the supported human-entered
references are:

- `KA-2026-00125` (the learner's admission number) for school fees through the
  existing legacy fee path.
- `T-KA-2026-00125` for transport. The router strips `T-`, resolves the learner,
  selects the current transport entitlement, and records the payment there.

Portal-generated references such as `FEE-...` and `TRN-...` remain supported and
are preferred for STK, bank, and reconciliation workflows because they bind a
payment to a specific payment intent.
