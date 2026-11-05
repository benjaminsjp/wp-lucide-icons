const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const fs = require('fs');
const path = require('path');

class CopyBlockMetadataPlugin {
    apply(compiler) {
        compiler.hooks.done.tap('CopyBlockMetadataPlugin', () => {
            const from = path.resolve(__dirname, 'src/assets/js/blocks/icon/block.json');
            const toDir = path.resolve(__dirname, 'build/blocks/icon');
            const to = path.join(toDir, 'block.json');
            fs.mkdirSync(toDir, { recursive: true });
            fs.copyFileSync(from, to);
            fs.rmSync(path.resolve(__dirname, 'build/assets'), { recursive: true, force: true });
            for (const file of [
                'build/blocks/icon/index.css',
                'build/blocks/icon/index-rtl.css',
                'build/blocks/icon/style-index.css',
                'build/blocks/icon/style-index-rtl.css',
                'build/admin/library/style-index.css',
                'build/admin/library/style-index-rtl.css',
                'build/tinymce/picker.css',
                'build/tinymce/picker-rtl.css',
                'build/tinymce/style-index.css',
                'build/tinymce/style-index-rtl.css',
                'build/api/index.css',
                'build/api/index-rtl.css',
                'build/api/style-index.css',
                'build/api/style-index-rtl.css',
            ]) {
                fs.rmSync(path.resolve(__dirname, file), { force: true });
            }
        });
    }
}

module.exports = {
    ...defaultConfig,
    entry: {
        'blocks/icon/index': path.resolve(__dirname, 'src/assets/js/blocks/icon/index.js'),
        'admin/library/index': path.resolve(__dirname, 'src/assets/js/admin/library/index.js'),
        'tinymce/picker': path.resolve(__dirname, 'src/assets/js/tinymce/picker.js'),
        'api/index': path.resolve(__dirname, 'src/assets/js/api/index.js'),
    },
    plugins: [...defaultConfig.plugins, new CopyBlockMetadataPlugin()],
};
