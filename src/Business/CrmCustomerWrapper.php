<?php

namespace Brix\CRM\Business;

use Brix\Core\Type\BrixEnv;
use Brix\CRM\Type\Customer\T_CRM_Customer;
use Brix\CRM\Type\Invoice\T_CRM_Invoice;
use Brix\CRM\Type\T_CrmConfig;
use Phore\Cli\Output\Out;
use Phore\FileSystem\PhoreDirectory;

class CrmCustomerWrapper
{

    public function __construct(public readonly T_CRM_Customer $customer, public readonly BrixEnv $brixEnv, public readonly T_CrmConfig $config, public readonly PhoreDirectory $customerDir)
    {
    }



    public function createNewInvoice()
    {
        $invoice = new T_CRM_Invoice();
        $invoice->invoiceId = "X-" . $this->brixEnv->getState("crm")->increment("invoiceId");
        $invoice->invoiceDate = date("Y-m-d");

        $invoiceDir = $this->customerDir->withRelativePath("inv_new")->assertDirectory(true);
        $invFile = $invoiceDir->withFileName($invoice->invoiceId . ".yml");
        $invFile->set_yaml((array)$invoice);

        echo "Created new Invoice: " . $invoice->invoiceId . "\n";


    }

}
