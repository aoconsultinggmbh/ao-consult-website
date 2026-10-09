<?php
/*
 * Liefert eine Ausgabe des Teamprophylaxe-Magazins aus, aber nur mit einem
 * gueltigen Link aus der Download-Mail (signiert, 30 Tage gueltig).
 * Die PDFs liegen ausserhalb der Webseite und sind direkt nicht erreichbar.
 */
define('AO_MAGAZIN', true);
require __DIR__ . '/magazin-ausgaben.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');

function ao_seite_fehler($titel, $text) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>' . htmlspecialchars($titel) . ' · AO Consulting</title>'
       . '<style>body{margin:0;background:#f4f6f9;font-family:Roboto,Arial,sans-serif;color:#3b4553;display:grid;place-items:center;min-height:100vh;padding:1.5rem;box-sizing:border-box}'
       . 'main{background:#fff;border-radius:16px;max-width:520px;padding:2.4rem;box-shadow:0 30px 70px -30px rgba(37,34,46,.35)}'
       . 'h1{font-family:Poppins,Arial,sans-serif;color:#25222e;font-size:1.5rem;margin:0 0 1rem}a{color:#2b87da;font-weight:600}</style></head><body><main>'
       . '<h1>' . htmlspecialchars($titel) . '</h1><p>' . $text . '</p>'
       . '<p><a href="https://ao-consult.de/teamprophylaxe-magazin/#formular">Magazin neu anfordern</a></p></main></body></html>';
    exit;
}

$a = isset($_GET['a']) ? (string)$_GET['a'] : '';
$e = isset($_GET['e']) ? strtolower((string)ao_b64url_zurueck((string)$_GET['e'])) : '';
$t = isset($_GET['t']) ? (int)$_GET['t'] : 0;
$s = isset($_GET['s']) ? (string)$_GET['s'] : '';

$schluessel = ao_magazin_schluessel();
if (!isset($AO_AUSGABEN[$a]) || $e === '' || $s === '' || $schluessel === ''
    || !hash_equals(ao_magazin_signatur($a, $e, $t, $schluessel), $s)) {
    ao_seite_fehler('Dieser Link funktioniert nicht', 'Bitte nutzen Sie den Link genau so, wie er in der E-Mail steht. Oder fordern Sie das Magazin einfach neu an.');
}
if ($t < time()) {
    ao_seite_fehler('Dieser Link ist abgelaufen', 'Download-Links gelten ' . AO_MAGAZIN_LINK_TAGE . ' Tage. Fordern Sie das Magazin einfach neu an, der neue Link kommt sofort per E-Mail.');
}

$datei = AO_MAGAZIN_DATEIEN . '/' . basename($AO_AUSGABEN[$a]['datei']);
if (!is_readable($datei)) {
    ao_seite_fehler('Die Ausgabe ist gerade nicht verfügbar', 'Bitte schreiben Sie uns kurz an <a href="mailto:service@ao-consult.de">service@ao-consult.de</a>, dann schicken wir Ihnen das Magazin direkt.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($datei));
header('Content-Disposition: inline; filename="' . $AO_AUSGABEN[$a]['download'] . '"');
readfile($datei);
