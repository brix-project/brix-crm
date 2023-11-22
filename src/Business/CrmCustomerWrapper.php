<?php

namespace Brix\CRM\Business;

use Brix\Core\Type\BrixEnv;
use Brix\CRM\Type\Customer\T_CRM_Customer;
use Brix\CRM\Type\Invoice\T_CRM_Invoice;
use Brix\CRM\Type\T_CrmConfig;
use Lack\Invoice\InvoiceFacet;
use Lack\Invoice\Type\T_Layout;
use Phore\Cli\Output\Out;
use Phore\FileSystem\PhoreDirectory;

class CrmCustomerWrapper
{

    public function __construct(public readonly T_CRM_Customer $customer, public readonly BrixEnv $brixEnv, public readonly T_CrmConfig $config, public readonly PhoreDirectory $customerDir)
    {
    }



    public function createNewInvoice() : string
    {
        $invoice = new T_CRM_Invoice();

        $template = $this->customerDir->withRelativePath("invoice-tpl.yml");
        if ($template->isFile()) {
            $invoice = $template->assertFile()->get_yaml(T_CRM_Invoice::class);
        }

        $invoice->invoiceId = "X-" . $this->brixEnv->getState("crm")->increment("invoiceId");
        $invoice->invoiceDate = date("d.m.Y");



        $invoiceDir = $this->customerDir->withRelativePath("inv_new")->assertDirectory(true);
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

}
