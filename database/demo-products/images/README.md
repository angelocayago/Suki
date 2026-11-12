# Demo image provenance and mapping

This folder contains one original AI-generated, unbranded product mockup for each
existing `lahat-meron-v1.json` record. Files are `lm001.webp` through `lm060.webp`.
The matching dataset key, title and description were supplied individually to
OpenAI's built-in image-generation tool. No marketplace listing photos, private
Seller media, government IDs or web-sourced stock photographs were used.

These are synthetic demo representations, not photographs of actual goods offered
by the Seller. They add no brand, certification, warranty or authenticity claims.
No third-party photo-license attribution is attached because no third-party photo
collection was imported. Keep this provenance with the project assets.

The common generation brief requested a recognizable product matching its title
and description, appropriate materials and quantities, a square studio composition,
warm ivory background, soft shadows, restrained sage accents, generous margins,
and no lettering, logos, price labels or watermarks. Each generation produced one
product image; variant products share their product's image.

`manifest.json` maps all 60 stable keys to deterministic filenames, product titles,
dimensions, SHA-256 checksums and the source method. Every asset is optimized to
640 x 640 WebP (quality 82). The dataset's names, descriptions, prices, stock and
variant options are unchanged.

`../prepare-images.php` is the offline preparation script. It accepts a local JSON
inventory with `key`, `name`, `description`, and `source` fields pointing at the
generated source PNGs. It verifies the inventory against all 60 dataset records,
resizes proportionally without cropping, writes WebP files and creates the manifest.
It does not boot Laravel, connect to a database or generate images through an API.
Source inventories with machine-specific paths remain outside the repository.

Importer preflight requires all 60 manifest entries/files, valid dimensions and
WebP decoding, matching filenames and checksums, and files no larger than 4 MiB.
This intentionally detects accidental source edits. Regenerate the manifest through
asset preparation after an approved image replacement; do not bypass checksums.

New product imports copy the matching file onto the existing local `public` disk
at `products/<product-id>/<key>.webp`, then create one position-1 `product_images`
record. Normal Buyer/Seller/Admin image paths remain compatible. Existing products
are skipped, so this phase neither backfills nor replaces prior product media.

No real product import or shared-database access is authorized by asset preparation.
