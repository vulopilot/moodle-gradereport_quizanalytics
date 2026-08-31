const fs = require('fs');
const path = require('path');
const { minify } = require('terser');

module.exports = function (grunt) {
    grunt.registerTask('amd', 'Minify AMD modules', async function () {
        const done = this.async();
        const srcDir = 'amd/src';
        const destDir = 'amd/build';

        if (!fs.existsSync(destDir)) {
            fs.mkdirSync(destDir, { recursive: true });
        }

        const files = fs.readdirSync(srcDir).filter(file => file.endsWith('.js'));

        try {
            for (const file of files) {
                const inputPath = path.join(srcDir, file);
                const outputPath = path.join(destDir, file.replace('.js', '.min.js'));

                // These are already hand-written AMD/UMD modules (define(deps, factory), no
                // import/export), so they only need minifying - not bundling or re-wrapping.
                const source = fs.readFileSync(inputPath, 'utf8');
                const result = await minify(source, {
                    sourceMap: false,
                });

                if (result.error) {
                    throw result.error;
                }

                fs.writeFileSync(outputPath, result.code);
                grunt.log.writeln(`✔ Built ${file} → ${outputPath}`);
            }
            done();
        } catch (err) {
            grunt.log.error(err);
            done(false);
        }
    });
};
