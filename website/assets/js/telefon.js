/* ============================================================================
   AO Consulting · Telefon mit eintreffenden Benachrichtigungen
   Eigene Zeichnung, bewusst ohne Herstellermarke und ohne fremdes App-Symbol.
   Läuft nur, solange das Gerät im Bild ist. Bricht ohne das Element sofort ab.
   ============================================================================ */
(function () {
  'use strict';

  var buehne = document.querySelector('[data-telefon]');
  if (!buehne) return;
  var stapel = buehne.querySelector('.telefon__stapel');
  var uhrEl = buehne.querySelector('[data-uhr]');
  var datumEl = buehne.querySelector('.telefon__datum');
  if (!stapel) return;

  var reduziert = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var MELDUNGEN = [
    { titel: 'Neue Bewerbung', text: 'ZFA, Vollzeit · Lebenslauf angehängt',
      art: 'bewerbung', betreff: 'Bewerbung als Zahnmedizinische Fachangestellte', stelle: 'als Zahnmedizinische Fachangestellte in Vollzeit' },
    { titel: 'Neue Terminanfrage', text: 'Neupatientin, Wunsch: nächste Woche',
      art: 'termin', betreff: 'Terminanfrage als neue Patientin', stelle: 'als neue Patientin, am liebsten in der kommenden Woche' },
    { titel: 'Neue Bewerbung', text: 'Auszubildende ZFA · Start im September',
      art: 'bewerbung', betreff: 'Bewerbung um einen Ausbildungsplatz', stelle: 'um den Ausbildungsplatz zur Zahnmedizinischen Fachangestellten ab September' },
    { titel: 'Neue Terminanfrage', text: 'Beratung Implantat, Rückruf erbeten',
      art: 'termin', betreff: 'Terminanfrage zur Beratung', stelle: 'für eine Beratung zum Thema Implantat' },
    { titel: 'Neue Bewerbung', text: 'MFA, 30 Stunden · aus der Nachbarstadt',
      art: 'bewerbung', betreff: 'Bewerbung als Medizinische Fachangestellte', stelle: 'als Medizinische Fachangestellte mit 30 Stunden pro Woche' },
    { titel: 'Neue Terminanfrage', text: 'Kontrolltermin, gesetzlich versichert',
      art: 'termin', betreff: 'Terminanfrage zur Kontrolle', stelle: 'zur Kontrolle' },
    { titel: 'Neue Bewerbung', text: 'Zahntechnikerin, Vollzeit · ab sofort',
      art: 'bewerbung', betreff: 'Bewerbung als Zahntechnikerin', stelle: 'als Zahntechnikerin in Vollzeit, ab sofort' },
    { titel: 'Neue Bewerbung', text: 'ZMP Prophylaxe, Teilzeit · 3 Jahre Erfahrung',
      art: 'bewerbung', betreff: 'Bewerbung im Bereich Prophylaxe', stelle: 'als Prophylaxefachkraft in Teilzeit' }
  ];



  var MAX = 3;
  var TAKT = 2600;
  var i = 0;
  var laeuft = null;

  function zweistellig(n) { return n < 10 ? '0' + n : '' + n; }

  function uhrStellen() {
    var d = new Date();
    if (uhrEl) uhrEl.textContent = d.getHours() + ':' + zweistellig(d.getMinutes());
    if (datumEl) datumEl.textContent = d.toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long' });
  }

  function baue(m, nr) {
    var li = document.createElement('li');
    li.className = 'telefon__slot';
    li.setAttribute('data-nr', nr);
    li.innerHTML =
      '<div class="telefon__meldung" role="button">' +
        '<span class="telefon__icon telefon__icon--ao"></span>' +
        '<span class="telefon__inhalt">' +
          '<span class="telefon__zeile"><span class="telefon__titel">' + m.titel + '</span><em>jetzt</em></span>' +
          '<span class="telefon__text">' + m.text + '</span>' +
        '</span>' +
      '</div>';
    return li;
  }

  function neue() {
    var nr = i++ % MELDUNGEN.length;
    var li = baue(MELDUNGEN[nr], nr);
    stapel.insertBefore(li, stapel.firstChild);

    if (reduziert) {
      li.classList.add('da');
    } else {
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(function () { li.classList.add('da'); });
      });
    }

    /* Zeitstempel der älteren Meldungen hochzählen */
    var alle = stapel.querySelectorAll('li:not(.weg)');
    for (var k = 1; k < alle.length; k++) {
      var em = alle[k].querySelector('em');
      if (em) em.textContent = k + ' Min.';
    }

    /* Überzählige nach unten ausblenden und entfernen */
    for (var j = alle.length - 1; j >= MAX; j--) {
      (function (el) {
        el.classList.add('weg');
        window.setTimeout(function () {
          if (el.parentNode) el.parentNode.removeChild(el);
        }, 700);
      })(alle[j]);
    }
  }

  function start() {
    if (laeuft) return;
    if (!stapel.children.length) neue();
    laeuft = window.setInterval(neue, TAKT);
  }

  function stopp() {
    if (!laeuft) return;
    window.clearInterval(laeuft);
    laeuft = null;
  }

  uhrStellen();
  window.setInterval(uhrStellen, 20000);

  if (reduziert) {
    neue(); neue(); neue();
    return;
  }

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (eintraege) {
      eintraege.forEach(function (e) { if (e.isIntersecting) start(); else stopp(); });
    }, { threshold: 0.25 }).observe(buehne);
  } else {
    start();
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stopp();
  });

  /* ---------- Spielerei: Meldung antippen öffnet die Mail ---------- */
  var schirm = buehne.querySelector('.telefon__schirm');
  var mail = null, mailUhr = null;

  function mailText(m) {
    if (m.art === 'termin') {
      return ['Sehr geehrte Damen und Herren,',
              'ich hätte gerne einen Termin bei Ihnen vereinbart, ' + m.stelle + '.',
              'Bitte melden Sie sich unter meiner E-Mail-Adresse oder telefonisch, damit wir einen Termin finden können.',
              'Liebe Grüße'];
    }
    return ['Sehr geehrte Damen und Herren,',
            'hiermit bewerbe ich mich ' + m.stelle + '.',
            'Meinen Lebenslauf finden Sie im Anhang. Über eine Rückmeldung freue ich mich sehr.',
            'Mit freundlichen Grüßen'];
  }

  function mailZu() {
    if (!mail) return;
    mail.classList.remove('offen');
    var weg = mail; mail = null;
    window.clearTimeout(mailUhr);
    window.setTimeout(function () { if (weg.parentNode) weg.parentNode.removeChild(weg); }, 400);
    if (!document.hidden) start();
  }

  function mailAuf(m) {
    if (!schirm) return;
    mailZu();
    stopp();
    var zeilen = mailText(m);
    var el = document.createElement('div');
    el.className = 'telefon__mail';
    el.innerHTML =
      '<div class="telefon__mailkopf"><span class="telefon__zurueck">‹ Posteingang</span></div>' +
      '<div class="telefon__mailrumpf">' +
        '<p class="telefon__betreff">' + m.betreff + '</p>' +
        '<div class="telefon__absender"><span class="telefon__avatar">MM</span>' +
          '<span><strong>Max Mustermann</strong><em>max.mustermann@example.de</em></span></div>' +
        (m.art === 'bewerbung'
          ? '<div class="telefon__anhang"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.5 6.5 8 15a2.5 2.5 0 0 0 3.5 3.5l8-8a4.5 4.5 0 1 0-6.4-6.4l-8 8a6.5 6.5 0 0 0 9.2 9.2l7-7-1.4-1.4-7 7a4.5 4.5 0 0 1-6.4-6.4l8-8a2.5 2.5 0 0 1 3.5 3.5z"/></svg>Lebenslauf.pdf</div>'
          : '') +
        '<p>' + zeilen[0] + '</p><p>' + zeilen[1] + '</p><p>' + zeilen[2] + '</p>' +
        '<p class="telefon__gruss">' + zeilen[3] + '<br>Max Mustermann</p>' +
      '</div>';
    schirm.appendChild(el);
    el.addEventListener('click', mailZu);
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () { el.classList.add('offen'); });
    });
    mail = el;
    mailUhr = window.setTimeout(mailZu, 7000);
  }

  stapel.addEventListener('click', function (e) {
    var slot = e.target.closest ? e.target.closest('.telefon__slot') : null;
    if (!slot) return;
    var nr = parseInt(slot.getAttribute('data-nr'), 10);
    if (!isNaN(nr) && MELDUNGEN[nr]) mailAuf(MELDUNGEN[nr]);
  });

  /* ---------- Spielerei: Taschenlampe und Kamera ---------- */
  var geraet = buehne.querySelector('.telefon') || buehne;
  var lampe = buehne.querySelector('[data-lampe]');
  var kamera = buehne.querySelector('[data-kamera]');
  var blitz = buehne.querySelector('[data-blitz]');
  var hinweis = document.querySelector('[data-hinweis]');
  var hinweisUhr = null;

  function sagen(text) {
    if (!hinweis) return;
    hinweis.textContent = text;
    hinweis.classList.add('da');
    window.clearTimeout(hinweisUhr);
    hinweisUhr = window.setTimeout(function () { hinweis.classList.remove('da'); }, 2800);
  }

  if (kamera) {
    kamera.addEventListener('click', function () {
      sagen('Sie haben keine Berechtigung, die Kamera zu nutzen.');
    });
  }

  var dreht = false;
  if (lampe) {
    lampe.addEventListener('click', function () {
      if (dreht) return;
      dreht = true;
      lampe.setAttribute('aria-label', 'Taschenlampe ausschalten');
      if (blitz) blitz.classList.add('an');
      if (!reduziert) geraet.classList.add('gedreht');
      else sagen('Taschenlampe an.');
      window.setTimeout(function () {
        geraet.classList.remove('gedreht');
        if (blitz) blitz.classList.remove('an');
        lampe.setAttribute('aria-label', 'Taschenlampe einschalten');
        dreht = false;
      }, 3000);
    });
  }
})();
