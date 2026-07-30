# Public Discovery Boundary

Screenshot Group 08 introduces an intentionally narrow public-discovery read
model. It does not make the private archive public.

## Publication Workflow

An Owner prepares a `PublicShowcaseEntry` for a reviewed media item and moves
it through draft, review, published or withdrawn state. Public pages select
only published entries. Draft, review and withdrawn records remain inside the
verified Owner workspace.

`SocialPublicationReceipt` records whether a public card was queued, published,
failed or withdrawn. External references and provider responses are retained
as operational facts but are never rendered by the public or evidence views.

## Location Privacy

`PublicMapPoint` stores the reviewed public place label and the precision used
for publication. Public map queries require all three conditions:

- the parent showcase entry is published;
- privacy review is complete; and
- precision is neighbourhood, town or region.

Exact coordinates are rejected from public output even if a record is
accidentally marked privacy-reviewed. Coordinates are used only to calculate a
non-geographic display position in memory, then removed before the view is
rendered. The browser receives a public title, a reviewed place label, a
precision label and the display position—not latitude or longitude.

## Access Boundary

`/discover` and `/discover/map` are deliberately public and contain only the
restricted read models above. `/admin/public-discovery` requires an
authenticated, verified Owner. A public-discovery record never grants archive
access, original-file access, family-branch access or permission to inspect
the underlying media item.

Automated release tests cover public state filtering, reduced-precision map
filtering, exact-coordinate rejection and the Owner authorization boundary.
