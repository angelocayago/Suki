# Demo image provenance and mapping

This folder contains one original AI-generated, unbranded product mockup for
each retained `lahat-meron-v1.json` record. Files are `lm001.webp` through
`lm060.webp`; `manifest.json` maps every stable key to its filename, title,
dimensions, and SHA-256 checksum.

The source images are synthetic demo representations, not photos of actual
goods. They contain no third-party marketplace photography, private seller media,
government IDs, logos, price labels, or watermarks. The shared image resolver
serves their checksum-matched public copies from
`public/images/demo-products/lahat-meron/` using installation-relative URLs.

These assets are retained for existing catalog media, safe cleanup of known
historical `suki-demo:*` records, and local presentation marketplace data. They
are not used by a product importer or a bulk approval feature. Ordinary seller
uploads retain their existing storage behavior.

`../prepare-images.php` is an offline asset-preparation utility. It does not boot
Laravel or connect to a database. Do not remove or replace files without updating
the manifest and verifying the required consumers.
