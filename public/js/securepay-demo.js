(function(){
  if (!window.SECUREPAY_DEMO) return; // Activación explícita
  document.addEventListener('DOMContentLoaded', function(){
    var form = document.getElementById('checkoutForm');
    if (!form) return;

    var numberEl = document.getElementById('cc-number');
    var expEl = document.getElementById('cc-expiration');
    var cvvEl = document.getElementById('cc-cvv');
    if (!numberEl || !expEl || !cvvEl) return;

    // Crear contenedor para iframe
    var container = document.createElement('div');
    container.id = 'secure-card-container';
    container.className = 'mb-3';
    var label = document.createElement('label');
    label.className = 'form-label';
    label.textContent = 'Tarjeta (Campos Seguros)';
    container.appendChild(label);

    var iframe = document.createElement('iframe');
    iframe.src = '/Ecommerce-Tinkuy/public/secure-fields.html';
    iframe.title = 'Campos de tarjeta seguros';
    iframe.style.width = '100%';
    iframe.style.height = '140px';
    iframe.style.border = '1px solid #ced4da';
    iframe.style.borderRadius = '.25rem';
    iframe.setAttribute('loading', 'lazy');
    container.appendChild(iframe);

    // Insertar antes del campo de número actual
    numberEl.parentNode.parentNode.insertBefore(container, numberEl.parentNode);

    // Ocultar campos sensibles originales (degradan si el iframe falla)
    numberEl.closest('.col-md-6') && (numberEl.closest('.col-md-6').style.display = 'none');
    expEl.closest('.col-md-3') && (expEl.closest('.col-md-3').style.display = 'none');
    cvvEl.closest('.col-md-3') && (cvvEl.closest('.col-md-3').style.display = 'none');

    // Comunicación con el iframe
    var pendingSubmit = false;
    function onMessage(ev){
      try {
        var origin = window.location.origin;
        if (ev.origin !== origin) return; // mismo origen requerido
        var data = ev.data || {};
        if (data.type === 'SECUREPAY_TOKEN') {
          var tokenInput = document.getElementById('secure_token');
          if (tokenInput) tokenInput.value = data.token || '';
          // Borrar campos originales por seguridad
          numberEl.value = '';
          expEl.value = '';
          cvvEl.value = '';
          window.removeEventListener('message', onMessage);
          // Enviar
          pendingSubmit = false;
          form.submit();
        }
        if (data.type === 'SECUREPAY_ERROR') {
          pendingSubmit = false;
          alert(data.message || 'Error validando la tarjeta.');
        }
      } catch(e) { /* noop */ }
    }
    window.addEventListener('message', onMessage);

    form.addEventListener('submit', function(e){
      // interceptar para pedir token al iframe
      e.preventDefault();
      if (pendingSubmit) return;
      pendingSubmit = true;
      try {
        iframe.contentWindow.postMessage({ type: 'SECUREPAY_REQUEST_TOKEN' }, window.location.origin);
      } catch(err){
        pendingSubmit = false;
        alert('No se pudo inicializar el widget de pago.');
      }
    });
  });
})();
