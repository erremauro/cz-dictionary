# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.7] 2026-11-07
### Fixed
- `[lemma name="…"]` now links to the tag with that name instead of a slug derived from the name, which broke when the tag's real slug differs (e.g. `bhikṣu` → `/lemma/bhikku/`, not `/lemma/bhiksu/`; `Śakra` → `/lemma/sakra/`, not `/lemma/akra/`). Falls back to the derived slug when no tag has that name; an explicit `slug="…"` is used as is.
- Plugin header version aligned with the changelog.

## [0.2.6] 2026-09-11
### Fixed
- Fix missing trailing slash on internal /lemma/ links (ACF short_definition/description cross-references, REST API definition_url, JS fallback URL) — was generating unnecessary 301 redirects on ~1150 internal links

## [0.2.5] 2026-07-31
### Fixed
- The `lemma` shortcode's generated URL now consistently ends with a trailing slash.

## [0.2.4] 2026-06-12
### Changed
- Updated author metadata to Roberto Mauro.
- Aligned plugin header version with repository release history.

## [0.2.3] 2026-02-04
### Fix
- Fix lemma underline border color

## [0.2.2]
### Fix
- The lemma shortcut property "name" is now optional.

## [0.2.1]
## Update
- The shortcut property "show_as" has been integrated between the opening and closing tags.
## Fix
- Fix the lemma shortcut pagination that was truncating words
- Add `popup=false` option to render a plain lemma link without popup behavior.

## [0.2.0] 2026-01-27
### Update
- Add the lemma shortcut to render lemma popup notes.
### Fix
- Fix card display on mobile

## [0.1.1] 2025-01-31
### Fix
- Fix dictionary container box sizing

## [0.1.0] 2025-01-31
- Initial release.
- Live search across tag fields and ACF term meta.
- Infinite scroll for results.
- Shows name, readings, short definition, and lemma link.
- Theme-aligned styling (dark/light via CSS variables).

[Unreleased]: https://github.com/erremauro/cz-dictionary/compare/v0.2.5...HEAD
[0.2.5]: https://github.com/erremauro/cz-dictionary/releases/tag/v0.2.5
[0.2.4]: https://github.com/erremauro/cz-dictionary/releases/tag/v0.2.4
[0.2.3]: https://github.com/erremauro/cz-dictionary/releases/tag/v0.2.3
[0.2.2]: https://github.com/erremauro/cz-dictionary/releases/tag/v0.2.2
[0.2.1]: https://github.com/erremauro/cz-dictionary/releases/tag/v0.2.1
[0.2.0]: https://github.com/erremauro/cz-dictionary/releases/tag/v0.2.0
[0.1.1]: https://github.com/erremauro/cz-dictionary/releases/tag/v0.1.1
[0.1.0]: https://github.com/erremauro/cz-dictionary/releases/tag/v0.1.0
