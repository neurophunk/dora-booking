<?php if ( ! defined('ABSPATH') ) exit;
global $wpdb;
$types = ['confirmation','reminder','cancellation'];
$langs = ['hu','en'];
$sel_type = sanitize_text_field($_GET['type'] ?? 'confirmation');
$sel_lang = sanitize_text_field($_GET['lang'] ?? 'hu');
if ( ! in_array($sel_type, $types, true) ) $sel_type = 'confirmation';
if ( ! in_array($sel_lang, $langs, true) ) $sel_lang = 'hu';
$tpl = $wpdb->get_row( $wpdb->prepare(
    "SELECT subject, body FROM {$wpdb->prefix}dora_email_templates WHERE type=%s AND lang=%s",
    $sel_type, $sel_lang
) );
?>
<div class="wrap">
  <h1>DoraBooking — Email sablonok</h1>
  <?php if (!empty($_GET['saved'])): ?><div class="notice notice-success"><p>Mentve.</p></div><?php endif; ?>

  <form method="get">
    <input type="hidden" name="page" value="dora-emails">
    Típus: <select name="type" onchange="this.form.submit()">
      <?php foreach ($types as $t): ?>
        <option value="<?= $t ?>" <?= selected($sel_type, $t, false) ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
    Nyelv: <select name="lang" onchange="this.form.submit()">
      <?php foreach ($langs as $l): ?>
        <option value="<?= $l ?>" <?= selected($sel_lang, $l, false) ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <form method="post" action="<?= admin_url('admin-post.php') ?>" style="margin-top:1rem">
    <input type="hidden" name="action" value="dora_save_email_template">
    <input type="hidden" name="type" value="<?= esc_attr($sel_type) ?>">
    <input type="hidden" name="lang" value="<?= esc_attr($sel_lang) ?>">
    <?php wp_nonce_field('dora_save_email_template'); ?>
    <p>Tárgy / Subject:<br>
    <input type="text" name="subject" value="<?= esc_attr($tpl->subject ?? '') ?>" style="width:100%"></p>
    <p>Szöveg / Body:<br>
    <?php wp_editor( $tpl->body ?? '', 'dora_email_body', ['textarea_name' => 'body', 'textarea_rows' => 15] ); ?>
    </p>
    <p>Változók: {name} {service} {date} {time} {persons} {total} {currency} {payment_type} {meeting_point} {guide_name} {cancel_url} {booking_ref}</p>
    <button class="button button-primary">Mentés</button>
  </form>
</div>
