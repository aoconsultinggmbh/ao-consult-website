<?php
/*
 * Download-Mail fuer das Teamprophylaxe-Magazin im AO-Design.
 * Wird nur von magazin.php eingebunden.
 *
 * Aufbau: multipart/alternative
 *   1. Textfassung
 *   2. multipart/related: HTML + Logo (cid:logo) + Titelbild (cid:titel)
 * Bilder stecken in der Mail selbst, damit sie auch erscheinen, wenn ein
 * Mailprogramm Bilder aus dem Internet blockiert.
 * Auf dem Handy rutschen Titelbild und Text untereinander.
 */
if (!defined('AO_MAGAZIN')) { http_response_code(404); exit; }

// Baut die Mail. Liefert [Betreff, HTML, Text, Bilder fuer cid:...]
function ao_magazin_mail_bauen($p) {
    // $p: vorname, nachname, ausgaben (Liste aus magazin-ausgaben.php, mit 'link'),
    //     newsletter (bool), doi (bool: Bestaetigungsmail fuer den Newsletter kommt separat)
    $h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $erste = reset($p['ausgaben']);
    $mehrere = count($p['ausgaben']) > 1;
    $anrede = 'Guten Tag ' . trim($p['vorname'] . ' ' . $p['nachname']) . ',';
    $betreff = $mehrere ? 'Ihre Ausgaben von Teamprophylaxe zum Herunterladen'
                        : 'Ihr Teamprophylaxe-Magazin: ' . $erste['titel'];

    $blau = '#2b87da'; $blauHell = '#8ec3f2'; $tinte = '#25222e'; $tinte2 = '#332f3f';
    $text = '#3b4553'; $text2 = '#6b7482'; $nebel = '#f4f6f9'; $fuss = '#17151d';
    $kopfS = "'Poppins',Arial,Helvetica,sans-serif"; $textS = "'Roboto',Arial,Helvetica,sans-serif";

    $knopf = function ($beschriftung, $url, $hell = false) use ($h, $blau, $kopfS) {
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
             . '<td bgcolor="' . $blau . '" style="background:' . $blau . ';border-radius:4px;">'
             . '<a href="' . $h($url) . '" style="display:inline-block;padding:15px 26px;font-family:' . $kopfS
             . ';font-size:15px;font-weight:600;line-height:1.2;color:#ffffff;text-decoration:none;">' . $h($beschriftung) . ' &rarr;</a>'
             . '</td></tr></table>';
    };
    $absatz = function ($s) use ($textS, $text) {
        return '<p style="margin:0 0 16px;font-family:' . $textS . ';font-size:16px;line-height:1.65;color:' . $text . ';">' . $s . '</p>';
    };

    // ---- Inhalt dieser Ausgabe ----------------------------------------------
    $inhalt = '';
    foreach ((array)($erste['inhalt'] ?? []) as $i => $z) {
        $inhalt .= '<tr><td style="padding:14px 0;border-top:1px solid #e8ecf2;" valign="top">'
                 . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr>'
                 . '<td width="78" valign="top" style="font-family:' . $kopfS . ';font-size:13px;font-weight:700;color:' . $blau . ';padding-top:2px;">' . $h($z[0]) . '</td>'
                 . '<td valign="top" style="font-family:' . $textS . ';font-size:15px;line-height:1.45;color:' . $text . ';">'
                 . '<span style="font-family:' . $kopfS . ';font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:' . $text2 . ';">' . $h($z[1]) . '</span><br>'
                 . '<strong style="font-family:' . $kopfS . ';font-weight:600;color:' . $tinte . ';">' . $h($z[2]) . '</strong></td>'
                 . '</tr></table></td></tr>';
    }

    // ---- weitere Ausgaben (wenn mehrere gewaehlt) ---------------------------
    $weitere = '';
    if ($mehrere) {
        foreach (array_slice($p['ausgaben'], 1) as $a) {
            $weitere .= '<tr><td style="padding:0 40px 16px;" class="rand">' . $knopf($a['titel'] . ' herunterladen', $a['link']) . '</td></tr>';
        }
    }

    $newsletterSatz = !$p['newsletter'] ? '' : (!empty($p['doi'])
        ? 'Sie möchten auch die nächsten Ausgaben bekommen. Das freut uns. Dafür kommt gleich eine zweite E-Mail: Bitte bestätigen Sie darin Ihre Anmeldung mit einem Klick.'
        : 'Sie möchten auch die nächsten Ausgaben bekommen. Das freut uns. Sobald eine neue Ausgabe erscheint, schicken wir sie Ihnen per E-Mail.');

    $html = '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<meta name="color-scheme" content="light only"><meta name="supported-color-schemes" content="light"><title>' . $h($betreff) . '</title>'
      . '<style>'
      . '@media only screen and (max-width:620px){'
      . '.rand{padding-left:24px!important;padding-right:24px!important}'
      . '.spalte{display:block!important;width:100%!important;max-width:100%!important}'
      . '.titelbild{padding:0 0 24px!important;text-align:left!important}'
      . '.titelbild img{width:150px!important;height:auto!important}'
      . '.h1{font-size:28px!important}'
      . '}</style></head>'
      . '<body style="margin:0;padding:0;background:' . $nebel . ';">'
      . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">Ihre Ausgabe von Teamprophylaxe, dem Magazin gegen Lücken im Team. Der Link gilt ' . AO_MAGAZIN_LINK_TAGE . ' Tage.</div>'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . $nebel . '" style="background:' . $nebel . ';"><tr><td align="center" style="padding:24px 10px;">'
      . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:6px;overflow:hidden;">'

      // Kopfzeile
      . '<tr><td bgcolor="' . $tinte . '" style="background:' . $tinte . ';padding:26px 40px 0;" class="rand">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
      . '<td valign="middle"><img src="cid:logo" width="61" height="50" alt="AO Consulting" style="display:block;border:0;width:61px;height:50px;"></td>'
      . '<td valign="middle" align="right" style="font-family:' . $kopfS . ';font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:' . $blauHell . ';">Teamprophylaxe</td>'
      . '</tr></table></td></tr>'

      // Buehne: Titelbild + Text
      . '<tr><td bgcolor="' . $tinte . '" style="background:' . $tinte . ';background-image:linear-gradient(160deg,' . $tinte2 . ' 0%,' . $tinte . ' 70%);padding:34px 40px 40px;" class="rand">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
      . '<td width="190" valign="top" class="spalte titelbild" style="padding-right:30px;">'
      . '<img src="cid:titel" width="180" alt="Titelseite ' . $h($erste['titel']) . '" style="display:block;border:0;width:180px;height:auto;border-radius:3px;box-shadow:0 18px 40px rgba(0,0,0,.45);">'
      . '</td>'
      . '<td valign="middle" class="spalte">'
      . '<p style="margin:0 0 12px;font-family:' . $kopfS . ';font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:' . $blauHell . ';">' . ($mehrere ? 'Ihre Ausgaben sind da' : 'Ihr Magazin ist da') . '</p>'
      . '<h1 class="h1" style="margin:0 0 10px;font-family:' . $kopfS . ';font-size:32px;line-height:1.15;font-weight:700;color:#ffffff;">Teamprophylaxe</h1>'
      . '<p style="margin:0 0 6px;font-family:' . $textS . ';font-size:16px;line-height:1.5;color:#d5d9e2;">Das Magazin gegen Lücken im Team</p>'
      . '<p style="margin:0 0 26px;font-family:' . $kopfS . ';font-size:13px;font-weight:600;color:' . $blauHell . ';">' . $h($erste['titel']) . '</p>'
      . $knopf('Magazin herunterladen', $erste['link'])
      . '<p style="margin:12px 0 0;font-family:' . $textS . ';font-size:12px;line-height:1.5;color:#a9adb8;">PDF, der Link gilt ' . AO_MAGAZIN_LINK_TAGE . ' Tage.</p>'
      . '</td></tr></table></td></tr>'
      . '<tr><td style="height:4px;line-height:4px;font-size:0;background:' . $blau . ';">&nbsp;</td></tr>'

      // Brief
      . '<tr><td style="padding:38px 40px 6px;" class="rand">'
      . $absatz($h($anrede))
      . $absatz('vielen Dank für Ihr Interesse an Teamprophylaxe. Bei Ihren Patienten machen Sie es jeden Tag: vorsorgen, bevor es weh tut. Dieses Magazin zeigt, wie das auch beim Personal geht.')
      . ($newsletterSatz ? $absatz($h($newsletterSatz)) : '')
      . '</td></tr>'
      . $weitere

      // Aufmacher-Zitat
      . (!empty($erste['thema'])
          ? '<tr><td style="padding:6px 40px 10px;" class="rand"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td style="border-left:3px solid ' . $blau . ';padding:6px 0 6px 20px;font-family:' . $kopfS . ';font-size:19px;line-height:1.4;font-weight:600;color:' . $tinte . ';">'
            . '&bdquo;' . $h($erste['thema']) . '&ldquo;</td></tr></table></td></tr>'
          : '')

      // In dieser Ausgabe
      . ($inhalt
          ? '<tr><td style="padding:24px 40px 10px;" class="rand">'
            . '<p style="margin:0 0 6px;font-family:' . $kopfS . ';font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:' . $blau . ';">In dieser Ausgabe</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $inhalt . '</table></td></tr>'
          : '')

      // Gruss
      . '<tr><td style="padding:22px 40px 34px;" class="rand">'
      . $absatz('Viel Freude beim Lesen. Fragen oder Anregungen zum Magazin? Antworten Sie einfach auf diese E-Mail.')
      . '<p style="margin:22px 0 0;font-family:' . $textS . ';font-size:16px;line-height:1.5;color:' . $text . ';">Viele Grüße<br>'
      . '<strong style="font-family:' . $kopfS . ';font-weight:600;color:' . $tinte . ';">Admir &amp; Ovidiu</strong><br>'
      . '<span style="font-size:14px;color:' . $text2 . ';">Gründer der AO Consulting GmbH</span></p>'
      . '</td></tr>'

      // Hinweis Erstgespraech
      . '<tr><td style="padding:0 40px 38px;" class="rand"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
      . '<td bgcolor="' . $nebel . '" style="background:' . $nebel . ';border-radius:6px;padding:20px 22px;font-family:' . $textS . ';font-size:15px;line-height:1.55;color:' . $text . ';">'
      . '<strong style="font-family:' . $kopfS . ';font-weight:600;color:' . $tinte . ';">Sie möchten wissen, wo Ihr Team steht?</strong><br>'
      . 'Im kostenfreien Erstgespräch schauen wir in 15 Minuten gemeinsam darauf. '
      . '<a href="https://ao-consult.de/ihr-erstgespraech/" style="color:' . $blau . ';font-weight:600;text-decoration:none;">Mehr erfahren &rarr;</a>'
      . '</td></tr></table></td></tr>'

      // Fuss
      . '<tr><td bgcolor="' . $fuss . '" style="background:' . $fuss . ';padding:24px 40px;font-family:' . $textS . ';font-size:13px;line-height:1.6;color:#a9adb8;" class="rand">'
      . '<strong style="color:#ffffff;">AO Consulting GmbH</strong><br>Zeiloch 13 · 76646 Bruchsal · '
      . '<a href="tel:+4917685933551" style="color:' . $blauHell . ';text-decoration:none;">0176&nbsp;85933551</a><br>'
      . '<a href="https://ao-consult.de" style="color:' . $blauHell . ';text-decoration:none;">ao-consult.de</a>'
      . ' · <a href="https://ao-consult.de/impressum/" style="color:' . $blauHell . ';text-decoration:none;">Impressum</a>'
      . ' · <a href="https://ao-consult.de/datenschutzerklaerung/" style="color:' . $blauHell . ';text-decoration:none;">Datenschutz</a>'
      . '</td></tr>'
      . '</table>'
      . '<p style="max-width:600px;margin:16px auto 0;font-family:' . $textS . ';font-size:12px;line-height:1.5;color:#6e7a8c;">'
      . 'Sie erhalten diese E-Mail, weil auf ao-consult.de das Magazin mit dieser Adresse angefordert wurde. Falls das nicht von Ihnen kam, können Sie die E-Mail einfach ignorieren.</p>'
      . '</td></tr></table></body></html>';

    // ---- Textfassung --------------------------------------------------------
    $txt = $anrede . "\n\n"
         . "vielen Dank für Ihr Interesse an Teamprophylaxe, dem Magazin gegen Lücken im Team.\n\n";
    foreach ($p['ausgaben'] as $a) $txt .= $a['titel'] . " herunterladen (PDF, " . AO_MAGAZIN_LINK_TAGE . " Tage gültig):\n" . $a['link'] . "\n\n";
    if ($newsletterSatz) $txt .= $newsletterSatz . "\n\n";
    if (!empty($erste['inhalt'])) {
        $txt .= "In dieser Ausgabe:\n";
        foreach ($erste['inhalt'] as $z) $txt .= "- " . $z[0] . ", " . $z[1] . ": " . $z[2] . "\n";
        $txt .= "\n";
    }
    $txt .= "Viel Freude beim Lesen. Fragen oder Anregungen? Antworten Sie einfach auf diese E-Mail.\n\n"
          . "Viele Grüße\nAdmir & Ovidiu\nGründer der AO Consulting GmbH\n\n"
          . "--\nAO Consulting GmbH · Zeiloch 13 · 76646 Bruchsal · 0176 85933551\nhttps://ao-consult.de\n";

    $bilder = [
        'logo'  => [__DIR__ . '/img/mail-logo-ao-consulting.png', 'image/png', 'ao-consulting.png'],
        'titel' => [__DIR__ . '/' . ($erste['mailbild'] ?? ''), 'image/jpeg', 'teamprophylaxe-titel.jpg'],
    ];
    $bilder['titel'][3] = 'https://ao-consult.de/' . ($erste['mailbild'] ?? '');
    $bilder['logo'][3]  = 'https://ao-consult.de/img/mail-logo-ao-consulting.png';
    return [$betreff, $html, $txt, $bilder];
}

// Verschickt die Mail: ueber Brevo, wenn der Schluessel da ist (Zustellung in
// Brevo nachvollziehbar), sonst ueber den Webserver. Liefert [ok, Info].
function ao_magazin_mail_senden($an, $absender, $p) {
    [$betreff, $html, $txt, $bilder] = ao_magazin_mail_bauen($p);
    if (function_exists('ao_brevo_aktiv') && ao_brevo_aktiv()) {
        // Bei Brevo stehen die Bilder als Adresse im HTML statt in der Mail
        foreach ($bilder as $cid => $b) $html = str_replace('cid:' . $cid, $b[3], $html);
        [$ok, $info] = ao_brevo_mail($an, trim($p['vorname'] . ' ' . $p['nachname']), $betreff, $html, $txt, 'magazin-download');
        if ($ok) return [true, 'Brevo ' . $info];
        $brevoFehler = 'Brevo ' . $info . ', Ersatz über Webserver: ';
    } else {
        $brevoFehler = '';
    }

    // ---- MIME (Versand ueber den Webserver) ---------------------------------
    $g1 = 'alt_' . bin2hex(random_bytes(8));
    $g2 = 'rel_' . bin2hex(random_bytes(8));
    $body  = "--$g1\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($txt)) . "\r\n";
    $body .= "--$g1\r\nContent-Type: multipart/related; boundary=\"$g2\"\r\n\r\n";
    $body .= "--$g2\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n";
    foreach ($bilder as $cid => $b) {
        $daten = is_file($b[0]) ? @file_get_contents($b[0]) : false;
        if ($daten === false) continue;
        $body .= "--$g2\r\nContent-Type: {$b[1]}; name=\"{$b[2]}\"\r\nContent-Transfer-Encoding: base64\r\n"
               . "Content-ID: <$cid>\r\nContent-Disposition: inline; filename=\"{$b[2]}\"\r\n\r\n" . chunk_split(base64_encode($daten)) . "\r\n";
    }
    $body .= "--$g2--\r\n--$g1--\r\n";

    $kopf = "From: =?UTF-8?B?" . base64_encode('AO Consulting · Teamprophylaxe') . "?= <$absender>\r\n"
          . "Reply-To: $absender\r\n"
          . "MIME-Version: 1.0\r\n"
          . "Content-Type: multipart/alternative; boundary=\"$g1\"\r\n"
          . "Auto-Submitted: auto-replied\r\n"
          . "X-Mailer: ao-consult.de/magazin\r\n";
    $ok = @mail($an, '=?UTF-8?B?' . base64_encode($betreff) . '?=', $body, $kopf, '-f ' . $absender);
    return [$ok, $brevoFehler . ($ok ? 'Webserver angenommen' : 'Webserver abgelehnt')];
}
