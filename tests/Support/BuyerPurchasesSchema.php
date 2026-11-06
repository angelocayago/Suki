<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/** Disposable UI/workflow fixtures only; never runs application migrations. */
class BuyerPurchasesSchema
{
    public static function create(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('SQLite memory fixtures required.');
        }
        foreach (['User', 'Order', 'OrderItem', 'Seller', 'SellerOrder', 'Shipment', 'DeliveryEvent',
            'Product', 'ProductVariant', 'ProductImage', 'Category', 'Payment', 'TransactionReview',
            'ReturnRefundRequest', 'Complaint', 'SellerWalletEntry', 'Payout', 'WishlistItem',
            'RiderCodRemittance', 'RiderShippingFeeEarning', 'LogisticsCodRemittanceItem', 'LogisticsCodRemittance'] as $name) {
            $class = 'App\\Models\\'.$name;
            $model = new $class();
            $table = $model->getTable();
            DB::statement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY)');
            $extra = match ($name) {
                'User' => ['remember_token'],
                'Product', 'ProductVariant' => ['deleted_at'],
                default => [],
            };
            foreach (array_unique([...$model->getFillable(), 'created_at', 'updated_at', ...$extra]) as $column) {
                if ($column === 'id') continue;
                $type = str_ends_with($column, '_id') || str_ends_with($column, '_minor')
                    || in_array($column, ['quantity', 'stock', 'is_active', 'is_suspended', 'attempts', 'cod_collected', 'position'], true) ? 'INTEGER' : 'TEXT';
                DB::statement('ALTER TABLE '.$table.' ADD COLUMN '.$column.' '.$type);
            }
        }
        DB::statement('CREATE TABLE notifications (id TEXT PRIMARY KEY, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT, created_at TEXT, updated_at TEXT)');
        ShopVoucherSchema::create();
    }
}
