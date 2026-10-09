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
    $ch = curl_init($basis . ltrim($pfad, '/'));
    $kopf = ['Accept: application/json'];
    if ($daten !== null) { $kopf[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($daten)); }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $methode,
        CURLOPT_USERPWD        => ao_schluessel('close') . ':',
        CURLOPT_HTTPHEADER     => $kopf,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 12,
    ]);
    $antwort = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $fehler = curl_error($ch);
    curl_close($ch);
    if ($antwort === false || $code < 200 || $code >= 300) {
        throw new AoCloseFehler("$methode $pfad: HTTP $code " . ($fehler ?: mb_substr((string)$antwort, 0, 200)));
    }
    return json_decode($antwort, true) ?: [];
}

function ao_normal($s) { return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string)$s))); }
function ao_domain($url) {
    $h = parse_url(preg_match('#^https?://#i', $url) ? $url : 'https://' . $url, PHP_URL_HOST);
    return $h ? preg_replace('/^www\./i', '', mb_strtolower($h)) : '';
}

// Leads per Volltextsuche holen, Felder fuer die Gegenpruefung mitnehmen
function ao_close_leads_suchen($text) {
    $r = ao_close('GET', 'lead/?' . http_build_query([
        'query'   => $text,
        '_limit'  => 10,
        '_fields' => 'id,display_name,url,contacts,custom',
    ]));
    return isset($r['data']) ? $r['data'] : [];
}

// Liefert [lead, contact_id|null, wie gefunden] oder null
function ao_close_lead_finden($email, $domain, $praxis) {
    $email = mb_strtolower($email);
    // 1. E-Mail-Adresse eines Kontakts
    foreach (ao_close_leads_suchen('"' . $email . '"') as $l) {
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
    foreach (ao_close_leads_suchen('"' . $praxis . '"') as $l) {
        if (ao_normal($l['display_name'] ?? '') === ao_normal($praxis)) return [$l, null, 'Praxisname'];
    }
    return null;
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
            if (!$kontaktId) {
                // Person am Lead suchen (gleicher Name), sonst neu anlegen
                foreach ((array)($lead['contacts'] ?? []) as $c) {
                    if (ao_normal($c['name'] ?? '') === ao_normal($kontakt['name'])) { $kontaktId = $c['id']; break; }
                }
                if (!$kontaktId) {
                    $c = ao_close('POST', 'contact/', $kontakt + ['lead_id' => $leadId]);
                    $kontaktId = $c['id'] ?? null;
                    $wie .= ', Kontakt neu angelegt';
                }
            }
            $bericht = 'Vorhandener Lead gefunden (über ' . $wie . '): ' . ($lead['display_name'] ?? $leadId);
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

        // Aktivitaet "Magazin": vorhandene nutzen, sonst neu
        $wert = $d['newsletter'] ? 'Ja' : 'Nein';
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
                       . '. Newsletter: ' . ($d['newsletter'] ? 'ja' : 'nein') . '.'
                       . ' Anrufen: ' . (!empty($d['anruf']) ? 'ja, ausdrücklich erlaubt' : 'nein, nur per E-Mail') . '.'
                       . ' Person: ' . $kontakt['name'] . ', ' . $d['email'] . ', ' . $d['telefon'] . '.',
        ]);
        return $bericht . '.' . "\n  https://app.close.com/lead/$leadId/";
    } catch (AoCloseFehler $e) {
        return 'FEHLER bei Close, bitte von Hand eintragen. (' . $e->getMessage() . ')';
    }
}
