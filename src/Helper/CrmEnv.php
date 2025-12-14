<?php

namespace Brix\CRM\Helper;

use Brix\CRM\Type\T_CrmConfig;
use Brix\Core\Type\BrixEnv;

class CrmEnv
{

    public function __construct(public T_CrmConfig $config, public BrixEnv $brixEnv)
    {
    }




}
