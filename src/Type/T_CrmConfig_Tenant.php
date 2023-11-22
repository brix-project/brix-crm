<?php

namespace Brix\CRM\Type;

class T_CrmConfig_Tenant
{

    /**
     * @var string
     */
    public string $id;

    /**
     * @var string
     */
    public string $pricelist;

    /**
     * @var string
     */
    public string $invoice_layout;

    /**
     * @var string
     */
    public string $invoice_email_tpl = "tpl/invoice_email.txt";

}
