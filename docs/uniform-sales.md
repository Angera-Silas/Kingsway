# Uniform sales and payments

Uniforms are optional school-store purchases. They are not school fees, transport charges, or a student-fee ledger obligation. The store operator records the sale and stock movement; the director or school administrator controls the store account and collection routes.

## Payment flow

1. A sale is created in `uniform_sales` with its item, size, quantity and unit price.
2. The frontend creates a `uniform_payment_intents` row for a partial or full amount.
3. The request receives a unique `U-<admission>-<sale-id>-<random>` reference and is also registered in `payment_routing_references` with purpose `uniforms`.
4. Daraja STK, KCB/Buni Express, PayBill/Till, bank, cash and cheque are represented separately by channel.
5. Provider callbacks are routed only by the uniform reference or a configured collection account. They never credit fees or transport.
6. Confirmed payments create `uniform_payment_records`, update the sale status, and queue a receipt/payment notification.

Cash, bank and cheque entries remain `manual_review` until an authorised store/finance user confirms them. Online callbacks are idempotent and provider transaction references are retained.

## Same account and reconciliation

If the school uses the same M-Pesa or bank account for fees, transport and uniforms, the payer must use the generated `U-...` reference. Payments with a missing, expired, invalid or conflicting reference are held in the payment-reconciliation screen. Staff must select the exact uniform sale before allocation; the system does not guess from amount, name or phone number.

If the school uses a separate store account, configure a collection route with purpose `uniforms` and its account/till identifier. The reference is still preferred because it identifies the exact sale.

Migration: `database/migrations/070_uniform_sales_payment_routing.sql`.

## Store catalogue and parent checkout

`uniform_catalog_products` exposes approved inventory items and their `uniform_sizes` as a public catalogue. Product images are stored through `UploadService` under the uniform catalogue upload category and referenced in `uniform_catalog_images`; images are not stored as blobs in the database. Staff manage publication and images from the inventory/store workflow. The public catalogue is available at `uniform_catalog.php`, and the parent portal links to it.

Parents can add a size and quantity to their authenticated cart, save products to a wishlist, and use the accumulated checkout endpoint. The checkout creates an order/reference (`UC-...`) and one online payment request; the confirmed callback allocates the amount across the learner's outstanding uniform sales only. It never posts to school fees or transport.
