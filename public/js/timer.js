(function () {
  var basePath = document.body.dataset.basePath || '';
  var popupVisible = false;

  function createPopup() {
    var overlay = document.createElement('div');
    overlay.id = 'session-timer-popup';
    overlay.style.cssText = [
      'position:fixed', 'inset:0', 'background:rgba(0,0,0,0.5)',
      'display:flex', 'align-items:center', 'justify-content:center',
      'z-index:99999'
    ].join(';');

    var box = document.createElement('div');
    box.style.cssText = [
      'background:#fff', 'padding:32px 40px', 'border-radius:8px',
      'text-align:center', 'max-width:400px', 'box-shadow:0 4px 24px rgba(0,0,0,0.2)'
    ].join(';');

    var msg = document.createElement('p');
    msg.textContent = 'Your session will be ended in 60 seconds. Click to continue.';
    msg.style.cssText = 'margin:0 0 20px;font-size:16px;';

    var btn = document.createElement('button');
    btn.textContent = 'Continue Session';
    btn.style.cssText = [
      'padding:10px 24px', 'font-size:15px', 'cursor:pointer',
      'background:#0066cc', 'color:#fff', 'border:none', 'border-radius:4px'
    ].join(';');

    btn.addEventListener('click', function () {
      fetch(basePath + '/api/timer?action=activity_renew').catch(function () {});
      removePopup();
    });

    box.appendChild(msg);
    box.appendChild(btn);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    popupVisible = true;
  }

  function removePopup() {
    var el = document.getElementById('session-timer-popup');
    if (el) el.parentNode.removeChild(el);
    popupVisible = false;
  }

  function checkTimer() {
    fetch(basePath + '/api/timer')
      .then(function (res) { return res.json(); })
      .then(function (data) {
        
        if (data.session_left < 0) {
          window.location.href = basePath + '/logout?expired=session';
          return;
        }
        if (data.activity_left <= 0) {
          window.location.href = basePath + '/logout?expired=activity';
          return;
        }
        if (data.activity_left < 60) {
          if (!popupVisible) createPopup();
        } else {
          if (popupVisible) removePopup();
        }
      })
      .catch(function () {});
  }

  checkTimer();
  setInterval(checkTimer, 15000);
})();
