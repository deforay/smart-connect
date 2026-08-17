# How to manage the enrollment key

The enrollment key is the one shared secret that lets InteLIS installations enroll themselves. This guide covers generating it, reading it back, and rotating it.

The key lives at `api.enrollment_key` in `config/autoload/custom.global.php`. For the full option list, see the [API v2 reference](../api/index.md#enrollment-key).

## Prerequisites

- Shell access to the Smart Connect server
- Write access to `config/autoload/custom.global.php`

## Generate the key

`composer post-update` runs the generator, so a deployment that has been updated already has a key. To generate one directly:

```bash
php bin/generate-enrollment-key.php
```

The script writes a 64-character hex key and prints it once.

```text
Filled empty api.enrollment_key in /var/www/smart-connect/config/autoload/custom.global.php

  efa3b006d6f58580e5d4305e81843ba7daa6299ea320e43888ad39bf79299650
```

Running it again changes nothing.

```text
api.enrollment_key is already set (length=64). No change. Pass --force to rotate.
```

## Read the key back

```bash
php bin/generate-enrollment-key.php --show
```

The command exits 1 when no key is set.

## Rotate the key

Rotation does not affect laboratories that already hold a token. It stops any laboratory that has not yet enrolled from enrolling, until its config carries the new key.

1. Rotate.

    ```bash
    php bin/generate-enrollment-key.php --force
    ```

2. Distribute the new key to the InteLIS installer config for every laboratory that has not yet enrolled.

To check who has already enrolled before rotating, run `php bin/console api-usage`.

## Disable enrollment

Set the key to `null` in `config/autoload/custom.global.php`.

```php
'enrollment_key' => null,
```

`POST /api/v2/enroll` then returns 403. Laboratories holding a token keep working. Laboratories that have not enrolled cannot enroll, including any that needs to recover a lost token.

## Keep the key out of version control

`config/autoload/custom.global.php` holds deployment secrets. Confirm git is not tracking it.

```bash
git ls-files --error-unmatch config/autoload/custom.global.php
```

An exit code of 0 means git tracks the file, and the `.gitignore` entry has no effect. Untrack it.

```bash
git rm --cached config/autoload/custom.global.php
```

## Verify

Enroll a throwaway instance with the configured key.

```bash
KEY=$(php bin/generate-enrollment-key.php --show)
curl -s -X POST https://dashboard.example.org/api/v2/enroll \
  -H 'Content-Type: application/json' \
  -d "{\"enrollment_key\":\"$KEY\",\"instance_uuid\":\"verify-throwaway\"}"
```

A 201 response confirms the key works. Remove the test row afterwards.

```sql
DELETE FROM dash_api_clients WHERE instance_uuid = 'verify-throwaway';
```
