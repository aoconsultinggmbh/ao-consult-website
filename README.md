# ao-consult-website

Die eigene Webseite der **AO Consulting GmbH**.
Entwurf, Gestaltung und Auslieferungspaket: **Ovidiu Rieger** („Entwurf v5.0", 17.09.2026).

## Aufbau

| Ordner | Inhalt |
|---|---|
| `website/` | **Die Seite. Nur was hier liegt, geht online.** |
| `doku/` | Unterlagen, darunter Ovis eigene Anleitung `LIESMICH-ovi.md` |
| `.github/workflows/` | Die zwei Abläufe: Vorschau und Livegang |

## Die zwei Zustände

- **Zweig `main`** → Vorschau unter <https://ao-consult.vorschau.ao-consult.de>.
  Suchmaschinen sind ausgesperrt. Änderungen sind nach ein bis zwei Minuten zu sehen.
- **Zweig `live`** → die echte Seite beim Hoster. **Nichts geht ohne Freigabe live.**

## Was drin ist

20 Seiten: Startseite, Über uns, Partner, Kontakt, Danke, Empfehlungsprogramm,
Soforthilfe, Ihr Erstgespräch, Ihr Beratungsgespräch, Gleichstellung, Impressum,
Datenschutz, AGB, Fallstudien-Übersicht und sechs einzelne Fallstudien.
Dazu 91 Bilder, Schriften (Poppins, Roboto, Caveat) lokal im Paket, ein gemeinsames
Stylesheet und acht Skripte — darunter unsere Bausteine für Einwilligung und
Barrierefreiheit sowie ein 3D-Kiefermodell (three.js).

## Der Ordner `website/urlaub/` — bitte lesen

Das ist der **interne Urlaubsantrag**, kein Teil der öffentlichen Seite.

- Der Vorschau-Ablauf **löscht diesen Ordner beim Veröffentlichen**. Er liegt also
  nie unter der Vorschau-Adresse. Das ist Absicht und darf nicht geändert werden.
- Die Zapier-Adresse steht als Platzhalter (`ZAPIER_HOOK_URL_HIER_EINTRAGEN`) in der
  Datei. Solange der drin ist, verschickt die Seite nichts.
- **Die echte Adresse gehört nicht in dieses öffentliche Projekt.** Sie kommt als
  GitHub-Secret `ZAPIER_URLAUB` hinein und wird im Livegang-Ablauf eingesetzt.
  Dazu im KAS ein Verzeichnisschutz auf `/urlaub`.
- Secret anlegen: Projekt -> **Settings** -> **Secrets and variables** -> **Actions** ->
  **New repository secret** -> Name `ZAPIER_URLAUB`, Wert die Adresse aus Zapier
  (Zap „Urlaubsantrag Webseite -> Asana“, Schritt 1, „Your webhook URL“). Ovidiu hat sie.
- Fehlt das Secret, laesst der Livegang-Ablauf den Ordner `urlaub/` bewusst weg.

## Regeln

- Keine Schriften, Skripte oder Karten von fremden Servern ohne Freigabe.
- Das Video läuft über **Wistia** und lädt erst nach Zustimmung im
  Einwilligungsfenster. Das ist Absicht und muss so bleiben.
- Impressum, Datenschutz und AGB nur nach Rücksprache ändern.
- Zugangsdaten nur in den Passwort-Manager und in GitHub-Secrets.

## Vor dem Livegang zu erledigen

- [ ] **`noindex` aus allen 20 Seiten entfernen** — außer aus `urlaub/`.
      Derzeit steht es in jeder Seite, richtig für die Vorschau, falsch für live.
- [ ] **`robots.txt` und `sitemap.xml`** liegen bereit und zeigen auf `ao-consult.de`.
      Erst nach dem Entfernen von `noindex` sinnvoll.
- [ ] **Kontaktformulare** prüfen und scharf schalten (Empfänger, PHP-Versand, Honeypot).
- [ ] **Impressum, Datenschutz und AGB** gegenlesen lassen.
- [ ] **3D-Kiefermodell:** Die GLB-Datei fehlt, das Skript erwartet sie. Bei Ovi nachfragen.
- [ ] **Urlaubsantrag:** Secret setzen, Verzeichnisschutz im KAS anlegen.
- [ ] **Umzug planen:** alte Adressen von `ao-consult.de` weiterleiten, damit keine
      Google-Platzierung verloren geht.
- [ ] **Messung** nach demselben Muster wie bei den Kundenprojekten
      (Google Analytics und Search Console über `ao-konfiguration.js`).
