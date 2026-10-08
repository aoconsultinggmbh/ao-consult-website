<?php
/*
 * Nimmt das Formular auf /teamprophylaxe-magazin/ entgegen.
 *  1. prueft die Angaben
 *  2. schickt dem Besucher die Download-Mail im AO-Design (mail-magazin.php),
 *     Link signiert und 30 Tage gueltig (siehe download.php)
 *  3. schickt dem Team eine Info-Mail an service@ mit allen Angaben
 * Speichert nichts auf dem Server.
 *
 *  4. traegt die Person in Close ein (magazin-close.php)
 * Brevo (Newsletter mit Bestaetigungsmail) kommt im naechsten Schritt.
 * Klappt Close nicht oder fehlt der Schluessel, steht in der Info-Mail,
 * was das Team von Hand eintragen muss.
 */
$EMPFAENGER    = 'service@ao-consult.de';
$ABSENDER      = 'service@ao-consult.de';
$ABSENDER_NAME = 'AO Consulting Webseite';

define('AO_MAGAZIN', true);
require __DIR__ . '/magazin-ausgaben.php';
require __DIR__ . '/magazin-close.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function antwort($ok, $fehler = '') {
    echo json_encode(['ok' => $ok, 'fehler' => $fehler], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); antwort(false, 'Nur POST.'); }

// Honigtopf: Bots fuellen das unsichtbare Feld aus. Still "ok" melden, nichts senden.
if (!empty($_POST['firmenfax'])) antwort(true);

function wert($n, $max = 300) {
    if (!isset($_POST[$n]) || is_array($_POST[$n])) return '';
    $v = trim((string)$_POST[$n]);
    $v = preg_replace('/[\r\n\t]+/', ' ', $v);
    return mb_substr($v, 0, $max);
}

$vorname     = wert('vorname', 80);
$nachname    = wert('nachname', 80);
$praxis      = wert('praxis', 160);
$webseite    = wert('webseite', 200);
$email       = wert('email', 200);
$telefon     = wert('telefon', 60);
$newsletter  = wert('newsletter') !== '';
$anruf       = wert('anruf') !== '';
$datenschutz = wert('datenschutz') !== '';

foreach ([$vorname, $nachname, $praxis, $webseite, $email, $telefon] as $p) {
    if ($p === '') antwort(false, 'Bitte alle Pflichtfelder ausfüllen.');
}
if (!$datenschutz) antwort(false, 'Bitte stimmen Sie den Datenschutzhinweisen zu.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) antwort(false, 'Die E-Mail-Adresse sieht nicht richtig aus.');
if (strlen(preg_replace('/\D/', '', $telefon)) < 5) antwort(false, 'Die Telefonnummer sieht nicht richtig aus.');

// Webseite einheitlich mit https:// schreiben (fuer Mail und spaeter Close)
if (!preg_match('#^https?://#i', $webseite)) $webseite = 'https://' . $webseite;
$host = parse_url($webseite, PHP_URL_HOST);
if (!$host || strpos($host, '.') === false) antwort(false, 'Die Webseite sieht nicht richtig aus.');

// Gewaehlte Ausgaben (nur bekannte)
$gewaehlt = [];
$roh = isset($_POST['ausgabe']) ? (array)$_POST['ausgabe'] : [];
foreach ($roh as $a) { $a = (string)$a; if (isset($AO_AUSGABEN[$a])) $gewaehlt[$a] = $AO_AUSGABEN[$a]; }
if (!$gewaehlt) antwort(false, 'Bitte wählen Sie mindestens eine Ausgabe.');

$ip    = $_SERVER['REMOTE_ADDR'] ?? '';
$zeit  = date('d.m.Y H:i');
$name  = $vorname . ' ' . $nachname;
$titelListe = implode(', ', array_map(function ($x) { return $x['titel']; }, $gewaehlt));

// ---- 1. Download-Mail an den Besucher -------------------------------------
$schluessel = ao_magazin_schluessel();
$mailOk = false;
if ($schluessel !== '') {
    $mitLink = [];
    foreach ($gewaehlt as $nr => $x) $mitLink[$nr] = $x + ['link' => ao_magazin_link($nr, $email, $schluessel)];
    require_once __DIR__ . '/mail-magazin.php';
    $mailOk = ao_magazin_mail_senden($email, $ABSENDER, [
        'vorname' => $vorname, 'nachname' => $nachname, 'ausgaben' => $mitLink, 'newsletter' => $newsletter,
    ]);
}

// ---- 2. Close ----------------------------------------------------------------
$closeBericht = ao_close_eintragen([
    'vorname' => $vorname, 'nachname' => $nachname, 'praxis' => $praxis, 'webseite' => $webseite,
    'email' => $email, 'telefon' => $telefon, 'newsletter' => $newsletter, 'anruf' => $anruf, 'ausgaben' => $titelListe,
]);

// ---- 3. Info-Mail an das Team ----------------------------------------------
$text = "Magazin angefordert über ao-consult.de/teamprophylaxe-magazin/\n\n"
      . str_pad('Vorname:', 16) . $vorname . "\n"
      . str_pad('Nachname:', 16) . $nachname . "\n"
      . str_pad('Praxis:', 16) . $praxis . "\n"
      . str_pad('Webseite:', 16) . $webseite . "\n"
      . str_pad('E-Mail:', 16) . $email . "\n"
      . str_pad('Telefon:', 16) . $telefon . "\n"
      . str_pad('Ausgabe:', 16) . $titelListe . "\n"
      . str_pad('Newsletter:', 16) . ($newsletter ? 'ja, möchte jede neue Ausgabe' : 'nein') . "\n"
      . str_pad('Anrufen:', 16) . ($anruf ? 'ja, darf angerufen werden' : 'nein, nur per E-Mail') . "\n"
      . str_pad('Datenschutz:', 16) . "zugestimmt\n\n"
      . ($mailOk ? "Der Download-Link ist per E-Mail rausgegangen.\n"
                 : "ACHTUNG: Die Download-Mail konnte NICHT verschickt werden. Bitte das Magazin von Hand schicken.\n")
      . "\nClose: " . $closeBericht . "\n"
      . (strpos($closeBericht, 'von Hand') !== false
          ? "  Von Hand: Lead suchen (E-Mail, Praxis, Webseite), sonst anlegen mit Quelle \"Teamprophylaxe Magazin\",\n"
            . "  Aktivität \"Magazin\" anlegen (Mazagin: " . ($newsletter ? 'Ja' : 'Nein') . ") und anpinnen.\n"
          : '')
      . "\n"
      . "Gesendet:  $zeit (Serverzeit)\nIP:        $ip\n\n"
      . "Diese Nachricht wurde automatisch von der Webseite erzeugt. Antworten gehen direkt an die angegebene Adresse.\n";
$kopf  = "From: =?UTF-8?B?" . base64_encode($ABSENDER_NAME) . "?= <$ABSENDER>\r\n"
       . "Reply-To: $email\r\n"
       . "MIME-Version: 1.0\r\n"
       . "Content-Type: text/plain; charset=UTF-8\r\n"
       . "Content-Transfer-Encoding: 8bit\r\n"
       . "X-Mailer: ao-consult.de/magazin\r\n";
$betreff = '=?UTF-8?B?' . base64_encode('Magazin angefordert: ' . $name . ', ' . $praxis) . '?=';
$teamOk = @mail($EMPFAENGER, $betreff, $text, $kopf, '-f ' . $ABSENDER);

if (!$mailOk) {
    if (!$teamOk) { http_response_code(500); antwort(false, 'Versand fehlgeschlagen.'); }
    antwort(false, 'Der Link konnte gerade nicht verschickt werden. Wir schicken Ihnen das Magazin von Hand.');
}
antwort(true);
