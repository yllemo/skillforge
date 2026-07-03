# Changelog

## 1.1.0 - 2026-07-03

### Added

- Formatted DokuWiki rendering for `<frontmatter>` and `<skillmeta>` metadata blocks
- Admin-only page download button for exporting the current skill namespace as ZIP
- Settings to toggle rendered metadata and the page download button
- Configurable download button label with frontmatter placeholders
- Plugin stylesheet for the rendered metadata panel

## 1.0.0 - 2026-06-25

Initial release-ready package.

### Added

- Namespace dropdown in admin export screen
- Config-based SKILL source page, default `start.txt`
- DokuWiki `.txt` to Markdown `.md` conversion
- `SKILL.md` generation
- `index.md` generation
- `skill.json` manifest generation
- Optional media export
- Internal ZIP writer with no `ZipArchive` dependency
- Dedicated `do=skillforge_download` action for reliable downloads

### Changed

- Renamed generated `okf-index.md` to `index.md`
- Cleaned README for public release
- Updated plugin metadata for release
