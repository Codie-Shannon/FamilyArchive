# Screenshot Group 02 Evidence Plan

Status: closed — evidence approved.

## Planned Captures

| Filename | Surface | Evidence |
|---|---|---|
| `01_v028_Release_Dashboard.png` | `/dashboard` desktop | v0.28.0 Family Access & Conversation boundary |
| `02_Public_Family_Conversations.png` | `/conversations` signed out | Moderated public content separated from private archive knowledge |
| `03_Approved_Member_Post.png` | `/conversations` signed in | Approved fictional account post and moderated-display result |
| `04_Anonymous_Message_Moderation.png` | Anonymous contact result | Pending moderation without account or archive access |
| `05_Conversation_Mobile.png` | `/conversations` mobile | Responsive public conversation and contact surface |
| `06_Access_And_Abuse_Boundaries.png` | Denied account/archive states | Pending account, locked thread and private archive denial |
| `07_v028_Validation.png` | Manual PowerShell validation | Tests, build, audits, migrations and repository state |

## Capture Requirements

- Fixture: isolated database with fictional New Zealand accounts and messages
- Real identities, email addresses and private family content: prohibited
- IP addresses, fingerprints and correlation tokens rendered: no
- Private archive records or original-storage information rendered: no
- Human approval: complete

The first six screenshots were captured from an isolated fictional browser
fixture. The repository owner ran the validation script manually and supplied
screenshot 07. All seven PNGs passed human review before canonical evidence
was committed.
