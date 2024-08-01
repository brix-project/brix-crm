<?php

namespace Brix\CRM\Actions;

use Brix\Core\Broker\Broker;
use Brix\Core\Broker\BrokerActionInterface;
use Brix\Core\Broker\BrokerActionResponse;
use Brix\Core\Broker\Log\Logger;
use Brix\CRM\Actions\Types\CustomerContextImportRequest;

class CustomerContextImportAction extends AbstractBrixCrmAction
{

    public function getName(): string
    {
        return "crm.customer.context.import";
    }

    public function getDescription(): string
    {
        return "Create a context for a existing customer (import a existing context-id)";
    }

    public function getInputClass(): string
    {
        return CustomerContextImportRequest::class;
    }

    public function getOutputClass(): string
    {
        // TODO: Implement getOutputClass() method.
    }

    public function getStateClass(): string
    {
        // TODO: Implement getStateClass() method.
    }

    public function needsContext(): bool
    {
        return false;
    }

    public function performAction(object $input, Broker $broker, Logger $logger, ?string $contextId): BrokerActionResponse
    {
        assert ($input instanceof CustomerContextImportRequest);

        $customer = $this->customerManager->selectCustomer($input->importCustomerId)->customer;

        $newContextId = $customer->customerId . "-" . $customer->customerSlug;
        $newSubscriptionId = $customer->customerSlug . "-" . strtolower($customer->customerId);


        $broker->getContextStorageDriver()->createContext($newContextId, $customer->email. " " . $customer->address);
        $broker->switchContext($newContextId);
        $broker->selectContextId($newContextId);

        $ret = new BrokerActionResponse("success",  "Customer created (ID: $customer->customerId, Slug: $customer->customerSlug, Context: $newContextId)", [], $newContextId);

        $ret->addContextUpdate("crm.customer_data", "The billing address of the customer. Use as main address if nothing other is specified", $customer);
        $ret->addContextUpdate("subscription_id", "The subscription_id for this customer.", $newSubscriptionId);
        return $ret;
    }
}
