<?php

namespace Brix\CRM\Type\Customer;

use Lack\Invoice\Type\T_Customer;

class T_CRM_Customer extends T_Customer
{


    /**
     * The Salutation of the Person in E-Mails (Like: "Sehr geehrte Frau Müller" or "Sehr geehrter Herr Müller") depending
     * on Sex and including Titles like (Dr.). Always starts with "Sehr geehrte" or "Sehr geehrter". If not
     * determinable, use "Sehr geehrte Damen und Herren".
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
     * Mobile Phone Number
     *
     * @var string
     */
    public string $mobile = "";

    /**
     * The E-Mail Address
     *
     * @var string
     */
    public string $email = "";

    /**
     * A short name for the customer (one word - short, lowercase)
     * Kürze Vornamen ab, z.B. "Hans-Peter Müller" -> "hp-mueller"
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
