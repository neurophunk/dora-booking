<?php /** @var array $vars */ ?>
<p>Kedves <?= esc_html( $vars['{name}'] ) ?>!</p>
<p>Foglalásod megerősítve.</p>
<ul>
  <li><strong>Szolgáltatás:</strong> <?= esc_html( $vars['{service}'] ) ?></li>
  <li><strong>Időpont:</strong> <?= esc_html( $vars['{date}'] ) ?> <?= esc_html( $vars['{time}'] ) ?></li>
  <li><strong>Utasok:</strong> <?= esc_html( $vars['{persons}'] ) ?> fő</li>
  <li><strong>Összeg:</strong> <?= esc_html( $vars['{total}'] ) ?> <?= esc_html( $vars['{currency}'] ) ?></li>
  <li><strong>Fizetés:</strong> <?= esc_html( $vars['{payment_type}'] ) ?></li>
  <li><strong>Találkozási pont:</strong> <?= esc_html( $vars['{meeting_point}'] ) ?></li>
  <li><strong>Túravezető:</strong> <?= esc_html( $vars['{guide_name}'] ) ?></li>
</ul>
<p>Foglalás száma: <?= esc_html( $vars['{booking_ref}'] ) ?></p>
<p>Lemondás (24 óráig lehetséges): <a href="<?= esc_url( $vars['{cancel_url}'] ) ?>"><?= esc_url( $vars['{cancel_url}'] ) ?></a></p>
