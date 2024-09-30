<?php

namespace Brix\CRM;

use Brix\CRM\Helper\AbstractCrmBrixCommand;
use Brix\MailSpool\MailSpoolFacet;
use Lack\MailSpool\OutgoingMail;
use Lack\MailSpool\OutgoingMailAttachment;
use Phore\Cli\Input\In;
use Phore\Cli\Output\Out;

class Invoice extends AbstractCrmBrixCommand
{


    public function create(string $cid = null) {
        if ($cid === null)
            $cid = In::AskLine("Create Invoice for Customer ID: ");
        $customer = $this->customerManager->selectCustomer($cid);
        $invoiceId = $customer->createNewInvoice();
        echo "\nCreated new invoice: $invoiceId\n";

        if (In::AskBool("Build invoice?", true))
            $this->build($cid, $invoiceId, true);

    }


    public function build(string $cid, string $invId, bool $loop = false) {

        do {
            $customer = $this->customerManager->selectCustomer($cid);
            $invoice = $customer->getInvoice($invId);

            $file = $customer->buildInvoice($invoice);

            echo "\nCreated invoice: $file\n";
            if ($loop === true) {
                if (In::AskBool("PDF created. Rebuild agein?", true) === false)
                    break;

            }
        } while ($loop === true);

        if (In::AskBool("Spool invoice?", true))
            $this->spool($cid, $invId, $file);

    }


    public function export_invoices() {
        $env = $this->brixEnv;

        $exportDir = phore_dir($env->rootDir)->join($this->config->invoice_export_dir)->asDirectory();
        if ( ! is_dir($exportDir))
            phore_dir($exportDir)->mkdir();

        $customersDir = phore_dir($env->rootDir)->join($this->config->customers_dir)->asDirectory();
        foreach ($customersDir->genWalk("*.pdf", true) as $file) {
            if ( ! $file->isFile())
                continue;
            if(!preg_match ("/(X-[0-9]+)/", $file->getBasename(), $matches)) {
                Out::TextDanger("Skipping file: " . $file->getBasename());
                continue;
            }

            $ymlFile = $file->getDirname()->withFileName($matches[1]. ".yml");
            $invYear = date("Y", strtotime($ymlFile->get_yaml()["invoiceDate"]));


            Out::TextInfo("Exporting: " . $file . " to $exportDir/" . $file->getBasename());
            $targetDir = $exportDir->join($invYear)->assertDirectory(true);
            $file->asFile()->copyTo($targetDir->join($file->getBasename()));
        }
    }

    public function spool (string $cid, string $invId, string $file) {
        $customer = $this->customerManager->selectCustomer($cid);
        $tenant = $this->config->getTenantById($customer->customer->tenant_id);
        $invoice = $customer->getInvoice($invId);

        $mailspool = MailSpoolFacet::getInstance();


        $invTemplate = $this->brixEnv->rootDir->withRelativePath($tenant->invoice_email_tpl)->assertFile();


        $mail = OutgoingMail::FromTemplate($invTemplate, [
            "customer" => (array)$customer->customer,
            "invoice" => (array)$invoice,
            "tenant" => (array)$tenant
        ]);
        $file = phore_file($file);
        $mail->attachments[] = new OutgoingMailAttachment($file->get_contents(), $file->getBasename());

        $mailId = $mailspool->spoolMail($mail);
        if (In::AskBool("Send spooled email?", true))
            $mailspool->sendMail($mailId);
    }


}
