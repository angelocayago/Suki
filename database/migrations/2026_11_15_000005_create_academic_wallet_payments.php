<?php

use App\Services\AcademicWalletPaymentContract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) throw new RuntimeException('Reviewed PostgreSQL/SQLite required.');
        DB::transaction(function () {
            app(\App\Services\SimulationWalletCreditContract::class)->requireReady();
            $required=['2026_11_15_000000_enforce_cod_rider_financial_uniqueness','2026_11_15_000001_create_unreconciled_custody_sources','2026_11_15_000002_create_simulation_settlement','2026_11_15_000003_enforce_simulation_refund_approval','2026_11_15_000004_create_simulation_wallet_backings'];
            if (DB::table('migrations')->whereIn('migration',$required)->count()!==5) throw new RuntimeException('Deploy all five reviewed prerequisite migrations first.');
            if (DB::table('orders')->where('payment_method','wallet')->exists()) throw new RuntimeException('Existing wallet orders require review; automatic historical adoption is refused.');
            Schema::create('academic_wallet_payments', function (Blueprint $t) {
                $t->id();$t->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
                $t->foreignId('seller_order_id')->unique()->constrained()->restrictOnDelete();
                $t->foreignId('buyer_id')->constrained('buyer_wallets','buyer_id')->restrictOnDelete();
                $t->foreignId('payment_entry_id')->unique()->constrained('buyer_wallet_entries')->restrictOnDelete();
                $t->foreignId('native_payment_id')->unique()->constrained('payments')->restrictOnDelete();
                $t->foreignId('earning_entry_id')->unique()->constrained('seller_wallet_entries')->restrictOnDelete();
                foreach (['total_minor','seller_minor','platform_minor','shipping_minor'] as $c) $t->bigInteger($c);
                $t->timestamp('created_at');
            });
            Schema::create('academic_wallet_allocations', function (Blueprint $t) {
                $t->foreignId('payment_id')->constrained('academic_wallet_payments')->restrictOnDelete();
                $t->foreignId('simulation_commitment_id')->constrained('simulation_wallet_backings','simulation_commitment_id')->restrictOnDelete();
                $t->bigInteger('amount_minor');$t->primary(['payment_id','simulation_commitment_id']);
            });
            Schema::create('academic_wallet_reversals', function (Blueprint $t) {
                $t->foreignId('payment_id')->primary()->constrained('academic_wallet_payments')->restrictOnDelete();
                $t->foreignId('wallet_entry_id')->unique()->constrained('buyer_wallet_entries')->restrictOnDelete();
                $t->foreignId('cancelled_by')->constrained('users')->restrictOnDelete();$t->timestamp('created_at');
            });
            Schema::create('academic_wallet_settlements', function (Blueprint $t) {
                $t->foreignId('payment_id')->primary()->constrained('academic_wallet_payments')->restrictOnDelete();
                $t->foreignId('release_entry_id')->unique()->constrained('seller_wallet_entries')->restrictOnDelete();
                $t->foreignId('shipment_id')->unique()->constrained()->restrictOnDelete();
                $t->foreignId('delivered_by')->constrained('users')->restrictOnDelete();$t->string('proof_path');$t->timestamp('created_at');
            });
            if (DB::getDriverName() === 'pgsql') $this->postgres(); else $this->sqlite();
        });
    }

    private function postgres(): void
    {
        DB::statement('ALTER TABLE public.academic_wallet_payments ADD CONSTRAINT academic_wallet_payment_shape CHECK (total_minor>0 AND seller_minor>0 AND platform_minor>=0 AND shipping_minor=5000 AND total_minor=seller_minor+platform_minor+shipping_minor)');
        DB::statement('ALTER TABLE public.academic_wallet_allocations ADD CONSTRAINT academic_wallet_allocation_shape CHECK (amount_minor>0)');
        // Preserve the complete original refund-credit guard body. Add only the
        // coordinated payment/reversal case, checked by the new deferred graph.
        $body=DB::selectOne("SELECT prosrc FROM pg_proc WHERE oid='public.sim_wallet_entry_guard()'::regprocedure")->prosrc;
        if (!str_contains($body,'Backed wallet entries are immutable/coordinated')) throw new RuntimeException('Original credit protection differs.');
        $prefix="IF TG_OP='INSERT' AND NEW.type IN ('ORDER_PAYMENT','PAYMENT_REVERSAL') AND EXISTS (SELECT 1 FROM public.simulation_settlement_mutex WHERE id=1 AND operation_open=1) THEN RETURN NEW; END IF; ";
        $body=preg_replace('/^\s*BEGIN\s*/i','BEGIN '.$prefix,$body,1,$n);if($n!==1)throw new RuntimeException('Original credit guard cannot be extended.');
        DB::unprepared('CREATE OR REPLACE FUNCTION public.sim_wallet_entry_guard() RETURNS trigger LANGUAGE plpgsql SET search_path=pg_catalog,public,pg_temp AS $$ '.$body.' $$');
        $invalid=AcademicWalletPaymentContract::violations();
        DB::unprepared("CREATE FUNCTION public.academic_wallet_validate() RETURNS trigger LANGUAGE plpgsql SET search_path=pg_catalog,public,pg_temp AS \$\$ BEGIN IF EXISTS ($invalid) THEN RAISE EXCEPTION 'Academic wallet payment conservation or ownership failed'; END IF; RETURN NULL; END \$\$");
        $hold=AcademicWalletPaymentContract::settlementHold('NEW.payment_id');
        DB::unprepared("CREATE FUNCTION public.academic_wallet_append_only() RETURNS trigger LANGUAGE plpgsql SET search_path=pg_catalog,public,pg_temp AS \$\$ BEGIN IF TG_OP<>'INSERT' OR NOT EXISTS(SELECT 1 FROM public.simulation_settlement_mutex WHERE id=1 AND operation_open=1) THEN RAISE EXCEPTION 'Immutable coordinated wallet records required'; END IF; IF TG_TABLE_NAME='academic_wallet_settlements' THEN IF EXISTS ($hold) THEN RAISE EXCEPTION 'Wallet earning has unresolved complaint or refund holds'; END IF; END IF; RETURN NEW; END \$\$");
        foreach (AcademicWalletPaymentContract::TABLES as $table) {
            DB::statement("CREATE TRIGGER academic_wallet_append_only BEFORE INSERT OR UPDATE OR DELETE ON public.$table FOR EACH ROW EXECUTE FUNCTION public.academic_wallet_append_only()");
            $this->secure($table);
        }
        foreach (AcademicWalletPaymentContract::WATCHED as $table) DB::statement("CREATE CONSTRAINT TRIGGER academic_wallet_validate AFTER INSERT OR UPDATE OR DELETE ON public.$table DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION public.academic_wallet_validate()");
        foreach (self::immutableConditions('OLD') as $table=>$condition) {
            DB::unprepared("CREATE FUNCTION public.academic_wallet_immutable_$table() RETURNS trigger LANGUAGE plpgsql SET search_path=pg_catalog,public,pg_temp AS \$\$ BEGIN IF $condition THEN RAISE EXCEPTION 'Wallet consumption history is immutable'; END IF; RETURN COALESCE(NEW,OLD); END \$\$");
            DB::statement("CREATE TRIGGER academic_wallet_immutable BEFORE UPDATE OR DELETE ON public.$table FOR EACH ROW EXECUTE FUNCTION public.academic_wallet_immutable_$table()");
        }
        $grantees=$this->grantees();
        foreach (['academic_wallet_validate','academic_wallet_append_only',...array_map(fn($t)=>'academic_wallet_immutable_'.$t,array_keys(self::immutableConditions('OLD')))] as $fn) DB::statement("REVOKE ALL ON FUNCTION public.$fn() FROM $grantees");
    }

    private function sqlite(): void
    {
        AcademicWalletPaymentContract::registerSqliteGuards(DB::connection()->getPdo());
        $sql=DB::table('sqlite_master')->where('type','trigger')->where('name','sim_wallet_entry_insert')->value('sql');
        if (!$sql || !str_contains($sql,'Coordinated simulation refund credit required')) throw new RuntimeException('Original SQLite credit guard differs.');
        $extended=preg_replace('/ WHEN (.*) BEGIN SELECT RAISE/s'," WHEN ($1) AND NOT (NEW.type IN ('ORDER_PAYMENT','PAYMENT_REVERSAL') AND EXISTS (SELECT 1 FROM simulation_settlement_mutex WHERE id=1 AND operation_open=1)) BEGIN SELECT RAISE",$sql,1,$n);
        if ($n!==1) throw new RuntimeException('Original SQLite credit guard cannot be extended.');
        DB::statement('DROP TRIGGER sim_wallet_entry_insert');DB::unprepared($extended);
        $invalid=AcademicWalletPaymentContract::violations();
        DB::unprepared("CREATE TRIGGER academic_wallet_validate AFTER UPDATE ON simulation_settlement_mutex WHEN NEW.operation_open=0 BEGIN SELECT RAISE(ABORT,'Academic wallet payment conservation or ownership failed') WHERE academic_wallet_invalid(); END");
        DB::unprepared("CREATE TRIGGER academic_wallet_settlement_hold BEFORE INSERT ON academic_wallet_settlements WHEN academic_wallet_release_held(NEW.payment_id) BEGIN SELECT RAISE(ABORT,'Wallet earning has unresolved complaint or refund holds'); END");
        foreach (AcademicWalletPaymentContract::TABLES as $table) {
            DB::unprepared("CREATE TRIGGER academic_wallet_insert_$table BEFORE INSERT ON $table WHEN NOT EXISTS (SELECT 1 FROM simulation_settlement_mutex WHERE id=1 AND operation_open=1) BEGIN SELECT RAISE(ABORT,'Coordinated wallet records required'); END");
            foreach (['UPDATE','DELETE'] as $op) DB::unprepared('CREATE TRIGGER academic_wallet_'.strtolower($op)."_$table BEFORE $op ON $table BEGIN SELECT RAISE(ABORT,'Wallet consumption history is immutable'); END");
        }
        foreach (self::immutableConditions('OLD') as $table=>$condition) foreach (['UPDATE','DELETE'] as $op) DB::unprepared('CREATE TRIGGER academic_wallet_immutable_'.strtolower($op)."_$table BEFORE $op ON $table WHEN $condition BEGIN SELECT RAISE(ABORT,'Wallet consumption history is immutable'); END");
        foreach (['academic_wallet_payments'=>"NEW.total_minor>0 AND NEW.seller_minor>0 AND NEW.platform_minor>=0 AND NEW.shipping_minor=5000 AND NEW.total_minor=NEW.seller_minor+NEW.platform_minor+NEW.shipping_minor AND typeof(NEW.total_minor)='integer' AND typeof(NEW.seller_minor)='integer' AND typeof(NEW.platform_minor)='integer' AND typeof(NEW.shipping_minor)='integer'",'academic_wallet_allocations'=>"NEW.amount_minor>0 AND typeof(NEW.amount_minor)='integer'"] as $t=>$condition) DB::unprepared("CREATE TRIGGER academic_wallet_shape_$t BEFORE INSERT ON $t WHEN NOT ($condition) BEGIN SELECT RAISE(ABORT,'Invalid wallet payment shape'); END");
    }

    private static function immutableConditions(string $row): array
    {
        return [
            'buyer_wallet_entries'=>"EXISTS (SELECT 1 FROM academic_wallet_payments p WHERE p.payment_entry_id=$row.id) OR EXISTS (SELECT 1 FROM academic_wallet_reversals r WHERE r.wallet_entry_id=$row.id)",
            'order_items'=>"EXISTS (SELECT 1 FROM academic_wallet_payments p WHERE p.order_id=$row.order_id)",
        ];
    }

    private function secure(string $table): void
    {
        $grantees=$this->grantees();
        DB::statement("REVOKE ALL ON TABLE public.$table FROM $grantees");
        $cols=implode(',',array_map(fn($c)=>'"'.$c.'"',Schema::getColumnListing($table)));
        DB::statement("REVOKE ALL PRIVILEGES ($cols) ON public.$table FROM $grantees");
        DB::statement("ALTER TABLE public.$table ENABLE ROW LEVEL SECURITY");
        $seq=Schema::hasColumn($table,'id') ? DB::selectOne("SELECT pg_get_serial_sequence(?, 'id') AS name",['public.'.$table])->name : null;
        if ($seq) DB::statement("REVOKE ALL ON SEQUENCE $seq FROM $grantees");
        foreach (DB::table('pg_roles')->whereIn('rolname',['anon','authenticated'])->pluck('rolname') as $r) {
            if (DB::selectOne("SELECT r.rolsuper OR r.rolbypassrls OR pg_has_role(r.oid,c.relowner,'MEMBER') OR has_table_privilege(r.oid,c.oid,'SELECT,INSERT,UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER,MAINTAIN') OR has_any_column_privilege(r.oid,c.oid,'SELECT,INSERT,UPDATE,REFERENCES') AS allowed FROM pg_roles r CROSS JOIN pg_class c WHERE r.rolname=? AND c.oid=?::regclass",[$r,'public.'.$table])->allowed) throw new RuntimeException('Inherited wallet client privilege remains.');
            if ($seq && DB::selectOne("SELECT has_sequence_privilege(?,?,'USAGE,SELECT,UPDATE') AS allowed",[$r,$seq])->allowed) throw new RuntimeException('Inherited wallet sequence privilege remains.');
        }
        if (DB::table('pg_policies')->where('schemaname','public')->where('tablename',$table)->exists())throw new RuntimeException('Unexpected wallet payment policy.');
    }

    private function grantees(): string
    {
        return 'PUBLIC'.implode('',array_map(fn($r)=>', "'.$r.'"',DB::table('pg_roles')->whereIn('rolname',['anon','authenticated'])->pluck('rolname')->all()));
    }

    public function down(): void
    {
        throw new RuntimeException('Retain wallet payments, reversals and settlement provenance; reversal requires separate review.');
    }
};
