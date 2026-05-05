-- ============================================================================
-- AntiSpam Guard - Emergency Full Removal SQL
-- Extension: mundophpbb/antispamguard
-- phpBB default table prefix used in this file: phpbb_
--
-- Purpose
-- -------
-- This script is intended to completely remove AntiSpam Guard database data
-- when the normal phpBB extension removal flow cannot be completed.
--
-- Normal removal should always be tried first:
--   ACP > Customise > Manage extensions > AntiSpam Guard > Disable > Delete data
--
-- Use this SQL only as a recovery/emergency procedure.
--
-- IMPORTANT SAFETY NOTES
-- ----------------------
-- 1. Back up the database before running this script.
-- 2. Make sure the table prefix is correct.
--    This file uses phpbb_. If your board uses another prefix, replace every
--    occurrence of phpbb_ with your actual database table prefix.
-- 3. Run this only for the extension path:
--      ext/mundophpbb/antispamguard
-- 4. After running this SQL, remove the extension files manually and clear the
--    phpBB cache.
-- 5. Do not run this while another extension install/update process is active.
--
-- Tested target: AntiSpam Guard 3.3.28 database objects and earlier migrations.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Step 1: Remove AntiSpam Guard data/runtime tables
-- ---------------------------------------------------------------------------
-- These tables store block logs, StopForumSpam cache/logs, local IP reputation,
-- IP rate-limit counters, slow-spam activity history, alerts and SFS submit logs.
-- DROP TABLE IF EXISTS is used so the script can be executed even if some tables
-- were never created, were already removed, or belong to an older/newer install.
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
-- Step 2: Remove AntiSpam Guard configuration values
-- ---------------------------------------------------------------------------
-- phpBB stores extension settings in the config table. AntiSpam Guard uses the
-- antispamguard_ prefix for its configuration keys.
-- ---------------------------------------------------------------------------

DELETE FROM phpbb_config
WHERE config_name LIKE 'antispamguard\_%' ESCAPE '\\';

-- ---------------------------------------------------------------------------
-- Step 3: Remove the extension registry entry
-- ---------------------------------------------------------------------------
-- This removes the extension from phpBB's extension registry table.
-- If this entry remains after files are removed, phpBB may still think the
-- extension exists or may show an inconsistent extension state.
-- ---------------------------------------------------------------------------

DELETE FROM phpbb_ext
WHERE ext_name = 'mundophpbb/antispamguard';

-- ---------------------------------------------------------------------------
-- Step 4: Remove migration history for AntiSpam Guard
-- ---------------------------------------------------------------------------
-- phpBB records executed migrations in phpbb_migrations. Removing these rows
-- prevents stale migration state from surviving after an emergency cleanup.
-- The namespace condition is intentionally specific to this extension.
-- ---------------------------------------------------------------------------

DELETE FROM phpbb_migrations
WHERE migration_name LIKE 'mundophpbb\\antispamguard\\migrations\\%'
   OR migration_name LIKE '%antispamguard%';

-- ---------------------------------------------------------------------------
-- Step 5: Remove ACP modules created by AntiSpam Guard
-- ---------------------------------------------------------------------------
-- This removes only ACP modules owned by the extension. It does not remove
-- generic phpBB parent categories such as ACP_CAT_DOT_MODS.
-- ---------------------------------------------------------------------------

DELETE FROM phpbb_modules
WHERE module_class = 'acp'
  AND (
        module_basename = '\\mundophpbb\\antispamguard\\acp\\main_module'
        OR module_basename LIKE '%antispamguard%'
        OR module_langname LIKE 'ACP_ANTISPAMGUARD%'
      );

-- ---------------------------------------------------------------------------
-- Step 6: Remove AntiSpam Guard ACP permission assignments
-- ---------------------------------------------------------------------------
-- AntiSpam Guard uses the administrator permission:
--   a_antispamguard_manage
--
-- The cleanup order matters:
-- 1. remove role assignments,
-- 2. remove user assignments,
-- 3. remove group assignments,
-- 4. remove the permission option itself.
-- ---------------------------------------------------------------------------

DELETE FROM phpbb_acl_roles_data
WHERE auth_option_id IN (
    SELECT auth_option_id
    FROM phpbb_acl_options
    WHERE auth_option = 'a_antispamguard_manage'
);

DELETE FROM phpbb_acl_users
WHERE auth_option_id IN (
    SELECT auth_option_id
    FROM phpbb_acl_options
    WHERE auth_option = 'a_antispamguard_manage'
);

DELETE FROM phpbb_acl_groups
WHERE auth_option_id IN (
    SELECT auth_option_id
    FROM phpbb_acl_options
    WHERE auth_option = 'a_antispamguard_manage'
);

DELETE FROM phpbb_acl_options
WHERE auth_option = 'a_antispamguard_manage';

-- ---------------------------------------------------------------------------
-- Step 7: Optional verification queries
-- ---------------------------------------------------------------------------
-- These SELECT statements are commented out by default. Uncomment and run them
-- after the cleanup if you want to verify that no AntiSpam Guard records remain.
-- ---------------------------------------------------------------------------

-- SELECT * FROM phpbb_ext WHERE ext_name = 'mundophpbb/antispamguard';
-- SELECT * FROM phpbb_config WHERE config_name LIKE 'antispamguard\_%' ESCAPE '\\';
-- SELECT * FROM phpbb_migrations WHERE migration_name LIKE '%antispamguard%';
-- SELECT * FROM phpbb_modules WHERE module_basename LIKE '%antispamguard%' OR module_langname LIKE 'ACP_ANTISPAMGUARD%';
-- SELECT * FROM phpbb_acl_options WHERE auth_option = 'a_antispamguard_manage';

-- ---------------------------------------------------------------------------
-- Step 8: Required manual cleanup after running this SQL
-- ---------------------------------------------------------------------------
-- 1. Delete the extension files from:
--      ext/mundophpbb/antispamguard
--
-- 2. Clear the phpBB cache manually.
--    In the phpBB cache directory, delete generated cache files but keep:
--      .htaccess
--      index.htm
--
-- 3. If the ACP is accessible afterwards, use:
--      ACP > General > Purge the cache
--
-- 4. Re-check:
--      ACP > Customise > Manage extensions
--    AntiSpam Guard should no longer appear as an installed/enabled extension.
-- ============================================================================
