# AO-Consulting-Webseite — Projektstand

**Entwurf und Paket:** Ovidiu Rieger, „AO Consulting · Entwurf v5.0", 17./18.09.2026
**Übernahme nach GitHub:** 18.09.2026

## Woher die Dateien stammen

Aus Ovis fertigem Paket `ao-consult-website-v5.0.zip`. Der Inhalt von `website/`
wurde **unverändert** übernommen: 20 Seiten, `assets/css`, `assets/js`,
`assets/fonts`, `img/`, `urlaub/`, `.nojekyll`. Ovis eigene Anleitung liegt als
`doku/LIESMICH-ovi.md` bei.

Ergänzt wurden nur die Dinge, die zum GitHub-Ablauf gehören: die zwei Workflows,
`README.md`, `robots.txt` und `sitemap.xml`.

## Geprüft beim Übernehmen

- **Alle 20 Seiten geladen**, auf 1440 und auf 390 Pixel Breite: keine fehlende Datei,
  keine Fehlermeldung in der Konsole, kein kaputtes Bild, kein seitliches Wegscrollen.
- **Fremde Server:** nur `fast.wistia.net` für das Video, und das lädt erst nach
  Zustimmung. Schriften liegen lokal. Keine Zählprogramme.
- **Alle verlinkten Dateien vorhanden.**
- **`noindex` steht in allen 20 Seiten** — richtig für die Vorschau, muss vor dem
  Livegang raus.
- **Zapier-Platzhalter im Urlaubsantrag ist unberührt**, die Seite verschickt nichts.

## Eine Änderung am Vorschau-Ablauf

Auf Ovis ausdrücklichen Hinweis in seiner Anleitung: Der Ordner `website/urlaub/`
wird beim Bauen der Vorschau **gelöscht**, damit das Urlaubsformular nicht ohne
Passwort unter der Vorschau-Adresse steht. Im Livegang-Ablauf bleibt er drin.
Die Zeile steht in `.github/workflows/vorschau.yml`, Schritt „Vorschau-Version bauen".

Außerdem sucht der Ablauf die HTML-Seiten jetzt mit `find` statt über feste
Ordnernamen — derselbe Fehler hatte vorher bei Bösing und Code Finance den ersten
Durchlauf scheitern lassen.

## Nicht übernommen

Der ältere Astro-Stand in OneDrive
(`Webseiten KI/Projekt-AO-Consulting-Webseite/05_stufe-5.../output/website/`,
9. September, vier Seiten, Schriftart Carlito). Überholt, Entscheidung Admir 18.09.2026.

## Offen

Siehe Liste in der `README.md`.
