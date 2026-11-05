# Changelog

All notable changes to this project will hopefully be documented in this file. (No promises)

The format for this changelog is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).  
Dates use the [ISO 8601 date format](https://www.iso.org/iso-8601-date-and-time-format.html) of `YYYY-MM-DD`.

## [2.0.0]

### Added

- Gutenberg **Icon** block in the Media category, with library, name, size, color, style or weight, and stroke width
- Eight bundled libraries: Lucide, Material Symbols, Simple Icons, Phosphor, Tabler Icons, Heroicons, Ionicons, and Octicons, with style, weight, and brand-color options
- Admin **Library** and **Settings** under **Appearance → WP Icons** (`edit_theme_options`), as tabs on one page
- Settings for defaults and for enabling each library, its source (plugin bundle or cached CDN), style, stroke width, and color
- `[wp_icon]` shortcode with a `library` attribute
- Optional cached CDN catalog refresh per library when the plugin bundle is stale
- Inline icons in Gutenberg RichText (paragraph, heading, and other text toolbars), with size, color, and stroke options
- Public helpers `wpicons_get_icon()`, `wpicons_icon()`, `wpicons_icon_exists()`, and `wpicons_get_libraries()` for rendering icons from themes and plugins
- Hooks `wpicons_loaded`, `wpicons_icon_args`, and `wpicons_icon_svg`
- Editor script `wpicons-api` (`window.wpIcons`) with `getIcon`, a React `Icon`, `openPicker`, and `parseRef`
- English and Norwegian translations
- **Insert icon** on Elementor visual text controls
- Elementor **WP Icons** widget, with the same icon settings as the block plus alignment
- Flatsome UX Builder **WP Icons** element
- Optional `[wp_icon]` `align` attribute (`left`, `center`, or `right`)

### Changed

- Plugin renamed from Lucide Icons to WP Icons
- Icon SVG comes from catalogs stored in the plugin, and the editor no longer loads bundled `lucide.min.js`
- Text editor insert button replaced by the shared icon picker, with live preview and size, color, and stroke options
- The block inserter, rich text toolbar, and classic editor button use the Lucide shapes mark
- Icons with an accessible name use `role="img"` and `aria-label`. Decorative icons use `aria-hidden="true"` and `focusable="false"`
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
