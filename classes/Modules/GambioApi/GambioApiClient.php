<?php

namespace Xentral\Modules\GambioApi;

use Xentral\Components\Database\Database;
use Xentral\Components\Logger\Logger;

/**
 * Gambio GX3 REST API Client
 * 
 * Abrufen von Bestellungen aus Gambio Shop über REST API
 */
class GambioApiClient
{
    /** @var Database */
    private $db;
    
    /** @var Logger */
    private $logger;
    
    /** @var string */
    private $baseUrl;
    
    /** @var string */
    private $username;
    
    /** @var string */
    private $password;
    
    /** @var array */
    private $config;
    
    public function __construct(Database $db, Logger $logger, array $config)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $config;
        
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->username = $config['username'];
        $this->password = $config['password'];
    }
    
    /**
     * Bestellungen von Gambio abrufen
     * 
     * @param array $filters Optional filters (status, date_from, date_to, etc.)
     * @return array
     */
    public function getOrders(array $filters = []): array
    {
        $url = $this->baseUrl . '/orders';
        
        // Filter als Query-Parameter hinzufügen
        if (!empty($filters)) {
            $url .= '?' . http_build_query($filters);
        }
        
        $response = $this->makeRequest('GET', $url);
        
        if (!isset($response['orders'])) {
            $this->logger->error('Gambio API: Invalid response format', ['response' => $response]);
            return [];
        }
        
        return $response['orders'];
    }
    
    /**
     * Einzelne Bestellung abrufen
     * 
     * @param int $orderId
     * @return array|null
     */
    public function getOrder(int $orderId): ?array
    {
        $url = $this->baseUrl . '/orders/' . $orderId;
        $response = $this->makeRequest('GET', $url);
        
        if (!isset($response['order'])) {
            $this->logger->error('Gambio API: Order not found', ['order_id' => $orderId]);
            return null;
        }
        
        return $response['order'];
    }
    
    /**
     * Bestellungen nach Status abrufen
     * 
     * @param string $status
     * @return array
     */
    public function getOrdersByStatus(string $status): array
    {
        return $this->getOrders(['status' => $status]);
    }
    
    /**
     * Bestellungen nach Datum abrufen
     * 
     * @param string $dateFrom Y-m-d format
     * @param string $dateTo Y-m-d format
     * @return array
     */
    public function getOrdersByDate(string $dateFrom, string $dateTo): array
    {
        return $this->getOrders([
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
    }
    
    /**
     * HTTP Request an Gambio API
     * 
     * @param string $method
     * @param string $url
     * @param array $data
     * @return array
     */
    private function makeRequest(string $method, string $url, array $data = []): array
    {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            $this->logger->error('Gambio API: cURL error', ['error' => $error, 'url' => $url]);
            throw new \RuntimeException('API request failed: ' . $error);
        }
        
        if ($httpCode >= 400) {
            $this->logger->error('Gambio API: HTTP error', [
                'http_code' => $httpCode,
                'response' => $response,
                'url' => $url
            ]);
            throw new \RuntimeException('API request failed with HTTP code: ' . $httpCode);
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Gambio API: JSON decode error', [
                'response' => $response,
                'error' => json_last_error_msg()
            ]);
            throw new \RuntimeException('Invalid JSON response from API');
        }
        
        return $decoded;
    }
    
    /**
     * Test der API-Verbindung
     * 
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', $this->baseUrl . '/orders?limit=1');
            return isset($response['orders']);
        } catch (\Exception $e) {
            $this->logger->error('Gambio API: Connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
} 