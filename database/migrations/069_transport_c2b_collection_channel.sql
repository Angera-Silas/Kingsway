ALTER TABLE transport_payment_intents
    MODIFY channel ENUM('daraja_mpesa','buni_mpesa','c2b_mpesa','bank_transfer','cash','cheque') NOT NULL;
