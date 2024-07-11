<?php
namespace Brix\CRM\Actions;
use Brix\Core\Broker\Broker;
use Brix\Core\Broker\BrokerActionInterface;
use Brix\Core\Broker\BrokerActionResponse;
use Brix\CRM\Actions\Types\CustomerCreateActionRequestType;
use Brix\CRM\Business\CustomerManager;
use Brix\CRM\Type\Customer\T_CRM_Customer;
use Brix\CRM\Type\T_CrmConfig;

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
        return CustomerCreateActionRequestType::class;
    }

    public function getOutputClass() : string
    {

    }

    public function getStateClass() : string
    {

    }


    public function performAction(object $input, Broker $broker): BrokerActionResponse
    {
        assert($input instanceof CustomerCreateActionRequestType);

        $config = $broker->brixEnv->brixConfig->get(
            "crm",
            T_CrmConfig::class,
            file_get_contents(__DIR__ . "/../config_tpl.yml")
        );
        $customerManager = new CustomerManager(
            $broker->brixEnv,
            $config,
            $broker->brixEnv->rootDir->withRelativePath(
                $config->customers_dir
            )->assertDirectory(true)
        );


        $input = phore_cast_object($input, T_CRM_Customer::class);

        $customerManager->createCustomer($input);
        $newContextId = $input->customerId . "-" . $input->customerSlug;

        $broker->contextStorageDriver->createContext($newContextId, "$input->address");
        $ret = new BrokerActionResponse();
        $ret->status = "ok";
        $ret->message = "Customer created (ID: $input->customerId, Slug: $input->customerSlug, Context: $newContextId)";

        return $ret;
    }

    public function needsContext(): bool
    {
        return false;
    }
}

