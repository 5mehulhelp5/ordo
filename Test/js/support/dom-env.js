'use strict';

const { JSDOM } = require('jsdom');
const jqueryFactory = require('jquery');

/**
 * Builds a fresh jsdom document + jQuery bound to it, and installs both as globals - the AMD
 * modules under test (view/**\/web/js/*.js) assume a global `$`/`jQuery` and a global `document`,
 * same as they get for real from Magento_Ui's own RequireJS bundle in the browser. Called once per
 * test to get an isolated DOM: reusing one jsdom instance across tests would leak state (event
 * delegates bound in one test firing during another) exactly the way a shared browser tab would.
 *
 * @param {String} [bodyHtml] initial markup for <body>
 * @return {{dom: JSDOM, $: Function}}
 */
function createDomEnv(bodyHtml) {
    const dom = new JSDOM(`<!doctype html><html><body>${bodyHtml || ''}</body></html>`);
    const $ = jqueryFactory(dom.window);

    global.window = dom.window;
    global.document = dom.window.document;
    global.$ = $;
    global.jQuery = $;

    return { dom: dom, $: $ };
}

module.exports = { createDomEnv: createDomEnv };
