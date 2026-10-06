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
