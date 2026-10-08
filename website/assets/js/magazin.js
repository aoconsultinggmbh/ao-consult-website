/* Teamprophylaxe-Magazin: grosse Ansicht der Leseprobe.
   Nur auf /teamprophylaxe-magazin/. */
(function () {
  var lupe = document.querySelector('.lupe');
  if (!lupe) return;
  var bild = lupe.querySelector('.lupe__bild');
  var seiten = Array.prototype.map.call(document.querySelectorAll('.leseprobe img'), function (img) {
    return { src: img.getAttribute('src'), alt: img.alt };
  });
  var jetzt = 0, vorher = null;

  function zeige(i) {
    jetzt = (i + seiten.length) % seiten.length;
    bild.src = seiten[jetzt].src;
    bild.alt = seiten[jetzt].alt;
  }
  function auf(i, ausloeser) {
    vorher = ausloeser || document.activeElement;
    zeige(i);
    lupe.hidden = false;
    document.documentElement.classList.add('lupe-offen');
    lupe.querySelector('.lupe__zu').focus();
  }
  function zu() {
    lupe.hidden = true;
    document.documentElement.classList.remove('lupe-offen');
    if (vorher && vorher.focus) vorher.focus();
  }

  document.querySelectorAll('[data-leseprobe]').forEach(function (k) {
    k.addEventListener('click', function () { auf(parseInt(k.getAttribute('data-leseprobe'), 10) || 0, k); });
  });
  lupe.querySelector('.lupe__zu').addEventListener('click', zu);
  lupe.querySelector('.lupe__nav--zurueck').addEventListener('click', function () { zeige(jetzt - 1); });
  lupe.querySelector('.lupe__nav--weiter').addEventListener('click', function () { zeige(jetzt + 1); });
  lupe.querySelector('.lupe__hinweis a').addEventListener('click', zu);
  lupe.addEventListener('click', function (e) { if (e.target === lupe) zu(); });
  document.addEventListener('keydown', function (e) {
    if (lupe.hidden) return;
    if (e.key === 'Escape') zu();
    else if (e.key === 'ArrowLeft') zeige(jetzt - 1);
    else if (e.key === 'ArrowRight') zeige(jetzt + 1);
    else if (e.key === 'Tab') {
      var f = lupe.querySelectorAll('button, a'), a = f[0], z = f[f.length - 1];
      if (e.shiftKey && document.activeElement === a) { e.preventDefault(); z.focus(); }
      else if (!e.shiftKey && document.activeElement === z) { e.preventDefault(); a.focus(); }
    }
  });
})();
