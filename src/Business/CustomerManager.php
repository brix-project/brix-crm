<?php

namespace Brix\CRM\Business;

use Brix\CRM\Type\T_CrmConfig;
use Brix\Core\Type\BrixEnv;
use Brix\CRM\Type\Customer\T_CRM_Customer;
use Lack\Invoice\Type\T_Customer;
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
}
