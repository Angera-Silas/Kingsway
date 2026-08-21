# Learner QR scanning

The QR scanner is a staff-operated workflow for school-issued learner ID cards. Learners do not log in or operate the scanner.

## Supported devices

- Phone/tablet camera: open `home.php?route=qr-scanner` over HTTPS, grant camera permission, and choose **Camera**.
- USB, Bluetooth, or desktop QR readers: these normally operate as keyboard-wedge devices. Choose **USB Scanner**, focus the input, and scan. The reader should append Enter.
- Manual entry is a controlled fallback for card numbers or previously issued credentials.

The browser submits scans to `POST /api/scan/verify`. The endpoint requires an authenticated staff user and validates the card server-side. QR images contain only an opaque credential (`KWA1.…`); names, balances, and portal URLs are not trusted from the image.

## Transport workflow

Choose **Transport**, then select **Verify assignment**, **Record boarding**, or **Record drop-off**, and the trip session. A boarding/drop-off scan writes to `student_transport_attendance`. The existing unique key `(student_id, attendance_date, trip_session)` makes repeated scans safe and the durable `qr_scan_events` table records accepted and rejected attempts.

Transport subscription billing is intentionally separate from school-fee billing. The scanner shows route/assignment status, not fee balances or parent contact details. Transport financial administration remains with the director/school administrator.

Transport access is date-bounded. A learner may be covered for one day, week, month, term, year (including Terms 1–3), bursary or approved waiver. The scanner checks the entitlement whose period contains the scan date and verifies its payment allocations. An assignment without coverage is denied boarding; a rejected attempt is audited. A drop-off is allowed only when the learner was previously recorded as boarded.

## Operations checklist

1. Issue an active learner ID card and generate its QR code from the student ID-card workflow.
2. Revoke or mark a lost card immediately; inactive cards are rejected by the API.
3. Use HTTPS for phone camera access and do not expose the API without JWT authentication.
4. Keep the scanner page open only for authorized staff; do not use a learner-facing portal.
5. Review `qr_scan_events` and `student_transport_attendance` when reconciling a trip.
