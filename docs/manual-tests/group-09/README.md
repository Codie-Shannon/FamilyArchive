# Family Archive Group 09 manual proof

Use fictional Group 09 media only.

1. Seed `Database\\Seeders\\Group09DemoSeeder` and open `/admin/viewing-derivatives` as the verified Owner.
2. Open `PH_000901`, record original path, bytes and SHA-256, then generate photo-v1 derivatives.
3. Verify separate ready `web_display` and `thumbnail` WebP records on `archive_derivatives`, both parented to the original.
4. Verify web-display longest side is at most 2000, thumbnail at most 480, and neither output upscales the source.
5. Open the private previews. Confirm they expose derivatives only and no original path/hash/client filename.
6. Re-run generation and confirm idempotency. Run the focused collision/failure tests and confirm no overwrite or orphan object.
7. Confirm original bytes, path, size and SHA-256 are unchanged and no `edited_full` version exists.
8. Confirm guest redirect, non-owner 403, Owner access, and no original-download/public route.
