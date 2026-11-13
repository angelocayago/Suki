# Presentation marketplace data

`presentation:marketplace-data` is an explicit, guarded generator for fictional local/testing presentation data. It never runs as a seeder, has no HTTP route, and is a dry run unless `--execute` is supplied.

It creates internally tracked fictional buyers, sellers, a provider, hubs, riders, approved catalog products with committed local media, and a small workflow-consistent order spread. Names and descriptions are customer-facing marketplace content; provenance is held only in `presentation_import_records`.

Preview against an isolated test database:

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=':memory:' DB_URL='' \
php artisan presentation:marketplace-data \
  --connection=sqlite --expect-environment=testing --confirm-database=':memory:'
```

After reviewing the preview, an isolated local or test execution additionally requires:

```text
--execute --confirm-execution="GENERATE FICTIONAL PRESENTATION DATA"
```

The command refuses PostgreSQL, connection URLs, production, unconfirmed database paths, and non-explicit connections. It does not overwrite or delete normal records; idempotent reruns use the internal provenance table. A production/shared-database rollout requires a separate reviewed plan, backup, and team approval. Do not run it against Supabase.

The standard 60-product importer may be run separately through its own review process. Together with this bounded expansion it provides a larger presentation catalog while maintaining Batch 6A image and publication rules.
