<?php

/**
 * Gambio Shop Setup Script
 * 
 * Konfiguriert die Gambio API Integration
 */

// Autoloader laden
require_once dirname(__DIR__) . '/xentral_autoloader.php';

class GambioSetup
{
    private $app;
    
    public function __construct()
    {
        $this->app = new Application();
    }
    
    /**
     * Konfiguration einrichten
     */
    public function setup()
    {
        echo "=== Gambio Shop API Setup ===\n\n";
        
        // API URL
        $baseUrl = $this->prompt("Gambio API Base URL (z.B. https://shop.example.com/api/v1): ");
        if (empty($baseUrl)) {
            echo "Base URL ist erforderlich!\n";
            return false;
        }
        
        // Username
        $username = $this->prompt("API Username: ");
        if (empty($username)) {
            echo "Username ist erforderlich!\n";
            return false;
        }
        
        // Password
        $password = $this->prompt("API Password: ");
        if (empty($password)) {
            echo "Password ist erforderlich!\n";
            return false;
        }
        
        // Default Project
        $defaultProject = $this->prompt("Default Project ID (Standard: 1): ", "1");
        
        // Default Bearbeiter
        $defaultBearbeiter = $this->prompt("Default Bearbeiter (optional): ");
        
        // Belegnummer Prefix
        $belegnrPrefix = $this->prompt("Belegnummer Prefix (Standard: GAMBIO): ", "GAMBIO");
        
        // Import Status
        $importStatus = $this->prompt("Import Status (Standard: pending): ", "pending");
        
        // Import Limit
        $importLimit = $this->prompt("Import Limit (Standard: 50): ", "50");
        
        // Konfiguration speichern
        $this->saveConfig([
            'gambio_api_url' => $baseUrl,
            'gambio_api_username' => $username,
            'gambio_api_password' => $password,
            'gambio_default_project' => $defaultProject,
            'gambio_default_bearbeiter' => $defaultBearbeiter,
            'gambio_belegnr_prefix' => $belegnrPrefix,
            'gambio_import_status' => $importStatus,
            'gambio_import_limit' => $importLimit,
            'gambio_last_import_date' => date('Y-m-d', strtotime('-1 day'))
        ]);
        
        echo "\n✓ Konfiguration erfolgreich gespeichert!\n\n";
        
        // Test durchführen
        $this->testConnection($baseUrl, $username, $password);
        
        return true;
    }
    
    /**
     * Konfiguration speichern
     */
    private function saveConfig(array $config)
    {
        foreach ($config as $name => $value) {
            // Prüfen ob Konfiguration bereits existiert
            $exists = $this->app->DB->Select("SELECT COUNT(*) FROM konfiguration WHERE name = :name", ['name' => $name]);
            
            if ($exists > 0) {
                // Update
                $this->app->DB->Update(
                    "UPDATE konfiguration SET wert = :wert WHERE name = :name",
                    ['wert' => $value, 'name' => $name]
                );
            } else {
                // Insert
                $this->app->DB->Insert(
                    "INSERT INTO konfiguration (name, wert) VALUES (:name, :wert)",
                    ['name' => $name, 'wert' => $value]
                );
            }
        }
    }
    
    /**
     * Verbindung testen
     */
    private function testConnection($baseUrl, $username, $password)
    {
        echo "Teste API Verbindung...\n";
        
        $config = [
            'base_url' => $baseUrl,
            'username' => $username,
            'password' => $password
        ];
        
        try {
            $apiClient = new \Xentral\Modules\GambioApi\GambioApiClient(
                $this->app->DB,
                $this->app->Logger,
                $config
            );
            
            $connected = $apiClient->testConnection();
            
            if ($connected) {
                echo "✓ API Verbindung erfolgreich!\n";
                
                // Test: Bestellungen abrufen
                $orders = $apiClient->getOrders(['limit' => 1]);
                echo "✓ Bestellungen können abgerufen werden (" . count($orders) . " gefunden)\n";
                
            } else {
                echo "✗ API Verbindung fehlgeschlagen!\n";
                echo "Bitte überprüfen Sie die Zugangsdaten.\n";
            }
            
        } catch (Exception $e) {
            echo "✗ Test fehlgeschlagen: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Konfiguration anzeigen
     */
    public function showConfig()
    {
        echo "=== Aktuelle Gambio Konfiguration ===\n\n";
        
        $configs = [
            'gambio_api_url' => 'API URL',
            'gambio_api_username' => 'API Username',
            'gambio_api_password' => 'API Password',
            'gambio_default_project' => 'Default Project',
            'gambio_default_bearbeiter' => 'Default Bearbeiter',
            'gambio_belegnr_prefix' => 'Belegnummer Prefix',
            'gambio_import_status' => 'Import Status',
            'gambio_import_limit' => 'Import Limit',
            'gambio_last_import_date' => 'Last Import Date'
        ];
        
        foreach ($configs as $name => $label) {
            $value = $this->app->DB->Select("SELECT wert FROM konfiguration WHERE name = :name", ['name' => $name]);
            
            if ($name === 'gambio_api_password' && !empty($value)) {
                $value = str_repeat('*', strlen($value));
            }
            
            echo sprintf("%-20s: %s\n", $label, $value ?: 'nicht gesetzt');
        }
    }
    
    /**
     * Konfiguration löschen
     */
    public function clearConfig()
    {
        echo "Lösche Gambio Konfiguration...\n";
        
        $configs = [
            'gambio_api_url',
            'gambio_api_username',
            'gambio_api_password',
            'gambio_default_project',
            'gambio_default_bearbeiter',
            'gambio_belegnr_prefix',
            'gambio_import_status',
            'gambio_import_limit',
            'gambio_last_import_date'
        ];
        
        foreach ($configs as $name) {
            $this->app->DB->Delete("DELETE FROM konfiguration WHERE name = :name", ['name' => $name]);
        }
        
        echo "✓ Konfiguration gelöscht!\n";
    }
    
    /**
     * Benutzer-Eingabe
     */
    private function prompt($message, $default = '')
    {
        echo $message;
        $input = trim(fgets(STDIN));
        
        if (empty($input) && !empty($default)) {
            return $default;
        }
        
        return $input;
    }
    
    /**
     * Hilfe anzeigen
     */
    public function showHelp()
    {
        echo "Gambio Shop Setup Script\n\n";
        echo "Verwendung:\n";
        echo "  php tools/gambio_setup.php setup     - Konfiguration einrichten\n";
        echo "  php tools/gambio_setup.php show      - Konfiguration anzeigen\n";
        echo "  php tools/gambio_setup.php clear     - Konfiguration löschen\n";
        echo "  php tools/gambio_setup.php help      - Diese Hilfe anzeigen\n\n";
    }
}

// CLI Handler
if (php_sapi_name() === 'cli') {
    $setup = new GambioSetup();
    
    $command = $argv[1] ?? 'help';
    
    switch ($command) {
        case 'setup':
            $setup->setup();
            break;
            
        case 'show':
            $setup->showConfig();
            break;
            
        case 'clear':
            $setup->clearConfig();
            break;
            
        case 'help':
        default:
            $setup->showHelp();
            break;
    }
} 