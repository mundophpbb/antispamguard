# Changelog do AntiSpam Guard

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
