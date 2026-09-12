<?php

namespace App\Events;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockDetected implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly Warehouse $warehouse,
        public readonly int $tenantId,
        public readonly int $oldQuantity,
        public readonly int $newQuantity,
    ) {}
}
