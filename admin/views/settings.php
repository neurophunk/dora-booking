<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap">
  <h1>DoraBooking — Beállítások</h1>
  <?php if (!empty($_GET['saved'])): ?><div class="notice notice-success"><p>Mentve.</p></div><?php endif; ?>

  <form method="post" action="<?= admin_url('admin-post.php') ?>">
    <input type="hidden" name="action" value="dora_save_settings">
    <?php wp_nonce_field('dora_save_settings'); ?>
    <table class="form-table">
      <tr><th>Alapértelmezett pénznem</th><td>
        <select name="default_currency">
          <?php foreach (['EUR','HUF','USD'] as $c): ?>
            <option value="<?= $c ?>" <?= selected(get_option('dora_default_currency','EUR'), $c, false) ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </td></tr>
      <tr><th>Max. utasok (globális)</th><td>
        <input type="number" name="max_persons_global" value="<?= absint(get_option('dora_max_persons_global',10)) ?>" min="1" max="99">
      </td></tr>
      <tr><th>Lemondási határidő (óra)</th><td>
        <input type="number" name="cancellation_deadline_hours" value="<?= absint(get_option('dora_cancellation_deadline_hours',24)) ?>" min="0">
        <p class="description">Ennyi órával a túra előtt még lemondható.</p>
      </td></tr>
      <tr><th>Előre foglalható (hónap)</th><td>
        <input type="number" name="advance_booking_months"
               value="<?= absint(get_option('dora_advance_booking_months', 2)) ?>"
               min="1" max="24" style="width:80px">
        <p class="description">Legfeljebb ennyi hónapra előre lehet foglalni a mai naptól.</p>
      </td></tr>
      <tr><th>Elsődleges szín</th><td>
        <input type="color" name="primary_color"
               value="<?= esc_attr(get_option('dora_primary_color', '#1a56db')) ?>">
        <p class="description">A foglalási form gombjainak, kiemelőinek színe.</p>
      </td></tr>
      <tr><th>Fizetési módok</th><td>
        <?php $methods = get_option('dora_payment_methods', ['onsite','online']); ?>
        <label style="display:block;margin-bottom:.4rem">
          <input type="checkbox" name="payment_methods[]" value="onsite" <?= in_array('onsite', $methods) ? 'checked' : '' ?>>
          Helyszíni fizetés
        </label>
        <label style="display:block">
          <input type="checkbox" name="payment_methods[]" value="online" <?= in_array('online', $methods) ? 'checked' : '' ?>>
          Online fizetés (Stripe / PayPal)
        </label>
        <p class="description">Legalább egy legyen bekapcsolva.</p>
      </td></tr>
      <tr><th>Admin értesítési email</th><td>
        <input type="email" name="admin_notification_email"
               value="<?= esc_attr(get_option('dora_admin_notification_email', get_option('admin_email'))) ?>"
               style="width:300px">
      </td></tr>
      <tr><th>WooCommerce</th><td>
        <?php if (class_exists('WooCommerce')): ?>
          <span style="color:green">✓ WooCommerce aktív</span>
        <?php else: ?>
          <span style="color:red">✗ WooCommerce nem aktív!</span>
        <?php endif; ?>
      </td></tr>
      <?php if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON): ?>
      <tr><th>Cron</th><td>
        <div class="notice notice-warning inline"><p>
          A WP-Cron megbízhatóbb ha <code>DISABLE_WP_CRON</code> be van állítva és rendszer cron futtatja a <code>wp-cron.php</code>-t.
        </p></div>
      </td></tr>
      <?php endif; ?>
    </table>
    <button class="button button-primary">Mentés</button>
  </form>
</div>
