<?php
/*
 * Nimmt das Formular auf /teamprophylaxe-magazin/ entgegen.
 *  1. prueft die Angaben
 *  2. schickt dem Besucher die Download-Mail im AO-Design (mail-magazin.php),
 *     Link signiert und 30 Tage gueltig (siehe download.php)
 *  3. Newsletter angehakt: Anmeldung bei Brevo mit Bestaetigungsmail
 *     (Double-Opt-in). Nach dem Klick darin setzt magazin-bestaetigt.php
 *     in Close "Mazagin" auf Ja.
 *  4. traegt die Person in Close ein (magazin-close.php)
 *  5. schickt dem Team eine Info-Mail an service@ mit allen Angaben
 * Mails gehen ueber Brevo (Zustellung dort nachvollziehbar), ohne Brevo-
 * Schluessel ueber den Webserver. Jeder Schritt landet als Zeile in
 * magazin-dateien/protokoll.log (ausserhalb der Webseite).
 */
$EMPFAENGER    = 'service@ao-consult.de';
$ABSENDER      = 'service@ao-consult.de';
$ABSENDER_NAME = 'AO Consulting Webseite';

define('AO_MAGAZIN', true);
require __DIR__ . '/magazin-ausgaben.php';
require __DIR__ . '/magazin-close.php';
require __DIR__ . '/magazin-brevo.php';
require __DIR__ . '/mail-magazin.php';

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
$mailOk = false; $mailInfo = 'kein Schlüssel für Download-Links (Ordner magazin-dateien fehlt oder ist schreibgeschützt)';
$doi = $newsletter && ao_brevo_aktiv();
if ($schluessel !== '') {
    $mitLink = [];
    foreach ($gewaehlt as $nr => $x) $mitLink[$nr] = $x + ['link' => ao_magazin_link($nr, $email, $schluessel)];
    [$mailOk, $mailInfo] = ao_magazin_mail_senden($email, $ABSENDER, [
        'vorname' => $vorname, 'nachname' => $nachname, 'ausgaben' => $mitLink, 'newsletter' => $newsletter, 'doi' => $doi,
    ]);
}
ao_protokoll('download-mail', $mailOk, ao_maske($email) . ' ' . $mailInfo);

// ---- 2. Newsletter: Bestaetigungsmail ueber Brevo ----------------------------
$nlStatus = $newsletter ? 'ja' : 'nein';
$nlInfo = $newsletter ? 'ohne Brevo, nur in Close vermerkt' : '';
if ($doi && $schluessel !== '') {
    [$nlOk, $nlInfo] = ao_brevo_doi($email, $vorname, $nachname, ao_newsletter_bestaetigungslink($email, $schluessel));
    ao_protokoll('newsletter-doi', $nlOk, ao_maske($email) . ' ' . $nlInfo);
    $nlStatus = $nlOk ? 'ausstehend' : 'ja';
    $nlInfo = $nlOk ? 'Bestätigungsmail verschickt, Mazagin wird nach dem Klick Ja' : 'FEHLER bei Brevo: ' . $nlInfo;
}

// ---- 3. Close ----------------------------------------------------------------
$closeBericht = ao_close_eintragen([
    'vorname' => $vorname, 'nachname' => $nachname, 'praxis' => $praxis, 'webseite' => $webseite,
    'email' => $email, 'telefon' => $telefon, 'newsletter' => $newsletter, 'newsletter_status' => $nlStatus,
    'anruf' => $anruf, 'ausgaben' => $titelListe,
]);
ao_protokoll('close', strpos($closeBericht, 'von Hand') === false, $closeBericht);

// ---- 4. Info-Mail an das Team ----------------------------------------------
$text = "Magazin angefordert über ao-consult.de/teamprophylaxe-magazin/\n\n"
      . str_pad('Vorname:', 16) . $vorname . "\n"
      . str_pad('Nachname:', 16) . $nachname . "\n"
      . str_pad('Praxis:', 16) . $praxis . "\n"
      . str_pad('Webseite:', 16) . $webseite . "\n"
      . str_pad('E-Mail:', 16) . $email . "\n"
      . str_pad('Telefon:', 16) . $telefon . "\n"
      . str_pad('Ausgabe:', 16) . $titelListe . "\n"
      . str_pad('Newsletter:', 16) . ($newsletter ? 'ja, möchte jede neue Ausgabe (' . $nlInfo . ')' : 'nein') . "\n"
      . str_pad('Anrufen:', 16) . ($anruf ? 'ja, darf angerufen werden' : 'nein, nur per E-Mail') . "\n"
      . str_pad('Datenschutz:', 16) . "zugestimmt\n\n"
      . ($mailOk ? "Der Download-Link ist per E-Mail rausgegangen ($mailInfo).\n"
                 : "ACHTUNG: Die Download-Mail konnte NICHT verschickt werden ($mailInfo). Bitte das Magazin von Hand schicken.\n")
      . "\nClose: " . $closeBericht . "\n"
      . (strpos($closeBericht, 'von Hand') !== false
          ? "  Von Hand: Lead suchen (E-Mail, Praxis, Webseite), sonst anlegen mit Quelle \"Teamprophylaxe Magazin\",\n"
            . "  Aktivität \"Magazin\" anlegen (Mazagin: " . ($nlStatus === 'ja' ? 'Ja' : 'Nein') . ") und anpinnen.\n"
          : '')
      . "\n"
      . "Gesendet:  $zeit (Serverzeit)\nIP:        $ip\n\n"
      . "Diese Nachricht wurde automatisch von der Webseite erzeugt. Antworten gehen direkt an die angegebene Adresse.\n";
$teamBetreff = 'Magazin angefordert: ' . $name . ', ' . $praxis;
$teamOk = false;
if (ao_brevo_aktiv()) {
    [$teamOk, $teamInfo] = ao_brevo_mail($EMPFAENGER, 'AO Consulting', $teamBetreff,
        '<pre style="font-family:Menlo,Consolas,monospace;font-size:13px;line-height:1.5;white-space:pre-wrap;">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</pre>',
        $text, 'magazin-team', $email);
    $teamInfo = 'Brevo ' . $teamInfo;
}
if (!$teamOk) {
    $kopf  = "From: =?UTF-8?B?" . base64_encode($ABSENDER_NAME) . "?= <$ABSENDER>\r\n"
           . "Reply-To: $email\r\n"
           . "MIME-Version: 1.0\r\n"
           . "Content-Type: text/plain; charset=UTF-8\r\n"
           . "Content-Transfer-Encoding: 8bit\r\n"
           . "X-Mailer: ao-consult.de/magazin\r\n";
    $teamOk = @mail($EMPFAENGER, '=?UTF-8?B?' . base64_encode($teamBetreff) . '?=', $text, $kopf, '-f ' . $ABSENDER);
    $teamInfo = ($teamInfo ?? '') . ' Webserver ' . ($teamOk ? 'angenommen' : 'abgelehnt');
}
ao_protokoll('team-mail', $teamOk, trim($teamInfo));

if (!$mailOk) {
    if (!$teamOk) { http_response_code(500); antwort(false, 'Versand fehlgeschlagen.'); }
    antwort(false, 'Der Link konnte gerade nicht verschickt werden. Wir schicken Ihnen das Magazin von Hand.');
}
antwort(true);
