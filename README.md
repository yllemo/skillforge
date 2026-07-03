# SkillForge for DokuWiki

**SkillForge** is a powerful DokuWiki plugin that exports entire namespaces as AI-ready Markdown packages.

It creates a downloadable ZIP package with a `SKILL.md` entry file, converted Markdown pages, an `index.md` file, a machine-readable `skill.json` manifest and optional media files.

The package is designed to work well with Anthropic-style `SKILL.md` workflows while remaining useful as a plain Markdown knowledge package for GitHub, Obsidian, MkDocs, AIWiki and similar tools.

## Features

- Select a DokuWiki namespace from a dropdown in the admin interface
- Use a configured source page, default `start.txt`, as `SKILL.md`
- Convert DokuWiki `.txt` pages to Markdown `.md`
- Generate YAML frontmatter for exported pages
- Generate `index.md`
- Generate `skill.json`
- Include media files from the matching media namespace
- Create ZIP files without requiring PHP `ZipArchive`
- Download through a dedicated DokuWiki action to avoid admin-page redirects
- Show formatted metadata and a ZIP download button on skill source pages

## Example output

```text
skilltest-skill/
├── SKILL.md
├── index.md
├── skill.json
├── examples.md
├── checklist.md
└── media/
```

## Installation

Copy the `skillforge` folder into your DokuWiki plugin directory:

```text
lib/plugins/skillforge
```

Then open DokuWiki admin:

```text
Admin → SkillForge
```

## Configuration

SkillForge settings are available through DokuWiki's configuration manager. All settings have sensible defaults and work out of the box.

| Setting | Default | Description |
|---|---:|---|
| `default_skill_source` | `start.txt` | Page inside the selected namespace used to generate `SKILL.md` |
| `output_skill_filename` | `SKILL.md` | Name of the generated skill entry file |
| `recursive` | `on` | Include subnamespaces |
| `include_media` | `on` | Include files from the matching media namespace |
| `generate_index` | `on` | Generate `index.md` |
| `zip_filename_pattern` | `{namespace}-skill-{date}.zip` | ZIP filename pattern |
| `show_rendered_metadata` | `on` | Show formatted YAML metadata where `<frontmatter>` or `<skillmeta>` is used |
| `show_page_download_button` | `on` | Show an admin-only ZIP download button beside rendered skill metadata |
| `download_button_label` | `Download SKILL.md (.zip)` | Text for the page download button |

## Metadata

Add metadata to the configured source page, usually `start.txt`:

```text
<frontmatter>
name: ai-prompting
description: Helps an AI assistant work with prompting knowledge.
version: 1.0.0
author: Henrik Yllemo
tags:
  - ai
  - prompting
  - dokuwiki
</frontmatter>
```

`<skillmeta>` is also supported for backward compatibility.

The metadata is written as YAML frontmatter at the top of `SKILL.md`.
In DokuWiki page rendering, the metadata block is shown as a formatted metadata panel instead of raw YAML. This can be disabled with `show_rendered_metadata`.

When `show_page_download_button` is enabled, admins also get a **Download skill ZIP** button on pages containing a metadata block. The button creates a fresh export for that page's namespace and uses the current page as the `SKILL.md` source.
The button text can be changed with `download_button_label`. It supports `{name}`, `{title}`, `{description}` and `{output}`, so a label like `Download {name} (.zip)` can use YAML frontmatter values.

## Export flow

1. Open `Admin → SkillForge`.
2. Select a namespace.
3. Choose whether to include subnamespaces and media.
4. Click **Forge Package**.
5. Download the generated ZIP file.

## ZIP support

SkillForge includes a small internal ZIP writer using the ZIP STORE method. It does not require PHP `ZipArchive` and does not depend on `inc/pclzip.lib.php`.

This is useful for lightweight DokuWiki installations such as DokuWiki-on-a-Stick with MicroApache.

## Markdown conversion

The current converter handles common DokuWiki syntax:

- Headings
- Internal links
- External links
- Images/media references
- Code/file blocks
- Bold and italic text
- Frontmatter blocks

Advanced DokuWiki syntax can be added incrementally.

## Files generated

| File | Purpose |
|---|---|
| `SKILL.md` | Main AI skill instruction file |
| `index.md` | Human-readable file index |
| `skill.json` | Machine-readable package manifest |
| `*.md` | Converted namespace pages |
| `media/` | Optional exported media files |

## Development notes

This release focuses on a practical, dependency-light export workflow. The next natural improvements are stronger link rewriting, richer media handling, validation and optional export profiles.

## License

MIT
