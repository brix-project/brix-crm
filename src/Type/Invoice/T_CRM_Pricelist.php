<?php

namespace Brix\CRM\Type\Invoice;

use Lack\Invoice\Type\T_Invoice_Item;

class T_CRM_Pricelist
{

    public string $version;

    /**
     * @var T_CRM_InvoiceItem[]
     */
    public $items = [];
}
