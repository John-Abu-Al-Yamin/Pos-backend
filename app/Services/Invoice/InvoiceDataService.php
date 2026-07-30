<?php

namespace App\Services\Invoice;

use App\Models\PurchaseHeader;
use App\Models\SalesHeader;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

class InvoiceDataService
{
    public function forPurchase(int $id): array
    {
        $purchase = PurchaseHeader::with(['supplier', 'createdBy', 'items.product'])->find($id);

        if (! $purchase) {
            throw new ModelNotFoundException('Purchase transaction not found.');
        }

        if (! $purchase->isCompleted()) {
            throw new \DomainException('Only completed purchases can be printed.');
        }

        return [
            'type' => 'purchase',
            'title' => 'Purchase Invoice',
            'invoice' => [
                'number' => $purchase->purchaseHeader_number ?: sprintf('PO-%06d', $purchase->id),
                'transaction_id' => $purchase->id,
                'transaction_type' => PurchaseHeader::class,
                'transaction_date' => $this->dateToIso($purchase->completed_at ?: $purchase->created_at),
                'generated_at' => now()->toISOString(),
                'status' => $purchase->status,
                'notes' => $purchase->notes,
                'supplier_invoice_number' => $purchase->supplier_invoice_number,
                'created_by' => $purchase->createdBy?->name,
            ],
            'party' => [
                'type' => 'supplier',
                'label' => 'Supplier',
                'name' => $purchase->supplier?->name,
                'phone' => $purchase->supplier?->phone,
            ],
            'items' => $purchase->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'product_type' => $item->product?->type,
                'serial_number' => null,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_amount' => (float) $item->total_price,
            ])->values(),
            'totals' => [
                'subtotal' => (float) $purchase->total_amount,
                'discount_amount' => 0.0,
                'total_amount' => (float) $purchase->total_amount,
            ],
            'payment' => $this->untrackedPayment(),
        ];
    }

    public function forSale(int $id): array
    {
        $sale = SalesHeader::with([
            'customer',
            'createdBy',
            'items.product',
            'items.inventoryItem',
        ])->find($id);

        if (! $sale) {
            throw new ModelNotFoundException('Sales transaction not found.');
        }

        return [
            'type' => 'sale',
            'title' => 'Sales Invoice',
            'invoice' => [
                'number' => $sale->invoice_number,
                'transaction_id' => $sale->id,
                'transaction_type' => SalesHeader::class,
                'transaction_date' => $this->dateToIso($sale->created_at),
                'generated_at' => now()->toISOString(),
                'status' => 'completed',
                'notes' => $sale->notes,
                'created_by' => $sale->createdBy?->name,
            ],
            'party' => [
                'type' => 'customer',
                'label' => 'Customer',
                'name' => $sale->customer?->name,
                'phone' => $sale->customer?->phone,
            ],
            'items' => $sale->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'product_type' => $item->product?->type,
                'serial_number' => $item->inventoryItem?->internal_serial,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_amount' => (float) $item->total_price,
            ])->values(),
            'totals' => [
                'subtotal' => (float) $sale->subtotal,
                'discount_amount' => (float) $sale->discount_amount,
                'total_amount' => (float) $sale->total_amount,
            ],
            'payment' => $this->untrackedPayment(),
        ];
    }

    private function untrackedPayment(): array
    {
        return [
            'status' => 'not_recorded',
            'method' => null,
            'details' => 'Payment details are not recorded by the current transaction flow.',
            'paid_amount' => null,
            'remaining_balance' => null,
        ];
    }

    private function dateToIso(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toISOString() : null;
    }
}
