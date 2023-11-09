<?php

namespace Brix\CRM;

use Brix\CRM\Business\CustomerManager;
use Brix\CRM\Helper\AbstractCrmBrixCommand;
use Brix\CRM\Type\Customer\T_CRM_Customer;
use Phore\Cli\CLIntputHandler;
use Phore\Cli\Output\Out;

class Customer extends AbstractCrmBrixCommand
{

    public CustomerManager $customerManager;

    public function __construct()
    {
        parent::__construct();
        $this->customerManager = new CustomerManager(
            $this->brixEnv,
            $this->config,
            $this->brixEnv->rootDir->withRelativePath(
                $this->config->customers_dir
            )->assertDirectory()
        );
    }


    public function create()
    {
        $cliInput = new CLIntputHandler();

        $customerData = $cliInput->askMultiLine("Enter customer data:");

        $newCustomer = $this->brixEnv->getOpenAiQuickFacet()->promptDataStruct($customerData, T_CRM_Customer::class);
        assert($newCustomer instanceof T_CRM_Customer);

        echo phore_json_encode($newCustomer, JSON_PRETTY_PRINT);
        $cliInput->askBool("Create customer?", false) || die ("Aborted.");

        $this->customerManager->createCustomer($newCustomer);

    }

    public function list() {
        $customers = $this->customerManager->listCustomers();
        Out::Table($customers);
    }

}
