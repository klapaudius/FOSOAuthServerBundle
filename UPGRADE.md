# UPGRADE TO 6.0

## OpenID Connect nonce and auth_time support

The OIDC `nonce` of the authorization request, and the authentication time behind the code, are now
carried by the authorization code so they can be issued in the `id_token` on the token request.
Requires `klapaudius/oauth2-php` >= 1.11.

* **Schema change**: the `AuthCode` mapping gains a nullable `nonce` column and a nullable
  `auth_time` column. Generate and run a migration (`ALTER TABLE <your_auth_code_table> ADD nonce
  VARCHAR(255) DEFAULT NULL, ADD auth_time INT DEFAULT NULL`). If your concrete `AuthCode` entity
  declares its own mapping instead of relying on the bundle's mapped-superclass, declare both
  properties there as well.
* **BC BREAK**: `AuthCodeInterface` declares `setNonce(?string $nonce)` and
  `setAuthTime(?int $authTime)`, and inherits `getNonce()` / `getAuthTime()` from
  `OAuth2\Model\IOAuth2AuthCode`. Custom implementations must provide all four (extending
  `FOS\OAuthServerBundle\Model\AuthCode` is enough).
* **BC BREAK**: `OAuthStorage::createAuthCode()` takes a 7th optional argument `$nonce`. Overrides
  must match the new signature.
* Overridden `authorize_content.html.twig` templates keep working through `form_rest(form)`; render
  `form.nonce` explicitly if you list the hidden fields one by one.
* `auth_time` is **not** populated by the bundle: only the application knows when its user actually
  authenticated. Stamp it where your auth codes are created — e.g. an
  `AuthCodeManagerInterface::createAuthCode()` override reading a login timestamp recorded in the
  session. Left null, integrators simply omit the `auth_time` claim.

# UPGRADE TO 5.2

**Note:** XML routing files are deprecated. While they continue to work for backwards compatibility,
you should migrate to YAML in your config/routes/oauth2.yaml file:

```yaml
# Old (deprecated, will trigger warnings):
resource: "@FOSOAuthServerBundle/Resources/config/routing/token.xml"

# New (recommended):
resource: "@FOSOAuthServerBundle/Resources/config/routing/token.yaml"
```

# UPGRADE TO 5.1

## BC BREAK: redirectUris and allowedGrantTypes are now json and not array anymore

This change were made to be doctrine/dbal v4 compliant.
Make sure to migrate you database schema.