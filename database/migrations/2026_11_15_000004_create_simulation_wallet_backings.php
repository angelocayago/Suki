<?php

use App\Services\{SimulationRefundApprovalGuard,SimulationWalletCreditContract};
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        if (!in_array(DB::getDriverName(),['pgsql','sqlite'],true)) throw new RuntimeException('Reviewed PostgreSQL/SQLite required.');
        DB::transaction(function () {
            app(SimulationRefundApprovalGuard::class)->requireReady();
            if (DB::table('buyer_wallet_entries as w')->join('simulation_refund_scopes as x','x.return_refund_request_id','=','w.return_refund_request_id')->exists()) throw new RuntimeException('Existing scoped wallet entries require review; backfill refused.');
            Schema::create('simulation_wallet_backings',function(Blueprint $t) {
                $t->foreignId('simulation_commitment_id')->primary()->constrained('simulation_commitments')->restrictOnDelete();
                $t->foreignId('simulation_settlement_id')->constrained('simulation_settlements')->restrictOnDelete();
                $t->foreignId('return_refund_request_id')->unique()->constrained('buyer_refund_fundings','return_refund_request_id')->restrictOnDelete();
                $t->foreignId('buyer_id')->constrained('buyer_wallets','buyer_id')->restrictOnDelete();
                $t->foreignId('buyer_wallet_entry_id')->unique()->constrained('buyer_wallet_entries')->restrictOnDelete();
                $t->foreignId('settlement_event_id')->unique()->constrained('simulation_settlement_events')->restrictOnDelete();
                $t->enum('kind',['BUYER_REFUND_BACKING']);$t->string('currency',3);
                $t->bigInteger('amount_minor');$t->bigInteger('seller_minor');$t->bigInteger('platform_minor');
                $t->string('source_identity',120)->unique();$t->foreignId('credited_by')->constrained('users')->restrictOnDelete();$t->timestamp('created_at');
                $t->index(['buyer_id','created_at']);$t->index('simulation_settlement_id');
            });
            $this->check('simulation_wallet_backings','simulation_wallet_backing_shape',"kind='BUYER_REFUND_BACKING' AND currency='PHP' AND amount_minor>0 AND seller_minor>=0 AND platform_minor>=0 AND amount_minor=seller_minor+platform_minor");
            $this->commitmentShape(true);
            $this->bookChecks(true);
            if (DB::getDriverName()==='pgsql') {
                $coverage=SimulationRefundApprovalGuard::violations(true).' UNION ALL '.SimulationWalletCreditContract::creditViolations();
                $this->function('sim_refund_coverage',"BEGIN IF EXISTS ($coverage) THEN RAISE EXCEPTION 'Simulation refund obligation lacks coordinated coverage'; END IF; RETURN NULL; END");
                $all=SimulationWalletCreditContract::bookViolations(true).' UNION ALL '.$coverage;
                $this->function('sim_wallet_credit_validate',"BEGIN IF EXISTS ($all) THEN RAISE EXCEPTION 'Simulation wallet credit/backing is incomplete'; END IF; RETURN NULL; END");
                foreach (SimulationWalletCreditContract::TABLES as $table) DB::statement("CREATE CONSTRAINT TRIGGER sim_wallet_credit_validate AFTER INSERT OR UPDATE OR DELETE ON public.$table DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION public.sim_wallet_credit_validate()");
                $new=$this->entryCondition('NEW');$old=$this->entryCondition('OLD');
                $this->function('sim_wallet_entry_guard',"BEGIN IF TG_OP<>'INSERT' THEN IF EXISTS (SELECT 1 FROM simulation_wallet_backings WHERE buyer_wallet_entry_id=OLD.id) OR $old THEN RAISE EXCEPTION 'Backed wallet entries are immutable/coordinated'; END IF; END IF; IF TG_OP<>'DELETE' THEN IF $new THEN RAISE EXCEPTION 'Coordinated simulation refund credit required'; END IF; RETURN NEW; END IF; RETURN OLD; END");
                $this->function('sim_wallet_backing_guard',"BEGIN IF TG_OP<>'INSERT' THEN RAISE EXCEPTION 'Buyer backing must be retained'; END IF; IF NOT EXISTS (SELECT 1 FROM simulation_settlement_mutex WHERE id=1 AND operation_open=1) THEN RAISE EXCEPTION 'Coordinated backing creation required'; END IF; RETURN NEW; END");
                foreach (['INSERT','UPDATE','DELETE'] as $op) {
                    DB::statement('CREATE TRIGGER sim_wallet_entry_'.strtolower($op)." BEFORE $op ON public.buyer_wallet_entries FOR EACH ROW EXECUTE FUNCTION public.sim_wallet_entry_guard()");
                    DB::statement('CREATE TRIGGER sim_wallet_backing_'.strtolower($op)." BEFORE $op ON public.simulation_wallet_backings FOR EACH ROW EXECUTE FUNCTION public.sim_wallet_backing_guard()");
                }
                $this->secure();
            } else {
                foreach (['INSERT'=>'NEW','UPDATE'=>'NEW','DELETE'=>'OLD'] as $op=>$row) {
                    $condition=$this->entryCondition($row);
                    if ($op!=='INSERT') $condition="($condition) OR EXISTS (SELECT 1 FROM simulation_wallet_backings WHERE buyer_wallet_entry_id=OLD.id)";
                    if ($op==='UPDATE') $condition.=' OR ('.$this->entryCondition('OLD').')';
                    DB::unprepared('CREATE TRIGGER sim_wallet_entry_'.strtolower($op)." BEFORE $op ON buyer_wallet_entries WHEN $condition BEGIN SELECT RAISE(ABORT,'Coordinated simulation refund credit required'); END");
                    $when=$op==='INSERT'?'WHEN NOT EXISTS (SELECT 1 FROM simulation_settlement_mutex WHERE id=1 AND operation_open=1)':'';
                    DB::unprepared('CREATE TRIGGER sim_wallet_backing_'.strtolower($op)." BEFORE $op ON simulation_wallet_backings $when BEGIN SELECT RAISE(ABORT,'Immutable coordinated Buyer backing required'); END");
                }
            }
        });
    }

    private function entryCondition(string $r): string
    {
        return "(EXISTS (SELECT 1 FROM simulation_refund_scopes x WHERE x.return_refund_request_id=$r.return_refund_request_id AND (
                NOT EXISTS (SELECT 1 FROM simulation_settlement_mutex WHERE id=1 AND operation_open=1) OR $r.type<>'REFUND_CREDIT'
                OR NOT EXISTS (SELECT 1 FROM simulation_settlements s JOIN simulation_commitments c ON c.simulation_settlement_id=s.id
                    WHERE s.id=x.simulation_settlement_id AND c.return_refund_request_id=x.return_refund_request_id AND c.kind='REFUND' AND c.status='RESERVED'
                        AND s.buyer_id=$r.buyer_id AND c.seller_minor+c.platform_minor=$r.amount_minor))))
            OR (EXISTS (SELECT 1 FROM simulation_wallet_backings WHERE buyer_id=$r.buyer_id) AND ($r.type<>'REFUND_CREDIT'
                OR NOT EXISTS (SELECT 1 FROM simulation_refund_scopes WHERE return_refund_request_id=$r.return_refund_request_id)))";
    }

    private function commitmentShape(bool $credits): void
    {
        if (DB::getDriverName()==='pgsql') DB::statement('ALTER TABLE public.simulation_commitments DROP CONSTRAINT simulation_commitments_shape');
        else foreach (['insert','update'] as $op) DB::statement('DROP TRIGGER simulation_commitments_shape_'.$op);
        $states=$credits?"'PARTIAL','RESERVED','WALLET_BACKED'":"'PARTIAL','RESERVED'";
        $this->check('simulation_commitments','simulation_commitments_shape',"seller_minor >= 0 AND platform_minor >= 0 AND ((kind = 'PAYOUT' AND payout_id IS NOT NULL AND return_refund_request_id IS NULL AND platform_minor = 0 AND seller_minor > 0 AND status IN ('PENDING','APPROVED','REJECTED','PAID')) OR (kind = 'REFUND' AND payout_id IS NULL AND return_refund_request_id IS NOT NULL AND status IN ($states)))");
    }

    private function bookChecks(bool $credits): void
    {
        $sql=SimulationWalletCreditContract::bookViolations(DB::getDriverName()==='pgsql',$credits);
        if (DB::getDriverName()==='pgsql') $this->function('simulation_settlement_validate',"BEGIN IF EXISTS ($sql) THEN RAISE EXCEPTION 'Simulation settlement conservation or payout linkage failed'; END IF; RETURN NULL; END");
        else {
            DB::statement('DROP TRIGGER simulation_settlement_validate');
            DB::unprepared("CREATE TRIGGER simulation_settlement_validate AFTER UPDATE ON simulation_settlement_mutex WHEN NEW.operation_open=0 BEGIN SELECT RAISE(ABORT,'Simulation settlement conservation or payout linkage failed') WHERE EXISTS ($sql); END");
        }
    }

    private function function(string $name,string $body): void
    {
        DB::unprepared("CREATE OR REPLACE FUNCTION public.$name() RETURNS trigger LANGUAGE plpgsql SET search_path=pg_catalog,public,pg_temp AS \$\$ $body \$\$");
    }

    private function check(string $table,string $name,string $sql): void
    {
        if (DB::getDriverName()==='pgsql') { DB::statement("ALTER TABLE public.$table ADD CONSTRAINT $name CHECK ($sql)");return; }
        $columns=Schema::getColumnListing($table);
        $sql=preg_replace_callback('/\b[a-z_]+\b/',fn($m)=>in_array($m[0],$columns,true)?'NEW.'.$m[0]:$m[0],$sql);
        foreach ($columns as $c) if (str_ends_with($c,'_minor')) $sql.=" AND typeof(NEW.$c)='integer'";
        foreach (['INSERT','UPDATE'] as $op) DB::unprepared("CREATE TRIGGER {$name}_".strtolower($op)." AFTER $op ON $table WHEN NOT ($sql) BEGIN SELECT RAISE(ABORT,'Invalid simulation backing/commitment shape'); END");
    }

    private function secure(): void
    {
        $roles=DB::table('pg_roles')->whereIn('rolname',['anon','authenticated'])->pluck('rolname')->all();$clients='PUBLIC'.implode('',array_map(fn($r)=>', "'.$r.'"',$roles));
        DB::statement('REVOKE ALL ON TABLE public.simulation_wallet_backings FROM '.$clients);
        $columns=implode(',',array_map(fn($c)=>'"'.$c.'"',Schema::getColumnListing('simulation_wallet_backings')));
        DB::statement("REVOKE ALL PRIVILEGES ($columns) ON TABLE public.simulation_wallet_backings FROM $clients");
        DB::statement('ALTER TABLE public.simulation_wallet_backings ENABLE ROW LEVEL SECURITY');
        if (DB::selectOne("SELECT pg_get_serial_sequence('public.simulation_wallet_backings','simulation_commitment_id') AS name")->name!==null) throw new RuntimeException('Unexpected backing sequence.');
        foreach (['sim_wallet_entry_guard','sim_wallet_backing_guard','sim_wallet_credit_validate','sim_refund_coverage','simulation_settlement_validate'] as $fn) DB::statement("REVOKE ALL ON FUNCTION public.$fn() FROM $clients");
        foreach ($roles as $role) {
            $bad=DB::selectOne("SELECT r.rolsuper OR r.rolbypassrls OR pg_has_role(r.rolname,c.relowner,'MEMBER') OR EXISTS (SELECT 1 FROM pg_roles p WHERE (p.rolsuper OR p.rolbypassrls) AND pg_has_role(r.oid,p.oid,'MEMBER')) OR has_table_privilege(r.rolname,c.oid,'SELECT,INSERT,UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER') OR has_any_column_privilege(r.rolname,c.oid,'SELECT,INSERT,UPDATE,REFERENCES') AS allowed FROM pg_roles r CROSS JOIN pg_class c WHERE r.rolname=? AND c.oid='public.simulation_wallet_backings'::regclass",[$role]);
            if (!$bad || $bad->allowed) throw new RuntimeException('Unsafe inherited wallet backing privileges.');
            if ((int)DB::selectOne('SHOW server_version_num')->server_version_num>=170000 && DB::selectOne("SELECT has_table_privilege(?,'public.simulation_wallet_backings','MAINTAIN') AS allowed",[$role])->allowed) throw new RuntimeException('Unsafe backing maintenance privilege.');
        }
        if (DB::table('pg_policies')->where('schemaname','public')->where('tablename','simulation_wallet_backings')->exists()) throw new RuntimeException('Unexpected backing RLS policy.');
    }

    public function down(): void
    {
        if (DB::table('simulation_wallet_backings')->exists() || DB::table('simulation_commitments')->where('status','WALLET_BACKED')->exists()) throw new RuntimeException('Retain posted Buyer backing; destructive rollback refused.');
        DB::transaction(function () {
            foreach (['insert','update','delete'] as $op) foreach (['entry'=>'buyer_wallet_entries','backing'=>'simulation_wallet_backings'] as $kind=>$table) DB::statement(DB::getDriverName()==='pgsql'?"DROP TRIGGER sim_wallet_{$kind}_$op ON public.$table":"DROP TRIGGER sim_wallet_{$kind}_$op");
            if (DB::getDriverName()==='pgsql') {
                foreach (SimulationWalletCreditContract::TABLES as $table) DB::statement("DROP TRIGGER sim_wallet_credit_validate ON public.$table");
                foreach (['sim_wallet_entry_guard','sim_wallet_backing_guard','sim_wallet_credit_validate'] as $fn) DB::statement("DROP FUNCTION public.$fn()");
                $sql=SimulationRefundApprovalGuard::violations();$this->function('sim_refund_coverage',"BEGIN IF EXISTS ($sql) THEN RAISE EXCEPTION 'Simulation refund obligation lacks coordinated coverage'; END IF; RETURN NULL; END");
            }
            $this->commitmentShape(false);$this->bookChecks(false);Schema::drop('simulation_wallet_backings');
        });
    }
};
