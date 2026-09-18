/* Versand der Formulare an formular.php.
   skript.js prueft die Pflichtfelder und setzt bei Erfolg die Klasse "gesendet" auf das
   Formular. Erst dann schicken wir die Daten ab. Formulare mit data-weiter werden nach
   dem Versand auf die angegebene Seite geleitet (Danke-Seite), sonst erscheint der
   Dank im Formular. In der Vorschau (kein PHP) gibt es einen Hinweis statt Versand. */
(function () {
  Array.prototype.forEach.call(document.querySelectorAll('form[action$="formular.php"]'), function (f) {
    var danke = f.querySelector('.danke'),
        knopf = f.querySelector('button[type=submit]'),
        knopfText = knopf ? knopf.textContent : '',
        fehler = f.querySelector('.fehler--versand'),
        weiter = f.getAttribute('data-weiter');
    if (!fehler) {
      fehler = document.createElement('p');
      fehler.className = 'fehler fehler--versand';
      fehler.hidden = true;
      fehler.setAttribute('role', 'alert');
      (danke || f).parentNode.insertBefore(fehler, danke || null);
    }
    if (danke) danke.hidden = true;

    var beobachter = new MutationObserver(function () {
      if (!f.classList.contains('gesendet') || f.dataset.laeuft) return;
      // "gesendet" blendet per CSS alles bis auf den Dank aus. Erst nach dem echten Versand.
      f.classList.remove('gesendet'); f.classList.add('sendet');
      f.dataset.laeuft = '1';
      if (danke) danke.hidden = true;
      fehler.hidden = true;
      if (knopf) { knopf.disabled = true; knopf.textContent = 'Wird gesendet …'; }
      var fd = new FormData(f);
      fetch(f.getAttribute('action'), { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error(String(r.status))); })
        .then(function (d) {
          if (!d || !d.ok) throw new Error(d && d.fehler || 'unbekannt');
          if (weiter) { location.href = weiter; return; }
          f.classList.remove('sendet'); f.classList.add('gesendet');
          if (danke) {
            danke.hidden = false;
            var h = danke.querySelector('h3');
            if (h) { h.setAttribute('tabindex', '-1'); h.focus(); }
          }
        })
        .catch(function (e) {
          f.classList.remove('sendet'); delete f.dataset.laeuft;
          if (knopf) { knopf.disabled = false; knopf.textContent = knopfText; }
          var vorschau = /vorschau|github\.io|localhost/.test(location.hostname) || e.message === '404' || e.message === '405';
          fehler.textContent = vorschau
            ? 'Das ist die Vorschau. Hier wird nichts verschickt, der Versand funktioniert erst auf der echten Adresse.'
            : (/^[A-Z]/.test(e.message) && e.message.length < 120 && e.message !== 'Failed to fetch'
                ? e.message + ' '
                : 'Die Nachricht konnte gerade nicht übertragen werden. ')
              + 'Sie erreichen uns auch direkt unter service@ao-consult.de.';
          fehler.hidden = false;
          fehler.focus && fehler.setAttribute('tabindex', '-1');
          fehler.focus();
        });
    });
    beobachter.observe(f, { attributes: true, attributeFilter: ['class'] });
  });
})();
