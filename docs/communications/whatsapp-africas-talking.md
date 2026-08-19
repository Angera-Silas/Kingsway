# Africa's Talking WhatsApp and SMS callbacks

The application queues outbound messages in the communications outbox. The worker sends them and records the provider `messageId`; delivery callbacks update the endpoint and communication status.

## Callback URLs

Configure these HTTPS URLs in the Africa's Talking dashboard:

- SMS delivery reports: `https://kingswaypreparatoryschool.sc.ke/api/communications/sms-delivery-report`
- SMS incoming/subscription notifications: `https://kingswaypreparatoryschool.sc.ke/api/communications/sms-subscription-callback`
- SMS bulk opt-out: `https://kingswaypreparatoryschool.sc.ke/api/communications/sms-opt-out-callback`
- WhatsApp delivery reports: `https://kingswaypreparatoryschool.sc.ke/api/communications/whatsapp-delivery-report`
- WhatsApp incoming messages: `https://kingswaypreparatoryschool.sc.ke/api/communications/whatsapp-incoming`

If the provider dashboard supports a URL query token, append `?token=<AFRICASTALKING_WEBHOOK_TOKEN>` to each callback URL. The application also accepts `X-Kingsway-Webhook-Token`. Keep the token long and random; do not use the Africa's Talking API key as the callback token.

## WhatsApp payloads

Text messages use `body.message`. Media uses `body.mediaType`, `body.url`, and an optional `body.caption`. The outbox maps image/video/audio MIME types to the provider media types and sends PDFs and other files as `Document`. Media URLs must be public HTTPS URLs, so generated statements, receipts, result PDFs, and images must be produced by the printing service and exposed through the application's protected document-delivery URL before queueing.

Provider templates are created through `/api/communications/create-whatsapp-template`. After Africa's Talking returns `templateId`, it is stored in `communication_template_channels`; later sends use `bodyValues` and optional `headerValue`, not the legacy `parameters` field.

Inbound WhatsApp messages are stored in `external_inbound_messages`, linked to a parent when the phone number matches an active parent contact, and appended to a `communication_threads` parent conversation. Inbound media is normalized in `external_inbound_media`. A phone match does not authorize disclosure of balances, refunds, learner records, or results; those messages must link to the authenticated parent portal or be routed to staff.
