# Jev Rules for WordPress

A WordPress plugin for queued post classification, versioned rules and an administrator review history. It uses native cron, capabilities, nonces, HTTP and error-mail facilities.

**v0.1.0 experimental alpha · MIT · PHP 8.1+**. Tested in WordPress 7.1.1 with PHP 8.3.33. Independent community integration.

## Install

Download the v0.1.0 GitHub release ZIP, unzip it as `wp-content/plugins/wordpress-jev-rules`, then activate **Jev Rules**. Add your Typesafe API key to server configuration, for example in `wp-config.php`:

```php
define('JEV_RULES_API_KEY', getenv('TYPESAFE_API_KEY'));
define('JEV_RULES_ENVIRONMENT', 'staging'); // Use prod on production.
```

Open **Tools → Jev Rules**. The **Rules** field expects an object keyed by your rule names. Copy the contents of `packs/support-triage.json` under `{"support": <the pack object>}` and save. Queue a post ID with the `support` rule. WordPress cron evaluates its title and text content; subsequent requests trigger cron on ordinary WordPress installations. Configure a real cron trigger if your site has little traffic or WP-Cron is disabled.

Accepted outcomes and review appear in the recent-decisions table. Posts remain unchanged. A changed post or rule makes the queued result stale; errors remain failed and are logged, emailed to the site administrator and raised. HTTP has a 30-second timeout, no redirects and a bounded response size.

Trusted plugin code may call `jev_rules_enqueue($post_id, 'support')`, customize state with the `jev_rules_post_state` filter, register packs with `jev_rules_packs`, and consume `jev_rules_decided($decision, $post_id, $rule, $job_id)`. Authorize callers and make downstream side effects idempotent. A post may still change after the final check: revalidate in the consumer before writing business data. Crashed workers may leave a running job/lock; inspect them before manually requeueing. No automatic publishing or retry is included.

## Verify

```sh
npm ci
npm test
```

The test launches an actual WordPress Playground host, activates the plugin, evaluates synthetic HTTP fixtures, checks persisted outcomes, single processing, stale input, HTTP failures, captured administrator email, the admin page, malformed probabilities, and 38 DecisionPacks reference cases (54 assertions total). No live inference or browser interaction was tested. Node.js is required only for this isolated test host, not by the installed plugin.

The PHP decision gates derive from DecisionPacks. Native PHP fingerprints are local provenance checks and are not a promise of byte-identical JavaScript replay hashes. Decisions are stored as private `jev_decision` posts and metadata; retention follows your host policy.

See [reuse and provenance](docs/reuse.md), [contributing](CONTRIBUTING.md) and [security](SECURITY.md).

[Recorded verification scope](docs/verification.md).
