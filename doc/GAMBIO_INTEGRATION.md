# Gambio Shop Integration für OpenXE

Diese Integration ermöglicht es, Bestellungen aus einem Gambio GX3 Shop automatisch in OpenXE Aufträge zu importieren.

## Übersicht

Die Integration besteht aus folgenden Komponenten:

- **GambioApiClient**: API-Client für die Gambio GX3 REST API
- **GambioOrderProcessor**: Konvertiert Gambio Bestellungen in OpenXE Aufträge
- **API Endpoints**: REST API für manuelle Imports und Tests
- **Cronjob**: Automatischer Import neuer Bestellungen
- **Setup Script**: Konfiguration der Integration

## Installation

### 1. Dateien kopieren

Die folgenden Dateien müssen in das OpenXE-System kopiert werden:

```
classes/Modules/GambioApi/GambioApiClient.php
classes/Modules/GambioApi/GambioOrderProcessor.php
classes/Modules/Api/Controller/Version1/GambioController.php
cronjobs/gambio_import.php
tools/gambio_setup.php
```

### 2. Konfiguration einrichten

Führen Sie das Setup-Script aus:

```bash
php tools/gambio_setup.php setup
```

Das Script führt Sie durch die Konfiguration:

- **API URL**: Base URL der Gambio API (z.B. `https://shop.example.com/api/v1`)
- **Username**: API Benutzername
- **Password**: API Passwort
- **Default Project**: Standard-Projekt für importierte Aufträge
- **Default Bearbeiter**: Standard-Bearbeiter für importierte Aufträge
- **Belegnummer Prefix**: Prefix für generierte Belegnummern
- **Import Status**: Status der zu importierenden Bestellungen
- **Import Limit**: Maximale Anzahl Bestellungen pro Import

### 3. API-Account konfigurieren

In OpenXE unter *Administration > Einstellungen > API-Account*:

1. Neuen API-Account erstellen
2. Berechtigungen `list_orders` und `create_orders` aktivieren
3. API-Account aktivieren

## Verwendung

### Automatischer Import (Cronjob)

Für den automatischen Import richten Sie einen Cronjob ein:

```bash
# Alle 5 Minuten neue Bestellungen importieren
*/5 * * * * php /path/to/openxe/cronjobs/gambio_import.php

# Oder manuell ausführen
php cronjobs/gambio_import.php
```

#### Cronjob Befehle

```bash
# Import durchführen
php cronjobs/gambio_import.php run

# Verbindung testen
php cronjobs/gambio_import.php test

# Konfiguration anzeigen
php cronjobs/gambio_import.php config
```

### Manueller Import über API

#### Bestellungen importieren

```bash
curl -X POST https://your-openxe.com/api/v1/gambio/import \
  -H "Content-Type: application/json" \
  -H "Authorization: Digest ..." \
  -d '{
    "config": {
      "base_url": "https://shop.example.com/api/v1",
      "username": "api_user",
      "password": "api_password",
      "default_project": 1,
      "default_bearbeiter": "Admin",
      "belegnr_prefix": "GAMBIO"
    },
    "filters": {
      "status": "pending",
      "limit": 50,
      "date_from": "2024-01-01"
    }
  }'
```

#### Einzelne Bestellung importieren

```bash
curl -X POST https://your-openxe.com/api/v1/gambio/import-order \
  -H "Content-Type: application/json" \
  -H "Authorization: Digest ..." \
  -d '{
    "config": {
      "base_url": "https://shop.example.com/api/v1",
      "username": "api_user",
      "password": "api_password",
      "default_project": 1,
      "default_bearbeiter": "Admin",
      "belegnr_prefix": "GAMBIO"
    },
    "order_id": 12345
  }'
```

#### API-Verbindung testen

```bash
curl -X POST https://your-openxe.com/api/v1/gambio/test \
  -H "Content-Type: application/json" \
  -H "Authorization: Digest ..." \
  -d '{
    "config": {
      "base_url": "https://shop.example.com/api/v1",
      "username": "api_user",
      "password": "api_password"
    }
  }'
```

#### Bestellungen abrufen (ohne Import)

```bash
curl -X POST https://your-openxe.com/api/v1/gambio/orders \
  -H "Content-Type: application/json" \
  -H "Authorization: Digest ..." \
  -d '{
    "config": {
      "base_url": "https://shop.example.com/api/v1",
      "username": "api_user",
      "password": "api_password"
    },
    "filters": {
      "status": "pending",
      "limit": 10
    }
  }'
```

## Datenmapping

### Gambio → OpenXE Auftrag

| Gambio Feld | OpenXE Feld | Beschreibung |
|-------------|-------------|--------------|
| `id` | `internet` | Externe Bestellnummer |
| `customer.email` | `email` | Kunden-E-Mail |
| `customer.customer_number` | `kundennummer` | Kundennummer |
| `customer.vat_number` | `ustid` | USt-ID |
| `billing_address.firstname + lastname` | `name` | Kundenname |
| `billing_address.street` | `strasse` | Straße |
| `billing_address.postcode` | `plz` | PLZ |
| `billing_address.city` | `ort` | Ort |
| `billing_address.country_code` | `land` | Land |
| `total` | `gesamtsumme` | Gesamtsumme |
| `total_net` | `gesamtsumme_netto` | Nettosumme |
| `tax` | `umsatzsteuer` | Umsatzsteuer |
| `currency` | `waehrung` | Währung |
| `shipping_method` | `versandart` | Versandart (gemappt) |
| `payment_method` | `zahlungsweise` | Zahlungsart (gemappt) |
| `comment` | `freitext` | Bestellkommentar |

### Versandarten Mapping

| Gambio | OpenXE |
|--------|--------|
| `dhl` | `DHL` |
| `dpd` | `DPD` |
| `hermes` | `Hermes` |
| `gls` | `GLS` |
| `ups` | `UPS` |
| `fedex` | `FedEx` |

### Zahlungsarten Mapping

| Gambio | OpenXE |
|--------|--------|
| `bank_transfer` | `Überweisung` |
| `cash_on_delivery` | `Nachnahme` |
| `credit_card` | `Kreditkarte` |
| `paypal` | `PayPal` |
| `klarna` | `Klarna` |
| `sofort` | `Sofortüberweisung` |

## Konfiguration

### Datenbank-Konfiguration

Die Konfiguration wird in der `konfiguration` Tabelle gespeichert:

| Name | Beschreibung | Standard |
|------|--------------|----------|
| `gambio_api_url` | API Base URL | - |
| `gambio_api_username` | API Username | - |
| `gambio_api_password` | API Password | - |
| `gambio_default_project` | Standard-Projekt | 1 |
| `gambio_default_bearbeiter` | Standard-Bearbeiter | - |
| `gambio_belegnr_prefix` | Belegnummer Prefix | GAMBIO |
| `gambio_import_status` | Import Status | pending |
| `gambio_import_limit` | Import Limit | 50 |
| `gambio_last_import_date` | Letzter Import | gestern |

### Konfiguration verwalten

```bash
# Konfiguration anzeigen
php tools/gambio_setup.php show

# Konfiguration löschen
php tools/gambio_setup.php clear

# Neue Konfiguration einrichten
php tools/gambio_setup.php setup
```

## Fehlerbehebung

### Häufige Probleme

#### 1. API-Verbindung fehlgeschlagen

**Symptom**: `API request failed with HTTP code: 401`

**Lösung**:
- API-Zugangsdaten überprüfen
- Base URL auf Korrektheit prüfen
- API-Account in Gambio aktiviert?

#### 2. Bestellungen werden nicht importiert

**Symptom**: `total: 0, imported: 0`

**Lösung**:
- Import-Status in Konfiguration prüfen
- Datum-Filter überprüfen
- Bestellungen im gewählten Status vorhanden?

#### 3. Duplikate werden erstellt

**Symptom**: Gleiche Bestellung wird mehrfach importiert

**Lösung**:
- Bestellungen werden über `internet` Feld dedupliziert
- Prüfen ob `internet` Feld korrekt gesetzt ist

#### 4. Artikel werden nicht gefunden

**Symptom**: `Could not find or create address for order`

**Lösung**:
- Artikelnummern in Gambio und OpenXE vergleichen
- Automatische Artikel-Erstellung funktioniert

### Logs

Logs werden in das OpenXE-Log-System geschrieben:

- **Info**: Erfolgreiche Imports, Verbindungstests
- **Error**: API-Fehler, Import-Fehler, Validierungsfehler

### Debug-Modus

Für detaillierte Debug-Informationen:

```php
// In der Konfiguration
define('GAMBIO_DEBUG', true);
```

## Erweiterungen

### Custom Mapping

Für eigene Mapping-Logik können Sie die Klassen erweitern:

```php
class CustomGambioOrderProcessor extends GambioOrderProcessor
{
    protected function mapShippingMethod(string $gambioShippingMethod): string
    {
        // Eigene Mapping-Logik
        $customMapping = [
            'custom_shipping' => 'Eigene Versandart'
        ];
        
        $key = strtolower($gambioShippingMethod);
        return $customMapping[$key] ?? parent::mapShippingMethod($gambioShippingMethod);
    }
}
```

### Webhook Integration

Für Echtzeit-Imports können Sie einen Webhook einrichten:

```php
// Webhook-Endpoint
public function webhookAction()
{
    $orderData = $this->getRequestData();
    
    $processor = new GambioOrderProcessor($this->db, $this->logger, $this->apiClient, $this->config);
    $processor->importSingleOrder($orderData);
    
    return $this->sendResult(['status' => 'ok']);
}
```

## Support

Bei Problemen oder Fragen:

1. Logs überprüfen
2. API-Verbindung testen: `php cronjobs/gambio_import.php test`
3. Konfiguration prüfen: `php tools/gambio_setup.php show`
4. Dokumentation der Gambio GX3 API konsultieren

## Changelog

### Version 1.0.0
- Initiale Version
- Gambio GX3 REST API Integration
- Automatischer Import via Cronjob
- REST API Endpoints
- Setup-Script
- Umfassende Dokumentation 