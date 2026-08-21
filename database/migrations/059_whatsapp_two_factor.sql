ALTER TABLE users MODIFY two_factor_method ENUM('totp','email','sms','whatsapp','none') NULL;
ALTER TABLE user_2fa_otp_sessions MODIFY method ENUM('email','sms','whatsapp') NOT NULL;
ALTER TABLE message_templates MODIFY type ENUM('email','sms','notification','whatsapp') NOT NULL;
