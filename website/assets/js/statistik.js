/* ---------------------------------------------------------------------------
   Besucherzaehlung mit Matomo - cookiefrei.

   Warum ohne Einwilligungsfenster: Es wird nichts auf dem Geraet des Besuchers
   gespeichert oder ausgelesen. Keine Cookies, kein Browser-Speicher. Die
   IP-Adresse wird gekuerzt, bevor sie gespeichert wird, und "Do Not Track" wird
   respektiert. Ausgewertet wird auf einem eigenen Server in Deutschland
   (All-Inkl), es gehen keine Daten an Google, Meta oder in die USA.

   Wer sich das Ergebnis ansieht: AO Consulting GmbH, statistik.ao-consult.de
   --------------------------------------------------------------------------- */
(function () {
  'use strict';

  var adresse = 'https://statistik.ao-consult.de/';
  var seite   = '5';   // Kennung dieser Webseite in der Auswertung

  var _paq = (window._paq = window._paq || []);

  // Keine Cookies setzen - doppelt abgesichert, der Server erzwingt es ebenfalls.
  _paq.push(['disableCookies']);
  // Besucher, die im Browser "Do Not Track" eingeschaltet haben, werden nicht gezaehlt.
  _paq.push(['setDoNotTrack', true]);

  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);

  _paq.push(['setTrackerUrl', adresse + 'matomo.php']);
  _paq.push(['setSiteId', seite]);

  var d = document,
      neu = d.createElement('script'),
      erstes = d.getElementsByTagName('script')[0];
  neu.async = true;
  neu.src = adresse + 'matomo.js';
  erstes.parentNode.insertBefore(neu, erstes);
})();
