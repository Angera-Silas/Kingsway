-- Normalise school configuration: replace the legacy `school_configuration`
-- wide table and the bloated `school_settings` key-value store with clean,
-- single-purpose tables.

-- ═══════════════════════════════════════════════════════════════════════════
-- 1. CREATE `school_profile` — single-row school identity table
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS school_profile (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_name         VARCHAR(255) NOT NULL DEFAULT '',
  school_code         VARCHAR(50)  DEFAULT NULL,
  logo_url            VARCHAR(500) DEFAULT NULL,
  favicon_url         VARCHAR(500) DEFAULT NULL,
  motto               VARCHAR(500) DEFAULT NULL,
  vision              TEXT         DEFAULT NULL,
  mission             TEXT         DEFAULT NULL,
  about_us            TEXT         DEFAULT NULL,
  email               VARCHAR(255) DEFAULT NULL,
  phone               VARCHAR(50)  DEFAULT NULL,
  alternative_phone   VARCHAR(50)  DEFAULT NULL,
  address             TEXT         DEFAULT NULL,
  city                VARCHAR(100) DEFAULT NULL,
  country             VARCHAR(100) DEFAULT 'Kenya',
  postal_code         VARCHAR(20)  DEFAULT NULL,
  website             VARCHAR(255) DEFAULT NULL,
  social_facebook     VARCHAR(255) DEFAULT NULL,
  social_twitter      VARCHAR(255) DEFAULT NULL,
  social_instagram    VARCHAR(255) DEFAULT NULL,
  social_youtube      VARCHAR(255) DEFAULT NULL,
  social_whatsapp     VARCHAR(50)  DEFAULT NULL,
  google_maps_url     VARCHAR(500) DEFAULT NULL,
  established_year    YEAR         DEFAULT NULL,
  office_hours_weekday VARCHAR(100) DEFAULT NULL,
  office_hours_saturday VARCHAR(100) DEFAULT NULL,
  timezone            VARCHAR(50)  DEFAULT 'Africa/Nairobi',
  currency            VARCHAR(10)  DEFAULT 'KES',
  created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════
-- 2. MIGRATE DATA from school_configuration → school_profile
-- ═══════════════════════════════════════════════════════════════════════════
INSERT INTO school_profile (
  school_name, school_code, logo_url, favicon_url, motto, vision, mission,
  about_us, email, phone, alternative_phone, address, city, country,
  postal_code, website, social_facebook, social_twitter, social_instagram,
  social_youtube, social_whatsapp, google_maps_url, established_year,
  timezone, currency
)
SELECT
  school_name, school_code, logo_url, favicon_url, motto, vision, mission,
  about_us, email, phone, alternative_phone, address, city,
  COALESCE(country, 'Kenya'), postal_code, website,
  facebook_url, twitter_url, instagram_url, youtube_url, NULL, NULL,
  established_year,
  COALESCE(timezone, 'Africa/Nairobi'), COALESCE(currency, 'KES')
FROM school_configuration
WHERE id = 1
ON DUPLICATE KEY UPDATE school_name = VALUES(school_name);

-- Merge profile keys from school_settings into school_profile
-- (only if school_profile row exists and settings have newer data)
UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'school_name' LIMIT 1) s ON 1
SET sp.school_name = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'school_motto' LIMIT 1) s ON 1
SET sp.motto = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'school_phone' LIMIT 1) s ON 1
SET sp.phone = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'school_email' LIMIT 1) s ON 1
SET sp.email = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'school_address' LIMIT 1) s ON 1
SET sp.address = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'school_founded_year' LIMIT 1) s ON 1
SET sp.established_year = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'school_phone_main' LIMIT 1) s ON 1
SET sp.phone = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'school_phone_alt' LIMIT 1) s ON 1
SET sp.alternative_phone = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'school_email_main' LIMIT 1) s ON 1
SET sp.email = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'school_address_physical' LIMIT 1) s ON 1
SET sp.city = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

-- school_address_postal is a full address string, not a postal code — skip mapping

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'social_facebook' LIMIT 1) s ON 1
SET sp.social_facebook = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'social_twitter' LIMIT 1) s ON 1
SET sp.social_twitter = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'social_instagram' LIMIT 1) s ON 1
SET sp.social_instagram = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'social_youtube' LIMIT 1) s ON 1
SET sp.social_youtube = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'social_whatsapp' LIMIT 1) s ON 1
SET sp.social_whatsapp = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'google_maps_url' LIMIT 1) s ON 1
SET sp.google_maps_url = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'office_hours_weekday' LIMIT 1) s ON 1
SET sp.office_hours_weekday = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

UPDATE school_profile sp
  JOIN (SELECT setting_value FROM school_settings WHERE setting_key = 'office_hours_saturday' LIMIT 1) s ON 1
SET sp.office_hours_saturday = s.setting_value
WHERE sp.id = 1 AND s.setting_value != '' AND s.setting_value IS NOT NULL;

-- ═══════════════════════════════════════════════════════════════════════════
-- 3. DELETE DEAD ROWS from school_settings
-- ═══════════════════════════════════════════════════════════════════════════
-- Hardcoded stats (already computed live by WebsiteManager::getStats)
DELETE FROM school_settings WHERE setting_key IN (
  'stat_students', 'stat_students_suffix',
  'stat_teachers', 'stat_teachers_suffix',
  'hero_stat_1_value', 'hero_stat_4_value',
  'careers_stat_staff'
);

-- Spaces (explicitly dropped by code — uniform 'Available')
DELETE FROM school_settings WHERE setting_key IN (
  'spaces_PP1', 'spaces_PP2', 'spaces_Grade1',
  'spaces_Grade2_3', 'spaces_Grade4_6', 'spaces_Grade7_9'
);

-- Fee marketing text (no code reads these)
DELETE FROM school_settings WHERE setting_key IN (
  'fees_day_from', 'fees_boarding_from',
  'fees_day_label', 'fees_day_grades',
  'fees_boarding_label', 'fees_boarding_grades'
);

-- Legacy duplicate keys (now in school_profile)
DELETE FROM school_settings WHERE setting_key IN (
  'school_name', 'school_phone', 'school_email', 'school_address',
  'school_motto', 'school_founded_year', 'school_tagline',
  'school_phone_main', 'school_phone_alt', 'school_email_main',
  'school_email_admissions', 'school_email_finance',
  'school_email_academic', 'school_email_boarding',
  'school_address_physical', 'school_address_postal',
  'office_hours_weekday', 'office_hours_saturday',
  'social_facebook', 'social_twitter', 'social_instagram',
  'social_youtube', 'social_whatsapp', 'google_maps_url'
);

-- Dead system keys (managed elsewhere)
DELETE FROM school_settings WHERE setting_key IN (
  'system.maintenance.enabled', 'system.environment',
  'system.default_rate_limit', 'security.remember_me_days'
);

-- ═══════════════════════════════════════════════════════════════════════════
-- 4. DROP school_configuration (legacy — now replaced by school_profile)
-- ═══════════════════════════════════════════════════════════════════════════
DROP TABLE IF EXISTS school_configuration;

-- ═══════════════════════════════════════════════════════════════════════════
-- 5. ADD category column to school_events
-- ═══════════════════════════════════════════════════════════════════════════
ALTER TABLE school_events
  ADD COLUMN category ENUM(
    'academic','examination','holiday','administrative',
    'meeting','admission','public_holiday','other'
  ) DEFAULT 'other' AFTER type;

-- Backfill categories from existing type values
UPDATE school_events SET category = 'examination'    WHERE type = 'exam';
UPDATE school_events SET category = 'public_holiday' WHERE type = 'public_holiday';
UPDATE school_events SET category = 'holiday'        WHERE type = 'school_holiday';
UPDATE school_events SET category = 'meeting'        WHERE type = 'meeting';
UPDATE school_events SET category = 'admission'      WHERE type LIKE 'admission%';
UPDATE school_events SET category = 'academic'       WHERE type IN ('term_start','term_end','half_term','opening_day','closing_day');
UPDATE school_events SET category = 'other'          WHERE category = 'other' AND type NOT IN ('exam','public_holiday','school_holiday','meeting');

-- ═══════════════════════════════════════════════════════════════════════════
-- 6. DROP principal_name from school_profile (leaders come from staff table)
-- ═══════════════════════════════════════════════════════════════════════════
ALTER TABLE school_profile DROP COLUMN IF EXISTS principal_name;

-- Delete static stat_years (now computed: YEAR(CURDATE()) - established_year)
DELETE FROM school_settings WHERE setting_key = 'stat_years';

-- ═══════════════════════════════════════════════════════════════════════════
-- 7. LINK leadership_team to staff (single source of truth)
-- ═══════════════════════════════════════════════════════════════════════════
ALTER TABLE leadership_team ADD COLUMN staff_id INT UNSIGNED NULL AFTER id;
ALTER TABLE leadership_team ADD CONSTRAINT fk_leadership_staff
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL;

-- Link existing entries to actual staff records
UPDATE leadership_team SET staff_id = (SELECT id FROM staff WHERE position = 'Director' AND staff_no = 'KPST#2' LIMIT 1) WHERE id = 1;
UPDATE leadership_team SET staff_id = (SELECT id FROM staff WHERE position = 'Headteacher' AND staff_no = 'KPST#3' LIMIT 1) WHERE id = 2;
UPDATE leadership_team SET staff_id = (SELECT id FROM staff WHERE position = 'Deputy Headteacher' AND staff_no = 'KPST#4' LIMIT 1) WHERE id = 3;
UPDATE leadership_team SET staff_id = (SELECT id FROM staff WHERE position = 'Deputy Headteacher' AND staff_no = 'KPST#5' LIMIT 1) WHERE id = 4;
UPDATE leadership_team SET staff_id = (SELECT id FROM staff WHERE position = 'Accountant' AND staff_no = 'KPST#21' LIMIT 1) WHERE id = 5;
