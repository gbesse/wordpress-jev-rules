<?php
/** Purpose: Provide nonce-protected rule configuration, explicit enqueueing and a private review history. */
function jev_rules_admin_menu(): void { add_management_page('Jev Rules', 'Jev Rules', 'manage_options', 'jev-rules', 'jev_rules_admin_page'); }
function jev_rules_admin_page(): void {
    if (!current_user_can('manage_options')) { wp_die('Forbidden', '', ['response'=>403]); }
    echo '<div class="wrap"><h1>Jev Rules</h1><p>Evaluate post text with versioned rules. Results do not modify or publish posts.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="jev_rules_save">';
    wp_nonce_field('jev_rules_save');
    echo '<h2>Rules</h2><p><label for="jev-packs">DecisionPacks keyed by rule name. API keys belong in server configuration.</label></p><textarea id="jev-packs" name="packs" rows="14" class="large-text code">' . esc_textarea(wp_json_encode(jev_rules_registry(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</textarea>';
    submit_button('Save rules'); echo '</form>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="jev_rules_enqueue">'; wp_nonce_field('jev_rules_enqueue');
    echo '<h2>Evaluate a post</h2><label>Post ID <input name="post_id" type="number" min="1" required></label> <label>Rule <select name="rule">';
    foreach (jev_rules_registry() as $name => $_pack) { echo '<option value="' . esc_attr($name) . '">' . esc_html($name) . '</option>'; }
    echo '</select></label>'; submit_button('Queue evaluation'); echo '</form><h2>Recent decisions</h2><table class="widefat"><thead><tr><th>Job</th><th>Status</th><th>Outcome</th><th>Error</th></tr></thead><tbody>';
    $jobs = get_posts(['post_type'=>'jev_decision','post_status'=>'private','numberposts'=>30]);
    foreach ($jobs as $job) { $result = get_post_meta($job->ID, '_jev_result', true); echo '<tr><td>' . esc_html($job->post_title) . '</td><td>' . esc_html(get_post_meta($job->ID, '_jev_status', true)) . '</td><td>' . esc_html($result['outcome'] ?? '') . '</td><td>' . esc_html(get_post_meta($job->ID, '_jev_error', true)) . '</td></tr>'; }
    echo '</tbody></table></div>';
}
function jev_rules_admin_save(): void {
    if (!current_user_can('manage_options')) { wp_die('Forbidden', '', ['response'=>403]); } check_admin_referer('jev_rules_save');
    $packs = json_decode(wp_unslash($_POST['packs'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    \JevRules\DecisionPacks::check(is_array($packs) && count($packs) <= 100, 'Invalid rule registry');
    foreach ($packs as $name => $pack) { \JevRules\DecisionPacks::check(is_string($name) && preg_match('/^[a-z0-9][a-z0-9_\/-]*$/', $name), 'Invalid rule name'); \JevRules\DecisionPacks::validate($pack); }
    update_option('jev_rules_packs', $packs, false); wp_safe_redirect(admin_url('tools.php?page=jev-rules')); exit;
}
function jev_rules_admin_enqueue(): void {
    if (!current_user_can('manage_options')) { wp_die('Forbidden', '', ['response'=>403]); } check_admin_referer('jev_rules_enqueue');
    $post_id = absint($_POST['post_id'] ?? 0);
    if (!current_user_can('edit_post', $post_id)) { wp_die('Forbidden', '', ['response'=>403]); }
    jev_rules_enqueue($post_id, sanitize_text_field(wp_unslash($_POST['rule'] ?? ''))); wp_safe_redirect(admin_url('tools.php?page=jev-rules')); exit;
}
