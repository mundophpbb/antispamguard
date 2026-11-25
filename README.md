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

Por padrão, o bloqueio direto exige e-mail listado com ocorrência forte, ou IP e nome de usuário fortes juntos. Correspondências parciais (só IP, só usuário ou várias ocorrências fracas) seguem a opção **Ação para correspondências parciais**: somente revisão (padrão), somar pontuação ou bloquear.

A integração possui página própria no ACP e registra suas ações em log separado. A partir da versão **3.3.19**, esses registros também aparecem na página **Logs de bloqueio**, além da página própria do StopForumSpam.

#### Modo discreto

Permite ocultar o motivo real do bloqueio, reduzindo pistas para bots ajustarem o comportamento.

#### Modo simulação

Registra o que seria bloqueado sem impedir o envio. É útil para testar novas regras antes de ativar bloqueios reais.

#### Logs e estatísticas

O ACP inclui logs, filtros, exportação CSV e JSON, estatísticas e diagnóstico da instalação. Em **Logs de bloqueio**, os botões **Exportar CSV** e **Exportar JSON** ficam ao lado de **Aplicar filtros** e respeitam os filtros preenchidos. O bloco **Outras exportações** gera CSV da auditoria de registros, das denúncias enviadas ao StopForumSpam e das revisões de logs SFS. Os logs gerais e os logs do StopForumSpam podem ser consultados no ACP, com filtros próprios.

Em **Estatísticas**, clique em uma barra da atividade por hora ou dia para abrir o log correspondente no período em UTC. Em **Logs de bloqueio**, clique em um IP para consultar a reputação local e o histórico de bloqueios disponível. Os filtros de IP e período se aplicam aos dois logs, e cada exportação CSV usa os filtros da respectiva tabela.

Em **Alertas e resumos**, um administrador com permissão para configurar o fórum pode cadastrar o destinatário, ativar a regra de pico e escolher um resumo diário ou semanal. O cron do phpBB usa as configurações de e-mail do fórum e calcula os períodos em UTC. Para alertas de pico em janelas curtas, configure a execução do cron com frequência suficiente para detectar os aumentos de atividade.

Use **Baixar PDF** em **Estatísticas** para obter os totais do intervalo selecionado. Para ativar a estimativa de países, importe o CSV ou CSV.GZ [DB-IP Country Lite](https://db-ip.com/db/download/ip-to-country-lite) pela opção **Base local de países**. O índice fica em `store/antispamguard-country.dat` e as consultas são locais. A base original é distribuída pela DB-IP sob CC BY 4.0.

A mesma tela de alertas permite ativar o Telegram separadamente. Informe o token do bot, o ID numérico da conversa e autorize o bot a publicar nela. O Telegram recebe os totais de bloqueios e de IPs distintos.

Em **Alertas e resumos**, salve o destino e use **Enviar e-mail de teste** ou **Enviar teste pelo Telegram**. Cada ação usa o destino salvo e envia uma mensagem identificada como teste, sem dados dos logs.

Em **Histórico de entregas**, filtre as tentativas dos últimos 30 dias por canal, tipo, resultado ou período. **De** e **Até** usam datas em UTC e incluem o dia final. Abra **Atividade de envios por dia** para ver os totais diários. Clique na data para consultar ou exportar as tentativas daquele dia com os demais filtros preservados. O resumo mostra, por canal, quantas tentativas foram aceitas, falharam ou ficaram sem confirmação em todo o resultado filtrado. Novas tentativas exibem o motivo da falha quando identificado. Clique em um motivo em **Diagnósticos mais frequentes** para filtrar a lista e o CSV mantendo os demais filtros. Use **Envio não confirmado** para localizar tentativas sem resposta conclusiva. Selecione **Exportar histórico (CSV)** para baixar todos os registros retidos que correspondem aos filtros escolhidos. O arquivo usa datas em UTC e códigos de diagnóstico.

Em **Alerta de pico de bloqueios**, use **Simular últimos 7 dias** para estimar quantos alertas a regra preenchida teria gerado com os registros disponíveis. A prévia verifica a regra a cada minuto e usa o intervalo entre alertas.

Em **Cron de alertas e resumos**, confira a última execução por canal, possíveis atrasos e a próxima janela de resumo em UTC.

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
3. Limpe o cache do phpBB.
4. Ative novamente a extensão pelo ACP.
5. Verifique a aba **Sobre / Diagnóstico**.
6. Após atualizar para a versão **3.3.89**, confirme a migração em **Sobre / Diagnóstico** e execute o autoteste do log principal.

---

### Recomendação inicial

Para uma configuração equilibrada:

- ative HoneyPot;
- ative análise de tempo;
- use um tempo mínimo moderado;
- mantenha logs ativos no início;
- use o modo simulação ao testar regras novas;
- ative o StopForumSpam em modo de teste ou somente log antes de aplicar bloqueios definitivos;
- configure retenção automática de logs;
- ative a confirmação de conta por e-mail no painel AntiSpam Guard somente depois de configurar e testar o envio de e-mail do phpBB;
- se administrar o servidor ou CDN, aplique um dos modelos em `contrib/edge-protection/` inicialmente no modo de observação.

---

### Requisitos

- phpBB 3.3.0 ou superior;
- PHP 7.1.3 ou superior.

---

### Status

Versão atual: **3.3.89**  
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

By default, a direct block requires a strongly listed e-mail, or a strong IP and username together. Partial matches (IP only, username only, or several weak hits) follow **Partial match action**: review only (default), add to score, or block.

The integration has its own ACP page and stores its actions in a dedicated log. Since version **3.3.19**, these records are also displayed on the **Blocking logs** page, in addition to the dedicated StopForumSpam page.

#### Silent mode

Can hide the real blocking reason, reducing clues that bots can use to adapt.

#### Simulation mode

Logs what would have been blocked without preventing the submission. Useful for testing new rules before enabling real blocking.

#### Logs and statistics

The ACP includes logs, filters, CSV and JSON export, statistics, and installation diagnostics. Under **Block logs**, **Export CSV** and **Export JSON** sit next to **Apply filters** and honour the selected filters. **Other exports** produces CSV files for the registration audit, reports sent to StopForumSpam and SFS log reviews.

Under **Alerts and summaries**, save the destination and use **Send test email** or **Send Telegram test**. Each action uses the saved destination and sends a labeled test message without log data.

Under **Delivery history**, filter attempts from the last 30 days by channel, type, result, or date. The **From** and **Through** filters use UTC dates and include the end date. Open **Daily delivery activity** to view daily counts. Select a date to view or export that day’s attempts with the other filters preserved. The summary counts accepted, failed, and unconfirmed attempts by channel across the entire filtered result. New attempts show the reason for a failure when known. Select a reason under **Most frequent diagnostics** to filter the list and CSV while keeping the other filters. Use **Delivery unconfirmed** to find attempts without a conclusive response. Select **Export delivery history (CSV)** to download all retained records matching the selected filters. The file uses UTC timestamps and diagnostic codes.

Under **Block spike alert**, select **Preview last 7 days** to estimate how many alerts the entered rule would have generated from retained records. The preview checks once per minute and applies the interval between alerts.

Under **Notification cron**, check the latest run per channel, overdue status, and the next summary window in UTC. General logs and StopForumSpam logs can be reviewed in the ACP with their own filters.

Under **Statistics**, select an hourly or daily activity bar to open the corresponding log for that UTC interval. Under **Block logs**, select an IP address to review its current local reputation and retained block history. IP and date filters apply to both logs, and each CSV export uses the filters for its respective table.

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
3. Clear the phpBB cache.
4. Re-enable the extension through the ACP.
5. Check the **About / Diagnostics** tab.
6. After updating to version **3.3.89**, confirm the migration under **About / Diagnostics** and run the main log self-test.

---

### Recommended initial setup

For a balanced configuration:

- enable HoneyPot;
- enable timing analysis;
- use a moderate minimum time;
- keep logs enabled at first;
- use simulation mode when testing new rules;
- enable StopForumSpam in test mode or log-only mode before applying definitive blocks;
- configure automatic log retention;
- enable email account confirmation in the AntiSpam Guard panel only after configuring and testing phpBB email delivery;
- if you manage the server or CDN, apply one of the `contrib/edge-protection/` templates in observation mode first.

---

### Requirements

- phpBB 3.3.0 or newer;
- PHP 7.1.3 or newer.

---

### Status

Current version: **3.3.89**  
Status: production-ready.

---

## License

GPL-2.0-only
