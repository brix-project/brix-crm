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


    public function create(array $argv = [], string $cid = null, string $previousInvoiceId = null) {
        if ($cid === null && count($argv) > 0) {
            $cid = trim(implode(" ", $argv));
        }
        if ($cid === null || trim($cid) === "")
            $cid = In::AskLine("Kundennummer: ");
        $customer = $this->customerManager->selectCustomerLazy($cid);
        $cid = $customer->customer->customerId;

        if ($previousInvoiceId === null)
            $previousInvoiceId = In::AskLine("Vorgaengerrechnung fuer Folgerechnung (leer = neue Rechnung aus Vorlage): ");

        if (trim($previousInvoiceId) !== "") {
            [$invoiceId, $previousInvoice, $invoice] = $customer->createFollowUpInvoice($previousInvoiceId);
            echo "\nCreated follow-up invoice: $invoiceId from $previousInvoiceId\n";
            echo "\nOld invoice items:\n";
            Out::Table($previousInvoice->items, false, ["title", "desc", "vat", "unit_price_net", "quantity"]);
        } else {
            $instruction = In::AskLine("Was soll gemacht werden? (leer = Standardangebot unveraendert uebernehmen): ");
            if (trim($instruction) !== "") {
                [$invoiceId, $templateInvoice, $invoice] = $customer->createAiInvoiceFromTemplate($instruction);
                echo "\nCreated AI adjusted invoice from standard offer/template: $invoiceId\n";
                echo "\nTemplate items:\n";
                Out::Table($templateInvoice->items, false, ["title", "desc", "vat", "unit_price_net", "quantity"]);
            } else {
                $invoiceId = $customer->createNewInvoice();
                $invoice = $customer->getInvoice($invoiceId);
                echo "\nCreated new invoice: $invoiceId\n";
            }
        }

        do {
            echo "\nAktuelle Rechnungsposten:\n";
            Out::Table($invoice->items, false, ["title", "desc", "vat", "unit_price_net", "quantity"]);
            $revisionInstruction = In::AskLine("Noch Aenderungen? (leer = alles OK): ");
            if (trim($revisionInstruction) === "")
                break;
            $invoice = $customer->reviseInvoiceItems($invoice, $revisionInstruction);
            echo "\nRechnungsposten ueberarbeitet.\n";
        } while (true);

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
