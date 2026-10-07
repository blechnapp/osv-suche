# OSV Suche

Eigene Shop-Suche. Der Suchindex wird aus Plenty gebaut, die Suche läuft im Browser.

## Adressen
- `/rest/osv-suche/index` liefert den gespeicherten Index (mit Suchregeln aus der Konfiguration), baut nie selbst.
- `/rest/osv-suche/rebuild?token=…` baut den Index neu (~45 s) und speichert ihn im Plugin-Speicher.
- `/rest/osv-suche/preise?ids=1,2,3` liefert aktuelle Preise und Verfügbarkeit für bis zu 24 Varianten.
- `POST /rest/osv-suche/verkauf?token=…` speichert Verkaufszahlen je Variantennummer (JSON), wirkt beim nächsten Neuaufbau.

## Konfiguration
- **Suchindex:** Schlüssel für Neuaufbau und Verkaufszahlen. Leer = gesperrt.
- **Suchregeln:** Synonyme (`suchwort=gemeint;…`), Eigenmarken, Eigenmarken-Bonus, Verkaufsbonus, Abwerten (Kategorien) und Faktor,
  Herkunftswörter (zählen nur leicht), Tippfehler-Toleranz. Änderungen wirken nach dem nächsten Neuaufbau.

Warum kein Zwischenspeicher: Plentys CachingRepository erlaubt für Plugins höchstens 512 Byte je Wert (gemessen 05.10.2026).

## Sofort-Liste (ab 0.4.0)
Container-Verknüpfung: **OSV Suche Sofort-Liste** → `Ceres::Script.AfterScriptsLoaded`.
Das Skript reagiert auf jedes Ceres-Suchfeld (`input.search-input`, auch das Handy-Feld), lädt den Index beim ersten Fokus
(1 Stunde im Browser gespeichert) und zeigt bis zu 8 Artikel mit Bild und Preis. Preise werden live nachgeladen.
Pfeiltasten + Enter öffnen den markierten Artikel, Enter ohne Auswahl führt zur normalen Ergebnisseite.

**Absicherung:** Die Ceres-Vorschläge werden nur ausgeblendet, solange unsere Liste Treffer zeigt. Lädt der Index nicht
oder gibt es keine Treffer, sieht der Kunde die normale Plenty-Suche. Plugin aus = alter Zustand.

## Ergebnisseite (ab 0.5.0)
ShopBuilder-Widget **OSV Suchergebnisse** auf die Seite „Artikelsuchergebnisse“ ziehen, oberhalb von Plentys Artikel-Raster.
Es zeigt dieselben Treffer und dieselbe Reihenfolge wie die Sofort-Liste, als Kachelraster im Ceres-Stil, 24 je Schritt
mit „Weitere Artikel anzeigen“, Preise live. Solange es Treffer zeigt, werden Plentys Raster, Toolbar, Filter und
Seitenblättern ausgeblendet. Findet es nichts oder lädt der Index nicht, bleibt Plentys Seite unverändert.

## Ab 0.6.0
- Ergebnisseite wie in der PWA: „Passende Kategorien“ als Knöpfe (höchstens 6, ohne Sale und Neu im Shop).
  Ohne gewählte Kategorie nur Preis, Hersteller, „Nur sofort lieferbar“; mit Kategorie zusätzlich deren Merkmale
  (Größe, Farbe …) mit den Werten, die es dort gibt.
- Die Vorlage im Shop ist ein fester Lader: Er fragt `/rest/osv-suche/version` und lädt das passende Skript.
  Neue Versionen kommen dadurch ohne Neuspeichern der Container-Verknüpfung an.
  **Beim Versionswechsel `IndexController::VERSION` mit hochzählen.** Das CSS steckt im Skript.
- Der Index enthält die Merkmale je Variante (`at`). Nach dem ersten Bereitstellen einmal neu aufbauen.

## Ab 0.7.0: Filter aus den Facetten (Eigenschaften)
- `POST /rest/osv-suche/facetten?token=…` speichert die Facetten-Zuordnung je Variante
  (`{"werte":{"<FacettenwertId>":["Farbe","rot",Facettenposition,Wertposition]},"v":{"<VariantenId>":[Wert-IDs]}}`).
  Erzeugt wird sie außerhalb über die Shop-Schnittstelle (je Facettenwert ein Abruf, ~7 Minuten), gedacht für einen nächtlichen Lauf.
- Beim Neuaufbau bekommt jede Variante `fa` (Wert-IDs), der Index die Werteliste `_fw`.
- Innerhalb einer Facette ODER, zwischen Facetten UND.
- Neuer Container **OSV Suche Kopf** → `Ceres::Template.Style`: blendet Plentys Ergebnisliste von Anfang an aus
  (kein Aufblitzen). Findet die Suche nichts, kommt Plentys Seite zurück.
