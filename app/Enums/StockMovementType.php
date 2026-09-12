<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Received = 'received';
    case Sold = 'sold';
    case Adjustment = 'adjustment';
    case Damaged = 'damaged';
    case Returned = 'returned';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
}
