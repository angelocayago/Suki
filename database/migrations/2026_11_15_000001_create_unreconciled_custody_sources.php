<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        if(!in_array(DB::getDriverName(),['pgsql','sqlite'],true))throw new RuntimeException('Reviewed PostgreSQL/SQLite required.');
        // Security and object creation remain atomic, even for a direct operator call.
        DB::transaction(function(){
            foreach(['buyer_refund_fundings','logistics_cod_remittance_items','cod_remittance_audits']as$table)
                if(!Schema::hasTable($table))throw new RuntimeException('Reviewed custody/wallet prerequisites required.');
            Schema::create('custody_sources',function(Blueprint $t){
                $t->id();$t->string('source_identity',120)->unique();
                $t->enum('kind',['SIMULATED_ACCEPTED_COD']);$t->enum('state',['UNRECONCILED']);$t->string('currency',3)->default('PHP');
                $t->foreignId('seller_id')->constrained()->restrictOnDelete();$t->foreignId('order_id')->constrained()->restrictOnDelete();
                $t->foreignId('seller_order_id')->unique()->constrained()->restrictOnDelete();$t->foreignId('shipment_id')->unique()->constrained()->restrictOnDelete();
                $t->foreignId('rider_cod_remittance_id')->unique()->constrained()->restrictOnDelete();
                $t->foreignId('logistics_cod_remittance_id')->constrained()->restrictOnDelete();
                $t->foreignId('logistics_cod_remittance_item_id')->unique()->constrained()->restrictOnDelete();
                $t->foreignId('accepted_by')->constrained('users')->restrictOnDelete();$t->timestamp('accepted_at');
                $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();$t->timestamp('recorded_at');
                foreach(['accepted_minor','unreconciled_minor','allocatable_minor','committed_minor','consumed_minor']as$field)$t->bigInteger($field);
                $t->json('components');$t->string('provenance_hash',64);$t->index(['seller_id','recorded_at']);
            });
            // Unknown liabilities are not zero liabilities. No positive allocation is possible.
            $shape="kind = 'SIMULATED_ACCEPTED_COD' AND state = 'UNRECONCILED' AND currency = 'PHP' AND accepted_minor > 0 AND unreconciled_minor = accepted_minor AND allocatable_minor = 0 AND committed_minor = 0 AND consumed_minor = 0 AND length(provenance_hash) = 64";
            if(DB::getDriverName()==='pgsql'){
                DB::statement('ALTER TABLE public.custody_sources ADD CONSTRAINT custody_sources_unreconciled_shape CHECK ('.$shape.')');
                $this->securePostgres();
            }else{
                $columns=Schema::getColumnListing('custody_sources');
                $new=preg_replace_callback('/\b[a-z_]+\b/',fn($m)=>in_array($m[0],$columns,true)?'NEW.'.$m[0]:$m[0],$shape);
                foreach(['accepted_minor','unreconciled_minor','allocatable_minor','committed_minor','consumed_minor']as$field)$new.=" AND typeof(NEW.".$field.") = 'integer'";
                foreach(['INSERT','UPDATE']as$operation)DB::unprepared('CREATE TRIGGER custody_sources_shape_'.strtolower($operation).' AFTER '.$operation.' ON custody_sources WHEN NOT ('.$new.") BEGIN SELECT RAISE(ABORT, 'Invalid unreconciled custody source'); END");
            }
        });
    }
    private function securePostgres(): void
    {
        $roles=DB::table('pg_roles')->whereIn('rolname',['anon','authenticated'])->pluck('rolname')->all();
        $grantees='PUBLIC'.implode('',array_map(fn($r)=>', "'.$r.'"',$roles));
        DB::statement('REVOKE ALL PRIVILEGES ON TABLE public.custody_sources FROM '.$grantees);
        $columns=implode(',',array_map(fn($c)=>'"'.str_replace('"','""',$c).'"',Schema::getColumnListing('custody_sources')));
        DB::statement('REVOKE ALL PRIVILEGES ('.$columns.') ON TABLE public.custody_sources FROM '.$grantees);
        DB::statement('ALTER TABLE public.custody_sources ENABLE ROW LEVEL SECURITY');
        $sequence=DB::selectOne("SELECT pg_get_serial_sequence('public.custody_sources','id') AS name")->name;
        if($sequence!=='public.custody_sources_id_seq')throw new RuntimeException('Unexpected custody source sequence; migration rolled back.');
        DB::statement('REVOKE ALL PRIVILEGES ON SEQUENCE public.custody_sources_id_seq FROM '.$grantees);
        foreach($roles as$role){
            $unsafe=DB::selectOne("SELECT r.rolsuper OR r.rolbypassrls OR pg_has_role(r.rolname,c.relowner,'MEMBER') OR EXISTS (SELECT 1 FROM pg_roles p WHERE (p.rolsuper OR p.rolbypassrls) AND pg_has_role(r.oid,p.oid,'MEMBER')) OR has_table_privilege(r.rolname,c.oid,'SELECT,INSERT,UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER') OR has_any_column_privilege(r.rolname,c.oid,'SELECT,INSERT,UPDATE,REFERENCES') OR has_sequence_privilege(r.rolname,'public.custody_sources_id_seq','USAGE,SELECT,UPDATE') AS allowed FROM pg_roles r CROSS JOIN pg_class c WHERE r.rolname=? AND c.oid='public.custody_sources'::regclass",[$role]);
            if(!$unsafe||$unsafe->allowed)throw new RuntimeException('Inherited custody client privileges remain; migration rolled back.');
            if((int)DB::selectOne('SHOW server_version_num')->server_version_num>=170000&&DB::selectOne("SELECT has_table_privilege(?,'public.custody_sources','MAINTAIN') AS allowed",[$role])->allowed)throw new RuntimeException('Client maintenance privileges remain; migration rolled back.');
        }
        if(DB::table('pg_policies')->where('schemaname','public')->where('tablename','custody_sources')->exists())throw new RuntimeException('Unexpected custody policy; migration rolled back.');
    }
    public function down(): void
    {
        if(DB::table('custody_sources')->exists())throw new RuntimeException('Recorded custody provenance must be retained; destructive rollback refused.');
        Schema::drop('custody_sources');
    }
};
