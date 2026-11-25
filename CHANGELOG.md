# AntiSpam Guard Changelog

## 3.3.89

- Fixed contact form protection: the HoneyPot and timestamp fields now target the prosilver contact form (`form="post"`). Previously they were never submitted, so every legitimate contact message was blocked when the protection was enabled.
- Posting validation now runs only on a real submit. Previews and attachment/poll refreshes no longer write logs, consume the IP rate limit or trigger "too fast" on the next submit.
- Private-message validation now runs only on the final send (not on preview, recipient, attachment or draft actions).
- Added **Partial match action** for StopForumSpam (review / score / block) covering IP-only, username-only and multiple weak hits. The default "review" keeps the previous behaviour.
- Rewrote the minimum confidence, minimum frequency and multiple weak hits help texts to describe the actual decision rules.
- Score-mode SFS matches are written to the SFS log even when "log only blocked" is enabled.
- Added **Export JSON** for the main block log, using the same filters as the CSV export.
- Added CSV exports for the registration audit, reports sent to StopForumSpam and SFS log reviews, honouring the period and IP filters.
- Corrected the contact protection help text (fields are rendered server-side, no JavaScript required).
- Updated outdated self-tests and added regression checks (82 checks).
- Redesigned the ACP with the mundophpBB Download Center / Changelog visual language: page header with icon and version tag, tab bar across all sections, cards with tinted header strips, prosilver-blue table headers, pills, callouts and flat buttons. All styles are scoped to `.mphpbb-asg-admin`.
- Replaced emoji headings with Font Awesome icons.
- Fixed the duplicated word "campo campo" in Brazilian Portuguese strings.

## 3.3.88 — Release candidate

- Standardized runtime and template files on LF line endings for EPV.
- Resolved the default registration notice through the current ACP language.
- Declared nullable parameters explicitly for PHP 8.4 compatibility.
- Prevented SQLite index name collisions during initial schema setup.
- Kept statistics chart scrolling inside its panel on narrow screens.
- Removed the old validator transcript from the distribution.
- Consolidated the dashboard and alerting changes since 3.3.74.

### Dashboard and alerting highlights since 3.3.74

- The ACP links hourly and daily activity, reason counts, HoneyPot hits, StopForumSpam blocks and the IP ranking to the relevant logs.
- Administrators can filter records, investigate an IP's local reputation and block history, export filtered CSV files, and download activity reports as PDF. The country breakdown uses an optional local IPv4/IPv6 database.
- Configurable spike alerts use block and distinct IP thresholds. Email supports daily and weekly summaries, and Telegram can be enabled for spike alerts. The ACP includes separate delivery tests, a seven-day rule preview and cron status.
- Delivery history tracks accepted, failed and unconfirmed attempts. Its channel, type, outcome, diagnostic and UTC date filters also apply to CSV. Per-channel and daily totals and diagnostic links help narrow the investigation.

## 3.3.87

- Added daily delivery counts in UTC for the filtered history.
- Date links narrow the list and the CSV export to the selected day.

## 3.3.86

- Ranked recorded delivery diagnostics across the filtered history.
- Diagnostic links narrow the history and CSV export while preserving the other filters.

## 3.3.85

- Added delivery totals by channel and outcome for the entire filtered history.

## 3.3.84

- Added optional UTC date filters to the delivery history and CSV export.
- The selected end date includes the full UTC calendar day.

## 3.3.83

- Added CSV export for the delivery history with the selected channel, type, and result filters.
- The export includes retained results across all pages and uses UTC timestamps and predefined diagnostic codes.

## 3.3.82

- Added a notification cron panel with the latest execution per channel and an overdue indicator.
- Added the next email summary window, pending periods, and retry eligibility.

## 3.3.81

- Added a read-only spike rule preview over the last seven days of retained records.
- Simulations use the entered thresholds, distinct IPs, and cooldown. Results include the total and the ten most recent simulated triggers.

## 3.3.80

- Added localized failure diagnostics to delivery history and manual test feedback.
- Unconfirmed attempts have their own result and filter.
- Existing records retain their original results. New attempts store only predefined diagnostic codes.

## 3.3.79

- Added a filterable delivery history for diagnostic messages, spike alerts, and summaries.
- Records contain the time, channel, message type, and transport result.
- History is retained for 30 days and pruned by the alerts cleanup task.

## 3.3.78

- Added separate manual email and Telegram delivery tests in the ACP.
- Test messages go to saved destinations with channel-specific success and error feedback.
- Manual tests use separate timing state and leave scheduled alerts, summaries, and log counts intact.

## 3.3.77

- Added PDF export of the selected hourly or daily activity chart, with independent general and StopForumSpam totals.
- Added an optional local IPv4/IPv6 country index from DB-IP Country Lite, with ACP import, country statistics and an estimated country on IP investigations.
- Added an optional Telegram destination for spike alerts. It uses the existing rule thresholds and maintains its own alert and retry state.

## 3.3.76

- Added an ACP page for opt-in email alerts and activity summaries.
- Block spike alerts use a configurable window, minimum confirmed block count, minimum distinct IP count and cooldown.
- Added daily and weekly UTC summaries of the general and StopForumSpam logs using the phpBB cron and email transport.
- Added delivery status for scheduled notifications and retry handling after transport failures.
- Alert emails and summaries include aggregate counts.

## 3.3.75

- Added clickable hourly and daily activity charts for general and StopForumSpam logs. Each series counts grouped records using their original timestamp in UTC.
- Added a seven-day IP ranking and links from reason statistics, HoneyPot counts and StopForumSpam blocks to the corresponding log views.
- Added exact IP and username filters, a UTC time range, score bounds and a final action filter. The IP and UTC time range also apply to the StopForumSpam log.
- Added an IP investigation summary with the current local reputation score and links to retained block history.
- Both CSV exports use the same filters as their respective log views.

## 3.3.73

- Repeated attempts are now grouped even when separate HTTP requests generate different `submission_key` values.
- The fallback correlation requires matching non-empty IP, form type, username and e-mail within the configured window, preventing unrelated users behind the same NAT or proxy from being merged.
- StopForumSpam logs use the same safe stable-identity fallback within a short 60-second window.
- `phpbb_rejected` remains available as an outcome/filter reason but is no longer exposed as an AntiSpam Guard rule in `matched_rules`.
- phpBB rejection logs now populate `decision_details` with the decision source, error count and up to five sanitized diagnostic messages.
- Added the `v_3_3_73` version migration and expanded grouping regression coverage.

## 3.3.72

- Main logs now correlate early and late phpBB events with a per-request `submission_key`, preserving first/last timestamps, occurrence count, observed actions and the final enforcement action.
- Early registration telemetry is recorded as review-only and is promoted to blocked only when the later validation path actually enforces a block.
- Stored risk scores now use the decision-engine result when applicable and include decision metadata for auditing.
- Auto-ban now runs only after a confirmed final block, validates the IP, respects whitelists and uses phpBB's `user_ban()` helper when available.
- StopForumSpam reports are no longer retried automatically, require an explicit recognized success response and are deduplicated by normalized identity.
- The SFS **Allow** action now writes the available IP, e-mail and username to their corresponding SFS whitelists; direct reports require all mandatory fields.
- SFS and main-log CSV exports are streamed in batches, use explicit UTC timestamps and include correlation metadata without a silent 1,000-row limit.
- Reason statistics now count each token in combined reasons, IP wildcards are normalized consistently, settings import/export covers current options and boolean strings are parsed strictly.
- Diagnostic rows are removed after the ACP write test, package-only `.sql` duplicates were removed and schema repair now restores missing columns and indexes.

## 3.3.71

- Registration POSTs are now observed on the early `core.ucp_register_requests_after` event with high priority, before phpBB or third-party validators can prevent the late event from running.
- Local HoneyPot and token/timestamp failures are logged immediately while complete validation and blocking remain on `core.ucp_register_data_after`.
- Grouped registration auditing now separates submission start from final outcome, preventing missing or duplicated submission counters.
- Added a main-log self-test under **About / Diagnostics**, including verification of the persisted database ID.
- Added the `v_3_3_71` repair migration for missing tables, columns, settings, ACP modules and permissions.
- Normalized the package version, documentation, User-Agent and auxiliary examples to 3.3.71.

## 3.3.69

- Fixed registration blocking by subscribing to `core.ucp_register_data_after`, the event that exposes the registration error array.
- Replaced the MySQL-only diagnostics table check and the legacy SQL substring migration with portable phpBB DBAL operations.
- Added reversible cleanup for historical configuration, ACP modules and permissions, and removed the obsolete extension-version configuration key.
- Exposed the active autoban and decision-engine settings in the ACP, connected critical-alert rows, and removed the unused shadowban feature.
- Hardened CSV exports against spreadsheet formula injection and generated the StopForumSpam User-Agent from package metadata.
- Added server-rendered contact and private-message protection fields with a no-JavaScript-safe fallback.
- Removed unused routing and language entries and normalized PHP indentation to phpBB tabs.
- Replaced the deprecated global `add_log()` call with phpBB's logger service and packaged auxiliary configuration samples with validator-recognized text filenames.

## 3.3.68

- ACP StopForumSpam tests now report transport, invalid-response, missing-result and circuit-breaker errors instead of presenting them as clean lookups.
- Technical SFS failures are written to the dedicated log whenever SFS logging is enabled, including blocked-only logging mode.
- The SFS error cache preserves the original technical status for repeated diagnostics.
- SFS debug tracing now runs when a decisive local rule skips the regular lookup, without duplicating lookups that already ran.
- The lookup-only and test-and-log ACP actions now explain their distinct logging behavior.
- Added regression coverage for remote errors, cached error status, clean lookups, log-all and forced manual audit rows.

## 3.3.67

- Added compact registration telemetry grouped by IP and configurable time window, with automatic retention cleanup.
- Added ACP counters for grouped registration-page visits, submitted forms, phpBB validation rejections, local-rule rejections and SFS decisions evaluated in the last 24 hours.
- Registration submissions already rejected by phpBB are now logged locally as review events, including available honeypot/timestamp evidence, without triggering StopForumSpam or IP-reputation lookups.
- Repeated identical phpBB rejection logs are deduplicated for the configured audit window and no longer perform an unnecessary update when their data is unchanged.
- Added ACP controls to enable the audit, count grouped page visits, set the grouping window and configure retention.

## 3.3.66

- Removed the dependency on the non-existent `http_client` service that prevented container compilation on some phpBB 3.3 installations.
- StopForumSpam now creates and reuses Guzzle lazily without falling back to direct `file_get_contents` transport.
- Added regression coverage for initialization without an injected HTTP service and static `services.yml` validation.

## 3.3.65

- Replaced direct stream HTTP calls with phpBB's shared HTTP client, disabled redirects and capped remote response size.
- Added StopForumSpam circuit-breaker status, tuning and manual reset to the ACP.
- Added a guarded ACP action for phpBB user email activation; it cannot be enabled while email delivery is disabled.
- Added conservative Cloudflare, Nginx and Apache/ModSecurity edge-rate-limit templates with observation-first guidance.
- Added regression checks for native HTTP transport, retry limits, status handling and oversized responses.

## 3.3.64

- StopForumSpam no longer hard-blocks an isolated IP or username match; those matches are logged for review.
- Hard SFS blocking now requires a strongly listed e-mail or the strong username + IP combination.
- Added unique keys for IP rate limits, local IP reputation and SFS cache identities.
- Replaced race-prone read/delete/insert counters with atomic insert-if-missing and update operations.
- Added a one-time migration that safely consolidates historical duplicate rows before installing unique indexes.
- Expanded regression coverage to 27 checks, including real SQLite writes for counters, reputation and cache refreshes.

## 3.3.63

- Registration now rejects missing/invalid honeypot and form tokens before any remote reputation lookup.
- StopForumSpam IP, e-mail and username checks are combined into one request, with short error caching, configurable timeouts and a circuit breaker.
- Corrected strict/lenient registration policy, score/log-only actions, per-field SFS whitelists and IPv6 /64 subnet limiting.
- Reduced duplicate database reads and log writes; SFS ACP logs now use SQL pagination and bulk submission-status lookup.
- Added automatic cleanup for alerts and stale protection data even after an individual protection is disabled.
- Added migration 3.3.63 for the form-token secret, SFS submission correlation and safer defaults.

## 3.3.47

- Reduced false positives during registration when `slow_spam` appears together with local `ip_reputation` but the user submitted a normal username and email.
- In lenient registration mode, `slow_spam` is now treated as an audit/review signal unless it is combined with hard-block signals such as StopForumSpam, blacklist, rate limit, subnet abuse, content filter, or random Gmail pattern.


## 3.3.46

- Reduced false positives from local IP reputation during registration.
- A registration with submitted username and email is no longer blocked by isolated local `ip_reputation`; it is logged for review instead.
- Prevented local IP reputation from self-amplifying on `ip_reputation` and `combined_decision` outcomes.

# Changelog — AntiSpam Guard

Todas as versões relevantes desta linha **3.3.x** (phpBB 3.3).  
Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

## [3.3.45] — 2026-06-01

### Corrigido
- Erro fatal `CANNOT_REMOVE_MODULE` ao ativar a extensão em fóruns que já tinham módulos ACP parciais (instalação anterior interrompida).
- `v_0_1_0` deixa de usar `module.add` (que dispara rollback perigoso) e passa a `install_acp_modules_if_missing()` — verifica cada módulo antes de criar.

---

## [3.3.44] — 2026-06-01

### Corrigido
- Erro fatal na migração `v_3_3_42` / `v_3_3_43`: `Undefined property: $module_tool` / `$permission_tool` em métodos `custom` — ferramentas do migrator passam a ser obtidas via `get_module_tool()` e `get_permission_tool()` em `v_0_1_0`.

---

## [3.3.43] — 2026-06-01

### Corrigido
- Menu **AntiSpam Guard** invisível no ACP quando a permissão `a_antispamguard_manage` não existia no banco (instalações parciais ou após “remover dados”).
- Migração `v_3_3_43` recria a permissão, atribui a **ROLE_ADMIN_FULL** e **ROLE_ADMIN_STANDARD**, e atualiza o `module_auth` dos módulos ACP.

### Alterado
- Autorização dos módulos ACP: `ext_mundophpbb/antispamguard && (acl_a_board || acl_a_antispamguard_manage)` — administradores com permissão geral do fórum também veem o painel.

---

## [3.3.42] — 2026-06-01

### Corrigido
- Módulos ACP ausentes em `phpbb_modules` após exclusão acidental da pasta da extensão ou limpeza incompleta.
- Migração `v_3_3_42` re-registra categoria **AntiSpam Guard** e modos (Configurações, Logs, Estatísticas, Sobre, StopForumSpam).

---

## [3.3.41] — 2026-06-01

### Corrigido
- Cadastros legítimos bloqueados por autofill/gerenciador de senhas (honeypot), envio rápido ou aba de registro antiga.
- Motor de decisão bloqueava só com honeypot/tempo mesmo em falsos positivos claros.

### Adicionado
- Serviço `registration_policy` e opção ACP **Cadastro tolerante (revisar em vez de bloquear)** (`antispamguard_register_audit_soft_signals`).
- Sinais “suaves” no registro passam a ser **auditados nos logs** sem bloquear; SFS, lista negra, reputação de IP e demais sinais fortes continuam bloqueando.
- Testes `RegistrationPolicyTest` (suite com 33 testes).
- Proteção extra no template de registro: `readonly`, `data-lpignore`, limpeza do honeypot no envio.

### Alterado
- `migrations/v_3_3_41.php` usa config seguro (sem `config.add` duplicado).

---

## [3.3.40] — 2026-06-01

### Adicionado
- `acp/logs_controller.php` — logs, estatísticas, export CSV de logs, poda e bloco de reputação de IP na página de logs.

### Alterado
- `acp/main_module.php` delega logs/estatísticas ao `logs_controller` (shell ACP mais enxuto).
- `sfs_controller::assign_sfs_logs()` aceita URL base opcional para paginação na página de logs (sem duplicar query SFS).
- Página **Block logs** usa apenas `assign_sfs_logs()` para o bloco StopForumSpam.

---

## [3.3.39] — 2026-06-01

### Adicionado
- `acp/sfs_controller.php` — painel StopForumSpam, moderação, exportação, estatísticas de revisão.
- `acp/pagination_helper.php` — paginação reutilizável no ACP.

### Alterado
- `acp/main_module.php` reduzido; modo `sfs` e ações SFS delegados ao controller.

---

## [3.3.38] — 2026-06-01

### Adicionado
- `service/form_guard.php` — honeypot e validação de timestamp/token (HMAC).
- `service/ip_matcher.php` — listas e whitelist de IP.
- `acp/settings_helper.php` — import/export JSON, normalização de IPs e segredos.
- Pasta `tests/` com runner `tests/run.php` (28+ testes: FormGuard, IpMatcher, DecisionEngine, SettingsHelper).

### Alterado
- `event/listener.php` delega validação de formulário e IP aos novos serviços.

---

## [3.3.37] — 2026-06-01

### Corrigido
- Inconsistência entre `max_seconds` e `max_form_age` na validação de tempo.
- Remoção de `early_contact_check` redundante no listener.

### Alterado
- Tempo unificado em `form_guard::get_timestamp_block_reason()`.
- Templates usam constantes/helpers para honeypot (nome/classe/estilo dinâmicos).

---

## [3.3.36] — 2026-06-01

### Corrigido
- Instalações com `antispamguard_version` definida mas tabelas ausentes — migração reexecuta `repair_schema` até existir `antispamguard_log`.

---

## [3.3.35] — 2026-06-01

### Adicionado
- Migração consolidada de upgrade/repair alinhada à linha 3.3.x.

---

## [0.1.0] — base consolidada

### Adicionado
- Instalação completa: tabelas (log, SFS cache/log, reputação IP, rate limit, slow spam, alertas).
- Módulos e permissão ACP `a_antispamguard_manage`.
- Proteção em registro, postagens, contato e MP; StopForumSpam; motor de decisão; reputação de IP; cron de limpeza.
- Idiomas: `pt_br`, `en`, `fr`.

---

## Atualização recomendada

1. Extraia o ZIP em `ext/mundophpbb/antispamguard/` (não renomeie `ext` nem aninhe `antispamguard/antispamguard/`).
2. ACP → **Gerenciar extensões** → **Atualizar** AntiSpam Guard.
3. ACP → **Manutenção geral** → **Limpar cache**.
4. Saia e entre de novo no ACP se o menu não aparecer de imediato.

**Não use “Remover dados”** na extensão, exceto se quiser apagar configuração e tabelas — isso também remove módulos e permissões do ACP.

---

## Créditos

Desenvolvido por [Mundophpbb](https://www.mundophpbb.com.br). Licença GPL-2.0-only.
