<?php

namespace Brix\CRM;

use Brix\CRM\Helper\AbstractCrmBrixCommand;
use Brix\MailSpool\MailSpoolFacet;
use Lack\MailSpool\OutgoingMail;
use Lack\MailSpool\OutgoingMailAttachment;
use Phore\Cli\Input\In;
use Phore\Cli\Output\Out;

class Offer extends AbstractCrmBrixCommand
{


    public function create(string $cid = null) {
        if ($cid === null)
            $cid = In::AskLine("Create Offer for Customer ID: ");
        $customer = $this->customerManager->selectCustomer($cid);
        $offerId = $customer->createNewOffer();
        echo "\nCreated new offer: $offerId\n";

        if (In::AskBool("Build offer?", true))
            $this->build($cid, $offerId, true);

    }


    public function build(string $cid, string $oId, bool $loop = false) {

        do {
            $customer = $this->customerManager->selectCustomer($cid);
            $invoice = $customer->getOffer($oId);

            $file = $customer->buildOffer($invoice);

            echo "\nCreated offer: $file\n";
            if ($loop === true) {
                if (In::AskBool("PDF created. Rebuild agein?", true) === false)
                    break;

            }
        } while ($loop === true);


    }




}
