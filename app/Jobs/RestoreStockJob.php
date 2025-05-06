<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Models\ProductSizes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RestoreStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $saleId;
    protected $reservedItems;

    /**
     * Create a new job instance.
     */
    public function __construct(string $saleId, array $reservedItems)
    {
        $this->saleId = $saleId;
        $this->reservedItems = $reservedItems;

        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $sale = Sale::find($this->saleId);
        if ($sale && $sale->status === 'pending' && now()->subMinutes(5)->gt($sale->created_at)) {
            foreach ($this->reservedItems as $item) {
                ProductSizes::where('size_id', $item['size_id'])
                    ->increment('stock_quantity', $item['quantity']);
            }
            $sale->saleItems()->delete();
            $sale->delete();
        }
        //
    }
}
