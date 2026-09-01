<?php

namespace Brix\CRM\Business;

use Brix\Core\Type\BrixEnv;
use Brix\CRM\Type\Customer\T_CRM_Customer;
use Brix\CRM\Type\Invoice\T_CRM_Invoice;
use Brix\CRM\Type\T_CrmConfig;
use Lack\Invoice\InvoiceFacet;
use Lack\Invoice\Type\T_Layout;
use Phore\AiHarness\PromptType\FilePrompt;
use Phore\AiHarness\PromptType\StructPrompt;
use Phore\AiHarness\PromptType\SystemPrompt;
use Phore\AiHarness\PromptType\TextPrompt;
use Phore\Cli\Output\Out;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;

/**
 * Creates and revises an invoice draft without reserving an invoice number.
 *
 * The draft remains outside the customer directory until commit() is called.
 * Every AI revision receives the immutable original invoice, the customer's
 * invoice template and the tenant's invoice skill as references.
 */
class AiInvoiceCreator
{
    private T_CRM_Invoice $initialInvoice;
    private ?T_CRM_Invoice $previousDraft = null;

    public function __construct(
        private readonly T_CRM_Invoice $originalInvoice,
        private readonly T_CRM_Invoice $templateInvoice,
        private readonly T_CRM_Customer $customer,
        private readonly BrixEnv $brixEnv,
        private readonly T_CrmConfig $config,
        private readonly PhoreDirectory $customerDir,
    ) {
        $this->initialInvoice = clone $this->originalInvoice;
        $this->initialInvoice->invoiceId = "";
        $this->initialInvoice->invoiceDate = date("d.m.Y");
    }

    /**
     * Writes the unmodified initial draft and generates its preview PDF.
     */
    public function createDraft(): T_CRM_Invoice
    {
        $this->previousDraft = null;
        return $this->writeDraft(clone $this->initialInvoice);
    }

    public function getDraft(): T_CRM_Invoice
    {
        $draft = $this->getDraftFile()->assertFile()->get_json(T_CRM_Invoice::class);
        if (!$draft instanceof T_CRM_Invoice) {
            throw new \RuntimeException("Invalid invoice draft");
        }
        return $draft;
    }

    /**
     * Lets phore-ai-harness return a revised, typed invoice structure.
     * A null instruction starts the automatic first run using only the invoice skill.
     */
    public function revise(?string $instruction): T_CRM_Invoice
    {
        if ($instruction !== null) {
            $instruction = trim($instruction);
            if ($instruction === "") {
                return $this->getDraft();
            }
        }
        $invoiceSkillFile = $this->getInvoiceSkillFile();
        if (! $invoiceSkillFile->exists()) {
            throw new \RuntimeException("Invoice skill file not found: " . $invoiceSkillFile->getUri());
        }
        Out::TextInfo("Lese Skill-Datei " . $invoiceSkillFile->getUri() . " als Basis für die Änderungen ein.");

        $currentDraft = $this->previousDraft;
        $prompts = [
            // Bei jedem Lauf gesetzt: Legt fest, welche Quelle beim ersten bzw. bei späteren Läufen die Basis ist.
            new SystemPrompt("Create the revised invoice items. If currentInvoiceDraft contains an invoice, use it as the base from the previous run. If currentInvoiceDraft is empty, this is the first run: create a follow-up invoice from originalInvoice and use customerInvoiceTemplate as the template. Apply userRevisionInstruction when it is provided. Always follow invoiceSkill. Do not change invoiceId or invoiceDate."),
        ];

        if ($instruction !== null) {
            // Nur bei manuellen Läufen gesetzt: Enthält ausschließlich die aktuelle Änderungsanweisung des Benutzers.
            $prompts[] = new TextPrompt($instruction, "userRevisionInstruction", "This is the user instruction for this revision. Apply it to currentInvoiceDraft when available; otherwise apply it to the first draft based on originalInvoice.");
        }

        $prompts = [
            ...$prompts,
            // Im ersten Lauf leer, danach gesetzt: Enthält exakt das Ergebnis des unmittelbar vorherigen KI-Laufs.
            new StructPrompt($currentDraft ?? [], "currentInvoiceDraft", "Invoice returned by the previous AI run. An empty structure means this is the first run and no previous AI draft exists."),

            // Bei jedem Lauf gesetzt und unverändert: Die alte Rechnung als fachliche und inhaltliche Referenz.
            new StructPrompt($this->originalInvoice, "originalInvoice", "Original invoice used as the factual basis on the first run and as an immutable reference on later runs."),

            // Bei jedem Lauf gesetzt und unverändert: Die kundenspezifische Vorlage für Aufbau und neue Positionen.
            new StructPrompt($this->templateInvoice, "customerInvoiceTemplate", "Use this customer invoice template when creating or adding invoice items."),

            // Bei jedem Lauf frisch eingelesen: Aktueller Inhalt der Skill-Datei mit den verbindlichen Rechnungsregeln.
            FilePrompt::fromFile(
                $invoiceSkillFile->getUri(),
                "invoiceSkill",
                "General Instructions for creating and revising the invoice. Follow them on every run.",
                "markdown",
            )
        ];

        $columnRenderers = [
            "quantity" => static fn(mixed $value, array $row): string => (string) round((float)$value),
        ];
        $baseInvoice = $currentDraft ?? $this->initialInvoice;
        $draft = \phore_ai_struct($prompts, T_CRM_Invoice::class, ["model" => "gpt-5.6"]);
        $draft->invoiceId = "";
        $draft->invoiceDate = $baseInvoice->invoiceDate;

        $noticeChangeRequested = $instruction !== null
            && preg_match('/\b(?:remarks?|notice|bemerkungen?|anmerkungen?|hinweise?|rechnungshinweise?)\b/ui', $instruction) === 1;
        if (!$noticeChangeRequested) {
            $draft->notice = $baseInvoice->notice;
        }

        $draft = $this->writeDraft($draft);
        $this->previousDraft = clone $draft;

        Out::TextSuccess("Überarbeitete Rechnungsposten:");
        Out::Table($draft->items, false, ["title", "desc", "vat", "unit_price_net", "quantity"], $columnRenderers);
        Out::TextSuccess("Remarks der aktuellen Version:\n" . ($draft->notice === null || trim($draft->notice) === "" ? "(leer)" : $draft->notice));

        return $draft;
    }

    /**
     * Restores the draft to the state before the first user instruction.
     */
    public function reset(): T_CRM_Invoice
    {
        $this->previousDraft = null;
        return $this->writeDraft(clone $this->initialInvoice);
    }

    /**
     * Reserves the invoice number and persists the accepted draft in the
     * customer's inv_new directory.
     */
    public function commit(): string
    {
        $invoice = $this->getDraft();
        $invoice->invoiceId = "X-" . $this->brixEnv->getState("crm")->increment("invoiceId");

        $invoiceDir = $this->customerDir->withRelativePath("inv_new")->assertDirectory(true);
        $invoiceDir->withFileName($invoice->invoiceId . ".yml")
            ->set_yaml(phore_dehydrate($invoice));

        return $invoice->invoiceId;
    }

    public function getDraftFile(): PhoreFile
    {
        return $this->getDraftDirectory()->withFileName($this->customer->customerId . "-vorlage.json");
    }

    public function getPreviewPdfFile(): PhoreFile
    {
        return $this->getDraftDirectory()->withFileName($this->customer->customerId . "-vorlage.pdf");
    }

    private function writeDraft(T_CRM_Invoice $invoice): T_CRM_Invoice
    {
        $invoice->invoiceId = "";
        $this->getDraftFile()->set_json(phore_dehydrate($invoice), true);

        $tenant = $this->config->getTenantById($this->customer->tenant_id);
        $layout = $this->brixEnv->rootDir
            ->withRelativePath($tenant->invoice_layout)
            ->assertFile()
            ->get_yaml(T_Layout::class);
        if (!$layout instanceof T_Layout) {
            throw new \UnexpectedValueException("Invalid invoice layout: " . $tenant->invoice_layout);
        }

        $facet = new InvoiceFacet(
            $layout,
            $this->customer,
            $invoice,
        );
        $facet->generate($this->getPreviewPdfFile());

        return $invoice;
    }

    private function getDraftDirectory(): PhoreDirectory
    {
        return $this->brixEnv->rootDir->withRelativePath(".cur-invoice")->assertDirectory(true);
    }

    private function getInvoiceSkillFile(): PhoreFile
    {
        $tenant = $this->config->getTenantById($this->customer->tenant_id);
        return $this->brixEnv->rootDir
            ->withRelativePath(dirname($tenant->invoice_email_tpl) . "/invoice-skill.md")
            ->asFile();
    }
}
