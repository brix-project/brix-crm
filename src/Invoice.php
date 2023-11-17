<?php

namespace Brix\CRM;

use Brix\CRM\Helper\AbstractCrmBrixCommand;
use Phore\Cli\Input\In;

class Invoice extends AbstractCrmBrixCommand
{


    public function create(string $cid) {
        $customer = $this->customerManager->selectCustomer($cid);
        $invoiceId = $customer->createNewInvoice();
        echo "\nCreated new invoice: $invoiceId\n";


    }


    public function build(string $cid, string $invId, bool $loop = false) {

        do {
            $customer = $this->customerManager->selectCustomer($cid);
            $invoice = $customer->getInvoice($invId);

            $file = $customer->buildInvoice($invoice);

            echo "\nCreated invoice: $file\n";
            if ($loop === true) {
                if (In::AskBool("PDF created. Quit rebuild?", false))
                    return true;

            }
        } while ($loop === false);

    }


    public function spool (string $cid, string $invId) {
        $customer = $this->customerManager->selectCustomer($cid);
        $invoice = $customer->getInvoice($invId);
        $customer->spoolInvoice($invoice);
    }


}
