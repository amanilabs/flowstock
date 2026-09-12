<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Fulfilled = 'fulfilled';
}
