# Reproduction: overblog/GraphQLBundle #1205

> Access denied / Internal server error when using `#[GQL\Provider]` + `#[GQL\Query]` with PHP attributes

**Bundle version:** `overblog/graphql-bundle` v1.9.0
**Symfony version:** 6.4
**Issue:** https://github.com/overblog/GraphQLBundle/issues/1205

---

## The bug

When declaring GraphQL query fields using `#[GQL\Provider]` + `#[GQL\Query]` (the PHP attribute way), the field resolves with an internal error at runtime:

```
"App\GraphQL\QueryProvider" service or alias has been removed or inlined when
the container was compiled. You should either make it public, or stop using
the container directly and use dependency injection instead.
```

The generated type code calls `$container->get('App\GraphQL\QueryProvider')` directly,
but Symfony makes services **private by default** since Symfony 4. The bundle never marks
`#[GQL\Provider]` classes as public in the container.

The schema dumps correctly (`graphql:dump-schema` shows the field) but any actual query fails.

---

## Setup

```bash
php composer install
```

---

## Reproduce the bug

```bash
# Clear cache (uses the original, unpatched bundle)
php bin/console cache:clear

# Start a dev server
php -S 127.0.0.1:8099 -t public public/index.php &

# Query — this will fail
curl -s -X POST http://127.0.0.1:8099/graphql/ \
  -H "Content-Type: application/json" \
  -d '{"query":"{ hello }"}' | python3 -m json.tool
```

**Expected:**
```json
{"data": {"hello": "Hello, world!"}}
```

**Actual:**
```json
{
    "errors": [{
        "message": "Internal server Error",
        "extensions": {
            "debugMessage": "The \"App\\GraphQL\\QueryProvider\" service or alias has been removed or inlined when the container was compiled..."
        }
    }]
}
```

---

## The fix

Apply the provided patch:

```bash
patch -p1 -d vendor/overblog/graphql-bundle < fix-provider-service-visibility.patch

php bin/console cache:clear

curl -s -X POST http://127.0.0.1:8099/graphql/ \
  -H "Content-Type: application/json" \
  -d '{"query":"{ hello }"}' | python3 -m json.tool
# {"data": {"hello": "Hello, world!"}}
```

---

## Root cause

In `MetadataParser::classMetadatasToGQLConfiguration()`, when a `#[GQL\Provider]` class is
found during the pre-parse phase, the bundle adds it to `self::$providers` but never marks
its Symfony service definition as public.

The generated resolver code for provider fields always calls:
```php
$services->get('container')->get("App\\GraphQL\\QueryProvider")
```

This requires the service to be public. The fix marks the service public during the
pre-parse phase, consistent with how the bundle itself makes other services public.

**Patch:** [`fix-provider-service-visibility.patch`](./fix-provider-service-visibility.patch)

```diff
 case $classMetadata instanceof Metadata\Provider:
     if ($preProcess) {
         self::$providers[] = ['reflectionClass' => $reflectionClass, 'metadata' => $classMetadata];
+        if ($container->hasDefinition($reflectionClass->getName())) {
+            $container->findDefinition($reflectionClass->getName())->setPublic(true);
+        }
     }
```
