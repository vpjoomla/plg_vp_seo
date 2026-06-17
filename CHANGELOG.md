# Changelog

All notable changes to VP SEO are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.0] — 2026-06-17

Initial public release.

### Added
- **SEO Title** field per article — browser title separate from the H1, in the Publishing tab.
- **OpenGraph** tags: `og:title`, `og:description`, `og:type`, `og:url`, `og:site_name`,
  `og:locale`, `og:image`.
- **Twitter Card** tags: `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`.
- **Auto meta description** generated from article intro text when none is set,
  trimmed to a search-friendly length (~160 chars; ~200 for social).
- **SVG-safe social images** — SVG candidates are skipped, with a configurable default image.
- `article:published_time` and `article:modified_time`.
- Honours Joomla's global "Include Site Name in Page Titles" setting.

### Pro (separate edition)
- Title templates with tokens, per-article social description/image, canonical
  management, `article:author`/`section`/`tag`, `og:image` dimensions/type/alt,
  and a live social-card preview in the editor.
