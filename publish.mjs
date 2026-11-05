/**
 * Usage: Call this script with pluginSlug as argument, example: `node publish.js plugin-name`
 * Version: 2.1.0
 * Changelog:
 *  1.0.0: initial
 *  2.0.0: Now outputs as /releases/{pluginSlug}-{version}.zip, with pluginSlug as root folder inside zip. Instead of /releases/{version}/{pluginSlug}.zip, with all files directly inside
 *  2.1.0: Ignore files matched by ignoreRules (gitignore patterns). *~ skips editor backups. pathsToZip is one include list of files and folders. The main plugin file is prepended before zipping.
 */

import fs from "fs-extra";
import path from "path";
import { ZipArchive } from "archiver";
import ignore from "ignore";

const rootDir = import.meta.dirname;
const pluginSlug = process.argv[2] ? process.argv[2].split(".")[0] : "";

const ignoreRules = ["src/languages/*~"];
const ignored = ignore().add(ignoreRules);

const pathsToZip = [
    "src/assets/css",
    "src/assets/js",
    "src/includes",
    "src/languages",
    "src/templates",
    "build",
    "vendor",
    "config",
    "licenses",
    "bootstrap.php",
    "uninstall.php",
    "src/functions.php",
    "readme.txt",
    "LICENSE",
];

function accept(folder) {
    return (entry) => {
        let relative = path.posix.join(folder, entry.name.replace(/\\/g, "/"));
        if (entry.stats && entry.stats.isDirectory()) {
            relative += "/";
        }
        return ignored.ignores(relative) ? false : entry;
    };
}

fs.readFile(path.join(rootDir, `${pluginSlug}.php`), "utf8", (err, data) => {
    if (err) {
        throw new Error("Error reading plugin file:", err);
    }

    const versionMatch = data.match(
        /Version:\s*(\d+\.\d+\.\d+(?:-[a-zA-Z0-9]+(?:\.[a-zA-Z0-9]+)*)?)/,
    );

    if (!pluginSlug || !versionMatch) {
        throw new Error("Version or filename not found.");
    }

    const version = versionMatch[1];
    const folder = `releases`;
    const filepath = `${folder}/${pluginSlug}-${version}.zip`;

    fs.ensureDirSync(path.join(rootDir, folder));
    const output = fs.createWriteStream(path.join(rootDir, filepath));
    const archive = new ZipArchive({ zlib: { level: 9 } });

    output.on("close", function () {
        console.log(archive.pointer() + " total bytes");
        console.log(`Zip file done, outputted to: ${filepath}`);
    });

    archive.on("error", function (err) {
        throw err;
    });

    archive.pipe(output);

    const root = `${pluginSlug}/`;
    const entries = [`${pluginSlug}.php`, ...pathsToZip];

    entries.forEach((entry) => {
        if (entry !== `${pluginSlug}.php` && ignored.ignores(entry)) {
            return;
        }
        const isDirectory = fs
            .statSync(path.join(rootDir, entry))
            .isDirectory();
        if (isDirectory) {
            archive.directory(`${entry}/`, `${root}${entry}`, accept(entry));
            return;
        }
        archive.file(entry, { name: `${root}${entry}` });
    });

    archive.finalize();
});
