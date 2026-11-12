<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\{SellerCategoryService};
use App\Services\Admin\ProductModerationService;
use App\Support\DemoProductBatch;
use App\Support\DemoImportDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DemoProductApprovalController extends Controller
{
    public const CONFIRMATION = 'APPROVE LAHAT MERON DEMO';

    private function authorizeAdmin(Request $request): void
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isAdmin() && $actor->status === 'active' && !$actor->is_suspended && $actor->hasVerifiedContacts(), 403);
    }

    private function databaseIdentity(DemoImportDatabase $guard): ?array
    {
        $connection = DB::connection();
        if ($connection->getDriverName() === 'sqlite') {
            abort_unless(app()->environment(['local','testing']), 403);
            return null;
        }
        $config = $connection->getConfig();
        return $guard->configuration($config, ['project'=>DemoImportDatabase::PROJECT,'owner'=>'1','database'=>$config['database'] ?? null]);
    }

    public function preview(Request $request, DemoProductBatch $batch, SellerCategoryService $categories, DemoImportDatabase $guard)
    {
        $this->authorizeAdmin($request);
        $plan = null; $issue = null;
        try {
            $identity = $this->databaseIdentity($guard);
            if ($identity) {
                $guard->verifyConnectionConfiguration(DB::connection(), $identity);
                DB::beginTransaction();
                try {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
                    DB::statement("SET LOCAL statement_timeout = '15000ms'");
                    $guard->verifyServer(DB::connection(), $identity, true);
                    $plan = $batch->approvalPlan($categories);
                } finally { DB::rollBack(); }
            } else { $plan = $batch->approvalPlan($categories); }
        }
        catch (ValidationException $error) { $issue = collect($error->errors())->flatten()->first(); }
        catch (\App\Support\DemoProductImageException $error) { $issue = $error->getMessage(); }
        return view('admin.products.demo-approval', compact('plan','issue'));
    }

    public function approve(Request $request, DemoProductBatch $batch, SellerCategoryService $categories, ProductModerationService $moderation, DemoImportDatabase $guard)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'confirmation' => ['required', \Illuminate\Validation\Rule::in([self::CONFIRMATION])],
            'plan_hash' => ['required','regex:/^[a-f0-9]{64}$/D'],
            'product_ids' => ['prohibited'], 'products' => ['prohibited'], 'seller_id' => ['prohibited'], 'owner_id' => ['prohibited'],
        ]);
        $identity = $this->databaseIdentity($guard);
        if ($identity) $guard->verifyConnectionConfiguration(DB::connection(), $identity);
        $approved = $skipped = 0;
        try { DB::transaction(function () use ($request,$batch,$categories,$moderation,$guard,$identity,$data,&$approved,&$skipped) {
            $actor = \App\Models\User::whereKey($request->user()->id)->lockForUpdate()->first();
            abort_unless($actor && $actor->isAdmin() && $actor->status === 'active' && !$actor->is_suspended && $actor->hasVerifiedContacts(), 403);
            if ($identity) {
                DB::statement("SET LOCAL lock_timeout = '5000ms'");
                DB::statement("SET LOCAL statement_timeout = '30000ms'");
                $guard->verifyServer(DB::connection(), $identity, false);
            }
            $plan = $batch->approvalPlan($categories, true);
            if (!hash_equals($plan['hash'], $data['plan_hash'])) throw ValidationException::withMessages(['demo_batch'=>'The batch changed. Review a new preview before confirming.']);
            foreach ($plan['products'] as $product) {
                if ($product->moderation_status === \App\Enums\ProductModerationStatus::Approved) { $skipped++; continue; }
                $moderation->approve($product, $actor);
                $approved++;
            }
        }); } catch (\App\Support\DemoProductImageException $error) {
            throw ValidationException::withMessages(['demo_batch'=>$error->getMessage()]);
        }
        return back()->with('success', "Demo batch: {$approved} products approved; {$skipped} already approved products skipped.");
    }
}
