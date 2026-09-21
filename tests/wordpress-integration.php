<?php
/** Purpose: Test the plugin inside real WordPress with synthetic HTTP responses and captured email delivery. */
define('JEV_RULES_API_KEY', 'synthetic-test-key');
define('JEV_RULES_ENVIRONMENT', 'staging');
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
$result = activate_plugin('wordpress-jev-rules/wordpress-jev-rules.php');
if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
do_action('init');
$checks = 0;
function check_jev($value, string $message): void { global $checks; $checks++; if (!$value) { throw new RuntimeException($message); } }
$base = dirname(__DIR__);
$pack = json_decode(file_get_contents($base . '/packs/support-triage.json'), true);
$fixture = json_decode(file_get_contents($base . '/examples/synthetic-billing-response.json'), true);
update_option('jev_rules_packs', ['support'=>$pack]);
$requests = 0; $emails = 0; $failure = false;
add_filter('pre_wp_mail', function ($return, $attributes) use (&$emails) { $emails++; return true; }, 10, 2);
add_filter('pre_http_request', function ($pre, $args, $url) use (&$requests, &$failure, $fixture) {
    if ($url !== 'https://api.typesafe.ai/v1/systemone') { return $pre; }
    $requests++;
    check_jev($args['timeout'] === 30 && $args['redirection'] === 0, 'Missing transport limits');
    $body = json_decode($args['body'], true); check_jev($body['model'] === 'jev-1.13.0', 'Unpinned model');
    return ['response'=>['code'=>$failure ? 503 : 200],'headers'=>[],'body'=>wp_json_encode($fixture),'cookies'=>[]];
}, 10, 3);
$post = wp_insert_post(['post_title'=>'Billing','post_content'=>'I was charged twice.','post_status'=>'draft']);
$job = jev_rules_enqueue($post, 'support'); check_jev(get_post_meta($job, '_jev_status', true) === 'queued', 'Not queued');
jev_rules_process($job); check_jev(get_post_meta($job, '_jev_status', true) === 'succeeded', 'Did not succeed');
check_jev(get_post_meta($job, '_jev_result', true)['outcome'] === 'billing', 'Wrong result');
check_jev(get_post_status($post) === 'draft', 'Source post was published');
jev_rules_process($job); check_jev($requests === 1, 'Completed job ran twice');
$stale = jev_rules_enqueue($post, 'support'); wp_update_post(['ID'=>$post,'post_content'=>'Changed while queued']);
jev_rules_process($stale); check_jev(get_post_meta($stale, '_jev_status', true) === 'stale' && $requests === 1, 'Stale snapshot called provider');
$failed = jev_rules_enqueue($post, 'support'); $failure = true;
try { jev_rules_process($failed); throw new RuntimeException('Expected provider failure'); } catch (InvalidArgumentException $error) { check_jev(str_contains($error->getMessage(), '503'), 'Wrong failure'); }
check_jev(get_post_meta($failed, '_jev_status', true) === 'failed' && $emails === 1, 'Failure was not persisted and reported');
check_jev(get_option('jev_rules_lock_' . $failed, null) === null, 'Lock leaked');
$answers = $fixture['answers']; $answers['department']['probabilities']['billing'] = .6; $answers['department']['probabilities']['technical'] = .3; $answers['department']['probabilities']['sales'] = .05; $answers['department']['probabilities']['other'] = .05;
check_jev(\JevRules\DecisionPacks::decide($pack, ['text'=>'fixture'], $answers)['outcome'] === 'review', 'Weak answer did not require review');
$answers['department']['probabilities']['billing'] = 2;
try { \JevRules\DecisionPacks::decide($pack, ['text'=>'fixture'], $answers); throw new RuntimeException('Invalid probability accepted'); } catch (InvalidArgumentException $error) { check_jev(true, 'Malformed answer rejected'); }
wp_set_current_user(1); ob_start(); jev_rules_admin_page(); $html = ob_get_clean();
check_jev(str_contains($html, 'Recent decisions') && str_contains($html, 'stale') && !str_contains($html, 'synthetic-test-key'), 'Admin history missing or exposed key');
if (file_exists($base . '/tests/golden-decisions.json')) {
    foreach (json_decode(file_get_contents($base . '/tests/golden-decisions.json'), true) as $case) {
        $actual = \JevRules\DecisionPacks::decide($case['pack'], $case['state'], $case['answers']);
        check_jev($actual === $case['expected'], 'Reference runtime disagreement: ' . $case['name']);
    }
}
echo wp_json_encode(['wordpress'=>$GLOBALS['wp_version'],'php'=>PHP_VERSION,'checks'=>$checks,'status'=>'passed']) . "\n";
