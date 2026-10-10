# wordpress-jev-rules — contrôle d’adoption · adoption check · comprobación de adopción

## Français

Point de départ local, après la préparation indiquée dans le README :

```sh
npm run demo:fixtures
```

Comparez un résultat accepté et un résultat à revoir avant de configurer une règle réelle. Un article modifié après mise en file doit rester à revalider ; aucune publication automatique ne découle du verdict.

## English

Local starting point, after the setup described in the README:

```sh
npm run demo:fixtures
```

Compare an accepted result with a review result before configuring a real rule. A post changed after queueing needs revalidation; a verdict does not publish it automatically.

## Español

Punto de partida local, después de la preparación descrita en el README:

```sh
npm run demo:fixtures
```

Compare un resultado aceptado con otro para revisión antes de configurar una regla real. Una entrada cambiada tras entrar en la cola requiere nueva validación; el dictamen no la publica automáticamente.
## Variante synthétique · Synthetic variation · Variante sintética

```text
queued_post_version=1; current_post_version=2
```

FR : adaptez une copie de la fixture locale à cette situation, puis vérifiez le comportement décrit ci-dessus. Les valeurs sont illustratives, pas des résultats Jev mesurés.

EN: adapt a copy of the local fixture to this situation, then check the behavior described above. Values are illustrative, not measured Jev output.

ES: adapte una copia de la fixture local a esta situación y compruebe el comportamiento descrito arriba. Los valores son ilustrativos, no resultados Jev medidos.

## Second cas · Second case · Segundo caso

```text
http_result=timeout; queued_post_id=42
```

**FR :** Un délai réseau dépassé doit laisser le job en échec visible, sans publier ni appliquer de décision par défaut. Inspectez la notification administrateur et la revue.

**EN:** A network timeout should leave a visible failed job without publishing or applying a default decision. Inspect administrator notification and review.

**ES:** Un tiempo de espera de red agotado debe dejar un trabajo fallido visible, sin publicar ni aplicar una decisión predeterminada. Revise la notificación al administrador y la revisión.
