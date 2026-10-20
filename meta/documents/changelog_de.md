# Changelog OSVSuche

Neueste Version oben. Jede Version ist ein Git-Tag; im Plugin-Set wird der Tag gewählt.

## 0.12.3 (20.10.2026)

- Suchebene auf Kategorie-, Artikel- und Startseite: Nach dem Schließen (✕ oder „Zurück“) blieb die Artikelliste der Kategorie ausgeblendet (seit 0.9.0). Die Ebene setzte die Klasse osvs-ergebnis-aktiv, die außerhalb der Ergebnisseite Plentys Liste versteckt, und nahm sie beim Schließen nicht zurück. Jetzt wird sie beim Schließen entfernt
- Suche von einer Tag-Seite aus (z. B. /halloween_t38/, Begriff eingeben, Enter): Die Überschrift wechselt jetzt auf „Suchergebnisse für: …“ statt „Mit Tag … verknüpfte Artikel“ stehen zu lassen. „Zurück“ bis auf die Tag-Seite lädt sie neu, statt die zuletzt gesuchten Treffer unter der Tag-Adresse stehen zu lassen

## 0.12.2 (20.10.2026)

- Tag-Seiten (z. B. /halloween_t38/), /search ohne Begriff und Suchen mit nur einem Buchstaben zeigen wieder Plentys Artikelliste. Bisher blendete die Suche Plentys Liste aus und zeigte dauerhaft „Suchergebnisse werden geladen …“, weil sie ohne Suchbegriff (unter 2 Zeichen) nichts tat und auch nicht zurücktrat (seit 0.7.0). Meldung Alex 19.10.2026

## 0.12.1 (19.10.2026)

- Warenkorb aus der Suchkachel: Artikel mit mehreren Varianten (z. B. Maritim, Nussknacker König) gingen zur Artikelseite statt in den Korb. Die Suche übergab die Variantenzahl des ganzen Artikels; die Kategoriekachel übergibt 1, weil jede Variante eine eigene Kachel ist. Jetzt wie dort

## 0.12.0 (19.10.2026)

- Ergebnisseite: Warenkorb direkt aus der Kachel wie auf den Kategorieseiten (Schritt 3a). Die Suche setzt dafür Ceres' eigenen Baustein add-to-basket ein (Symbol oben rechts am Rechner, Knopf „In den Warenkorb“ am Handy), mit denselben Angaben wie die Ceres-Kategoriekachel; Ceres legt in den Korb, zählt, zeigt die Vorschau und schickt bei Varianten oder Bestellmerkmalen zur Artikelseite. Am Handy weicht „Artikel anzeigen“ dem Ceres-Knopf
- Preisabruf liefert dafür zusätzlich sc, iq, mn, mx, op, hop, hrop, hp, gpn, it

## 0.11.1 (19.10.2026)

- Kein Aufblitzen des blauen Knopfs „Artikel anzeigen“ beim Wechsel der Kategorie oder beim Nachladen: Die Suchkachel bringt „Zum Artikel“ gleich mit (Klasse osvk-zum wie OSVBadges, Gestaltung weiter von OSVBadges); OSVBadges setzte ihn bisher erst bis zu 1 Sekunde später
- Neue Versionsnummer auch, damit die Skript-Adresse (osvsuche.js?v=…) frisch ist: unter ?v=0.11.0 liegt im CDN von Set 72 versehentlich noch 0.10.0

## 0.11.0 (19.10.2026)

- Ergebnisseite: Kacheln wie auf den Kategorieseiten, Schritt 2. Bewertungssterne mit Anzahl, gleiches HTML wie das Feedback-Plugin auf Kategoriekacheln; Gestaltung (und Ausblenden ohne Bewertung) von OSVBadges. Der Preisabruf liefert dafür Durchschnitt und Anzahl (Felder fd, fc); fehlen sie, bleibt die Kachel ohne Sterne

## 0.10.0 (19.10.2026)

- Ergebnisseite: Kacheln wie auf den Kategorieseiten, Schritt 1. Lieferschild („Sofort versandfertig“, „Lieferbar ab …“) und am Rechner der Knopf „Zum Artikel“ kommen von OSVBadges (ab 0.7.0); die Suche liefert dafür Lieferbarkeit und Erscheinungsdatum mit (Preisabruf: neues Feld rel) und markiert ihre Kacheln mit osvk-voll
- UVP mit „UVP“ davor wie auf den Kategorieseiten, Spalten am Tablet wie dort (2 statt 3)
- Ohne OSVBadges 0.7.0 bleibt die Kachel wie bisher (eigener Knopf „Artikel anzeigen“)

## 0.9.9 (16.10.2026)

- Wörter, die nur über die Keywords gefunden werden (Honecker, Wärmepyramide): Der Link „Alle … Artikel anzeigen“ sucht mit dem getippten Wort statt mit dem Wortstamm („honecker“ statt „honeck“), der Hinweis „Ergebnisse für …“ entfällt dort

## 0.9.8 (15.10.2026)

- Sofort-Liste: Findet weder die Suche noch Plenty etwas, steht dort eine Zeile „Keine Treffer für …“ statt Plentys Kasten mit dreimal „Keine Suchergebnisse gefunden“
- Wörter, die nur über die Keywords gefunden werden, werden genau gesucht, nicht unscharf (Honecker zeigt keine Rauchschnecken mehr)

## 0.9.7 (14.10.2026)

- Wörter, die nur in den Keywords stehen (ACDC, Politiker, Wärmepyramide), werden gefunden, wenn sonst nichts übrig bliebe
- Sofort-Liste: Kann das getippte Wort kein bekanntes Wort mehr werden, geht die alte Liste nach einer kurzen Tipp-Pause zu (Simson zeigt nicht mehr die Liste von „Si“); beim flüssigen Weitertippen bleibt sie wie bisher stehen

## 0.9.6 (09.10.2026)

- Ergebnisebene beginnt unter dem Shop-Kopf, auch nach dem Scrollen
- Rückkehr an die alte Stelle

## 0.9.5 (08.10.2026)

- Messung - Trefferzahl, Suche ohne Treffer, Filter/Kategorie/Sortierung/Weitere, Klickposition (nur mit Einwilligung)

## 0.9.4 (08.10.2026)

- Tab-Titel der Ergebnisebene wie Plentys Ergebnisseite, GA4 select_item aus Liste und Ergebnissen (nur mit Einwilligung)

## 0.9.3 (08.10.2026)

- Tippfehler im Wortanfang und zu kurze Wortstämme korrigieren (eäucherhäuschen, engel barun)

## 0.9.2 (07.10.2026)

- Suche nach EAN, Suchen ohne Neuladen an GA4 melden (nur mit Einwilligung)

## 0.9.1 (07.10.2026)

- Hinweis zum Preis-Sternchen auch in der Sofort-Ergebnisansicht

## 0.9.0 (07.10.2026)

- Sofort-Ergebnisse bei Enter ohne Neuladen der Seite

## 0.8.1 (07.10.2026)

- Index im Seitenkopf vorladen, neuen Index sofort erkennen, Zahl in der Liste wie Ergebnisseite

## 0.8.0 (07.10.2026)

- Keywords, Stilwörter aus Variantentext 110 und Facettenwerte durchsuchbar (Rückmeldung Jasmin)

## 0.7.2 (07.10.2026)

- Filterwerte hängen von den übrigen gewählten Filtern ab (Rückmeldung Alex)

## 0.7.1 (07.10.2026)

- Ergebnisseite zeigt jede Variante als eigene Kachel (Rückmeldung Alex)

## 0.7.0 (07.10.2026)

- Filter aus den Plenty-Facetten, Filterleiste neu, kein Aufblitzen der alten Liste

## 0.6.0 (06.10.2026)

- Ergebnisseite nach PWA-Muster, fester Lader statt Versionsnummer in der Vorlage

## 0.5.2 (06.10.2026)

- Ergebnisseite mit Filtern (Kategorie, Hersteller, Preis) und Sortierung

## 0.5.1 (06.10.2026)

- Widget wie Ceres-Artikelraster einordnen (Typ/Kategorie categoryitem)

## 0.5.0 (06.10.2026)

- ShopBuilder-Widget OSV Suchergebnisse für die Ergebnisseite

## 0.4.6 (06.10.2026)

- Alle Treffer in der Liste aufklappen, Ergebnisseite mit korrigiertem Wort, ab 2 Buchstaben (Rückmeldungen Alex)

## 0.4.5 (06.10.2026)

- Wortteile in zusammengesetzten Wörtern ab 4 Buchstaben (Rückmeldung Alex)

## 0.4.4 (06.10.2026)

- Anfang eines Synonyms gewinnt beim Tippen (schwipp -> Schwibbogen statt Schippe)

## 0.4.3 (06.10.2026)

- Tippfehler in halben Wörtern, Liste beim Zurücklöschen behalten, exakt getipptes Wort zuerst

## 0.4.2 (06.10.2026)

- Liste am Handy auf volle Bildschirmbreite, beim Weitertippen letzte Liste behalten

## 0.4.1 (06.10.2026)

- Abwerten unabhängig von Groß-/Kleinschreibung und auch ueber den Artikelnamen

## 0.4.0 (06.10.2026)

- Stufe 2, Sofort-Liste am Ceres-Suchfeld, Kategoriepfad im Index

## 0.3.1 (06.10.2026)

- Rein numerische Variantennummern bei den Verkaufszahlen nicht mehr verwerfen

## 0.3.0 (06.10.2026)

- Live-Preise, Verkaufszahlen, Suchregeln in der Konfiguration

## 0.2.0 (05.10.2026)

- Index im Plugin-Speicher, Neuaufbau nur mit Schlüssel

## 0.1.0 (05.10.2026)

- Stufe 1, Suchindex unter /rest/osv-suche/index
