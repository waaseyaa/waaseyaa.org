# Signal Garden Frontend Art Prototype

**Date:** 2026-03-21
**Status:** Approved

## Overview

Build `Signal Garden`, a frontend-only art prototype for the DEV WeCoded 2026 `Frontend Art: Gender Equity` prompt. The piece presents an artistic social feed as a living collage that celebrates the visibility and joy of women in tech. Geography influences what the user sees, but the experience is not a literal social app or a production map product.

The prototype runs entirely in the browser with seeded data. It may be hosted within the Waaseyaa site or adjacent to it, but it does not depend on live ingestion, a backend API, or real-time platform integrations for the challenge submission.

## Goals

- Celebrate women in tech as publicly visible, self-possessed, and joyful.
- Use social-feed language without cloning a real platform UI.
- Make geography part of discovery by changing the composition as the user moves through regions.
- Embed AI as a poetic curator that adds connective meaning without becoming the main attraction.
- Keep scope small enough to ship as a polished frontend-art prototype.

## Non-Goals

- No live Facebook, X, or Instagram integrations.
- No North Cloud ingestion pipeline work for the challenge prototype.
- No backend persistence, authentication, moderation, or admin surface.
- No geographic precision requirements beyond broad regional grouping.
- No attempt to model a complete social network product.

## Core Experience

`Signal Garden` opens as a living collage composed of profile fragments, post excerpts, links, reactions, and decorative visual motifs. The overall effect should feel luminous and editorial rather than product-like. Cards may overlap, drift, bloom into place, and recede as the user explores.

The emotional center is `visibility + joy`. The people in the piece are already present and expressive. The UI should not frame them as asking for permission to be seen. Instead, the composition should communicate public presence, creativity, leadership, mentorship, and delight.

## Interaction Model

### Region Navigation

- The user explores via a stylized globe, field, or drag surface that represents movement across broad world regions.
- Geography acts as an artistic filter, not a GIS tool.
- Moving to a new region recomposes the collage:
  - Different creators surface
  - Different related links appear
  - Palette, motion, and density adjust
  - Region-specific thematic labels may appear

### Feed Behavior

- The feed is the primary stage.
- Visible items should feel partially familiar as social artifacts, but abstracted enough to avoid direct imitation of any one platform.
- Cards can include:
  - Profile identity block
  - Short post fragments
  - Platform or website links
  - Relation hints to other creators
  - Decorative counts or badges used as visual texture rather than analytics

### AI Curation

AI is embedded as a lightweight curator layer. It does not converse with the user and does not drive the whole experience.

Valid AI uses for the prototype:

- Generate short thematic overlays for the active region
- Produce connective phrases between related creators
- Summarize the current cluster as a “constellation” of themes
- Suggest ambient copy that frames the visible collage

For the prototype, this content can be prewritten, precomputed, or deterministically mocked if that produces a stronger final piece.

## Data Design

The prototype uses seeded JSON delivered with the frontend bundle. Profiles may be inspired by real public voices, but the implementation should avoid implying official endorsements unless the people are explicitly chosen and credited for that use.

### Suggested Seed Shape

```json
{
  "regions": [
    {
      "id": "north-america",
      "label": "North America",
      "palette": ["#f59e0b", "#ff7a59", "#ffd166"],
      "theme": "Visible leadership, public craft, generous mentorship"
    }
  ],
  "profiles": [
    {
      "id": "creator-1",
      "name": "Example Creator",
      "region": "north-america",
      "roles": ["Engineer", "Founder", "Speaker"],
      "tone": ["joy", "boldness", "craft"],
      "avatar": "/assets/signal-garden/example.jpg",
      "postFragments": ["Building in public and loving the work."],
      "links": [
        {"label": "Website", "url": "https://example.com"},
        {"label": "Profile", "url": "https://example.com/profile"}
      ],
      "relatedProfileIds": ["creator-2"],
      "aiOverlay": "A bright cluster of public builders and mentors."
    }
  ]
}
```

### Content Principles

- Use a curated set small enough to art-direct carefully.
- Prefer strong, short fragments over long text blocks.
- Include relationship data so the UI can draw visible links between people.
- Encode tone markers so visuals can respond to emotional texture, not just geography.

## Visual System

### Visual Direction

The piece should read as `artistic feed`, not `dashboard` and not `platform clone`.

Desired qualities:

- Luminous
- Layered
- Celebratory
- Editorial
- Slightly theatrical

Visual ingredients:

- Layered cards and panels
- Region-driven color atmospheres
- Soft glows and bloom effects
- Link lines or thread trails between related people
- Expressive typography for headings and overlays
- Motion that suggests emergence and gathering rather than notification churn

### Geography as Mood

Region changes should affect:

- Background gradients
- Card density and spacing
- Motion cadence
- Accent illustrations or motifs
- AI-curator text

This keeps geography meaningful without requiring a literal map-heavy layout.

### Accessibility

- Maintain readable text contrast despite decorative glow and overlap.
- Respect `prefers-reduced-motion`.
- Ensure keyboard focus can move through the main interactive regions and visible cards.
- Provide alt text or accessible labels for profiles and navigational controls.

## Technical Shape

The implementation should stay strictly frontend. A single-page app is sufficient.

### Suggested Architecture

- Static HTML shell
- Frontend state for active region and visible cluster
- Seeded JSON loaded locally
- Render layer for collage cards, relation lines, and overlays
- Small AI-curator adapter that reads seeded overlay content or deterministic generated text

### Candidate Stack

Use the smallest stack that makes visual iteration fast. Likely options:

- Vanilla JS + CSS if the composition is mostly DOM/CSS driven
- A lightweight React or Vite setup if component state and animation orchestration benefit from it

The deciding factor should be animation and layout control, not framework preference.

## Failure Handling

Because the prototype is seeded and static, failure handling is simple:

- If a region has too few profiles, widen the visible cluster and present a smaller but intentional composition.
- If AI-curator copy is unavailable, fall back to handcrafted text.
- If assets fail, preserve layout with placeholders rather than collapsing cards.
- If motion is reduced or disabled, the composition should still look deliberate as a mostly static collage.

## Testing

Testing should stay proportional to the prototype:

- Region changes update the visible collage and overlays correctly
- Seeded profiles and links render consistently
- Reduced-motion mode preserves usability
- Layout remains legible on desktop and mobile
- AI-curator fallbacks never block rendering

Visual regression or deterministic snapshot coverage is appropriate if the chosen stack supports it.

## Deliverable

The challenge submission deliverable is a polished, frontend-only art experience with seeded data that demonstrates:

- An artistic social-feed collage
- Geography-driven recomposition
- Embedded AI curation
- A clear celebration of women in tech through visibility and joy

## Future Expansion

If the prototype succeeds, later versions could add:

- North Cloud ingestion pipeline integration
- Real social profile ingestion with consent and curation
- More sophisticated geographic exploration
- Richer AI-generated thematic stitching
- Additional community storytelling modes

These are explicitly post-challenge ideas and not required for the prototype.
