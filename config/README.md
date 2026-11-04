# Icon exclusions

`icon-exclusions.json` lists icons that WP Icons never bundles and never stores from a CDN download.

| Library      | Rule                                                                                                                                                                                                                                                          | Why                                                                                                                                                                                                                                                  |
| ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Simple Icons | Logos whose `license.type` in `simple-icons/data/simple-icons.json` matches one of `excludeLicenseTypes` (regular expressions, case-insensitive): non-commercial (`-NC`), no-derivatives (`-ND`), GPL/AGPL/LGPL-family, or `custom` (trademark-policy terms). | Simple Icons itself is CC0, but those logos come with terms that conflict with redistributing them on arbitrary sites. Logos without a `license` field are CC0. Permissive licenses are kept and listed in `licenses/simple-icons-icon-licenses.md`. |
| Octicons     | `mark-github`, `logo-github`, `logo-gist`, `lockup-github`, and every name starting with `copilot`.                                                                                                                                                           | GitHub and Copilot logos are GitHub trademarks; Octicons' MIT license does not cover them.                                                                                                                                                           |

Each library entry supports:

- `excludeNames`: exact icon names.
- `excludePrefixes`: name prefixes.
- `excludeLicenseTypes`: Simple Icons only; regular expressions matched against the logo's license type.

The same file is read by:

- `scripts/sync-packages.js`, which writes the bundled catalogs in `src/includes/Icons/Catalogs/`.
- `WPIcons\Icons\Exclusions`, which filters catalogs downloaded with the "CDN (npm, cached)" source.

After editing, run `pnpm sync-icons`, then rebuild. That regenerates every bundled catalog, including Lucide and Material Symbols.

Brand icons (all Simple Icons, Phosphor brand logos, Tabler `brand-*`, Ionicons `logo-*`) are kept but flagged as `brand` in the catalogs. The picker and Library page then show the trademark note and, where known, a link to the owner's guidelines.
