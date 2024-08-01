<?php

namespace Brix\CRM\Actions\Types;

use Brix\Core\Broker\Message\BrokerRequestTrait;

class CustomerContextImportRequest
{

    use BrokerRequestTrait;

    /**
     * The customer-id to import (format K[0-9])
     *
     * Take this value from subscription id or context Id provided in JobDescription (K134-xyz or xyz-k123 => K123)
     *
     * @var string
     */
    public string $importCustomerId = "";

}
