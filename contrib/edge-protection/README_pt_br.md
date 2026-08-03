# Proteção antes do PHP

Estes modelos reduzem requisições repetidas ao cadastro antes que o phpBB e o PHP sejam executados. Eles não são instalados automaticamente pela extensão: escolha **uma** camada compatível com sua infraestrutura, teste e só então ative o bloqueio.

## Perfil inicial recomendado

- Aplique apenas a `POST /ucp.php?mode=register`.
- Permita uma rajada de 5 envios e recupere capacidade lentamente.
- Comece em observação por 24 a 48 horas.
- Retorne HTTP 429 ou desafio gerenciado depois de confirmar que não há falsos positivos.
- Mantenha a limitação interna do AntiSpam Guard ativa como segunda camada.

## Arquivos

- `cloudflare-rate-limit.md`: regras por plano do Cloudflare.
- `nginx-antispamguard.conf.example`: `limit_req` seletivo; requisições fora do cadastro usam chave vazia e não são contadas.
- `apache-modsecurity-antispamguard.conf.example`: contador persistente por IP para Apache com ModSecurity 2.x.

## IP real e proxies

O limite só funciona corretamente se a camada enxergar o IP real. Ao usar CDN ou proxy, configure explicitamente os proxies confiáveis. Nunca confie em `X-Forwarded-For` ou `CF-Connecting-IP` vindo de qualquer origem. No Nginx, use o módulo Real IP e somente as faixas oficiais do seu proxy/CDN; no Apache, configure `mod_remoteip` com a mesma restrição.

## Validação e reversão

1. Faça backup da configuração do servidor.
2. Valide a sintaxe (`nginx -t` ou teste equivalente do Apache/ModSecurity).
3. Ative o modo de observação e acompanhe logs.
4. Teste cadastro normal, erro de formulário e reenvio.
5. Ative 429/desafio apenas após validar.
6. Para reverter, remova o include/regra e recarregue o serviço.

Os limites são um ponto de partida. Fóruns com muitas pessoas sob o mesmo NAT, escolas, empresas ou provedores móveis podem precisar de uma rajada maior.

Referências oficiais:

- Cloudflare: https://developers.cloudflare.com/waf/rate-limiting-rules/
- Nginx: https://nginx.org/en/docs/http/ngx_http_limit_req_module.html
- ModSecurity 2.x: https://github.com/owasp-modsecurity/ModSecurity/wiki/Reference-Manual-(v2.x)
