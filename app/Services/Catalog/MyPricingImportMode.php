<?php

namespace App\Services\Catalog;

enum MyPricingImportMode: string
{
    case Bootstrap = 'bootstrap';
    case Reconciliation = 'reconcile';
}
