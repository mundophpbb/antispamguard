<?php
/**
 * AntiSpam Guard common language file.
 */

if (!defined('IN_PHPBB'))
{
    exit;
}

$lang = array_merge($lang, array(
    'ANTISPAMGUARD_HP_LABEL'     => 'Leave this field empty',
    'ANTISPAMGUARD_BLOCKED'      => 'Your submission could not be accepted.',
    'ANTISPAMGUARD_BLOCKED_GENERIC' => 'Your submission could not be accepted. Please review the information and try again.',
    'ANTISPAMGUARD_BLOCKED_CONTENT' => 'The submission could not be accepted because it contains suspicious content.',
    'ANTISPAMGUARD_BLOCKED_TIME' => 'Your submission could not be accepted at this time.',
    'ANTISPAMGUARD_BLOCKED_RATE_LIMIT' => 'Too many attempts were detected from your IP. Please wait a few minutes and try again.',
    'ANTISPAMGUARD_BLOCKED_IP' => 'The submission could not be accepted from this IP address.',
));
