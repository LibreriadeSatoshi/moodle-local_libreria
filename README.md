# moodle-local_libreria

Small site-wide tweaks for the Librería de Satoshi Moodle instance — the things
that are too small to deserve a plugin of their own, but shouldn't be hand-edited
into core or carried as a patch across upgrades.

Installed as a git submodule at `public/local/libreria` in
[`custom-moodle`](https://github.com/LibreriadeSatoshi/custom-moodle).

## What's in here

### Pseudonym note on the signup form

Moodle hardcodes `firstname` and `lastname` as required fields on the signup
form; neither can be removed or made optional through settings. Rather than
relabel the core language strings — which would change the wording in the
gradebook, participant lists and profile pages too — this adds one line above
the name fields:

> You may use a pseudonym instead of your real name. Whatever you enter here is
> what will appear on your course certificates.

The certificate sentence matters: `mod_customcert` renders these fields onto the
issued certificate, so someone choosing a pseudonym should know that up front
rather than discovering it after finishing a course.

Implemented with the `extend_signup_form` callback (Moodle 3.8+) in `lib.php`.

**Scope:** email self-registration only. Nostr logins (`auth_nostr`) never render
this form, and Moodle does not run this callback for the mobile app or the web
services API.

**Pairs with:** *Site administration → Users → Permissions → User policies* →
**Full name format** and **Alternative full name format** set to `firstname`.
Without that, someone entering the same pseudonym in both fields displays as
"Satoshi Satoshi" across the site.

## Adding another tweak

Drop it into `lib.php`, add its strings to `lang/en/` and `lang/es/`, bump
`$plugin->version` in `version.php`, and document it under "What's in here".
No new repo or submodule needed.

## Deploying

From the production checkout:

```bash
~/moodle/deploy-prod.sh --dry-run   # preview
~/moodle/deploy-prod.sh
```

## License

MIT — see [LICENSE](LICENSE). PHP file headers carry the GPL notice per Moodle
convention.
