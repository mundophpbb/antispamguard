# AntiSpam Guard

**AntiSpam Guard** is a phpBB extension by **Mundophpbb** that provides invisible anti-spam protection using HoneyPot, timing checks, IP controls, content filters, StopForumSpam integration, logs, statistics, and ACP diagnostics.

---

## Português

### O que é?

O **AntiSpam Guard** é uma extensão para phpBB que protege seu fórum contra spam usando técnicas invisíveis, sem depender de CAPTCHA tradicional.

A extensão foi criada para reduzir registros automatizados, postagens de spam, mensagens privadas maliciosas e abuso em formulários de contato, mantendo uma experiência limpa para usuários reais.

---

### Para que serve?

O AntiSpam Guard ajuda a proteger o fórum contra:

- registros automatizados;
- postagens de spam;
- mensagens privadas abusivas;
- abuso no formulário de contato;
- envios muito rápidos ou suspeitos;
- excesso de URLs em mensagens;
- IPs bloqueados ou comportamento repetitivo;
- remetentes suspeitos identificados pelo StopForumSpam.

---

### Como funciona?

A extensão combina várias camadas de proteção.

#### HoneyPot

Adiciona um campo invisível aos formulários. Usuários reais não veem nem preenchem esse campo, mas muitos bots automatizados preenchem todos os campos encontrados no HTML.

#### Análise de tempo

Verifica se o formulário foi enviado rápido demais ou depois de muito tempo, ajudando a detectar automação.

#### Controle por IP

Permite configurar:

- whitelist de IPs confiáveis;
- blacklist de IPs bloqueados;
- suporte a IP exato e CIDR;
- limite por IP em janela de tempo.

#### Filtro de conteúdo

Permite bloquear termos suspeitos e limitar a quantidade de URLs por envio.

#### StopForumSpam

Permite consultar dados do **StopForumSpam** para avaliar IP, e-mail e/ou nome de usuário durante as verificações anti-spam.

A integração possui página própria no ACP e registra suas ações em log separado. A partir da versão **3.3.19**, esses registros também aparecem na página **Logs de bloqueio**, além da página própria do StopForumSpam.

#### Modo discreto

Permite ocultar o motivo real do bloqueio, reduzindo pistas para bots ajustarem o comportamento.

#### Modo simulação

Registra o que seria bloqueado sem impedir o envio. É útil para testar novas regras antes de ativar bloqueios reais.

#### Logs e estatísticas

O ACP inclui logs, filtros, exportação CSV, estatísticas e diagnóstico da instalação. Os logs gerais e os logs do StopForumSpam podem ser consultados no ACP, com filtros próprios.

#### Limpeza automática

A extensão pode limpar logs antigos automaticamente via cron do phpBB, usando a retenção configurada no ACP.

---

### Recursos principais

- Proteção no registro;
- proteção em postagens, respostas e edições;
- proteção em mensagens privadas;
- proteção no formulário de contato;
- HoneyPot configurável;
- tempo mínimo e máximo de envio;
- filtro de palavras/termos suspeitos;
- limite de URLs;
- whitelist e blacklist de IPs;
- rate limit por IP;
- integração com StopForumSpam;
- logs próprios do StopForumSpam;
- logs do StopForumSpam visíveis na página própria e também em **Logs de bloqueio**;
- filtros e exportação CSV dos logs do StopForumSpam;
- modo discreto;
- modo simulação;
- logs no ACP;
- filtros e paginação de logs;
- exclusão individual, em massa e por filtro;
- exportação CSV;
- importação/exportação de configurações;
- estatísticas visuais;
- diagnóstico da instalação;
- limpeza automática via cron;
- suporte a permissões ACP;
- interface ACP em cards com visual limpo.

---

### Instalação

1. Envie a extensão para:

```text
/ext/mundophpbb/antispamguard
```

2. Acesse o ACP do phpBB.
3. Vá em **Personalizar > Gerenciar extensões**.
4. Ative **AntiSpam Guard**.
5. Configure a extensão no ACP.

---

### Atualização

1. Desative a extensão no ACP sem apagar dados, salvo se desejar remover tudo.
2. Substitua os arquivos da extensão.
3. Ative novamente ou execute a atualização pelo ACP.
4. Limpe o cache do phpBB.
5. Verifique a aba **Sobre / Diagnóstico**.
6. Após atualizar para a versão **3.3.26**, confira se os logs do StopForumSpam aparecem tanto na página própria quanto em **Logs de bloqueio**.

---

### Recomendação inicial

Para uma configuração equilibrada:

- ative HoneyPot;
- ative análise de tempo;
- use um tempo mínimo moderado;
- mantenha logs ativos no início;
- use o modo simulação ao testar regras novas;
- ative o StopForumSpam em modo de teste ou somente log antes de aplicar bloqueios definitivos;
- configure retenção automática de logs.

---

### Requisitos

- phpBB 3.3.0 ou superior;
- PHP 7.1.3 ou superior.

---

### Status

Versão atual: **3.3.26**  
Status: pronto para uso em produção.

---

## English

### What is it?

**AntiSpam Guard** is a phpBB extension that protects your forum against spam using invisible techniques, without relying on traditional CAPTCHA.

It is designed to reduce automated registrations, spam posts, malicious private messages, and contact form abuse while keeping the user experience clean for real users.

---

### What is it for?

AntiSpam Guard helps protect your forum against:

- automated registrations;
- spam posts;
- abusive private messages;
- contact form abuse;
- submissions that are too fast or suspicious;
- excessive URLs in messages;
- blocked IPs or repeated abusive behavior;
- suspicious senders identified by StopForumSpam.

---

### How does it work?

The extension combines several protection layers.

#### HoneyPot

Adds an invisible field to forms. Real users do not see or fill this field, but many automated bots fill every field found in the HTML.

#### Timing analysis

Checks whether a form was submitted too quickly or after too much time, helping detect automation.

#### IP control

Allows you to configure:

- trusted IP whitelist;
- blocked IP blacklist;
- exact IP and CIDR support;
- IP rate limiting within a time window.

#### Content filtering

Allows blocking suspicious terms and limiting the number of URLs per submission.

#### StopForumSpam

Allows checking **StopForumSpam** data to evaluate IP address, email address, and/or username during anti-spam checks.

The integration has its own ACP page and stores its actions in a dedicated log. Since version **3.3.19**, these records are also displayed on the **Blocking logs** page, in addition to the dedicated StopForumSpam page.

#### Silent mode

Can hide the real blocking reason, reducing clues that bots can use to adapt.

#### Simulation mode

Logs what would have been blocked without preventing the submission. Useful for testing new rules before enabling real blocking.

#### Logs and statistics

The ACP includes logs, filters, CSV export, statistics, and installation diagnostics. General logs and StopForumSpam logs can be reviewed in the ACP with their own filters.

#### Automatic cleanup

The extension can automatically prune old logs through phpBB cron using the retention period configured in the ACP.

---

### Main features

- Registration protection;
- posts, replies and edits protection;
- private message protection;
- contact form protection;
- configurable HoneyPot;
- minimum and maximum submission time;
- suspicious word/term filtering;
- URL limit;
- IP whitelist and blacklist;
- IP rate limiting;
- StopForumSpam integration;
- dedicated StopForumSpam logs;
- StopForumSpam logs visible on the dedicated page and on **Blocking logs**;
- StopForumSpam log filters and CSV export;
- manual StopForumSpam submission audit;
- silent mode;
- simulation mode;
- ACP logs;
- log filters and pagination;
- individual, bulk and filtered log deletion;
- CSV export;
- settings import/export;
- visual statistics;
- installation diagnostics;
- automatic cleanup through cron;
- ACP permission support;
- clean card-based ACP interface.

---

### Installation

1. Upload the extension to:

```text
/ext/mundophpbb/antispamguard
```

2. Open the phpBB ACP.
3. Go to **Customise > Manage extensions**.
4. Enable **AntiSpam Guard**.
5. Configure the extension in the ACP.

---

### Update

1. Disable the extension in the ACP without deleting data, unless you want to remove everything.
2. Replace the extension files.
3. Enable it again or run the update through the ACP.
4. Clear the phpBB cache.
5. Check the **About / Diagnostics** tab.
6. After updating to version **3.3.26**, confirm that StopForumSpam logs appear both on the dedicated page and on **Blocking logs**.

---

### Recommended initial setup

For a balanced configuration:

- enable HoneyPot;
- enable timing analysis;
- use a moderate minimum time;
- keep logs enabled at first;
- use simulation mode when testing new rules;
- enable StopForumSpam in test mode or log-only mode before applying definitive blocks;
- configure automatic log retention.

---

### Requirements

- phpBB 3.3.0 or newer;
- PHP 7.1.3 or newer.

---

### Status

Current version: **3.3.26**  
Status: production-ready.

---

## License

GPL-2.0-only
