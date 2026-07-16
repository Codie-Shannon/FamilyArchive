# Controlled quarantine persistence

Group 05 introduces the first retained-byte boundary. A Group 04 validated photo is revalidated, streamed once into `archive_quarantine` through an exclusive no-overwrite writer, SHA-256 hashed during streaming, byte-count verified, and only then marked retained. Absolute roots remain internal. Originals, derivatives and manifests are never touched.
