<?php
/*
 * Eingangsbestaetigung im AO-Design (HTML-Mail mit Textfassung).
 * Wird nur von formular.php und magazin.php eingebunden, nie direkt aufgerufen.
 *
 * $extra (optional): 'absaetze' ersetzt die Absaetze, 'knoepfe' ist eine Liste
 * aus [Beschriftung, Adresse] und ersetzt den einen Knopf (z. B. Download-Links).
 *
 * Aufbau der Mail: multipart/alternative
 *   1. Textfassung (fuer Programme ohne HTML)
 *   2. multipart/related: HTML + Logo als eingebettetes Bild (cid:logo)
 * Das Logo steckt in der Mail selbst, damit es auch angezeigt wird, wenn ein
 * Mailprogramm Bilder aus dem Internet blockiert.
 * Farben und Schriften wie auf ao-consult.de (stil.css).
 */
if (!defined('AO_FORMULAR') && !defined('AO_MAGAZIN')) { http_response_code(404); exit; }

function ao_bestaetigung_senden($an, $art, $absender, $extra = []) {
    $T = [
        'kontakt' => [
            'betreff' => 'Ihre Anfrage bei AO Consulting ist angekommen',
            'titel'   => 'Ihre Anfrage ist angekommen',
            'absaetze' => [
                'vielen Dank für Ihre Anfrage über unsere Webseite. Sie ist bei uns angekommen.',
                'So geht es weiter: Innerhalb eines Werktags meldet sich jemand aus unserem Team in Bruchsal bei Ihnen und stimmt einen Termin für das kostenfreie Erstgespräch ab. Das Gespräch dauert etwa 15 Minuten und findet telefonisch statt.',
                'Wenn Sie noch etwas ergänzen möchten, antworten Sie einfach auf diese E-Mail.',
            ],
            'knopf'   => ['Was Sie im Erstgespräch erwartet', 'https://ao-consult.de/ihr-erstgespraech/'],
        ],
        'sos' => [
            'betreff' => 'Ihre SOS-Anfrage bei AO Consulting ist angekommen',
            'titel'   => 'Ihre SOS-Anfrage ist angekommen',
            'absaetze' => [
                'vielen Dank für Ihre SOS-Anfrage. Sie ist bei uns angekommen.',
                'So geht es weiter: Wir sehen uns Ihre Angaben an und melden uns innerhalb eines Werktags bei Ihnen.',
                'Wenn Sie noch etwas ergänzen möchten, antworten Sie einfach auf diese E-Mail.',
            ],
            'knopf'   => ['Unsere Fallstudien ansehen', 'https://ao-consult.de/fallstudien/'],
        ],
        'empfehlung' => [
            'betreff' => 'Danke für Ihre Empfehlung an AO Consulting',
            'titel'   => 'Danke für Ihre Empfehlung',
            'absaetze' => [
                'vielen Dank für Ihre Empfehlung. Sie ist bei uns angekommen.',
                'So geht es weiter: Wir nehmen Kontakt mit Ihrer Empfehlung auf und melden uns bei Ihnen, sobald es Neuigkeiten gibt.',
                'Wenn Sie noch etwas ergänzen möchten, antworten Sie einfach auf diese E-Mail.',
            ],
            'knopf'   => ['Zum Empfehlungsprogramm', 'https://ao-consult.de/empfehlungsprogramm/'],
        ],
        'magazin' => [
            'betreff' => 'Ihr Teamprophylaxe-Magazin zum Herunterladen',
            'titel'   => 'Ihr Magazin ist da',
            'absaetze' => [],   // kommen aus magazin.php
            'knopf'   => ['Zum Magazin', 'https://ao-consult.de/teamprophylaxe-magazin/'],
        ],
    ];
    if (!isset($T[$art])) return false;
    $b = $T[$art];
    if (!empty($extra['absaetze'])) $b['absaetze'] = $extra['absaetze'];
    $knoepfe = !empty($extra['knoepfe']) ? $extra['knoepfe'] : [$b['knopf']];
    $h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    // ---- Textfassung ------------------------------------------------------
    $text = "Guten Tag,\n\n" . implode("\n\n", $b['absaetze']) . "\n\n"
          . implode('', array_map(function ($k) { return $k[0] . ":\n" . $k[1] . "\n\n"; }, $knoepfe))
          . "Ist es eilig? Montag bis Freitag erreichen Sie uns unter 0176 85933551.\n\n"
          . "Viele Grüße\nIhr Team von AO Consulting\n\n"
          . "--\nAO Consulting GmbH · Zeiloch 13 · 76646 Bruchsal\nhttps://ao-consult.de\n\n"
          . "Sie erhalten diese E-Mail, weil über ao-consult.de ein Formular mit dieser Adresse abgeschickt wurde. "
          . "Falls das nicht von Ihnen kam, können Sie die E-Mail einfach ignorieren.\n";

    // ---- HTML-Fassung -----------------------------------------------------
    $blau = '#2b87da'; $tinte = '#25222e'; $textf = '#3b4553'; $nebel = '#f4f6f9'; $fuss = '#17151d';
    $schrift = "'Poppins',Arial,Helvetica,sans-serif";
    $schrift2 = "'Roboto',Arial,Helvetica,sans-serif";
    $p = '';
    foreach ($b['absaetze'] as $a) {
        $p .= '<p style="margin:0 0 16px;font-family:' . $schrift2 . ';font-size:16px;line-height:1.6;color:' . $textf . ';">' . $h($a) . '</p>';
    }
    $html = '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<meta name="color-scheme" content="light"><title>' . $h($b['betreff']) . '</title></head>'
      . '<body style="margin:0;padding:0;background:' . $nebel . ';">'
      . '<div style="display:none;max-height:0;overflow:hidden;">' . $h($b['absaetze'][0]) . '</div>'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . $nebel . '" style="background:' . $nebel . ';"><tr><td align="center" style="padding:24px 12px;">'
      . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:3px;overflow:hidden;">'
      // Kopf
      . '<tr><td bgcolor="' . $tinte . '" style="background:' . $tinte . ';padding:28px 40px;">'
      . '<img src="cid:logo" width="73" height="60" alt="AO Consulting" style="display:block;border:0;width:73px;height:60px;">'
      . '</td></tr>'
      . '<tr><td style="height:4px;line-height:4px;font-size:0;background:' . $blau . ';">&nbsp;</td></tr>'
      // Inhalt
      . '<tr><td style="padding:40px 40px 8px;">'
      . '<h1 style="margin:0 0 24px;font-family:' . $schrift . ';font-size:26px;line-height:1.25;font-weight:700;color:' . $tinte . ';">' . $h($b['titel']) . '</h1>'
      . '<p style="margin:0 0 16px;font-family:' . $schrift2 . ';font-size:16px;line-height:1.6;color:' . $textf . ';">Guten Tag,</p>'
      . $p
      . '</td></tr>'
      // Knopf
      . implode('', array_map(function ($k) use ($h, $blau, $schrift) {
            return '<tr><td style="padding:8px 40px 24px;"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
              . '<td bgcolor="' . $blau . '" style="background:' . $blau . ';border-radius:3px;">'
              . '<a href="' . $h($k[1]) . '" style="display:inline-block;padding:14px 26px;font-family:' . $schrift . ';font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">' . $h($k[0]) . ' &rarr;</a>'
              . '</td></tr></table></td></tr>';
        }, $knoepfe))
      . '<tr><td style="height:8px;line-height:8px;font-size:0;">&nbsp;</td></tr>'
      // Eilig
      . '<tr><td style="padding:0 40px 36px;">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="background:' . $nebel . ';border-left:3px solid ' . $blau . ';padding:16px 20px;font-family:' . $schrift2 . ';font-size:15px;line-height:1.5;color:' . $textf . ';">'
      . '<strong style="color:' . $tinte . ';">Ist es eilig?</strong> Montag bis Freitag erreichen Sie uns unter '
      . '<a href="tel:+4917685933551" style="color:' . $blau . ';text-decoration:none;font-weight:600;">0176&nbsp;85933551</a>.'
      . '</td></tr></table>'
      . '<p style="margin:28px 0 0;font-family:' . $schrift2 . ';font-size:16px;line-height:1.6;color:' . $textf . ';">Viele Grüße<br><strong style="color:' . $tinte . ';">Ihr Team von AO Consulting</strong></p>'
      . '</td></tr>'
      // Fuss
      . '<tr><td bgcolor="' . $fuss . '" style="background:' . $fuss . ';padding:24px 40px;font-family:' . $schrift2 . ';font-size:13px;line-height:1.6;color:#a9adb8;">'
      . '<strong style="color:#ffffff;">AO Consulting GmbH</strong><br>Zeiloch 13 · 76646 Bruchsal<br>'
      . '<a href="https://ao-consult.de" style="color:#8ec3f2;text-decoration:none;">ao-consult.de</a>'
      . ' · <a href="https://ao-consult.de/impressum/" style="color:#8ec3f2;text-decoration:none;">Impressum</a>'
      . ' · <a href="https://ao-consult.de/datenschutzerklaerung/" style="color:#8ec3f2;text-decoration:none;">Datenschutz</a>'
      . '</td></tr>'
      . '</table>'
      . '<p style="max-width:600px;margin:16px auto 0;font-family:' . $schrift2 . ';font-size:12px;line-height:1.5;color:#6e7a8c;">'
      . 'Sie erhalten diese E-Mail, weil über ao-consult.de ein Formular mit dieser Adresse abgeschickt wurde. Falls das nicht von Ihnen kam, können Sie die E-Mail einfach ignorieren.</p>'
      . '</td></tr></table></body></html>';

    // ---- MIME zusammenbauen ----------------------------------------------
    $logo = @file_get_contents(__DIR__ . '/img/mail-logo-ao-consulting.png');
    $g1 = 'alt_' . bin2hex(random_bytes(8));
    $g2 = 'rel_' . bin2hex(random_bytes(8));
    $body  = "--$g1\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
           . chunk_split(base64_encode($text)) . "\r\n";
    $body .= "--$g1\r\nContent-Type: multipart/related; boundary=\"$g2\"\r\n\r\n";
    $body .= "--$g2\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
           . chunk_split(base64_encode($html)) . "\r\n";
    if ($logo !== false) {
        $body .= "--$g2\r\nContent-Type: image/png; name=\"ao-consulting.png\"\r\nContent-Transfer-Encoding: base64\r\n"
               . "Content-ID: <logo>\r\nContent-Disposition: inline; filename=\"ao-consulting.png\"\r\n\r\n"
               . chunk_split(base64_encode($logo)) . "\r\n";
    }
    $body .= "--$g2--\r\n--$g1--\r\n";

    $kopf = "From: =?UTF-8?B?" . base64_encode('AO Consulting') . "?= <$absender>\r\n"
          . "Reply-To: $absender\r\n"
          . "MIME-Version: 1.0\r\n"
          . "Content-Type: multipart/alternative; boundary=\"$g1\"\r\n"
          . "Auto-Submitted: auto-replied\r\n"
          . "X-Mailer: ao-consult.de/$art-bestaetigung\r\n";
    return @mail($an, '=?UTF-8?B?' . base64_encode($b['betreff']) . '?=', $body, $kopf, '-f ' . $absender);
}
