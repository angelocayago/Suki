# Retained demo catalog assets

The Lahat Meron dataset, manifest, and committed WebP assets are retained only
for safe handling of existing historical demo-provenance records and for the
local presentation marketplace generator. They are not an import source.

`CatalogDemoCleanupService` reads `lahat-meron-v1.json` through
`DemoProductBatch` to identify known `suki-demo:*` references. Its
`catalog:cleanup-demo-content` command can preview or explicitly normalize only
those existing records, recording each applied change in `catalog_cleanup_audits`.
It does not create new products, alter ordinary seller products, or delete data.

`DemoProductImages` resolves the reserved, committed public paths under
`public/images/demo-products/lahat-meron/`. Do not remove the dataset, manifest,
or image files while cleanup, existing product media, or presentation data still
uses them. The public copies and source images must remain checksum-matched.

The dedicated demo product importer and its Admin bulk-approval workflow have
been removed. Normal seller submissions continue through the standard catalog
and product moderation flows.
