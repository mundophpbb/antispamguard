# AntiSpam Guard Changelog

All notable changes to AntiSpam Guard are documented here.

## 3.3.23

### Fixed
- Fixed the StopForumSpam report shortcut in SFS log rows.
- The **Use for SFS report** action is now displayed when a StopForumSpam log row contains at least one useful value: IP address, email address, or username.
- Previously, the shortcut required IP, email, and username to be present at the same time, which made it unavailable for contact-form submissions or partial SFS records.

### Changed
- The manual StopForumSpam report form continues to validate the final submission before sending data to StopForumSpam.
- Missing required fields must still be completed manually by the administrator.

### Migration
- No database migration required.

## 3.3.22

### Added
- Added manual submission of confirmed spammers to StopForumSpam from the ACP.
- Added StopForumSpam API key support for manual/reporting workflows.
- Added an internal audit table for StopForumSpam submissions: `antispamguard_sfs_submit_log`.
- Added audit logging for manual StopForumSpam reports, including:
  - administrator responsible for the submission;
  - submitted IP address, email address, and username;
  - report source;
  - source SFS log ID, when available;
  - StopForumSpam response/status.
- Added a manual StopForumSpam submission panel in the ACP.
- Added pre-fill support from SFS log rows to the manual StopForumSpam submission form.
- Added confirmation before submitting data to StopForumSpam.
- Added ACP language strings in English, Portuguese, and French.

### Migration
- Adds the StopForumSpam submission audit table.

## 3.3.21

### Changed
- Refined the visual layout of ACP log pagination.
- Replaced compact bullet-separated pagination with a cleaner layout.
- Separated total counters, filtered counters, current page information, and navigation links.
- Added styled page buttons, current-page highlight, previous/next links, and ellipsis for long page ranges.
- Applied the same pagination style to:
  - general blocking logs;
  - StopForumSpam logs;
  - the StopForumSpam panel inside Blocking logs;
  - the dedicated StopForumSpam page.

### Migration
- No database migration required.

## 3.3.20

### Fixed
- Added duplicate protection to the dedicated StopForumSpam log table.
- Reuses an existing SFS log row when the same StopForumSpam decision is logged again within 5 seconds.
- Prevents inflated SFS statistics and repeated SFS log entries caused by duplicate writes.

### Migration
- Adds migration `v_3_3_20` to remove exact duplicate rows already stored in `antispamguard_sfs_log`.

## 3.3.19

### Fixed
- Prevented duplicate rows in the general blocking log when phpBB reaches the logger twice during the same submission.
- Added a 5-second duplicate guard based on IP address, username, email address, form type, reason, and user agent.

### Added
- Added independent StopForumSpam pagination support using the `sfs_start` parameter.
- StopForumSpam pagination is separated from the general blocking log pagination.

### Migration
- Adds migration `v_3_3_19` to remove exact duplicate rows already stored in `antispamguard_log`.

## 3.3.18

### Fixed
- Repaired the ACP Extensions tab/category after delete-data and reinstall cycles.
- Ensures the AntiSpam Guard ACP category is correctly placed under the global phpBB Extensions ACP category.
- Rebuilds ACP nested-set values when needed.

## Notes for administrators

- After updating from an older version, clear the phpBB cache.
- If updating to a version that includes migrations, run the normal phpBB extension database update process.
- StopForumSpam lookups work without an API key.
- The StopForumSpam API key is used for submitting/reporting confirmed spammers.
- Manual reporting should be used only for confirmed spam activity to avoid false reports.
