<?php if ( ! defined('ABSPATH') ) exit;
global $wpdb;

$edit_id = absint($_GET['edit'] ?? 0);
$edit    = $edit_id ? $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}dora_services WHERE id=%d", $edit_id
)) : null;
$services  = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dora_services ORDER BY sort_order ASC, name ASC");
$slot_mode = '';
$existing_slots = [];
if ( $edit_id ) {
    $cfg       = $wpdb->get_row($wpdb->prepare("SELECT slot_mode FROM {$wpdb->prefix}dora_service_config WHERE service_id=%d", $edit_id));
    $slot_mode = $cfg->slot_mode ?? 'recurring';
    if ( $slot_mode === 'specific' ) {
        $existing_slots = $wpdb->get_results($wpdb->prepare(
            "SELECT id, slot_date, slot_time FROM {$wpdb->prefix}dora_specific_slots WHERE service_id=%d ORDER BY slot_date ASC, slot_time ASC",
            $edit_id
        ));
    }
}
$day_names = ['V','H','K','Sze','Cs','P','Szo'];
?>
<div class="wrap">
  <h1>DoraBooking — Szolgáltatások</h1>
  <?php if (!empty($_GET['saved'])): ?><div class="notice notice-success"><p>Mentve.</p></div><?php endif; ?>
  <?php if (!empty($_GET['deleted'])): ?><div class="notice notice-success"><p>Törölve.</p></div><?php endif; ?>

  <!-- List -->
  <table class="wp-list-table widefat fixed striped" style="margin-bottom:2rem">
    <thead><tr><th>Név</th><th>Időtartam</th><th>Indulási idők</th><th>Napok</th><th>Sorrend</th><th>Aktív</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($services as $s):
      $times = json_decode($s->available_times, true) ?? [];
      $days  = json_decode($s->available_days,  true) ?? [];
      $day_labels = implode(' ', array_map(fn($d) => $day_names[$d] ?? $d, $days));
    ?>
      <tr>
        <td><strong><?= esc_html($s->name) ?></strong></td>
        <td><?= absint($s->duration_minutes) ?> perc</td>
        <td><?= esc_html(implode(', ', $times)) ?></td>
        <td><?= esc_html($day_labels) ?></td>
        <td><?= absint($s->sort_order) ?></td>
        <td><?= $s->active ? '✓' : '–' ?></td>
        <td>
          <a href="?page=dora-services&edit=<?= absint($s->id) ?>" class="button button-small">Szerkesztés</a>
          <form method="post" action="<?= admin_url('admin-post.php') ?>" style="display:inline"
                onsubmit="return confirm('Biztosan törlöd?')">
            <input type="hidden" name="action" value="dora_delete_service">
            <input type="hidden" name="id" value="<?= absint($s->id) ?>">
            <?php wp_nonce_field('dora_delete_service'); ?>
            <button class="button button-small button-link-delete">Törlés</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Add / Edit form -->
  <h2><?= $edit ? 'Szerkesztés: ' . esc_html($edit->name) : 'Új szolgáltatás' ?></h2>
  <?php
  $edit_times = $edit ? implode(', ', json_decode($edit->available_times, true) ?? []) : '';
  $edit_days  = $edit ? (json_decode($edit->available_days, true) ?? []) : [];
  ?>
  <form method="post" action="<?= admin_url('admin-post.php') ?>">
    <input type="hidden" name="action" value="dora_save_service">
    <input type="hidden" name="id" value="<?= $edit ? absint($edit->id) : 0 ?>">
    <?php wp_nonce_field('dora_save_service'); ?>
    <table class="form-table">
      <tr><th>Név *</th><td><input type="text" name="name" value="<?= esc_attr($edit->name ?? '') ?>" required style="width:400px"></td></tr>
      <tr><th>Leírás</th><td><textarea name="description" rows="3" style="width:400px"><?= esc_textarea($edit->description ?? '') ?></textarea></td></tr>
      <tr><th>Időtartam (perc) *</th><td><input type="number" name="duration_minutes" value="<?= absint($edit->duration_minutes ?? 60) ?>" min="15" max="1440" required style="width:100px"></td></tr>
      <tr><th>Indulási idők *</th><td>
        <input type="text" name="available_times" value="<?= esc_attr($edit_times) ?>" style="width:300px" placeholder="09:00, 14:00">
        <p class="description">Vesszővel elválasztva, pl.: 09:00, 14:00</p>
      </td></tr>
      <tr><th>Elérhető napok *</th><td>
        <?php foreach ([1=>'Hétfő',2=>'Kedd',3=>'Szerda',4=>'Csütörtök',5=>'Péntek',6=>'Szombat',0=>'Vasárnap'] as $d => $label): ?>
          <label style="margin-right:1rem">
            <input type="checkbox" name="day_<?= $d ?>" value="1" <?= in_array($d, $edit_days, true) ? 'checked' : '' ?>>
            <?= $label ?>
          </label>
        <?php endforeach; ?>
      </td></tr>
      <tr><th>Sorrend</th><td><input type="number" name="sort_order" value="<?= absint($edit->sort_order ?? 0) ?>" style="width:80px"></td></tr>
      <tr><th>Aktív</th><td><input type="checkbox" name="active" value="1" <?= ($edit->active ?? 1) ? 'checked' : '' ?>></td></tr>
      <?php if ( $edit_id ): ?>
      <tr><th>Foglalási mód</th><td>
        <label><input type="radio" name="slot_mode" value="recurring" <?= $slot_mode !== 'specific' ? 'checked' : '' ?>> Ismétlődő (heti menetrend)</label>
        &nbsp;&nbsp;
        <label><input type="radio" name="slot_mode" value="specific" <?= $slot_mode === 'specific' ? 'checked' : '' ?>> Egyedi időpontok</label>
      </td></tr>
      <?php endif; ?>
    </table>
    <button class="button button-primary"><?= $edit ? 'Mentés' : 'Létrehozás' ?></button>
    <?php if ($edit): ?>
      <a href="?page=dora-services" class="button">Mégse</a>
    <?php endif; ?>
  </form>

  <?php if ( $edit && $slot_mode === 'specific' ): ?>
  <hr style="margin:2rem 0">
  <h2>Egyedi időpontok — <?= esc_html($edit->name) ?></h2>

  <?php if (!empty($_GET['slots_saved'])): ?>
    <div class="notice notice-success"><p><?= absint($_GET['slots_saved']) ?> időpont hozzáadva.</p></div>
  <?php endif; ?>
  <?php if (!empty($_GET['slot_deleted'])): ?>
    <div class="notice notice-success"><p>Időpont törölve.</p></div>
  <?php endif; ?>

  <!-- Bulk import -->
  <h3>Importálás</h3>
  <form method="post" action="<?= admin_url('admin-post.php') ?>">
    <input type="hidden" name="action" value="dora_save_slots">
    <input type="hidden" name="service_id" value="<?= absint($edit->id) ?>">
    <?php wp_nonce_field('dora_save_slots'); ?>
    <p class="description">Formátum soronként: <code>HH.NN ÓÓ:PP;ÓÓ:PP</code> — pl. <code>03.28 9:00;10:15;11:30</code></p>
    <textarea name="slots_import" rows="8" style="width:500px;font-family:monospace" placeholder="03.28 9:00;10:15;11:30&#10;04.01 9:00;10:15;17:00"></textarea><br>
    <button class="button button-primary" style="margin-top:.5rem">Időpontok hozzáadása</button>
  </form>

  <!-- Existing slots -->
  <?php if ( $existing_slots ): ?>
  <h3 style="margin-top:1.5rem">Meglévő időpontok (<?= count($existing_slots) ?>)</h3>
  <table class="wp-list-table widefat fixed striped" style="max-width:500px">
    <thead><tr><th>Dátum</th><th>Időpont</th><th></th></tr></thead>
    <tbody>
    <?php
    $prev_date = null;
    foreach ( $existing_slots as $slot ):
      $show_date = $slot->slot_date !== $prev_date;
      $prev_date = $slot->slot_date;
    ?>
      <tr>
        <td><?= $show_date ? esc_html($slot->slot_date) : '' ?></td>
        <td><?= esc_html($slot->slot_time) ?></td>
        <td>
          <form method="post" action="<?= admin_url('admin-post.php') ?>" style="display:inline">
            <input type="hidden" name="action" value="dora_delete_slot">
            <input type="hidden" name="slot_id" value="<?= absint($slot->id) ?>">
            <input type="hidden" name="service_id" value="<?= absint($edit->id) ?>">
            <?php wp_nonce_field('dora_delete_slot'); ?>
            <button class="button-link-delete">Törlés</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <p style="color:#666">Még nincsenek egyedi időpontok ehhez a szolgáltatáshoz.</p>
  <?php endif; ?>
  <?php endif; ?>
</div>
