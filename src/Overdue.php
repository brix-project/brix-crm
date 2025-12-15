<?php

namespace Brix\CRM;

use Brix\CRM\Business\OverdueManager;
use Brix\CRM\Helper\AbstractCrmBrixCommand;
use Brix\MailSpool\MailSpoolFacet;
use Lack\MailSpool\OutgoingMail;
use Lack\MailSpool\OutgoingMailAttachment;
use Phore\Cli\Input\In;
use Phore\Cli\Output\Out;

class Overdue extends AbstractCrmBrixCommand
{

    protected  function getManager(): OverdueManager {
        return new OverdueManager($this->brixEnv, $this->config, $this->customerManager->customersDir);
    }

   public function listOverdue() {
       $manager = $this->getManager();
       $data = $manager->listOverdueEntries();
       Out::Table($data["data"]);
       Out::TextInfo("Total overdue entries: " . count($data["data"]));
       Out::TextInfo("Total overdue amount: " . $data["total"]);
   }


   public function send(string $invId) {
       $manager = $this->getManager();

       $manager->sendDueMail($invId);
       Out::TextSuccess("Overdue mail for invoice $invId spooled.");
   }

   public function sendAll() {
       $manager = $this->getManager();

       $manager->buildDueMails();
       Out::TextSuccess("Total overdue mails spooled.");
       if (In::AskBool("Send all spooled email?", true))
           MailSpoolFacet::getInstance()->sendMail();
   }

}
