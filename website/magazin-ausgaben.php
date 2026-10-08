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
