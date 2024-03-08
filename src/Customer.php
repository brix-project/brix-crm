<?php

namespace Brix\CRM;

use Brix\CRM\Business\CustomerManager;
use Brix\CRM\Helper\AbstractCrmBrixCommand;
use Brix\CRM\Type\Customer\T_CRM_Customer;
use Phore\Cli\CLIntputHandler;
use Phore\Cli\Output\Out;

class Customer extends AbstractCrmBrixCommand
{


    public function __construct()
    {
        parent::__construct();

    }


    public function create()
    {
        $cliInput = new CLIntputHandler();

        $tenantId = $cliInput->askLine("Enter tenant id: ");
        $tenant = $this->config->getTenantById($tenantId);
        if ($tenant === null)
            throw new \InvalidArgumentException("Tenant with id '$tenantId' not found.");

        $customerData = $cliInput->askMultiLine("Enter customer data:");

        $newCustomer = $this->brixEnv->getOpenAiQuickFacet()->promptDataStruct($customerData, T_CRM_Customer::class);
        assert($newCustomer instanceof T_CRM_Customer);
        $newCustomer->tenant_id = $tenant->id;


        echo phore_json_encode($newCustomer, JSON_PRETTY_PRINT);
        $cliInput->askBool("Create customer?", true) || die ("Aborted.");

        $this->customerManager->createCustomer($newCustomer);




    }


    public function search(array $argv) {
        $customers = $this->customerManager->listCustomers($argv[0] ?? "*");
        Out::Table($customers);
    }


    public function list() {
        $customers = $this->customerManager->listCustomers();
        Out::Table($customers);
    }

}
