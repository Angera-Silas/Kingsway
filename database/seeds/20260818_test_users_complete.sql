/*
 * Complete synthetic test-user profiles without touching non-test users.
 * Values are explicitly marked TEST and are suitable for integration tests;
 * they are not real identity, payroll, or banking records.
 */
START TRANSACTION;

UPDATE users
SET password_changed_at = COALESCE(password_changed_at, '2026-01-01 09:00:00'),
    profile_completed_at = COALESCE(profile_completed_at, CURRENT_TIMESTAMP),
    force_password_change = 0,
    failed_login_attempts = 0,
    two_factor_method = COALESCE(two_factor_method, 'none')
WHERE is_test_user = 1;

UPDATE persons p
JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
SET p.dob = COALESCE(p.dob, DATE_SUB('1990-01-15', INTERVAL (u.id * 37) DAY)),
    p.gender = COALESCE(p.gender, CASE WHEN MOD(u.id, 2) = 0 THEN 'female' ELSE 'male' END),
    p.national_id_no = COALESCE(p.national_id_no, CONCAT('TEST-KPS-ID-', LPAD(u.id, 4, '0'))),
    p.email = COALESCE(NULLIF(p.email, ''), CONCAT(u.username, '@test.kingsway.local')),
    p.phone = COALESCE(NULLIF(p.phone, ''), CONCAT('+254700', LPAD(u.id, 6, '0'))),
    p.photo_url = COALESCE(NULLIF(p.photo_url, ''), '/Kingsway/uploads/staff/profile_pictures/staff_avatar.jpeg')
WHERE u.is_test_user = 1;

/* The school administrator and inventory manager are school-domain staff.
   The system administrator remains a SYSTEM-domain account only. */
INSERT INTO staff
    (id, person_id, staff_no, staff_type_id, staff_category_id, position,
     contract_type, employment_date, status, salary, bank_name, bank_account)
SELECT 110, p.id, 'KWPS045', 3, 17, 'School Administrator', 'permanent', '2026-01-02',
       'active', 65000.00, 'KCB Bank', 'TEST-KCB-000045'
FROM users u JOIN persons p ON p.id = u.person_id
WHERE u.username = 'test_scholadmin'
  AND u.is_test_user = 1
  AND NOT EXISTS (SELECT 1 FROM staff s WHERE s.person_id = p.id);

INSERT INTO staff
    (id, person_id, staff_no, staff_type_id, staff_category_id, position,
     contract_type, employment_date, status, salary, bank_name, bank_account)
SELECT 111, p.id, 'KWPS046', 3, 17, 'Inventory Manager', 'permanent', '2026-01-02',
       'active', 45000.00, 'KCB Bank', 'TEST-KCB-000046'
FROM users u JOIN persons p ON p.id = u.person_id
WHERE u.username = 'test_inventorymgr'
  AND u.is_test_user = 1
  AND NOT EXISTS (SELECT 1 FROM staff s WHERE s.person_id = p.id);

UPDATE staff s
JOIN persons p ON p.id = s.person_id
JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
SET s.position = CASE
        WHEN s.position IS NULL OR s.position = '' OR s.position = 'Staff'
            THEN CASE s.staff_category_id
                WHEN 4 THEN 'Class Teacher'
                WHEN 6 THEN 'Subject Teacher'
                WHEN 7 THEN 'Talent Development Coordinator'
                WHEN 8 THEN 'Intern Teacher'
                WHEN 9 THEN 'Driver'
                WHEN 10 THEN 'Cleaner'
                WHEN 12 THEN 'Security Officer'
                WHEN 13 THEN 'Cateress / Cook'
                WHEN 14 THEN 'Director'
                WHEN 15 THEN 'Headteacher'
                WHEN 16 THEN 'Deputy Headteacher'
                WHEN 18 THEN 'Accountant'
                WHEN 21 THEN 'Chaplain'
                WHEN 22 THEN 'Boarding Master'
                ELSE 'School Staff'
            END
        ELSE s.position
    END,
    s.salary = CASE WHEN s.salary IS NULL OR s.salary <= 1 THEN
        CASE s.staff_type_id WHEN 1 THEN 42000.00 WHEN 2 THEN 36000.00 ELSE 55000.00 END
        ELSE s.salary END,
    s.bank_name = COALESCE(NULLIF(s.bank_name, ''), 'KCB Bank'),
    s.bank_account = COALESCE(NULLIF(s.bank_account, ''), CONCAT('TEST-KCB-', LPAD(s.id, 6, '0'))),
    s.status = 'active',
    s.updated_at = CURRENT_TIMESTAMP
WHERE u.is_test_user = 1;

UPDATE staff s
JOIN persons p ON p.id = s.person_id
JOIN users u ON u.person_id = p.id
SET s.staff_category_id = 22,
    s.position = 'Boarding Master',
    s.updated_at = CURRENT_TIMESTAMP
WHERE u.username = 'test_boardingmaster' AND u.is_test_user = 1;

/* One communication and one billing contact, plus a primary address. */
INSERT INTO person_contact_points
    (person_id, channel, purpose, contact_value, is_primary, verified_at)
SELECT p.id, 'email', 'communication', p.email, 1, CURRENT_TIMESTAMP
FROM persons p JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
ON DUPLICATE KEY UPDATE contact_value = VALUES(contact_value), is_primary = 1, verified_at = CURRENT_TIMESTAMP;

INSERT INTO person_contact_points
    (person_id, channel, purpose, contact_value, is_primary, verified_at)
SELECT p.id, 'phone', 'communication', p.phone, 1, CURRENT_TIMESTAMP
FROM persons p JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
ON DUPLICATE KEY UPDATE contact_value = VALUES(contact_value), is_primary = 1, verified_at = CURRENT_TIMESTAMP;

INSERT INTO person_addresses
    (person_id, address_type, address_line, is_primary, valid_from)
SELECT p.id, 'residential', CONCAT('TEST HOUSE ', LPAD(u.id, 3, '0'), ', Kingsway Staff Estate, Nairobi'), 1, '2026-01-01'
FROM persons p JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
WHERE NOT EXISTS (
    SELECT 1 FROM person_addresses a WHERE a.person_id = p.id AND a.address_type = 'residential' AND a.is_primary = 1
);

INSERT INTO person_marital_statuses (person_id, marital_status, valid_from)
SELECT p.id, CASE WHEN MOD(u.id, 3) = 0 THEN 'married' ELSE 'single' END, '2026-01-01'
FROM persons p JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
WHERE NOT EXISTS (SELECT 1 FROM person_marital_statuses m WHERE m.person_id = p.id AND m.valid_to IS NULL);

SET @next_emergency_contact_id = (SELECT COALESCE(MAX(id), 0) FROM emergency_contacts);

INSERT INTO emergency_contacts (id, person_id, name, phone, relationship)
SELECT (@next_emergency_contact_id := @next_emergency_contact_id + 1), p.id,
       CONCAT('TEST EMERGENCY CONTACT ', LPAD(u.id, 3, '0')),
       CONCAT('+254711', LPAD(u.id, 6, '0')), 'guardian'
FROM persons p JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
WHERE NOT EXISTS (SELECT 1 FROM emergency_contacts e WHERE e.person_id = p.id);

/* Staff-only operational profiles. */
INSERT INTO staff_employment_profiles
    (staff_id, department_id, position, employment_date, contract_type, status)
SELECT s.id,
       CASE
           WHEN s.staff_type_id = 1 THEN 1
           WHEN s.staff_category_id = 9 THEN 2
           WHEN s.staff_category_id = 13 THEN 3
           WHEN s.staff_category_id = 7 THEN 7
           WHEN s.staff_category_id = 21 THEN 6
           WHEN s.staff_category_id = 22 THEN 1003
           ELSE 4
       END,
       s.position, s.employment_date, s.contract_type, 'active'
FROM staff s JOIN persons p ON p.id = s.person_id JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
ON DUPLICATE KEY UPDATE
    department_id = VALUES(department_id), position = VALUES(position),
    employment_date = VALUES(employment_date), contract_type = VALUES(contract_type),
    status = 'active', updated_at = CURRENT_TIMESTAMP;

SET @next_staff_assignment_id = (SELECT COALESCE(MAX(id), 0) FROM staff_department_assignments);

INSERT INTO staff_department_assignments
    (id, staff_id, department_id, role, effective_from)
SELECT (@next_staff_assignment_id := @next_staff_assignment_id + 1),
       ep.staff_id, ep.department_id, ep.position, ep.employment_date
FROM staff_employment_profiles ep
JOIN staff s ON s.id = ep.staff_id
JOIN persons p ON p.id = s.person_id
JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
WHERE NOT EXISTS (
    SELECT 1 FROM staff_department_assignments da
    WHERE da.staff_id = ep.staff_id AND da.department_id = ep.department_id AND da.effective_from = ep.employment_date
);

INSERT INTO staff_payroll_profiles
    (staff_id, basic_salary, bank_name, bank_account, kra_pin, nssf_no, nhif_no, status)
SELECT s.id, COALESCE(s.salary, 0), s.bank_name, s.bank_account,
       CONCAT('TEST-KRA-', LPAD(s.id, 6, '0')),
       CONCAT('TEST-NSSF-', LPAD(s.id, 6, '0')),
       CONCAT('TEST-SHIF-', LPAD(s.id, 6, '0')), 'active'
FROM staff s JOIN persons p ON p.id = s.person_id JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
ON DUPLICATE KEY UPDATE
    basic_salary = VALUES(basic_salary), bank_name = VALUES(bank_name), bank_account = VALUES(bank_account),
    kra_pin = VALUES(kra_pin), nssf_no = VALUES(nssf_no), nhif_no = VALUES(nhif_no),
    status = 'active', updated_at = CURRENT_TIMESTAMP;

INSERT INTO staff_attendance_profiles (staff_id, work_start_time, work_end_time, late_threshold_minutes, is_active)
SELECT s.id, '08:00:00', '17:00:00', 15, 1
FROM staff s JOIN persons p ON p.id = s.person_id JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
ON DUPLICATE KEY UPDATE is_active = 1, updated_at = CURRENT_TIMESTAMP;

INSERT INTO staff_experience
    (staff_id, organization, position, start_date, responsibilities)
SELECT s.id, 'TEST Kingsway Preparatory School', s.position, s.employment_date,
       CONCAT('Synthetic test experience for ', s.position, ' integration workflows.')
FROM staff s JOIN persons p ON p.id = s.person_id JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
WHERE NOT EXISTS (SELECT 1 FROM staff_experience e WHERE e.staff_id = s.id);

INSERT INTO staff_qualifications
    (staff_id, qualification_type, title, institution, year_obtained, description)
SELECT s.id,
       CASE WHEN s.staff_type_id = 1 THEN 'degree' ELSE 'certificate' END,
       CASE WHEN s.staff_type_id = 1 THEN 'Bachelor of Education (Test)' ELSE 'Professional Certificate (Test)' END,
       'TEST Kenya Training Institute', 2020,
       'Synthetic qualification record for integration and workflow testing.'
FROM staff s JOIN persons p ON p.id = s.person_id JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
WHERE NOT EXISTS (SELECT 1 FROM staff_qualifications q WHERE q.staff_id = s.id);

INSERT INTO staff_id_cards
    (staff_id, card_number, generated_by, generated_at, issued_by, issued_at, expires_at, status, metadata)
SELECT s.id, CONCAT('TEST-KPS-CARD-', LPAD(s.id, 6, '0')), 1, CURRENT_TIMESTAMP, 1, CURRENT_TIMESTAMP,
       '2027-12-31', 'issued', JSON_OBJECT('test_fixture', TRUE, 'source', '20260818_test_users_complete')
FROM staff s JOIN persons p ON p.id = s.person_id JOIN users u ON u.person_id = p.id AND u.is_test_user = 1
WHERE NOT EXISTS (SELECT 1 FROM staff_id_cards c WHERE c.staff_id = s.id);

COMMIT;
