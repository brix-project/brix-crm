<?php

namespace Brix\CRM;

use Brix\CRM\Helper\AbstractCrmBrixCommand;
use Phore\Cli\Input\In;

class Invoice extends AbstractCrmBrixCommand
{


    public function create(string $cid = null) {
        if ($cid === null)
            $cid = In::AskLine("Create Invoice for Customer ID: ");
        $customer = $this->customerManager->selectCustomer($cid);
        $invoiceId = $customer->createNewInvoice();
        echo "\nCreated new invoice: $invoiceId\n";

        if (In::AskBool("Build invoice?", true))
            $this->build($cid, $invoiceId, true);

    }


    public function build(string $cid, string $invId, bool $loop = false) {

        do {
            $customer = $this->customerManager->selectCustomer($cid);
            $invoice = $customer->getInvoice($invId);

            $file = $customer->buildInvoice($invoice);

            echo "\nCreated invoice: $file\n";
            if ($loop === true) {
                if (In::AskBool("PDF created. Rebuild agein?", true) === false)
                    break;

            }
        } while ($loop === true);

        if (In::AskBool("Spool invoice?", true))
            $this->spool($cid, $invId);

    }


    public function spool (string $cid, string $invId) {
        $customer = $this->customerManager->selectCustomer($cid);
        $invoice = $customer->getInvoice($invId);
        $customer->spoolInvoice($invoice);
    }


}
