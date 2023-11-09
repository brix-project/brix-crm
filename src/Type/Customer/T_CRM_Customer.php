<?php

namespace Brix\CRM\Type\Customer;

use Lack\Invoice\Type\T_Customer;

class T_CRM_Customer extends T_Customer
{

    /**
     * The Phone Number
     *
     * @var string
     */
    public $phone = "";

    /**
     * A short name for the customer (one word - short, lowercase)
     *
     * @var string
     */
    public $customerSlug = "";

    /**
     * List of assets (domain, webspace, email etc.) belonging to this customer
     *
     * @var string[]
     */
    public $assets = [];
}
