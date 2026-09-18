<?php
/*
 * SEPA-Lastschriftmandat von der Seite /willkommen entgegennehmen und an die
 * Buchhaltung mailen. Laeuft nur auf dem echten Server (PHP), nicht in der
 * GitHub-Vorschau. Speichert nichts auf dem Server.
 *
 * Empfaenger und Absender:
 */
$EMPFAENGER = 'buchhaltung@ao-consult.de';
$ABSENDER   = 'service@ao-consult.de';
$ABSENDER_NAME = 'AO Consulting Webseite';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function antwort($ok, $fehler = '') {
    echo json_encode(['ok' => $ok, 'fehler' => $fehler], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); antwort(false, 'Nur POST.'); }

// Honigtopf: Bots fuellen das unsichtbare Feld aus. Still "ok" melden, nichts senden.
if (!empty($_POST['webseite'])) antwort(true);

function feld($n, $max = 200) {
    $v = isset($_POST[$n]) ? trim((string)$_POST[$n]) : '';
    $v = preg_replace('/[\r\n\t]+/', ' ', $v);
    return mb_substr($v, 0, $max);
}
$firma  = feld('firma');  $inhaber = feld('kontoinhaber');
$str    = feld('strasse'); $nr = feld('hausnummer', 20);
$plz    = feld('plz', 10); $ort = feld('ort');
$iban   = strtoupper(preg_replace('/\s+/', '', feld('iban', 40)));
$mandat = !empty($_POST['mandat']);

foreach ([$firma, $inhaber, $str, $nr, $plz, $ort, $iban] as $p) {
    if ($p === '') antwort(false, 'Bitte alle Pflichtfelder ausfuellen.');
}
if (!$mandat) antwort(false, 'Ohne Mandat koennen wir keine Lastschrift einziehen.');

// IBAN pruefen (ISO 7064, mod 97)
function ibanOk($iban) {
    if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) return false;
    $s = substr($iban, 4) . substr($iban, 0, 4);
    $rest = 0;
    for ($i = 0; $i < strlen($s); $i++) {
        $c = ord($s[$i]);
        $z = $c >= 65 ? (string)($c - 55) : $s[$i];
        for ($j = 0; $j < strlen($z); $j++) $rest = ($rest * 10 + (ord($z[$j]) - 48)) % 97;
    }
    return $rest === 1;
}
if (!ibanOk($iban)) antwort(false, 'Die IBAN ist nicht gueltig.');
$ibanLesbar = trim(chunk_split($iban, 4, ' '));

$ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$zeit = date('d.m.Y H:i');
$text = "Neues SEPA-Basislastschriftmandat ueber ao-consult.de/willkommen\n\n"
      . "Unternehmen:     $firma\n"
      . "Kontoinhaber:    $inhaber\n"
      . "Anschrift:       $str $nr, $plz $ort\n"
      . "IBAN:            $ibanLesbar\n"
      . "Mandat erteilt:  ja, am $zeit (Serverzeit)\n"
      . "Mandatstext:     SEPA-Basislastschrift, wiederkehrende Zahlungen, Glaeubiger-ID DE22ZZZ00002499554\n"
      . "IP-Adresse:      $ip\n\n"
      . "Diese Nachricht wurde automatisch von der Webseite erzeugt. Antworten gehen an service@ao-consult.de.\n";

$betreff = '=?UTF-8?B?' . base64_encode("SEPA-Mandat: $firma") . '?=';
$absName = '=?UTF-8?B?' . base64_encode($ABSENDER_NAME) . '?=';
$kopf  = "From: $absName <$ABSENDER>\r\n"
       . "Reply-To: $ABSENDER\r\n"
       . "MIME-Version: 1.0\r\n"
       . "Content-Type: text/plain; charset=UTF-8\r\n"
       . "Content-Transfer-Encoding: 8bit\r\n"
       . "X-Mailer: ao-consult.de/willkommen\r\n";

// -f setzt den technischen Absender (Envelope), wichtig fuer SPF beim Hoster
$ok = @mail($EMPFAENGER, $betreff, $text, $kopf, '-f ' . $ABSENDER);
if (!$ok) { http_response_code(500); antwort(false, 'Versand fehlgeschlagen.'); }
antwort(true);
