# OSV Suche

Stufe 1 (0.1.x): Stellt unter `/rest/osv-suche/index` einen Suchindex aller im Shop sichtbaren Varianten als JSON bereit
(Name, Variante, Merkmale, Nummer, Hersteller, Standardkategorie, Link, Bild, Preis, Verfügbarkeit).
Der Index wird 60 Minuten zwischengespeichert. `?refresh=1` baut ihn neu.

Im Shop wird noch nichts angezeigt. Die Sofort-Suche folgt in Stufe 2.
