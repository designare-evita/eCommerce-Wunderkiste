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
Nach Aktivierung der Module erscheinen in der **Seitenleiste** des Produkt-Editors:
- **Preis auf Anfrage Box**: Checkbox zum Aktivieren + individueller Anzeigetext
- **Versandarten Box**: Checkboxen für alle Versandarten die deaktiviert werden sollen

## CSS Anpassung

Die "Preis auf Anfrage" Anzeige nutzt die CSS-Klasse `.price-on-request`.

### Beispiel CSS:
```css
.price-on-request {
    color: #e74c3c;
    font-weight: bold;
    font-size: 1.2em;
    text-transform: uppercase;
    background: #fff3cd;
    padding: 5px 10px;
    border-radius: 3px;
}
```

### Verfügbare CSS-Eigenschaften:
- `color` - Textfarbe
- `font-weight` - Schriftstärke (normal, bold, 100-900)
- `font-size` - Schriftgröße
- `font-style` - Schriftstil (normal, italic)
- `text-transform` - Textumwandlung (uppercase, lowercase, capitalize)
- `background` - Hintergrundfarbe
- `padding` - Innenabstand
- `border` - Rahmen
- `border-radius` - Abgerundete Ecken

## Anforderungen

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

## Changelog

### 1.0.0
- Initiale Version
- Preis auf Anfrage Modul
- Versandarten deaktivieren Modul
- Custom CSS Editor mit CodeMirror

## Lizenz

GPL v2 oder höher
