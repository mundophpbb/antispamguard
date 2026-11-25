<?php
/**
 * Lightweight regression checks. Run with: php tests/run.php
 */

if (!interface_exists('phpbb\\db\\driver\\driver_interface'))
{
	eval('namespace phpbb\\db\\driver; interface driver_interface {}');
}
if (!class_exists('phpbb\\config\\config'))
{
	eval('namespace phpbb\\config; class config extends \\ArrayObject { public function set($key, $value, $dynamic = false) { $this[$key] = $value; } }');
}
if (!class_exists('GuzzleHttp\\Client'))
{
	eval('namespace GuzzleHttp; class Client { public static $calls = array(); public static $responses = array(); public function request($method, $url, array $options = array()) { self::$calls[] = array("method" => $method, "url" => $url, "options" => $options); if (empty(self::$responses)) { throw new \\RuntimeException("No fake Guzzle response configured."); } return array_shift(self::$responses); } }');
}

require_once __DIR__ . '/../service/form_guard.php';
require_once __DIR__ . '/../service/ip_matcher.php';
require_once __DIR__ . '/../service/registration_policy.php';
require_once __DIR__ . '/../service/decision_engine.php';
require_once __DIR__ . '/../service/atomic_store.php';
require_once __DIR__ . '/../service/registration_audit.php';
require_once __DIR__ . '/../service/sfs_cache.php';
require_once __DIR__ . '/../service/stopforumspam_client.php';
require_once __DIR__ . '/../service/sfs_log.php';
require_once __DIR__ . '/../service/sfs_decision.php';
require_once __DIR__ . '/../service/ip_rate_limit.php';
require_once __DIR__ . '/../service/ip_reputation.php';
require_once __DIR__ . '/../acp/settings_helper.php';

use mundophpbb\antispamguard\acp\settings_helper;
use mundophpbb\antispamguard\service\atomic_store;
use mundophpbb\antispamguard\service\decision_engine;
use mundophpbb\antispamguard\service\form_guard;
use mundophpbb\antispamguard\service\ip_rate_limit;
use mundophpbb\antispamguard\service\ip_reputation;
use mundophpbb\antispamguard\service\registration_policy;
use mundophpbb\antispamguard\service\registration_audit;
use mundophpbb\antispamguard\service\sfs_cache;
use mundophpbb\antispamguard\service\sfs_decision;
use mundophpbb\antispamguard\service\sfs_log;
use mundophpbb\antispamguard\service\stopforumspam_client;

$tests = 0;
$failures = array();

function asg_assert($condition, $message)
{
	global $tests, $failures;
	$tests++;

	if (!$condition)
	{
		$failures[] = $message;
	}
}

$listener_source = file_get_contents(__DIR__ . '/../event/listener.php');
$main_module_source = file_get_contents(__DIR__ . '/../acp/main_module.php');
$migration_source = file_get_contents(__DIR__ . '/../migrations/v_0_1_0.php');
$atomic_migration_source = file_get_contents(__DIR__ . '/../migrations/v_3_3_64.php');
$log_repair_migration_source = file_get_contents(__DIR__ . '/../migrations/v_3_3_71.php');
$request_log_migration_source = file_get_contents(__DIR__ . '/../migrations/v_3_3_72.php');
$identity_grouping_migration_source = file_get_contents(__DIR__ . '/../migrations/v_3_3_73.php');
$sfs_log_source = file_get_contents(__DIR__ . '/../service/sfs_log.php');
asg_assert(strpos($listener_source, "'core.ucp_register_data_after'") !== false && strpos($listener_source, "'core.ucp_register_data_before'") === false, 'Registration validation must use the phpBB event that exposes the error array.');
asg_assert(strpos($listener_source, "'core.ucp_register_requests_after'") !== false && strpos($listener_source, "array('observe_registration_request', 1000)") !== false, 'Registration telemetry must observe the early phpBB request event before other validators can stop the late event.');
asg_assert(strpos($main_module_source, 'SHOW TABLES') === false && strpos($main_module_source, "get('dbal.tools')->sql_table_exists") !== false, 'ACP table diagnostics must use the portable phpBB DBAL tool.');
asg_assert(strpos($main_module_source, 'add_log(') === false && strpos($main_module_source, "get('log')->add(") !== false, 'ACP audit entries must use the phpBB logger service instead of deprecated add_log().');
asg_assert(strpos($main_module_source, "reason' => 'diagnostic_test'") !== false && strpos($main_module_source, "is_set_post('test_main_log')") !== false, 'ACP diagnostics must provide an independent main-log write test.');
asg_assert(strpos($migration_source, 'SUBSTRING(cache_key') === false, 'Legacy SFS cache conversion must not use database-specific SUBSTRING syntax.');
asg_assert((strpos($migration_source, 'sql_unique_index_exists($table, $index_name)') !== false || strpos($migration_source, 'sql_unique_index_exists($table, $name)') !== false), 'The consolidated migration must detect existing unique indexes with the dedicated phpBB DBAL helper.');
asg_assert((strpos($atomic_migration_source, 'sql_unique_index_exists($table, $index_name)') !== false || (strpos($atomic_migration_source, 'extends \\mundophpbb\\antispamguard\\migrations\\v_0_1_0') !== false && strpos($atomic_migration_source, 'add_unique_index_if_missing(') !== false)), 'The atomic-store migration must detect existing unique indexes with the dedicated phpBB DBAL helper.');
asg_assert(strpos($log_repair_migration_source, "'repair_schema'") !== false && strpos($log_repair_migration_source, "'ensure_config_defaults'") !== false, 'The 3.3.71 migration must repair log storage and missing runtime settings.');
asg_assert(strpos($request_log_migration_source, "'set_version'") !== false && strpos($request_log_migration_source, "'3.3.72', '>='") !== false && strpos($migration_source, "'submission_key'") !== false && strpos($migration_source, "'observed_actions'") !== false, 'The 3.3.72 migration must install request-safe log metadata, use its own effective-version threshold and persist the extension version.');
asg_assert(strpos($listener_source, "write_log('audit:' . \$reason, 'register'") !== false, 'The early registration observer must record review-only telemetry, never claim that it blocked the request.');
asg_assert(strpos($listener_source, "'submission_key' => \$this->get_submission_key") !== false, 'Main logs must correlate events with a per-request submission key.');
asg_assert(strpos($identity_grouping_migration_source, "'3.3.73', '>='") !== false && strpos($identity_grouping_migration_source, "'set_version'") !== false, 'The 3.3.73 migration must persist the stable-identity grouping release version.');
asg_assert(strpos($listener_source, 'find_recent_duplicate_log') !== false && strpos($listener_source, 'logs_share_stable_identity') !== false && strpos($listener_source, 'has_complete_log_identity') !== false, 'Main logs must fall back to a complete stable identity when different requests use different submission keys.');
asg_assert(strpos($listener_source, 'build_phpbb_rejection_decision_context') !== false && strpos($listener_source, "'external_outcome' => 'phpbb_rejected'") !== false && strpos($listener_source, "array_key_exists('matched_rules', \$decision_context)") !== false, 'phpBB rejection logs must keep external outcomes separate from AntiSpam Guard rules and store diagnostic details.');
asg_assert(strpos($sfs_log_source, 'has_same_stable_identity') !== false && strpos($sfs_log_source, "data['created_at'] - 60") !== false, 'SFS logs must group fully identified retries with different submission keys inside a short window.');
asg_assert(!file_exists(__DIR__ . '/../config/routing.yml'), 'The unused empty routing file should not be packaged.');
asg_assert(empty(glob(__DIR__ . '/../contrib/*.sql')) && empty(glob(__DIR__ . '/../contrib/edge-protection/*.example')), 'Auxiliary samples must use validator-recognized text filenames.');

$settings_helper = new settings_helper();
$csv_row = $settings_helper->sanitize_csv_row(array('=2+3', "\t+SUM(A1:A2)", '-10', '@cmd', 'safe'));
asg_assert($csv_row === array("'=2+3", "'\t+SUM(A1:A2)", "'-10", "'@cmd", 'safe'), 'CSV exports must neutralize spreadsheet formula prefixes, including after leading whitespace.');

$guard_config = array(
	'antispamguard_token_secret' => 'test-secret',
	'antispamguard_hp_dynamic_enabled' => 1,
	'antispamguard_hp_dynamic_prefix' => 'asg_hp',
	'antispamguard_min_seconds' => 3,
	'antispamguard_max_seconds' => 1800,
	'antispamguard_max_form_age' => 3600,
);
$guard = new form_guard($guard_config);
$timestamp = 1700000000;
$raw_ts = $timestamp . ':' . $guard->build_token($timestamp);
$honeypot = $guard->get_honeypot_name($timestamp);

asg_assert($guard->get_timestamp_block_reason($raw_ts, array(), $timestamp + 4) === '', 'A valid form token should pass.');
asg_assert($guard->get_timestamp_block_reason('', array(), $timestamp + 4) === 'timestamp', 'A missing token must be rejected.');
asg_assert($guard->passes_honeypot($raw_ts, array()) === false, 'A missing honeypot field must be rejected.');
asg_assert($guard->passes_honeypot($raw_ts, array($honeypot => '')) === true, 'An empty submitted honeypot should pass.');
asg_assert($guard->passes_honeypot($raw_ts, array($honeypot => 'bot')) === false, 'A filled honeypot must be rejected.');

$matcher = new \mundophpbb\antispamguard\service\ip_matcher();
asg_assert($matcher->normalize_list("192.168.*.*\n10.*.*.*\n203.0.113.5") === "192.168.*.*\n203.0.113.5", 'IP normalization must preserve safe wildcards and reject overly broad ones.');
asg_assert($matcher->whitelist_match('192.168.10.20', "203.0.113.1,192.168.*.*")['matched'] === true, 'Whitelist parsing must accept the same comma-separated wildcard syntax as runtime matching.');

$import_config = new \phpbb\config\config();
$import_result = $settings_helper->import_settings_json($import_config, json_encode(array('settings' => array(
	'antispamguard_enabled' => 'false',
	'antispamguard_sfs_enabled' => true,
	'antispamguard_decision_weight_honeypot' => 999,
))));
asg_assert(!empty($import_result['success']) && (int) $import_config['antispamguard_enabled'] === 0 && (int) $import_config['antispamguard_sfs_enabled'] === 1, 'Settings import must parse boolean strings and native booleans strictly.');
asg_assert((int) $import_config['antispamguard_decision_weight_honeypot'] === 255 && in_array('antispamguard_sfs_enabled', $settings_helper->get_settings_keys(), true), 'Settings import must clamp weights and include current SFS options.');

class asg_fake_audit_db implements \phpbb\db\driver\driver_interface
{
	public $exists = false;
	public $queries = array();

	public function sql_escape($value) { return str_replace("'", "''", (string) $value); }
	public function sql_query_limit($sql, $limit) { $this->queries[] = $sql; return 'select'; }
	public function sql_fetchrow($result) { return $this->exists ? array('audit_id' => 1) : false; }
	public function sql_freeresult($result) {}
	public function sql_query($sql) { $this->queries[] = $sql; return true; }
	public function sql_affectedrows() { return 0; }
}

class asg_fake_audit_atomic_store extends atomic_store
{
	public $inserts = array();
	protected $fake_db;

	public function __construct($db) { $this->fake_db = $db; }
	public function insert_if_missing($table, array $data, $unique_column)
	{
		$this->inserts[] = $data;
		$this->fake_db->exists = true;
	}
}

$audit_db = new asg_fake_audit_db();
$audit_config = new \phpbb\config\config(array(
	'antispamguard_registration_audit_enabled' => 1,
	'antispamguard_registration_track_page_views' => 1,
	'antispamguard_registration_audit_window' => 300,
	'antispamguard_registration_audit_retention_days' => 7,
));
$audit_atomic = new asg_fake_audit_atomic_store($audit_db);
$registration_audit = new registration_audit($audit_db, $audit_config, 'phpbb_', $audit_atomic);
asg_assert($registration_audit->record_page_view('203.0.113.10', 'test-agent', 1700000000) === true, 'The first registration page visit in a bucket should be recorded.');
asg_assert($registration_audit->record_page_view('203.0.113.10', 'test-agent', 1700000001) === false, 'A repeated page visit in the same IP bucket should not create another row.');
$registration_audit->record_submission('203.0.113.10', 'test-agent', array('phpbb_rejected' => true, 'reason' => 'phpbb_rejected'), 1700000002);
asg_assert(strpos(end($audit_db->queries), 'phpbb_rejected = phpbb_rejected + 1') !== false, 'A phpBB rejection should increment only local telemetry counters.');
$registration_audit->record_outcome('203.0.113.10', 'test-agent', array('local_rejected' => true, 'reason' => 'honeypot'), 1700000003);
$outcome_query = end($audit_db->queries);
asg_assert(strpos($outcome_query, 'local_rejected = local_rejected + 1') !== false && strpos($outcome_query, 'form_submissions = form_submissions + 1') === false, 'A late registration outcome must update its counters without counting the same submission twice.');
$cross_bucket_key = $registration_audit->record_submission('203.0.113.11', 'test-agent', array('reason' => 'submitted'), 1700000399);
$registration_audit->record_outcome('203.0.113.11', 'test-agent', array('phpbb_rejected' => true, 'reason' => 'phpbb_rejected'), 1700000401, $cross_bucket_key);
asg_assert(strpos(end($audit_db->queries), (string) $cross_bucket_key) !== false, 'A registration outcome crossing a time-bucket boundary must update the original submission bucket.');

$policy = new registration_policy();
asg_assert($policy->should_audit_without_blocking('register', array('timestamp_too_fast'), true, true) === true, 'A fast real registration can be audited in lenient mode.');
asg_assert($policy->should_audit_without_blocking('register', array('honeypot', 'timestamp'), true, true) === false, 'A direct bot POST must not be audited as a false positive.');
asg_assert($policy->should_audit_without_blocking('register', array('sfs_identity'), true, true) === false, 'A listed submitted identity must block.');
asg_assert($policy->should_audit_without_blocking('register', array('sfs_reputation'), true, true) === true, 'An isolated IP reputation hit should be audited.');
asg_assert($policy->should_audit_without_blocking('register', array('timestamp_too_fast'), true, false) === false, 'Strict mode must block every signal.');

$engine = new decision_engine(array(
	'antispamguard_decision_engine_enabled' => 1,
	'antispamguard_decision_score_log' => 25,
	'antispamguard_decision_score_block' => 80,
));
asg_assert($engine->evaluate(array('honeypot' => true))['action'] === 'block', 'Honeypot score must block.');
asg_assert($engine->evaluate(array('timestamp_too_fast' => true))['action'] === 'allow', 'A single fast timestamp must remain below the log threshold.');
asg_assert($engine->evaluate(array('rate_limit' => true))['action'] === 'log', 'Rate score must produce a log action at the default threshold.');

class asg_fake_sfs_cache extends sfs_cache
{
	public $rows = array();

	public function __construct()
	{
	}

	public function get($type, $value = null)
	{
		$key = (string) $type . ':' . (string) $value;
		return isset($this->rows[$key]) ? $this->rows[$key] : false;
	}

	public function get_many(array $checks)
	{
		$found = array();
		foreach ($checks as $type => $value)
		{
			$row = $this->get($type, $value);
			if ($row !== false)
			{
				$found[(string) $type] = $row;
			}
		}

		return $found;
	}

	public function set($type, $value = null, $data = null, $is_listed = false, $confidence = 0, $frequency = 0, $ttl_override = null)
	{
		$this->rows[(string) $type . ':' . (string) $value] = array(
			'cached' => true,
			'data' => $data,
			'is_listed' => (bool) $is_listed,
			'confidence' => (float) $confidence,
			'frequency' => (int) $frequency,
			'error' => false,
		);
	}

	public function set_error($type, $value = null, $status = 'remote_error')
	{
		$status = trim((string) $status) !== '' ? (string) $status : 'remote_error';
		$this->rows[(string) $type . ':' . (string) $value] = array(
			'cached' => true,
			'data' => array('error' => true, 'status' => $status),
			'is_listed' => false,
			'confidence' => 0,
			'frequency' => 0,
			'error' => true,
			'error_status' => $status,
		);
	}
}

class asg_fake_sfs_client extends stopforumspam_client
{
	public $requests = 0;
	public $last_url = '';
	public $response_fields = array();

	protected function http_get($url)
	{
		$this->requests++;
		$this->last_url = (string) $url;

		return json_encode(array_merge(array('success' => 1), $this->response_fields ?: array(
			'ip' => array('appears' => 1, 'confidence' => 90, 'frequency' => 12),
			'email' => array('appears' => 0, 'confidence' => 0, 'frequency' => 0),
			'username' => array('appears' => 1, 'confidence' => 85, 'frequency' => 7),
		)));
	}
}

class asg_failing_sfs_client extends stopforumspam_client
{
	protected function http_get($url)
	{
		return false;
	}
}

class asg_fake_http_stream
{
	protected $contents;
	protected $declared_size;

	public function __construct($contents, $declared_size = null)
	{
		$this->contents = (string) $contents;
		$this->declared_size = $declared_size === null ? strlen($this->contents) : (int) $declared_size;
	}

	public function getSize()
	{
		return $this->declared_size;
	}

	public function read($length)
	{
		return substr($this->contents, 0, (int) $length);
	}
}

class asg_fake_http_response
{
	protected $status;
	protected $contents;
	protected $declared_size;

	public function __construct($status, $contents, $declared_size = null)
	{
		$this->status = (int) $status;
		$this->contents = (string) $contents;
		$this->declared_size = $declared_size;
	}

	public function getStatusCode()
	{
		return $this->status;
	}

	public function getBody()
	{
		return new asg_fake_http_stream($this->contents, $this->declared_size);
	}
}

class asg_fake_native_http_client
{
	public $calls = array();
	public $responses = array();

	public function request($method, $url, array $options)
	{
		$this->calls[] = array('method' => $method, 'url' => $url, 'options' => $options);
		if (empty($this->responses))
		{
			throw new \RuntimeException('No fake response configured.');
		}

		return array_shift($this->responses);
	}
}

class asg_exposed_native_sfs_client extends stopforumspam_client
{
	public function fetch($url)
	{
		return $this->http_get($url);
	}

	public function send($url, array $fields)
	{
		return $this->http_post($url, $fields);
	}
}

class asg_fake_sfs_log extends sfs_log
{
	public $decisions = array();

	public function __construct()
	{
	}

	public function add($source, $ip, $email, $username, array $decision)
	{
		$this->decisions[] = $decision;
		return count($this->decisions);
	}
}

$cache = new asg_fake_sfs_cache();
$client = new asg_fake_sfs_client($cache);
$lookups = array('ip' => '203.0.113.8', 'email' => 'USER@example.test', 'username' => 'TestUser');
$first = $client->check_many($lookups);
$second = $client->check_many($lookups);

asg_assert($client->requests === 1, 'Three SFS identifiers and a repeat should use one remote request.');
asg_assert(strpos($client->last_url, 'ip=203.0.113.8') !== false && strpos($client->last_url, 'email=') !== false && strpos($client->last_url, 'username=') !== false, 'The SFS request must combine all identifiers.');
asg_assert(!empty($first['ip']['is_listed']) && !empty($first['username']['is_listed']), 'Combined SFS response fields should be parsed.');
asg_assert(!empty($second['ip']['cached']) && !empty($second['email']['cached']) && !empty($second['username']['cached']), 'Repeated SFS fields should come from cache.');

$failure_cache = new asg_fake_sfs_cache();
$failure_client = new asg_failing_sfs_client($failure_cache);
$first_failure = $failure_client->check('ip', '203.0.113.99');
$cached_failure = $failure_client->check('ip', '203.0.113.99');
asg_assert(!empty($first_failure['error']) && $first_failure['error_status'] === 'request_failed', 'A failed SFS request must expose its technical status.');
asg_assert(!empty($cached_failure['cached']) && $cached_failure['error_status'] === 'request_failed', 'The SFS error cache must preserve the original failure status.');

$native_config = new \phpbb\config\config(array(
	'antispamguard_sfs_api_key' => 'secret',
	'antispamguard_sfs_http_timeout' => 2,
	'antispamguard_sfs_http_retries' => 1,
	'antispamguard_sfs_http_max_response_bytes' => 4096,
));
$native_http = new asg_fake_native_http_client();
$native_http->responses = array(
	new asg_fake_http_response(503, 'unavailable'),
	new asg_fake_http_response(200, '{"success":1}'),
);
$native_sfs = new asg_exposed_native_sfs_client(new asg_fake_sfs_cache(), $native_config, $native_http);
asg_assert($native_sfs->fetch('https://api.stopforumspam.org/api?json') === '{"success":1}' && count($native_http->calls) === 2, 'The phpBB HTTP transport must retry once and accept only a successful status.');
asg_assert($native_http->calls[0]['options']['allow_redirects'] === false && $native_http->calls[0]['options']['http_errors'] === false, 'The SFS HTTP transport must disable redirects and handle status codes itself.');
$package = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
asg_assert($native_http->calls[0]['options']['headers']['User-Agent'] === 'AntiSpamGuard/' . $package['version'], 'The SFS User-Agent must follow the package version.');

$native_http->responses = array(new asg_fake_http_response(200, 'ok'));
asg_assert($native_sfs->send('https://www.stopforumspam.com/add.php', array('api_key' => 'secret')) === 'ok' && isset($native_http->calls[2]['options']['form_params']['api_key']), 'SFS submissions must use the native client form body.');

$native_http->responses = array(
	new asg_fake_http_response(503, 'unavailable'),
	new asg_fake_http_response(200, 'ok'),
);
asg_assert($native_sfs->send('https://www.stopforumspam.com/add.php', array('api_key' => 'secret')) === false && count($native_http->calls) === 4, 'A non-idempotent SFS POST must never be retried automatically.');

$native_http->responses = array(
	new asg_fake_http_response(200, 'small', 5000),
	new asg_fake_http_response(200, 'small', 5000),
);
asg_assert($native_sfs->fetch('https://api.stopforumspam.org/api?json') === false, 'An oversized remote response must be rejected after the bounded retry count.');
asg_assert(count($native_http->calls) === 6, 'HTTP retries must remain capped and must not generate unbounded traffic.');

$native_http->responses = array(new asg_fake_http_response(200, 'pending moderation'));
$unexpected_submit = $native_sfs->submit_spammer('203.0.113.50', 'user@example.test', 'TestUser');
asg_assert(empty($unexpected_submit['success']) && $unexpected_submit['status'] === 'unexpected_response', 'An unrecognized SFS submission response must not be reported as success.');
$native_http->responses = array(new asg_fake_http_response(200, '{"success":1}'));
$confirmed_submit = $native_sfs->submit_spammer('203.0.113.50', 'user@example.test', 'TestUser');
asg_assert(!empty($confirmed_submit['success']), 'An explicit JSON success response must confirm an SFS submission.');

\GuzzleHttp\Client::$calls = array();
\GuzzleHttp\Client::$responses = array(new asg_fake_http_response(200, 'lazy-ok'));
$lazy_sfs = new asg_exposed_native_sfs_client(new asg_fake_sfs_cache(), $native_config);
asg_assert($lazy_sfs->fetch('https://api.stopforumspam.org/api?json') === 'lazy-ok' && count(\GuzzleHttp\Client::$calls) === 1, 'The SFS client must lazily create Guzzle without a phpBB http_client service dependency.');

$decision_config = new \phpbb\config\config(array(
	'antispamguard_sfs_enabled' => 1,
	'antispamguard_sfs_action_mode' => 'block',
	'antispamguard_sfs_min_confidence' => 80,
	'antispamguard_sfs_min_frequency' => 5,
	'antispamguard_sfs_block_multiple_hits' => 0,
	'antispamguard_sfs_log_enabled' => 0,
));

function asg_sfs_decision_for(array $fields, $ip = '203.0.113.8', $email = 'user@example.test', $username = 'TestUser')
{
	global $decision_config;
	$cache = new asg_fake_sfs_cache();
	$client = new asg_fake_sfs_client($cache);
	$client->response_fields = $fields;
	$service = new sfs_decision($decision_config, $client, new asg_fake_sfs_log());

	return $service->should_block($ip, $email, $username, 'register');
}

$clean = array('appears' => 0, 'confidence' => 0, 'frequency' => 0);
$strong = array('appears' => 1, 'confidence' => 90, 'frequency' => 10);

$username_only = asg_sfs_decision_for(array('ip' => $clean, 'email' => $clean, 'username' => $strong));
asg_assert(empty($username_only['block']) && !empty($username_only['review_only']), 'A strong username alone must be reviewed, not blocked.');

$ip_only = asg_sfs_decision_for(array('ip' => $strong, 'email' => $clean, 'username' => $clean));
asg_assert(empty($ip_only['block']) && !empty($ip_only['review_only']), 'A strong IP alone must be reviewed, not blocked.');

$email_only = asg_sfs_decision_for(array('ip' => $clean, 'email' => $strong, 'username' => $clean));
asg_assert(!empty($email_only['block']) && !empty($email_only['hard_identity_match']), 'A strongly listed e-mail must block.');

$username_ip = asg_sfs_decision_for(array('ip' => $strong, 'email' => $clean, 'username' => $strong));
asg_assert(!empty($username_ip['block']) && !empty($username_ip['hard_identity_match']), 'A strong username plus strong IP must block.');

$weak = array('appears' => 1, 'confidence' => 10, 'frequency' => 1);
$decision_config['antispamguard_sfs_partial_action'] = 'score';
$ip_only_score = asg_sfs_decision_for(array('ip' => $strong, 'email' => $clean, 'username' => $clean));
asg_assert(empty($ip_only_score['block']) && !empty($ip_only_score['soft']) && empty($ip_only_score['log_only']), 'Partial action "score" must turn a strong IP alone into a scoring signal.');

$decision_config['antispamguard_sfs_partial_action'] = 'block';
$ip_only_block = asg_sfs_decision_for(array('ip' => $strong, 'email' => $clean, 'username' => $clean));
asg_assert(!empty($ip_only_block['block']), 'Partial action "block" must block a strong IP alone.');

$decision_config['antispamguard_sfs_block_multiple_hits'] = 1;
$multi_weak_block = asg_sfs_decision_for(array('ip' => $weak, 'email' => $weak, 'username' => $clean));
asg_assert(!empty($multi_weak_block['block']), 'Multiple weak hits must block when the partial action is "block".');

$decision_config['antispamguard_sfs_action_mode'] = 'log_only';
$partial_log_only = asg_sfs_decision_for(array('ip' => $strong, 'email' => $clean, 'username' => $clean));
asg_assert(empty($partial_log_only['block']) && empty($partial_log_only['soft']) && !empty($partial_log_only['log_only']), 'Global log-only mode must still override the partial action.');

$decision_config['antispamguard_sfs_action_mode'] = 'block';
$decision_config['antispamguard_sfs_block_multiple_hits'] = 0;
$decision_config['antispamguard_sfs_partial_action'] = 'review';
$ip_only_review = asg_sfs_decision_for(array('ip' => $strong, 'email' => $clean, 'username' => $clean));
asg_assert(empty($ip_only_review['block']) && empty($ip_only_review['soft']) && !empty($ip_only_review['review_only']), 'The default partial action must keep the previous review-only behaviour.');

$contact_template = file_get_contents(__DIR__ . '/../styles/all/template/event/overall_footer_copyright_prepend.html');
asg_assert(strpos($contact_template, 'form="post"') !== false && strpos($contact_template, 'form="postform"') === false, 'Contact protection fields must target the prosilver contact form id "post".');
asg_assert(strpos($listener_source, "isset(\$event['submit']) && !\$event['submit']") !== false, 'Posting validation must skip previews and refreshes.');
asg_assert(strpos($listener_source, '$is_pm && $this->is_pm_final_submit()') !== false, 'Private-message validation must run only on the final send.');

$logs_controller_source = file_get_contents(__DIR__ . '/../acp/logs_controller.php');
asg_assert(strpos($logs_controller_source, "is_set_post('export_json')") !== false && strpos($logs_controller_source, "'export_extra_' . \$export_key") !== false, 'The block log page must offer JSON and auxiliary-table exports.');

$error_log = new asg_fake_sfs_log();
$error_config = new \phpbb\config\config(array(
	'antispamguard_sfs_enabled' => 1,
	'antispamguard_sfs_action_mode' => 'block',
	'antispamguard_sfs_min_confidence' => 80,
	'antispamguard_sfs_min_frequency' => 5,
	'antispamguard_sfs_block_multiple_hits' => 0,
	'antispamguard_sfs_log_enabled' => 1,
	'antispamguard_sfs_log_only_blocked' => 1,
	'antispamguard_sfs_log_all_checks' => 0,
));
$error_service = new sfs_decision($error_config, new asg_failing_sfs_client(new asg_fake_sfs_cache()), $error_log);
$error_decision = $error_service->should_block('203.0.113.100', '', '', 'register');
asg_assert(!empty($error_decision['had_error']) && !empty($error_decision['log_written']) && count($error_log->decisions) === 1, 'SFS transport errors must be logged when technical logging is enabled, even in blocked-only mode.');

$clean_log = new asg_fake_sfs_log();
$clean_config = new \phpbb\config\config(array(
	'antispamguard_sfs_enabled' => 1,
	'antispamguard_sfs_action_mode' => 'block',
	'antispamguard_sfs_min_confidence' => 80,
	'antispamguard_sfs_min_frequency' => 5,
	'antispamguard_sfs_block_multiple_hits' => 0,
	'antispamguard_sfs_log_enabled' => 1,
	'antispamguard_sfs_log_only_blocked' => 0,
	'antispamguard_sfs_log_all_checks' => 0,
));
$clean_client = new asg_fake_sfs_client(new asg_fake_sfs_cache());
$clean_client->response_fields = array('ip' => $clean);
$clean_service = new sfs_decision($clean_config, $clean_client, $clean_log);
$clean_decision = $clean_service->should_block('203.0.113.101', '', '', 'register');
asg_assert(empty($clean_decision['log_written']) && count($clean_log->decisions) === 0, 'A clean SFS check must remain unlogged when log-all is disabled.');

$log_all_config = new \phpbb\config\config(array(
	'antispamguard_sfs_enabled' => 1,
	'antispamguard_sfs_action_mode' => 'block',
	'antispamguard_sfs_min_confidence' => 80,
	'antispamguard_sfs_min_frequency' => 5,
	'antispamguard_sfs_block_multiple_hits' => 0,
	'antispamguard_sfs_log_enabled' => 1,
	'antispamguard_sfs_log_only_blocked' => 0,
	'antispamguard_sfs_log_all_checks' => 1,
));
$log_all_log = new asg_fake_sfs_log();
$log_all_client = new asg_fake_sfs_client(new asg_fake_sfs_cache());
$log_all_client->response_fields = array('ip' => $clean);
$log_all_service = new sfs_decision($log_all_config, $log_all_client, $log_all_log);
$log_all_decision = $log_all_service->should_block('203.0.113.102', '', '', 'register');
asg_assert(!empty($log_all_decision['log_written']) && count($log_all_log->decisions) === 1, 'Log-all must write clean SFS checks.');

$forced_log = new asg_fake_sfs_log();
$forced_config = new \phpbb\config\config(array(
	'antispamguard_sfs_enabled' => 0,
	'antispamguard_sfs_log_enabled' => 0,
	'antispamguard_sfs_min_confidence' => 80,
	'antispamguard_sfs_min_frequency' => 5,
));
$forced_client = new asg_fake_sfs_client(new asg_fake_sfs_cache());
$forced_client->response_fields = array('ip' => $clean);
$forced_service = new sfs_decision($forced_config, $forced_client, $forced_log);
$forced_decision = $forced_service->should_block('203.0.113.103', '', '', 'manual_acp_test', true);
asg_assert(!empty($forced_decision['log_written']) && count($forced_log->decisions) === 1, 'The ACP test-and-log action must force an audit row even when live SFS logging is disabled.');

if (class_exists('SQLite3'))
{
	class asg_sqlite_db implements \phpbb\db\driver\driver_interface
	{
		public $db;
		protected $affected = 0;

		public function __construct()
		{
			$this->db = new \SQLite3(':memory:');
		}

		public function get_sql_layer() { return 'sqlite3'; }
		public function sql_escape($value) { return \SQLite3::escapeString((string) $value); }
		public function sql_affectedrows() { return $this->affected; }
		public function sql_nextid() { return (int) $this->db->lastInsertRowID(); }
		public function sql_freeresult($result) { if ($result instanceof \SQLite3Result) { $result->finalize(); } }
		public function sql_fetchrow($result) { $row = $result->fetchArray(SQLITE3_ASSOC); return $row === false ? false : $row; }
		public function sql_query_limit($sql, $limit, $offset = 0) { return $this->sql_query($sql . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset); }
		public function sql_in_set($field, $values)
		{
			$values = (array) $values;
			if (empty($values)) { return '1=0'; }
			return $field . ' IN (' . implode(', ', array_map(array($this, 'literal'), $values)) . ')';
		}
		public function sql_build_array($mode, array $data)
		{
			if ($mode === 'INSERT')
			{
				return '(' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_map(array($this, 'literal'), array_values($data))) . ')';
			}
			$parts = array();
			foreach ($data as $column => $value) { $parts[] = $column . ' = ' . $this->literal($value); }
			return implode(', ', $parts);
		}
		public function literal($value)
		{
			if ($value === null) { return 'NULL'; }
			if (is_int($value) || is_float($value)) { return (string) $value; }
			return "'" . $this->sql_escape($value) . "'";
		}
		public function sql_query($sql)
		{
			if (preg_match('/^\s*(SELECT|PRAGMA|WITH)\b/i', $sql))
			{
				return $this->db->query($sql);
			}
			if (!$this->db->exec($sql))
			{
				throw new \RuntimeException($this->db->lastErrorMsg() . ': ' . $sql);
			}
			$this->affected = $this->db->changes();
			return true;
		}
		public function scalar($sql)
		{
			$result = $this->sql_query($sql);
			$row = $this->sql_fetchrow($result);
			$this->sql_freeresult($result);
			return $row ? reset($row) : null;
		}
	}

	$db = new asg_sqlite_db();
	$db->sql_query('CREATE TABLE phpbb_antispamguard_ip_rate (rate_id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT NOT NULL UNIQUE, hits INTEGER NOT NULL, first_hit INTEGER NOT NULL, last_hit INTEGER NOT NULL, expires_at INTEGER NOT NULL)');
	$db->sql_query('CREATE TABLE phpbb_antispamguard_ip_score (score_id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT NOT NULL UNIQUE, score INTEGER NOT NULL, hits INTEGER NOT NULL, last_reason TEXT NOT NULL, first_seen INTEGER NOT NULL, last_update INTEGER NOT NULL, expires_at INTEGER NOT NULL)');
	$db->sql_query('CREATE TABLE phpbb_antispamguard_sfs_cache (cache_id INTEGER PRIMARY KEY AUTOINCREMENT, lookup_type TEXT NOT NULL, lookup_value TEXT NOT NULL, lookup_hash TEXT NOT NULL UNIQUE, response_json TEXT NOT NULL, is_listed INTEGER NOT NULL, confidence REAL NOT NULL, frequency INTEGER NOT NULL, created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL)');
	$db->sql_query('CREATE TABLE phpbb_antispamguard_sfs_log (log_id INTEGER PRIMARY KEY AUTOINCREMENT, check_source TEXT NOT NULL, user_ip TEXT NOT NULL, user_email TEXT NOT NULL, username TEXT NOT NULL, listed_count INTEGER NOT NULL, strong_hit INTEGER NOT NULL, blocked INTEGER NOT NULL, action_mode TEXT NOT NULL, submission_key TEXT NOT NULL, details_json TEXT NOT NULL, created_at INTEGER NOT NULL, first_seen INTEGER NOT NULL, last_seen INTEGER NOT NULL, occurrence_count INTEGER NOT NULL)');

	$atomic = new atomic_store($db);
	$runtime_config = new \phpbb\config\config(array(
		'antispamguard_ip_rate_limit_enabled' => 1,
		'antispamguard_ip_rate_limit_window' => 60,
		'antispamguard_ip_rate_limit_max_hits' => 5,
		'antispamguard_ip_rate_limit_action' => 'block',
		'antispamguard_ip_reputation_enabled' => 1,
		'antispamguard_ip_reputation_threshold' => 20,
		'antispamguard_ip_reputation_weight_honeypot' => 3,
		'antispamguard_ip_reputation_decay_interval' => 600,
		'antispamguard_ip_reputation_ttl' => 86400,
		'antispamguard_sfs_cache_ttl' => 86400,
	));

	$rate = new ip_rate_limit($runtime_config, $db, 'phpbb_', $atomic);
	for ($i = 0; $i < 7; $i++) { $rate_result = $rate->hit('198.51.100.10'); }
	asg_assert($rate_result['hits'] === 7 && !empty($rate_result['limited']), 'The atomic rate row must count every hit and enforce the limit.');
	asg_assert((int) $db->scalar("SELECT COUNT(*) FROM phpbb_antispamguard_ip_rate WHERE ip = '198.51.100.10'") === 1, 'The rate limiter must keep one unique row per IP.');

	$reputation = new ip_reputation($runtime_config, $db, 'phpbb_', $atomic);
	$rep_one = $reputation->add_event('198.51.100.11', 'honeypot');
	$rep_two = $reputation->add_event('198.51.100.11', 'honeypot');
	asg_assert($rep_one['hits'] === 1 && $rep_two['hits'] === 2 && $rep_two['score'] === 6, 'Atomic reputation updates must preserve every event.');
	asg_assert((int) $db->scalar("SELECT COUNT(*) FROM phpbb_antispamguard_ip_score WHERE ip = '198.51.100.11'") === 1, 'IP reputation must keep one unique row per IP.');

	$real_cache = new sfs_cache($db, $runtime_config, 'phpbb_', $atomic);
	$real_cache->set('email', 'user@example.test', array('email' => $strong), true, 90, 10);
	$real_cache->set('email', 'user@example.test', array('email' => $clean), false, 0, 0);
	$cached = $real_cache->get('email', 'user@example.test');
	asg_assert((int) $db->scalar("SELECT COUNT(*) FROM phpbb_antispamguard_sfs_cache") === 1, 'SFS cache refreshes must keep one unique hash row.');
	asg_assert(!empty($cached['cached']) && empty($cached['is_listed']), 'The latest SFS cache refresh must win atomically.');

	$real_sfs_log = new sfs_log($db, 'phpbb_');
	$sfs_decision_row = array(
		'results' => array(),
		'action_mode' => 'log_only',
		'matched' => false,
		'block' => false,
		'listed_count' => 0,
		'submission_key' => str_repeat('a', 64),
	);
	$first_sfs_log_id = $real_sfs_log->add('register', '203.0.113.20', 'Same@Example.test', 'SameUser', $sfs_decision_row);
	$sfs_decision_row['submission_key'] = str_repeat('b', 64);
	$second_sfs_log_id = $real_sfs_log->add('register', '203.0.113.20', 'same@example.test', 'sameuser', $sfs_decision_row);
	asg_assert($first_sfs_log_id === $second_sfs_log_id && (int) $db->scalar('SELECT occurrence_count FROM phpbb_antispamguard_sfs_log WHERE log_id = ' . (int) $first_sfs_log_id) === 2, 'SFS retries with different request keys but the same complete identity must be grouped.');

	$sfs_decision_row['submission_key'] = str_repeat('c', 64);
	$third_sfs_log_id = $real_sfs_log->add('register', '203.0.113.20', 'different@example.test', 'sameuser', $sfs_decision_row);
	asg_assert($third_sfs_log_id !== $first_sfs_log_id && (int) $db->scalar('SELECT COUNT(*) FROM phpbb_antispamguard_sfs_log') === 2, 'SFS retries with a different e-mail must remain separate even behind the same IP.');
}

if (!empty($failures))
{
	fwrite(STDERR, "FAILED (" . count($failures) . '/' . $tests . ")\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo 'OK (' . $tests . " checks)\n";
