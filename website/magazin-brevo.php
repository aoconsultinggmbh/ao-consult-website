<?php
/*
 * Brevo-Anbindung fuer das Magazin. Wird von magazin.php eingebunden.
 *  - ao_brevo_mail():  einzelne Mail ueber Brevo verschicken (Download-Mail,
 *                      Info an service@). In Brevo unter "Transaktional ->
 *                      Protokolle" sieht man jede Mail mit Zustellstatus.
 *  - ao_brevo_doi():   Newsletter-Anmeldung mit Bestaetigungsmail
 *                      (Double-Opt-in, Vorlage in Brevo, ID siehe unten).
 * Der Schluessel kommt aus _konfig/schluessel.php (GitHub-Secret BREVO_API_KEY).
 */
if (!defined('AO_MAGAZIN')) { http_response_code(404); exit; }

const AO_BREVO_ABSENDER      = 'service@ao-consult.de';
const AO_BREVO_ABSENDER_NAME = 'AO Consulting · Teamprophylaxe';
const AO_BREVO_LISTE_NAME    = 'Teamprophylaxe Magazin';   // wird bei Bedarf angelegt
const AO_BREVO_DOI_VORLAGE   = 79;                          // Vorlage "Newsletter bestätigen (Double-Opt-in)"

function ao_brevo_aktiv() { return ao_schluessel('brevo') !== ''; }

function ao_brevo($methode, $pfad, $daten = null) {
    // AO_BREVO_TEST nur fuer Tests auf dem eigenen Rechner (Attrappe statt Brevo)
    $basis = getenv('AO_BREVO_TEST') ?: 'https://api.brevo.com/v3/';
    [$code, $antwort, $fehler] = ao_http($methode, $basis . ltrim($pfad, '/'),
        ['api-key: ' . ao_schluessel('brevo')], $daten);
    return [$code >= 200 && $code < 300, $code, json_decode($antwort, true) ?: [], $fehler ?: mb_substr((string)$antwort, 0, 200)];
}

// Liefert [ok, Info]
function ao_brevo_mail($an, $anName, $betreff, $html, $text, $tag, $antwortAn = null) {
    [$ok, $code, $r, $info] = ao_brevo('POST', 'smtp/email', [
        'sender'      => ['name' => AO_BREVO_ABSENDER_NAME, 'email' => AO_BREVO_ABSENDER],
        'to'          => [['email' => $an, 'name' => $anName ?: $an]],
        'replyTo'     => ['email' => $antwortAn ?: AO_BREVO_ABSENDER],
        'subject'     => $betreff,
        'htmlContent' => $html,
        'textContent' => $text,
        'tags'        => [$tag],
    ]);
    return [$ok, $ok ? ($r['messageId'] ?? 'gesendet') : "HTTP $code $info"];
}

// ID der Liste "Teamprophylaxe Magazin"; legt sie beim ersten Mal an und merkt sie sich
function ao_brevo_liste_id() {
    $f = AO_MAGAZIN_DATEIEN . '/brevo-liste.txt';
    if (is_readable($f) && ($id = (int)trim(file_get_contents($f))) > 0) return $id;
    for ($offset = 0; $offset < 500; $offset += 50) {
        [$ok, , $r] = ao_brevo('GET', 'contacts/lists?limit=50&offset=' . $offset);
        if (!$ok) break;
        foreach ((array)($r['lists'] ?? []) as $l) {
            if (trim($l['name']) === AO_BREVO_LISTE_NAME) { @file_put_contents($f, $l['id']); return (int)$l['id']; }
        }
        if (count((array)($r['lists'] ?? [])) < 50) break;
    }
    [$ok, , $r] = ao_brevo('POST', 'contacts/lists', ['name' => AO_BREVO_LISTE_NAME, 'folderId' => 1]);
    if ($ok && !empty($r['id'])) { @file_put_contents($f, $r['id']); return (int)$r['id']; }
    return 0;
}

// Newsletter-Anmeldung mit Bestaetigungsmail. Liefert [ok, Info]
function ao_brevo_doi($email, $vorname, $nachname, $weiterleitung) {
    $liste = ao_brevo_liste_id();
    if (!$liste) return [false, 'Liste nicht gefunden und nicht anlegbar'];
    [$ok, $code, , $info] = ao_brevo('POST', 'contacts/doubleOptinConfirmation', [
        'email'          => $email,
        'attributes'     => ['VORNAME' => $vorname, 'NACHNAME' => $nachname],
        'includeListIds' => [$liste],
        'templateId'     => AO_BREVO_DOI_VORLAGE,
        'redirectionUrl' => $weiterleitung,
    ]);
    return [$ok, $ok ? "Liste $liste" : "HTTP $code $info"];
}
