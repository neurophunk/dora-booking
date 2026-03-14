/* global doraBooking, jQuery */
(function ($) {
  'use strict';

  const state = {
    step: 1,
    serviceId: null,
    staffId: null,
    persons: 1,
    maxPersons: 99,
    pricePerPerson: null,
    total: null,
    currency: 'EUR',
    selectedDate: null,
    startDatetime: null,
    endDatetime: null,
    bookingId: null,
  };

  function ajax(action, data) {
    var payload = Object.assign({ action: action, nonce: doraBooking.nonce }, data);
    console.log('[DoraBooking] AJAX →', action, payload);
    return $.post(doraBooking.ajaxUrl, payload).done(function(res) {
      if (!res.success) console.error('[DoraBooking] Error ←', action, res);
    });
  }

  function updateStepIndicator(step) {
    $('.dora-step-dot').each(function() {
      var s = parseInt($(this).data('step'), 10);
      $(this).removeClass('active done');
      if (s === step) $(this).addClass('active');
      if (s < step) $(this).addClass('done');
    });
  }

  function goToStep(n) {
    $('.dora-step').hide();
    $('#dora-step-' + n).show();
    state.step = n;
    updateStepIndicator(n);
    $('html,body').animate({ scrollTop: $('#dora-booking').offset().top - 20 }, 200);
  }

  // ── Step 1: Services ─────────────────────────────────────────
  function loadServices() {
    ajax('dora_get_services').done(function (res) {
      if (!res.success) return;
      var $list = $('#dora-services-list').empty();
      res.data.forEach(function (s) {
        var mins = s.duration_minutes;
        var durText = mins >= 60 ? (Math.floor(mins/60) + ' óra' + (mins%60 ? ' ' + mins%60 + ' perc' : '')) : mins + ' perc';
        var $card = $('<div class="dora-service-card" tabindex="0">')
          .attr('data-id', s.id)
          .attr('data-max', s.max_persons || 99)
          .attr('data-staff', s.staff_id || 1)
          .append($('<strong>').text(s.title))
          .append(s.description ? $('<span class="dora-service-desc">').text(s.description) : '')
          .append($('<span class="dora-service-duration">').text('⏱ ' + durText));
        $list.append($card);
      });
    });
  }

  $(document).on('click', '.dora-service-card', function () {
    $('.dora-service-card').removeClass('selected');
    $(this).addClass('selected');
    state.serviceId = parseInt($(this).data('id'), 10);
    state.maxPersons = parseInt($(this).data('max'), 10) || 99;
    state.staffId = parseInt($(this).data('staff'), 10) || 1;
    state.persons = 1;
    $('#dora-persons').text(1);
    $('#dora-persons-row').show();
    updatePrice();
  });

  $(document).on('click', '.dora-btn-minus', function () {
    if (state.persons > 1) {
      state.persons--;
      $('#dora-persons').text(state.persons);
      updatePrice();
    }
  });

  $(document).on('click', '.dora-btn-plus', function () {
    if (state.persons < state.maxPersons) {
      state.persons++;
      $('#dora-persons').text(state.persons);
      updatePrice();
    }
  });

  function updatePrice() {
    ajax('dora_get_price', { service_id: state.serviceId, persons: state.persons })
      .done(function (res) {
        if (!res.success || !res.data) {
          $('#dora-price-preview').text('Érdeklődjön / Inquire for pricing');
          $('#dora-step1-next').hide();
          state.total = null;
          return;
        }
        state.pricePerPerson = res.data.price_per_person;
        state.total = res.data.total;
        state.currency = res.data.currency;
        $('#dora-price-preview').text(
          state.persons + ' fő × ' + state.pricePerPerson + ' ' + state.currency +
          ' = ' + state.total + ' ' + state.currency
        );
        $('#dora-step1-next').show();
      });
  }

  $(document).on('click', '#dora-step1-next', function () {
    if (!state.serviceId || !state.total) return;
    goToStep(2);
    loadCalendar();
  });

  // ── Step 2: Calendar ─────────────────────────────────────────
  var calYear, calMonth;

  function loadCalendar() {
    var now = new Date();
    calYear = now.getFullYear();
    calMonth = now.getMonth() + 1;
    renderMonth(calYear, calMonth);
  }

  $(document).on('click', '#dora-cal-prev', function() {
    calMonth--;
    if (calMonth < 1) { calMonth = 12; calYear--; }
    renderMonth(calYear, calMonth);
  });

  $(document).on('click', '#dora-cal-next', function() {
    calMonth++;
    if (calMonth > 12) { calMonth = 1; calYear++; }
    renderMonth(calYear, calMonth);
  });

  function renderMonth(year, month) {
    var monthStr = String(month).padStart(2, '0');
    var lastDay = new Date(year, month, 0).getDate();
    var firstDow = new Date(year, month - 1, 1).getDay(); // 0=Sun
    // Convert Sun=0 to Mon=0 system
    var startOffset = (firstDow === 0) ? 6 : firstDow - 1;

    $('#dora-cal-label').text(year + '. ' + ['jan','feb','már','ápr','máj','jún','júl','aug','sze','okt','nov','dec'][month-1]);

    ajax('dora_get_available_days', {
      service_id: state.serviceId,
      year_month: year + '-' + monthStr,
    }).done(function (res) {
      if (!res.success) return;
      var available = res.data;
      var $cal = $('#dora-calendar').empty();

      // Empty cells before first day (Mon-based)
      for (var i = 0; i < startOffset; i++) {
        $cal.append('<span class="dora-cal-day empty"></span>');
      }

      for (var d = 1; d <= lastDay; d++) {
        var date = year + '-' + monthStr + '-' + String(d).padStart(2, '0');
        var $day = $('<span class="dora-cal-day">')
          .text(d)
          .attr('data-date', date);
        if (available.indexOf(date) !== -1) {
          $day.addClass('available');
        } else {
          $day.addClass('disabled');
        }
        $cal.append($day);
      }
    });
  }

  $(document).on('click', '.dora-cal-day.available', function () {
    $('.dora-cal-day').removeClass('active');
    $(this).addClass('active');
    state.selectedDate = $(this).data('date');
    loadSlots(state.selectedDate);
  });

  function loadSlots(date) {
    ajax('dora_get_available_slots', {
      service_id: state.serviceId,
      date: date,
    }).done(function (res) {
      if (!res.success) return;
      var $list = $('#dora-slots-list').empty();
      res.data.forEach(function (time) {
        var $btn = $('<button type="button" class="dora-slot-btn">')
          .text(time)
          .attr('data-start', date + ' ' + time);
        $list.append($btn);
      });
      if (res.data.length) $('#dora-slots').show();
    });
  }

  $(document).on('click', '.dora-slot-btn', function () {
    $('.dora-slot-btn').removeClass('selected');
    $(this).addClass('selected');
    state.startDatetime = $(this).data('start'); // "YYYY-MM-DD HH:MM" local time
    goToStep(3);
  });

  $(document).on('click', '#dora-step2-back', function () { goToStep(1); });

  // ── Step 3: Details ──────────────────────────────────────────
  $(document).on('click', '#dora-step3-next', function () {
    if (!$('#dora-name').val() || !$('#dora-email').val()) {
      alert('Kérjük töltse ki a kötelező mezőket. / Please fill in required fields.');
      return;
    }
    renderSummary();
    goToStep(4);
  });

  $(document).on('click', '#dora-step3-back', function () { goToStep(2); });

  // ── Step 4: Payment ───────────────────────────────────────────
  function renderSummary() {
    var paymentType = $('input[name="dora-payment"]:checked').val();
    var paymentLabel = paymentType === 'onsite' ? 'Helyszíni fizetés' : 'Online fizetés';
    var html = '<ul>' +
      '<li><span>Személyek</span><strong>' + state.persons + ' fő</strong></li>' +
      '<li><span>Összeg</span><strong>' + state.total + ' ' + state.currency + '</strong></li>' +
      '<li><span>Időpont</span><strong>' + (state.startDatetime || '') + '</strong></li>' +
      '<li><span>Fizetési mód</span><strong>' + paymentLabel + '</strong></li>' +
      '</ul>';
    $('#dora-summary').html(html);
  }

  $(document).on('change', 'input[name="dora-payment"]', function () {
    renderSummary();
  });

  $(document).on('click', '#dora-step4-back', function () { goToStep(3); });

  $(document).on('click', '#dora-step4-confirm', function () {
    var $btn = $(this).prop('disabled', true).text('...');
    var paymentType = $('input[name="dora-payment"]:checked').val();
    var isOnline = paymentType === 'online';

    ajax('dora_create_pending', {
      service_id:     state.serviceId,
      staff_id:       state.staffId,
      start_datetime: state.startDatetime,
      end_datetime:   state.endDatetime,
      persons:        state.persons,
      payment_type:   isOnline ? 'stripe' : 'onsite',
      lang:           doraBooking.lang,
      customer_name:  $('#dora-name').val(),
      customer_email: $('#dora-email').val(),
      customer_phone: $('#dora-phone').val(),
      customer_notes: $('#dora-notes').val(),
    }).done(function (res) {
      if (!res.success) {
        alert('Hiba: ' + (res.data || 'slot_taken') + '. Kérjük válasszon másik időpontot.');
        $btn.prop('disabled', false).text('Foglalás megerősítése');
        return;
      }
      state.bookingId = res.data.booking_id;
      if (isOnline) {
        ajax('dora_get_checkout_url', { booking_id: state.bookingId }).done(function (r) {
          if (r.success) {
            window.location.href = r.data.checkout_url;
          } else {
            alert('Checkout hiba. Kérjük próbálja újra.');
            $btn.prop('disabled', false).text('Foglalás megerősítése');
          }
        });
      } else {
        ajax('dora_confirm_onsite', { booking_id: state.bookingId }).done(function (r) {
          if (r.success) {
            $('#dora-booking-ref').text(r.data.booking_ref);
            $('#dora-cancel-link').attr('href', r.data.cancel_url);
            goToStep(5);
          } else {
            alert('Megerősítési hiba. Kérjük próbálja újra.');
            $btn.prop('disabled', false).text('Foglalás megerősítése');
          }
        });
      }
    });
  });

  // ── Init ─────────────────────────────────────────────────────
  $(document).ready(function () {
    if ($('#dora-booking').length) {
      loadServices();
    }
  });

}(jQuery));
