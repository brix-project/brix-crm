<?php

namespace Brix\CRM\Type\Overdue;

use Brix\CRM\Helper\CSVEntityTable;

class OverdueTable extends CSVEntityTable
{

    public function __construct(string $filePath)
    {
        parent::__construct(OverdueTableEntity::class, $filePath);
    }

    public function getEntryByInvoiceId(string $invoiceId): OverdueTableEntity
    {

        return $this->select(['invoiceId' => $invoiceId]) ?? throw new \Exception("Overdue entry not found for invoiceId: $invoiceId");
    }

}
