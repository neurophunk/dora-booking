  <div class="dora-step" id="dora-step-4" style="display:none">
    <h2 class="dora-step-title">Összefoglaló és fizetés</h2>
    <div id="dora-summary" class="dora-summary"></div>
    <?php
    $enabled_methods = get_option('dora_payment_methods', ['onsite','online']);
    $single = count($enabled_methods) === 1;
    ?>
    <div class="dora-payment-options" <?= $single ? 'style="grid-template-columns:1fr;max-width:320px"' : '' ?>>
      <?php if (in_array('onsite', $enabled_methods)): ?>
      <label class="dora-payment-card">
        <input type="radio" name="dora-payment" value="onsite" checked>
        <div class="dora-payment-card-inner">
          <span class="dora-payment-icon">💵</span>
          <strong>Helyszíni fizetés</strong>
          <small>Készpénz vagy kártya a helyszínen</small>
        </div>
      </label>
      <?php endif; ?>
      <?php if (in_array('online', $enabled_methods)): ?>
      <label class="dora-payment-card">
        <input type="radio" name="dora-payment" value="online" <?= ! in_array('onsite', $enabled_methods) ? 'checked' : '' ?>>
        <div class="dora-payment-card-inner">
          <span class="dora-payment-icon">💳</span>
          <strong>Online fizetés</strong>
          <small>Stripe / PayPal — biztonságos</small>
        </div>
      </label>
      <?php endif; ?>
    </div>
    <div class="dora-btn-row">
      <button type="button" id="dora-step4-back" class="dora-btn-back">← Vissza</button>
      <button type="button" id="dora-step4-confirm" class="dora-btn-primary dora-btn-confirm">
        Foglalás megerősítése
      </button>
    </div>
  </div>
