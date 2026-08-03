<?php

namespace Brix\CRM\Business;

use Brix\Core\Type\BrixEnv;
use Brix\CRM\Type\Customer\T_CRM_Customer;
use Brix\CRM\Type\Invoice\T_CRM_Invoice;
use Brix\CRM\Type\Invoice\T_CRM_InvoiceItem;
use Brix\CRM\Type\T_CrmConfig;
use Lack\Invoice\InvoiceFacet;
use Lack\Invoice\OfferFacet;
use Lack\Invoice\Type\T_Layout;
use Phore\Cli\Input\In;
use Phore\Cli\Output\Out;
use Phore\AiHarness\PromptType\StructPrompt;
use Phore\AiHarness\PromptType\TextPrompt;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;

class CrmCustomerWrapper
{

    public function __construct(public readonly T_CRM_Customer $customer, public readonly BrixEnv $brixEnv, public readonly T_CrmConfig $config, public readonly PhoreDirectory $customerDir)
    {
    }



    public function createNewInvoice() : string
    {
        $invoice = new T_CRM_Invoice();

        $template = $this->customerDir->withRelativePath("invoice-tpl.yml");
        if ( ! $template->isFile()) {
            throw new \InvalidArgumentException("Customer has no invoice-tpl.yml");
        }
        $invoice = $template->assertFile()->get_yaml(T_CRM_Invoice::class);

        $invoice->invoiceId = "X-" . $this->brixEnv->getState("crm")->increment("invoiceId");
        $invoice->invoiceDate = date("d.m.Y");



        $invoiceDir = $this->customerDir->withRelativePath("inv_new")->assertDirectory(true);
        $invFile = $invoiceDir->withFileName($invoice->invoiceId . ".yml");
        $invFile->set_yaml(phore_dehydrate($invoice));

        return $invoice->invoiceId;
    }

    /**
     * Creates a follow-up invoice from a previous invoice.
     *
     * One-time items are removed. Recurring items are identified by an explicit
     * time period in title/description and are moved to the current invoice date
     * by phore/ai-harness.
     *
     * @return array{0: string, 1: T_CRM_Invoice, 2: T_CRM_Invoice}
     */
    public function createFollowUpInvoice(string $previousInvoiceId): array
    {
        $previousInvoice = $this->getInvoice($previousInvoiceId);
        $invoice = clone $previousInvoice;
        $invoice->invoiceId = "X-" . $this->brixEnv->getState("crm")->increment("invoiceId");
        $invoice->invoiceDate = date("d.m.Y");

        $prompts = [
            new TextPrompt("Passe die angegebenen alten Rechnungsposten fuer eine Folgerechnung an.\n"
                . "Die Datenstruktur der Rechnungsposten darf sich nicht aendern: title, desc, vat, unit_price_net, quantity.\n"
                . "Nur wiederkehrende Posten duerfen in der Ergebnisliste bleiben. Wiederkehrende Posten erkennst du daran, dass in Titel oder Beschreibung ein Zeitraum angegeben ist (z.B. 02/2026 - 02/2027, November 2025 bis November 2026, monatlich fuer 12 Monate).\n"
                . "Alle einmaligen Posten ohne Zeitraum muessen aus der Ergebnisliste entfernt werden.\n"
                . "Setze bei uebernommenen wiederkehrenden Posten den Zeitraum passend zur aktuellen neuen Rechnung weiter. Aktuelles Rechnungsdatum: " . $invoice->invoiceDate . ". Aktueller Monat/Jahr: " . date("m/Y") . ".\n"
                . "Behalte Titel, MwSt., Nettopreis und Menge unveraendert, ausser die Beschreibung enthaelt den alten Zeitraum. Aktualisiere dann nur den Zeitbezug in der Beschreibung."),
            new StructPrompt([
                "previous_invoice_id" => $previousInvoice->invoiceId,
                "previous_invoice_date" => $previousInvoice->invoiceDate,
                "new_invoice_id" => $invoice->invoiceId,
                "new_invoice_date" => $invoice->invoiceDate,
                "items" => $previousInvoice->items,
            ], "previousInvoice", "Alte Rechnungsposten. Gib als Ergebnis ausschliesslich die angepassten Rechnungsposten in unveraenderter Struktur zurueck.")
        ];

        $tenant = $this->config->getTenantById($this->customer->tenant_id);
        $invoiceSkillFile = $this->brixEnv->rootDir
            ->withRelativePath(dirname($tenant->invoice_email_tpl) . "/invoice-skill.md")->asFile();
        if ($invoiceSkillFile->exists()) {
            $prompts[] = new TextPrompt(
                $invoiceSkillFile->get_contents(),
                "invoiceSkill",
                "Zusaetzliche kundenspezifische Regeln fuer den Umgang mit Rechnungen. Diese Regeln sind bei der Folgerechnung zu beruecksichtigen.",
                "markdown"
            );
        }

        $invoice->items = \phore_ai_struct_array($prompts, T_CRM_InvoiceItem::class, ["model"=>"gpt-5.4-mini"]);

        $invoiceDir = $this->customerDir->withRelativePath("inv_new")->assertDirectory(true);
        $invFile = $invoiceDir->withFileName($invoice->invoiceId . ".yml");
        $invFile->set_yaml(phore_dehydrate($invoice));

        return [$invoice->invoiceId, $previousInvoice, $invoice];
    }

    public function createNewOffer() : string
    {
        $invoice = new T_CRM_Invoice();

        $template = $this->customerDir->withRelativePath("invoice-tpl.yml");
        if ( ! $template->isFile()) {
            throw new \InvalidArgumentException("Customer has no invoice-tpl.yml");
        }
        $invoice = $template->assertFile()->get_yaml(T_CRM_Invoice::class);

        $invoice->invoiceId = "O-" . $this->brixEnv->getState("crm")->increment("offerId");
        $invoice->invoiceDate = date("d.m.Y");



        $invoiceDir = $this->customerDir->withRelativePath("offers_new")->assertDirectory(true);
        $invFile = $invoiceDir->withFileName($invoice->invoiceId . ".yml");
        $invFile->set_yaml(phore_dehydrate($invoice));

        return $invoice->invoiceId;
    }

    /**
     * @return T_CRM_Invoice[]
     * @throws \Phore\FileSystem\Exception\FilesystemException
     * @throws \Phore\FileSystem\Exception\PathOutOfBoundsException
     */
    public function listNewInvoices () : array {
        $invoiceDir = $this->customerDir->withRelativePath("inv_new")->assertDirectory(true);
        $invoices = [];
        foreach ($invoiceDir->listFiles() as $file) {
            $invoices[] = $file->get_yaml(T_CRM_Invoice::class);
        }
        return $invoices;
    }

   public function getOffer(string $offerId) : T_CRM_Invoice {
        $invoiceDir = $this->customerDir->withRelativePath("offers_new")->assertDirectory(true);
        $invFile = $invoiceDir->withFileName($offerId . ".yml");
        return $invFile->get_yaml(T_CRM_Invoice::class);
    }

    public function getInvoice(string $invId) : T_CRM_Invoice {
        $invoiceDir = $this->customerDir->withRelativePath("inv_new")->assertDirectory(true);
        $invFile = $invoiceDir->withFileName($invId . ".yml");
        return $invFile->get_yaml(T_CRM_Invoice::class);
    }

    public function buildInvoice (T_CRM_Invoice $invoice) : string {
        $tentant = $this->config->getTenantById($this->customer->tenant_id);

        $invoiceDir = $this->customerDir->withRelativePath("inv_new")->assertDirectory(true);
        $invFile = $invoiceDir->withFileName($invoice->invoiceId . ".yml");

        $iv = new InvoiceFacet(
            $this->brixEnv->rootDir->withRelativePath($tentant->invoice_layout)->assertFile()->get_yaml(T_Layout::class),
            $this->customer,
            $invoice
        );
        $pdfFile = $invFile->getDirname()->withFileName("Rechnung_" . $this->customer->tenant_id . "_" . $invoice->invoiceId . ".pdf");
        $iv->generate($pdfFile);
        return $pdfFile->getUri();
    }


    public function getInvoicePdfFile(string $invId) : PhoreFile {
        $invoiceDir = $this->customerDir->withRelativePath("inv_new")->assertDirectory(false);
        $pdfFile = $invoiceDir->withFileName("Rechnung_" . $this->customer->tenant_id . "_" . $invId . ".pdf");
        return $pdfFile->assertFile();
    }


    public function buildOffer(T_CRM_Invoice $offer) : string {
        $tentant = $this->config->getTenantById($this->customer->tenant_id);

        $invoiceDir = $this->customerDir->withRelativePath("offers_new")->assertDirectory(true);
        $invFile = $invoiceDir->withFileName($offer->invoiceId . ".yml");

        $iv = new OfferFacet(
            $this->brixEnv->rootDir->withRelativePath($tentant->invoice_layout)->assertFile()->get_yaml(T_Layout::class),
            $this->customer,
            $offer
        );
        $pdfFile = $invFile->getDirname()->withFileName("Angebot_" . $this->customer->tenant_id . "_" . $offer->invoiceId . ".pdf");
        $iv->generate($pdfFile);
        return $pdfFile->getUri();
    }

}
