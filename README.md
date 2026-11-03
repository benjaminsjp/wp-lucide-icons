# WP Icons

A WordPress plugin for placing icons from eight open-source icon libraries, including [Lucide](https://lucide.dev/), [Material Symbols](https://fonts.google.com/icons) (Outlined), Phosphor, Tabler Icons, and Simple Icons. Icons render as inline SVG. See [Icon libraries](#icon-libraries) for the full list, versions, and licenses.

The plugin is **WP Icons**. The repository and plugin folder are `wp-lucide-icons`.

- Requires WordPress 6.6+ and PHP 8.1+
- License: [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)
- Site: [GitHub](https://github.com/kimlukasmyrvold/wp-lucide-icons)
- Issues: [GitHub](https://github.com/kimlukasmyrvold/wp-lucide-icons/issues)

## Features

- **Icon** block in the block editor
- **Insert icon** on paragraph, heading, and other RichText toolbars
- **Insert icon** button in the classic editor (TinyMCE)
- `[wp_icon]` shortcode
- Admin **Library** browser and **Settings**
- Bundled SVG catalogs, with an optional cached CDN refresh when the bundle is stale

In Flatsome UX Builder iframes, the plugin loads a small script so inline icons preview correctly.

## Icon libraries

All eight libraries ship with the plugin. Each catalog is generated from a pinned npm package version (below); the optional CDN source can download a newer version later. Catalogs load per library, only when a library is opened in the picker or used on a page.

| Library                                                       | Status  | Library id         | npm package                 | Version | License                                                                                                                                                                    |
| ------------------------------------------------------------- | ------- | ------------------ | --------------------------- | ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [Lucide](https://lucide.dev/)                                 | Bundled | `lucide`           | `lucide-static`             | 1.61.0  | [ISC](https://github.com/lucide-icons/lucide/blob/main/LICENSE). Icons derived from Feather are MIT.                                                                       |
| [Material Symbols](https://fonts.google.com/icons) (Outlined) | Bundled | `material-symbols` | `@material-symbols/svg-400` | 0.48.1  | [Apache-2.0](https://github.com/google/material-design-icons/blob/master/LICENSE)                                                                                          |
| [Simple Icons](https://simpleicons.org/)                      | Bundled | `simple-icons`     | `simple-icons`              | 16.36.0 | [CC0-1.0](https://github.com/simple-icons/simple-icons/blob/develop/LICENSE.md) for the project. Brand logos are trademarks of their owners; some icons carry other terms. |
| [Phosphor](https://phosphoricons.com/)                        | Bundled | `phosphor`         | `@phosphor-icons/core`      | 2.1.1   | [MIT](https://github.com/phosphor-icons/core/blob/main/LICENSE)                                                                                                            |
| [Tabler Icons](https://tabler.io/icons)                       | Bundled | `tabler`           | `@tabler/icons`             | 3.50.0  | [MIT](https://github.com/tabler/tabler-icons/blob/main/LICENSE)                                                                                                            |
| [Heroicons](https://heroicons.com/)                           | Bundled | `heroicons`        | `heroicons`                 | 2.2.0   | [MIT](https://github.com/tailwindlabs/heroicons/blob/master/LICENSE)                                                                                                       |
| [Ionicons](https://ionic.io/ionicons)                         | Bundled | `ionicons`         | `ionicons`                  | 8.1.0   | [MIT](https://github.com/ionic-team/ionicons/blob/main/LICENSE)                                                                                                            |
| [Octicons](https://primer.style/octicons/)                    | Bundled | `octicons`         | `@primer/octicons`          | 19.42.0 | [MIT](https://github.com/primer/octicons/blob/main/LICENSE). GitHub logos follow GitHub's [logo guidelines](https://github.com/logos).                                     |

Some icons are left out on purpose. Simple Icons whose own license is non-commercial, no-derivatives, AGPL, GPL-only, or custom are excluded, and so are the GitHub and Copilot logos in Octicons. The rules are in `config/icon-exclusions.json` (see `config/README.md`), and the license texts ship in `licenses/` and on **WP Icons → Settings → View licenses**.

Each icon set is a third-party work under its own license. See [Trademarks & usage responsibility](#trademarks--usage-responsibility) before using brand icons.

## Requirements

|           |              |
| --------- | ------------ |
| WordPress | 6.6 or newer |
| PHP       | 8.1 or newer |

A release zip already includes `vendor/` and `build/`. A git checkout does not.

## Install

### From a release zip

1. Upload `wp-lucide-icons.zip` in **Plugins → Add New → Upload Plugin**, or extract it into `wp-content/plugins/wp-lucide-icons/`.
2. Activate **WP Icons**.

The zip must contain `vendor/autoload.php` and the `build/` bundles. Without the autoloader, the plugin shows an admin error and does not load.

### From git

Composer does not pull PHP packages. It only generates the PSR-4 autoloader (`WPIcons\` → `src/includes/`). `bootstrap.php` requires `vendor/autoload.php`.

```bash
composer install
pnpm install
pnpm build
pnpm sass:build
```

Then activate the plugin from that directory. See [Development](#development) for scripts and icon sync.

## Usage

### Block editor

Insert the **Icon** block. Choose a library (or search all of them at once), pick an icon, then set size and color. Libraries with styles show a Weight or Style control, stroke-based styles show a stroke width, and Simple Icons can use each brand's own color.

To place an icon inside a paragraph, heading, or other text, use the **Insert icon** control on the block toolbar. Inline icons follow the surrounding text size and color.

### Classic editor (TinyMCE)

Use the star **Insert icon** button on the TinyMCE toolbar. The same control appears in the Classic block and other TinyMCE fields.

### Shortcode

```
[wp_icon library="lucide" name="heart" size="24" color="currentColor" stroke="2"]
[wp_icon library="material-symbols" name="favorite" size="24" color="currentColor"]
```

| Attribute | Default                               | Notes                                                                |
| --------- | ------------------------------------- | -------------------------------------------------------------------- |
| `library` | Settings → default library (`lucide`) | Any library id from [Icon libraries](#icon-libraries)                |
| `name`    | `circle`                              | Icon name in that library                                            |
| `size`    | Settings (24)                         |                                                                      |
| `color`   | Settings, per library                 | CSS color, or `brand` for the Simple Icons brand color               |
| `stroke`  | Settings, per library (2)             | Stroke-based styles only (Lucide, Tabler outline, Heroicons outline) |
| `weight`  | Settings, per library                 | Phosphor: `thin`, `light`, `regular`, `bold`, `fill`, `duotone`     |
| `style`   | Settings, per library                 | Tabler, Heroicons, and Ionicons styles, as shown on the Library page |
| `width`   | empty                                 | Legacy alias for `stroke` when `stroke` is omitted                   |
| `class`   | empty                                 | Extra class on the SVG                                               |
| `title`   | empty                                 | Accessible title. Empty means the icon is hidden from assistive tech |

Omitted attributes fall back to **WP Icons → Settings**.

`[lucide_icon name="heart" size="24" color="currentColor" width="2"]` still works. It is a Lucide-only alias; prefer `[wp_icon]`.

## Admin

WP Icons adds a top-level menu (capability `manage_options`):

- **Library** — browse bundled icons, preview options, copy a shortcode
- **Settings** — defaults, which libraries are enabled, and per-library source

Each library source is either **plugin** (the catalog shipped in the plugin) or **cdn** (a newer catalog downloaded from the npm registry). CDN downloads are cached under `wp-content/uploads/wp-icons/` by a background WP-Cron job and are never fetched during page views. If the cache is empty, the plugin uses the bundled catalog.

### External requests and privacy

- **Visitors:** icons are printed as inline SVG from the bundled or cached catalog. Front-end page views never contact npm, a CDN, or any other third party for icons.
- **Settings page:** opening **WP Icons → Settings** asks `registry.npmjs.org` for the list of published versions of each library's package, to fill the **CDN version** list. The answer is cached for 12 hours. This happens even when every library uses the **plugin** source.
- **CDN source:** when a library is enabled and set to **cdn**, the server downloads that package's metadata and tarball from the npm registry shortly after you save, then once a day. Nothing is downloaded for libraries on the **plugin** source.
- These are server-to-server requests made with `wp_remote_get()`. The npm registry sees your server's IP address and the default WordPress user agent, which includes your site URL. No visitor data is sent.
- Uninstalling the plugin deletes its settings, the cached catalogs in `wp-content/uploads/wp-icons/`, its transients, and its cron event.

If you rely on a privacy policy, mention the npm registry when you use the CDN source or the Settings page.

## Development

You need PHP 8.1+, [Composer](https://getcomposer.org/), Node.js, and [pnpm](https://pnpm.io/).

```bash
composer install
pnpm install
pnpm sync-icons   # only when regenerating bundled icon maps
pnpm build
pnpm sass:build
```

While editing, `pnpm start` rebuilds the JavaScript bundles, and `pnpm sass` watches SCSS.

### Scripts

| Command                    | What it does                                                                                     |
| -------------------------- | ------------------------------------------------------------------------------------------------ |
| `pnpm start`               | `@wordpress/scripts` in watch mode. Writes `build/` (Icon block, admin library, TinyMCE picker). |
| `pnpm build`               | Production build of the same bundles.                                                            |
| `pnpm sass`                | Watch `src/assets/scss` and write compressed CSS to `src/assets/css`.                            |
| `pnpm sass:build`          | One-shot SCSS compile.                                                                           |
| `pnpm sync-icons`          | Download Lucide and Material Symbols and rewrite the generated PHP maps.                         |
| `pnpm sync-icons:lucide`   | Lucide only (`lucide-static`).                                                                   |
| `pnpm sync-icons:material` | Material Symbols Outlined only (`@material-symbols/svg-400`).                                    |
| `pnpm sync-icons:packages` | The six npm-package libraries, at the versions pinned in `package.json`, with the exclusions.     |
| `pnpm publish`             | Zip a release with `node publish.js wp-lucide-icons.php`. This is not `npm publish`.             |

Pass a version after `--` to pin a catalog. Without one, the script uses `latest`:

```bash
pnpm sync-icons:lucide -- 1.52.0
pnpm sync-icons:material -- 0.48.0
```

Do not hand-edit the generated files:

- `src/includes/Icons/LucideIcon.php`
- `src/includes/Icons/Material/material-symbols-outlined-map.php`

### Composer

`composer.json` requires PHP `>=8.1` and autoloads `WPIcons\` from `src/includes/`. There are no Composer packages.

```bash
composer install
composer dump-autoload
```

Before packing a release zip, regenerate an optimized autoloader:

```bash
composer install --no-dev --optimize-autoloader
```

`vendor/` is gitignored. A checkout without `composer install` will not run.

## Release zip

`publish.js` reads the `Version:` header in `wp-lucide-icons.php` and writes `releases/v{version}/wp-lucide-icons.zip`. It does not bump the version, create a git tag, or upload the zip.

1. Update the version in both places in `wp-lucide-icons.php`: the `Version:` header and the `WP_ICONS__VERSION` constant.
2. Update `package.json` and [CHANGELOG.md](CHANGELOG.md).
3. Build what the zip must contain:

```bash
composer install --no-dev --optimize-autoloader
pnpm build
pnpm sass:build
pnpm publish
```

The zip includes:

- `wp-lucide-icons.php`, `bootstrap.php`, `uninstall.php`
- `src/assets/css`, `src/assets/js`, `src/includes`, `src/languages`, `src/templates`
- `build/`
- `vendor/`

It omits `node_modules`, `scripts/`, SCSS, Composer and npm config, this README, the changelog, and `publish.js`.

Run `pnpm sync-icons` before that sequence when the bundled catalogs should change.

## PHP

Theme and plugin code can render an icon without a shortcode:

```php
use WPIcons\Icons\Renderer;

echo (new Renderer())->fromAttributes([
    'library' => 'lucide', // or material-symbols
    'name'    => 'heart',
    'size'    => 24,
    'color'   => 'currentColor',
    'stroke'  => 2,        // Lucide only
    'class'   => '',
    'title'   => 'Favorite',
]);
```

Use any library id from [Icon libraries](#icon-libraries). `stroke` applies to stroke-based styles, `weight` to Phosphor, and `style` to Tabler, Heroicons, and Ionicons. An unknown name or a disabled library returns an empty string.

## Trademarks & usage responsibility

WP Icons makes it quick to use existing open-source icon sets inside WordPress. It does not create or own those icons.

- **Third-party works.** Every icon set is the work of its authors and is licensed under its own terms, listed in [Icon libraries](#icon-libraries). WP Icons only provides access to the icons. It does not grant any rights beyond those licenses.
- **Brand icons.** Logos and brand marks (for example in Simple Icons, or the GitHub logos in Octicons) are trademarks of their owners. Owners often publish their own usage guidelines. Using a brand icon does not mean the brand owner endorses you, your site, or WP Icons.
- **Your responsibility.** You, as the site owner or user, decide which icons to use and where. You are responsible for making sure that use complies with each icon's license and with any trademark owner's guidelines.
- **No warranty.** WP Icons is free software, provided "as is" without warranty of any kind, as described in the GPL.

## License

WP Icons is licensed under [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html). The bundled icon sets keep their own licenses, listed in [Icon libraries](#icon-libraries).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
