<?php

use App\Services\SimulationRefundApprovalGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    private const TABLE='simulation_refund_scopes';
    private const NATIVE=['accounting'=>'return_refund_accounting','ledger'=>'seller_wallet_entries','funding'=>'buyer_refund_fundings'];
    private const VALIDATED=['return_refund_requests','return_refund_accounting','seller_wallet_entries','buyer_refund_fundings','simulation_commitments','simulation_refund_scopes','simulation_settlement_mutex','simulation_settlements'];

    public function up(): void
    {
        if (!in_array(DB::getDriverName(),['pgsql','sqlite'],true)) throw new RuntimeException('Reviewed PostgreSQL/SQLite required.');
        DB::transaction(function () {
            foreach (['simulation_settlements','simulation_settlement_mutex','simulation_commitments',...array_values(self::NATIVE)] as $table) if (!Schema::hasTable($table)) throw new RuntimeException('Reviewed simulation/refund prerequisites required.');
            if (DB::table('return_refund_requests as r')->join('simulation_settlements as s','s.seller_order_id','=','r.seller_order_id')->exists()) throw new RuntimeException('Existing simulation requests require explicit review; automatic scope backfill refused.');
            Schema::create(self::TABLE,function(Blueprint $t) {
                $t->foreignId('return_refund_request_id')->primary()->constrained('return_refund_requests')->restrictOnDelete();
                $t->foreignId('simulation_settlement_id')->constrained('simulation_settlements')->restrictOnDelete();
                $t->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
                $t->enum('workflow_mode',['SELLER_REVIEW','ADMIN_REVIEW','FINANCIAL']);
                $t->index('simulation_settlement_id');
            });
            // SQLite fixtures deliberately remove the historical discriminator.
            // Its persistent triggers record required cohort mode, without a
            // dependency on that rebuildable column; runtime coverage checks the
            // actual mode. PostgreSQL freezes and enforces the actual mode too.
            $mode=DB::getDriverName()==='pgsql'?"COALESCE(NEW.workflow_mode,'FINANCIAL')":"'SELLER_REVIEW'";
            $modeMismatch=DB::getDriverName()==='pgsql'?" OR COALESCE(NEW.workflow_mode,'FINANCIAL')<>x.workflow_mode":'';
            $register="INSERT INTO simulation_refund_scopes (return_refund_request_id,simulation_settlement_id,order_item_id,workflow_mode)
                SELECT NEW.id,s.id,NEW.order_item_id,$mode FROM simulation_settlements s
                WHERE s.seller_order_id=NEW.seller_order_id OR s.order_id=NEW.order_id";
            $wrongIdentity="EXISTS (SELECT 1 FROM simulation_refund_scopes x JOIN simulation_settlements s ON s.id=x.simulation_settlement_id
                WHERE x.return_refund_request_id=NEW.id AND (NEW.order_id<>s.order_id OR NEW.seller_order_id<>s.seller_order_id OR NEW.seller_id<>s.seller_id OR NEW.buyer_id<>s.buyer_id
                    OR NEW.order_item_id<>x.order_item_id $modeMismatch))";
            $scope="EXISTS (SELECT 1 FROM simulation_refund_scopes WHERE return_refund_request_id=NEW.id)";
            $unregistered="(NOT ($scope) AND EXISTS (SELECT 1 FROM simulation_settlements s WHERE s.order_id=NEW.order_id OR s.seller_order_id=NEW.seller_order_id))";
            $approval="EXISTS (SELECT 1 FROM simulation_refund_scopes WHERE return_refund_request_id=NEW.id AND (workflow_mode<>'SELLER_REVIEW'
                OR NOT EXISTS (SELECT 1 FROM simulation_settlement_mutex WHERE id=1 AND operation_open=1)))";
            if (DB::getDriverName()==='pgsql') {
                $this->function('sim_refund_register',"BEGIN $register; IF $wrongIdentity OR (NEW.status='ADMIN_APPROVED' AND $approval) THEN RAISE EXCEPTION 'Coordinated Seller-only simulation approval required'; END IF; RETURN NEW; END");
                DB::statement('CREATE TRIGGER sim_refund_scope_register AFTER INSERT ON public.return_refund_requests FOR EACH ROW EXECUTE FUNCTION public.sim_refund_register()');
                $this->function('sim_refund_request_guard',"BEGIN IF TG_OP='DELETE' THEN IF EXISTS (SELECT 1 FROM simulation_refund_scopes WHERE return_refund_request_id=OLD.id) THEN RAISE EXCEPTION 'Simulation request identity must be retained'; END IF; RETURN OLD; END IF; IF $wrongIdentity OR $unregistered OR ((NEW.status='ADMIN_APPROVED' OR OLD.status='ADMIN_APPROVED') AND $approval) THEN RAISE EXCEPTION 'Coordinated Seller-only simulation approval required'; END IF; RETURN NEW; END");
                foreach (['UPDATE','DELETE'] as $op) DB::statement('CREATE TRIGGER sim_refund_request_'.strtolower($op)." BEFORE $op ON public.return_refund_requests FOR EACH ROW EXECUTE FUNCTION public.sim_refund_request_guard()");
                foreach (self::NATIVE as $kind=>$table) {
                    $this->function('sim_refund_'.$kind.'_guard',$this->nativeBody($kind));
                    foreach (['INSERT','UPDATE','DELETE'] as $op) DB::statement('CREATE TRIGGER sim_refund_'.$kind.'_'.strtolower($op)." BEFORE $op ON public.$table FOR EACH ROW EXECUTE FUNCTION public.sim_refund_{$kind}_guard()");
                }
                $this->function('sim_refund_scope_immutable',"BEGIN RAISE EXCEPTION 'Simulation refund scope is immutable'; END");
                foreach (['UPDATE','DELETE'] as $op) DB::statement('CREATE TRIGGER sim_refund_scope_'.strtolower($op)." BEFORE $op ON public.simulation_refund_scopes FOR EACH ROW EXECUTE FUNCTION public.sim_refund_scope_immutable()");
                $invalid=SimulationRefundApprovalGuard::violations();
                $this->function('sim_refund_coverage',"BEGIN IF EXISTS ($invalid) THEN RAISE EXCEPTION 'Simulation refund obligation lacks coordinated coverage'; END IF; RETURN NULL; END");
                foreach (self::VALIDATED as $table) DB::statement("CREATE CONSTRAINT TRIGGER sim_refund_coverage AFTER INSERT OR UPDATE OR DELETE ON public.$table DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION public.sim_refund_coverage()");
                $this->secure();
            } else {
                DB::unprepared("CREATE TRIGGER sim_refund_scope_register AFTER INSERT ON return_refund_requests BEGIN $register; SELECT RAISE(ABORT,'Coordinated Seller-only simulation approval required') WHERE $wrongIdentity OR (NEW.status='ADMIN_APPROVED' AND $approval); END");
                DB::unprepared("CREATE TRIGGER sim_refund_request_update BEFORE UPDATE ON return_refund_requests BEGIN SELECT RAISE(ABORT,'Coordinated Seller-only simulation approval required') WHERE $wrongIdentity OR $unregistered OR ((NEW.status='ADMIN_APPROVED' OR OLD.status='ADMIN_APPROVED') AND $approval); END");
                DB::unprepared("CREATE TRIGGER sim_refund_request_delete BEFORE DELETE ON return_refund_requests WHEN EXISTS (SELECT 1 FROM simulation_refund_scopes WHERE return_refund_request_id=OLD.id) BEGIN SELECT RAISE(ABORT,'Simulation request identity must be retained'); END");
                foreach (self::NATIVE as $kind=>$table) foreach (['INSERT'=>'NEW','UPDATE'=>'NEW','DELETE'=>'OLD'] as $op=>$row) {
                    $condition=$this->nativeCondition($kind,$row);
                    if ($op==='UPDATE') $condition='('.$condition.') OR ('.$this->nativeCondition($kind,'OLD').')';
                    DB::unprepared('CREATE TRIGGER sim_refund_'.$kind.'_'.strtolower($op)." BEFORE $op ON $table WHEN $condition BEGIN SELECT RAISE(ABORT,'Coordinated simulation refund obligation required'); END");
                }
                foreach (['UPDATE','DELETE'] as $op) DB::unprepared('CREATE TRIGGER sim_refund_scope_'.strtolower($op)." BEFORE $op ON simulation_refund_scopes BEGIN SELECT RAISE(ABORT,'Simulation refund scope is immutable'); END");
            }
        });
    }

    private function nativeCondition(string $kind,string $row): string
    {
        $bad="EXISTS (SELECT 1 FROM simulation_refund_scopes x WHERE x.return_refund_request_id=$row.return_refund_request_id
            AND (x.workflow_mode<>'SELLER_REVIEW' OR NOT EXISTS (SELECT 1 FROM simulation_settlement_mutex WHERE id=1 AND operation_open=1)))";
        if ($kind!=='ledger') return $bad;
        return "$row.type='REFUND_ADJUSTMENT' AND (($bad) OR EXISTS (SELECT 1 FROM simulation_settlements s WHERE (s.seller_id=$row.seller_id OR s.seller_order_id=$row.seller_order_id)
            AND (NOT EXISTS (SELECT 1 FROM simulation_refund_scopes x WHERE x.return_refund_request_id=$row.return_refund_request_id AND x.simulation_settlement_id=s.id AND x.workflow_mode='SELLER_REVIEW')
                OR NOT EXISTS (SELECT 1 FROM simulation_settlement_mutex WHERE id=1 AND operation_open=1))))";
    }

    private function nativeBody(string $kind): string
    {
        $old=$this->nativeCondition($kind,'OLD');$new=$this->nativeCondition($kind,'NEW');
        return "BEGIN IF TG_OP<>'INSERT' THEN IF $old THEN RAISE EXCEPTION 'Coordinated simulation refund obligation required'; END IF; END IF; IF TG_OP<>'DELETE' THEN IF $new THEN RAISE EXCEPTION 'Coordinated simulation refund obligation required'; END IF; RETURN NEW; END IF; RETURN OLD; END";
    }

    private function function(string $name,string $body): void
    {
        DB::unprepared("CREATE FUNCTION public.$name() RETURNS trigger LANGUAGE plpgsql SET search_path=pg_catalog,public,pg_temp AS \$\$ $body \$\$");
    }

    private function secure(): void
    {
        $roles=DB::table('pg_roles')->whereIn('rolname',['anon','authenticated'])->pluck('rolname')->all();
        $clients='PUBLIC'.implode('',array_map(fn($r)=>', "'.$r.'"',$roles));
        DB::statement('REVOKE ALL PRIVILEGES ON TABLE public.simulation_refund_scopes FROM '.$clients);
        DB::statement('REVOKE ALL PRIVILEGES (return_refund_request_id,simulation_settlement_id,order_item_id,workflow_mode) ON TABLE public.simulation_refund_scopes FROM '.$clients);
        DB::statement('ALTER TABLE public.simulation_refund_scopes ENABLE ROW LEVEL SECURITY');
        if (DB::selectOne("SELECT pg_get_serial_sequence('public.simulation_refund_scopes','return_refund_request_id') AS name")->name!==null) throw new RuntimeException('Unexpected approval scope sequence.');
        foreach (['sim_refund_register','sim_refund_request_guard','sim_refund_accounting_guard','sim_refund_ledger_guard','sim_refund_funding_guard','sim_refund_scope_immutable','sim_refund_coverage'] as $fn) DB::statement("REVOKE ALL ON FUNCTION public.$fn() FROM $clients");
        foreach ($roles as $role) {
            $bad=DB::selectOne("SELECT r.rolsuper OR r.rolbypassrls OR pg_has_role(r.rolname,c.relowner,'MEMBER') OR EXISTS (SELECT 1 FROM pg_roles p WHERE (p.rolsuper OR p.rolbypassrls) AND pg_has_role(r.oid,p.oid,'MEMBER')) OR has_table_privilege(r.rolname,c.oid,'SELECT,INSERT,UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER') OR has_any_column_privilege(r.rolname,c.oid,'SELECT,INSERT,UPDATE,REFERENCES') AS allowed FROM pg_roles r CROSS JOIN pg_class c WHERE r.rolname=? AND c.oid='public.simulation_refund_scopes'::regclass",[$role]);
            if (!$bad || $bad->allowed) throw new RuntimeException('Unsafe inherited approval scope privileges.');
            if ((int)DB::selectOne('SHOW server_version_num')->server_version_num>=170000 && DB::selectOne("SELECT has_table_privilege(?,'public.simulation_refund_scopes','MAINTAIN') AS allowed",[$role])->allowed) throw new RuntimeException('Unsafe scope maintenance privilege.');
        }
        if (DB::table('pg_policies')->where('schemaname','public')->where('tablename',self::TABLE)->exists()) throw new RuntimeException('Unexpected scope RLS policy.');
    }

    public function down(): void
    {
        if (DB::table(self::TABLE)->exists()) throw new RuntimeException('Simulation refund scope/history must be retained.');
        DB::transaction(function () {
            $drop=function(string $name,string $table) { DB::statement(DB::getDriverName()==='pgsql'?"DROP TRIGGER $name ON public.$table":"DROP TRIGGER $name"); };
            $drop('sim_refund_scope_register','return_refund_requests');
            foreach (['update','delete'] as $op) { $drop('sim_refund_request_'.$op,'return_refund_requests');$drop('sim_refund_scope_'.$op,self::TABLE); }
            foreach (self::NATIVE as $kind=>$table) foreach (['insert','update','delete'] as $op) $drop('sim_refund_'.$kind.'_'.$op,$table);
            if (DB::getDriverName()==='pgsql') {
                foreach (self::VALIDATED as $table) $drop('sim_refund_coverage',$table);
                foreach (['sim_refund_register','sim_refund_request_guard','sim_refund_accounting_guard','sim_refund_ledger_guard','sim_refund_funding_guard','sim_refund_scope_immutable','sim_refund_coverage'] as $fn) DB::statement("DROP FUNCTION public.$fn()");
            }
            Schema::drop(self::TABLE);
        });
    }
};
