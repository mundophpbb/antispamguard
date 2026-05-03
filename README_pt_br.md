# AntiSpam Guard

## O que é?

O **AntiSpam Guard** é uma extensão para phpBB desenvolvida pela **Mundophpbb** para proteger fóruns contra spam de forma invisível, sem depender de CAPTCHA tradicional.

A extensão combina HoneyPot, análise de tempo, controle por IP, filtros de conteúdo, integração com StopForumSpam, logs, estatísticas e diagnóstico no ACP.

---

## Para que serve?

O AntiSpam Guard ajuda a evitar:

- registros automatizados;
- postagens de spam;
- mensagens privadas maliciosas;
- abuso no formulário de contato;
- envios rápidos demais ou suspeitos;
- excesso de URLs;
- IPs bloqueados ou abusivos;
- remetentes suspeitos identificados pelo StopForumSpam.

Tudo isso com o menor impacto possível para usuários reais.

---

## Como funciona?

A extensão utiliza múltiplas camadas de proteção:

- **HoneyPot:** adiciona um campo invisível aos formulários para capturar bots que preenchem todos os campos.
- **Análise de tempo:** detecta envios rápidos demais ou fora do intervalo configurado.
- **Controle por IP:** permite whitelist, blacklist, CIDR e limite por IP.
- **Filtro de conteúdo:** bloqueia termos suspeitos e limita URLs.
- **StopForumSpam:** consulta IP, e-mail e/ou nome de usuário em base externa anti-spam.
- **Modo discreto:** oculta o motivo real do bloqueio.
- **Modo simulação:** registra o que seria bloqueado sem impedir o envio.
- **Logs e estatísticas:** registra eventos, permite filtros, exportação CSV e análise no ACP.
- **Limpeza automática:** remove logs antigos via cron do phpBB conforme a retenção configurada.

---

## StopForumSpam

A integração com **StopForumSpam** possui página própria no ACP e log separado.

A partir da versão **3.3.19**, os logs do StopForumSpam aparecem em dois locais:

- na página própria do **StopForumSpam**;
- na página **Logs de bloqueio**.

Também há filtros e exportação CSV específicos para os registros do StopForumSpam.

---

## Recursos

- Proteção no registro;
- proteção em postagens, respostas e edições;
- proteção em mensagens privadas;
- proteção no formulário de contato;
- HoneyPot configurável;
- análise de tempo mínimo e máximo;
- filtro de palavras e termos suspeitos;
- limite de URLs;
- whitelist e blacklist de IPs;
- rate limit por IP;
- integração com StopForumSpam;
- logs próprios do StopForumSpam;
- logs do StopForumSpam na página própria e em **Logs de bloqueio**;
- filtros e exportação CSV dos logs do StopForumSpam;
- envio manual ao StopForumSpam pelo ACP, com preenchimento a partir dos logs SFS e auditoria interna;
- dashboard visual no ACP;
- filtros e paginação de logs;
- exclusão individual, em massa e por filtro;
- exportação CSV;
- importação/exportação de configurações;
- estatísticas visuais;
- diagnóstico da instalação;
- limpeza automática via cron;
- suporte multilíngue;
- suporte a permissões ACP.

---

## Instalação

1. Envie os arquivos para:

```text
/ext/mundophpbb/antispamguard
```

2. Acesse o ACP do phpBB.
3. Vá em **Personalizar > Gerenciar extensões**.
4. Ative **AntiSpam Guard**.
5. Configure conforme necessário.

---

## Atualização

1. Desative a extensão no ACP sem apagar dados, salvo se desejar remover tudo.
2. Substitua os arquivos da extensão.
3. Ative novamente ou execute a atualização pelo ACP.
4. Limpe o cache do phpBB.
5. Verifique a aba **Sobre / Diagnóstico**.
6. Após atualizar para a versão **3.3.26**, confirme se os logs do StopForumSpam aparecem tanto na página própria quanto em **Logs de bloqueio**.

---

## Recomendação inicial

Para melhor proteção:

- ative HoneyPot;
- ative análise de tempo;
- use um tempo mínimo moderado;
- mantenha os logs ativos no início;
- teste novas regras com modo simulação;
- ative o StopForumSpam em modo de teste ou somente log antes de aplicar bloqueios definitivos;
- configure retenção automática de logs.

---

## Requisitos

- phpBB 3.3.0 ou superior;
- PHP 7.1.3 ou superior.

---

## Status

Versão atual: **3.3.26**  
Status: pronto para uso em produção.

---

## Licença

GPL-2.0-only
