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

            // DataTables and Chart.js are third-party libraries (see thirdpartylibs.xml), so
            // they aren't kept as hand-vendored source under amd/src - they're pulled straight
            // from their npm packages (already minified there) into amd/build on every build.
            const dtSrc = path.join('node_modules', 'datatables.net', 'js', 'dataTables.min.js');
            const dtDest = path.join(destDir, 'datatables.min.js');
            let dtSource = fs.readFileSync(dtSrc, 'utf8');

            // Since v2, DataTables' own UMD banner no longer declares 'jquery' as its AMD
            // dependency - it just reads window.jQuery when its factory runs. Nothing else then
            // guarantees jquery's factory (which sets window.jQuery) has already run first, so
            // this re-adds that AMD dependency edge, the same guarantee 1.x provided natively.
            const dtAmdHeader = 'define([],function()';
            if (!dtSource.includes(dtAmdHeader)) {
                throw new Error(
                    'datatables.net AMD header has changed shape - update the jquery dependency patch in Gruntfile.js'
                );
            }
            dtSource = dtSource.replace(dtAmdHeader, 'define(["jquery"],function()');

            fs.writeFileSync(dtDest, dtSource);
            grunt.log.writeln(`✔ Copied datatables.net → ${dtDest}`);

            // Chart.js's UMD bundle has no dependencies of its own (jquery-free), so unlike
            // DataTables it can be copied across as-is.
            const chartSrc = path.join('node_modules', 'chart.js', 'dist', 'chart.umd.min.js');
            const chartDest = path.join(destDir, 'chart.min.js');
            fs.copyFileSync(chartSrc, chartDest);
            grunt.log.writeln(`✔ Copied chart.js → ${chartDest}`);

            done();
        } catch (err) {
            grunt.log.error(err);
            done(false);
        }
    });
};
