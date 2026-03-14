<?php /** @var array $vars */ ?>
<p>Kedves <?= esc_html( $vars['{name}'] ) ?>!</p>
<p>Emlékeztetjük, hogy holnap foglalása van.</p>
<ul>
  <li><strong>Szolgáltatás:</strong> <?= esc_html( $vars['{service}'] ) ?></li>
  <li><strong>Időpont:</strong> <?= esc_html( $vars['{date}'] ) ?> <?= esc_html( $vars['{time}'] ) ?></li>
  <li><strong>Találkozási pont:</strong> <?= esc_html( $vars['{meeting_point}'] ) ?></li>
  <li><strong>Túravezető:</strong> <?= esc_html( $vars['{guide_name}'] ) ?></li>
</ul>
<p>Foglalás száma: <?= esc_html( $vars['{booking_ref}'] ) ?></p>
