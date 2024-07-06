<?php


namespace Brix;




use Brix\Core\Broker\Broker;
use Brix\CRM\Customer;
use Brix\CRM\Invoice;
use Phore\Cli\CliDispatcher;

CliDispatcher::addClass(Customer::class);
CliDispatcher::addClass(Invoice::class);


Broker::getInstance()->registerAction("customer.create", function ($input) {
    return Customer::create($input);
});
