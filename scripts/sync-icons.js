'use strict';

const { spawnSync } = require('child_process');
const path = require('path');

const scripts = ['sync-lucide-icons.js', 'sync-material-symbols.js', 'sync-packages.js'];
const extraArgs = process.argv.slice(2);

for (const script of scripts) {
    const result = spawnSync(process.execPath, [path.join(__dirname, script), ...extraArgs], {
        stdio: 'inherit',
    });
    if (result.status !== 0) {
        process.exit(result.status || 1);
    }
}
