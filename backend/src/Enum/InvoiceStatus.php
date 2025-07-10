<?php

namespace App\Enum;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
}

