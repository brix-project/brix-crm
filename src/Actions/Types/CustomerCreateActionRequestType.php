<?php

namespace Brix\CRM\Actions\Types;

use Brix\Core\Broker\Message\BrokerRequestTrait;
use Brix\CRM\Type\Customer\T_CRM_Customer;

class CustomerCreateActionRequestType extends T_CRM_Customer
{
    use BrokerRequestTrait;

}
