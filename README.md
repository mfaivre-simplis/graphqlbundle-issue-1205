# Reproduction: overblog/GraphQLBundle #1205

> `#[GQL\Access('isAnonymous()')]` on a Provider always returns "Access denied to this field"

**Bundle versions affected:** `overblog/graphql-bundle` v1.7.0 and v1.9.0
**Symfony version:** 6.4
**Issue:** https://github.com/overblog/GraphQLBundle/issues/1205

---

## The bug

When declaring a GraphQL query field via `#[GQL\Provider]` + `#[GQL\Access('isAnonymous()')]`,
every request returns "Access denied to this field" — even for fully unauthenticated requests.

```php
#[GQL\Provider]
#[GQL\Access('isAnonymous()')]
final readonly class ConnectedUserQuery
{
    #[GQL\Query(name: 'connectedUser', type: 'Boolean!')]
    #[GQL\Access('isAnonymous()')]
    public function __invoke(): bool
    {
        return true;
    }
}
```

**Root cause:** `BaseSecurity::isAnonymous()` calls `isGranted('IS_AUTHENTICATED_ANONYMOUSLY')`.
In Symfony 6.x, `IS_AUTHENTICATED_ANONYMOUSLY` was **removed from `AuthenticatedVoter`** — it is
no longer in `supportsAttribute()`. No voter handles it, so the access decision manager abstains
and returns `false`. Therefore `isAnonymous()` always returns `false` in Symfony 6.x regardless
of whether the user is authenticated.

The correct replacement since Symfony 5.4 is `PUBLIC_ACCESS`, which always grants access.

> **Note:** this reproduction also requires setting the Provider service to `public: true`
> in `services.yaml` as a workaround for a separate (known) issue where the bundle fetches
> provider services via `$container->get()` at runtime.

---

## Setup

```bash
php composer install
```

---

## Reproduce the bug (before applying the fix)

Revert the patch first:

```bash
patch -R -p1 -d vendor/overblog/graphql-bundle < fix-is-anonymous-symfony6.patch
php bin/console cache:clear

# Start dev server
php -S 127.0.0.1:8099 -t public public/index.php &

# Query — returns "Access denied to this field"
curl -s -X POST http://127.0.0.1:8099/graphql/ \
  -H "Content-Type: application/json" \
  -d '{"query":"{ connectedUser }"}' | python3 -m json.tool
```

**Actual (buggy) response:**
```json
{
    "extensions": {
        "warnings": [{
            "message": "Access denied to this field.",
            "path": ["connectedUser"]
        }]
    }
}
```

---

## Apply the fix

```bash
patch -p1 -d vendor/overblog/graphql-bundle < fix-is-anonymous-symfony6.patch
php bin/console cache:clear

curl -s -X POST http://127.0.0.1:8099/graphql/ \
  -H "Content-Type: application/json" \
  -d '{"query":"{ connectedUser }"}' | python3 -m json.tool
```

**Expected (fixed) response:**
```json
{"data": {"connectedUser": true}}
```

---

## The fix

**File:** `src/Security/Security.php` — same change applies to both v1.7.0 and v1.9.0.

```diff
 public function isAnonymous(): bool
 {
-    return $this->isGranted('IS_AUTHENTICATED_ANONYMOUSLY');
+    // IS_AUTHENTICATED_ANONYMOUSLY was removed from AuthenticatedVoter in Symfony 6.x.
+    // PUBLIC_ACCESS (added in Symfony 5.4) is the correct replacement and always grants access.
+    return $this->isGranted(Kernel::VERSION_ID >= 60000 ? 'PUBLIC_ACCESS' : 'IS_AUTHENTICATED_ANONYMOUSLY');
 }
```

**Patch file:** [`fix-is-anonymous-symfony6.patch`](./fix-is-anonymous-symfony6.patch)
