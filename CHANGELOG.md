# Changelog — AntiSpam Guard

Todas as versões relevantes desta linha **3.3.x** (phpBB 3.3).  
Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

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