<?php
/*
 * Hierhin leitet Brevo weiter, wenn jemand in der Bestaetigungsmail auf
 * "Anmeldung bestaetigen" klickt (Double-Opt-in). Brevo hat die Person dann
 * schon in die Liste "Teamprophylaxe Magazin" eingetragen. Hier setzen wir
 * in Close "Mazagin" auf Ja und zeigen die Danke-Seite.
 * Die Adresse ist signiert, damit niemand fremde Kontakte umstellen kann.
 */
define('AO_MAGAZIN', true);
require __DIR__ . '/magazin-ausgaben.php';
require __DIR__ . '/magazin-close.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$e = isset($_GET['e']) ? strtolower((string)ao_b64url_zurueck((string)$_GET['e'])) : '';
$s = isset($_GET['s']) ? (string)$_GET['s'] : '';
$schluessel = ao_magazin_schluessel();

if ($e !== '' && $schluessel !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)
    && hash_equals(hash_hmac('sha256', 'newsletter|' . $e, $schluessel), $s)) {
    $bericht = ao_close_newsletter_bestaetigt($e);
    ao_protokoll('newsletter-ok', strpos($bericht, 'ok') === 0, ao_maske($e) . ' Close: ' . $bericht);
} else {
    ao_protokoll('newsletter-ok', false, 'Link ungueltig');
}
header('Location: https://ao-consult.de/teamprophylaxe-magazin/bestaetigt/', true, 302);
