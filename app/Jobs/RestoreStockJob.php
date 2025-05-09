<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Models\MpesaTransaction;
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
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $sale = Sale::find($this->saleId);
        $mpesaTransaction = MpesaTransaction::where('sale_id', $this->saleId)->first();

        if ($sale && $mpesaTransaction && $mpesaTransaction->status === 'pending' && now()->subMinutes(1)->gt($sale->created_at)) {
            foreach ($this->reservedItems as $item) {
                ProductSizes::where('size_id', $item['size_id'])
                    ->increment('stock_quantity', $item['quantity']);
            }
            $mpesaTransaction->result_desc = 'Transaction timed out';
            $mpesaTransaction->save();
        }
    }
}
