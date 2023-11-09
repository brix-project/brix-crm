<?php

namespace Brix\CRM\Helper;

use Brix\Core\Type\BrixEnv;
use Brix\CRM\Type\T_CrmConfig;
use Brix\Core\AbstractBrixCommand;

class AbstractCrmBrixCommand extends AbstractBrixCommand
{

    public T_CrmConfig $config;
    public function __construct()
    {
        parent::__construct();
        $this->config = $this->brixEnv->brixConfig->get(
            "crm",
            T_CrmConfig::class,
            file_get_contents(__DIR__ . "/../config_tpl.yml")
        );
    }
}
