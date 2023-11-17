<?php

namespace Brix\CRM\Type;

use Brix\Core\Type\BrixEnv;

class T_CrmConfig
{

    public string $customers_dir = "./customers";

    public string $template_dir = "./tpl";


    /**
     * @var T_CrmConfig_Tenant[]
     */
    public array $tenants = [];


    public function getTenantById(string $id) : ?T_CrmConfig_Tenant {
        foreach($this->tenants as $tenant) {
            if($tenant->id == $id) {
                return $tenant;
            }
        }
        return null;
    }

}
