-- Seed the canonical database template catalogue from the former JSON templates.
INSERT INTO communication_template_catalog (code, name, purpose, status) VALUES
('sms.results', 'Student Results SMS', 'results', 'active'),
('sms.fees', 'Fees Payment Reminder SMS', 'fees', 'active'),
('sms.announcement', 'Announcement to Parents SMS', 'announcement', 'active'),
('sms.notification', 'Notification to Parents SMS', 'notification', 'active'),
('whatsapp.results', 'Student Results WhatsApp', 'results', 'active'),
('whatsapp.fees', 'Fees Payment Reminder WhatsApp', 'fees', 'active'),
('whatsapp.announcement', 'Announcement to Parents WhatsApp', 'announcement', 'active'),
('whatsapp.notification', 'Notification to Parents WhatsApp', 'notification', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name), purpose = VALUES(purpose), status = 'active';

INSERT INTO communication_template_versions (template_id, version_no, status)
SELECT id, 1, 'active' FROM communication_template_catalog
WHERE code IN ('sms.results','sms.fees','sms.announcement','sms.notification','whatsapp.results','whatsapp.fees','whatsapp.announcement','whatsapp.notification')
ON DUPLICATE KEY UPDATE status = 'active';

INSERT INTO communication_template_channels (template_version_id, channel, subject, body)
SELECT tv.id, 'sms', NULL,
       CASE c.code
           WHEN 'sms.results' THEN 'Kingsway Academy Results:\n{{student_name}}, {{term_name}}\n\nSummative\n{{summative_lines}}\nMean: GPA {{summative_gpa}}, GRADE {{summative_grade}}\n\nFormative Summary\n{{formative_lines}}\nMean: GPA {{formative_gpa}}, GRADE {{formative_grade}}\n\nAverage\n{{average_lines}}\nMean: GPA {{average_gpa}}, GRADE {{average_grade}}'
           WHEN 'sms.fees' THEN 'Kingsway Academy Fees Reminder:\nDear {{parent_name}},\nOutstanding Fees: KES {{amount_due}} for {{student_name}} (Class {{class_name}}).\nDue Date: {{due_date}}.\nPlease pay promptly to avoid interruption.'
           WHEN 'sms.announcement' THEN 'Kingsway Academy Announcement:\n{{announcement_body}}\n- {{school_name}}'
           ELSE 'Kingsway Academy Notification:\n{{notification_body}}\n- {{school_name}}'
       END
FROM communication_template_catalog c JOIN communication_template_versions tv ON tv.template_id = c.id AND tv.version_no = 1
WHERE c.code LIKE 'sms.%'
ON DUPLICATE KEY UPDATE body = VALUES(body), subject = VALUES(subject);

INSERT INTO communication_template_channels (template_version_id, channel, subject, body)
SELECT tv.id, 'whatsapp',
       CASE c.code
           WHEN 'whatsapp.results' THEN 'Kingsway Academy Results'
           WHEN 'whatsapp.fees' THEN 'Kingsway Academy Fees Reminder'
           WHEN 'whatsapp.announcement' THEN 'Kingsway Academy Announcement'
           ELSE 'Kingsway Academy Notification'
       END,
       CASE c.code
           WHEN 'whatsapp.results' THEN '<b>Kingsway Academy Results</b><br>{{student_name}}, {{term_name}}<br><br><b>Summative</b><br>{{summative_lines}}<br>Mean: GPA {{summative_gpa}}, GRADE {{summative_grade}}<br><br><b>Formative Summary</b><br>{{formative_lines}}<br>Mean: GPA {{formative_gpa}}, GRADE {{formative_grade}}<br><br><b>Average</b><br>{{average_lines}}<br>Mean: GPA {{average_gpa}}, GRADE {{average_grade}}'
           WHEN 'whatsapp.fees' THEN '<b>Kingsway Academy Fees Reminder</b><br>Dear {{parent_name}},<br>Outstanding Fees: KES {{amount_due}} for {{student_name}} (Class {{class_name}}).<br>Due Date: {{due_date}}.<br>Please pay promptly to avoid interruption.'
           WHEN 'whatsapp.announcement' THEN '<b>Kingsway Academy Announcement</b><br>{{announcement_body}}<br>- {{school_name}}'
           ELSE '<b>Kingsway Academy Notification</b><br>{{notification_body}}<br>- {{school_name}}'
       END
FROM communication_template_catalog c JOIN communication_template_versions tv ON tv.template_id = c.id AND tv.version_no = 1
WHERE c.code LIKE 'whatsapp.%'
ON DUPLICATE KEY UPDATE body = VALUES(body), subject = VALUES(subject);

INSERT INTO communication_template_variables (template_channel_id, variable_name, data_type)
SELECT tc.id, v.variable_name, 'string'
FROM communication_template_channels tc
JOIN communication_template_versions tv ON tv.id = tc.template_version_id
JOIN communication_template_catalog c ON c.id = tv.template_id
JOIN (
    SELECT 'student_name' AS variable_name UNION ALL SELECT 'term_name' UNION ALL SELECT 'summative_lines' UNION ALL SELECT 'summative_gpa' UNION ALL SELECT 'summative_grade' UNION ALL SELECT 'formative_lines' UNION ALL SELECT 'formative_gpa' UNION ALL SELECT 'formative_grade' UNION ALL SELECT 'average_lines' UNION ALL SELECT 'average_gpa' UNION ALL SELECT 'average_grade'
) v ON c.purpose = 'results'
ON DUPLICATE KEY UPDATE data_type = VALUES(data_type);

INSERT INTO communication_template_variables (template_channel_id, variable_name, data_type)
SELECT tc.id, v.variable_name, 'string'
FROM communication_template_channels tc
JOIN communication_template_versions tv ON tv.id = tc.template_version_id
JOIN communication_template_catalog c ON c.id = tv.template_id
JOIN (
    SELECT 'parent_name' AS variable_name UNION ALL SELECT 'amount_due' UNION ALL SELECT 'student_name' UNION ALL SELECT 'class_name' UNION ALL SELECT 'due_date'
) v ON c.purpose = 'fees'
ON DUPLICATE KEY UPDATE data_type = VALUES(data_type);

INSERT INTO communication_template_variables (template_channel_id, variable_name, data_type)
SELECT tc.id, v.variable_name, 'string'
FROM communication_template_channels tc
JOIN communication_template_versions tv ON tv.id = tc.template_version_id
JOIN communication_template_catalog c ON c.id = tv.template_id
JOIN (
    SELECT 'announcement_body' AS variable_name UNION ALL SELECT 'school_name'
) v ON c.purpose = 'announcement'
ON DUPLICATE KEY UPDATE data_type = VALUES(data_type);

INSERT INTO communication_template_variables (template_channel_id, variable_name, data_type)
SELECT tc.id, v.variable_name, 'string'
FROM communication_template_channels tc
JOIN communication_template_versions tv ON tv.id = tc.template_version_id
JOIN communication_template_catalog c ON c.id = tv.template_id
JOIN (
    SELECT 'notification_body' AS variable_name UNION ALL SELECT 'school_name'
) v ON c.purpose = 'notification'
ON DUPLICATE KEY UPDATE data_type = VALUES(data_type);
