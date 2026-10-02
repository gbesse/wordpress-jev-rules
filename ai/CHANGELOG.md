## 2026-10-02 — v0.1.1

Add a synthetic low-probability billing fixture and replay it in the WordPress integration test.

# AI change log

This file records the purpose and technical decisions of agent-authored changes.

## 2026-09-21 — v0.1.0

- Implemented `wordpress-jev-rules` as a native host integration with a runnable example and synthetic verification.
- Reused DecisionPacks directly for JavaScript, or ported its finite gates with reference cases for PHP/Python; retained MIT attribution.
- Added bounded provider calls, pinned model checks and explicit failure handling. Host authorization remains with the integrating application.
- Documented verified scope and alpha limitations. Tests and type/syntax checks only; no production build or live inference performed.
- Validation: WordPress 7.1.1 / PHP 8.3.33 Playground: 54 assertions, including 38 reference gate cases.
