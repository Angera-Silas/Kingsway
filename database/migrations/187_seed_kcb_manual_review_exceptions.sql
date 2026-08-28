-- Surface pre-reconciliation KCB failures in the explicit exception queue.
INSERT INTO kcb_disbursement_exceptions
    (disbursement_id, exception_code, reason, status, first_detected_at, last_detected_at)
SELECT d.id,
       'historical_manual_review',
       CONCAT('Historical KCB failure requires statement/reference verification before retry. ', COALESCE(d.result_description, '')),
       'open',
       COALESCE(d.failed_at, d.created_at),
       NOW()
FROM disbursement_transactions d
LEFT JOIN kcb_disbursement_exceptions e ON e.disbursement_id = d.id
WHERE d.channel = 'kcb_bank'
  AND d.status = 'failed'
  AND d.reconciliation_status = 'manual_review'
  AND e.id IS NULL;
