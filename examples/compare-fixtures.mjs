// Compare the bundled synthetic billing responses against the pack's explicit threshold.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const read = async path => JSON.parse(await readFile(new URL(path, import.meta.url), 'utf8'));
const pack = await read('../packs/support-triage.json');
const accepted = await read('./synthetic-billing-response.json');
const review = await read('./synthetic-billing-review.json');
const billingRule = pack.rules.find(rule => rule.id === 'billing');
const minimum = billingRule.all.find(condition => condition.field === 'answers.department.probabilities.billing').value;
const route = response => response.answers.department.choice === 'billing' && response.answers.department.probabilities.billing >= minimum ? 'billing' : pack.fallback;
assert.equal(route(accepted), 'billing');
assert.equal(route(review), 'review');
console.log(JSON.stringify({source: 'synthetic fixtures; no WordPress or Jev', threshold: minimum, accepted: route(accepted), uncertain: route(review)}));
