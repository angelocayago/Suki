<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        if(!in_array(DB::getDriverName(),['pgsql','sqlite'],true))throw new RuntimeException('Reviewed PostgreSQL/SQLite required.');
        // Keep creation and privilege hardening atomic, including direct operator calls.
        if(DB::getDriverName()==='pgsql')DB::transaction(fn()=> $this->createFoundation());
        else $this->createFoundation();
    }

    private function createFoundation(): void
    {
        if(!Schema::hasColumn('return_refund_requests','workflow_mode') || !Schema::hasColumn('seller_wallet_entries','return_refund_request_id'))throw new RuntimeException('Apply and review the Return/Refund prerequisites first.');
        Schema::create('buyer_wallets',function(Blueprint $t){
            $t->foreignId('buyer_id')->primary()->constrained('users')->restrictOnDelete();$t->timestamps();
        });
        Schema::create('buyer_refund_fundings',function(Blueprint $t){
            $t->foreignId('return_refund_request_id')->primary()->constrained()->restrictOnDelete();
            $t->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('seller_id')->constrained()->restrictOnDelete();
            $t->foreignId('seller_wallet_entry_id')->unique()->constrained()->restrictOnDelete();
            $t->string('source_identity',100)->unique();
            $t->enum('status',['APPROVED_AWAITING_FUNDING','FUNDED_READY_TO_CREDIT','CREDITED_TO_BUYER_WALLET'])->default('APPROVED_AWAITING_FUNDING');
            foreach(['buyer_refund_minor','merchandise_minor','seller_adjustment_minor','commission_reversal_minor','seller_discount_minor','platform_discount_minor']as$column)$t->bigInteger($column);
            foreach(['funded_minor','custody_funded_minor','reserve_funded_minor','seller_recovery_funded_minor']as$column)$t->bigInteger($column)->default(0);
            $t->string('funding_source_type',50)->nullable();$t->string('funding_source_reference',150)->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();$t->timestamp('verified_at')->nullable();$t->timestamp('consumed_at')->nullable();$t->timestamps();
            $t->index(['buyer_id','created_at']);$t->index(['status','created_at']);
        });
        Schema::create('buyer_wallet_entries',function(Blueprint $t){
            $t->id();$t->foreignId('buyer_id')->constrained('buyer_wallets','buyer_id')->restrictOnDelete();
            $t->enum('type',['REFUND_CREDIT','ORDER_PAYMENT','PAYMENT_REVERSAL']);$t->enum('status',['POSTED'])->default('POSTED');$t->bigInteger('amount_minor');
            $t->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            // Funding uses the same primary key as its original return request.
            $t->foreignId('return_refund_request_id')->nullable()->unique()->constrained('buyer_refund_fundings','return_refund_request_id')->restrictOnDelete();
            $t->foreignId('source_entry_id')->nullable()->unique()->constrained('buyer_wallet_entries')->restrictOnDelete();
            $t->string('source_identity',120)->unique();$t->timestamp('created_at');
            $t->unique(['order_id','type']);$t->index(['buyer_id','created_at']);
        });
        $funding="buyer_refund_minor >= 0 AND merchandise_minor >= 0 AND seller_adjustment_minor >= 0 AND commission_reversal_minor >= 0 AND seller_discount_minor >= 0 AND platform_discount_minor >= 0 AND funded_minor >= 0 AND funded_minor <= buyer_refund_minor AND custody_funded_minor >= 0 AND reserve_funded_minor >= 0 AND seller_recovery_funded_minor >= 0 AND seller_recovery_funded_minor <= seller_adjustment_minor AND funded_minor = custody_funded_minor + reserve_funded_minor + seller_recovery_funded_minor AND ((status = 'APPROVED_AWAITING_FUNDING' AND funded_minor = 0 AND verified_at IS NULL AND verified_by IS NULL AND consumed_at IS NULL AND funding_source_type IS NULL AND funding_source_reference IS NULL) OR (status IN ('FUNDED_READY_TO_CREDIT','CREDITED_TO_BUYER_WALLET') AND funded_minor = buyer_refund_minor AND funded_minor > 0 AND verified_at IS NOT NULL AND verified_by IS NOT NULL AND funding_source_type IS NOT NULL AND funding_source_reference IS NOT NULL AND ((status = 'FUNDED_READY_TO_CREDIT' AND consumed_at IS NULL) OR (status = 'CREDITED_TO_BUYER_WALLET' AND consumed_at IS NOT NULL))))";
        $funding.=' AND buyer_refund_minor = merchandise_minor - seller_discount_minor - platform_discount_minor AND commission_reversal_minor <= merchandise_minor AND seller_adjustment_minor = CASE WHEN merchandise_minor - seller_discount_minor - commission_reversal_minor > 0 THEN merchandise_minor - seller_discount_minor - commission_reversal_minor ELSE 0 END';
        $entry="((type = 'REFUND_CREDIT' AND amount_minor > 0 AND return_refund_request_id IS NOT NULL AND order_id IS NULL AND source_entry_id IS NULL) OR (type = 'ORDER_PAYMENT' AND amount_minor < 0 AND order_id IS NOT NULL AND return_refund_request_id IS NULL AND source_entry_id IS NULL) OR (type = 'PAYMENT_REVERSAL' AND amount_minor > 0 AND order_id IS NOT NULL AND return_refund_request_id IS NULL AND source_entry_id IS NOT NULL AND source_entry_id <> id))";
        $this->check('buyer_refund_fundings','buyer_refund_funding_shape',$funding);
        $this->check('buyer_wallet_entries','buyer_wallet_entry_shape',$entry);
        if(DB::getDriverName()==='pgsql')$this->securePostgresObjects();
    }

    private function securePostgresObjects(): void
    {
        // Only these newly created objects. Never alter schema-wide default grants.
        $roles=DB::table('pg_roles')->whereIn('rolname',['anon','authenticated'])->pluck('rolname')->all();
        $grantees='PUBLIC'.implode('',array_map(fn($role)=>', "'.$role.'"',$roles));
        foreach(['buyer_wallets','buyer_refund_fundings','buyer_wallet_entries']as$table){
            $qualified='"public"."'.$table.'"';
            DB::statement('REVOKE ALL PRIVILEGES ON TABLE '.$qualified.' FROM '.$grantees);
            $columns=implode(', ',array_map(fn($column)=>'"'.str_replace('"','""',$column).'"',Schema::getColumnListing($table)));
            DB::statement('REVOKE ALL PRIVILEGES ('.$columns.') ON TABLE '.$qualified.' FROM '.$grantees);
            DB::statement('ALTER TABLE '.$qualified.' ENABLE ROW LEVEL SECURITY');
            // No policies: client roles get default-deny RLS; the Laravel owner retains access.
            $sequences=DB::select("SELECT n.nspname,s.relname FROM pg_class s JOIN pg_namespace n ON n.oid=s.relnamespace JOIN pg_depend d ON d.classid='pg_class'::regclass AND d.objid=s.oid AND d.refclassid='pg_class'::regclass WHERE s.relkind='S' AND d.refobjid=to_regclass(?) AND d.deptype IN ('a','i')",['public.'.$table]);
            foreach($sequences as$sequence){
                $name='"'.str_replace('"','""',$sequence->nspname).'"."'.str_replace('"','""',$sequence->relname).'"';
                DB::statement('REVOKE ALL PRIVILEGES ON SEQUENCE '.$name.' FROM '.$grantees);
                foreach($roles as$role)if(DB::selectOne('SELECT has_sequence_privilege(?, ?, ?) AS allowed',[$role,$sequence->nspname.'.'.$sequence->relname,'USAGE, SELECT, UPDATE'])->allowed)throw new RuntimeException('Inherited client sequence privileges remain; wallet migration rolled back.');
            }
            foreach($roles as$role){
                $unsafe=DB::selectOne("SELECT r.rolsuper OR r.rolbypassrls OR pg_has_role(r.rolname,c.relowner,'MEMBER') OR EXISTS (SELECT 1 FROM pg_roles p WHERE (p.rolsuper OR p.rolbypassrls) AND pg_has_role(r.oid,p.oid,'MEMBER')) OR has_table_privilege(r.rolname,c.oid,'SELECT, INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES, TRIGGER') OR has_any_column_privilege(r.rolname,c.oid,'SELECT, INSERT, UPDATE, REFERENCES') AS allowed FROM pg_roles r CROSS JOIN pg_class c WHERE r.rolname=? AND c.oid=to_regclass(?)",[$role,'public.'.$table]);
                if(!$unsafe || $unsafe->allowed)throw new RuntimeException('Inherited client table privileges or privileged role membership remain; wallet migration rolled back.');
                if((int)DB::selectOne('SHOW server_version_num')->server_version_num>=170000 && DB::selectOne('SELECT has_table_privilege(?, ?, ?) AS allowed',[$role,'public.'.$table,'MAINTAIN'])->allowed)throw new RuntimeException('Inherited client maintenance privilege remains; wallet migration rolled back.');
            }
            // Catch custom DDL hooks that might silently add client access policies.
            if(DB::table('pg_policies')->where('schemaname','public')->where('tablename',$table)->exists())throw new RuntimeException('Unexpected wallet RLS policy; migration rolled back.');
        }
    }

    private function check(string $table,string $name,string $expression): void
    {
        if(DB::getDriverName()==='pgsql')DB::statement('ALTER TABLE '.$table.' ADD CONSTRAINT '.$name.' CHECK ('.$expression.')');
        else{
            $columns=Schema::getColumnListing($table);
            $new=preg_replace_callback('/\b[a-z_]+\b/',fn($m)=>in_array($m[0],$columns,true)?'NEW.'.$m[0]:$m[0],$expression);
            $money=$table==='buyer_wallet_entries'?['amount_minor']:['buyer_refund_minor','merchandise_minor','seller_adjustment_minor','commission_reversal_minor','seller_discount_minor','platform_discount_minor','funded_minor','custody_funded_minor','reserve_funded_minor','seller_recovery_funded_minor'];
            foreach($money as$column)$new.=" AND typeof(NEW.".$column.") = 'integer'";
            foreach(['INSERT','UPDATE']as$operation)DB::unprepared('CREATE TRIGGER '.$name.'_'.strtolower($operation).' AFTER '.$operation.' ON '.$table.' WHEN NOT ('.$new.") BEGIN SELECT RAISE(ABORT, 'Invalid Buyer wallet row shape'); END");
        }
    }

    public function down(): void
    {
        foreach(['buyer_wallet_entries','buyer_refund_fundings','buyer_wallets']as$table)if(DB::table($table)->exists())throw new RuntimeException('Retain Buyer wallet/funding records; destructive rollback refused.');
        Schema::drop('buyer_wallet_entries');Schema::drop('buyer_refund_fundings');Schema::drop('buyer_wallets');
    }
};
