# moodle-local_libreria

Small site-wide tweaks for the Librería de Satoshi Moodle instance. Installed as
a git submodule at `public/local/libreria` in
[`custom-moodle`](https://github.com/LibreriadeSatoshi/custom-moodle).

## Simplified email signup

An optional setting keeps **email and password** visible and moves username,
first name, last name, city, country and optional custom profile fields into a collapsed
**Additional details (optional)** section. It uses native HTML details, so it
works with the keyboard and without JavaScript. The section opens after a
submission to expose validation errors.

Blank standard fields receive these server-side defaults before Moodle validates
the form:

| Field | Default when blank |
| --- | --- |
| Username | Full email address, lowercased |
| First name | Email address |
| Last name | `.` |

Explicitly entered values are preserved. Domains and special characters are not
removed from generated usernames. Existing accounts are not rewritten, and
changing an account email later does not automatically change its username or
name. The email-based display name is visible wherever the configured Moodle
name format displays the first name.

The plugin uses the existing `extend_signup_form` callback. Moodle still handles
password policy, duplicate accounts, email restrictions, CAPTCHA, session keys,
site-policy consent, account creation and email confirmation. Custom profile
fields keep their existing visibility and requirements. Required custom fields stay
outside the optional section. The repeated email input is removed; the plugin
supplies its value to core validation from the email input. Users still receive
and must follow Moodle's confirmation email.

### Enable

After deploying and upgrading the plugin:

1. In Site administration > Security > Site security settings, enable **Allow
   extended characters in usernames**. This is needed for emails containing `+`
   and other valid email characters. It broadens the existing username character
   policy; the plugin does not modify that global setting automatically.
2. In Site administration > Plugins > Local plugins > Librería de Satoshi tweaks,
   enable **Simplified email signup**. It is off by default.

Equivalent CLI commands, from the Moodle checkout and using the web-server user:

```bash
sudo -u www-data php admin/cli/upgrade.php --non-interactive
sudo -u www-data php admin/cli/cfg.php --name=extendedusernamechars --set=1
sudo -u www-data php admin/cli/cfg.php --component=local_libreria --name=simplifiedsignup --set=1
sudo -u www-data php admin/cli/purge_caches.php
```

The default username is the email address, so those accounts can log in using
that address without changing authentication settings. If users enter a custom
username and should also be able to log in with email, Moodle's existing **Allow
log in via email** setting must already be enabled or be enabled separately.

**Rollback:** turn off Simplified email signup to restore the original form. New
accounts already created remain usable. Keep extended username characters enabled
while accounts with such characters exist.

**Scope:** browser email self-registration only. Other authentication methods,
including Nostr and OAuth, are unchanged. The callback does not apply to mobile
app/web-service registration or ordinary profile editing.

## Pseudonym note on the standard signup form

When simplified signup is off, the original behavior adds a note above the name
fields:

> You may use a pseudonym instead of your real name. Whatever you enter here is
> what will appear on your course certificates.

Name display and certificate generation retain the existing site configuration.

## Testing

With a configured Moodle PHPUnit environment:

```bash
php vendor/bin/phpunit public/local/libreria/tests/signup_test.php
php vendor/bin/phpunit --testsuite local_libreria_testsuite,auth_email_testsuite,auth_oauth2_testsuite
```

The signup tests exercise the real form, account creation, confirmation email,
confirmation and login. They cover explicit/blank/omitted details, duplicates,
invalid email, password policy, consent, session keys, feature disablement and
the collapsed/expanded form structure.

Before enabling on a site, check desktop and mobile signup with its theme: submit
only email/password, follow the confirmation link, log out and log in again.
Expand the details and repeat using a custom username and names. Check that optional
custom fields appear in the same section and required custom fields remain visible
and enforce their validation. Confirm there is no repeated email input. Test keyboard interaction
with the section and ensure required policy/CAPTCHA controls remain visible.

## Deploying

Deploy the plugin commit and update the parent repository's submodule reference.
Then use the normal site deployment process and enable the settings above.

```bash
~/moodle/deploy-prod.sh --dry-run
~/moodle/deploy-prod.sh
```

## License

MIT - see [LICENSE](LICENSE). PHP file headers carry the GPL notice per Moodle
convention.
