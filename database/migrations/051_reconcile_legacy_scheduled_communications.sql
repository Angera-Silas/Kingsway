-- Legacy scheduled rows with no endpoint cannot be safely delivered. Preserve
-- them as failed records rather than guessing a recipient address.
UPDATE communications c
LEFT JOIN (
    SELECT DISTINCT r.communication_id
      FROM communication_recipients r
      JOIN communication_recipient_endpoints e ON e.communication_recipient_id = r.id
) valid_endpoints ON valid_endpoints.communication_id = c.id
SET c.status = 'failed',
    c.processed_at = COALESCE(c.processed_at, NOW()),
    c.last_error = 'Legacy scheduled communication had no resolvable recipient endpoint.'
WHERE c.status = 'scheduled'
  AND c.scheduled_at IS NOT NULL
  AND c.scheduled_at < NOW()
  AND valid_endpoints.communication_id IS NULL;

INSERT INTO communication_audit_events (communication_id, event_type, raw_payload)
SELECT c.id, 'legacy_scheduled_reconciled', 'recipient endpoint unavailable'
FROM communications c
WHERE c.status = 'failed'
  AND c.last_error = 'Legacy scheduled communication had no resolvable recipient endpoint.'
  AND NOT EXISTS (
      SELECT 1 FROM communication_audit_events a
      WHERE a.communication_id = c.id AND a.event_type = 'legacy_scheduled_reconciled'
  );
