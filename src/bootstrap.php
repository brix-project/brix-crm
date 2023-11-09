<?php


namespace Brix;




use Brix\CRM\Customer;
use Brix\CRM\Invoice;
use Phore\Cli\CliDispatcher;

CliDispatcher::addClass(Customer::class);
CliDispatcher::addClass(Invoice::class);
