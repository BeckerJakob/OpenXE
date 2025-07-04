<?php

namespace Xentral\Modules\GambioApi;

use Xentral\Components\Database\Database;
use Xentral\Components\Logger\Logger;

/**
 * Gambio Order Processor
 * 
 * Konvertiert Gambio Bestellungen in OpenXE Aufträge
 */
class GambioOrderProcessor
{
    /** @var Database */
    private $db;
    
    /** @var Logger */
    private $logger;
    
    /** @var GambioApiClient */
    private $apiClient;
    
    /** @var array */
    private $config;
    
    public function __construct(Database $db, Logger $logger, GambioApiClient $apiClient, array $config)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->apiClient = $apiClient;
        $this->config = $config;
    }
    
    /**
     * Bestellungen von Gambio importieren
     * 
     * @param array $filters
     * @return array
     */
    public function importOrders(array $filters = []): array
    {
        $result = [
            'total' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => []
        ];
        
        try {
            $orders = $this->apiClient->getOrders($filters);
            $result['total'] = count($orders);
            
            foreach ($orders as $order) {
                try {
                    $imported = $this->importSingleOrder($order);
                    if ($imported) {
                        $result['imported']++;
                    } else {
                        $result['skipped']++;
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = [
                        'order_id' => $order['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    $this->logger->error('Gambio import error', [
                        'order' => $order,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } catch (\Exception $e) {
            $result['errors'][] = [
                'error' => 'API request failed: ' . $e->getMessage()
            ];
            $this->logger->error('Gambio API request failed', ['error' => $e->getMessage()]);
        }
        
        return $result;
    }
    
    /**
     * Einzelne Bestellung importieren
     * 
     * @param array $gambioOrder
     * @return bool
     */
    public function importSingleOrder(array $gambioOrder): bool
    {
        // Prüfen ob Bestellung bereits importiert wurde
        $existingOrder = $this->db->fetchRow(
            'SELECT id FROM auftrag WHERE internet = :external_id',
            ['external_id' => (string)$gambioOrder['id']]
        );
        
        if ($existingOrder) {
            $this->logger->info('Gambio order already imported', [
                'gambio_id' => $gambioOrder['id'],
                'auftrag_id' => $existingOrder['id']
            ]);
            return false;
        }
        
        // Kunde/Adresse finden oder erstellen
        $addressId = $this->findOrCreateAddress($gambioOrder);
        if (!$addressId) {
            throw new \RuntimeException('Could not find or create address for order ' . $gambioOrder['id']);
        }
        
        // Auftrag erstellen
        $auftragData = $this->convertGambioOrderToAuftrag($gambioOrder, $addressId);
        $auftragId = $this->createAuftrag($auftragData);
        
        if (!$auftragId) {
            throw new \RuntimeException('Failed to create auftrag for order ' . $gambioOrder['id']);
        }
        
        // Auftragspositionen erstellen
        $this->createAuftragPositions($auftragId, $gambioOrder);
        
        $this->logger->info('Gambio order imported successfully', [
            'gambio_id' => $gambioOrder['id'],
            'auftrag_id' => $auftragId
        ]);
        
        return true;
    }
    
    /**
     * Gambio Bestellung in OpenXE Auftrag konvertieren
     * 
     * @param array $gambioOrder
     * @param int $addressId
     * @return array
     */
    private function convertGambioOrderToAuftrag(array $gambioOrder, int $addressId): array
    {
        $customer = $gambioOrder['customer'] ?? [];
        $billingAddress = $gambioOrder['billing_address'] ?? [];
        $shippingAddress = $gambioOrder['shipping_address'] ?? [];
        
        $auftragData = [
            'datum' => date('Y-m-d'),
            'art' => 'firma',
            'projekt' => $this->config['default_project'] ?? 1,
            'belegnr' => '', // Wird automatisch generiert
            'internet' => (string)$gambioOrder['id'], // Externe Bestellnummer
            'bearbeiter' => $this->config['default_bearbeiter'] ?? '',
            'angebot' => '',
            'freitext' => $gambioOrder['comment'] ?? '',
            'internebemerkung' => 'Importiert von Gambio Shop',
            'status' => 'angelegt',
            'adresse' => $addressId,
            'name' => $billingAddress['firstname'] . ' ' . $billingAddress['lastname'],
            'abteilung' => '',
            'unterabteilung' => '',
            'strasse' => $billingAddress['street'] ?? '',
            'adresszusatz' => $billingAddress['house_number'] ?? '',
            'ansprechpartner' => $billingAddress['firstname'] . ' ' . $billingAddress['lastname'],
            'plz' => $billingAddress['postcode'] ?? '',
            'ort' => $billingAddress['city'] ?? '',
            'land' => $billingAddress['country_code'] ?? 'DE',
            'ustid' => $customer['vat_number'] ?? '',
            'ust_befreit' => 0,
            'ust_inner' => 0,
            'email' => $customer['email'] ?? '',
            'telefon' => $customer['telephone'] ?? '',
            'telefax' => '',
            'betreff' => 'Bestellung ' . $gambioOrder['id'],
            'kundennummer' => $customer['customer_number'] ?? '',
            'versandart' => $this->mapShippingMethod($gambioOrder['shipping_method'] ?? ''),
            'vertrieb' => 'Gambio Import',
            'zahlungsweise' => $this->mapPaymentMethod($gambioOrder['payment_method'] ?? ''),
            'zahlungszieltage' => 0,
            'zahlungszieltageskonto' => 0,
            'zahlungszielskonto' => 0.00,
            'bank_inhaber' => '',
            'bank_institut' => '',
            'bank_blz' => '',
            'bank_konto' => '',
            'kreditkarte_typ' => '',
            'kreditkarte_inhaber' => '',
            'kreditkarte_nummer' => '',
            'kreditkarte_pruefnummer' => '',
            'kreditkarte_monat' => '',
            'kreditkarte_jahr' => '',
            'firma' => 1,
            'versendet' => 0,
            'versendet_am' => '0000-00-00 00:00:00',
            'versendet_per' => '',
            'versendet_durch' => '',
            'autoversand' => 0,
            'gesamtsumme' => (float)($gambioOrder['total'] ?? 0),
            'gesamtsumme_netto' => (float)($gambioOrder['total_net'] ?? 0),
            'umsatzsteuer' => (float)($gambioOrder['tax'] ?? 0),
            'waehrung' => $gambioOrder['currency'] ?? 'EUR',
            'sprache' => $gambioOrder['language'] ?? 'de',
            'angelegtam' => date('Y-m-d H:i:s'),
            'shop' => 1, // Markierung als Shop-Import
        ];
        
        // Abweichende Lieferadresse
        if (!empty($shippingAddress) && $shippingAddress !== $billingAddress) {
            $auftragData['abweichendelieferadresse'] = 1;
            $auftragData['liefername'] = $shippingAddress['firstname'] . ' ' . $shippingAddress['lastname'];
            $auftragData['lieferabteilung'] = '';
            $auftragData['lieferunterabteilung'] = '';
            $auftragData['lieferstrasse'] = $shippingAddress['street'] ?? '';
            $auftragData['lieferadresszusatz'] = $shippingAddress['house_number'] ?? '';
            $auftragData['lieferansprechpartner'] = $shippingAddress['firstname'] . ' ' . $shippingAddress['lastname'];
            $auftragData['lieferplz'] = $shippingAddress['postcode'] ?? '';
            $auftragData['lieferort'] = $shippingAddress['city'] ?? '';
            $auftragData['lieferland'] = $shippingAddress['country_code'] ?? 'DE';
        }
        
        return $auftragData;
    }
    
    /**
     * Kunde/Adresse finden oder erstellen
     * 
     * @param array $gambioOrder
     * @return int|null
     */
    private function findOrCreateAddress(array $gambioOrder): ?int
    {
        $customer = $gambioOrder['customer'] ?? [];
        $billingAddress = $gambioOrder['billing_address'] ?? [];
        
        // Zuerst nach E-Mail suchen
        if (!empty($customer['email'])) {
            $addressId = $this->db->fetchValue(
                'SELECT id FROM adresse WHERE email = :email AND IFNULL(geloescht,0) = 0 LIMIT 1',
                ['email' => $customer['email']]
            );
            
            if ($addressId) {
                return (int)$addressId;
            }
        }
        
        // Nach Kundennummer suchen
        if (!empty($customer['customer_number'])) {
            $addressId = $this->db->fetchValue(
                'SELECT id FROM adresse WHERE kundennummer = :kundennummer AND IFNULL(geloescht,0) = 0 LIMIT 1',
                ['kundennummer' => $customer['customer_number']]
            );
            
            if ($addressId) {
                return (int)$addressId;
            }
        }
        
        // Neue Adresse erstellen
        $addressData = [
            'kundennummer' => $customer['customer_number'] ?? '',
            'name' => $billingAddress['firstname'] . ' ' . $billingAddress['lastname'],
            'abteilung' => '',
            'unterabteilung' => '',
            'strasse' => $billingAddress['street'] ?? '',
            'adresszusatz' => $billingAddress['house_number'] ?? '',
            'ansprechpartner' => $billingAddress['firstname'] . ' ' . $billingAddress['lastname'],
            'plz' => $billingAddress['postcode'] ?? '',
            'ort' => $billingAddress['city'] ?? '',
            'land' => $billingAddress['country_code'] ?? 'DE',
            'ustid' => $customer['vat_number'] ?? '',
            'email' => $customer['email'] ?? '',
            'telefon' => $customer['telephone'] ?? '',
            'telefax' => '',
            'typ' => 'firma',
            'angelegtam' => date('Y-m-d H:i:s'),
            'geloescht' => 0
        ];
        
        $addressId = $this->db->insert('adresse', $addressData);
        
        return $addressId ? (int)$addressId : null;
    }
    
    /**
     * Auftrag in Datenbank erstellen
     * 
     * @param array $auftragData
     * @return int|null
     */
    private function createAuftrag(array $auftragData): ?int
    {
        $auftragId = $this->db->insert('auftrag', $auftragData);
        
        if ($auftragId) {
            // Belegnummer generieren
            $belegnr = $this->generateBelegnummer($auftragId, $auftragData['projekt']);
            $this->db->update('auftrag', ['belegnr' => $belegnr], ['id' => $auftragId]);
        }
        
        return $auftragId ? (int)$auftragId : null;
    }
    
    /**
     * Auftragspositionen erstellen
     * 
     * @param int $auftragId
     * @param array $gambioOrder
     */
    private function createAuftragPositions(int $auftragId, array $gambioOrder): void
    {
        $positions = $gambioOrder['items'] ?? [];
        $sort = 1;
        
        foreach ($positions as $position) {
            // Artikel finden oder erstellen
            $artikelId = $this->findOrCreateArtikel($position);
            
            $positionData = [
                'auftrag' => $auftragId,
                'artikel' => $artikelId,
                'projekt' => $gambioOrder['project'] ?? 1,
                'bezeichnung' => $position['name'] ?? '',
                'beschreibung' => $position['description'] ?? '',
                'internerkommentar' => '',
                'nummer' => $position['product_number'] ?? '',
                'menge' => (float)($position['quantity'] ?? 1),
                'preis' => (float)($position['price'] ?? 0),
                'waehrung' => $gambioOrder['currency'] ?? 'EUR',
                'lieferdatum' => date('Y-m-d'),
                'vpe' => '',
                'sort' => $sort++,
                'status' => 'angelegt',
                'umsatzsteuer' => (float)($position['tax'] ?? 0),
                'bemerkung' => '',
                'geliefert' => 0,
                'geliefert_menge' => 0,
                'explodiert' => 0,
                'explodiert_parent' => 0,
                'punkte' => 0,
                'bonuspunkte' => 0,
                'einheit' => 'Stück',
                'rabatt' => (float)($position['discount'] ?? 0),
                'zolltarifnummer' => '0',
                'herkunftsland' => '0',
                'artikelnummerkunde' => ''
            ];
            
            $this->db->insert('auftrag_position', $positionData);
        }
    }
    
    /**
     * Artikel finden oder erstellen
     * 
     * @param array $position
     * @return int
     */
    private function findOrCreateArtikel(array $position): int
    {
        // Zuerst nach Artikelnummer suchen
        if (!empty($position['product_number'])) {
            $artikelId = $this->db->fetchValue(
                'SELECT id FROM artikel WHERE nummer = :nummer AND IFNULL(geloescht,0) = 0 LIMIT 1',
                ['nummer' => $position['product_number']]
            );
            
            if ($artikelId) {
                return (int)$artikelId;
            }
        }
        
        // Neuen Artikel erstellen
        $artikelData = [
            'nummer' => $position['product_number'] ?? 'GAMBIO-' . uniqid(),
            'bezeichnung' => $position['name'] ?? '',
            'beschreibung' => $position['description'] ?? '',
            'preis' => (float)($position['price'] ?? 0),
            'waehrung' => 'EUR',
            'einheit' => 'Stück',
            'typ' => 'artikel',
            'angelegtam' => date('Y-m-d H:i:s'),
            'geloescht' => 0
        ];
        
        $artikelId = $this->db->insert('artikel', $artikelData);
        
        return $artikelId ? (int)$artikelId : 1; // Fallback auf Artikel ID 1
    }
    
    /**
     * Belegnummer generieren
     * 
     * @param int $auftragId
     * @param int $projektId
     * @return string
     */
    private function generateBelegnummer(int $auftragId, int $projektId): string
    {
        $prefix = $this->config['belegnr_prefix'] ?? 'GAMBIO';
        $year = date('Y');
        $nextNumber = $this->db->fetchValue(
            'SELECT COUNT(*) + 1 FROM auftrag WHERE projekt = :projekt AND YEAR(angelegtam) = :year',
            ['projekt' => $projektId, 'year' => $year]
        );
        
        return sprintf('%s-%s-%06d', $prefix, $year, $nextNumber);
    }
    
    /**
     * Versandart mappen
     * 
     * @param string $gambioShippingMethod
     * @return string
     */
    private function mapShippingMethod(string $gambioShippingMethod): string
    {
        $mapping = [
            'dhl' => 'DHL',
            'dpd' => 'DPD',
            'hermes' => 'Hermes',
            'gls' => 'GLS',
            'ups' => 'UPS',
            'fedex' => 'FedEx'
        ];
        
        $key = strtolower($gambioShippingMethod);
        return $mapping[$key] ?? 'Standard';
    }
    
    /**
     * Zahlungsart mappen
     * 
     * @param string $gambioPaymentMethod
     * @return string
     */
    private function mapPaymentMethod(string $gambioPaymentMethod): string
    {
        $mapping = [
            'bank_transfer' => 'Überweisung',
            'cash_on_delivery' => 'Nachnahme',
            'credit_card' => 'Kreditkarte',
            'paypal' => 'PayPal',
            'klarna' => 'Klarna',
            'sofort' => 'Sofortüberweisung'
        ];
        
        $key = strtolower($gambioPaymentMethod);
        return $mapping[$key] ?? 'Überweisung';
    }
} 