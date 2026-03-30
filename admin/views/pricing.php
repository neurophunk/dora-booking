<?php if ( ! defined('ABSPATH') ) exit;
global $wpdb;
$services = $wpdb->get_results("SELECT id, name as title FROM {$wpdb->prefix}dora_services WHERE active=1 ORDER BY sort_order ASC, name ASC");
$sel_id   = absint($_GET['service'] ?? ($services[0]->id ?? 0));
$engine   = new Dora_Pricing_Engine();
$tiers    = $engine->get_tiers($sel_id);
$config   = $engine->get_service_config($sel_id);
?>
<div class="wrap">
  <h1>DoraBooking — Árazás</h1>
  <?php if (!empty($_GET['error']) && $_GET['error'] === 'overlap'):?>
    <div class="notice notice-error"><p>Átfedő sávok! Kérjük javítsa az árazást.</p></div>
  <?php endif; ?>
  <?php if (!empty($_GET['saved'])):?>
    <div class="notice notice-success"><p>Mentve.</p></div>
  <?php endif; ?>

  <form method="get">
    <input type="hidden" name="page" value="dora-pricing">
    <select name="service" onchange="this.form.submit()">
      <?php foreach ($services as $s): ?>
        <option value="<?= absint($s->id) ?>" <?= selected($sel_id, $s->id, false) ?>><?= esc_html($s->title) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <!-- Service config -->
  <form method="post" action="<?= admin_url('admin-post.php') ?>" style="margin:1rem 0">
    <input type="hidden" name="action" value="dora_save_service_config">
    <input type="hidden" name="service_id" value="<?= $sel_id ?>">
    <?php wp_nonce_field('dora_save_service_config');
    $slot_mode = $config->slot_mode ?? 'recurring'; ?>
    Max. személyek: <input type="number" name="max_persons" value="<?= absint($config->max_persons ?? 99) ?>" style="width:60px">
    &nbsp;&nbsp;
    Találkozási pont: <input type="text" name="meeting_point" value="<?= esc_attr($config->meeting_point ?? '') ?>" style="width:300px">
    &nbsp;&nbsp;
    <button class="button">Mentés</button>
  </form>

  <!-- Tier table -->
  <table class="wp-list-table widefat fixed striped">
    <thead><tr><th>Min. fő</th><th>Max. fő</th><th>Ár/fő</th><th>Pénznem</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tiers as $t): ?>
      <tr>
        <td><?= absint($t->min_persons) ?></td>
        <td><?= absint($t->max_persons) ?></td>
        <td><?= esc_html($t->price_per_person) ?></td>
        <td><?= esc_html($t->currency) ?></td>
        <td>
          <form method="post" action="<?= admin_url('admin-post.php') ?>">
            <input type="hidden" name="action" value="dora_delete_tier">
            <input type="hidden" name="tier_id" value="<?= absint($t->id) ?>">
            <?php wp_nonce_field('dora_delete_tier'); ?>
            <button class="button-link-delete">Törlés</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Add tier -->
  <h3>Sáv hozzáadása</h3>
  <form method="post" action="<?= admin_url('admin-post.php') ?>">
    <input type="hidden" name="action" value="dora_save_tier">
    <input type="hidden" name="service_id" value="<?= $sel_id ?>">
    <?php wp_nonce_field('dora_save_tier'); ?>
    Min. fő: <input type="number" name="min_persons" min="1" max="99" required style="width:60px">
    Max. fő: <input type="number" name="max_persons" min="1" max="99" required style="width:60px">
    Ár/fő: <input type="text" name="price_per_person" required style="width:80px">
    Pénznem: <select name="currency">
      <option value="EUR">EUR</option><option value="HUF">HUF</option><option value="USD">USD</option>
    </select>
    <button class="button button-primary">Hozzáadás</button>
  </form>
</div>
