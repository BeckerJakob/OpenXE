<?php

class DocumentLinkService
{
  /** @var Application */
  protected $app;

  protected $documentTypes = [
    'angebot' => ['module' => 'angebot', 'table' => 'angebot', 'label' => 'Angebot'],
    'auftrag' => ['module' => 'auftrag', 'table' => 'auftrag', 'label' => 'Auftrag'],
    'lieferschein' => ['module' => 'lieferschein', 'table' => 'lieferschein', 'label' => 'Lieferschein'],
    'rechnung' => ['module' => 'rechnung', 'table' => 'rechnung', 'label' => 'Rechnung'],
    'gutschrift' => ['module' => 'gutschrift', 'table' => 'gutschrift', 'label' => 'Gutschrift'],
    'bestellung' => ['module' => 'bestellung', 'table' => 'bestellung', 'label' => 'Bestellung'],
    'retoure' => ['module' => 'retoure', 'table' => 'retoure', 'label' => 'Retoure'],
    'preisanfrage' => ['module' => 'preisanfrage', 'table' => 'preisanfrage', 'label' => 'Preisanfrage'],
    'versandpakete' => ['module' => 'versandpakete', 'table' => 'versandpakete', 'label' => 'Paket'],
  ];

  protected $documentButtonPrefixes = [
    'angebot' => 'AN',
    'auftrag' => 'AU',
    'lieferschein' => 'LS',
    'rechnung' => 'RE',
    'gutschrift' => 'GS',
    'bestellung' => 'BE',
    'retoure' => 'RT',
    'preisanfrage' => 'PA',
    'versandpakete' => 'PK',
  ];

  protected $messageDocumentTypes = [
    'auftrag' => ['bestellung', 'lieferschein', 'rechnung', 'gutschrift'],
    'bestellung' => ['auftrag'],
    'lieferschein' => ['auftrag'],
    'rechnung' => ['auftrag', 'bestellung', 'lieferschein', 'gutschrift'],
    'gutschrift' => ['rechnung'],
  ];

  protected $columnExistsCache = [];

  public function __construct($app)
  {
    $this->app = $app;
  }

  public function getRelatedDocuments($documentType, $id)
  {
    $relations = $this->emptyRelations();
    $id = (int)$id;
    if($id <= 0) {
      return $relations;
    }

    switch($documentType) {
      case 'auftrag':
        $this->collectOrderRelations($id, $relations);
        break;
      case 'rechnung':
        $this->collectInvoiceRelations($id, $relations);
        break;
      case 'lieferschein':
        $this->collectDeliveryNoteRelations($id, $relations);
        break;
      case 'gutschrift':
        $this->collectCreditNoteRelations($id, $relations);
        break;
      case 'bestellung':
        $this->collectPurchaseOrderRelations($id, $relations);
        break;
    }

    return $relations;
  }

  public function renderDocumentLinks($documentType, $ids)
  {
    $ids = $this->normalizeIds($ids);
    if(empty($ids)) {
      return '-';
    }

    if($documentType === 'versandpakete') {
      return $this->renderPackageLinks($ids);
    }

    if(empty($this->documentTypes[$documentType])) {
      return '-';
    }

    $meta = $this->documentTypes[$documentType];
    $rows = $this->getDocumentRows($documentType, $meta['table'], $ids, $documentType === 'rechnung');
    if(empty($rows)) {
      return '-';
    }

    $links = [];
    foreach($ids as $id) {
      if(empty($rows[$id])) {
        continue;
      }
      if(!$this->canViewDocumentRow($documentType, $rows[$id])) {
        continue;
      }
      $links[] = $this->renderDocumentLink($documentType, $rows[$id]);
    }

    return empty($links) ? '-' : implode('<br />', $links);
  }

  public function setTemplateDocumentLinks($tpl, $links, $placeholders)
  {
    foreach($placeholders as $placeholder => $documentType) {
      $tpl->Set($placeholder, $this->renderDocumentLinks($documentType, isset($links[$documentType]) ? $links[$documentType] : []));
    }
  }

  public function renderRelatedDocumentsMessage($documentType, $id, $options = [])
  {
    $buttons = $this->renderRelatedDocumentButtons($documentType, $id, $options);
    if($buttons === '') {
      return '';
    }

    $class = !empty($options['class']) ? $this->escapeHtml($options['class']) : 'info';
    $text = isset($options['text']) ? (string)$options['text'] : 'Zu diesem Beleg gibt es folgende Dokumente:';

    return '<div class="'.$class.'">'.$this->escapeHtml($text)
      .'&nbsp;<span style="float:right;margin-top:-7px;">'.$buttons.'</span></div>';
  }

  public function renderRelatedDocumentButtons($documentType, $id, $options = [])
  {
    $relatedDocuments = $this->getRelatedDocuments($documentType, $id);
    $documentTypes = !empty($options['documentTypes'])
      ? (array)$options['documentTypes']
      : (isset($this->messageDocumentTypes[$documentType]) ? $this->messageDocumentTypes[$documentType] : []);

    $buttons = '';
    foreach($documentTypes as $relatedDocumentType) {
      if(empty($relatedDocuments[$relatedDocumentType])) {
        continue;
      }
      $buttons .= $this->renderDocumentButtons($relatedDocumentType, $relatedDocuments[$relatedDocumentType]);
    }

    return $buttons;
  }

  public function renderDocumentButtons($documentType, $ids)
  {
    $ids = $this->normalizeIds($ids);
    if(empty($ids)) {
      return '';
    }

    if($documentType === 'versandpakete') {
      return $this->renderPackageButtons($ids);
    }

    if(empty($this->documentTypes[$documentType])) {
      return '';
    }

    $meta = $this->documentTypes[$documentType];
    $rows = $this->getDocumentRows($documentType, $meta['table'], $ids, $documentType === 'rechnung');
    if(empty($rows)) {
      return '';
    }

    $buttons = '';
    foreach($ids as $id) {
      if(empty($rows[$id]) || !$this->canViewDocumentRow($documentType, $rows[$id])) {
        continue;
      }
      $buttons .= $this->renderDocumentButton($documentType, $rows[$id]);
    }

    return $buttons;
  }

  protected function collectOrderRelations($orderId, &$relations)
  {
    $order = $this->app->DB->SelectRow(
      sprintf('SELECT angebotid, rechnungid FROM auftrag WHERE id = %d LIMIT 1', $orderId)
    );

    if(!empty($order['angebotid'])) {
      $this->addIds($relations, 'angebot', [(int)$order['angebotid']]);
    }

    if(!empty($order['rechnungid'])) {
      $this->addIds($relations, 'rechnung', [(int)$order['rechnungid']]);
    }

    $deliveryNoteIds = $this->getDeliveryNoteIdsByOrder($orderId);
    $this->addIds($relations, 'lieferschein', $deliveryNoteIds);

    $invoiceIds = $this->getInvoiceIdsByOrder($orderId);
    $this->addIds($relations, 'rechnung', $invoiceIds);

    $this->addIds($relations, 'gutschrift', $this->getCreditNoteIdsByInvoices($relations['rechnung']));

    $purchaseOrderIds = $this->getPurchaseOrderIdsByOrder($orderId);
    $this->addIds($relations, 'bestellung', $purchaseOrderIds);
    $this->addIds($relations, 'preisanfrage', $this->getPriceRequestIdsByPurchaseOrders($purchaseOrderIds));

    $this->addIds($relations, 'retoure', $this->getReturnOrderIdsByOrders([$orderId]));
    $this->addIds($relations, 'retoure', $this->getReturnOrderIdsByDeliveryNotes($deliveryNoteIds));
    $this->addIds($relations, 'versandpakete', $this->getPackageIdsByOrder($orderId));
  }

  protected function collectInvoiceRelations($invoiceId, &$relations)
  {
    $invoice = $this->app->DB->SelectRow(
      sprintf('SELECT auftragid, lieferschein FROM rechnung WHERE id = %d LIMIT 1', $invoiceId)
    );

    $orderIds = [];
    if(!empty($invoice['auftragid'])) {
      $orderIds[] = (int)$invoice['auftragid'];
    }
    $this->addIds($orderIds, null, $this->app->DB->SelectFirstCols(
      sprintf('SELECT id FROM auftrag WHERE rechnungid = %d', $invoiceId)
    ));
    $this->addIds($orderIds, null, $this->getOrderIdsByCollectiveInvoice($invoiceId));
    $this->addIds($relations, 'auftrag', $orderIds);

    foreach($relations['auftrag'] as $orderId) {
      $this->addIds($relations, 'bestellung', $this->getPurchaseOrderIdsByOrder($orderId));
    }

    $deliveryNoteIds = [];
    if(!empty($invoice['lieferschein'])) {
      $deliveryNoteIds[] = (int)$invoice['lieferschein'];
    }
    $this->addIds($deliveryNoteIds, null, $this->app->DB->SelectFirstCols(
      sprintf('SELECT id FROM lieferschein WHERE rechnungid = %d', $invoiceId)
    ));
    $this->addIds($deliveryNoteIds, null, $this->getDeliveryNoteIdsByCollectiveInvoice($invoiceId));
    $this->addIds($relations, 'lieferschein', $deliveryNoteIds);

    $returnDeliveryNoteIds = $relations['lieferschein'];
    foreach($relations['auftrag'] as $orderId) {
      $this->addIds($returnDeliveryNoteIds, null, $this->getDeliveryNoteIdsByOrder($orderId));
    }

    $this->addIds($relations, 'gutschrift', $this->getCreditNoteIdsByInvoices([$invoiceId]));
    $this->addIds($relations, 'retoure', $this->getReturnOrderIdsByOrders($relations['auftrag']));
    $this->addIds($relations, 'retoure', $this->getReturnOrderIdsByDeliveryNotes($returnDeliveryNoteIds));
    $this->addIds($relations, 'versandpakete', $this->getPackageIdsByDeliveryNotes($relations['lieferschein']));
  }

  protected function collectDeliveryNoteRelations($deliveryNoteId, &$relations)
  {
    $deliveryNote = $this->app->DB->SelectRow(
      sprintf('SELECT auftragid, rechnungid FROM lieferschein WHERE id = %d LIMIT 1', $deliveryNoteId)
    );

    if(!empty($deliveryNote['auftragid'])) {
      $this->addIds($relations, 'auftrag', [(int)$deliveryNote['auftragid']]);
      $this->addIds($relations, 'rechnung', $this->getInvoiceIdsByOrder((int)$deliveryNote['auftragid']));
    }

    if(!empty($deliveryNote['rechnungid'])) {
      $this->addIds($relations, 'rechnung', [(int)$deliveryNote['rechnungid']]);
    }
    $this->addIds($relations, 'rechnung', $this->app->DB->SelectFirstCols(
      sprintf('SELECT id FROM rechnung WHERE lieferschein = %d', $deliveryNoteId)
    ));
    $this->addIds($relations, 'rechnung', $this->getInvoiceIdsByDeliveryNote($deliveryNoteId));

    $this->addIds($relations, 'retoure', $this->getReturnOrderIdsByDeliveryNotes([$deliveryNoteId]));
    $this->addIds($relations, 'versandpakete', $this->getPackageIdsByDeliveryNotes([$deliveryNoteId]));
  }

  protected function collectCreditNoteRelations($creditNoteId, &$relations)
  {
    $creditNote = $this->app->DB->SelectRow(
      sprintf('SELECT rechnungid, lieferschein FROM gutschrift WHERE id = %d LIMIT 1', $creditNoteId)
    );

    if(!empty($creditNote['rechnungid'])) {
      $invoiceId = (int)$creditNote['rechnungid'];
      $this->addIds($relations, 'rechnung', [$invoiceId]);
      $invoiceRelations = $this->getRelatedDocuments('rechnung', $invoiceId);
      $this->addIds($relations, 'auftrag', $invoiceRelations['auftrag']);
      $this->addIds($relations, 'lieferschein', $invoiceRelations['lieferschein']);
      $this->addIds($relations, 'retoure', $invoiceRelations['retoure']);
      $this->addIds($relations, 'versandpakete', $invoiceRelations['versandpakete']);
    }

    if(!empty($creditNote['lieferschein'])) {
      $this->addIds($relations, 'lieferschein', [(int)$creditNote['lieferschein']]);
      $this->addIds($relations, 'versandpakete', $this->getPackageIdsByDeliveryNotes([(int)$creditNote['lieferschein']]));
    }
  }

  protected function collectPurchaseOrderRelations($purchaseOrderId, &$relations)
  {
    $purchaseOrder = $this->app->DB->SelectRow(
      sprintf('SELECT preisanfrageid FROM bestellung WHERE id = %d LIMIT 1', $purchaseOrderId)
    );
    if(!empty($purchaseOrder['preisanfrageid'])) {
      $this->addIds($relations, 'preisanfrage', [(int)$purchaseOrder['preisanfrageid']]);
    }

    $orderIds = $this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT ap.auftrag
        FROM bestellung_position AS bp
        INNER JOIN auftrag_position AS ap ON ap.id = bp.auftrag_position_id
        WHERE bp.bestellung = %d AND ap.auftrag > 0
        ORDER BY ap.auftrag',
        $purchaseOrderId
      )
    );
    $this->addIds($relations, 'auftrag', $orderIds);

    foreach($relations['auftrag'] as $orderId) {
      $orderRelations = $this->getRelatedDocuments('auftrag', $orderId);
      $this->addIds($relations, 'lieferschein', $orderRelations['lieferschein']);
      $this->addIds($relations, 'rechnung', $orderRelations['rechnung']);
      $this->addIds($relations, 'gutschrift', $orderRelations['gutschrift']);
      $this->addIds($relations, 'retoure', $orderRelations['retoure']);
      $this->addIds($relations, 'versandpakete', $orderRelations['versandpakete']);
    }
  }

  protected function getInvoiceIdsByOrder($orderId)
  {
    $ids = $this->app->DB->SelectFirstCols(
      sprintf('SELECT id FROM rechnung WHERE auftragid = %d ORDER BY belegnr, id', $orderId)
    );

    $this->addIds($ids, null, $this->app->DB->SelectFirstCols(
      sprintf('SELECT rechnungid FROM auftrag WHERE id = %d AND rechnungid > 0', $orderId)
    ));
    $this->addIds($ids, null, $this->getInvoiceIdsByCollectiveOrder($orderId));

    return $this->normalizeIds($ids);
  }

  protected function getInvoiceIdsByCollectiveOrder($orderId)
  {
    $ids = [];
    if(!$this->tableExists('sammelrechnung_position')) {
      return $ids;
    }

    $this->addIds($ids, null, $this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT s.rechnung
        FROM sammelrechnung_position AS s
        INNER JOIN auftrag_position AS ap ON ap.id = s.auftrag_position_id
        WHERE ap.auftrag = %d AND s.rechnung > 0',
        $orderId
      )
    ));
    $this->addIds($ids, null, $this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT s.rechnung
        FROM sammelrechnung_position AS s
        INNER JOIN lieferschein_position AS lp ON lp.id = s.lieferschein_position_id
        INNER JOIN auftrag_position AS ap ON ap.id = lp.auftrag_position_id
        WHERE ap.auftrag = %d AND s.rechnung > 0',
        $orderId
      )
    ));

    return $ids;
  }

  protected function getOrderIdsByCollectiveInvoice($invoiceId)
  {
    $ids = [];
    if(!$this->tableExists('sammelrechnung_position')) {
      return $ids;
    }

    $this->addIds($ids, null, $this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT ap.auftrag
        FROM sammelrechnung_position AS s
        INNER JOIN auftrag_position AS ap ON ap.id = s.auftrag_position_id
        WHERE s.rechnung = %d AND ap.auftrag > 0',
        $invoiceId
      )
    ));
    $this->addIds($ids, null, $this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT ap.auftrag
        FROM sammelrechnung_position AS s
        INNER JOIN lieferschein_position AS lp ON lp.id = s.lieferschein_position_id
        INNER JOIN auftrag_position AS ap ON ap.id = lp.auftrag_position_id
        WHERE s.rechnung = %d AND ap.auftrag > 0',
        $invoiceId
      )
    ));

    return $ids;
  }

  protected function getInvoiceIdsByDeliveryNote($deliveryNoteId)
  {
    $ids = [];
    if(!$this->tableExists('sammelrechnung_position')) {
      return $ids;
    }

    $this->addIds($ids, null, $this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT s.rechnung
        FROM sammelrechnung_position AS s
        INNER JOIN lieferschein_position AS lp ON lp.id = s.lieferschein_position_id
        WHERE lp.lieferschein = %d AND s.rechnung > 0',
        $deliveryNoteId
      )
    ));

    return $ids;
  }

  protected function getDeliveryNoteIdsByCollectiveInvoice($invoiceId)
  {
    $ids = [];
    if(!$this->tableExists('sammelrechnung_position')) {
      return $ids;
    }

    $this->addIds($ids, null, $this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT lp.lieferschein
        FROM sammelrechnung_position AS s
        INNER JOIN lieferschein_position AS lp ON lp.id = s.lieferschein_position_id
        WHERE s.rechnung = %d AND lp.lieferschein > 0',
        $invoiceId
      )
    ));
    $this->addIds($ids, null, $this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT lp.lieferschein
        FROM sammelrechnung_position AS s
        INNER JOIN auftrag_position AS ap ON ap.id = s.auftrag_position_id
        INNER JOIN lieferschein_position AS lp ON lp.auftrag_position_id = ap.id
        WHERE s.rechnung = %d AND lp.lieferschein > 0',
        $invoiceId
      )
    ));

    return $ids;
  }

  protected function getDeliveryNoteIdsByOrder($orderId)
  {
    return $this->normalizeIds($this->app->DB->SelectFirstCols(
      sprintf('SELECT id FROM lieferschein WHERE auftragid = %d ORDER BY belegnr, id', $orderId)
    ));
  }

  protected function getCreditNoteIdsByInvoices($invoiceIds)
  {
    $invoiceIds = $this->normalizeIds($invoiceIds);
    if(empty($invoiceIds)) {
      return [];
    }

    return $this->normalizeIds($this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT id FROM gutschrift WHERE rechnungid IN (%s) ORDER BY belegnr, id',
        implode(',', $invoiceIds)
      )
    ));
  }

  protected function getPurchaseOrderIdsByOrder($orderId)
  {
    return $this->normalizeIds($this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT bp.bestellung
        FROM bestellung_position AS bp
        INNER JOIN auftrag_position AS ap ON ap.id = bp.auftrag_position_id
        WHERE ap.auftrag = %d AND bp.bestellung > 0
        ORDER BY bp.bestellung',
        $orderId
      )
    ));
  }

  protected function getPriceRequestIdsByPurchaseOrders($purchaseOrderIds)
  {
    $purchaseOrderIds = $this->normalizeIds($purchaseOrderIds);
    if(empty($purchaseOrderIds)) {
      return [];
    }

    return $this->normalizeIds($this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT preisanfrageid
        FROM bestellung
        WHERE id IN (%s) AND preisanfrageid > 0
        ORDER BY preisanfrageid',
        implode(',', $purchaseOrderIds)
      )
    ));
  }

  protected function getReturnOrderIdsByOrders($orderIds)
  {
    $orderIds = $this->normalizeIds($orderIds);
    if(empty($orderIds)) {
      return [];
    }

    return $this->normalizeIds($this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT ro.id
        FROM retoure AS ro
        WHERE ro.auftragid IN (%s)
        ORDER BY ro.id',
        implode(',', $orderIds)
      )
    ));
  }

  protected function getReturnOrderIdsByDeliveryNotes($deliveryNoteIds)
  {
    $deliveryNoteIds = $this->normalizeIds($deliveryNoteIds);
    if(empty($deliveryNoteIds)) {
      return [];
    }

    return $this->normalizeIds($this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT ro.id
        FROM retoure AS ro
        WHERE ro.lieferscheinid IN (%s)
        ORDER BY ro.id',
        implode(',', $deliveryNoteIds)
      )
    ));
  }

  protected function getPackageIdsByOrder($orderId)
  {
    return $this->normalizeIds($this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT vp.id
        FROM versandpakete AS vp
        LEFT JOIN versandpaket_lieferschein_position AS vlp ON vp.id = vlp.versandpaket
        LEFT JOIN lieferschein_position AS lp ON lp.id = vlp.lieferschein_position
        LEFT JOIN lieferschein AS l ON lp.lieferschein = l.id
        LEFT JOIN lieferschein AS lop ON lop.id = vp.lieferschein_ohne_pos
        WHERE l.auftragid = %d OR lop.auftragid = %d
        ORDER BY vp.id',
        $orderId,
        $orderId
      )
    ));
  }

  protected function getPackageIdsByDeliveryNotes($deliveryNoteIds)
  {
    $deliveryNoteIds = $this->normalizeIds($deliveryNoteIds);
    if(empty($deliveryNoteIds)) {
      return [];
    }

    return $this->normalizeIds($this->app->DB->SelectFirstCols(
      sprintf(
        'SELECT DISTINCT vp.id
        FROM versandpakete AS vp
        LEFT JOIN versandpaket_lieferschein_position AS vlp ON vp.id = vlp.versandpaket
        LEFT JOIN lieferschein_position AS lp ON lp.id = vlp.lieferschein_position
        WHERE lp.lieferschein IN (%s) OR vp.lieferschein_ohne_pos IN (%s)
        ORDER BY vp.id',
        implode(',', $deliveryNoteIds),
        implode(',', $deliveryNoteIds)
      )
    ));
  }

  protected function renderDocumentLink($documentType, $row)
  {
    $meta = $this->documentTypes[$documentType];
    $id = (int)$row['id'];
    $label = $this->documentLabel(isset($row['belegnr']) ? $row['belegnr'] : '');
    $isCanceled = isset($row['status']) && $row['status'] === 'storniert';
    $title = $isCanceled ? ' title="'.$this->escapeHtml($meta['label'].' storniert').'"' : '';

    $html = '<a href="index.php?module='.$meta['module'].'&action=edit&id='.$id.'" target="_blank"'.$title.'>';
    $html .= $isCanceled ? '<s>'.$label.'</s>' : $label;
    $html .= '</a>&nbsp;';
    $html .= $this->renderDownloadIcon($documentType, $row).'&nbsp;';
    $html .= '<a href="index.php?module='.$meta['module'].'&action=edit&id='.$id.'" target="_blank">';
    $html .= '<img src="./themes/new/images/edit.svg" title="'.$this->escapeHtml($meta['label'].' bearbeiten').'" border="0">';
    $html .= '</a>';

    return $html;
  }

  protected function renderDocumentButton($documentType, $row)
  {
    if(empty($this->documentTypes[$documentType])) {
      return '';
    }

    $meta = $this->documentTypes[$documentType];
    $id = (int)$row['id'];
    if($id <= 0) {
      return '';
    }

    $prefix = isset($this->documentButtonPrefixes[$documentType]) ? $this->documentButtonPrefixes[$documentType] : strtoupper(substr($documentType, 0, 2));
    $buttonText = $prefix.' '.$this->documentLabel(isset($row['belegnr']) ? $row['belegnr'] : '');
    $title = '';
    if(isset($row['status']) && $row['status'] === 'storniert') {
      $title = ' title="'.$this->escapeHtml($meta['label'].' storniert').'"';
    }

    return '&nbsp;<input type="button" value="'.$buttonText.'"'.$title
      .' onclick="window.location.href=\'index.php?module='.$meta['module'].'&action=edit&id='.$id.'\'">';
  }

  protected function renderDownloadIcon($documentType, $row)
  {
    $meta = $this->documentTypes[$documentType];
    $id = (int)$row['id'];
    $action = 'pdf';
    $icon = 'pdf.svg';
    $title = $meta['label'].' PDF';

    if($documentType === 'rechnung' && !empty($row['xmlrechnung'])) {
      $action = 'xml';
      $icon = 'xml.svg';
      $title = 'Rechnung XML';
    }

    return '<a href="index.php?module='.$meta['module'].'&action='.$action.'&id='.$id.'" target="_blank">'
      .'<img src="./themes/new/images/'.$icon.'" title="'.$this->escapeHtml($title).'" border="0"></a>';
  }

  protected function renderPackageLinks($ids)
  {
    $rows = $this->getPackageRows($ids);
    if(empty($rows)) {
      return '-';
    }

    $links = [];
    foreach($ids as $id) {
      if(empty($rows[$id])) {
        continue;
      }
      $row = $rows[$id];
      if(empty($row['visible'])) {
        continue;
      }
      $tracking = trim((string)$row['tracking']);
      $trackingLink = $this->sanitizeTrackingUrl($row['tracking_link']);
      $html = '<a href="index.php?module=versandpakete&action=edit&id='.$id.'" target="_blank">Paket Nr.'.$id.'</a>';
      if($tracking !== '') {
        if($trackingLink !== '') {
          $html .= ' (<a href="'.$this->escapeHtml($trackingLink).'" target="_blank" rel="noopener noreferrer">'.$this->escapeHtml($tracking).'</a>)';
        }
        else {
          $html .= ' ('.$this->escapeHtml($tracking).')';
        }
      }
      $links[] = $html;
    }

    return empty($links) ? '-' : implode('<br />', $links);
  }

  protected function getPackageRows($ids)
  {
    $ids = $this->normalizeIds($ids);
    if(empty($ids)) {
      return [];
    }

    $rows = $this->app->DB->SelectArr(
      sprintf(
        'SELECT
          vp.id,
          vp.tracking,
          vp.tracking_link,
          l.id AS lieferschein_id,
          l.projekt AS lieferschein_projekt,
          l.adresse AS lieferschein_adresse,
          l.usereditid AS lieferschein_usereditid,
          lop.id AS lieferschein_ohne_pos_id,
          lop.projekt AS lieferschein_ohne_pos_projekt,
          lop.adresse AS lieferschein_ohne_pos_adresse,
          lop.usereditid AS lieferschein_ohne_pos_usereditid
        FROM versandpakete AS vp
        LEFT JOIN versandpaket_lieferschein_position AS vlp ON vp.id = vlp.versandpaket
        LEFT JOIN lieferschein_position AS lp ON lp.id = vlp.lieferschein_position
        LEFT JOIN lieferschein AS l ON l.id = lp.lieferschein
        LEFT JOIN lieferschein AS lop ON lop.id = vp.lieferschein_ohne_pos
        WHERE vp.id IN (%s)
        ORDER BY vp.id',
        implode(',', $ids)
      )
    );
    if(empty($rows)) {
      return [];
    }

    $byId = [];
    foreach($rows as $row) {
      $packageId = (int)$row['id'];
      if(empty($byId[$packageId])) {
        $byId[$packageId] = [
          'id' => $packageId,
          'tracking' => $row['tracking'],
          'tracking_link' => $row['tracking_link'],
          'visible' => false,
        ];
      }
      if($this->canViewPackageRow($row)) {
        $byId[$packageId]['visible'] = true;
      }
    }

    return $byId;
  }

  protected function renderPackageButtons($ids)
  {
    $rows = $this->getPackageRows($ids);
    if(empty($rows)) {
      return '';
    }

    $buttons = '';
    foreach($ids as $id) {
      if(empty($rows[$id]) || empty($rows[$id]['visible'])) {
        continue;
      }
      $buttonText = $this->documentButtonPrefixes['versandpakete'].' '.$id;
      $buttons .= '&nbsp;<input type="button" value="'.$this->escapeHtml($buttonText)
        .'" onclick="window.location.href=\'index.php?module=versandpakete&action=edit&id='.(int)$id.'\'">';
    }

    return $buttons;
  }

  protected function canViewPackageRow($row)
  {
    if(!empty($row['lieferschein_id'])) {
      if($this->canViewDocumentRow(
        'lieferschein',
        [
          'id' => $row['lieferschein_id'],
          'projekt' => $row['lieferschein_projekt'],
          'adresse' => $row['lieferschein_adresse'],
          'usereditid' => $row['lieferschein_usereditid'],
        ]
      )) {
        return true;
      }
    }

    if(!empty($row['lieferschein_ohne_pos_id'])) {
      return $this->canViewDocumentRow(
        'lieferschein',
        [
          'id' => $row['lieferschein_ohne_pos_id'],
          'projekt' => $row['lieferschein_ohne_pos_projekt'],
          'adresse' => $row['lieferschein_ohne_pos_adresse'],
          'usereditid' => $row['lieferschein_ohne_pos_usereditid'],
        ]
      );
    }

    return false;
  }

  protected function getDocumentRows($documentType, $table, $ids, $withXml)
  {
    $selectColumns = ['id', 'belegnr', 'status'];
    foreach(['projekt', 'adresse', 'usereditid'] as $column) {
      if($this->columnExists($table, $column)) {
        $selectColumns[] = $column;
      }
    }
    if($withXml && $this->columnExists($table, 'xmlrechnung')) {
      $selectColumns[] = 'xmlrechnung';
    }
    $rows = $this->app->DB->SelectArr(
      sprintf(
        'SELECT %s FROM %s WHERE id IN (%s)',
        implode(', ', $selectColumns),
        $table,
        implode(',', $ids)
      )
    );
    if(empty($rows)) {
      return [];
    }

    $byId = [];
    foreach($rows as $row) {
      $byId[(int)$row['id']] = $row;
    }

    return $byId;
  }

  protected function canViewDocumentRow($documentType, $row)
  {
    if(!array_key_exists('projekt', $row)) {
      return true;
    }

    $project = (int)$row['projekt'];
    if($this->app->erp->UserProjektRecht($project)) {
      return true;
    }

    if(!$this->isSalesDocumentType($documentType) || !$this->app->erp->ModulVorhanden('vertriebscockpit')) {
      return false;
    }

    $currentAddress = (int)$this->app->User->GetAdresse();
    $currentUser = (int)$this->app->User->GetID();
    if(!empty($row['adresse']) && $currentAddress > 0) {
      $salesAddress = (int)$this->app->DB->Select(
        sprintf(
          'SELECT id FROM adresse WHERE id = %d AND vertrieb = %d LIMIT 1',
          (int)$row['adresse'],
          $currentAddress
        )
      );
      if($salesAddress > 0) {
        return true;
      }
    }

    return !empty($row['usereditid']) && (int)$row['usereditid'] === $currentUser;
  }

  protected function isSalesDocumentType($documentType)
  {
    return in_array($documentType, ['auftrag', 'rechnung', 'gutschrift', 'angebot', 'lieferschein', 'retoure'], true);
  }

  protected function sanitizeTrackingUrl($url)
  {
    $url = trim((string)$url);
    if($url === '') {
      return '';
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    if(!in_array(strtolower((string)$scheme), ['http', 'https'], true)) {
      return '';
    }

    return $url;
  }

  protected function documentLabel($belegnr)
  {
    $belegnr = (string)$belegnr;
    if($belegnr === '' || $belegnr === '0') {
      return 'ENTWURF';
    }

    return $this->escapeHtml($belegnr);
  }

  protected function emptyRelations()
  {
    return [
      'angebot' => [],
      'auftrag' => [],
      'lieferschein' => [],
      'rechnung' => [],
      'gutschrift' => [],
      'bestellung' => [],
      'retoure' => [],
      'preisanfrage' => [],
      'versandpakete' => [],
    ];
  }

  protected function addIds(&$target, $type, $ids)
  {
    $ids = $this->normalizeIds($ids);
    if(empty($ids)) {
      return;
    }

    if($type !== null) {
      if(!isset($target[$type]) || !is_array($target[$type])) {
        $target[$type] = [];
      }
      $target =& $target[$type];
    }

    foreach($ids as $id) {
      if(!in_array($id, $target, true)) {
        $target[] = $id;
      }
    }
  }

  protected function normalizeIds($ids)
  {
    if(empty($ids) || !is_array($ids)) {
      return [];
    }

    $result = [];
    foreach($ids as $id) {
      if(is_array($id)) {
        $id = reset($id);
      }
      $id = (int)$id;
      if($id > 0 && !in_array($id, $result, true)) {
        $result[] = $id;
      }
    }

    return $result;
  }

  protected function tableExists($table)
  {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
    if($table === '') {
      return false;
    }

    return (bool)$this->app->DB->Select("SHOW TABLES LIKE '".$this->app->DB->real_escape_string($table)."'");
  }

  protected function columnExists($table, $column)
  {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
    if($table === '' || $column === '') {
      return false;
    }

    $cacheKey = $table.'.'.$column;
    if(array_key_exists($cacheKey, $this->columnExistsCache)) {
      return $this->columnExistsCache[$cacheKey];
    }

    $this->columnExistsCache[$cacheKey] = (bool)$this->app->DB->Select(
      "SHOW COLUMNS FROM `".$table."` LIKE '".$this->app->DB->real_escape_string($column)."'"
    );

    return $this->columnExistsCache[$cacheKey];
  }

  protected function escapeHtml($value)
  {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}
