# WooCommerce Product Extras

Ein WordPress/WooCommerce Plugin mit erweiterten Produktoptionen.

## Features

### 1. Preis auf Anfrage
- Versteckt den Produktpreis und zeigt stattdessen "Preis auf Anfrage" an
- Entfernt den "In den Warenkorb" Button
- Individuell pro Produkt aktivierbar
- Anpassbarer Anzeigetext pro Produkt
- **Custom CSS** für individuelle Gestaltung (Farbe, Schriftgröße, etc.)

### 2. Versandarten deaktivieren
- Bestimmte Versandarten pro Produkt deaktivieren
- Zeigt alle konfigurierten Versandzonen und -methoden an
- Wenn ein Produkt im Warenkorb ist, werden die deaktivierten Versandarten nicht angezeigt

### 3. Zubehör Tab (Cross-Selling)
- Fügt einen neuen Tab "Zubehör" auf der Produktseite hinzu
- Einfache Zuweisung von passenden Produkten über eine Suchmaske in der Produkt-Seitenleiste
- Ideal für Ersatzteile oder ergänzende Artikel

### 4. Image Resizer 800px / 1200px
- Skaliert Bilder auf maximal 800px oder 1200px Breite/Höhe
- Button in der Mediathek (Einzelansicht und Listenansicht)
- Hohe Qualität (92%) für optimale Ergebnisse
- Überschreibt das Originalbild und aktualisiert alle Metadaten

### 5. Order Recovery (Zahlungsabbruch)
- Hilft verlorene Umsätze bei abgebrochenen Zahlungen zu retten
- **Szenario A:** Sendet automatisch eine E-Mail (inkl. Zahlungslink), wenn eine Bestellung 1 Stunde lang den Status "Zahlung ausstehend" hat.
- **Szenario B:** Sendet sofort eine E-Mail, wenn eine Zahlung fehlschlägt ("Failed").
- **Szenario C:** Fügt einen Button in der Bestellübersicht hinzu, um den Zahlungslink manuell erneut per E-Mail zu senden.

### 6. Staffelpreise (Tiered Pricing)
- Ermöglicht mengenbasierte Preise (Mengenrabatte)
- Automatische Preisanpassung im Warenkorb, sobald die Menge erreicht ist
- Zeigt automatisch eine Preistabelle auf der Produktseite an
- Flexibel konfigurierbar (z.B. 1-5 Stück: 4€, ab 6 Stück: 3€)

## Installation

1. Plugin-Ordner `woo-product-extras` in `/wp-content/plugins/` hochladen
2. Plugin im WordPress Admin unter "Plugins" aktivieren
3. Zu **WooCommerce → Product Extras** gehen und die gewünschten Module aktivieren

## Konfiguration

### Globale Einstellungen
Unter **WooCommerce → Product Extras** können Sie:
- Module einzeln aktivieren/deaktivieren
- Custom CSS für "Preis auf Anfrage" definieren

### Pro Produkt Einstellungen
Nach Aktivierung der Module erscheinen im Produkt-Editor folgende Bereiche:

**In der Seitenleiste:**
- **Preis auf Anfrage Box**: Checkbox zum Aktivieren + individueller Anzeigetext
- **Versandarten Box**: Checkboxen für alle Versandarten die deaktiviert werden sollen
- **Zubehör Box**: Suchfeld zum Hinzufügen von verknüpften Produkten

**Unter dem Editor (Hauptbereich):**
- **Staffelpreise Box**: Tabelle zum Hinzufügen von Preisstaffeln (Min/Max Stückzahl und Preis)

## CSS Anpassung
Für individuelle Styles können folgende Klassen genutzt werden:

- `.price-on-request`: Der Text "Preis auf Anfrage"
- `.wpe-tiered-pricing-table`: Die Tabelle der Staffelpreise im Frontend
