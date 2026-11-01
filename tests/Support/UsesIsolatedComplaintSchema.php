<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/** Runs existing complaint regression cases without executing application migrations. */
trait UsesIsolatedComplaintSchema
{
    public function refreshDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $directory = getenv('SELLER_COMPLAINTS_CHECK_DIR');
        $this->assertNotEmpty($directory, 'An explicit disposable test directory is required.');
        $this->assertStringStartsWith(realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR, realpath($directory));
        $this->app->useStoragePath($directory.'/storage-'.bin2hex(random_bytes(6)));

        $tables = [
            'users' => 'name TEXT, first_name TEXT, last_name TEXT, middle_initial TEXT, email TEXT, phone TEXT, password TEXT, remember_token TEXT, role TEXT, status TEXT, is_suspended INTEGER DEFAULT 0, verification_required_at TEXT, email_verified_at TEXT, phone_verified_at TEXT',
            'roles' => 'name TEXT', 'role_user' => 'role_id INTEGER, user_id INTEGER',
            'sellers' => 'user_id INTEGER, name TEXT, slug TEXT, status TEXT, logo_path TEXT, category_id INTEGER, commission_bps INTEGER DEFAULT 0',
            'categories' => 'name TEXT, slug TEXT, is_active INTEGER',
            'products' => 'seller_id INTEGER, category_id INTEGER, name TEXT, slug TEXT, description TEXT, is_active INTEGER, moderation_status TEXT, deleted_at TEXT',
            'product_variants' => 'product_id INTEGER, sku TEXT, name TEXT, price_minor INTEGER, stock INTEGER, is_active INTEGER',
            'orders' => 'order_number TEXT, reference TEXT, buyer_id INTEGER, seller_id INTEGER, status TEXT, payment_method TEXT, payment_status TEXT, subtotal REAL, shipping_fee REAL, discount_amount REAL, total_amount REAL, total_minor INTEGER, recipient_name TEXT, recipient_phone TEXT, province TEXT, municipality TEXT, barangay TEXT, street_address TEXT, placed_at TEXT, delivered_at TEXT',
            'seller_orders' => 'seller_id INTEGER, order_id INTEGER, logistics_provider_id INTEGER, subtotal_minor INTEGER, shipping_fee_minor INTEGER, commission_minor INTEGER, status TEXT, workflow_status TEXT, delivered_at TEXT',
            'order_items' => 'order_id INTEGER, seller_id INTEGER, seller_order_id INTEGER, product_id INTEGER, product_variant_id INTEGER, product_slug TEXT, product_name TEXT, unit_price REAL, unit_price_minor INTEGER, quantity INTEGER, line_total REAL',
            'logistics_providers' => 'user_id INTEGER, name TEXT, slug TEXT, status TEXT',
            'riders' => 'user_id INTEGER, logistics_provider_id INTEGER, vehicle_type TEXT, plate_no TEXT, is_active INTEGER, approved_at TEXT',
            'shipments' => 'seller_order_id INTEGER, logistics_provider_id INTEGER, pickup_rider_id INTEGER, rider_id INTEGER, tracking_code TEXT, status TEXT, workflow_status TEXT, fee_minor INTEGER, cod_amount_minor INTEGER, cod_collected INTEGER, attempts INTEGER',
            'complaints' => 'reference TEXT UNIQUE, complainant_id INTEGER, complainant_role TEXT, order_id INTEGER, seller_order_id INTEGER, shipment_id INTEGER, buyer_id INTEGER, seller_id INTEGER, product_id INTEGER, rider_id INTEGER, rider_role TEXT, logistics_provider_id INTEGER, assigned_to INTEGER, assigned_at TEXT, category TEXT, status TEXT, waiting_on TEXT, subject TEXT, description TEXT, context_snapshot TEXT, resolved_by INTEGER, resolved_at TEXT, resolution_notes TEXT',
            'complaint_events' => 'complaint_id INTEGER, actor_id INTEGER, action TEXT, previous_status TEXT, new_status TEXT, previous_assignee_id INTEGER, new_assignee_id INTEGER, waiting_on TEXT, notes TEXT, metadata TEXT',
            'complaint_evidence' => 'complaint_id INTEGER, uploaded_by INTEGER, disk TEXT, path TEXT, original_name TEXT, mime_type TEXT, size INTEGER, checksum TEXT, description TEXT',
            'seller_wallet_entries' => 'seller_id INTEGER, seller_order_id INTEGER, payout_id INTEGER, type TEXT, status TEXT, amount_minor INTEGER, reference TEXT, description TEXT',
            'return_refund_requests' => 'seller_order_id INTEGER, status TEXT',
            'notifications' => 'type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT',
            'notification_outbox' => 'user_id INTEGER, title TEXT, message TEXT, context TEXT, available_at TEXT, processed_at TEXT, attempts INTEGER DEFAULT 0, last_error TEXT',
        ];
        foreach ($tables as $table => $columns) {
            $id = in_array($table, ['notifications', 'notification_outbox'], true) ? 'id TEXT PRIMARY KEY' : 'id INTEGER PRIMARY KEY AUTOINCREMENT';
            DB::statement('CREATE TABLE '.$table.' ('.$id.', '.$columns.', created_at TEXT, updated_at TEXT)');
        }
    }
}
