<?php if ( ! defined('ABSPATH') ) exit;
global $wpdb;

$edit_id = absint($_GET['edit'] ?? 0);
$edit    = $edit_id ? $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}dora_services WHERE id=%d", $edit_id
)) : null;
$services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dora_services ORDER BY sort_order ASC, name ASC");
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
    </table>
    <button class="button button-primary"><?= $edit ? 'Mentés' : 'Létrehozás' ?></button>
    <?php if ($edit): ?>
      <a href="?page=dora-services" class="button">Mégse</a>
    <?php endif; ?>
  </form>
</div>
