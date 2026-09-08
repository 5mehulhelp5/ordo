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
            if (dep === 'Magento_Ui/js/modal/modal') {
                // Real usage only imports this for its side effect (registering $.fn.modal() on
                // jQuery) - campaign-flow-editor.js/free-gift-offer-form.js tests exercise their
                // own pure top-level helpers (unionNodeOutputConnections/isProductSkuField/etc.),
                // never the actual modal dialog, so nothing needs to consume this value.
                return undefined;
            }
            if (dep === 'uiRegistry') {
                // Stub matching Magento_Ui's own registry.get(name, callback) shape closely enough
                // for a module's top-level define() to resolve - never actually invoked by the
                // tests here (they don't exercise the "Apply flow to form" save path).
                return { get: function () {} };
            }
            if (dep === 'drawflow') {
                // A no-op stand-in for the Drawflow constructor - real construction
                // (`new Drawflow(container)`) only happens inside initCampaignFlowEditor()'s own
                // body, which the tests here never call (they exercise its exposed pure helpers
                // directly), so this only needs to exist, not actually work.
                return function Drawflow() {};
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
