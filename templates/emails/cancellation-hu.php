<?php /** @var array $vars */ ?>
<p>Kedves <?= esc_html( $vars['{name}'] ) ?>!</p>
<p>Foglalásod lemondva.</p>
<ul>
  <li><strong>Szolgáltatás:</strong> <?= esc_html( $vars['{service}'] ) ?></li>
  <li><strong>Időpont:</strong> <?= esc_html( $vars['{date}'] ) ?> <?= esc_html( $vars['{time}'] ) ?></li>
  <li><strong>Foglalás száma:</strong> <?= esc_html( $vars['{booking_ref}'] ) ?></li>
</ul>
<p>Ha online fizettél, a visszatérítés 3-5 munkanapon belül megtörténik.</p>
