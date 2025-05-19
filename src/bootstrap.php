<?php


namespace Brix;




use Brix\Core\Broker\Broker;
use Brix\CRM\Actions\CustomerContextImportAction;
use Brix\CRM\Actions\CustomerCreateAction;
use Brix\CRM\Customer;
use Brix\CRM\Invoice;
use Brix\CRM\Offer;
use Phore\Cli\CliDispatcher;

CliDispatcher::addClass(Customer::class);
CliDispatcher::addClass(Invoice::class);
CliDispatcher::addClass(Offer::class);


Broker::getInstance()->registerAction(new CustomerCreateAction());
Broker::getInstance()->registerAction(new CustomerContextImportAction());
