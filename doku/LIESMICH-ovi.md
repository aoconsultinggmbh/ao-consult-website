# ao-consult.de · Entwurf v5.0 als statische Webseite

Entpackt aus dem Cowork-Artefakt „AO Consulting · Entwurf v5.0" (Stand 17.09.2026)
am 18.09.2026. Das Artefakt selbst wurde nicht verändert.

## Aufbau

```
website/                 nur was hier liegt, geht online
  index.html             Startseite
  *.html                 19 Unterseiten, darunter danke.html, sos.html, kontakt.html,
                         impressum.html, datenschutz.html, agb.html, sechs Fallstudien
  urlaub/index.html      Urlaubsantrag (intern, siehe unten)
  assets/css/stil.css    das komplette Stylesheet
  assets/js/             einwilligung.js, barrierefreiheit.js, skript.js, telefon.js,
                         three.min.js, GLTFLoader.js, kiefer-modell.js, kiefer.js
  assets/fonts/          Poppins, Roboto, Caveat als WOFF2, lokal, keine Google-Server
  img/                   91 WebP-Bilder mit sprechenden Namen aus den Alt-Texten,
                         Logos als SVG, favicon.svg, apple-touch-icon.png
  .nojekyll              damit GitHub Pages nichts umbaut
```

Keine Verbindung zu fremden Servern beim Laden, geprüft. Alle Seiten haben
`noindex, nofollow`, das ist für die Vorschau richtig und muss vor dem Livegang raus.

## Für Admir: so kommt es auf GitHub

Nach dem Ablauf „Kundenwebseiten veröffentlichen":

1. github.com → **+** → **New repository** → Owner **aoconsultinggmbh** → Name
   `ao-consult-website` → **Public** → Create repository
2. Settings → **Pages** → Source auf **GitHub Actions**, Custom domain
   `ao-consult.vorschau.ao-consult.de` → Save
3. Den Ordner `website/` und die zwei Abläufe aus einem bestehenden Projekt
   (`.github/workflows/`) hochladen, Namen anpassen
4. **Wichtig, eine Zeile im Vorschau-Ablauf:** den Ordner `website/urlaub/` beim
   Veröffentlichen zur Vorschau **auslassen**. Sonst liegt das Urlaubsformular ohne
   Passwort unter der Vorschau-Adresse. Im Livegang-Ablauf bleibt der Ordner drin.
   Beispiel für den Schritt, der `website/` nach Pages kopiert:
   `rsync -a --exclude 'urlaub/' website/ _site/` oder vor dem Upload `rm -rf website/urlaub`
   (nur im Ablauf, nicht im Projekt).

## Die Seite /urlaub

- Liegt in `website/urlaub/index.html`, alles in einer Datei
- Die Zapier-Adresse steht hier **noch als Platzhalter** (`ZAPIER_HOOK_URL_HIER_EINTRAGEN`).
  Solange der Platzhalter drin ist, verschickt die Seite nichts, sondern zeigt nur die
  Zusammenfassung. Deshalb kann sie in der Vorschau gefahrlos ausprobiert werden.
- Die echte Adresse steht in der Datei auf Ovidius Mac
  (`ao-mini-os-v0.2/urlaub-asana/website/urlaub/index.html`, Zeile mit `ZAPIER_HOOK_URL`).
  Sie kommt **erst beim Livegang** in die Datei, zusammen mit dem Verzeichnisschutz
  im KAS auf `/urlaub` (Tools → Verzeichnisschutz → Verzeichnisschutz anlegen).
- Wer die Adresse kennt, kann Anträge ins Asana-Board schieben. Deshalb nicht in ein
  öffentliches Projekt schreiben. Sauberer Weg: als GitHub-Secret `ZAPIER_URLAUB`
  anlegen und im Livegang-Ablauf per `sed` in die Datei setzen, bevor sie per FTPS
  hochgeladen wird.

## Vor dem Livegang (aus dem Ablauf, hier nur die Erinnerung)

- `noindex` aus allen Seiten raus, außer `urlaub/`
- Hinweisleiste „Entwurf v5.0" oben entfernen (steht im Rumpf der Seiten)
- Domain in canonical, robots.txt, sitemap.xml
- Kontakt- und SOS-Formular scharf schalten (PHP-Empfänger abstimmen), Honeypot
- Datenschutzerklärung gegen den Code prüfen: Wistia nur nach Einwilligung, Schriften lokal

## Was nicht mitgekommen ist

Die Bild-Dateinamen stammen aus den Alt-Texten und sind gekürzt. Wer für die Bildsuche
Keyword und Ort vorne haben will, benennt vor dem Livegang um (dann auch in den
HTML-Dateien). Drei Bilder ohne Alt-Text heißen `bild-a18.webp`, `bild-a73.webp`,
`bild-a107.webp`, `bild-a108.webp`; die brauchen vor dem Livegang einen Alt-Text.
