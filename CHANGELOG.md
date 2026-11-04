# Changelog

All notable changes to this project will hopefully be documented in this file. (No promises)

The format for this changelog is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).  
Dates use the [ISO 8601 date format](https://www.iso.org/iso-8601-date-and-time-format.html) of `YYYY-MM-DD`.

## [Unreleased]

### Changed

- The Icon block now appears in the Media category.
- WP Icons now lives under **Appearance → WP Icons**, with Library and Settings as tabs on one page. Old `admin.php?page=wp-icons` and `admin.php?page=wp-icons-settings` links redirect there.
- Admin pages, settings saves, and the licenses link now require `edit_theme_options` instead of `manage_options`. A role that has `manage_options` but not `edit_theme_options` can no longer open them.

## [2.0.0] - 2026-10-21

### Added

- Gutenberg **Icon** block with library, name, size, color, and Lucide stroke width
- Material Symbols Outlined as a second bundled icon library
- Admin **Library** browser and top-level WP Icons menu
- Settings for defaults and per-library source (plugin bundle or cached CDN)
- `[wp_icon]` shortcode with a `library` attribute
- Optional cached CDN catalog refresh per library when the plugin bundle is stale
- Inline icons in Gutenberg RichText (paragraph, heading, and other text toolbars), with size, color, and stroke options

### Changed

- Plugin renamed from Lucide Icons to WP Icons
- Icon SVG comes from catalogs stored in the plugin, and the editor no longer loads bundled `lucide.min.js`
- Text editor insert button replaced by the shared icon picker, with live preview and size, color, and stroke options
- Styling uses theme colors
- PHP classes load through a Composer PSR-4 autoload (`vendor/` is required)
- WordPress minimum version is 6.6 and PHP minimum version is 8.1

### Deprecated

- `[lucide_icon]` remains as a compatibility alias for Lucide icons

## [1.3.1] - 2025-05-07

### Fixed

- Icon dropdown on installs that are not at the domain root (for example `example.com/wordpress/`)

## [1.3.0] - 2025-05-07

### Changed

- Bundled Lucide icons updated from 0.487.0 to 0.507.0

## [1.2.0] - 2025-04-02

### Changed

- Ship `js/lib/lucide.min.js` instead of fetching icons from the Lucide API

## [1.1.0] - 2025-03-03

### Added

- More icon ids

### Changed

- Icons render as inline SVG so Lucide does not have to create them on the page

### Removed

- AJAX injection into the editor script

## [1.0.4] - 2025-02-28

### Fixed

- Conflict with Gravity Forms

## [1.0.3] - 2025-02-27

### Changed

- Fuse.js bundled as `js/lib/fuse.min.js` instead of loaded from a CDN
- Dropdown styling updated

## [1.0.2] - 2025-02-24

### Fixed

- Icons not rendering in the admin menu

## [1.0.1] - 2025-02-04

### Added

- Icon options and more reliable insertion from the text editor

### Changed

- Style and script cache-busting uses the file modification time

## [1.0.0] - 2025-01-03

### Added

- Initial release as Lucide Icons (plugin header version `1.0`)
- `[lucide_icon]` shortcode with `name`, `size`, `color`, and `width`
- **Insert Lucide icon** button in the text editor
- Icons loaded from `unpkg.com/lucide@latest`

[2.0.0]: https://github.com/kimlukasmyrvold/wp-lucide-icons/compare/3d3d6448d710a1620daf4f7ca2e65710c50fd27b...HEAD
[1.3.1]: https://github.com/kimlukasmyrvold/wp-lucide-icons/compare/4fb3e6c23af4d0dd36fcc240e37ab4a8bc670ead...3d3d6448d710a1620daf4f7ca2e65710c50fd27b
[1.3.0]: https://github.com/kimlukasmyrvold/wp-lucide-icons/compare/7279b067627e742c6de5d453a4ef9fcde9ce3eda...4fb3e6c23af4d0dd36fcc240e37ab4a8bc670ead
[1.2.0]: https://github.com/kimlukasmyrvold/wp-lucide-icons/compare/69ab9d807cf6978daeb01b66e933b39f055d6daf...7279b067627e742c6de5d453a4ef9fcde9ce3eda
[1.1.0]: https://github.com/kimlukasmyrvold/wp-lucide-icons/compare/1b8bbacbc2eed86052d0950f54577050f2e21e9f...69ab9d807cf6978daeb01b66e933b39f055d6daf
[1.0.4]: https://github.com/kimlukasmyrvold/wp-lucide-icons/compare/d7e12f1dbdc41616ab3d99500047d44926d0daef...1b8bbacbc2eed86052d0950f54577050f2e21e9f
[1.0.3]: https://github.com/kimlukasmyrvold/wp-lucide-icons/compare/b3f4ee82f731fb69efefdac76e48798ac9bf4eaa...d7e12f1dbdc41616ab3d99500047d44926d0daef
[1.0.2]: https://github.com/kimlukasmyrvold/wp-lucide-icons/compare/91bc77caf9eaad9ae3566651018f0f1a758ea013...b3f4ee82f731fb69efefdac76e48798ac9bf4eaa
[1.0.1]: https://github.com/kimlukasmyrvold/wp-lucide-icons/compare/2bdd0a5506cef1d946f8167a0cfa79423baf21d3...91bc77caf9eaad9ae3566651018f0f1a758ea013
[1.0.0]: https://github.com/kimlukasmyrvold/wp-lucide-icons/commit/2bdd0a5506cef1d946f8167a0cfa79423baf21d3
