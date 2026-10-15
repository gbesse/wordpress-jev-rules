# Jev Rules for WordPress

A WordPress plugin for queued post classification, versioned rules and an administrator review history. It uses native cron, capabilities, nonces, HTTP and error-mail facilities.

**v0.1.2 experimental alpha · MIT · PHP 8.1+**. Tested in WordPress 7.1.1 with PHP 8.3.33. Independent community integration.

## Install

Download the v0.1.2 GitHub release ZIP, unzip it as `wp-content/plugins/wordpress-jev-rules`, then activate **Jev Rules**. Add your Typesafe API key to server configuration, for example in `wp-config.php`:

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

## Compare accepted and review fixtures

`examples/synthetic-billing-response.json` gives billing probability 0.94; `examples/synthetic-billing-review.json` gives 0.60 despite confidence 0.99. The same `packs/support-triage.json` routes the first to billing and the second to review. Run `npm test` to replay both in WordPress Playground. These responses are synthetic, not live Jev measurements.

## October 2026 improvement · Amélioration d’octobre 2026 · Mejora de octubre de 2026

Run `npm run demo:fixtures` to compare the bundled synthetic billing response (0.94) with the review case (0.60) against the declared 0.90 threshold, without starting WordPress. `npm test` remains the plugin integration check.

Exécutez `npm run demo:fixtures` pour comparer les réponses de facturation synthétiques 0,94 et 0,60 au seuil déclaré de 0,90, sans démarrer WordPress. `npm test` reste le contrôle d’intégration du plugin.

Ejecute `npm run demo:fixtures` para comparar las respuestas sintéticas de facturación 0,94 y 0,60 con el umbral declarado de 0,90, sin iniciar WordPress. `npm test` sigue siendo la comprobación de integración del complemento.

## Contrôle d’adoption · Adoption check · Comprobación de adopción

[Français : essayer un cas concret](examples/adoption-check.md) · [English: try a concrete case](examples/adoption-check.md) · [Español: pruebe un caso concreto](examples/adoption-check.md).

## Private phased failures / Échecs privés par étape / Fallos privados por etapa

EN: Failure receipts now distinguish decision execution from `consumer_hook` failures after a valid result. Persisted admin errors, standard logs, notification bodies and thrown messages use fixed FR/EN/ES diagnostics without arbitrary provider/consumer text. The explicit local `jev_rules_error` hook still receives the original exception; its own failure cannot replace the safe public error. Computed results remain inspectable after a consumer failure, but external hook effects cannot be rolled back or assumed absent. Tests run in isolated WordPress Playground with HTTP and email intercepted; no real email or production post is modified.

FR : Les reçus distinguent désormais calcul de décision et échec `consumer_hook` après résultat valide. Erreurs admin, logs standards, corps de notification et exceptions utilisent des diagnostics fixes FR/EN/ES, sans texte fournisseur/consommateur arbitraire. Le hook local explicite `jev_rules_error` reçoit encore l’exception originale ; son échec ne remplace pas l’erreur publique sûre. Le résultat calculé reste consultable, sans pouvoir annuler ni supposer absents les effets externes du hook. Tests WordPress Playground isolés, HTTP/emails interceptés ; aucun email réel ni post de production modifié.

ES: Recibos distinguen cálculo de decisión y fallos `consumer_hook` tras resultado válido. Errores admin, logs estándar, notificaciones y excepciones usan diagnósticos fijos FR/EN/ES sin texto arbitrario del proveedor/consumidor. Hook local explícito `jev_rules_error` sigue recibiendo excepción original; su fallo no sustituye error público seguro. Resultado sigue visible, sin poder revertir ni asumir ausentes efectos externos. Pruebas en WordPress Playground aislado, HTTP/emails interceptados; sin emails reales ni posts de producción modificados.
