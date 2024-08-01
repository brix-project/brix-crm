<?php

namespace Brix\CRM\Business;

use Brix\Core\Type\BrixEnv;
use Brix\CRM\Type\Customer\T_CRM_Customer;
use Brix\CRM\Type\T_CrmConfig;
use Brix\CRM\Type\T_CrmConfig_Tenant;
use http\Exception\InvalidArgumentException;
use Phore\Cli\Input\In;
use Phore\FileSystem\PhoreDirectory;

class CustomerManager
{

    public function __construct(public BrixEnv $brixEnv, public T_CrmConfig $config,  public PhoreDirectory $customersDir)
    {
    }

    public function createCustomer(T_CRM_Customer $customer)
    {

        $customer->customerId = "K" . $this->brixEnv->getState("crm")->increment("customerId");

        if ($customer->customerSlug === null || $customer->customerSlug === "") {
            throw new \InvalidArgumentException("customerSlug must be set");
        }
        if ($customer->tenant_id === null || $customer->tenant_id === "") {
            throw new \InvalidArgumentException("tenant_id must be set");
        }
        if ($customer->email === null || $customer->email === "") {
            throw new \InvalidArgumentException("email must be set");
        }


        $customerDir = $this->customersDir->withRelativePath($customer->customerId . "-" . $customer->customerSlug)->assertDirectory(true);

        // Check if Template with tenant exisists
        $templateDir = $this->brixEnv->rootDir->withRelativePath($this->config->template_dir . "/". $customer->tenant_id);
        if ($templateDir->exists()) {
            $templateDir->assertDirectory()->copyTo($customerDir);
        }

        $customerFile = $customerDir->withFileName("customer.yml")->set_yaml((array)$customer);
    }



    public function repairCustomers() {
        foreach ($this->customersDir->assertDirectory()->genWalk() as $dir) {
            $customerFile = $dir->getFilename();
            if ( ! preg_match("/^(K[0-9]+)-(.*)$/", $customerFile, $matches)) {
                throw new \InvalidArgumentException("cannot parse customer slug of path: '$customerFile'");
            }
            $customerId = $matches[1];
            $customerSlug = $matches[2];

            echo "\nChecking $customerId...";

            $customerFile = $dir->withFileName("customer.yml");
            if ( ! $customerFile->isFile())
                continue;

            try {
                $customer = $customerFile->get_yaml(T_CRM_Customer::class);
                if ($customer->customerId !== $customerId) {
                    echo "Fixing customerId from '{$customer->customerId}' to '$customerId'...";
                    $customer->customerId = $customerId;
                    $customerFile->set_yaml((array)$customer);
                }
                if ($customer->customerSlug !== $customerSlug) {
                    echo "Fixing customerSlug from '{$customer->customerSlug}' to '$customerSlug'...";
                    $customer->customerSlug = $customerSlug;
                    $customerFile->set_yaml((array)$customer);
                }
                if (empty($customer->email))
                    echo " - email missing";
                if (empty($customer->tenant_id))
                    echo " - tenant_id missing";
                echo "OK";
            } catch (\Exception $e) {
                echo "Error: " . $e->getMessage();
                $customer = new T_CRM_Customer();
                foreach ($data = $customerFile->get_yaml() as $key => $value) {
                    $customer->$key = $value;
                }

                $customer->customerId = $customerId;
                $customer->customerSlug = $customerSlug;
                if (In::AskBool("Fix?", false)) {
                    $customerFile->set_yaml((array)$customer);
                    echo "FIXED!";
                    continue;
                }
                continue;
            }
        }
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
                    if (is_array($field))
                        $field = implode(" ", $field);
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
            throw new \InvalidArgumentException("Multiple customers found for id '$customerId'");
        if (count($customers) === 0)
            throw new \InvalidArgumentException("No customer found for id '$customerId'");
        return new CrmCustomerWrapper($customers[0], $this->brixEnv, $this->config, $this->customersDir->withRelativePath($customers[0]->customerId . "-" . $customers[0]->customerSlug)->assertDirectory(false));
    }

}
