# Protection before PHP

These templates reduce repeated registration requests before phpBB and PHP run. The extension does not install them automatically: select **one** layer supported by your infrastructure, test it, and only then enable blocking.

Recommended starting profile: match only `POST /ucp.php?mode=register`, allow a burst of five submissions, observe for 24–48 hours, then return HTTP 429 or a managed challenge. Keep AntiSpam Guard's internal limiter enabled as a second layer.

If a CDN or proxy is present, configure the real client IP using only explicitly trusted proxy ranges. Never trust arbitrary forwarding headers. Validate the web-server configuration and test normal registration, validation errors, and resubmission before enforcement.

Official references:

- Cloudflare: https://developers.cloudflare.com/waf/rate-limiting-rules/
- Nginx: https://nginx.org/en/docs/http/ngx_http_limit_req_module.html
- ModSecurity 2.x: https://github.com/owasp-modsecurity/ModSecurity/wiki/Reference-Manual-(v2.x)
