<?php /** @var array $vars */ ?>
<p>Dear <?= esc_html( $vars['{name}'] ) ?>,</p>
<p>This is a reminder about your booking tomorrow.</p>
<ul>
  <li><strong>Service:</strong> <?= esc_html( $vars['{service}'] ) ?></li>
  <li><strong>Date:</strong> <?= esc_html( $vars['{date}'] ) ?> <?= esc_html( $vars['{time}'] ) ?></li>
  <li><strong>Meeting point:</strong> <?= esc_html( $vars['{meeting_point}'] ) ?></li>
  <li><strong>Guide:</strong> <?= esc_html( $vars['{guide_name}'] ) ?></li>
</ul>
<p>Booking reference: <?= esc_html( $vars['{booking_ref}'] ) ?></p>
