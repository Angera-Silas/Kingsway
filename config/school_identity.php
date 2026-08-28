<?php

declare(strict_types=1);

namespace App\Config;

/** Populate legacy SCHOOL_* constants from canonical database records. */
function loadSchoolIdentity(?\PDO $db): void
{
    if (defined('SCHOOL_NAME')) {
        return;
    }

    $profile = [];
    $principalName = '';
    $principalTitle = '';

    if ($db !== null) {
        try {
            $profile = $db->query(
                "SELECT school_name, school_code, address, phone,
                        alternative_phone, email, motto, website, logo_url
                   FROM school_profile ORDER BY id LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC) ?: [];

            $leader = $db->query(
                "SELECT CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS principal_name,
                        s.position AS principal_title
                   FROM staff s
                   JOIN persons p ON p.id = s.person_id
                  WHERE LOWER(TRIM(COALESCE(s.position, ''))) IN ('headteacher', 'head teacher')
                  ORDER BY CASE WHEN LOWER(COALESCE(s.status, '')) = 'active' THEN 0 ELSE 1 END, s.id
                  LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            if (is_array($leader)) {
                $principalName = trim((string) ($leader['principal_name'] ?? ''));
                $principalTitle = trim((string) ($leader['principal_title'] ?? ''));
            }
        } catch (\Throwable $e) {
            error_log('[SchoolIdentity] Database identity lookup failed.');
        }
    }

    $phones = array_values(array_filter([
        trim((string) ($profile['phone'] ?? '')),
        trim((string) ($profile['alternative_phone'] ?? '')),
    ], static fn(string $value): bool => $value !== ''));

    define('SCHOOL_NAME', trim((string) ($profile['school_name'] ?? '')) ?: 'School');
    define('SCHOOL_CODE', trim((string) ($profile['school_code'] ?? '')) ?: 'SCHOOL');
    define('SCHOOL_ADDRESS', trim((string) ($profile['address'] ?? '')));
    define('SCHOOL_PHONE', implode(' / ', $phones));
    define('SCHOOL_EMAIL', trim((string) ($profile['email'] ?? '')));
    define('SCHOOL_PRINCIPAL_NAME', $principalName);
    define('SCHOOL_PRINCIPAL_TITLE', $principalTitle ?: 'Headteacher');
    define('SCHOOL_MOTTO', trim((string) ($profile['motto'] ?? '')));
    define('SCHOOL_WEBSITE', trim((string) ($profile['website'] ?? '')));
    define('SCHOOL_LOGO_PATH', trim((string) ($profile['logo_url'] ?? '')));
}
