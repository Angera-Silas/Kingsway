CREATE TABLE IF NOT EXISTS communication_business_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_code VARCHAR(100) NOT NULL,
    event_key VARCHAR(190) NOT NULL,
    status ENUM('created','processed','failed','cancelled') NOT NULL DEFAULT 'created',
    occurred_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comm_business_event (event_code, event_key),
    KEY idx_comm_business_event_status (status, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_event_exam_workflows (
    event_id BIGINT UNSIGNED NOT NULL,
    workflow_instance_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (event_id, workflow_instance_id),
    CONSTRAINT fk_comm_event_exam_event FOREIGN KEY (event_id) REFERENCES communication_business_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_event_school_events (
    event_id BIGINT UNSIGNED NOT NULL,
    school_event_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (event_id, school_event_id),
    CONSTRAINT fk_comm_event_school_event FOREIGN KEY (event_id) REFERENCES communication_business_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_comm_event_school_source FOREIGN KEY (school_event_id) REFERENCES school_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_event_fee_students (
    event_id BIGINT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    reminder_window ENUM('upcoming','due','overdue','manual') NOT NULL,
    PRIMARY KEY (event_id, student_id),
    CONSTRAINT fk_comm_event_fee_event FOREIGN KEY (event_id) REFERENCES communication_business_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_event_inquiries (
    event_id BIGINT UNSIGNED NOT NULL,
    inquiry_id INT NOT NULL,
    PRIMARY KEY (event_id, inquiry_id),
    CONSTRAINT fk_comm_event_inquiry_event FOREIGN KEY (event_id) REFERENCES communication_business_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_event_messages (
    event_id BIGINT UNSIGNED NOT NULL,
    internal_message_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (event_id, internal_message_id),
    CONSTRAINT fk_comm_event_message_event FOREIGN KEY (event_id) REFERENCES communication_business_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_comm_event_message_source FOREIGN KEY (internal_message_id) REFERENCES internal_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE communications
    ADD COLUMN business_event_id BIGINT UNSIGNED NULL AFTER thread_id,
    ADD KEY idx_comm_business_event (business_event_id),
    ADD CONSTRAINT fk_comm_business_event FOREIGN KEY (business_event_id) REFERENCES communication_business_events(id) ON DELETE SET NULL;

INSERT INTO communication_template_catalog (code, name, purpose, status) VALUES
('email.results', 'Student Results Email', 'results', 'active'),
('email.fees', 'Fees Payment Reminder Email', 'fees', 'active'),
('email.parent_event', 'Parent Event Invitation Email', 'parent_event', 'active'),
('email.inquiry_reply', 'Public Inquiry Reply Email', 'inquiry_reply', 'active'),
('sms.parent_event', 'Parent Event Invitation SMS', 'parent_event', 'active'),
('whatsapp.parent_event', 'Parent Event Invitation WhatsApp', 'parent_event', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name), purpose = VALUES(purpose), status = 'active';

INSERT INTO communication_template_versions (template_id, version_no, status)
SELECT id, 1, 'active' FROM communication_template_catalog
WHERE code IN ('email.results','email.fees','email.parent_event','email.inquiry_reply','sms.parent_event','whatsapp.parent_event')
ON DUPLICATE KEY UPDATE status = 'active';

INSERT INTO communication_template_channels (template_version_id, channel, subject, body)
SELECT tv.id, 'email',
       CASE c.code
           WHEN 'email.results' THEN 'Kingsway Academy Results - {{student_name}}'
           WHEN 'email.fees' THEN 'Kingsway Academy Fee Reminder - {{student_name}}'
           WHEN 'email.parent_event' THEN '{{event_title}} - Kingsway Academy'
           ELSE 'Response from Kingsway Academy: {{inquiry_subject}}'
       END,
       CASE c.code
           WHEN 'email.results' THEN '<p>Dear Parent/Guardian,</p><p>The results for <strong>{{student_name}}</strong> for {{term_name}} are now available.</p><p>{{results_summary}}</p><p>Please log in to the parent portal to view the complete report.</p>'
           WHEN 'email.fees' THEN '<p>Dear {{parent_name}},</p><p>This is a reminder that <strong>KES {{amount_due}}</strong> is outstanding for {{student_name}} ({{class_name}}).</p><p>Due date: {{due_date}}</p>'
           WHEN 'email.parent_event' THEN '<p>Dear Parent/Guardian,</p><p>You are invited to <strong>{{event_title}}</strong>.</p><p>Date: {{event_date}}<br>Time: {{event_time}}<br>Venue: {{event_venue}}</p><p>{{event_description}}</p>'
           ELSE '<p>Dear {{inquirer_name}},</p><p>Thank you for contacting Kingsway Academy regarding <strong>{{inquiry_subject}}</strong>.</p><p>{{reply_body}}</p><p>Kind regards,<br>Kingsway Academy</p>'
       END
FROM communication_template_catalog c JOIN communication_template_versions tv ON tv.template_id = c.id AND tv.version_no = 1
WHERE c.code LIKE 'email.%'
ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body);

INSERT INTO communication_template_channels (template_version_id, channel, subject, body)
SELECT tv.id, 'sms', 'Kingsway Academy Event: {{event_title}}', '{{event_title}} on {{event_date}} at {{event_time}}. Venue: {{event_venue}}. {{event_description}}'
FROM communication_template_catalog c JOIN communication_template_versions tv ON tv.template_id = c.id AND tv.version_no = 1
WHERE c.code = 'sms.parent_event'
ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body);

INSERT INTO communication_template_channels (template_version_id, channel, subject, body)
SELECT tv.id, 'whatsapp', 'Kingsway Academy Event: {{event_title}}', '<b>{{event_title}}</b><br>Date: {{event_date}}<br>Time: {{event_time}}<br>Venue: {{event_venue}}<br>{{event_description}}'
FROM communication_template_catalog c JOIN communication_template_versions tv ON tv.template_id = c.id AND tv.version_no = 1
WHERE c.code = 'whatsapp.parent_event'
ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body);
