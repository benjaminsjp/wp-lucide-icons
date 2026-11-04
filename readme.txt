=== WP Icons ===
Contributors: kimlukas
Tags: icons, svg, lucide, phosphor, block
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Inline SVG icons from Lucide, Material Symbols, Phosphor, Tabler, Simple Icons and more, via a block, toolbar button, or shortcode.

== Description ==

WP Icons lets you place icons from open-source icon libraries anywhere in WordPress. Icons are printed as inline SVG, so they follow your text color, scale cleanly, and need no icon font or external script on the front end.

= Features =

* **Icon** block for the block editor, with library, icon, size, color, style or weight, and stroke width (stroke-based styles)
* **Insert icon** on the paragraph, heading, and other rich-text toolbars. Inline icons follow the surrounding text size and color
* **Insert icon** button in the classic editor (TinyMCE)
* `[wp_icon]` shortcode, plus the legacy `[lucide_icon]` alias
* Admin **Library** to browse icons, preview options, and copy a shortcode
* **Settings** for default size, color, and library, plus per-library style, stroke width, and color, and for enabling each library
* Icon catalogs ship with the plugin. Optionally, a newer catalog can be downloaded in the background and cached on your site
* Live preview in Flatsome UX Builder

= Icon libraries =

All bundled, at these versions:

* **Lucide** 1.61.0 (ISC; icons derived from Feather are MIT)
* **Material Symbols** 0.48.1, Outlined style (Apache-2.0)
* **Simple Icons** 16.36.0 (CC0-1.0 project license; brand logos are trademarks of their owners, and some icons carry other terms)
* **Phosphor** 2.1.1 (MIT)
* **Tabler Icons** 3.50.0 (MIT)
* **Heroicons** 2.2.0 (MIT)
* **Ionicons** 8.1.0 (MIT)
* **Octicons** 19.42.0 (MIT; GitHub logos follow GitHub's logo guidelines)

Simple Icons under non-commercial, no-derivatives, AGPL, GPL-only, or custom licenses, and the GitHub and Copilot logos in Octicons, are left out.

License links are listed under "Third-party libraries & licenses" below.

= Shortcode =

`[wp_icon library="lucide" name="heart" size="24" color="currentColor" stroke="2"]`

`[wp_icon library="material-symbols" name="favorite" size="24" color="currentColor"]`

Attributes: `library`, `name`, `size`, `color` (a CSS color, or `brand` for Simple Icons), `stroke` (stroke-based styles), `weight` (Phosphor), `style` (Tabler, Heroicons, Ionicons), `class`, and `title`. Omitted attributes fall back to **Appearance > WP Icons > Settings**. An empty `title` hides the icon from assistive technology.

= External requests and privacy =

* **Visitors:** icons are printed as inline SVG from the bundled or cached catalog. Front-end page views never contact npm, a CDN, or any other third party for icons.
* **Settings page:** opening **Appearance > WP Icons > Settings** asks the npm registry (registry.npmjs.org) for the list of published versions of each library, to fill the "CDN version" list. The answer is cached for 12 hours. This happens even when every library uses the bundled source.
* **CDN source (optional):** when a library is enabled and set to the CDN source, your server downloads that package's metadata and archive from the npm registry shortly after you save, then once a day via WP-Cron. The catalog is stored in `wp-content/uploads/wp-icons/`.
* These are server-to-server requests. The npm registry sees your server's IP address and the default WordPress user agent, which includes your site URL. No visitor data is sent.
* npm privacy policy: https://docs.npmjs.com/policies/privacy

== Trademarks & usage responsibility ==

WP Icons makes it quick to use existing open-source icon sets inside WordPress. It does not create or own those icons.

* **Third-party works.** Every icon set is the work of its authors and is licensed under its own terms (see "Third-party libraries & licenses"). WP Icons only provides access to the icons. It does not grant any rights beyond those licenses.
* **Brand icons.** Logos and brand marks (for example in Simple Icons, or the GitHub logos in Octicons) are trademarks of their owners. Owners often publish their own usage guidelines. Using a brand icon does not mean the brand owner endorses you, your site, or WP Icons.
* **Your responsibility.** You, as the site owner or user, decide which icons to use and where. You are responsible for making sure that use complies with each icon's license and with any trademark owner's guidelines.
* **No warranty.** WP Icons is free software, provided "as is" without warranty of any kind, as described in the GNU General Public License.

== Installation ==

1. In **Plugins > Add New > Upload Plugin**, upload `wp-lucide-icons.zip`, or extract it into `wp-content/plugins/wp-lucide-icons/`.
2. Activate **WP Icons**.
3. Optional: set defaults and enabled libraries in **Appearance > WP Icons > Settings**.
4. Add the **Icon** block, use **Insert icon** on a text toolbar, or use the `[wp_icon]` shortcode.

Requires WordPress 6.6 or newer and PHP 8.1 or newer. The release zip already contains everything the plugin needs.

== Frequently Asked Questions ==

= Does the plugin load icons from a CDN on my pages? =

No. Icons are printed as inline SVG from catalogs stored on your site. Visitors' browsers never request icons from a third party.

= What does the "CDN" icon source do? =

It downloads a newer published version of a library from the npm registry in the background and caches it on your site. Use it only when the plugin's bundled catalog is out of date. If the download fails or the cache is empty, the bundled catalog is used.

= Which icon libraries are included? =

Lucide, Material Symbols (Outlined), Simple Icons, Phosphor, Tabler Icons, Heroicons, Ionicons, and Octicons. You can turn each one on or off in **Appearance > WP Icons > Settings**.

= Can I use brand icons freely? =

Not necessarily. Brand logos are trademarks of their owners and often have their own usage guidelines, even when the icon file itself is freely licensed. Check the owner's guidelines before using a brand icon. See "Trademarks & usage responsibility".

= Does it work with the classic editor and page builders? =

Yes. There is an **Insert icon** button in the classic editor (TinyMCE), and the shortcode works anywhere shortcodes run. Inline icons also preview correctly in Flatsome UX Builder.

= Can I render an icon from PHP? =

Yes. `wpicons_get_icon( 'lucide:check' )` returns the SVG markup, and `wpicons_icon()` prints it. The string is already escaped; do not pass it through `wp_kses_post()`. A reference is `library:name` or `library:name:variant` (for example `phosphor:heart:bold`). A bare name uses the default library from Settings. An unknown name or a disabled library returns an empty string.

`wpicons_get_icon( 'lucide:check', [ 'size' => 20, 'title' => 'Done' ] )` sets the size and an accessible name. Without a title the icon is decorative. On a button or link, leave the icon decorative and name the control.

The lower-level call `( new WPIcons\Icons\Renderer() )->fromAttributes( [ 'library' => 'lucide', 'name' => 'heart' ] )` still works. An omitted library uses Lucide, and an omitted size is 24.

= Can another plugin open the icon picker? =

In the editor, enqueue the `wpicons-api` script (or depend on it) and call `wpIcons.openPicker()` or `wpIcons.getIcon( 'tabler:star' )`. That script is for users who can edit posts. On the front end, use the PHP helpers.

= What is removed when I uninstall the plugin? =

Its settings, cached catalogs in `wp-content/uploads/wp-icons/`, transients, and scheduled cron event. Icons already placed in content keep their block or shortcode markup.

== Screenshots ==

1. Icon Library: browse icons, preview size, color, and stroke, and copy a shortcode.
2. Icon Library with no icon selected.
3. Settings: default size, color, stroke width, and library, plus per-library source.
4. Settings after saving, with an inline CDN version error.

== Changelog ==

= 2.0.0 =
* Added: Icon block in the Media category, with library, name, size, color, style or weight, and stroke width.
* Added: Eight bundled libraries: Lucide, Material Symbols, Simple Icons, Phosphor, Tabler Icons, Heroicons, Ionicons, and Octicons, with style, weight, and brand-color options.
* Added: Library and Settings under Appearance > WP Icons (edit_theme_options), as tabs on one page.
* Added: Settings for defaults and for enabling each library, its source (plugin bundle or cached CDN), style, stroke width, and color.
* Added: `[wp_icon]` shortcode with a `library` attribute.
* Added: Optional cached CDN catalog refresh per library.
* Added: Inline icons in rich text (paragraph, heading, and other text toolbars).
* Added: `wpicons_get_icon()`, `wpicons_icon()`, `wpicons_icon_exists()`, and `wpicons_get_libraries()`.
* Added: Hooks `wpicons_loaded`, `wpicons_icon_args`, and `wpicons_icon_svg`.
* Added: Editor script `wpicons-api` (`window.wpIcons`) for previews and the icon picker.
* Added: English and Norwegian translations.
* Changed: Plugin renamed from Lucide Icons to WP Icons.
* Changed: Icon SVG comes from catalogs stored in the plugin; the editor no longer loads `lucide.min.js`.
* Changed: Classic editor button now uses the shared icon picker with live preview.
* Changed: The block inserter, rich text toolbar, and classic editor button use the shapes mark.
* Changed: Icons with an accessible name use role="img" and aria-label. Decorative icons stay hidden from assistive tech.
* Changed: Requires WordPress 6.6 and PHP 8.1.
* Deprecated: `[lucide_icon]` remains as a compatibility alias for Lucide icons.

= 1.3.1 - 2025-05-07 =
* Fixed: Icon dropdown on installs that are not at the domain root.

= 1.3.0 - 2025-05-07 =
* Changed: Bundled Lucide icons updated from 0.487.0 to 0.507.0.

= 1.2.0 - 2025-04-02 =
* Changed: Ship Lucide locally instead of fetching icons from the Lucide API.

= 1.1.0 - 2025-03-03 =
* Added: More icon ids.
* Changed: Icons render as inline SVG.

= 1.0.4 - 2025-02-28 =
* Fixed: Conflict with Gravity Forms.

= 1.0.3 - 2025-02-27 =
* Changed: Fuse.js bundled instead of loaded from a CDN. Dropdown styling updated.

= 1.0.2 - 2025-02-24 =
* Fixed: Icons not rendering in the admin menu.

= 1.0.1 - 2025-02-04 =
* Added: Icon options and more reliable insertion from the text editor.

= 1.0.0 - 2025-01-03 =
* Initial release as Lucide Icons.

== Upgrade Notice ==

= 2.0.0 =
Renamed to WP Icons. Adds the Icon block, eight icon libraries, and PHP helpers. Requires WordPress 6.6 and PHP 8.1. Existing `[lucide_icon]` shortcodes keep working.

== Third-party libraries & licenses ==

The icons are third-party works and keep their own licenses. WP Icons itself is GPLv2 or later.

Bundled:

* Lucide - ISC License; icons derived from Feather are MIT. https://github.com/lucide-icons/lucide/blob/main/LICENSE
* Material Symbols - Apache License 2.0. https://github.com/google/material-design-icons/blob/master/LICENSE
* Simple Icons - CC0-1.0 for the project. Brand logos are trademarks of their owners, and some icons carry other terms. https://github.com/simple-icons/simple-icons/blob/develop/LICENSE.md
* Phosphor - MIT. https://github.com/phosphor-icons/core/blob/main/LICENSE
* Tabler Icons - MIT. https://github.com/tabler/tabler-icons/blob/main/LICENSE
* Heroicons - MIT. https://github.com/tailwindlabs/heroicons/blob/master/LICENSE
* Ionicons - MIT. https://github.com/ionic-team/ionicons/blob/main/LICENSE
* Octicons - MIT. GitHub logos follow https://github.com/logos. https://github.com/primer/octicons/blob/main/LICENSE