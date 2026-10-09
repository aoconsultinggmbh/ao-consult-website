<?php
/*
 * Close-Anbindung fuer das Magazin-Formular. Wird von magazin.php eingebunden.
 *
 * Ablauf:
 *  1. Lead suchen: erst ueber die E-Mail-Adresse, dann ueber die Webseite,
 *     dann ueber den Praxisnamen. Jeder Treffer wird gegengeprueft.
 *  2. Nichts gefunden: neuen Lead anlegen (Quelle "Teamprophylaxe Magazin").
 *     Lead gefunden, Person fehlt: Kontakt zum Lead hinzufuegen.
 *  3. Aktivitaet "Magazin" (Feld "Mazagin" Ja/Nein) anlegen und anpinnen.
 *     Gibt es sie schon, wird sie nur von Nein auf Ja gesetzt, nie zurueck.
 *  4. Notiz am Lead: welche Ausgabe, Newsletter ja/nein.
 *
 * Der Schluessel kommt aus _konfig/schluessel.php (schreibt der Livegang aus
 * dem GitHub-Secret CLOSE_API_KEY). Ohne Schluessel passiert hier nichts.
 * Geht etwas schief, wird nichts doppelt angelegt: dann steht in der
 * Info-Mail an service@, was von Hand zu tun ist.
 */
if (!defined('AO_MAGAZIN')) { http_response_code(404); exit; }

const AO_CLOSE_TYP_MAGAZIN = 'actitype_41vbywRDDMlsJboeeUEYES';
const AO_CLOSE_FELD_MAGAZIN = 'cf_RUSRl565bUWRegqiMGQJz54QgPDCIgpuWvN2oqbFspn'; // "Mazagin" Ja/Nein
const AO_CLOSE_FELD_QUELLE = 'cf_yL9mfhHk1ZqrDZ6ierSXfQGjN0JsjkHjiD35xY52DFl';  // "Quelle"
const AO_CLOSE_QUELLE      = 'Teamprophylaxe Magazin';

function ao_schluessel($name) {
    static $k = null;
    if ($k === null) {
        $f = __DIR__ . '/_konfig/schluessel.php';
        $k = is_readable($f) ? (array)(include $f) : [];
    }
    return isset($k[$name]) ? trim((string)$k[$name]) : '';
}

class AoCloseFehler extends Exception {}

function ao_close($methode, $pfad, $daten = null) {
    // AO_CLOSE_TEST nur fuer Tests auf dem eigenen Rechner (Attrappe statt Close)
    $basis = getenv('AO_CLOSE_TEST') ?: 'https://api.close.com/api/v1/';
    [$code, $antwort, $fehler] = ao_http($methode, $basis . ltrim($pfad, '/'),
        ['Authorization: Basic ' . base64_encode(ao_schluessel('close') . ':')], $daten);
    if ($code < 200 || $code >= 300) {
        throw new AoCloseFehler("$methode " . strtok($pfad, '?') . ": HTTP $code " . ($fehler ?: mb_substr((string)$antwort, 0, 200)));
    }
    return json_decode($antwort, true) ?: [];
}

function ao_normal($s) { return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string)$s))); }
function ao_domain($url) {
    $h = parse_url(preg_match('#^https?://#i', $url) ? $url : 'https://' . $url, PHP_URL_HOST);
    return $h ? preg_replace('/^www\./i', '', mb_strtolower($h)) : '';
}

// Leads per Volltextsuche holen, Felder fuer die Gegenpruefung mitnehmen
function ao_close_leads_suchen($text, $limit = 25) {
    $r = ao_close('GET', 'lead/?' . http_build_query([
        'query'   => $text,
        '_limit'  => $limit,
        '_fields' => 'id,display_name,url,contacts,custom',
    ]));
    return isset($r['data']) ? $r['data'] : [];
}

// Leads mit genau dieser E-Mail-Adresse an einem Kontakt (Erweiterte Suche von Close).
// Die Volltextsuche taugt dafuer nicht: Die eigene Adresse steckt in hunderten Leads
// (gesendete Mails), dann ist der richtige Lead nicht unter den ersten Treffern.
function ao_close_leads_mit_email($email) {
    $ids = [];
    try {
        $r = ao_close('POST', 'data/search/', [
            'query' => ['type' => 'and', 'queries' => [
                ['type' => 'object_type', 'object_type' => 'lead'],
                ['type' => 'has_related', 'this_object_type' => 'lead', 'related_object_type' => 'contact',
                 'related_query' => ['type' => 'and', 'queries' => [
                    ['type' => 'has_related', 'this_object_type' => 'contact', 'related_object_type' => 'contact_email',
                     'related_query' => ['type' => 'and', 'queries' => [
                        ['type' => 'field_condition',
                         'field' => ['type' => 'regular_field', 'object_type' => 'contact_email', 'field_name' => 'email'],
                         'condition' => ['type' => 'text', 'mode' => 'phrase', 'value' => $email]],
                     ]]],
                 ]]],
            ]],
            '_fields' => ['lead' => ['id']],
            'results_limit' => 20,
        ]);
        foreach ((array)($r['data'] ?? []) as $l) if (!empty($l['id'])) $ids[] = $l['id'];
    } catch (AoCloseFehler $e) {
        ao_protokoll('close-suche', false, 'Erweiterte Suche: ' . $e->getMessage());
    }
    if (!$ids) {
        // Ersatz: alte Suchsprache, eng auf Kontakt-E-Mails
        try {
            foreach (ao_close_leads_suchen('email:"' . $email . '"', 50) as $l) $ids[] = $l['id'];
        } catch (AoCloseFehler $e) {
            ao_protokoll('close-suche', false, 'email-Suche: ' . $e->getMessage());
        }
    }
    $treffer = [];
    foreach (array_slice(array_unique($ids), 0, 20) as $id) {
        $l = ao_close('GET', 'lead/' . $id . '/?_fields=id,display_name,url,contacts,custom');
        if ($l) $treffer[] = $l;
    }
    return $treffer;
}

// Liefert [lead, contact_id|null, wie gefunden] oder null
function ao_close_lead_finden($email, $domain, $praxis) {
    $email = mb_strtolower($email);
    // 1. E-Mail-Adresse eines Kontakts (jeder Treffer wird gegengeprueft)
    foreach (ao_close_leads_mit_email($email) as $l) {
        foreach ((array)($l['contacts'] ?? []) as $c) {
            foreach ((array)($c['emails'] ?? []) as $e) {
                if (mb_strtolower($e['email'] ?? '') === $email) return [$l, $c['id'], 'E-Mail'];
            }
        }
    }
    // 2. Webseite (Lead-Adresse oder Feld "Webseite")
    if ($domain !== '') {
        foreach (ao_close_leads_suchen('"' . $domain . '"') as $l) {
            $w = ($l['custom']['Webseite'] ?? '') ?: ($l[ 'custom.cf_kQHgHa53GydpK8IdzS3xkqVvfsA3lBvRty1Uleyzjy1'] ?? '');
            if (ao_domain($l['url'] ?? '') === $domain || ($w && ao_domain($w) === $domain)) return [$l, null, 'Webseite'];
        }
    }
    // 3. Praxisname, genau gleich geschrieben
    if (trim($praxis) === '') return null;
    foreach (ao_close_leads_suchen('"' . $praxis . '"') as $l) {
        if (ao_normal($l['display_name'] ?? '') === ao_normal($praxis)) return [$l, null, 'Praxisname'];
    }
    return null;
}

function ao_ziffern($tel) {
    $z = preg_replace('/\D/', '', (string)$tel);
    if (strpos($z, '0049') === 0) $z = '0' . substr($z, 4);
    elseif (strpos($z, '49') === 0 && strlen($z) > 10) $z = '0' . substr($z, 2);
    return $z;
}

// Ergaenzt einen vorhandenen Kontakt um neue Telefonnummer/E-Mail.
// Vorhandene Eintraege bleiben stehen (nichts wird ueberschrieben).
// Liefert eine kurze Beschreibung der Aenderung oder ''.
function ao_close_kontakt_ergaenzen($c, $email, $telefon) {
    $emails = (array)($c['emails'] ?? []);
    $phones = (array)($c['phones'] ?? []);
    $neu = [];
    $hatMail = false;
    foreach ($emails as $e) if (mb_strtolower($e['email'] ?? '') === mb_strtolower($email)) $hatMail = true;
    if (!$hatMail) { $emails[] = ['type' => 'office', 'email' => $email]; $neu[] = 'E-Mail ' . $email; }
    $hatTel = false;
    foreach ($phones as $ph) if (ao_ziffern($ph['phone'] ?? '') === ao_ziffern($telefon)) $hatTel = true;
    if (!$hatTel && ao_ziffern($telefon) !== '') { $phones[] = ['type' => 'office', 'phone' => $telefon]; $neu[] = 'Telefon ' . $telefon; }
    if (!$neu) return '';
    $saubere = function ($liste, $feld) {
        return array_values(array_map(function ($x) use ($feld) { return ['type' => $x['type'] ?? 'office', $feld => $x[$feld]]; }, $liste));
    };
    ao_close('PUT', 'contact/' . $c['id'] . '/', ['emails' => $saubere($emails, 'email'), 'phones' => $saubere($phones, 'phone')]);
    return 'Kontakt ergänzt: ' . implode(', ', $neu);
}

function ao_close_eintragen($d) {
    if (ao_schluessel('close') === '') return 'Close ist noch nicht angebunden (kein Schlüssel). Bitte von Hand eintragen.';
    try {
        $kontakt = [
            'name'   => $d['vorname'] . ' ' . $d['nachname'],
            'emails' => [['type' => 'office', 'email' => $d['email']]],
            'phones' => [['type' => 'office', 'phone' => $d['telefon']]],
        ];
        $fund = ao_close_lead_finden($d['email'], ao_domain($d['webseite']), $d['praxis']);
        if ($fund) {
            [$lead, $kontaktId, $wie] = $fund;
            $leadId = $lead['id'];
            // Vollstaendigen Lead holen (alle Kontakte mit Nummern und Adressen)
            $voll = ao_close('GET', 'lead/' . $leadId . '/?_fields=id,display_name,url,contacts');
            if (!empty($voll['contacts'])) $lead['contacts'] = $voll['contacts'];
            $kontaktObj = null;
            foreach ((array)($lead['contacts'] ?? []) as $c) {
                if ($kontaktId && $c['id'] === $kontaktId) { $kontaktObj = $c; break; }
                if (!$kontaktId && ao_normal($c['name'] ?? '') === ao_normal($kontakt['name'])) { $kontaktObj = $c; $kontaktId = $c['id']; break; }
            }
            $aenderung = '';
            if ($kontaktObj) {
                // Bekannte Person: neue Nummer/E-Mail ergaenzen
                $aenderung = ao_close_kontakt_ergaenzen($kontaktObj, $d['email'], $d['telefon']);
            } else {
                $c = ao_close('POST', 'contact/', $kontakt + ['lead_id' => $leadId]);
                $kontaktId = $c['id'] ?? null;
                $aenderung = 'Kontakt ' . $kontakt['name'] . ' neu angelegt';
            }
            // Webseite nachtragen, wenn am Lead noch keine steht
            if (trim((string)($voll['url'] ?? $lead['url'] ?? '')) === '' && $d['webseite'] !== '') {
                ao_close('PUT', 'lead/' . $leadId . '/', ['url' => $d['webseite']]);
                $aenderung .= ($aenderung ? ', ' : '') . 'Webseite ergänzt';
            }
            $bericht = 'Vorhandener Lead gefunden (über ' . $wie . '): ' . ($lead['display_name'] ?? $leadId)
                     . ($aenderung ? '. ' . $aenderung : '. Kontaktdaten waren aktuell');
        } else {
            $neu = ao_close('POST', 'lead/', [
                'name'     => $d['praxis'],
                'url'      => $d['webseite'],
                'contacts' => [$kontakt],
                'custom.' . AO_CLOSE_FELD_QUELLE => AO_CLOSE_QUELLE,
            ]);
            $leadId = $neu['id'];
            $kontaktId = $neu['contacts'][0]['id'] ?? null;
            $bericht = 'Neuer Lead angelegt: ' . $d['praxis'];
        }

        // Aktivitaet "Magazin": Wer das Magazin anfordert, steht auf "Mazagin: Ja"
        // (Wunsch Admir, 09.10.2026). Vorhandene Aktivitaet wird auf Ja gesetzt,
        // sonst neu angelegt und angepinnt. Ob der Newsletter bestaetigt ist,
        // steht in der Notiz.
        $status = $d['newsletter_status'] ?? ($d['newsletter'] ? 'ja' : 'nein');
        $wert = 'Ja';
        $vorhanden = ao_close('GET', 'activity/custom/?' . http_build_query([
            'lead_id' => $leadId, 'custom_activity_type_id' => AO_CLOSE_TYP_MAGAZIN, '_limit' => 1,
        ]));
        $akt = $vorhanden['data'][0] ?? null;
        if ($akt) {
            if ($wert === 'Ja' && ($akt['custom.' . AO_CLOSE_FELD_MAGAZIN] ?? '') !== 'Ja') {
                ao_close('PUT', 'activity/custom/' . $akt['id'] . '/', ['custom.' . AO_CLOSE_FELD_MAGAZIN => 'Ja']);
                $bericht .= '. Aktivität "Magazin" auf Ja gesetzt';
            } else {
                $bericht .= '. Aktivität "Magazin" war schon da';
            }
        } else {
            $akt = ao_close('POST', 'activity/custom/', [
                'custom_activity_type_id' => AO_CLOSE_TYP_MAGAZIN,
                'lead_id'    => $leadId,
                'contact_id' => $kontaktId,
                'status'     => 'published',
                'pinned'     => true,
                'custom.' . AO_CLOSE_FELD_MAGAZIN => $wert,
            ]);
            $bericht .= '. Aktivität "Magazin" angelegt (Mazagin: ' . $wert . ')';
            if (empty($akt['pinned'])) {
                try { ao_close('PUT', 'activity/custom/' . $akt['id'] . '/', ['pinned' => true]); }
                catch (AoCloseFehler $e) { $bericht .= ', anpinnen bitte von Hand'; }
            }
        }

        ao_close('POST', 'activity/note/', [
            'lead_id' => $leadId,
            'note'    => 'Teamprophylaxe-Magazin über die Webseite angefordert: ' . $d['ausgaben']
                       . '. Newsletter: ' . ($status === 'ausstehend' ? 'angefragt, Bestätigung per Mail ausstehend' : ($status === 'ja' ? 'ja' : 'nein')) . '.'
                       . ' Anrufen: ' . (!empty($d['anruf']) ? 'ja, ausdrücklich erlaubt' : 'nein, nur per E-Mail') . '.'
                       . ' Person: ' . $kontakt['name'] . ', ' . $d['email'] . ', ' . $d['telefon'] . '.',
        ]);
        return $bericht . '.' . "\n  https://app.close.com/lead/$leadId/";
    } catch (AoCloseFehler $e) {
        return 'FEHLER bei Close, bitte von Hand eintragen. (' . $e->getMessage() . ')';
    }
}

// Nach dem Klick in der Bestaetigungsmail (Double-Opt-in): "Mazagin" auf Ja
function ao_close_newsletter_bestaetigt($email) {
    if (ao_schluessel('close') === '') return 'kein Schlüssel';
    try {
        $fund = ao_close_lead_finden($email, '', '');
        if (!$fund) return 'Lead nicht gefunden';
        $leadId = $fund[0]['id'];
        $vorhanden = ao_close('GET', 'activity/custom/?' . http_build_query([
            'lead_id' => $leadId, 'custom_activity_type_id' => AO_CLOSE_TYP_MAGAZIN, '_limit' => 1,
        ]));
        $akt = $vorhanden['data'][0] ?? null;
        if ($akt) {
            ao_close('PUT', 'activity/custom/' . $akt['id'] . '/', ['custom.' . AO_CLOSE_FELD_MAGAZIN => 'Ja']);
        } else {
            $akt = ao_close('POST', 'activity/custom/', [
                'custom_activity_type_id' => AO_CLOSE_TYP_MAGAZIN, 'lead_id' => $leadId, 'contact_id' => $fund[1],
                'status' => 'published', 'pinned' => true, 'custom.' . AO_CLOSE_FELD_MAGAZIN => 'Ja',
            ]);
        }
        ao_close('POST', 'activity/note/', [
            'lead_id' => $leadId,
            'note'    => 'Newsletter Teamprophylaxe bestätigt (Double-Opt-in) am ' . date('d.m.Y H:i') . ' Uhr von ' . $email . '.',
        ]);
        return 'ok ' . $leadId;
    } catch (AoCloseFehler $e) {
        return 'FEHLER ' . $e->getMessage();
    }
}
