<?php

namespace Brix\CRM\Type\Overdue;

class OverdueTableEntity
{

    public string $invoiceId;

    public ?bool $isPaid = null;

    public ?string $customerId = null;

    public ?string $customerSlug = null;

    public ?float $totalAmount = null;

    public ?string $currency = null;

    public ?string $invoiceDate = null;

    public ?string $dueDate = null;

    public ?int $reminderLevel = null;

    public ?string $lastReminderDate = "0000-00-00";

}
