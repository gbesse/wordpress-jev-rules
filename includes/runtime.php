<?php
/** Purpose: Persist explicit post snapshots, run bounded Jev requests and report errors without applying content changes. */
function jev_rules_registry(): array {
    $packs = get_option('jev_rules_packs', []);
    return apply_filters('jev_rules_packs', is_array($packs) ? $packs : []);
}
function jev_rules_state(WP_Post $post): array {
    return apply_filters('jev_rules_post_state', ['text'=>wp_strip_all_tags($post->post_title . "\n" . $post->post_content)], $post);
}
function jev_rules_hash($value): string { return hash('sha256', wp_json_encode($value, JSON_THROW_ON_ERROR)); }
function jev_rules_enqueue(int $post_id, string $rule): int {
    $post = get_post($post_id); $registry = jev_rules_registry();
    \JevRules\DecisionPacks::check($post && $post->post_type !== 'jev_decision' && isset($registry[$rule]), 'Unknown post or rule');
    $pack = $registry[$rule]; $state = jev_rules_state($post);
    \JevRules\DecisionPacks::validate($pack); \JevRules\DecisionPacks::state($pack, $state);
    $data = ['post_id'=>$post_id,'rule'=>$rule,'pack'=>$pack,'state'=>$state,'input_hash'=>jev_rules_hash($state)];
    $job = wp_insert_post(wp_slash(['post_type'=>'jev_decision','post_status'=>'private','post_title'=>$rule . ' / ' . $post_id,'post_content'=>wp_json_encode($data, JSON_THROW_ON_ERROR)]), true);
    if (is_wp_error($job)) { throw new RuntimeException($job->get_error_message()); }
    update_post_meta($job, '_jev_status', 'queued');
    $scheduled = wp_schedule_single_event(time()+1, 'jev_rules_process', [$job], true);
    if (is_wp_error($scheduled) || !$scheduled) { update_post_meta($job, '_jev_status', 'failed'); throw new RuntimeException('Could not schedule Jev job'); }
    return $job;
}
function jev_rules_current(array $data): bool {
    $post = get_post($data['post_id']); $registry = jev_rules_registry();
    return $post && isset($registry[$data['rule']]) && jev_rules_hash($registry[$data['rule']]) === jev_rules_hash($data['pack']) && jev_rules_hash(jev_rules_state($post)) === $data['input_hash'];
}
function jev_rules_evaluate(array $pack, array $state): array {
    \JevRules\DecisionPacks::validate($pack); \JevRules\DecisionPacks::state($pack, $state);
    $key = defined('JEV_RULES_API_KEY') ? JEV_RULES_API_KEY : getenv('TYPESAFE_API_KEY');
    \JevRules\DecisionPacks::check(is_string($key) && $key !== '', 'Configure JEV_RULES_API_KEY or TYPESAFE_API_KEY server-side');
    $started = microtime(true);
    $response = wp_remote_post('https://api.typesafe.ai/v1/systemone', ['timeout'=>30,'redirection'=>0,'limit_response_size'=>1048576,'headers'=>['Authorization'=>'Bearer ' . $key,'Content-Type'=>'application/json'],'body'=>wp_json_encode(['model'=>$pack['model'],'state'=>$state,'questions'=>$pack['questions']], JSON_THROW_ON_ERROR)]);
    if (is_wp_error($response)) { throw new RuntimeException($response->get_error_message()); }
    \JevRules\DecisionPacks::check(wp_remote_retrieve_response_code($response) === 200, 'Jev HTTP error: ' . wp_remote_retrieve_response_code($response));
    $result = json_decode(wp_remote_retrieve_body($response), true, 512, JSON_THROW_ON_ERROR);
    \JevRules\DecisionPacks::check(($result['model'] ?? null) === $pack['model'], 'Provider model mismatch');
    $decision = \JevRules\DecisionPacks::decide($pack, $state, $result['answers'] ?? []);
    return ['schemaVersion'=>1,'id'=>wp_generate_uuid4(),'timestamp'=>gmdate('c'),'model'=>$pack['model'],'pack'=>['name'=>$pack['name'],'version'=>$pack['version'],'fingerprint'=>jev_rules_hash($pack)],'questionFingerprint'=>jev_rules_hash(['model'=>$pack['model'],'questions'=>$pack['questions']]),'inputFingerprint'=>jev_rules_hash($state),'answers'=>$result['answers'],'latencyMs'=>round((microtime(true)-$started)*1000,2)] + $decision;
}
function jev_rules_process(int $job_id): void {
    $job = get_post($job_id);
    if (!$job || $job->post_type !== 'jev_decision' || get_post_meta($job_id, '_jev_status', true) !== 'queued') { return; }
    // add_option uses a unique database key, unlike a check-then-set transient lock.
    if (!add_option('jev_rules_lock_' . $job_id, time(), '', false)) { return; }
    try {
        if (get_post_meta($job_id, '_jev_status', true) !== 'queued') { return; }
        update_post_meta($job_id, '_jev_status', 'running');
        $data = json_decode($job->post_content, true, 512, JSON_THROW_ON_ERROR);
        if (!jev_rules_current($data)) { update_post_meta($job_id, '_jev_status', 'stale'); return; }
        $decision = jev_rules_evaluate($data['pack'], $data['state']);
        update_post_meta($job_id, '_jev_result', $decision);
        if (!jev_rules_current($data)) { update_post_meta($job_id, '_jev_status', 'stale'); return; }
        update_post_meta($job_id, '_jev_status', 'succeeded');
        // Consumers own side effects and authorization; this hook does not publish or modify the source post.
        do_action('jev_rules_decided', $decision, $data['post_id'], $data['rule'], $job_id);
    } catch (Throwable $error) {
        update_post_meta($job_id, '_jev_status', 'failed'); update_post_meta($job_id, '_jev_error', $error->getMessage());
        error_log('Jev Rules job ' . $job_id . ': ' . $error->getMessage());
        $environment = defined('JEV_RULES_ENVIRONMENT') ? JEV_RULES_ENVIRONMENT : 'prod';
        if (!wp_mail(get_option('admin_email'), '[wordpress-jev-rules][' . $environment . '] DecisionError', 'Job ' . $job_id . ': ' . get_class($error) . ': ' . $error->getMessage())) { error_log('Jev Rules administrator email delivery failed for job ' . $job_id); }
        do_action('jev_rules_error', $error, $job_id);
        throw $error;
    } finally { delete_option('jev_rules_lock_' . $job_id); }
}
