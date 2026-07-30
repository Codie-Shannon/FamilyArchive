# Secure and Federated Communication

Screenshot Group 10 adds consent-first public direct messages, versioned
encrypted-envelope records, private attachment scan states, constrained
site-guidance records and official business-messaging bridge records.

## Recipient and Consent Boundary

Public identities use non-identifying aliases. A direct-message request is
scoped to its recipient and remains `pending` until that recipient explicitly
accepts it. The authenticated read model:

- selects only threads whose `recipient_user_id` matches the current user;
- reads message-envelope and attachment summaries only for accepted threads;
- never treats a public alias as a verified family identity; and
- never exposes another recipient's thread, sender alias or attachment.

The view deliberately excludes moderation fingerprints, internal thread IDs,
ciphertext, wrapped content keys, content digests, private storage keys,
checksums and plaintext private messages.

## Encrypted Envelopes and Attachments

Encrypted envelopes are versioned and require ciphertext, a wrapped content
key and a hexadecimal SHA-256 content digest. The current implementation
validates the envelope contract without inspecting plaintext.

End-to-end encryption remains disabled at runtime until interoperable
key-management, device recovery and browser-client handling are implemented
and tested. Repository evidence therefore reports `Runtime setup required`
rather than claiming live encryption.

Attachments remain private and can be `pending`, `clean` or `rejected`.
Only safe display facts from authorized accepted threads are rendered. A
pending or rejected attachment is unavailable at the access boundary.

## Guidance Bot Boundary

The site-guidance bot is disabled by default. Its configuration permanently
prohibits access to the private archive. Stored interaction records are
redacted operational evidence only; prompts and responses are not displayed
on the Owner dashboard.

## Official Business Messaging Only

The bridge model covers:

- WhatsApp Business Cloud API; and
- Messenger Platform.

Both integrations fail closed while credentials are absent. The operations
view exposes only provider, readiness and grouped delivery states. It excludes
credentials, provider message identifiers and metadata.

These bridges do not provide access to arbitrary personal WhatsApp or
Messenger chats, and the product must never imply that they do.
