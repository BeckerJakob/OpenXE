<?php

namespace Xentral\Modules\Api\Controller\Version1;

use Xentral\Components\Http\Response;
use Xentral\Modules\Api\Exception\BadRequestException;
use Xentral\Modules\Api\Exception\ValidationErrorException;
use Xentral\Modules\GambioApi\GambioApiClient;
use Xentral\Modules\GambioApi\GambioOrderProcessor;

/**
 * Controller für Gambio Shop Integration
 */
class GambioController extends AbstractController
{
    /**
     * Bestellungen von Gambio importieren
     *
     * @return Response
     */
    public function importOrdersAction()
    {
        $input = $this->getRequestData();
        $errors = [];
        
        // Konfiguration validieren
        if (empty($input['config']['base_url'])) {
            $errors[] = 'Required field "config.base_url" is empty.';
        }
        if (empty($input['config']['username'])) {
            $errors[] = 'Required field "config.username" is empty.';
        }
        if (empty($input['config']['password'])) {
            $errors[] = 'Required field "config.password" is empty.';
        }
        
        if (count($errors) > 0) {
            throw new ValidationErrorException($errors);
        }
        
        // API Client erstellen
        $apiClient = new GambioApiClient(
            $this->db,
            $this->logger,
            $input['config']
        );
        
        // Order Processor erstellen
        $processor = new GambioOrderProcessor(
            $this->db,
            $this->logger,
            $apiClient,
            $input['config']
        );
        
        // Filter für Bestellungen
        $filters = $input['filters'] ?? [];
        
        // Import durchführen
        $result = $processor->importOrders($filters);
        
        return $this->sendResult($result, Response::HTTP_OK);
    }
    
    /**
     * Einzelne Bestellung von Gambio importieren
     *
     * @return Response
     */
    public function importSingleOrderAction()
    {
        $input = $this->getRequestData();
        $errors = [];
        
        // Konfiguration validieren
        if (empty($input['config']['base_url'])) {
            $errors[] = 'Required field "config.base_url" is empty.';
        }
        if (empty($input['config']['username'])) {
            $errors[] = 'Required field "config.username" is empty.';
        }
        if (empty($input['config']['password'])) {
            $errors[] = 'Required field "config.password" is empty.';
        }
        if (empty($input['order_id'])) {
            $errors[] = 'Required field "order_id" is empty.';
        }
        
        if (count($errors) > 0) {
            throw new ValidationErrorException($errors);
        }
        
        // API Client erstellen
        $apiClient = new GambioApiClient(
            $this->db,
            $this->logger,
            $input['config']
        );
        
        // Order Processor erstellen
        $processor = new GambioOrderProcessor(
            $this->db,
            $this->logger,
            $apiClient,
            $input['config']
        );
        
        // Bestellung abrufen
        $order = $apiClient->getOrder((int)$input['order_id']);
        if (!$order) {
            throw new BadRequestException('Order not found in Gambio');
        }
        
        // Import durchführen
        $imported = $processor->importSingleOrder($order);
        
        $result = [
            'imported' => $imported,
            'order_id' => $input['order_id'],
            'message' => $imported ? 'Order imported successfully' : 'Order already imported'
        ];
        
        return $this->sendResult($result, Response::HTTP_OK);
    }
    
    /**
     * API-Verbindung testen
     *
     * @return Response
     */
    public function testConnectionAction()
    {
        $input = $this->getRequestData();
        $errors = [];
        
        // Konfiguration validieren
        if (empty($input['config']['base_url'])) {
            $errors[] = 'Required field "config.base_url" is empty.';
        }
        if (empty($input['config']['username'])) {
            $errors[] = 'Required field "config.username" is empty.';
        }
        if (empty($input['config']['password'])) {
            $errors[] = 'Required field "config.password" is empty.';
        }
        
        if (count($errors) > 0) {
            throw new ValidationErrorException($errors);
        }
        
        // API Client erstellen
        $apiClient = new GambioApiClient(
            $this->db,
            $this->logger,
            $input['config']
        );
        
        // Verbindung testen
        $connected = $apiClient->testConnection();
        
        $result = [
            'connected' => $connected,
            'message' => $connected ? 'Connection successful' : 'Connection failed'
        ];
        
        return $this->sendResult($result, Response::HTTP_OK);
    }
    
    /**
     * Bestellungen von Gambio abrufen (ohne Import)
     *
     * @return Response
     */
    public function getOrdersAction()
    {
        $input = $this->getRequestData();
        $errors = [];
        
        // Konfiguration validieren
        if (empty($input['config']['base_url'])) {
            $errors[] = 'Required field "config.base_url" is empty.';
        }
        if (empty($input['config']['username'])) {
            $errors[] = 'Required field "config.username" is empty.';
        }
        if (empty($input['config']['password'])) {
            $errors[] = 'Required field "config.password" is empty.';
        }
        
        if (count($errors) > 0) {
            throw new ValidationErrorException($errors);
        }
        
        // API Client erstellen
        $apiClient = new GambioApiClient(
            $this->db,
            $this->logger,
            $input['config']
        );
        
        // Filter für Bestellungen
        $filters = $input['filters'] ?? [];
        
        // Bestellungen abrufen
        $orders = $apiClient->getOrders($filters);
        
        $result = [
            'orders' => $orders,
            'count' => count($orders)
        ];
        
        return $this->sendResult($result, Response::HTTP_OK);
    }
    
    /**
     * Einzelne Bestellung von Gambio abrufen (ohne Import)
     *
     * @return Response
     */
    public function getOrderAction()
    {
        $input = $this->getRequestData();
        $errors = [];
        
        // Konfiguration validieren
        if (empty($input['config']['base_url'])) {
            $errors[] = 'Required field "config.base_url" is empty.';
        }
        if (empty($input['config']['username'])) {
            $errors[] = 'Required field "config.username" is empty.';
        }
        if (empty($input['config']['password'])) {
            $errors[] = 'Required field "config.password" is empty.';
        }
        if (empty($input['order_id'])) {
            $errors[] = 'Required field "order_id" is empty.';
        }
        
        if (count($errors) > 0) {
            throw new ValidationErrorException($errors);
        }
        
        // API Client erstellen
        $apiClient = new GambioApiClient(
            $this->db,
            $this->logger,
            $input['config']
        );
        
        // Bestellung abrufen
        $order = $apiClient->getOrder((int)$input['order_id']);
        
        if (!$order) {
            throw new BadRequestException('Order not found in Gambio');
        }
        
        $result = [
            'order' => $order
        ];
        
        return $this->sendResult($result, Response::HTTP_OK);
    }
} 