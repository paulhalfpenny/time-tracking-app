<?php

namespace App\Enums;

enum BillingType: string
{
    case Hourly = 'hourly';
    case FixedFee = 'fixed_fee';
    case NonBillable = 'non_billable';
}
