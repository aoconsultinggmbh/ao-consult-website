/* ============================================================================
   AO Consulting · Startseite · Entwurf v4
   Alle Blöcke prüfen zuerst, ob ihr Element existiert.
   einwilligung.js muss vor dieser Datei eingebunden sein.
   ============================================================================ */
(function () {
  'use strict';

  var reduziert = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Kopf: schrumpfen beim Scrollen ---------- */
  var kopf = document.getElementById('kopf');
  if (kopf) {
    var pruefeKopf = function () { kopf.classList.toggle('geschrumpft', window.scrollY > 24); };
    pruefeKopf();
    window.addEventListener('scroll', pruefeKopf, { passive: true });
  }

  /* ---------- Burger-Menü ---------- */
  var burger = document.querySelector('.burger');
  var nav = document.getElementById('nav');
  if (burger && nav) {
    var setzeMenue = function (offen) {
      nav.classList.toggle('offen', offen);
      burger.setAttribute('aria-expanded', offen ? 'true' : 'false');
      burger.setAttribute('aria-label', offen ? 'Menü schließen' : 'Menü öffnen');
      document.documentElement.style.overflow = offen ? 'hidden' : '';
    };
    burger.addEventListener('click', function () {
      setzeMenue(burger.getAttribute('aria-expanded') !== 'true');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) setzeMenue(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('offen')) { setzeMenue(false); burger.focus(); }
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 1279 && nav.classList.contains('offen')) setzeMenue(false);
    });
  }

  /* ---------- Unterschrift: der Stift fährt jeden Zug wirklich nach ----------
     Der Schriftzug liegt als Strichzeichnung vor, ein Pfad je Schreibzug. Jeder
     Zug wird über stroke-dashoffset von seinem Anfang her gezogen; der Stift
     sitzt über getPointAtLength genau auf der Spitze, die gerade entsteht.
     Zwischen zwei Zügen hebt er kurz ab, zwischen zwei Wörtern etwas länger.  */
  var unterschrift = document.querySelector('[data-unterschrift]');
  if (unterschrift) {
    var uPfade = Array.prototype.slice.call(unterschrift.querySelectorAll('.unterschrift__striche path'));
    var uStift = unterschrift.querySelector('.unterschrift__stift');

    var fertigZeichnen = function () {
      uPfade.forEach(function (pf) { pf.style.strokeDasharray = 'none'; pf.style.strokeDashoffset = '0'; });
      unterschrift.classList.add('fertig');
    };

    if (!uPfade.length || reduziert || !('IntersectionObserver' in window)) {
      unterschrift.classList.add('schreibt');
      fertigZeichnen();
    } else {
      /* Wortgrenzen: nach dem dritten und dem sechsten Zug beginnt ein neues Wort */
      var WORTENDE = { 2: true, 5: true };
      var TEMPO = 0.34;   /* Millisekunden je Längeneinheit des Pfades */
      var HEBEN = 70;     /* Pause zwischen zwei Zügen */
      var WORTPAUSE = 190;

      var zuege = uPfade.map(function (pf, i) {
        var l = pf.getTotalLength();
        pf.style.strokeDasharray = l + ' ' + l;
        pf.style.strokeDashoffset = l;
        return { pf: pf, laenge: l, dauer: Math.max(180, l * TEMPO), pause: WORTENDE[i] ? WORTPAUSE : HEBEN };
      });

      var setzeStift = function (pf, laenge) {
        if (!uStift) return;
        var pt = pf.getPointAtLength(laenge);
        /* Der Stift ist in einem 24er Raster gezeichnet, die Spitze liegt bei
           (3.4, 20.6). Erst dorthin verschieben, dann auf Schriftgröße bringen. */
        uStift.setAttribute('transform',
          'translate(' + pt.x.toFixed(1) + ' ' + pt.y.toFixed(1) + ') scale(1.55) translate(-3.4 -20.6)');
      };

      var schreiben = function () {
        unterschrift.classList.add('schreibt');
        var nr = 0, beginn = null;

        var schritt = function (jetzt) {
          if (beginn === null) beginn = jetzt;
          var z = zuege[nr];
          var p = (jetzt - beginn) / z.dauer;
          if (p > 1) p = 1;
          z.pf.style.strokeDashoffset = (z.laenge * (1 - p)).toFixed(1);
          setzeStift(z.pf, z.laenge * p);

          if (p < 1) { window.requestAnimationFrame(schritt); return; }

          nr++;
          if (nr >= zuege.length) { fertigZeichnen(); return; }
          beginn = null;
          /* Kurz abheben, dann am Anfang des nächsten Zuges wieder aufsetzen */
          window.setTimeout(function () {
            setzeStift(zuege[nr].pf, 0);
            window.requestAnimationFrame(schritt);
          }, z.pause);
        };

        setzeStift(zuege[0].pf, 0);
        window.requestAnimationFrame(schritt);
      };

      var uBeobachter = new IntersectionObserver(function (eintraege) {
        eintraege.forEach(function (e) {
          if (!e.isIntersecting) return;
          uBeobachter.unobserve(e.target);
          window.setTimeout(schreiben, 250);
        });
      }, { threshold: 0.6 });
      uBeobachter.observe(unterschrift);
    }
  }

  /* ---------- Aktiver Menüpunkt ---------- */
  // Sprungziel aus dem Verweis lesen: "#faq" ebenso wie "index.html#faq" (Gesamtvorschau)
  function ankerId(href) { var m = /[#\/]([A-Za-z][\w-]*)$/.exec(href || ''); return m ? m[1] : ''; }
  var navLinks = Array.prototype.slice.call(document.querySelectorAll('.nav__liste a[href^="#"]'));
  var ziele = navLinks.map(function (a) { return document.getElementById(ankerId(a.getAttribute('href'))); }).filter(Boolean);
  if (ziele.length && 'IntersectionObserver' in window) {
    var aktivBeobachter = new IntersectionObserver(function (eintraege) {
      eintraege.forEach(function (e) {
        if (!e.isIntersecting) return;
        navLinks.forEach(function (a) { a.classList.toggle('aktiv', ankerId(a.getAttribute('href')) === e.target.id); });
      });
    }, { rootMargin: '-40% 0px -55% 0px' });
    ziele.forEach(function (z) { aktivBeobachter.observe(z); });
  }

  /* ---------- Scroll-Reveal ---------- */
  var reveals = document.querySelectorAll('.reveal');
  if (reveals.length) {
    if (reduziert || !('IntersectionObserver' in window)) {
      reveals.forEach(function (el) { el.classList.add('sichtbar'); });
    } else {
      var revealBeobachter = new IntersectionObserver(function (eintraege) {
        eintraege.forEach(function (e) {
          if (e.isIntersecting) { e.target.classList.add('sichtbar'); revealBeobachter.unobserve(e.target); }
        });
      }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
      reveals.forEach(function (el) { revealBeobachter.observe(el); });
    }
  }

  /* ---------- Gezeichnete Unterstreichungen (Überschriften ohne .reveal) ---------- */
  var hls = document.querySelectorAll('.hl');
  if (hls.length && 'IntersectionObserver' in window && !reduziert) {
    var hlBeobachter = new IntersectionObserver(function (eintraege) {
      eintraege.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('gezeichnet'); hlBeobachter.unobserve(e.target); }
      });
    }, { threshold: 0.6 });
    hls.forEach(function (el) { hlBeobachter.observe(el); });
  } else {
    hls.forEach(function (el) { el.classList.add('gezeichnet'); });
  }

  /* ---------- Hochzählende Zahlen ---------- */
  var zaehler = document.querySelectorAll('[data-zaehler]');
  if (zaehler.length) {
    var zaehle = function (el) {
      var ziel = parseInt(el.getAttribute('data-zaehler'), 10);
      var prefix = el.getAttribute('data-prefix') || '';
      var suffix = el.getAttribute('data-suffix') || '';
      if (reduziert) { el.textContent = prefix + ziel + suffix; return; }
      var start = null, dauer = 1400;
      var schritt = function (t) {
        if (!start) start = t;
        var p = Math.min((t - start) / dauer, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = prefix + Math.round(ziel * eased) + suffix;
        if (p < 1) window.requestAnimationFrame(schritt);
      };
      window.requestAnimationFrame(schritt);
    };
    if ('IntersectionObserver' in window) {
      var zBeobachter = new IntersectionObserver(function (eintraege) {
        eintraege.forEach(function (e) {
          if (e.isIntersecting) { zaehle(e.target); zBeobachter.unobserve(e.target); }
        });
      }, { threshold: 0.5 });
      zaehler.forEach(function (el) { zBeobachter.observe(el); });
    } else {
      zaehler.forEach(zaehle);
    }
  }

  /* ---------- Tabs (Zielgruppen) ---------- */
  document.querySelectorAll('[data-tabs]').forEach(function (wurzel) {
    var tabs = Array.prototype.slice.call(wurzel.querySelectorAll('[role="tab"]'));
    var panels = tabs.map(function (t) { return document.getElementById(t.getAttribute('aria-controls')); });
    var waehle = function (i, fokus) {
      tabs.forEach(function (t, j) {
        var an = i === j;
        t.setAttribute('aria-selected', an ? 'true' : 'false');
        t.setAttribute('tabindex', an ? '0' : '-1');
        if (panels[j]) panels[j].hidden = !an;
      });
      if (fokus) tabs[i].focus();
    };
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { waehle(i, false); });
      t.addEventListener('keydown', function (e) {
        var n = null;
        if (e.key === 'ArrowRight') n = (i + 1) % tabs.length;
        if (e.key === 'ArrowLeft') n = (i - 1 + tabs.length) % tabs.length;
        if (e.key === 'Home') n = 0;
        if (e.key === 'End') n = tabs.length - 1;
        if (n !== null) { e.preventDefault(); waehle(n, true); }
      });
    });
  });

  /* ---------- FAQ: nur ein Eintrag offen ---------- */
  var faqs = document.querySelectorAll('.faq__eintrag');
  faqs.forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (d.open) faqs.forEach(function (a) { if (a !== d) a.open = false; });
    });
  });

  /* ---------- Video: erst nach Einwilligung ---------- */
  var MEDIEN = 'medien';
  var darfMedien = function () {
    return !!(window.aoEinwilligung && window.aoEinwilligung.erlaubt(MEDIEN));
  };
  var videoBox = document.querySelector('.video__box');
  var ladeVideo = function (abspielen) {
    if (!videoBox || videoBox.dataset.geladen === '1') return;
    var id = videoBox.dataset.video;
    if (!id || id.indexOf('EINTRAGEN') !== -1) {
      var hinweis = videoBox.querySelector('.video__hinweis p');
      if (hinweis) hinweis.textContent = 'Im Entwurf ist noch keine Wistia-Video-ID hinterlegt. Der Ablauf (Einwilligung, dann Laden) funktioniert bereits.';
      return;
    }
    videoBox.dataset.geladen = '1';
    var rahmen = document.createElement('iframe');
    rahmen.src = 'https://fast.wistia.net/embed/iframe/' + encodeURIComponent(id) + '?videoFoam=true' + (abspielen ? '&autoPlay=true' : '');
    rahmen.title = 'Vorstellungsvideo AO Consulting';
    rahmen.allow = 'autoplay; fullscreen';
    rahmen.setAttribute('allowfullscreen', '');
    videoBox.innerHTML = '';
    videoBox.appendChild(rahmen);
  };
  if (videoBox) {
    var knopf = videoBox.querySelector('[data-video-laden]');
    if (knopf) knopf.addEventListener('click', function () {
      if (window.aoEinwilligung) window.aoEinwilligung.setze(MEDIEN, true);
      ladeVideo(true); // erst der Klick startet das Video
    });
    // Liegt die Einwilligung schon vor, bleibt trotzdem das Vorschaubild stehen.
    // Das Video startet nie von selbst, nur der Hinweistext entfaellt.
    var pruefeMedien = function () {
      if (!darfMedien() || videoBox.dataset.geladen === '1') return;
      if (knopf) knopf.textContent = 'Video abspielen';
      var text = videoBox.querySelector('.video__hinweis p');
      if (text) text.hidden = true;
    };
    document.addEventListener('ao:einwilligung', pruefeMedien);
    pruefeMedien();
  }

  /* ---------- Karte: erst nach Einwilligung ----------
     Gleiche Kategorie wie das Video. Vor der Zustimmung geht keine einzige
     Anfrage an Google, im Entwurf genauso wenig wie später live. */
  var karteBox = document.querySelector('.karte__box');
  var ladeKarte = function () {
    if (!karteBox || karteBox.dataset.geladen === '1') return;
    var ort = karteBox.dataset.karte;
    if (!ort) return;
    karteBox.dataset.geladen = '1';
    var rahmen = document.createElement('iframe');
    rahmen.src = 'https://www.google.com/maps?q=' + encodeURIComponent(ort) + '&output=embed';
    rahmen.title = 'Karte mit dem Standort von AO Consulting in Bruchsal';
    rahmen.loading = 'lazy';
    rahmen.referrerPolicy = 'no-referrer-when-downgrade';
    rahmen.setAttribute('allowfullscreen', '');
    karteBox.innerHTML = '';
    karteBox.appendChild(rahmen);
  };
  if (karteBox) {
    var karteKnopf = karteBox.querySelector('[data-karte-laden]');
    if (karteKnopf) karteKnopf.addEventListener('click', function () {
      if (window.aoEinwilligung) window.aoEinwilligung.setze(MEDIEN, true);
      ladeKarte();
    });
    var pruefeKarte = function () { if (darfMedien()) ladeKarte(); };
    document.addEventListener('ao:einwilligung', pruefeKarte);
    pruefeKarte();
  }

  /* ---------- Terminbuchung: erst nach Einwilligung ----------
     Im Entwurf steckt hinter dem Tor eine Musteransicht. Live wird an der
     Stelle das Buchungsfenster als iframe eingesetzt, dieselbe Kategorie und
     dieselbe Schnittstelle wie Karte und Video. */
  var buchBox = document.querySelector('[data-buchung]');
  var zeigeBuchung = function () {
    if (!buchBox || buchBox.dataset.geladen === '1') return;
    buchBox.dataset.geladen = '1';
    var tor = buchBox.querySelector('.buchung__tor');
    var muster = buchBox.querySelector('.buchung__muster');
    if (tor) tor.hidden = true;
    if (muster) {
      muster.hidden = false;
      var h = muster.querySelector('h3');
      if (h) { h.setAttribute('tabindex', '-1'); h.focus(); }
    }
  };
  if (buchBox) {
    var buchKnopf = buchBox.querySelector('[data-buchung-laden]');
    if (buchKnopf) buchKnopf.addEventListener('click', function () {
      if (window.aoEinwilligung) window.aoEinwilligung.setze(MEDIEN, true);
      zeigeBuchung();
    });
    var pruefeBuchung = function () { if (darfMedien()) zeigeBuchung(); };
    document.addEventListener('ao:einwilligung', pruefeBuchung);
    pruefeBuchung();
  }

  /* ---------- Banner offen: mobile Leiste ausblenden ---------- */
  var beobachteBanner = function () {
    var offen = !!document.querySelector('.ein-hinter[data-offen="true"]');
    if (offen) document.documentElement.setAttribute('data-banner-offen', '');
    else document.documentElement.removeAttribute('data-banner-offen');
  };
  if ('MutationObserver' in window) {
    new MutationObserver(beobachteBanner).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-offen', 'class'] });
  }

  /* ---------- Formular: Prüfung, dann Danke-Ansicht ---------- */
  var form = document.getElementById('formular');
  if (form) {
    var zeige = function (id, an) {
      var f = document.getElementById(id);
      if (f) f.hidden = !an;
    };
    var markiere = function (el, ungueltig, fehlerId) {
      if (!el) return;
      if (ungueltig) { el.setAttribute('aria-invalid', 'true'); el.setAttribute('aria-describedby', fehlerId); }
      else { el.removeAttribute('aria-invalid'); el.removeAttribute('aria-describedby'); }
    };
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = form.querySelector('#f-name');
      var einr = form.querySelector('#f-einrichtung');
      var tel = form.querySelector('#f-tel');
      var mail = form.querySelector('#f-mail');
      var ds = form.querySelector('#f-ds');
      var erster = null;

      var nameLeer = !name.value.trim();
      markiere(name, nameLeer, 'f-name-fehler'); zeige('f-name-fehler', nameLeer);
      if (nameLeer && !erster) erster = name;

      var einrLeer = !einr.value.trim();
      markiere(einr, einrLeer, 'f-einrichtung-fehler'); zeige('f-einrichtung-fehler', einrLeer);
      if (einrLeer && !erster) erster = einr;

      var mailOk = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(mail.value.trim());
      var telOk = tel.value.replace(/[^\d]/g, '').length >= 6;
      var kontaktFehlt = !mailOk && !telOk;
      markiere(tel, kontaktFehlt, 'f-kontakt-fehler'); markiere(mail, kontaktFehlt, 'f-kontakt-fehler');
      zeige('f-kontakt-fehler', kontaktFehlt);
      if (kontaktFehlt && !erster) erster = tel;

      var dsFehlt = !ds.checked;
      markiere(ds, dsFehlt, 'f-ds-fehler'); zeige('f-ds-fehler', dsFehlt);
      if (dsFehlt && !erster) erster = ds;

      if (erster) { erster.focus(); return; }

      form.classList.add('gesendet');
      var danke = form.querySelector('.danke');
      if (danke) { danke.hidden = false; danke.querySelector('h3').setAttribute('tabindex', '-1'); danke.querySelector('h3').focus(); }
    });
    form.addEventListener('input', function (e) {
      var el = e.target;
      if (el.getAttribute('aria-invalid') === 'true') {
        el.removeAttribute('aria-invalid');
        var fid = el.getAttribute('aria-describedby');
        if (fid) zeige(fid, false);
        el.removeAttribute('aria-describedby');
      }
    });
  }

  /* ---------- Weitere Formulare: allgemeine Prüfung der Pflichtfelder ----------
     Greift bei jedem Formular mit data-pruefen. Zu jedem Pflichtfeld gehört ein
     Absatz mit der Kennung <feld-id>-fehler, der im Fehlerfall sichtbar wird.  */
  Array.prototype.forEach.call(document.querySelectorAll('form[data-pruefen]'), function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var erster = null;
      Array.prototype.forEach.call(f.querySelectorAll('[required]'), function (el) {
        var leer = el.type === 'checkbox' ? !el.checked : !el.value.trim();
        var fid = el.id + '-fehler';
        var fe = document.getElementById(fid);
        if (fe) fe.hidden = !leer;
        if (leer) {
          el.setAttribute('aria-invalid', 'true');
          if (fe) el.setAttribute('aria-describedby', fid);
          if (!erster) erster = el;
        } else {
          el.removeAttribute('aria-invalid');
          el.removeAttribute('aria-describedby');
        }
      });
      if (erster) { erster.focus(); return; }
      f.classList.add('gesendet');
      var d = f.querySelector('.danke');
      if (d) {
        d.hidden = false;
        var h = d.querySelector('h3');
        if (h) { h.setAttribute('tabindex', '-1'); h.focus(); }
      }
    });
    f.addEventListener('input', function (e) {
      var el = e.target;
      if (el.getAttribute('aria-invalid') === 'true') {
        el.removeAttribute('aria-invalid');
        var fid = el.getAttribute('aria-describedby');
        var fe = fid && document.getElementById(fid);
        if (fe) fe.hidden = true;
        el.removeAttribute('aria-describedby');
      }
    });
  });
})();

/* ---------- Tiefe: Karten neigen sich leicht zur Maus ---------- */
(function () {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
  document.querySelectorAll('[data-tilt]').forEach(function (el) {
    var raf = null;
    el.addEventListener('mousemove', function (e) {
      var r = el.getBoundingClientRect();
      var x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
      if (raf) return;
      raf = window.requestAnimationFrame(function () {
        el.style.transform = 'perspective(900px) rotateX(' + (-y * 4).toFixed(2) + 'deg) rotateY(' + (x * 5).toFixed(2) + 'deg) translateY(-5px)';
        el.style.transition = 'transform .12s ease-out, box-shadow .3s';
        raf = null;
      });
    });
    el.addEventListener('mouseleave', function () {
      el.style.transform = '';
      el.style.transition = 'transform .5s cubic-bezier(.22,.8,.3,1), box-shadow .3s';
    });
  });
})();
