# Cloudflare Rate Limiting — cadastro phpBB

Crie uma regra em **Security > WAF > Rate limiting rules**. Comece com ação de desafio gerenciado ou registro/observação, quando disponível, e revise os eventos antes de bloquear.

## Business ou Enterprise

Expressão exata:

```text
(http.request.method eq "POST" and http.request.uri.path eq "/ucp.php" and http.request.uri.query contains "mode=register")
```

- Característica de contagem: IP.
- Limite inicial: 5 requisições em 10 minutos.
- Mitigação: 10 minutos.
- Ação preferida: Managed Challenge; use Block se desafio não estiver disponível.

Se o fórum estiver em subdiretório, troque `/ucp.php` pelo caminho real, por exemplo `/forum/ucp.php`.

## Pro

O campo de método pode não estar disponível em regras de rate limiting desse plano. Use a query para restringir o cadastro:

```text
(http.request.uri.path eq "/ucp.php" and http.request.uri.query contains "mode=register")
```

- Característica: IP.
- Limite inicial: 5 requisições em 60 segundos.
- Mitigação: 10 minutos.

A consulta também conta GETs da página de cadastro. Por isso, acompanhe os eventos e aumente a rajada se necessário.

## Free

As regras de rate limiting podem permitir apenas correspondência por caminho e uma janela curta. Não limite todo `/ucp.php`, pois esse arquivo também atende login e outras funções. Prefira o modelo Nginx/Apache deste diretório ou faça upgrade para uma regra capaz de filtrar a query.

## Observações

- Os campos, períodos e ações disponíveis dependem do plano.
- O Cloudflare pode deixar algumas requisições excedentes chegarem à origem durante a atualização dos contadores; mantenha o limitador interno da extensão.
- Referência oficial: https://developers.cloudflare.com/waf/rate-limiting-rules/
