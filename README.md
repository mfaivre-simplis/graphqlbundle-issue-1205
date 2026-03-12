# Reproduction: overblog/GraphQLBundle #1205

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
