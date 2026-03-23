# SEO & Accessibility Audit — waaseyaa.org

**Date:** 2026-03-22
**Auditor:** Claude Code
**Site type:** Symfony 7.x + Twig, dark theme, server-rendered

---

## SEO Findings

### Present (good)

| Item | Status |
|------|--------|
| `<title>` with brand suffix | Per-page, dynamic |
| `<meta name="description">` | Unique per page |
| `<meta name="viewport">` | Correct |
| `<html lang="en">` | Present |
| Semantic heading hierarchy | h1 per page, h2/h3/h4 nested correctly |
| HTTPS + compression | TLS via ACME, gzip + zstd |
| Clean URL structure | `/docs/{category}/{slug}` |

### Missing (critical)

| Issue | Impact | Fix |
|-------|--------|-----|
| **No Open Graph tags** | Social sharing shows no preview | Add `og:title`, `og:description`, `og:url`, `og:type`, `og:image` to `base.html.twig` |
| **No Twitter Card tags** | Same for Twitter/X | Add `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` |
| **No canonical URLs** | Risk of duplicate content indexing | Add `<link rel="canonical" href="...">` per page |
| **No `robots.txt`** | Crawlers get 404 for it (Caddy has handler but no file) | Create `public/robots.txt` with sitemap reference |
| **No `sitemap.xml`** | Search engines can't discover all pages | Generate sitemap (static or dynamic route) |
| **No structured data** | No rich results in search | Add JSON-LD: `Organization`, `WebSite`, `BreadcrumbList` (docs) |
| **No favicon / `<link rel="icon">`** | Browser tabs show generic icon, search results lack branding | Add favicon files and meta tags |

### Missing (nice to have)

| Issue | Fix |
|-------|-----|
| No `<link rel="preconnect">` for CDN resources (Prism.js) | Add preconnect to `cdn.jsdelivr.net` |
| Footer links lack `rel="noopener"` on external links | Add to Discord/GitHub links |
| No RSS/Atom feed for docs updates | Consider for developer audience |

---

## Accessibility Findings (WCAG 2.1 AA)

### Passing

| Item | Notes |
|------|-------|
| Semantic HTML landmarks | `<nav>`, `<main>`, `<footer>`, `<aside>`, `<article>` |
| Heading hierarchy | Correct nesting throughout |
| Language attribute | `lang="en"` on `<html>` |
| Responsive layout | Breakpoints at 600/768/1024px |
| No auto-playing media | Clean server-rendered pages |
| Sufficient text size | 16px base, line-height 1.7 |

### Failing

#### Critical (WCAG A)

| Issue | WCAG | Location | Fix |
|-------|------|----------|-----|
| **No skip link** | 2.4.1 Bypass Blocks | `base.html.twig` | Add `<a href="#main" class="skip-link">Skip to content</a>` before nav; add `id="main"` to `<main>` |
| **No focus indicators** | 2.4.7 Focus Visible | `site.css` globally | Add `:focus-visible` outline to all interactive elements |
| **Anchor links invisible to keyboard** | 2.1.1 Keyboard | `.docs-anchor` (opacity: 0) | Show on `:focus` not just parent `:hover` |
| **Docs sidebar hidden on mobile with no alternative** | 1.3.1 Info and Relationships | `@media (max-width: 1024px)` | Sidebar uses `display: none` — add hamburger menu or in-page TOC |

#### Serious (WCAG AA)

| Issue | WCAG | Location | Fix |
|-------|------|----------|-----|
| **Muted text contrast too low** | 1.4.3 Contrast (Minimum) | `#9ca3af` on `#0f1117` = **4.26:1** (fails AA for normal text, needs 4.5:1) | Lighten to `#a8b0bd` (~4.6:1) or `#b0b8c4` (~5.1:1) |
| **Discord link contrast** | 1.4.3 | `#5865F2` on `#1a1d27` = ~3.9:1 (fails AA) | Lighten to `#7289DA` or add underline |
| **No `aria-current="page"`** | 1.3.1 | Active nav links use `.active` class only | Add `aria-current="page"` to active link |
| **No `prefers-reduced-motion`** | 2.3.3 Animation from Interactions | `scroll-behavior: smooth`, transitions | Add `@media (prefers-reduced-motion: reduce)` to disable |

#### Moderate (WCAG AA)

| Issue | WCAG | Location | Fix |
|-------|------|----------|-----|
| External links lack indication | 1.3.1 | Discord, GitHub links in nav/footer | Add visual indicator or `aria-label="Discord (opens in new tab)"` with `target="_blank"` |
| Breadcrumb lacks `<nav aria-label="Breadcrumb">` | 1.3.1 | `docs/page.html.twig` | Wrap in `<nav aria-label="Breadcrumb">` |
| Code blocks lack accessible labeling | 1.3.1 | Prism.js blocks | Add `aria-label` or preceding heading |
| Docs prev/next links lack `rel="prev"`/`rel="next"` | — (SEO + a11y) | `docs/page.html.twig` | Add `rel` attributes |

---

## Security Headers (Caddyfile)

The Caddyfile is missing standard security headers:

```
header {
    X-Content-Type-Options "nosniff"
    X-Frame-Options "DENY"
    Referrer-Policy "strict-origin-when-cross-origin"
    Permissions-Policy "camera=(), microphone=(), geolocation=()"
    Content-Security-Policy "default-src 'self'; script-src 'self' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; font-src 'self'; img-src 'self' data:; connect-src 'self'"
}
```

---

## Contrast Calculations

| Foreground | Background | Ratio | AA Normal | AA Large |
|-----------|-----------|-------|-----------|----------|
| `#e4e4e7` (text) | `#0f1117` (bg) | **13.2:1** | Pass | Pass |
| `#9ca3af` (muted) | `#0f1117` (bg) | **4.26:1** | **Fail** | Pass |
| `#9ca3af` (muted) | `#1a1d27` (surface) | **3.73:1** | **Fail** | **Fail** |
| `#f59e0b` (accent) | `#0f1117` (bg) | **7.4:1** | Pass | Pass |
| `#f59e0b` (accent) | `#1a1d27` (surface) | **6.5:1** | Pass | Pass |
| `#5865F2` (discord) | `#1a1d27` (surface) | **3.9:1** | **Fail** | Pass |

---

## Recommended Priority

1. **Skip link + focus styles** — most impactful a11y fix, affects every page
2. **Muted text contrast** — single CSS variable change, fixes all pages
3. **Open Graph + Twitter Card tags** — biggest SEO win for social discovery
4. **robots.txt + sitemap.xml** — search engine crawlability
5. **Canonical URLs** — prevents duplicate indexing
6. **`prefers-reduced-motion`** — small CSS addition
7. **Security headers** — Caddyfile change
8. **Structured data (JSON-LD)** — rich search results
9. **`aria-current`, breadcrumb nav, external link indicators** — a11y polish
10. **Mobile docs navigation** — sidebar alternative for <1024px
