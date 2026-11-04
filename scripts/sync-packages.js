"use strict";

/**
 * Builds the bundled catalogs for every icon library from the exact versions pinned in
 * package.json devDependencies, and copies each library's LICENSE into licenses/.
 *
 *   pnpm sync-icons
 *
 * Output per library (one PHP file per style so a page only loads the style it renders):
 *   src/includes/Icons/Catalogs/<library>/index.php    version, counts, viewBoxes, glyph, brand meta
 *   src/includes/Icons/Catalogs/<library>/<style>.php  icon name => inner SVG markup
 *
 * Exclusions (trademark / license) come from config/icon-exclusions.json.
 * Versions come from the installed packages, not from a command-line argument.
 */

const fs = require("fs/promises");
const fsSync = require("fs");
const path = require("path");
const { pathToFileURL } = require("url");

const ROOT = path.resolve(__dirname, "..");
const NODE_MODULES = path.join(ROOT, "node_modules");
const CATALOG_DIR = path.join(ROOT, "src", "includes", "Icons", "Catalogs");
const LICENSE_DIR = path.join(ROOT, "licenses");
const EXCLUSIONS = JSON.parse(
    fsSync.readFileSync(
        path.join(ROOT, "config", "icon-exclusions.json"),
        "utf8",
    ),
);

function pkgDir(name) {
    return path.join(NODE_MODULES, ...name.split("/"));
}

function pkgVersion(name) {
    return JSON.parse(
        fsSync.readFileSync(path.join(pkgDir(name), "package.json"), "utf8"),
    ).version;
}

function phpString(value) {
    return `'${String(value).replace(/\\/g, "\\\\").replace(/'/g, "\\'")}'`;
}

function phpValue(value, indent = "    ") {
    if (value === null || value === undefined) {
        return "null";
    }
    if (typeof value === "boolean") {
        return value ? "true" : "false";
    }
    if (typeof value === "number") {
        return String(value);
    }
    if (typeof value === "string") {
        return phpString(value);
    }
    const next = `${indent}    `;
    if (Array.isArray(value)) {
        if (value.length === 0) {
            return "[]";
        }
        return `[\n${value.map((item) => `${next}${phpValue(item, next)},`).join("\n")}\n${indent}]`;
    }
    const entries = Object.entries(value);
    if (entries.length === 0) {
        return "[]";
    }
    return `[\n${entries.map(([key, item]) => `${next}${phpString(key)} => ${phpValue(item, next)},`).join("\n")}\n${indent}]`;
}

function markdownTable(headers, rows) {
    const widths = headers.map((header, index) =>
        Math.max(
            header.length,
            ...rows.map((row) => String(row[index] ?? "").length),
        ),
    );
    const line = (row) =>
        `| ${row.map((cell, index) => String(cell).padEnd(widths[index])).join(" | ")} |`;
    const rule = `| ${widths.map((width) => "-".repeat(width)).join(" | ")} |`;
    return [line(headers), rule, ...rows.map(line)];
}

function phpFile(comment, value) {
    return `<?php\n\nif (!\\defined('ABSPATH')) {\n    exit;\n}\n\n/**\n${comment
        .split("\n")
        .map((line) => ` * ${line}`.trimEnd())
        .join("\n")}\n */\nreturn ${phpValue(value, "")};\n`;
}

/** Inner markup of an SVG file: no XML prolog, root element or <title>, whitespace collapsed. */
function innerSvg(svg) {
    return svg
        .replace(/<\?xml[^>]*>/gi, "")
        .replace(/<!DOCTYPE[^>]*>/gi, "")
        .replace(/<!--[\s\S]*?-->/g, "")
        .replace(/<svg\b[^>]*>/i, "")
        .replace(/<\/svg>\s*$/i, "")
        .replace(/<title>[\s\S]*?<\/title>/gi, "")
        .replace(/\s+/g, " ")
        .replace(/>\s+</g, "><")
        .replace(/\s+\/>/g, "/>")
        .trim();
}

function viewBoxOf(svg) {
    const match = svg.match(/<svg\b[^>]*\bviewBox="([^"]+)"/i);
    return match ? match[1].trim() : "";
}

function exclusionRules(libraryId) {
    const rules = EXCLUSIONS.libraries[libraryId] || {};
    return {
        names: new Set(rules.excludeNames || []),
        prefixes: rules.excludePrefixes || [],
        licenses: (rules.excludeLicenseTypes || []).map(
            (pattern) => new RegExp(pattern, "i"),
        ),
    };
}

function isExcludedName(rules, name) {
    return (
        rules.names.has(name) ||
        rules.prefixes.some((prefix) => name.startsWith(prefix))
    );
}

async function readSvgDir(dir, mapName) {
    const files = (await fs.readdir(dir))
        .filter((file) => file.endsWith(".svg"))
        .sort();
    const icons = {};
    for (const file of files) {
        const name = mapName ? mapName(file.slice(0, -4)) : file.slice(0, -4);
        if (!name) {
            continue;
        }
        const svg = await fs.readFile(path.join(dir, file), "utf8");
        const inner = innerSvg(svg);
        if (inner) {
            icons[name] = { inner, viewBox: viewBoxOf(svg) };
        }
    }
    return icons;
}

/** Guidelines URLs from Simple Icons, used for brand logos in the other libraries too. */
function simpleIconsData() {
    const data = JSON.parse(
        fsSync.readFileSync(
            path.join(pkgDir("simple-icons"), "data", "simple-icons.json"),
            "utf8",
        ),
    );
    return Array.isArray(data) ? data : data.icons;
}

let guidelinesBySlug = null;
function guidelinesFor(slugCandidates) {
    if (!guidelinesBySlug) {
        guidelinesBySlug = new Map();
        for (const entry of simpleIconsData()) {
            if (entry.slug && typeof entry.guidelines === "string") {
                guidelinesBySlug.set(entry.slug, entry.guidelines);
            }
        }
    }
    for (const slug of slugCandidates) {
        const key = slug.replace(/-/g, "");
        if (guidelinesBySlug.has(key)) {
            return guidelinesBySlug.get(key);
        }
        if (guidelinesBySlug.has(slug)) {
            return guidelinesBySlug.get(slug);
        }
    }
    return null;
}

// ---------------------------------------------------------------------------------------------
// Library readers. Each returns { variants: { style: { name: {inner, viewBox} } }, brands: [], meta: {}, excluded: [] }
// ---------------------------------------------------------------------------------------------

async function readSimpleIcons() {
    const dir = pkgDir("simple-icons");
    const rules = exclusionRules("simple-icons");
    const icons = {};
    const meta = {};
    const excluded = [];
    const attributions = [];

    for (const entry of simpleIconsData()) {
        const slug = entry.slug;
        const licenseType =
            entry.license && entry.license.type
                ? String(entry.license.type)
                : "";
        if (isExcludedName(rules, slug)) {
            excluded.push({ name: slug, reason: "name" });
            continue;
        }
        if (
            licenseType &&
            rules.licenses.some((pattern) => pattern.test(licenseType))
        ) {
            excluded.push({
                name: slug,
                title: entry.title,
                reason: licenseType,
            });
            continue;
        }
        const file = path.join(dir, "icons", `${slug}.svg`);
        if (!fsSync.existsSync(file)) {
            continue;
        }
        const svg = await fs.readFile(file, "utf8");
        icons[slug] = { inner: innerSvg(svg), viewBox: viewBoxOf(svg) };
        const item = {
            title: entry.title,
            hex: String(entry.hex || "").toUpperCase(),
        };
        if (
            typeof entry.guidelines === "string" &&
            /^https?:\/\//.test(entry.guidelines)
        ) {
            item.guidelines = entry.guidelines;
        }
        if (licenseType && licenseType !== "CC0-1.0") {
            item.license = licenseType;
            attributions.push({
                slug,
                title: entry.title,
                type: licenseType,
                url: entry.license.url || "",
                source: entry.source || "",
            });
        }
        meta[slug] = item;
    }

    return {
        variants: { default: icons },
        brands: Object.keys(icons),
        meta,
        excluded,
        attributions,
    };
}

async function readPhosphor() {
    const dir = path.join(pkgDir("@phosphor-icons/core"), "assets");
    const weights = ["thin", "light", "regular", "bold", "fill", "duotone"];
    const variants = {};
    for (const weight of weights) {
        const suffix = weight === "regular" ? "" : `-${weight}`;
        variants[weight] = await readSvgDir(path.join(dir, weight), (base) => {
            if (!suffix) {
                return base;
            }
            return base.endsWith(suffix) ? base.slice(0, -suffix.length) : null;
        });
    }

    const mod = await import(
        pathToFileURL(
            path.join(pkgDir("@phosphor-icons/core"), "dist", "index.mjs"),
        ).href
    );
    const brands = mod.icons
        .filter(
            (icon) =>
                (icon.categories || []).includes("brands") &&
                /-logo(?:-|$)/.test(icon.name),
        )
        .map((icon) => icon.name)
        .filter((name) => variants.regular[name])
        .sort();
    const meta = {};
    for (const name of brands) {
        const guidelines = guidelinesFor([
            name.replace(/-logo(?:-simple)?$/, ""),
            name.replace(/-logo.*$/, ""),
        ]);
        if (guidelines) {
            meta[name] = { guidelines };
        }
    }

    return { variants, brands, meta, excluded: [] };
}

async function readTabler() {
    const dir = path.join(pkgDir("@tabler/icons"), "icons");
    const strip = (icons) => {
        for (const icon of Object.values(icons)) {
            icon.inner = icon.inner
                .replace(
                    /<path stroke="none" d="M0 0h24v24H0z" fill="none"\/>/g,
                    "",
                )
                .trim();
        }
        return icons;
    };
    const variants = {
        outline: strip(await readSvgDir(path.join(dir, "outline"))),
        filled: strip(await readSvgDir(path.join(dir, "filled"))),
    };
    const all = new Set([
        ...Object.keys(variants.outline),
        ...Object.keys(variants.filled),
    ]);
    const brands = [...all].filter((name) => name.startsWith("brand-")).sort();
    const meta = {};
    for (const name of brands) {
        const guidelines = guidelinesFor([name.replace(/^brand-/, "")]);
        if (guidelines) {
            meta[name] = { guidelines };
        }
    }
    return { variants, brands, meta, excluded: [] };
}

async function readHeroicons() {
    const dir = pkgDir("heroicons");
    return {
        variants: {
            outline: await readSvgDir(path.join(dir, "24", "outline")),
            solid: await readSvgDir(path.join(dir, "24", "solid")),
            mini: await readSvgDir(path.join(dir, "20", "solid")),
            micro: await readSvgDir(path.join(dir, "16", "solid")),
        },
        brands: [],
        meta: {},
        excluded: [],
    };
}

async function readIonicons() {
    const dir = path.join(pkgDir("ionicons"), "dist", "svg");
    const all = await readSvgDir(dir);
    const names = Object.keys(all);
    const base = new Set(
        names.filter(
            (name) =>
                !/-(outline|sharp)$/.test(name) ||
                !all[name.replace(/-(outline|sharp)$/, "")],
        ),
    );
    const variants = { outline: {}, filled: {}, sharp: {} };
    for (const name of names) {
        const match = name.match(/^(.*)-(outline|sharp)$/);
        if (match && base.has(match[1])) {
            variants[match[2]][match[1]] = all[name];
        } else {
            variants.filled[name] = all[name];
        }
    }
    const brands = [...base].filter((name) => name.startsWith("logo-")).sort();
    const meta = {};
    for (const name of brands) {
        const guidelines = guidelinesFor([name.replace(/^logo-/, "")]);
        if (guidelines) {
            meta[name] = { guidelines };
        }
    }
    return { variants, brands, meta, excluded: [] };
}

async function readFlat(libraryId, dir) {
    const rules = exclusionRules(libraryId);
    const icons = await readSvgDir(dir);
    const kept = {};
    const excluded = [];
    for (const [name, icon] of Object.entries(icons)) {
        if (isExcludedName(rules, name)) {
            excluded.push({ name, reason: "name" });
            continue;
        }
        kept[name] = icon;
    }
    return { variants: { default: kept }, brands: [], meta: {}, excluded };
}

async function readLucide() {
    return readFlat("lucide", path.join(pkgDir("lucide-static"), "icons"));
}

async function readMaterial() {
    return readFlat(
        "material-symbols",
        path.join(pkgDir("@material-symbols/svg-400"), "outlined"),
    );
}

async function readOcticons() {
    const data = JSON.parse(
        await fs.readFile(
            path.join(pkgDir("@primer/octicons"), "build", "data.json"),
            "utf8",
        ),
    );
    const rules = exclusionRules("octicons");
    const icons = {};
    const excluded = [];
    for (const name of Object.keys(data).sort()) {
        if (isExcludedName(rules, name)) {
            excluded.push({ name, reason: "trademark" });
            continue;
        }
        const heights = data[name].heights || {};
        const size = ["24", "16", "12", "48", "96"].find((key) => heights[key]);
        if (!size) {
            continue;
        }
        const entry = heights[size];
        icons[name] = {
            inner: innerSvg(`<svg>${entry.path}</svg>`),
            viewBox: `0 0 ${entry.width} ${size}`,
        };
    }
    return { variants: { default: icons }, brands: [], meta: {}, excluded };
}

/** Icon used as the library's mark in the admin UI. Written into index.php so one glyph does not load the style file. */
const GLYPHS = {
    lucide: { name: "feather", variant: "default" },
    "material-symbols": { name: "interests", variant: "default" },
    "simple-icons": { name: "simpleicons", variant: "default" },
    phosphor: { name: "atom", variant: "regular" },
    tabler: { name: "brand-tabler", variant: "outline" },
    heroicons: { name: "sparkles", variant: "outline" },
    ionicons: { name: "logo-ionic", variant: "filled" },
    octicons: { name: "telescope", variant: "default" },
};

const LIBRARIES = [
    {
        id: "lucide",
        pkg: "lucide-static",
        read: readLucide,
        defaultVariant: "default",
        listUnion: false,
        license: ["LICENSE"],
        spdx: "ISC",
    },
    {
        id: "material-symbols",
        pkg: "@material-symbols/svg-400",
        read: readMaterial,
        defaultVariant: "default",
        listUnion: false,
        license: ["LICENSE", "LICENSE.txt", "LICENSE.md"],
        licenseMode: "first",
        spdx: "Apache-2.0",
    },
    {
        id: "simple-icons",
        pkg: "simple-icons",
        read: readSimpleIcons,
        defaultVariant: "default",
        listUnion: false,
        license: ["LICENSE.md", "DISCLAIMER.md"],
        spdx: "CC0-1.0",
    },
    {
        id: "phosphor",
        pkg: "@phosphor-icons/core",
        read: readPhosphor,
        defaultVariant: "regular",
        listUnion: false,
        license: ["LICENSE"],
        spdx: "MIT",
    },
    {
        id: "tabler",
        pkg: "@tabler/icons",
        read: readTabler,
        defaultVariant: "outline",
        listUnion: false,
        license: ["LICENSE"],
        spdx: "MIT",
    },
    {
        id: "heroicons",
        pkg: "heroicons",
        read: readHeroicons,
        defaultVariant: "outline",
        listUnion: false,
        license: ["LICENSE"],
        spdx: "MIT",
    },
    {
        id: "ionicons",
        pkg: "ionicons",
        read: readIonicons,
        defaultVariant: "outline",
        listUnion: true,
        license: ["LICENSE"],
        spdx: "MIT",
    },
    {
        id: "octicons",
        pkg: "@primer/octicons",
        read: readOcticons,
        defaultVariant: "default",
        listUnion: false,
        license: ["LICENSE"],
        spdx: "MIT",
    },
];

async function writeLibrary(lib) {
    const version = pkgVersion(lib.pkg);
    const result = await lib.read();
    const outDir = path.join(CATALOG_DIR, lib.id);
    await fs.rm(outDir, { recursive: true, force: true });
    await fs.mkdir(outDir, { recursive: true });

    const variantCounts = {};
    const viewBoxes = {};
    const defaultViewBox = {};
    let bytes = 0;

    for (const [variant, icons] of Object.entries(result.variants)) {
        const names = Object.keys(icons).sort();
        variantCounts[variant] = names.length;

        // Most common viewBox is the style default; record only the exceptions.
        const tally = {};
        names.forEach((name) => {
            tally[icons[name].viewBox] = (tally[icons[name].viewBox] || 0) + 1;
        });
        const common =
            Object.entries(tally).sort((a, b) => b[1] - a[1])[0]?.[0] ||
            "0 0 24 24";
        defaultViewBox[variant] = common;
        const map = {};
        for (const name of names) {
            map[name] = icons[name].inner;
            if (icons[name].viewBox && icons[name].viewBox !== common) {
                viewBoxes[variant] = viewBoxes[variant] || {};
                viewBoxes[variant][name] = icons[name].viewBox;
            }
        }
        const file = path.join(outDir, `${variant}.php`);
        const php = phpFile(
            `Generated by \`pnpm sync-icons\` from ${lib.pkg}@${version} (${variant}).\nDo not hand-edit; re-run the script instead.`,
            map,
        );
        await fs.writeFile(file, php, "utf8");
        bytes += Buffer.byteLength(php);
    }

    const allNames = new Set();
    Object.values(result.variants).forEach((icons) =>
        Object.keys(icons).forEach((name) => allNames.add(name)),
    );
    const listed = lib.listUnion
        ? allNames.size
        : variantCounts[lib.defaultVariant];

    const index = {
        version,
        package: lib.pkg,
        defaultVariant: lib.defaultVariant,
        listUnion: lib.listUnion,
        count: listed,
        variants: variantCounts,
        viewBox: defaultViewBox,
        viewBoxes,
        brands: result.brands,
        meta: result.meta,
        excluded: result.excluded.map((item) => item.name),
    };
    const glyph = GLYPHS[lib.id];
    if (glyph) {
        const icon = result.variants[glyph.variant]?.[glyph.name];
        if (!icon) {
            throw new Error(
                `Missing glyph ${lib.id}:${glyph.name} (${glyph.variant})`,
            );
        }
        index.glyph = {
            name: glyph.name,
            variant: glyph.variant,
            inner: icon.inner,
        };
    }
    const indexPhp = phpFile(
        `Generated by \`pnpm sync-icons\` from ${lib.pkg}@${version}.\nCatalog index: version, per-style counts, viewBoxes, brand flags and brand metadata.\nDo not hand-edit; re-run the script instead.`,
        index,
    );
    await fs.writeFile(path.join(outDir, "index.php"), indexPhp, "utf8");
    bytes += Buffer.byteLength(indexPhp);

    // Licenses copied verbatim from the installed package.
    // licenseMode "first" keeps the first file that exists (Material Symbols ships one of several names).
    const licenseFiles = [];
    const licenseNames =
        lib.licenseMode === "first"
            ? [
                  lib.license.find((file) =>
                      fsSync.existsSync(path.join(pkgDir(lib.pkg), file)),
                  ),
              ].filter(Boolean)
            : lib.license;
    if (licenseNames.length === 0) {
        throw new Error(
            `No license file in ${lib.pkg} (tried ${lib.license.join(", ")})`,
        );
    }
    for (const file of licenseNames) {
        const source = path.join(pkgDir(lib.pkg), file);
        if (!fsSync.existsSync(source)) {
            throw new Error(`Missing ${file} in ${lib.pkg}`);
        }
        const target =
            lib.licenseMode === "first"
                ? `${lib.id}.txt`
                : `${lib.id}${file === "LICENSE" || file === "LICENSE.md" ? "" : `-${file.replace(/\.md$/i, "").toLowerCase()}`}.txt`;
        await fs.copyFile(source, path.join(LICENSE_DIR, target));
        licenseFiles.push(target);
    }

    if (result.attributions && result.attributions.length) {
        const lines = [
            `# Simple Icons logos with their own license (simple-icons ${version})`,
            "",
            "Simple Icons is CC0-1.0, but these bundled logos carry the license below (from `data/simple-icons.json`).",
            "Logos with non-commercial, no-derivatives, GPL/AGPL-family or custom licenses are not bundled (see config/icon-exclusions.json).",
            "",
            ...markdownTable(
                ["Icon", "Slug", "License", "License URL", "Source"],
                result.attributions.map((item) => [
                    item.title.replace(/\|/g, "\\|"),
                    `\`${item.slug}\``,
                    item.type,
                    item.url,
                    item.source,
                ]),
            ),
            "",
        ];
        await fs.writeFile(
            path.join(LICENSE_DIR, "simple-icons-icon-licenses.md"),
            lines.join("\n"),
            "utf8",
        );
        licenseFiles.push("simple-icons-icon-licenses.md");
    }

    console.log(
        `${lib.id}: ${lib.pkg}@${version} · ${listed} listed · styles ${JSON.stringify(variantCounts)} · brands ${result.brands.length} · excluded ${result.excluded.length} · ${(bytes / 1024 / 1024).toFixed(2)} MB`,
    );
    if (result.excluded.length) {
        console.log(
            `  excluded: ${result.excluded.map((item) => `${item.name}${item.reason && item.reason !== "name" && item.reason !== "trademark" ? ` (${item.reason})` : ""}`).join(", ")}`,
        );
    }

    return {
        ...lib,
        version,
        listed,
        variantCounts,
        brands: result.brands.length,
        excluded: result.excluded.length,
        bytes,
        licenseFiles,
    };
}

async function main() {
    await fs.mkdir(CATALOG_DIR, { recursive: true });
    await fs.mkdir(LICENSE_DIR, { recursive: true });

    const only = process.argv.slice(2);
    const results = [];
    for (const lib of LIBRARIES) {
        if (only.length && !only.includes(lib.id)) {
            continue;
        }
        results.push(await writeLibrary(lib));
    }

    const rows = LIBRARIES.map((lib) => {
        const version = pkgVersion(lib.pkg);
        const files =
            lib.id === "simple-icons"
                ? "simple-icons.txt, simple-icons-disclaimer.txt, simple-icons-icon-licenses.md"
                : `${lib.id}.txt`;
        return [lib.id, lib.pkg, version, lib.spdx, files];
    });
    const labels = {
        lucide: "Lucide",
        "material-symbols": "Material Symbols",
        "simple-icons": "Simple Icons",
        phosphor: "Phosphor",
        tabler: "Tabler Icons",
        heroicons: "Heroicons",
        ionicons: "Ionicons",
        octicons: "Octicons",
    };
    const readme = [
        "# Third-party icon licenses",
        "",
        "WP Icons bundles icons from the libraries below. Each file in this folder is the license text copied",
        "verbatim from the npm package version that is bundled (regenerated by `pnpm sync-icons`).",
        "",
        "Brand logos (Simple Icons, and the brand/logo icons in Phosphor, Tabler and Ionicons) are trademarks of",
        "their owners. The icon licenses cover the artwork only, not the trademarks. Using a logo does not imply",
        "endorsement; follow the owner's brand guidelines. Logos with incompatible terms are excluded, see",
        "`config/icon-exclusions.json`.",
        "",
        ...markdownTable(
            ["Library", "npm package", "Bundled version", "License", "File"],
            rows.map(([label, pkg, version, spdx, file]) => [
                labels[label] || label,
                `\`${pkg}\``,
                version,
                spdx,
                file,
            ]),
        ),
        "",
    ];
    await fs.writeFile(
        path.join(LICENSE_DIR, "README.md"),
        readme.join("\n"),
        "utf8",
    );

    console.log(
        `licenses: ${results.map((item) => `${item.pkg}@${item.version}`).join(", ")} → licenses/`,
    );
}

main().catch((error) => {
    console.error(error.stack || error.message || error);
    process.exit(1);
});
