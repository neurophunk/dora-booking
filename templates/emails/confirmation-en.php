<?php /** @var array $vars */ ?>
<p>Dear <?= esc_html( $vars['{name}'] ) ?>,</p>
<p>Your booking is confirmed.</p>
<ul>
  <li><strong>Service:</strong> <?= esc_html( $vars['{service}'] ) ?></li>
  <li><strong>Date:</strong> <?= esc_html( $vars['{date}'] ) ?> <?= esc_html( $vars['{time}'] ) ?></li>
  <li><strong>Persons:</strong> <?= esc_html( $vars['{persons}'] ) ?></li>
  <li><strong>Total:</strong> <?= esc_html( $vars['{total}'] ) ?> <?= esc_html( $vars['{currency}'] ) ?></li>
  <li><strong>Payment:</strong> <?= esc_html( $vars['{payment_type}'] ) ?></li>
  <li><strong>Meeting point:</strong> <?= esc_html( $vars['{meeting_point}'] ) ?></li>
  <li><strong>Guide:</strong> <?= esc_html( $vars['{guide_name}'] ) ?></li>
</ul>
<p>Booking reference: <?= esc_html( $vars['{booking_ref}'] ) ?></p>
<p>Cancel (within 24h): <a href="<?= esc_url( $vars['{cancel_url}'] ) ?>"><?= esc_url( $vars['{cancel_url}'] ) ?></a></p>
