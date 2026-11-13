<?php

namespace App\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Validation\ValidationException;

/** Fixed-project identity checks; no network access occurs in configuration(). */
class DemoImportDatabase
{
    public const PROJECT = 'yzvcdrjriziejmecewci';
    public const CONFIRMATION = 'IMPORT 60 LAHAT MERON DEMO PRODUCTS';

    public function configuration(array $raw, array $flags): array
    {
        $db = (new ConfigurationUrlParser())->parseConfiguration($raw);
        $this->require(($db['driver'] ?? null) === 'pgsql', 'Only PostgreSQL is accepted by this guard.');
        $this->require(($flags['project'] ?? null) === self::PROJECT && !empty($flags['owner']) && ctype_digit((string)$flags['owner']), 'PostgreSQL/Supabase requires explicit project/database/owner confirmations.');
        $host = strtolower((string)($db['host'] ?? ''));
        $user = (string)($db['username'] ?? '');
        $direct = $host === 'db.'.self::PROJECT.'.supabase.co';
        $pooler = preg_match('/^[a-z0-9.-]+\.pooler\.supabase\.com$/D', $host) && $user === 'postgres.'.self::PROJECT;
        $this->require(($direct && $user === 'postgres') || $pooler, 'Configured PostgreSQL endpoint/tenant does not match the pinned Supabase project.');
        $this->require(empty($db['read']) && empty($db['write']) && empty($db['connect_via_database']) && empty($db['connect_via_port']) && empty($db['prefix']) && ($db['search_path'] ?? 'public') === 'public', 'Split, redirected, or alternate-schema PostgreSQL connections are refused.');
        $this->require(($db['sslmode'] ?? null) === 'verify-full', 'PostgreSQL requires verify-full TLS with a trusted certificate and hostname verification.');
        $database = $db['database'] ?? null;
        $this->require(is_string($database) && preg_match('/^[a-zA-Z0-9_-]+$/D',$database) && ($flags['database'] ?? null) === $database, 'Explicit PostgreSQL database confirmation does not match configuration.');
        $port = (string)($db['port'] ?? '5432');
        $this->require(ctype_digit($port) && (int)$port > 0 && (int)$port <= 65535, 'Invalid PostgreSQL port configuration.');
        foreach (['sslcert','sslkey','sslrootcert'] as $key) $this->require(empty($db[$key]) || !preg_match('/[;\r\n]/',(string)$db[$key]), 'Unsafe PostgreSQL TLS configuration is refused.');
        $this->require(empty($flags['execute']) || ($flags['confirmation'] ?? null) === self::CONFIRMATION, 'PostgreSQL execution requires the exact separate confirmation phrase.');
        $this->require(empty($flags['execute']) || (is_string($flags['plan'] ?? null) && preg_match('/^[a-f0-9]{64}$/D',$flags['plan'])), 'PostgreSQL execution requires the reviewed --confirm-plan fingerprint.');
        return ['database' => $database, 'role' => 'postgres', 'routing_hash' => hash('sha256',json_encode([$host,$port,$database,$user,$db['sslmode']],JSON_THROW_ON_ERROR))];
    }

    public function verifyConnectionConfiguration(ConnectionInterface $connection, array $identity): void
    {
        $actual = $this->configuration($connection->getConfig(), ['project'=>self::PROJECT,'owner'=>'1','database'=>$identity['database']]);
        $this->require(hash_equals($identity['routing_hash'], $actual['routing_hash']), 'Resolved connection routing differs from the confirmed PostgreSQL configuration.');
    }

    public function verifyServer(ConnectionInterface $connection, array $identity, bool $readOnly): void
    {
        $this->verifyConnectionConfiguration($connection, $identity);
        $server = $connection->selectOne("SELECT current_database() AS database_name, session_user AS role_name, current_setting('transaction_read_only') AS read_only");
        $this->require($server && $server->database_name === $identity['database'] && $server->role_name === $identity['role'] && $server->read_only === ($readOnly ? 'on' : 'off'), 'PostgreSQL server identity or transaction mode could not be verified.');
        $indexes = $connection->select("SELECT tablename,indexdef FROM pg_catalog.pg_indexes WHERE schemaname='public' AND tablename IN ('products','product_variants')");
        $found = [];
        foreach ($indexes as $index) {
            $definition = $index->indexdef;
            if (!str_starts_with($definition,'CREATE UNIQUE INDEX ') || str_contains($definition,' WHERE ')) continue;
            foreach (['products'=>['slug','seller_id, legacy_reference'],'product_variants'=>['sku']] as $table=>$columns) {
                foreach ($columns as $column) if ($index->tablename === $table && str_ends_with($definition,'('.$column.')')) $found[$table.':'.$column] = true;
            }
        }
        $this->require(count($found) === 3, 'Required unconditional catalog unique indexes are missing.');
    }

    private function require(mixed $condition, string $message): void
    {
        if (!$condition) throw ValidationException::withMessages(['demo_database'=>$message]);
    }
}
