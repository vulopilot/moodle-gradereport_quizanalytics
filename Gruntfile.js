const fs = require('fs');
const path = require('path');
const { minify } = require('terser');

module.exports = function (grunt) {
    // moodle-plugin-ci's "grunt" CI check always runs a fixed task list - amd, yui,
    // gherkinlint, stylelint - skipping whichever ones don't apply to this plugin. Since we
    // ship plain CSS (no yui/, no Behat features), "stylelint" is the only one of those we
    // need to actually provide; grunt-stylelint registers it once loaded below, driven by the
    // .stylelintrc.json ruleset (adapted from Moodle core's own, minus the SCSS-only bits we
    // don't need since this plugin has no .scss files).
    grunt.loadNpmTasks('grunt-stylelint');
    grunt.initConfig({
        stylelint: {
            options: {
                configFile: '.stylelintrc.json',
                // These rule names are stylelint's own legacy names for rules it later renamed
                // or merged (this ruleset is adapted from Moodle core's, which sets this same
                // option for the same reason) - without it, every run prints ~50 lines of noise
                // about that regardless of whether the CSS itself has any real problems.
                quietDeprecationWarnings: true,
            },
            css: {
                src: ['css/*.css'],
            },
        },
    });

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

            // DataTables' base styling (search box, length menu, pagination, sort icons) is
            // likewise pulled from npm rather than hand-maintained, so it can't drift out of sync
            // with the datatables.net JS version above the way a hand-copied file eventually would.
            const dtCssSrc = path.join('node_modules', 'datatables.net-dt', 'css', 'dataTables.dataTables.css');
            const dtCssDest = path.join('css', 'datatables.css');
            fs.copyFileSync(dtCssSrc, dtCssDest);
            grunt.log.writeln(`✔ Copied datatables.net-dt → ${dtCssDest}`);

            done();
        } catch (err) {
            grunt.log.error(err);
            done(false);
        }
    });
};
