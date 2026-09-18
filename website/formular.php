<?php
/*
 * Nimmt die Formulare der Webseite entgegen (Kontakt, SOS, Empfehlung) und mailt
 * sie an das Team. Laeuft nur auf dem echten Server (PHP), nicht in der
 * GitHub-Vorschau. Speichert nichts auf dem Server.
 *
 * Welches Formular geschickt wurde, steht im versteckten Feld "formular".
 */
$EMPFAENGER    = 'service@ao-consult.de';
$ABSENDER      = 'service@ao-consult.de';
$ABSENDER_NAME = 'AO Consulting Webseite';

$FORMULARE = [
    'kontakt'    => ['betreff' => 'Anfrage Erstgespräch',   'pflicht' => ['name', 'einrichtung', 'datenschutz']],
    'sos'        => ['betreff' => 'SOS-Anfrage',            'pflicht' => ['unternehmen', 'herausforderung', 'anrede', 'name', 'email', 'mobil', 'webseite', 'erreichbar', 'datenschutz']],
    'empfehlung' => ['betreff' => 'Empfehlung',             'pflicht' => ['name', 'email', 'empfohlen', 'firma', 'telefon', 'einwilligung']],
];
$BESCHRIFTUNG = [
    'name' => 'Name', 'einrichtung' => 'Praxis, MVZ oder Labor', 'telefon' => 'Telefon', 'email' => 'E-Mail',
    'anliegen' => 'Worum geht es', 'nachricht' => 'Nachricht', 'datenschutz' => 'Datenschutz akzeptiert',
    'unternehmen' => 'Unternehmen', 'herausforderung' => 'Herausforderung', 'anrede' => 'Anrede', 'titel' => 'Titel',
    'mobil' => 'Mobil', 'webseite' => 'Webseite', 'erreichbar' => 'Erreichbar',
    'empfohlen' => 'Empfohlen wird', 'firma' => 'Praxis oder Firma', 'auszahlung' => 'Prämie als', 'einwilligung' => 'Einwilligung',
];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function antwort($ok, $fehler = '') {
    echo json_encode(['ok' => $ok, 'fehler' => $fehler], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); antwort(false, 'Nur POST.'); }

// Honigtopf: Bots fuellen das unsichtbare Feld aus. Still "ok" melden, nichts senden.
if (!empty($_POST['firmenfax'])) antwort(true);

$art = isset($_POST['formular']) ? $_POST['formular'] : '';
if (!isset($FORMULARE[$art])) antwort(false, 'Unbekanntes Formular.');
$konf = $FORMULARE[$art];

function wert($n, $max = 2000) {
    if (!isset($_POST[$n])) return '';
    $v = $_POST[$n];
    if (is_array($v)) $v = implode(', ', array_map('strval', $v));
    $v = trim((string)$v);
    $v = preg_replace('/[\r\t]+/', ' ', $v);
    return mb_substr($v, 0, $max);
}
foreach ($konf['pflicht'] as $p) {
    if (wert($p) === '') antwort(false, 'Bitte alle Pflichtfelder ausfüllen.');
}
// Kontaktformular: Telefon oder E-Mail reicht
if ($art === 'kontakt' && wert('telefon') === '' && wert('email') === '') antwort(false, 'Bitte Telefon oder E-Mail angeben.');

$email = wert('email', 200);
$emailOk = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
if ($email !== '' && !$emailOk) antwort(false, 'Die E-Mail-Adresse sieht nicht richtig aus.');

$zeilen = [];
foreach ($_POST as $k => $v) {
    if (in_array($k, ['formular', 'firmenfax'], true)) continue;
    $t = wert($k);
    if ($t === '') continue;
    if (in_array($k, ['datenschutz', 'einwilligung'], true)) $t = 'ja';
    $label = isset($BESCHRIFTUNG[$k]) ? $BESCHRIFTUNG[$k] : $k;
    $zeilen[] = str_pad($label . ':', 26) . $t;
}
$ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$zeit = date('d.m.Y H:i');
$seite = isset($_SERVER['HTTP_REFERER']) ? preg_replace('/[^\x20-\x7e]/', '', $_SERVER['HTTP_REFERER']) : '';

$text = $konf['betreff'] . " über ao-consult.de\n\n"
      . implode("\n", $zeilen) . "\n\n"
      . "Gesendet:  $zeit (Serverzeit)\n"
      . ($seite ? "Seite:     $seite\n" : '')
      . "IP:        $ip\n\n"
      . "Diese Nachricht wurde automatisch von der Webseite erzeugt.\n"
      . ($emailOk ? "Antworten gehen direkt an die angegebene Adresse.\n" : '');

$wer = wert('name', 120);
$betreff = '=?UTF-8?B?' . base64_encode($konf['betreff'] . ($wer ? ': ' . $wer : '')) . '?=';
$absName = '=?UTF-8?B?' . base64_encode($ABSENDER_NAME) . '?=';
$kopf  = "From: $absName <$ABSENDER>\r\n"
       . "Reply-To: " . ($emailOk ? $email : $ABSENDER) . "\r\n"
       . "MIME-Version: 1.0\r\n"
       . "Content-Type: text/plain; charset=UTF-8\r\n"
       . "Content-Transfer-Encoding: 8bit\r\n"
       . "X-Mailer: ao-consult.de/$art\r\n";

// -f setzt den technischen Absender (Envelope), wichtig fuer SPF beim Hoster
$ok = @mail($EMPFAENGER, $betreff, $text, $kopf, '-f ' . $ABSENDER);
if (!$ok) { http_response_code(500); antwort(false, 'Versand fehlgeschlagen.'); }
antwort(true);
