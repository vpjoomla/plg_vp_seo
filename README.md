# VP SEO (Free)

**Proper SEO titles, OpenGraph and Twitter Cards for Joomla.**

VP SEO is a lightweight Joomla **system plugin** that fixes two gaps Joomla leaves
open: there is no per-article browser title (so your `<title>` ends up identical to
the on-page H1), and the core outputs no OpenGraph or Twitter Card tags at all.
VP SEO adds both — plus auto-generated descriptions and SVG-safe social images —
and works automatically on every front-end page.

This repository contains the **Free** edition. A **Pro** edition with title
templates, per-article social overrides, canonical management and a live in-editor
social-card preview is available at <https://vpjoomla.com/extensions/vp-seo>.

## Features (Free)

- **Separate SEO Title** — a dedicated title field per article, independent of the H1.
- **OpenGraph & Twitter Cards** — `og:title`, `og:description`, `og:type`, `og:url`,
  `og:site_name`, `og:locale`, `og:image`, and the matching `twitter:*` tags.
- **Auto descriptions** — generates a meta description from the article text when none is set.
- **SVG-safe images** — skips SVGs social platforms can't render, with a default-image fallback.
- **Article timestamps** — `article:published_time` / `article:modified_time`.
- Honours Joomla's "Include Site Name in Page Titles" setting.

## Free vs Pro

| | Free | Pro |
|---|:---:|:---:|
| SEO Title (title ≠ H1) | ✓ | ✓ |
| OpenGraph & Twitter Cards | ✓ | ✓ |
| Auto meta description | ✓ | ✓ |
| SVG-safe images + default image | ✓ | ✓ |
| Title templates (tokens) | — | ✓ |
| Per-article social description & image | — | ✓ |
| Canonical URL (manual + auto) | — | ✓ |
| `article:author` / `section` / `tag` | — | ✓ |
| `og:image` dimensions, type & alt | — | ✓ |
| Live social-card preview in editor | — | ✓ |

## Requirements

- Joomla **5.x** or **6.x**
- PHP **8.1+**

## Installation

1. Download the latest `plg_system_vpseo_free_vX.Y.Z.zip` from
   [Releases](../../releases).
2. In Joomla: **System → Install → Extensions → Upload Package File**.
3. Enable **System - VP SEO** under **System → Manage → Plugins**.
4. Open any article → **Publishing** tab → fill **SEO Title** (or leave it; VP SEO
   falls back to the article title).

## License

GNU General Public License v2 or later. See [LICENSE.txt](LICENSE.txt).

---

Made by [VPJoomla](https://vpjoomla.com) · *The Joomla!® name and trademarks are used
under a limited license from Open Source Matters, Inc. VPJoomla is not affiliated with
or endorsed by Open Source Matters or The Joomla! Project.*
