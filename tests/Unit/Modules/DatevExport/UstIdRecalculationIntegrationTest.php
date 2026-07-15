<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\DatevExport;

use PHPUnit\Framework\TestCase;

final class UstIdRecalculationIntegrationTest extends TestCase
{
    public function testErpApiProvidesFullTaxRebuildForOrdersAndInvoices(): void
    {
        $source = $this->readRepoFile('www/lib/class.erpapi.php');

        self::assertStringContainsString('function BelegSteuerlichNeuaufbauen($typ, $id, $force = false, $resetExtsoll = false)', $source);
        self::assertStringContainsString('$this->LoadSteuersaetzeWaehrung($id, $typ);', $source);
        self::assertStringContainsString('$this->BelegPositionenSteuerlichNeuaufbauen($typ, $id);', $source);
        self::assertStringContainsString('UPDATE `%s` SET steuersatz = NULL WHERE id = %d LIMIT 1', $source);
        self::assertStringContainsString('$this->GetSteuerPosition($typ, $positionId, $steuersatz, $steuertext, $erloese);', $source);
        self::assertStringContainsString('if(!$mitUmsatzsteuer) {', $source);
        self::assertStringContainsString('ust_ok = 0', $source);
        self::assertStringContainsString('function RechnungNeuberechnen($id,$force=false)', $source);
        self::assertStringContainsString('$this->ANABREGSNeuberechnen($id,"rechnung",$force);', $source);
    }

    public function testOrderAndInvoiceSaveFlowsTriggerTaxRebuildAfterUstIdChanges(): void
    {
        $orderPage = $this->readRepoFile('www/pages/auftrag.php');
        $invoicePage = $this->readRepoFile('www/pages/rechnung.php');

        self::assertStringContainsString('$orderTaxChanged', $orderPage);
        self::assertStringContainsString('BelegSteuerlichNeuaufbauen(\'auftrag\', $id, true, true)', $orderPage);
        self::assertStringContainsString('BelegSteuerlichNeuaufbauen(\'rechnung\', (int)$linkedInvoiceId, true, true)', $orderPage);
        self::assertStringContainsString('BelegUstIdInAdresseUebernehmen(\'auftrag\', $id)', $orderPage);
        self::assertStringContainsString('ustid_force_recalculate_confirmed', $orderPage);

        self::assertStringContainsString('$invoiceTaxChanged', $invoicePage);
        self::assertStringContainsString('BelegSteuerlichNeuaufbauen(\'auftrag\', (int)$linkedOrderId, true, true)', $invoicePage);
        self::assertStringContainsString('BelegSteuerlichNeuaufbauen(\'rechnung\', (int)$linkedInvoiceId, true, true)', $invoicePage);
        self::assertStringContainsString('BelegUstIdInAdresseUebernehmen(\'rechnung\', $id)', $invoicePage);
        self::assertStringContainsString('ustid_force_recalculate_confirmed', $invoicePage);
    }

    public function testOrderAndInvoiceFormsOfferCustomerAddressUpdateOption(): void
    {
        $orderTemplate = $this->readRepoFile('www/widgets/templates/_gen/auftrag.tpl');
        $invoiceTemplate = $this->readRepoFile('www/widgets/templates/_gen/rechnung.tpl');

        foreach (array($orderTemplate, $invoiceTemplate) as $template) {
            self::assertStringContainsString('name="ustid_update_adresse"', $template);
            self::assertStringContainsString('USt-ID auch im Kundenstamm aktualisieren', $template);
            self::assertStringContainsString('name="ustid_force_recalculate_confirmed"', $template);
        }
    }

    private function readRepoFile(string $relativePath): string
    {
        $path = dirname(__DIR__, 4).'/'.$relativePath;
        $content = file_get_contents($path);
        self::assertIsString($content);

        return $content;
    }
}
