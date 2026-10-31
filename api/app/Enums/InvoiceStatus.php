<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Processing = 'processing';
    case Paid = 'paid';
    case Void = 'void';
}
