/* Stoerer "Das neue Teamprophylaxe-Magazin": faehrt unten rechts herein,
   laesst sich wegklicken. Bringt sein Aussehen selbst mit, damit er spaeter
   mit einer einzigen Skriptzeile auf allen Seiten laufen kann.
   Weggeklickt bleibt er fuer diesen Besuch (Browser-Tab) zu. */
(function () {
  var SCHLUESSEL = 'ao-stoerer-magazin-01';
  var ZIEL = '/teamprophylaxe-magazin/#formular';
  try { if (sessionStorage.getItem(SCHLUESSEL)) return; } catch (e) {}

  var css = ''
    + '.stoerer{position:fixed;right:22px;bottom:92px;z-index:90;width:min(330px,calc(100vw - 2rem));display:grid;grid-template-columns:64px 1fr;gap:1rem;align-items:center;'
    + 'background:#fff;border-radius:var(--radius-k,16px);padding:1rem 2.6rem 1rem 1rem;box-shadow:0 24px 60px -24px rgba(37,34,46,.45),0 0 0 1px rgba(37,34,46,.06);'
    + 'transform:translateX(calc(100% + 40px));opacity:0;visibility:hidden;transition:transform .7s cubic-bezier(.22,.8,.3,1),opacity .5s,visibility 0s .7s}'
    + '.stoerer.da{transform:none;opacity:1;visibility:visible;transition:transform .7s cubic-bezier(.22,.8,.3,1),opacity .5s,visibility 0s}'
    + '.stoerer::before{content:"";position:absolute;left:0;top:14px;bottom:14px;width:3px;border-radius:0 3px 3px 0;background:var(--blau,#2b87da)}'
    + '.stoerer__bild{width:64px;height:auto;border-radius:3px;box-shadow:0 8px 18px -8px rgba(37,34,46,.5);display:block}'
    + '.stoerer__neu{display:block;font-family:var(--schrift-kopf,Poppins,sans-serif);font-size:.7rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--blau,#2b87da);margin:0 0 .25rem}'
    + '.stoerer__titel{display:block;font-family:var(--schrift-kopf,Poppins,sans-serif);font-weight:600;font-size:1rem;line-height:1.3;color:var(--kopf,#25222e);margin:0 0 .6rem}'
    + '.stoerer__knopf{display:inline-block;font-family:var(--schrift-kopf,Poppins,sans-serif);font-weight:600;font-size:.88rem;color:#fff;background:var(--blau,#2b87da);border-radius:var(--radius,6px);padding:.5rem .95rem;text-decoration:none;white-space:nowrap;transition:background .2s}'
    + '.stoerer__knopf:hover,.stoerer__knopf:focus-visible{background:var(--blau-dunkel,#1f6fb8);color:#fff}'
    + '.stoerer__zu{position:absolute;top:.4rem;right:.4rem;width:36px;height:36px;border:0;border-radius:999px;background:none;color:var(--text-2,#6b7482);font-size:1.4rem;line-height:1;cursor:pointer;display:grid;place-items:center}'
    + '.stoerer__zu:hover{background:var(--nebel,#f4f6f9);color:var(--kopf,#25222e)}'
    + '.stoerer__zu:focus-visible,.stoerer__knopf:focus-visible{outline:3px solid var(--blau,#2b87da);outline-offset:2px}'
    + 'html[data-banner-offen] .stoerer,html.lupe-offen .stoerer{display:none}'
    + '@media (max-width:860px){.stoerer{left:.75rem;right:74px;width:auto;bottom:calc(78px + env(safe-area-inset-bottom,0px));grid-template-columns:48px 1fr;gap:.8rem;padding:.8rem 2.4rem .8rem .8rem}.stoerer__bild{width:44px}.stoerer__neu{font-size:.64rem}.stoerer__titel{font-size:.9rem;margin-bottom:.4rem}.stoerer__knopf{font-size:.84rem;padding:.42rem .85rem}}'
    + '@media (prefers-reduced-motion:reduce){.stoerer,.stoerer.da{transition:none;transform:none}}';
  var stil = document.createElement('style');
  stil.textContent = css;
  document.head.appendChild(stil);

  var box = document.createElement('aside');
  box.className = 'stoerer';
  box.setAttribute('aria-label', 'Hinweis auf das Teamprophylaxe-Magazin');
  box.innerHTML = ''
    + '<img class="stoerer__bild" src="/img/teamprophylaxe-magazin/teamprophylaxe-magazin-ausgabe-01-leseprobe-titel.webp" alt="" width="720" height="1019" decoding="async">'
    + '<div><span class="stoerer__neu">Neu · Ausgabe 01</span>'
    + '<span class="stoerer__titel">Das neue Teamprophylaxe-Magazin</span>'
    + '<a class="stoerer__knopf" href="' + ZIEL + '">Jetzt abonnieren</a></div>'
    + '<button type="button" class="stoerer__zu" aria-label="Hinweis schließen">×</button>';
  document.body.appendChild(box);

  function weg() {
    box.classList.remove('da');
    try { sessionStorage.setItem(SCHLUESSEL, '1'); } catch (e) {}
    setTimeout(function () { if (box.parentNode) box.parentNode.removeChild(box); }, 800);
  }
  box.querySelector('.stoerer__zu').addEventListener('click', weg);
  box.querySelector('.stoerer__knopf').addEventListener('click', weg);

  // Erst nach ein paar Sekunden hereinfahren. Solange das Magazin-Formular
  // zu sehen ist, bleibt er draussen, weil er dort nichts Neues sagt.
  var formular = document.getElementById('magazin-formular');
  var formSichtbar = false, bereit = false;
  function pruefen() { box.classList.toggle('da', bereit && !formSichtbar); }
  if (formular && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (e) { formSichtbar = e[0].isIntersecting; pruefen(); }, { threshold: 0.1 }).observe(formular);
  }
  setTimeout(function () { bereit = true; pruefen(); }, 4000);
})();
