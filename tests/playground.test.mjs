// Purpose: Execute PHP integration assertions in an actual isolated WordPress Playground host.
import test from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { resolve } from 'node:path';
test('WordPress plugin queues, evaluates, rejects stale input and reports failures', { timeout: 230000 }, async () => {
  const output = await new Promise((res, reject) => {
    const child = spawn(process.execPath, ['node_modules/@wp-playground/cli/wp-playground.js', 'php', '--wp=7.1.1', '--php=8.3', `--mount=${resolve('.')}:/wordpress/wp-content/plugins/wordpress-jev-rules`, '--', '/wordpress/wp-content/plugins/wordpress-jev-rules/tests/wordpress-integration.php'], { timeout: 220000 });
    let text = ''; child.stdout.on('data', d => { text += d; }); child.stderr.on('data', d => { text += d; });
    child.on('error', reject); child.on('close', code => code === 0 ? res(text) : reject(new Error(`Playground exited ${code}: ${text}`)));
  });
  assert.match(output, /"status":"passed"/); console.log(output);
});
