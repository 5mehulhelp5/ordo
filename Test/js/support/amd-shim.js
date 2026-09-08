'use strict';

/**
 * Minimal AMD `define()` shim so a RequireJS module under view/**\/web/js can be loaded directly
 * by Node's own `require()` in tests, without pulling in a full RequireJS/Node loader just to
 * resolve two dependencies these modules actually declare: 'jquery' and the 'domReady!' loader
 * plugin (which none of them consume as a factory parameter - they only list it so their own
 * top-level code doesn't run until the DOM exists, see each module's own `define([...])` call).
 *
 * Resolves 'jquery' to whatever `$` Test/js/support/dom-env.js's createDomEnv() put on `global.$`,
 * and 'domReady!' to a no-op (safe: jsdom's DOM already exists by the time a test requires the
 * module, so there's nothing left for the real domReady! plugin to defer).
 *
 * @param {Function} loadModule call require(path) for the AMD module under this shim active
 * @return {*} whatever the module's factory function returned
 */
function loadAmdModule(loadModule) {
    let exported;

    global.define = function (deps, factory) {
        const resolved = deps.map(function (dep) {
            if (dep === 'jquery') {
                return global.$;
            }
            if (dep === 'domReady!') {
                return null;
            }
            if (dep === 'underscore') {
                // A minimal stand-in for underscore's own debounce(): calls straight through with
                // no actual delay. Tests exercise the named functions a module builds around its
                // debounced wrapper directly (searchProducts/isSkuField/renderDropdown, see each
                // module's own return statement) rather than the debounce timing itself, so a
                // real timer isn't needed here and would only make tests slower/flakier.
                return {
                    debounce: function (fn) {
                        return function () {
                            return fn.apply(this, arguments);
                        };
                    }
                };
            }
            throw new Error('Test/js/support/amd-shim: unsupported dependency "' + dep + '"');
        });

        exported = factory.apply(null, resolved);
    };
    global.define.amd = true;

    try {
        loadModule();
    } finally {
        delete global.define;
    }

    return exported;
}

module.exports = { loadAmdModule: loadAmdModule };
