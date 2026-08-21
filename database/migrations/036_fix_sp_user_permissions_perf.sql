-- 036_fix_sp_user_permissions_perf.sql
--
-- Performance fix for sp_user_get_effective_permissions.
--
-- The old body ran `SELECT DISTINCT permission_code FROM v_user_permissions_effective
-- WHERE user_id = p_user_id`. Because the view is a UNION of role-permissions and
-- direct-permissions, MariaDB materialises the full merged set (all users) before
-- applying the user filter, making every authenticated request take several seconds.
--
-- The new body pushes the user predicate into each branch so the query uses the
-- existing (user_id) indexes (uk_user_role, unique_user_perm) and no longer scans
-- every user's permissions.

DROP PROCEDURE IF EXISTS `sp_user_get_effective_permissions`;
DELIMITER $$
CREATE PROCEDURE `sp_user_get_effective_permissions`(IN `p_user_id` INT)
BEGIN
    SELECT DISTINCT p.`code`
    FROM `user_roles` ur
    JOIN `role_permissions` rp ON rp.`role_id` = ur.`role_id`
    JOIN `permissions` p ON p.`id` = rp.`permission_id`
    WHERE ur.`user_id` = p_user_id

    UNION

    SELECT DISTINCT p.`code`
    FROM `user_permissions` up
    JOIN `permissions` p ON p.`id` = up.`permission_id`
    WHERE up.`user_id` = p_user_id
      AND up.`permission_type` IN ('grant','override')
      AND (up.`expires_at` IS NULL OR up.`expires_at` > CURRENT_TIMESTAMP());
END$$
DELIMITER ;
