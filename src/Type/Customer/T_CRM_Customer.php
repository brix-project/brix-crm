<?php

namespace Brix\CRM\Type\Customer;

use Lack\Invoice\Type\T_Customer;

class T_CRM_Customer extends T_Customer
{


    /**
     * The Saluation of the Persion (Like: "Sehr geehrte Frau Müller" or "Sehr geehrter Herr Müller") depending
     * on Sex and including Titles like (Dr.)
     *
     * @var string
     */
    public string $salutation = "Sehr geehrte Damen und Herren";

    /**
     * @var string
     */
    public string $tenant_id = "";

    /**
     * The Phone Number
     *
     * @var string
     */
    public string $phone = "";

    /**
     * A short name for the customer (one word - short, lowercase)
     *
     * @var string
     */
    public string $customerSlug = "";

    /**
     * List of assets (domain, webspace, email etc.) belonging to this customer
     *
     * @var string[]
     */
    public array $assets = [];
}
