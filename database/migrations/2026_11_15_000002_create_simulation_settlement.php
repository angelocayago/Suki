<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    private const TABLES = ['simulation_settlement_mutex', 'simulation_settlements', 'simulation_commitments', 'simulation_settlement_events'];

    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) throw new RuntimeException('Reviewed SQLite/PostgreSQL required.');
        DB::transaction(function () {
            foreach (['custody_sources', 'buyer_refund_fundings', 'seller_wallet_entries'] as $table) {
                if (!Schema::hasTable($table)) throw new RuntimeException('Settlement prerequisites missing.');
            }
            Schema::create('simulation_settlement_mutex', function (Blueprint $t) { $t->integer('id')->primary(); $t->integer('operation_open')->default(0); });
            DB::table('simulation_settlement_mutex')->insert(['id'=>1,'operation_open'=>0]);
            Schema::create('simulation_settlements', function (Blueprint $t) {
                $t->id(); $t->string('scenario_key',80)->unique();
                $t->foreignId('seller_id')->unique()->constrained()->restrictOnDelete();
                $t->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
                $t->foreignId('seller_order_id')->unique()->constrained()->restrictOnDelete();
                $t->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
                $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $t->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->foreignId('custody_source_id')->nullable()->unique()->constrained()->restrictOnDelete();
                $t->string('policy',80); $t->string('currency',3); $t->json('quote'); $t->string('quote_hash',64);
                foreach (['cap_minor','seller_claim_minor','platform_claim_minor','shipping_claim_minor','reverse_shipping_minor','issued_minor','seller_available_minor','platform_available_minor','shipping_protected_minor','refund_reserved_minor','payout_reserved_minor','consumed_minor'] as $field) $t->bigInteger($field);
                $t->timestamp('created_at'); $t->timestamp('issued_at')->nullable();
            });
            Schema::create('simulation_commitments', function (Blueprint $t) {
                $t->id(); $t->foreignId('simulation_settlement_id')->constrained()->restrictOnDelete();
                $t->enum('kind',['REFUND','PAYOUT']);
                $t->foreignId('payout_id')->nullable()->unique()->constrained()->restrictOnDelete();
                $t->foreignId('return_refund_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
                $t->string('status',20); $t->bigInteger('seller_minor'); $t->bigInteger('platform_minor');
                $t->index(['simulation_settlement_id','kind','status']);
            });
            Schema::create('simulation_settlement_events', function (Blueprint $t) {
                $t->id(); $t->foreignId('simulation_settlement_id')->constrained()->restrictOnDelete();
                $t->string('operation_key',100)->unique(); $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
                $t->json('snapshot'); $t->timestamp('created_at');
            });
            $this->shape('simulation_settlement_mutex', 'id = 1 AND operation_open IN (0,1)');
            $this->shape('simulation_settlements', "policy = 'ACADEMIC_SIMULATION_V1_NO_REVERSE_CHARGE' AND currency = 'PHP' AND reverse_shipping_minor = 0 AND cap_minor > 0 AND seller_claim_minor >= 0 AND platform_claim_minor >= 0 AND shipping_claim_minor = 5000 AND cap_minor = seller_claim_minor + platform_claim_minor + shipping_claim_minor AND issued_minor IN (0,cap_minor) AND seller_available_minor >= 0 AND platform_available_minor >= 0 AND shipping_protected_minor >= 0 AND refund_reserved_minor >= 0 AND payout_reserved_minor >= 0 AND consumed_minor >= 0 AND issued_minor = seller_available_minor + platform_available_minor + shipping_protected_minor + refund_reserved_minor + payout_reserved_minor + consumed_minor AND ((issued_minor = 0 AND custody_source_id IS NULL AND issued_by IS NULL AND issued_at IS NULL) OR (issued_minor = cap_minor AND custody_source_id IS NOT NULL AND issued_by IS NOT NULL AND issued_at IS NOT NULL AND shipping_protected_minor = shipping_claim_minor))");
            $this->shape('simulation_commitments', "seller_minor >= 0 AND platform_minor >= 0 AND ((kind = 'PAYOUT' AND payout_id IS NOT NULL AND return_refund_request_id IS NULL AND platform_minor = 0 AND seller_minor > 0 AND status IN ('PENDING','APPROVED','REJECTED','PAID')) OR (kind = 'REFUND' AND payout_id IS NULL AND return_refund_request_id IS NOT NULL AND status IN ('PARTIAL','RESERVED')))");
            // The final operation-close check sees native payouts and the relational
            // allocations together, rather than trusting a display reference.
            $invalid = $this->violations(DB::getDriverName()==='pgsql');
            if (DB::getDriverName() === 'pgsql') {
                DB::unprepared("CREATE FUNCTION public.simulation_settlement_validate() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF EXISTS ($invalid) THEN RAISE EXCEPTION 'Simulation settlement conservation or payout linkage failed'; END IF; RETURN NULL; END \$\$");
                foreach (['simulation_settlements','simulation_commitments','payouts','seller_wallet_entries','simulation_settlement_mutex','buyer_refund_fundings'] as $table) DB::unprepared("CREATE CONSTRAINT TRIGGER simulation_settlement_validate AFTER INSERT OR UPDATE OR DELETE ON public.$table DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION public.simulation_settlement_validate()");
                DB::unprepared("CREATE FUNCTION public.simulation_settlement_write_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF EXISTS (SELECT 1 FROM public.simulation_settlements s WHERE s.seller_id = COALESCE(NEW.seller_id, OLD.seller_id)) AND NOT EXISTS (SELECT 1 FROM public.simulation_settlement_mutex WHERE id=1 AND operation_open=1) THEN RAISE EXCEPTION 'Coordinated simulation operation required'; END IF; RETURN COALESCE(NEW,OLD); END \$\$");
                DB::unprepared('CREATE TRIGGER simulation_payout_guard BEFORE INSERT OR UPDATE OR DELETE ON public.payouts FOR EACH ROW EXECUTE FUNCTION public.simulation_settlement_write_guard()');
                foreach (self::TABLES as $table) $this->secure($table);
                $clients=DB::table('pg_roles')->whereIn('rolname',['anon','authenticated'])->pluck('rolname')->all();
                DB::statement('REVOKE ALL ON FUNCTION public.simulation_settlement_validate(), public.simulation_settlement_write_guard() FROM PUBLIC'.implode('',array_map(fn($r)=>', "'.$r.'"',$clients)));
            } else {
                DB::unprepared("CREATE TRIGGER simulation_settlement_validate AFTER UPDATE ON simulation_settlement_mutex WHEN NEW.operation_open = 0 BEGIN SELECT RAISE(ABORT,'Simulation settlement conservation or payout linkage failed') WHERE EXISTS ($invalid); END");
                foreach (['INSERT'=>'NEW','UPDATE'=>'NEW','DELETE'=>'OLD'] as $operation=>$row) DB::unprepared("CREATE TRIGGER simulation_payout_".strtolower($operation)." BEFORE $operation ON payouts WHEN EXISTS (SELECT 1 FROM simulation_settlements WHERE seller_id=$row.seller_id) AND NOT EXISTS (SELECT 1 FROM simulation_settlement_mutex WHERE id=1 AND operation_open=1) BEGIN SELECT RAISE(ABORT,'Coordinated simulation operation required'); END");
            }
            $this->immutability();
        });
    }

    private function violations(bool $nativeLedger): string
    {
        $sql="SELECT s.id FROM simulation_settlements s WHERE s.issued_minor > 0 AND (
            s.refund_reserved_minor <> COALESCE((SELECT SUM(c.seller_minor+c.platform_minor) FROM simulation_commitments c WHERE c.simulation_settlement_id=s.id AND c.kind='REFUND'),0)
            OR s.payout_reserved_minor <> COALESCE((SELECT SUM(c.seller_minor) FROM simulation_commitments c WHERE c.simulation_settlement_id=s.id AND c.kind='PAYOUT' AND c.status IN ('PENDING','APPROVED')),0)
            OR s.consumed_minor <> COALESCE((SELECT SUM(c.seller_minor) FROM simulation_commitments c WHERE c.simulation_settlement_id=s.id AND c.kind='PAYOUT' AND c.status='PAID'),0)
            OR s.seller_claim_minor <> s.seller_available_minor + COALESCE((SELECT SUM(c.seller_minor) FROM simulation_commitments c WHERE c.simulation_settlement_id=s.id AND (c.kind='REFUND' OR c.status IN ('PENDING','APPROVED','PAID'))),0)
            OR s.platform_claim_minor <> s.platform_available_minor + COALESCE((SELECT SUM(c.platform_minor) FROM simulation_commitments c WHERE c.simulation_settlement_id=s.id AND c.kind='REFUND'),0))
            UNION ALL SELECT s.id FROM simulation_settlements s JOIN payouts p ON p.seller_id=s.seller_id LEFT JOIN simulation_commitments c ON c.payout_id=p.id WHERE c.id IS NULL OR c.simulation_settlement_id<>s.id OR c.kind<>'PAYOUT' OR c.status<>p.status OR c.seller_minor<>p.amount_minor";
        $sql.=" UNION ALL SELECT c.id FROM simulation_commitments c JOIN simulation_settlements s ON s.id=c.simulation_settlement_id LEFT JOIN payouts p ON p.id=c.payout_id WHERE c.kind='PAYOUT' AND (p.id IS NULL OR p.seller_id<>s.seller_id)";
        if ($nativeLedger) $sql.=" UNION ALL SELECT c.id FROM simulation_commitments c JOIN simulation_settlements s ON s.id=c.simulation_settlement_id LEFT JOIN buyer_refund_fundings f ON f.return_refund_request_id=c.return_refund_request_id LEFT JOIN return_refund_requests r ON r.id=c.return_refund_request_id WHERE c.kind='REFUND' AND (f.return_refund_request_id IS NULL OR r.id IS NULL OR r.seller_order_id<>s.seller_order_id OR r.buyer_id<>s.buyer_id OR r.seller_id<>s.seller_id OR r.status<>'ADMIN_APPROVED' OR f.seller_id<>s.seller_id OR f.buyer_id<>s.buyer_id OR c.seller_minor>f.seller_adjustment_minor OR c.platform_minor>f.buyer_refund_minor-f.seller_adjustment_minor OR (c.status='RESERVED' AND (c.seller_minor+c.platform_minor<>f.buyer_refund_minor OR f.status<>'FUNDED_READY_TO_CREDIT' OR f.funded_minor<>f.buyer_refund_minor OR f.funding_source_type<>'SIMULATION_SETTLEMENT')) OR (c.status='PARTIAL' AND f.status<>'APPROVED_AWAITING_FUNDING'))";
        // SQLite cannot retain a trigger referencing a table being rebuilt by an
        // older migration. The coordinator validates native ledger rows there.
        if ($nativeLedger) $sql.=" UNION ALL SELECT c.id FROM simulation_commitments c JOIN simulation_settlements s ON s.id=c.simulation_settlement_id LEFT JOIN payouts p ON p.id=c.payout_id WHERE c.kind='PAYOUT' AND (p.id IS NULL OR p.seller_id<>s.seller_id OR (SELECT COUNT(*) FROM seller_wallet_entries w WHERE w.payout_id=p.id AND w.seller_id=p.seller_id AND w.type='PAYOUT_RESERVATION' AND w.amount_minor=p.amount_minor AND w.status=CASE p.status WHEN 'PAID' THEN 'SETTLED' WHEN 'REJECTED' THEN 'RELEASED' ELSE 'RESERVED' END)<>1 OR (p.status='PAID' AND (SELECT COUNT(*) FROM seller_wallet_entries w WHERE w.payout_id=p.id AND w.seller_id=p.seller_id AND w.type='PAYOUT_COMPLETED' AND w.status='POSTED' AND w.amount_minor=p.amount_minor)<>1)) UNION ALL SELECT id FROM simulation_settlement_mutex WHERE operation_open<>0";
        return $sql;
    }

    private function immutability(): void
    {
        $fields=['scenario_key','seller_id','buyer_id','order_id','seller_order_id','created_by','created_at','policy','currency','quote_hash','cap_minor','seller_claim_minor','platform_claim_minor','shipping_claim_minor','reverse_shipping_minor'];
        if (DB::getDriverName()==='pgsql') {
            $changed=implode(' OR ',array_map(fn($f)=>"NEW.$f IS DISTINCT FROM OLD.$f",$fields));
            DB::unprepared("CREATE FUNCTION public.simulation_settlement_immutable() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF TG_TABLE_NAME='simulation_settlement_events' OR TG_OP='DELETE' THEN RAISE EXCEPTION 'Simulation history is immutable'; END IF; IF $changed OR NEW.quote::text IS DISTINCT FROM OLD.quote::text OR (OLD.issued_minor>0 AND (NEW.custody_source_id IS DISTINCT FROM OLD.custody_source_id OR NEW.issued_by IS DISTINCT FROM OLD.issued_by OR NEW.issued_at IS DISTINCT FROM OLD.issued_at OR NEW.issued_minor<>OLD.issued_minor)) THEN RAISE EXCEPTION 'Scenario policy and issuance are immutable'; END IF; RETURN NEW; END \$\$");
            DB::unprepared('CREATE TRIGGER simulation_policy_immutable BEFORE UPDATE OR DELETE ON public.simulation_settlements FOR EACH ROW EXECUTE FUNCTION public.simulation_settlement_immutable()');
            DB::unprepared('CREATE TRIGGER simulation_events_immutable BEFORE UPDATE OR DELETE ON public.simulation_settlement_events FOR EACH ROW EXECUTE FUNCTION public.simulation_settlement_immutable()');
            $roles=DB::table('pg_roles')->whereIn('rolname',['anon','authenticated'])->pluck('rolname')->all();
            DB::statement('REVOKE ALL ON FUNCTION public.simulation_settlement_immutable() FROM PUBLIC'.implode('',array_map(fn($r)=>', "'.$r.'"',$roles)));
        } else {
            $changed=implode(' OR ',array_map(fn($f)=>"NEW.$f IS NOT OLD.$f",array_merge($fields,['quote'])));
            DB::unprepared("CREATE TRIGGER simulation_policy_immutable BEFORE UPDATE ON simulation_settlements WHEN $changed OR (OLD.issued_minor>0 AND (NEW.custody_source_id IS NOT OLD.custody_source_id OR NEW.issued_by IS NOT OLD.issued_by OR NEW.issued_at IS NOT OLD.issued_at OR NEW.issued_minor<>OLD.issued_minor)) BEGIN SELECT RAISE(ABORT,'Scenario policy and issuance are immutable'); END");
            DB::unprepared("CREATE TRIGGER simulation_policy_retained BEFORE DELETE ON simulation_settlements BEGIN SELECT RAISE(ABORT,'Simulation history is immutable'); END");
            foreach (['UPDATE','DELETE'] as $op) DB::unprepared("CREATE TRIGGER simulation_events_".strtolower($op)." BEFORE $op ON simulation_settlement_events BEGIN SELECT RAISE(ABORT,'Simulation history is immutable'); END");
        }
    }

    private function shape(string $table, string $condition): void
    {
        if (DB::getDriverName()==='pgsql') { DB::statement("ALTER TABLE public.$table ADD CONSTRAINT {$table}_shape CHECK ($condition)"); return; }
        $columns=Schema::getColumnListing($table);
        $condition=preg_replace_callback('/\b[a-z_]+\b/',fn($m)=>in_array($m[0],$columns,true)?'NEW.'.$m[0]:$m[0],$condition);
        foreach ($columns as $c) if (str_ends_with($c,'_minor')) $condition.=" AND typeof(NEW.$c)='integer'";
        foreach (['INSERT','UPDATE'] as $op) DB::unprepared("CREATE TRIGGER {$table}_shape_".strtolower($op)." AFTER $op ON $table WHEN NOT ($condition) BEGIN SELECT RAISE(ABORT,'Invalid simulation shape'); END");
    }

    private function secure(string $table): void
    {
        $roles=DB::table('pg_roles')->whereIn('rolname',['anon','authenticated'])->pluck('rolname')->all();
        $grantees='PUBLIC'.implode('',array_map(fn($r)=>', "'.$r.'"',$roles));
        DB::statement("REVOKE ALL PRIVILEGES ON TABLE public.$table FROM $grantees");
        $columns=implode(',',array_map(fn($c)=>'"'.$c.'"',Schema::getColumnListing($table)));
        DB::statement("REVOKE ALL PRIVILEGES ($columns) ON TABLE public.$table FROM $grantees");
        DB::statement("ALTER TABLE public.$table ENABLE ROW LEVEL SECURITY");
        $sequence=DB::selectOne('SELECT pg_get_serial_sequence(?,\'id\') AS name',['public.'.$table])->name;
        if ($sequence !== null) {
            if ($sequence!=="public.{$table}_id_seq") throw new RuntimeException('Unexpected simulation sequence.');
            DB::statement("REVOKE ALL PRIVILEGES ON SEQUENCE $sequence FROM $grantees");
        }
        foreach ($roles as $role) {
            $bad=DB::selectOne("SELECT r.rolsuper OR r.rolbypassrls OR pg_has_role(r.rolname,c.relowner,'MEMBER') OR EXISTS (SELECT 1 FROM pg_roles p WHERE (p.rolsuper OR p.rolbypassrls) AND pg_has_role(r.oid,p.oid,'MEMBER')) OR has_table_privilege(r.rolname,c.oid,'SELECT,INSERT,UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER') OR has_any_column_privilege(r.rolname,c.oid,'SELECT,INSERT,UPDATE,REFERENCES') AS allowed FROM pg_roles r CROSS JOIN pg_class c WHERE r.rolname=? AND c.oid=?::regclass",[$role,'public.'.$table]);
            if (!$bad || $bad->allowed || ($sequence && DB::selectOne('SELECT has_sequence_privilege(?,?,\'USAGE,SELECT,UPDATE\') AS allowed',[$role,$sequence])->allowed)) throw new RuntimeException('Unsafe inherited simulation privileges.');
            if ((int)DB::selectOne('SHOW server_version_num')->server_version_num>=170000 && DB::selectOne('SELECT has_table_privilege(?,?,\'MAINTAIN\') AS allowed',[$role,'public.'.$table])->allowed) throw new RuntimeException('Unsafe maintenance privilege.');
        }
        if (DB::table('pg_policies')->where('schemaname','public')->where('tablename',$table)->exists()) throw new RuntimeException('Unexpected simulation policy.');
    }

    public function down(): void
    {
        if (DB::table('simulation_settlements')->exists()) throw new RuntimeException('Simulation accounting must be retained; rollback refused.');
        DB::transaction(function () {
            if (DB::getDriverName()==='pgsql') {
                foreach (['simulation_settlements','simulation_commitments','payouts','seller_wallet_entries','simulation_settlement_mutex','buyer_refund_fundings'] as $table) DB::statement("DROP TRIGGER simulation_settlement_validate ON public.$table");
                DB::statement('DROP TRIGGER simulation_payout_guard ON public.payouts');
                DB::statement('DROP FUNCTION public.simulation_settlement_validate(), public.simulation_settlement_write_guard()');
            } else foreach (['insert','update','delete'] as $op) DB::statement("DROP TRIGGER simulation_payout_$op");
            foreach (array_reverse(self::TABLES) as $table) Schema::drop($table);
            if (DB::getDriverName()==='pgsql') DB::statement('DROP FUNCTION public.simulation_settlement_immutable()');
        });
    }
};
