<?php if ( ! defined('ABSPATH') ) exit;
global $wpdb;

// Filter params
$f_date_from = sanitize_text_field($_GET['date_from'] ?? '');
$f_date_to   = sanitize_text_field($_GET['date_to']   ?? '');
$f_service   = absint($_GET['service'] ?? 0);
$f_status    = sanitize_text_field($_GET['status'] ?? '');
$f_payment   = sanitize_text_field($_GET['payment'] ?? '');
$view        = sanitize_text_field($_GET['view'] ?? 'list');

$where  = ['1=1'];
$params = [];
if ($f_date_from) { $where[] = 'b.start_datetime >= %s'; $params[] = $f_date_from . ' 00:00:00'; }
if ($f_date_to)   { $where[] = 'b.start_datetime <= %s'; $params[] = $f_date_to   . ' 23:59:59'; }
if ($f_service)   { $where[] = 'b.service_id = %d';      $params[] = $f_service; }
if ($f_status)    { $where[] = 'b.status = %s';          $params[] = $f_status; }
if ($f_payment)   { $where[] = 'b.payment_type = %s';    $params[] = $f_payment; }

$sql = "SELECT b.*, s.title as service_title FROM {$wpdb->prefix}dora_bookings b
        LEFT JOIN {$wpdb->prefix}bookly_services s ON s.id = b.service_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY b.start_datetime DESC LIMIT 200";
$rows = $params
    ? $wpdb->get_results($wpdb->prepare($sql, ...$params))
    : $wpdb->get_results($sql);
$tz = wp_timezone();
$services = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}bookly_services ORDER BY title");
?>
<div class="wrap">
  <h1>DoraBooking — Foglalások</h1>
  <?php if (!empty($_GET['resent'])): ?><div class="notice notice-success"><p>Email elküldve.</p></div><?php endif; ?>
  <?php if (!empty($_GET['cancelled'])): ?><div class="notice notice-success"><p>Foglalás lemondva.</p></div><?php endif; ?>

  <!-- Filters -->
  <form method="get" style="margin:1rem 0; display:flex; gap:.75rem; flex-wrap:wrap; align-items:center">
    <input type="hidden" name="page" value="dora-booking">
    Dátumtól: <input type="date" name="date_from" value="<?= esc_attr($f_date_from) ?>">
    Dátumig:  <input type="date" name="date_to"   value="<?= esc_attr($f_date_to) ?>">
    Szolgáltatás: <select name="service">
      <option value="">— Összes —</option>
      <?php foreach ($services as $s): ?>
        <option value="<?= absint($s->id) ?>" <?= selected($f_service, $s->id, false) ?>><?= esc_html($s->title) ?></option>
      <?php endforeach; ?>
    </select>
    Státusz: <select name="status">
      <option value="">— Összes —</option>
      <?php foreach (['pending','confirmed','cancelled'] as $st): ?>
        <option value="<?= $st ?>" <?= selected($f_status, $st, false) ?>><?= $st ?></option>
      <?php endforeach; ?>
    </select>
    Fizetés: <select name="payment">
      <option value="">— Összes —</option>
      <?php foreach (['onsite','stripe','paypal'] as $pt): ?>
        <option value="<?= $pt ?>" <?= selected($f_payment, $pt, false) ?>><?= $pt ?></option>
      <?php endforeach; ?>
    </select>
    Nézet: <select name="view">
      <option value="list" <?= selected($view,'list',false) ?>>Lista</option>
      <option value="calendar" <?= selected($view,'calendar',false) ?>>Naptár</option>
    </select>
    <button class="button">Szűrés</button>
    <a href="<?= admin_url('admin.php?page=dora-booking') ?>" class="button">Reset</a>
  </form>

  <form method="post" action="<?= admin_url('admin-post.php') ?>">
    <input type="hidden" name="action" value="dora_export_csv">
    <?php wp_nonce_field('dora_export_csv'); ?>
    <button class="button">CSV export</button>
  </form>

<?php if ($view === 'calendar'):
  // Calendar view — group bookings by date
  $by_date = [];
  foreach ($rows as $r) {
    $dt = new DateTime($r->start_datetime, new DateTimeZone('UTC'));
    $dt->setTimezone($tz);
    $by_date[$dt->format('Y-m-d')][] = $r;
  }
  // Determine month range to display
  $cal_month = sanitize_text_field($_GET['cal_month'] ?? gmdate('Y-m'));
  [$cy, $cm] = explode('-', $cal_month);
  $first_day = new DateTime("$cy-$cm-01", $tz);
  $last_day  = new DateTime("$cy-$cm-" . $first_day->format('t'), $tz);
  $prev = (clone $first_day)->modify('-1 month')->format('Y-m');
  $next = (clone $first_day)->modify('+1 month')->format('Y-m');
?>
  <div style="margin:1rem 0">
    <a href="?page=dora-booking&view=calendar&cal_month=<?= $prev ?>">← Előző</a>
    &nbsp;<strong><?= esc_html($cy . '/' . $cm) ?></strong>&nbsp;
    <a href="?page=dora-booking&view=calendar&cal_month=<?= $next ?>">Következő →</a>
  </div>
  <table class="wp-list-table widefat" style="table-layout:fixed">
    <thead><tr>
      <?php foreach (['H','K','Sze','Cs','P','Szo','V'] as $d): ?><th><?= $d ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php
    $start_dow = (int)$first_day->format('N') - 1; // 0=Mon
    $total_days = (int)$last_day->format('j');
    $cell = 0;
    echo '<tr>';
    for ($i = 0; $i < $start_dow; $i++) { echo '<td></td>'; $cell++; }
    for ($d = 1; $d <= $total_days; $d++) {
        $date = sprintf('%s-%02d', $cal_month, $d);
        $count = count($by_date[$date] ?? []);
        echo '<td style="vertical-align:top; min-height:60px">';
        echo "<strong>$d</strong>";
        if ($count > 0) {
            echo "<br><a href='?page=dora-booking&date_from=$date&date_to=$date'>$count foglalás</a>";
        }
        echo '</td>';
        $cell++;
        if ($cell % 7 === 0 && $d < $total_days) echo '</tr><tr>';
    }
    while ($cell % 7 !== 0) { echo '<td></td>'; $cell++; }
    echo '</tr>';
    ?>
    </tbody>
  </table>
<?php else: ?>
  <table class="wp-list-table widefat fixed striped" style="margin-top:1rem">
    <thead><tr>
      <th>#</th><th>Szolgáltatás</th><th>Időpont</th><th>Fő</th>
      <th>Összeg</th><th>Fizetés</th><th>Státusz</th><th>Vendég</th><th>Műveletek</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $b):
      $dt = new DateTime($b->start_datetime, new DateTimeZone('UTC'));
      $dt->setTimezone($tz);
    ?>
      <tr>
        <td><?= absint($b->id) ?></td>
        <td><?= esc_html($b->service_title) ?></td>
        <td><?= esc_html($dt->format('Y-m-d H:i')) ?></td>
        <td><?= absint($b->persons) ?></td>
        <td><?= esc_html($b->total_price . ' ' . $b->currency) ?></td>
        <td><?= esc_html($b->payment_type) ?></td>
        <td><?= esc_html($b->status) ?></td>
        <td><?= esc_html($b->customer_name) ?> &lt;<?= esc_html($b->customer_email) ?>&gt;</td>
        <td>
          <?php if ($b->status === 'confirmed'): ?>
          <form method="post" action="<?= admin_url('admin-post.php') ?>" style="display:inline">
            <input type="hidden" name="action" value="dora_resend_email">
            <input type="hidden" name="booking_id" value="<?= absint($b->id) ?>">
            <?php wp_nonce_field('dora_resend_email'); ?>
            <button class="button button-small">Email</button>
          </form>
          <form method="post" action="<?= admin_url('admin-post.php') ?>" style="display:inline"
                onsubmit="return confirm('Biztosan lemondod?')">
            <input type="hidden" name="action" value="dora_cancel_booking">
            <input type="hidden" name="booking_id" value="<?= absint($b->id) ?>">
            <?php wp_nonce_field('dora_cancel_booking'); ?>
            <button class="button button-small button-link-delete">Lemondás</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
