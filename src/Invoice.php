<?php

namespace Brix\CRM;

use Brix\CRM\Helper\AbstractCrmBrixCommand;

class Invoice extends AbstractCrmBrixCommand
{


    public function create(string $cid=null) {
        $customer = $this->customerManager->selectCustomer($cid);
        $customer->createNewInvoice();
    }


}
