<?php

namespace Brix\CRM\Business;

use Brix\Core\Type\BrixEnv;
use Brix\CRM\Type\Customer\T_CRM_Customer;
use Brix\CRM\Type\T_CrmConfig;
use http\Exception\InvalidArgumentException;
use Phore\FileSystem\PhoreDirectory;

class CustomerManager
{

    public function __construct(public BrixEnv $brixEnv, public T_CrmConfig $config,  public PhoreDirectory $customersDir)
    {
    }

    public function createCustomer(T_CRM_Customer $customer)
    {
        if ($customer->customerId === null) {
            $customer->customerId = "K" . $this->brixEnv->getState("crm")->increment("customerId");
        }

        $customerDir = $this->customersDir->withRelativePath($customer->customerId . "-" . $customer->customerSlug)->assertDirectory(true);
        $customerFile = $customerDir->withFileName("customer.yml")->set_yaml((array)$customer);
    }

    /**
     * @param string $filter
     * @return T_CRM_Customer[]
     */
    public function listCustomers(string $filter = "*") {
        $customers = [];
        foreach ($this->customersDir->assertDirectory()->genWalk() as $dir) {

            $customerFile = $dir->withFileName("customer.yml");
            if ( ! $customerFile->isFile())
                continue;

            $customer = $customerFile->get_yaml(T_CRM_Customer::class);

            if ($filter !== "*") {
                $match = false;
                foreach ($customer as $field) {
                    if (stripos($field, $filter) !== false) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    continue;
                }
            }

            $customers[] = $customer;
        }
        return $customers;
    }

    public function selectCustomer(string $customerId) : CrmCustomerWrapper {
        $customers = [];
        foreach ($this->customersDir->assertDirectory()->genWalk() as $dir) {

            $customerFile = $dir->withFileName("customer.yml");
            if ( ! $customerFile->isFile())
                continue;

            $customer = $customerFile->get_yaml(T_CRM_Customer::class);
            assert($customer instanceof T_CRM_Customer);

            if ($customer->customerId !== $customerId)
                continue;

            $customers[] = $customer;
        }
        if (count($customers) > 1)
            throw new InvalidArgumentException("Multiple customers found for id '$customerId'");
        if (count($customers) === 0)
            throw new InvalidArgumentException("No customer found for id '$customerId'");
        return new CrmCustomerWrapper($customers[0], $this->brixEnv, $this->config, $this->customersDir->withRelativePath($customers[0]->customerId . "-" . $customers[0]->customerSlug)->assertDirectory(false));
    }

}
