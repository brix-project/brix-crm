<?php
namespace Brix\CRM\Actions;
use Brix\CRM\Actions\Types\CustomerCreateActionRequestType;

class CustomerCreateAction implements BrokerActionInterface
{
    public function getName() : string
    {
        return "customer.create";
    }

    public function getDescription() : string
    {
        return "Create a new customer and return the customer id and subscription id (including the slug). The tenant id is required.";
    }

    public function getInputClass() : string
    {

    }

    public function getOutputClass() : string
    {

    }

    public function getStateClass() : string
    {

    }

    public function performAction(CustomerCreateActionRequestType $input) : BrokerActionResponse
    {

    }
}

