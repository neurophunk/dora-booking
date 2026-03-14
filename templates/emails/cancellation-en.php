<?php /** @var array $vars */ ?>
<p>Dear <?= esc_html( $vars['{name}'] ) ?>,</p>
<p>Your booking has been cancelled.</p>
<ul>
  <li><strong>Service:</strong> <?= esc_html( $vars['{service}'] ) ?></li>
  <li><strong>Date:</strong> <?= esc_html( $vars['{date}'] ) ?> <?= esc_html( $vars['{time}'] ) ?></li>
  <li><strong>Reference:</strong> <?= esc_html( $vars['{booking_ref}'] ) ?></li>
</ul>
<p>If you paid online, your refund will be processed within 3-5 business days.</p>
