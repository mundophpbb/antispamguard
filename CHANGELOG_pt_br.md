# Changelog

## 3.3.66 - Compatibilidade do cliente HTTP

- Removida a dependência do serviço inexistente `http_client`, que impedia a compilação do container em algumas instalações phpBB 3.3.
- O cliente StopForumSpam agora cria o Guzzle sob demanda e reutiliza a instância, sem retornar ao transporte direto por `file_get_contents`.
- Adicionado teste de regressão para a inicialização sem injeção do serviço HTTP e validação estática do `services.yml`.

## 3.3.65 - Borda, confirmação por e-mail e transporte HTTP

- Chamadas HTTP diretas substituídas pelo cliente compartilhado do phpBB, sem redirecionamentos e com limite de resposta remota.
- Status, parâmetros e restauração manual do circuit breaker StopForumSpam adicionados ao ACP.
- Ação protegida no ACP para ativar confirmação de conta por e-mail; a extensão impede a ativação enquanto o envio de e-mail estiver desligado.
- Modelos conservadores de rate limit para Cloudflare, Nginx e Apache/ModSecurity, com observação antes do bloqueio.
- Testes ampliados para transporte HTTP, repetição, status inválido e resposta excessiva.

## 3.3.64 - Decisão SFS segura e gravações atômicas

- Ocorrência isolada de IP ou username no StopForumSpam passa a ser registrada para revisão, sem bloqueio direto.
- Bloqueio SFS exige e-mail fortemente listado ou a combinação forte de username + IP.
- Adicionadas chaves únicas ao rate limit, reputação local e cache SFS.
- Contadores deixam de usar leitura/exclusão/inserção sujeita a corrida e passam a usar inserção condicional e atualização atômica.
- Migração consolida com segurança registros duplicados antigos antes de instalar os índices únicos.
- Testes ampliados para 27 verificações, incluindo gravações reais em SQLite.

## 3.3.63 - Proteção de cadastro e redução de tráfego

- Honeypot e token ausentes ou inválidos agora bloqueiam antes de consultas externas.
- IP, e-mail e usuário são consultados no StopForumSpam em uma única requisição, com cache de erro curto e circuit breaker.
- Ocorrência forte da identidade enviada bloqueia; ocorrência isolada do IP fica para revisão no cadastro tolerante.
- Corrigidos os modos de ação por pontuação/somente log, whitelist por campo, subnet IPv6 /64 e valores padrão divergentes.
- Paginação SFS passa a ocorrer no banco e consultas repetidas do ACP foram removidas.
- Limpezas automáticas continuam removendo dados antigos mesmo quando uma proteção é desativada.

## 3.3.26 - Janela ampliada para mesclagem do log principal

- Reforça a detecção de quase duplicados nos Logs de bloqueio.
- Usa uma janela curta de 60 segundos para caminhos de validação repetidos da mesma submissão.
- Compara campos estáveis da tentativa e mescla conjuntos de motivos diferentes em vez de criar uma segunda linha visível.
- Adiciona a migration `v_3_3_26` para mesclar duplicados existentes que escapavam da regra mais restrita da 3.3.25.

## 3.3.25 - Mesclagem de quase duplicados no log principal

- Melhora a deduplicação dos Logs de bloqueio.
- A deduplicação agora considera a identidade da tentativa, sem exigir que o texto do motivo seja idêntico.
- Mescla os motivos quando a mesma tentativa é gravada duas vezes com uma heurística adicional, como `slow_spam`.
- Adiciona a migration `v_3_3_25` para mesclar e limpar linhas quase duplicadas já existentes em `antispamguard_log`.
- Não requer alterações de template ou idioma.

# Changelog do AntiSpam Guard

## 3.3.24 - Refinamento visual do botão de denúncia StopForumSpam

- Encurtado o texto do botão de denúncia SFS nas tabelas de log.
- Adicionado tooltip com a descrição completa da ação.
- Adicionado estilo compacto sem quebra de linha e alinhamento central na coluna de denúncia.

Todas as alterações relevantes do AntiSpam Guard estão documentadas aqui.

## 3.3.23

### Corrigido
- Corrigido o atalho de denúncia StopForumSpam nas linhas dos logs SFS.
- A ação **Usar em denúncia SFS** agora aparece quando o log StopForumSpam contém pelo menos um dado útil: IP, e-mail ou nome de usuário.
- Antes, o atalho exigia IP, e-mail e nome de usuário ao mesmo tempo, o que escondia o botão em envios pelo formulário de contato ou em registros SFS parciais.

### Alterado
- O formulário manual de envio ao StopForumSpam continua validando a submissão final antes de enviar os dados.
- Campos obrigatórios ausentes ainda devem ser completados manualmente pelo administrador.

### Migration
- Não requer migration.

## 3.3.22

### Adicionado
- Adicionado envio manual de spammers confirmados ao StopForumSpam pelo ACP.
- Adicionado suporte à chave API do StopForumSpam para fluxos de denúncia/envio.
- Adicionada tabela interna de auditoria para envios ao StopForumSpam: `antispamguard_sfs_submit_log`.
- Adicionado registro de auditoria para denúncias manuais ao StopForumSpam, incluindo:
  - administrador responsável pelo envio;
  - IP, e-mail e nome de usuário enviados;
  - origem da denúncia;
  - ID do log SFS de origem, quando disponível;
  - status/resposta do StopForumSpam.
- Adicionado painel manual de envio StopForumSpam no ACP.
- Adicionado preenchimento automático do formulário de denúncia a partir dos logs SFS.
- Adicionada confirmação antes do envio ao StopForumSpam.
- Adicionadas strings de idioma no ACP em inglês, português e francês.

### Migration
- Adiciona a tabela de auditoria de envios ao StopForumSpam.

## 3.3.21

### Alterado
- Refinado o layout visual da paginação dos logs no ACP.
- Substituída a paginação compacta com separadores por uma estrutura visual mais limpa.
- Separados visualmente os totais, totais filtrados, informação da página atual e links de navegação.
- Adicionados botões de página, destaque da página atual, links anterior/próxima e reticências para muitas páginas.
- Aplicado o mesmo estilo de paginação em:
  - logs gerais de bloqueio;
  - logs StopForumSpam;
  - painel StopForumSpam dentro de Logs de bloqueio;
  - página própria do StopForumSpam.

### Migration
- Não requer migration.

## 3.3.20

### Corrigido
- Adicionada proteção contra duplicação na tabela própria de logs StopForumSpam.
- Reaproveita um registro SFS existente quando a mesma decisão StopForumSpam é gravada novamente em até 5 segundos.
- Evita estatísticas infladas e registros SFS repetidos causados por gravações duplicadas.

### Migration
- Adiciona a migration `v_3_3_20` para remover duplicados exatos já existentes em `antispamguard_sfs_log`.

## 3.3.19

### Corrigido
- Evita linhas duplicadas no log geral de bloqueio quando o phpBB aciona o logger duas vezes no mesmo envio.
- Adicionado bloqueio de duplicidade por 5 segundos com base em IP, nome de usuário, e-mail, tipo de formulário, motivo e user agent.

### Adicionado
- Adicionada paginação independente para logs StopForumSpam usando o parâmetro `sfs_start`.
- A paginação StopForumSpam fica separada da paginação dos logs gerais.

### Migration
- Adiciona a migration `v_3_3_19` para remover duplicados exatos já existentes em `antispamguard_log`.

## 3.3.18

### Corrigido
- Reparada a categoria/aba ACP de Extensões após ciclos de excluir dados e reinstalar.
- Garante que a categoria ACP do AntiSpam Guard fique corretamente abaixo da categoria global de Extensões do phpBB.
- Reconstrói os valores nested-set do ACP quando necessário.

## Observações para administradores

- Após atualizar de uma versão antiga, limpe o cache do phpBB.
- Se a atualização incluir migrations, execute o processo normal de atualização do banco da extensão no phpBB.
- As consultas ao StopForumSpam funcionam sem chave API.
- A chave API do StopForumSpam é usada para enviar/denunciar spammers confirmados.
- A denúncia manual deve ser usada apenas para spam confirmado, evitando falsos reportes.
