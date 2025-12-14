<?php

namespace Brix\CRM\Type\Invoice;

use Lack\Invoice\Type\T_Invoice_Item;

class T_CRM_InvoiceItem extends T_Invoice_Item
{


    public function getTotalPrice(): float
    {
        return $this->quantity * $this->unit_price_net * (1 + $this->vat / 100);
    }
}
