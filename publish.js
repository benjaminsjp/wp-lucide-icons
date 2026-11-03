/**
 * Usage: Call this script with pluginSlug as argument, example: `node publish.js plugin-name`
 * Version: 2.0.0
 * Changelog:
 *  1.0.0: initial
 *  2.0.0: Now outputs as /releases/{pluginSlug}-{version}.zip, with pluginSlug as root folder inside zip. Instead of /releases/{version}/{pluginSlug}.zip, with all files directly inside
 */

const fs = require("fs-extra");
const path = require("path");
const archiver = require("archiver");

fs.readFile(path.join(__dirname, process.argv[2]), "utf8", (err, data) => {
    if (err) {
        throw new Error("Error reading plugin file:", err);
    }

    // Expects the plugin name with .php extension, so we strip it here to ensure it is not included
    const pluginSlug = process.argv[2].split(".")[0];
    const versionMatch = data.match(
        /Version:\s*(\d+\.\d+\.\d+(?:-[a-zA-Z0-9]+(?:\.[a-zA-Z0-9]+)*)?)/,
    );

    if (!pluginSlug || !versionMatch) {
        throw new Error("Version or filename not found.");
    }

    const version = versionMatch[1];
    const folder = `releases`;
    const filepath = `${folder}/${pluginSlug}-${version}.zip`;

    fs.ensureDirSync(path.join(__dirname, folder));
    const output = fs.createWriteStream(path.join(__dirname, filepath));
    const archive = archiver("zip", {
        zlib: { level: 9 },
    });

    output.on("close", function () {
        console.log(archive.pointer() + " total bytes");
        console.log(`Zip file done, outputted to: ${filepath}`);
    });

    archive.on("error", function (err) {
        throw err;
    });

    archive.pipe(output);

    const root = `${pluginSlug}/`;

    const foldersToZip = [
        "src/assets/css",
        "src/assets/js",
        "src/includes",
        "src/languages",
        "src/templates",
        "build",
        "vendor",
        "config",
        "licenses",
    ];
    foldersToZip.forEach((folder) => {
        archive.directory(`${folder}/`, `${root}${folder}`);
    });

    const filesToZip = [`${pluginSlug}.php`, "bootstrap.php", "uninstall.php"];
    filesToZip.forEach((file) => {
        archive.file(file, { name: `${root}${file}` });
    });

    archive.finalize();
});
