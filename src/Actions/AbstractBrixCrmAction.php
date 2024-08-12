<?php

namespace Brix\CRM\Actions;

use Brix\Core\BrixEnvFactorySingleton;
use Brix\Core\Broker\AbstractBrokerAction;
use Brix\Core\Broker\BrokerActionInterface;
use Brix\Core\Type\BrixEnv;
use Brix\CRM\Business\CustomerManager;
use Brix\CRM\Type\T_CrmConfig;
use tests\Http\Message\MultipartStream\FunctionTest;

abstract class AbstractBrixCrmAction extends AbstractBrokerAction
{

    protected T_CrmConfig $config;
    protected CustomerManager $customerManager;

    public function __construct()
    {
        $brixEnv = BrixEnvFactorySingleton::getInstance()->getEnv();
        $this->config = $brixEnv->brixConfig->get(
            "crm",
            T_CrmConfig::class,
            file_get_contents(__DIR__ . "/../config_tpl.yml")
        );
        $this->customerManager = new CustomerManager(
            $brixEnv,
            $this->config,
            $brixEnv->rootDir->withRelativePath(
                $this->config->customers_dir
            )->assertDirectory(true)
        );

    }
}
