<?php

namespace Brix\CRM\Business;

use Brix\Core\Type\BrixEnv;
use Brix\CRM\Type\Overdue\OverdueTable;
use Brix\CRM\Type\Overdue\OverdueTableEntity;
use Brix\CRM\Type\T_CrmConfig;
use Brix\MailSpool\MailSpoolFacet;
use Lack\MailSpool\OutgoingMail;
use Lack\MailSpool\OutgoingMailAttachment;
use Phore\Cli\Output\Out;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;

class OverdueManager
{
    public function __construct(public BrixEnv $brixEnv, public T_CrmConfig $config,  public PhoreDirectory $customersDir)
    {
    }



    private function getOverDueTable(): OverdueTable
    {
        $file = $this->brixEnv->rootDir->withRelativePath($this->config->overdue_table)->assertFile(true);
        return new OverdueTable($file);
    }

    private function getCustomerManager() : CustomerManager {
        return new CustomerManager($this->brixEnv, $this->config, $this->customersDir);
    }


    public function updateOverdueEntries() {
        $overdueTable = $this->getOverDueTable();
        $customerManager = $this->getCustomerManager();
        $overdueTable->save();
        foreach ($overdueTable->getData() as $overdueEntry) {
            assert($overdueEntry instanceof OverdueTableEntity);
            $customerWrapper = $customerManager->getCustomerByInvoiceId($overdueEntry->invoiceId);

            $invoice = $customerWrapper->getInvoice($overdueEntry->invoiceId);

            $overdueEntry->customerId = $customerWrapper->customer->customerId;
            $overdueEntry->customerSlug = $customerWrapper->customer->customerSlug;
            $overdueEntry->totalAmount = $invoice->getTotalAmount();
            $overdueEntry->currency = "EUR";
            $overdueEntry->invoiceDate = $invoice->invoiceDate;
            $overdueEntry->dueDate = $invoice->getDueDateRaw();
            if ($overdueEntry->lastReminderDate == "")
                $overdueEntry->lastReminderDate = "0000-00-00";


        }
        $overdueTable->save();
    }


    public function listOverdueEntries() {
        $this->updateOverdueEntries();
        $overdueTable = $this->getOverDueTable();
        $total = 0.0;

        foreach ($overdueTable->getData() as $overdueEntry) {
            assert($overdueEntry instanceof OverdueTableEntity);
            $total += $overdueEntry->totalAmount;
        }

        return ["data" =>$overdueTable->getData(), "total" => $total];
    }


    public function sendDueMail(string $invoiceId) {
        $this->updateOverdueEntries();
        $overdueTable = $this->getOverDueTable();
        $customerManager = $this->getCustomerManager();

        $overdueEntry = $overdueTable->getEntryByInvoiceId($invoiceId);
        assert($overdueEntry instanceof OverdueTableEntity);

        if ($overdueEntry->isPaid === true) {
            throw new \InvalidArgumentException("Invoice is already paid.");
        }

        $customerWrapper = $customerManager->getCustomerByInvoiceId($overdueEntry->invoiceId);
        $invoice = $customerWrapper->getInvoice($overdueEntry->invoiceId);

        $tenant = $this->config->getTenantById($customerWrapper->customer->tenant_id);
        $mailTemplate = $this->brixEnv->rootDir->withRelativePath($tenant->due_reminder_email_tpl)->assertFile();

        $mail = OutgoingMail::FromTemplate($mailTemplate, [
            "invoice" => (array)$overdueEntry,
            "customer" => (array)$customerWrapper->customer,
            "amount" => number_format($overdueEntry->totalAmount, 2, ",", "."),
        ]);
        $file = $customerWrapper->getInvoicePdfFile($invoice->invoiceId);
        $mail->attachments[] = new OutgoingMailAttachment($file->get_contents(), $file->getBasename());

        MailSpoolFacet::getInstance()->spoolMail($mail);

        $overdueEntry->lastReminderDate = date("Y-m-d");
        $overdueTable->save();
    }


    public function buildDueMails() {
        $this->updateOverdueEntries();
        $overdueTable = $this->getOverDueTable();
        $customerManager = $this->getCustomerManager();

        foreach ($overdueTable->getData() as $overdueEntry) {
            assert($overdueEntry instanceof OverdueTableEntity);

            if ($overdueEntry->isPaid === true) {
                Out::TextInfo("Skipping invoice " . $overdueEntry->invoiceId . ": already paid." );
                continue;
            }

            try {
                $this->sendDueMail($overdueEntry->invoiceId);
                Out::TextSuccess("Due mail sent for invoice " . $overdueEntry->invoiceId );
            } catch (\InvalidArgumentException $e) {
                Out::TextDanger("Skipping invoice " . $overdueEntry->invoiceId . ": " . $e->getMessage() );
            }
        }


    }




}
