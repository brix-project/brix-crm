<?php

namespace Brix\CRM\Type\Invoice;

use Lack\Invoice\Type\T_Invoice;

class T_CRM_Invoice extends T_Invoice
{

    /**
     * @var T_CRM_InvoiceItem[]
     */
    public array $items = [];


}
