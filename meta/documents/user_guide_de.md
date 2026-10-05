# OSV Suche

Stufe 1 (0.2.x): Suchindex aller im Shop sichtbaren Varianten.

- `/rest/osv-suche/index` liefert den gespeicherten Index als JSON (schnell, baut nie selbst).
- `/rest/osv-suche/rebuild?token=…` baut den Index neu (dauert ~45 s) und speichert ihn im Plugin-Speicher.
  Der Schlüssel steht in der Plugin-Konfiguration unter „Suchindex“. Leer = Neuaufbau gesperrt.

Warum kein Zwischenspeicher: Plentys CachingRepository erlaubt für Plugins höchstens 512 Byte je Wert (gemessen 05.10.2026).

Im Shop wird noch nichts angezeigt. Die Sofort-Suche folgt in Stufe 2.
