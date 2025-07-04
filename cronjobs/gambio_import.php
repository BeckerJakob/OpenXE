<?php

/**
 * Gambio Shop Import Cronjob
 * 
 * Importiert automatisch neue Bestellungen aus Gambio Shop
 * 
 * Verwendung:
 * php cronjobs/gambio_import.php
 * 
 * Oder als Cronjob:
 * */5 * * * * php /path/to/openxe/cronjobs/gambio_import.php
 */

// Autoloader laden
require_once dirname(__DIR__) . '/xentral_autoloader.php';

use Xentral\Modules\GambioApi\GambioApiClient;
use Xentral\Modules\GambioApi\GambioOrderProcessor;

class GambioImportCronjob
{
    private $app;
    private $config;
    private $logger;
    
    public function __construct()
    {
        // OpenXE App laden
        $this->app = new Application();
        
        // Konfiguration laden
        $this->loadConfig();
        
        // Logger initialisieren
        $this->logger = $this->app->Logger;
    }
    
    /**
     * Konfiguration laden
     */
    private function loadConfig()
    {
        // Konfiguration aus Datenbank oder Datei laden
        $this->config = [
            'base_url' => $this->app->DB->Select("SELECT wert FROM konfiguration WHERE name = 'gambio_api_url'"),
            'username' => $this->app->DB->Select("SELECT wert FROM konfiguration WHERE name = 'gambio_api_username'"),
            'password' => $this->app->DB->Select("SELECT wert FROM konfiguration WHERE name = 'gambio_api_password'"),
            'default_project' => (int)$this->app->DB->Select("SELECT wert FROM konfiguration WHERE name = 'gambio_default_project'") ?: 1,
            'default_bearbeiter' => $this->app->DB->Select("SELECT wert FROM konfiguration WHERE name = 'gambio_default_bearbeiter'") ?: '',
            'belegnr_prefix' => $this->app->DB->Select("SELECT wert FROM konfiguration WHERE name = 'gambio_belegnr_prefix'") ?: 'GAMBIO',
            'import_status' => $this->app->DB->Select("SELECT wert FROM konfiguration WHERE name = 'gambio_import_status'") ?: 'pending',
            'import_limit' => (int)$this->app->DB->Select("SELECT wert FROM konfiguration WHERE name = 'gambio_import_limit'") ?: 50,
            'last_import_date' => $this->app->DB->Select("SELECT wert FROM konfiguration WHERE name = 'gambio_last_import_date'") ?: date('Y-m-d', strtotime('-1 day'))
        ];
        
        // Prüfen ob Konfiguration vollständig ist
        if (empty($this->config['base_url']) || empty($this->config['username']) || empty($this->config['password'])) {
            throw new RuntimeException('Gambio API Konfiguration unvollständig. Bitte konfigurieren Sie die API-Zugangsdaten.');
        }
    }
    
    /**
     * Import durchführen
     */
    public function run()
    {
        $this->logger->info('Gambio Import gestartet');
        
        try {
            // API Client erstellen
            $apiClient = new GambioApiClient(
                $this->app->DB,
                $this->logger,
                $this->config
            );
            
            // Order Processor erstellen
            $processor = new GambioOrderProcessor(
                $this->app->DB,
                $this->logger,
                $apiClient,
                $this->config
            );
            
            // Filter für neue Bestellungen
            $filters = [
                'status' => $this->config['import_status'],
                'limit' => $this->config['import_limit'],
                'date_from' => $this->config['last_import_date']
            ];
            
            // Import durchführen
            $result = $processor->importOrders($filters);
            
            // Ergebnis loggen
            $this->logger->info('Gambio Import abgeschlossen', $result);
            
            // Letzten Import-Zeitpunkt aktualisieren
            $this->app->DB->Update(
                "UPDATE konfiguration SET wert = :date WHERE name = 'gambio_last_import_date'",
                ['date' => date('Y-m-d')]
            );
            
            // Erfolg ausgeben
            echo "Gambio Import erfolgreich abgeschlossen:\n";
            echo "- Gesamt: {$result['total']}\n";
            echo "- Importiert: {$result['imported']}\n";
            echo "- Übersprungen: {$result['skipped']}\n";
            echo "- Fehler: " . count($result['errors']) . "\n";
            
            if (!empty($result['errors'])) {
                echo "\nFehler:\n";
                foreach ($result['errors'] as $error) {
                    echo "- {$error['error']}\n";
                }
            }
            
        } catch (Exception $e) {
            $this->logger->error('Gambio Import fehlgeschlagen', ['error' => $e->getMessage()]);
            echo "Fehler beim Gambio Import: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    /**
     * Konfiguration testen
     */
    public function test()
    {
        echo "Teste Gambio API Verbindung...\n";
        
        try {
            // API Client erstellen
            $apiClient = new GambioApiClient(
                $this->app->DB,
                $this->logger,
                $this->config
            );
            
            // Verbindung testen
            $connected = $apiClient->testConnection();
            
            if ($connected) {
                echo "✓ Verbindung erfolgreich\n";
                
                // Test: Bestellungen abrufen
                $orders = $apiClient->getOrders(['limit' => 1]);
                echo "✓ Bestellungen können abgerufen werden (" . count($orders) . " gefunden)\n";
                
            } else {
                echo "✗ Verbindung fehlgeschlagen\n";
                exit(1);
            }
            
        } catch (Exception $e) {
            echo "✗ Test fehlgeschlagen: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    /**
     * Konfiguration anzeigen
     */
    public function showConfig()
    {
        echo "Gambio API Konfiguration:\n";
        echo "- Base URL: {$this->config['base_url']}\n";
        echo "- Username: {$this->config['username']}\n";
        echo "- Password: " . str_repeat('*', strlen($this->config['password'])) . "\n";
        echo "- Default Project: {$this->config['default_project']}\n";
        echo "- Default Bearbeiter: {$this->config['default_bearbeiter']}\n";
        echo "- Belegnummer Prefix: {$this->config['belegnr_prefix']}\n";
        echo "- Import Status: {$this->config['import_status']}\n";
        echo "- Import Limit: {$this->config['import_limit']}\n";
        echo "- Last Import Date: {$this->config['last_import_date']}\n";
    }
}

// CLI Handler
if (php_sapi_name() === 'cli') {
    $cronjob = new GambioImportCronjob();
    
    $command = $argv[1] ?? 'run';
    
    switch ($command) {
        case 'test':
            $cronjob->test();
            break;
            
        case 'config':
            $cronjob->showConfig();
            break;
            
        case 'run':
        default:
            $cronjob->run();
            break;
    }
} 