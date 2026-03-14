  <div class="dora-step" id="dora-step-4" style="display:none">
    <h2 class="dora-step-title">Összefoglaló és fizetés</h2>
    <div id="dora-summary" class="dora-summary"></div>
    <div class="dora-payment-options">
      <label class="dora-payment-card">
        <input type="radio" name="dora-payment" value="onsite" checked>
        <div class="dora-payment-card-inner">
          <span class="dora-payment-icon">💵</span>
          <strong>Helyszíni fizetés</strong>
          <small>Készpénz vagy kártya a helyszínen</small>
        </div>
      </label>
      <label class="dora-payment-card">
        <input type="radio" name="dora-payment" value="online">
        <div class="dora-payment-card-inner">
          <span class="dora-payment-icon">💳</span>
          <strong>Online fizetés</strong>
          <small>Stripe / PayPal — biztonságos</small>
        </div>
      </label>
    </div>
    <div class="dora-btn-row">
      <button type="button" id="dora-step4-back" class="dora-btn-back">← Vissza</button>
      <button type="button" id="dora-step4-confirm" class="dora-btn-primary dora-btn-confirm">
        Foglalás megerősítése
      </button>
    </div>
  </div>
