'use strict';

const path = require('path');
const { createDomEnv } = require('./dom-env');
const { loadAmdModule } = require('./amd-shim');

/**
 * Sets up a fresh DOM (see createDomEnv) and loads one AMD module under it via the amd-shim,
 * clearing Node's require cache first so the module's top-level code (its `$(document).on(...)`
 * delegates, its initial refreshGroupRows()/syncVisibility() call) actually re-runs against the
 * new DOM instead of returning an already-cached export tied to a previous test's now-discarded
 * document.
 *
 * @param {String} relativePathFromRepoRoot e.g. 'view/adminhtml/web/js/segment-group-modal.js'
 * @param {String} [bodyHtml] initial markup for the fresh document's <body>
 * @return {*} the module's own return value (see each module's own trailing `return {...}`)
 */
function loadModule(relativePathFromRepoRoot, bodyHtml) {
    createDomEnv(bodyHtml);

    const absolutePath = path.join(__dirname, '..', '..', '..', relativePathFromRepoRoot);

    delete require.cache[require.resolve(absolutePath)];

    return loadAmdModule(function () {
        require(absolutePath);
    });
}

module.exports = { loadModule: loadModule };
