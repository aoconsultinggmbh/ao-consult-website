<?php
/*
 * Liste aller Ausgaben von Teamprophylaxe. Wird von magazin.php und
 * download.php eingebunden, nie direkt aufgerufen.
 *
 * Neue Ausgabe:
 *  1. PDF per KAS-WebFTP in den Ordner /www/htdocs/w0221b78/magazin-dateien/
 *     hochladen (liegt AUSSERHALB der Webseite, ist also nicht oeffentlich).
 *  2. Hier einen Eintrag ergaenzen ("datei" = Dateiname genau wie hochgeladen).
 *  3. Auf der Seite /teamprophylaxe-magazin/ die Ausgabe und das Haekchen im
 *     Formular ergaenzen (value = Schluessel hier, z. B. "02").
 *  4. Titelbild fuer die Mail als JPG (360 px breit) unter img/teamprophylaxe-magazin/
 *     ablegen und bei "mailbild" eintragen, Inhalt (3 Punkte) bei "inhalt".
 */
if (!defined('AO_MAGAZIN')) { http_response_code(404); exit; }

// Ordner mit den PDFs und dem geheimen Schluessel fuer die Download-Links.
// Eine Ebene ueber der Webseite: /www/htdocs/w0221b78/magazin-dateien
if (!defined('AO_MAGAZIN_DATEIEN')) define('AO_MAGAZIN_DATEIEN', dirname(__DIR__) . '/magazin-dateien');

// Wie lange ein Download-Link gilt (Tage)
define('AO_MAGAZIN_LINK_TAGE', 30);

$AO_AUSGABEN = [
    '01' => [
        'titel'    => 'Ausgabe 01 · Herbst 2026',
        'thema'    => 'Die beste Zeit, eine ZFA zu suchen, ist, wenn Sie keine brauchen.',
        'datei'    => 'AO_Teamprophylaxe_Magazin_Ausgabe01_v7.pdf',
        'download' => 'Teamprophylaxe-Magazin-Ausgabe-01-Herbst-2026.pdf',
        // fuer die Download-Mail (mail-magazin.php)
        'mailbild' => 'img/teamprophylaxe-magazin/teamprophylaxe-magazin-ausgabe-01-titel-mail.jpg',
        'inhalt'   => [
            ['Seite 4',  'Befund',   'Vier Wochen Frist, vier Monate Suche'],
            ['Seite 8',  'Anamnese', 'Der Teamstatus zum Ausfüllen'],
            ['Seite 12', 'Recall',   'Ihr Plan für die nächsten 90 Tage'],
        ],
    ],
];

// Geheimer Schluessel fuer die Download-Links. Wird beim ersten Aufruf
// automatisch erzeugt und bleibt im Ordner magazin-dateien liegen.
function ao_magazin_schluessel() {
    $f = AO_MAGAZIN_DATEIEN . '/.schluessel';
    if (is_readable($f)) {
        $s = trim((string)@file_get_contents($f));
        if (strlen($s) >= 32) return $s;
    }
    if (!is_dir(AO_MAGAZIN_DATEIEN) || !is_writable(AO_MAGAZIN_DATEIEN)) return '';
    $s = bin2hex(random_bytes(32));
    if (@file_put_contents($f, $s, LOCK_EX) === false) return '';
    @chmod($f, 0600);
    return $s;
}

function ao_magazin_signatur($ausgabe, $email, $bis, $schluessel) {
    return hash_hmac('sha256', $ausgabe . '|' . strtolower($email) . '|' . $bis, $schluessel);
}

function ao_b64url($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function ao_b64url_zurueck($s) { return base64_decode(strtr($s, '-_', '+/')); }

function ao_magazin_link($ausgabe, $email, $schluessel) {
    $bis = time() + AO_MAGAZIN_LINK_TAGE * 86400;
    $sig = ao_magazin_signatur($ausgabe, $email, $bis, $schluessel);
    return 'https://ao-consult.de/download.php?a=' . rawurlencode($ausgabe)
         . '&e=' . ao_b64url(strtolower($email)) . '&t=' . $bis . '&s=' . $sig;
}

// ---------------------------------------------------------------------------
// Protokoll: eine Zeile pro Schritt in magazin-dateien/protokoll.log
// (ausserhalb der Webseite). E-Mail-Adressen nur gekuerzt, damit man Fehler
// findet, ohne Daten zu sammeln. Datei wird bei 1 MB neu begonnen.
// ---------------------------------------------------------------------------
function ao_maske($email) {
    $t = explode('@', (string)$email, 2);
    return count($t) === 2 ? mb_substr($t[0], 0, 2) . '***@' . $t[1] : '***';
}
function ao_protokoll($schritt, $ok, $info = '') {
    $f = AO_MAGAZIN_DATEIEN . '/protokoll.log';
    if (!is_dir(AO_MAGAZIN_DATEIEN)) return;
    if (is_file($f) && filesize($f) > 1048576) @rename($f, $f . '.alt');
    $zeile = date('Y-m-d H:i:s') . ' | ' . str_pad($schritt, 14) . ' | ' . ($ok ? 'ok    ' : 'FEHLER') . ' | '
           . preg_replace('/[\r\n]+/', ' ', mb_substr((string)$info, 0, 400)) . "\n";
    @file_put_contents($f, $zeile, FILE_APPEND | LOCK_EX);
}

// ---------------------------------------------------------------------------
// HTTP fuer Close und Brevo. Nutzt curl, sonst PHP-Streams.
// Liefert [HTTP-Code, Antworttext, Fehlertext].
// ---------------------------------------------------------------------------
function ao_http($methode, $url, $kopf = [], $daten = null, $timeout = 12) {
    $koerper = $daten === null ? null : json_encode($daten, JSON_UNESCAPED_UNICODE);
    if ($koerper !== null) $kopf[] = 'Content-Type: application/json';
    $kopf[] = 'Accept: application/json';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $methode,
            CURLOPT_HTTPHEADER     => $kopf,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
        if ($koerper !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $koerper);
        $antwort = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $fehler = curl_error($ch);
        curl_close($ch);
        return [$code, $antwort === false ? '' : $antwort, $fehler];
    }
    $ctx = stream_context_create(['http' => [
        'method' => $methode, 'header' => implode("\r\n", $kopf), 'content' => (string)$koerper,
        'timeout' => $timeout, 'ignore_errors' => true,
    ]]);
    $antwort = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach ((array)($http_response_header ?? []) as $z) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $z, $m)) $code = (int)$m[1];
    return [$code, $antwort === false ? '' : $antwort, $antwort === false ? 'keine Verbindung' : ''];
}

// Signierte Bestaetigungsadresse fuer den Newsletter (Double-Opt-in)
function ao_newsletter_bestaetigungslink($email, $schluessel) {
    $e = strtolower($email);
    return 'https://ao-consult.de/magazin-bestaetigt.php?e=' . ao_b64url($e)
         . '&s=' . hash_hmac('sha256', 'newsletter|' . $e, $schluessel);
}
