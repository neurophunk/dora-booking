<div id="dora-booking" class="dora-wrap">

  <div class="dora-steps-indicator">
    <div class="dora-step-dot active" data-step="1"><span>1</span><label>Szolgáltatás</label></div>
    <div class="dora-step-dot" data-step="2"><span>2</span><label>Időpont</label></div>
    <div class="dora-step-dot" data-step="3"><span>3</span><label>Adatok</label></div>
    <div class="dora-step-dot" data-step="4"><span>4</span><label>Fizetés</label></div>
  </div>

  <div class="dora-step" id="dora-step-1">
    <h2 class="dora-step-title">Válassz szolgáltatást</h2>
    <div id="dora-services-list" class="dora-services-grid"></div>

    <div id="dora-persons-row" class="dora-persons-row" style="display:none">
      <div class="dora-persons-control">
        <button type="button" class="dora-btn-minus">−</button>
        <div class="dora-persons-display">
          <span id="dora-persons">1</span>
          <small>fő</small>
        </div>
        <button type="button" class="dora-btn-plus">+</button>
      </div>
      <div id="dora-price-preview" class="dora-price-preview"></div>
    </div>

    <button type="button" id="dora-step1-next" class="dora-btn-primary" style="display:none">
      Tovább →
    </button>
  </div>

  <?php include __DIR__ . '/step-2-datetime.php'; ?>
  <?php include __DIR__ . '/step-3-details.php'; ?>
  <?php include __DIR__ . '/step-4-payment.php'; ?>
  <?php include __DIR__ . '/step-5-confirmation.php'; ?>
