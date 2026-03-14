<div id="dora-booking" class="dora-booking" data-step="1">
  <div class="dora-step" id="dora-step-1">
    <h2>Válassz szolgáltatást / Choose a service</h2>
    <div id="dora-services-list" class="dora-services"></div>

    <div id="dora-persons-row" style="display:none">
      <label>Utasok száma / Passengers:
        <button type="button" class="dora-btn-minus" data-target="dora-persons">−</button>
        <span id="dora-persons">1</span>
        <button type="button" class="dora-btn-plus" data-target="dora-persons">+</button>
      </label>
      <div id="dora-price-preview" class="dora-price-preview"></div>
    </div>

    <button type="button" id="dora-step1-next" class="dora-btn-next" style="display:none">
      Tovább / Next →
    </button>
  </div>

  <?php include __DIR__ . '/step-2-datetime.php'; ?>
  <?php include __DIR__ . '/step-3-details.php'; ?>
  <?php include __DIR__ . '/step-4-payment.php'; ?>
  <?php include __DIR__ . '/step-5-confirmation.php'; ?>
</div>
