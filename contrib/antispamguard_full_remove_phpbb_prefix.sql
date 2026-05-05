-- ============================================================================
-- AntiSpam Guard - Emergency full removal SQL
-- Target extension: mundophpbb/antispamguard
-- Default phpBB table prefix used below: phpbb_
--
-- IMPORTANT:
-- 1) Use this ONLY if the normal phpBB ACP uninstall/delete-data process fails.
-- 2) BACK UP the database before running this script.
-- 3) If your board uses another table prefix, replace every occurrence of phpbb_
--    with your real prefix before executing.
-- 4) After running it, delete the extension files from:
--      ext/mundophpbb/antispamguard
--    and clear phpBB cache manually.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Remove AntiSpam Guard runtime/data tables
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS phpbb_antispamguard_sfs_submit_log;
DROP TABLE IF EXISTS phpbb_antispamguard_alerts;
DROP TABLE IF EXISTS phpbb_antispamguard_activity_log;
DROP TABLE IF EXISTS phpbb_antispamguard_ip_rate;
DROP TABLE IF EXISTS phpbb_antispamguard_ip_score;
DROP TABLE IF EXISTS phpbb_antispamguard_sfs_log;
DROP TABLE IF EXISTS phpbb_antispamguard_sfs_cache;
DROP TABLE IF EXISTS phpbb_antispamguard_log;

-- ---------------------------------------------------------------------------
-- 2. Remove extension configuration values
-- ---------------------------------------------------------------------------
DELETE FROM phpbb_config
WHERE config_name LIKE 'antispamguard\_%' ESCAPE '\\';

-- ---------------------------------------------------------------------------
-- 3. Remove phpBB extension registry entry
-- ---------------------------------------------------------------------------
DELETE FROM phpbb_ext
WHERE ext_name = 'mundophpbb/antispamguard';

-- ---------------------------------------------------------------------------
-- 4. Remove migration history for the extension
-- ---------------------------------------------------------------------------
DELETE FROM phpbb_migrations
WHERE migration_name LIKE '%antispamguard%';

-- ---------------------------------------------------------------------------
-- 5. Remove ACP modules created by the extension
-- ---------------------------------------------------------------------------
DELETE FROM phpbb_modules
WHERE module_class = 'acp'
  AND (
        module_basename = '\\mundophpbb\\antispamguard\\acp\\main_module'
        OR module_langname LIKE 'ACP_ANTISPAMGUARD%'
      );

-- ---------------------------------------------------------------------------
-- 6. Remove permission data for a_antispamguard_manage
-- ---------------------------------------------------------------------------
DELETE FROM phpbb_acl_roles_data
WHERE auth_option_id IN (
    SELECT auth_option_id FROM phpbb_acl_options
    WHERE auth_option = 'a_antispamguard_manage'
);

DELETE FROM phpbb_acl_users
WHERE auth_option_id IN (
    SELECT auth_option_id FROM phpbb_acl_options
    WHERE auth_option = 'a_antispamguard_manage'
);

DELETE FROM phpbb_acl_groups
WHERE auth_option_id IN (
    SELECT auth_option_id FROM phpbb_acl_options
    WHERE auth_option = 'a_antispamguard_manage'
);

DELETE FROM phpbb_acl_options
WHERE auth_option = 'a_antispamguard_manage';

-- ---------------------------------------------------------------------------
-- 7. Optional cleanup note
-- ---------------------------------------------------------------------------
-- After executing this SQL:
-- - Remove files from ext/mundophpbb/antispamguard
-- - Delete phpBB cache files manually, except .htaccess and index.htm
-- - Then log in to ACP and purge cache again if possible
-- ============================================================================
