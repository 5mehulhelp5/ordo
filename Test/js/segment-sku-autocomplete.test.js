'use strict';

const QUnit = require('qunit');
const { loadModule } = require('./support/load-module');

const MODULE_PATH = 'view/adminhtml/web/js/segment-sku-autocomplete.js';

/**
 * Stubs global.fetch for one call and returns the args it was called with, so a test can assert
 * the request URL/params without a real network round trip.
 *
 * @param {Object} responseInit {ok, json: () => Promise}
 */
function stubFetch(responseInit) {
    const calls = [];

    global.fetch = function () {
        calls.push(Array.from(arguments));

        return Promise.resolve(responseInit);
    };

    return calls;
}

QUnit.module('Ordo_Automation/js/segment-sku-autocomplete', function () {
    QUnit.test('isSkuField() matches a top-level condition row\'s sku field, nothing else', function (assert) {
        const api = loadModule(MODULE_PATH);

        assert.true(api.isSkuField(global.$('<input name="conditions[conditions][2][sku]">')));
        assert.true(api.isSkuField(global.$('<input name="conditions[conditions][0][sku]">')));
        assert.false(api.isSkuField(global.$('<input name="conditions[conditions][2][tag]">')));
        assert.false(api.isSkuField(global.$('<input name="conditions[conditions][2][category_id]">')));
        assert.false(api.isSkuField(global.$('<input>')));
    });

    QUnit.test('searchProducts() resolves the response\'s items on a 200', async function (assert) {
        const api = loadModule(MODULE_PATH);

        stubFetch({
            ok: true,
            json: () => Promise.resolve({ items: [{ sku: '24-MB01', name: 'Blue Shirt' }] })
        });

        const items = await api.searchProducts('shirt');

        assert.deepEqual(items, [{ sku: '24-MB01', name: 'Blue Shirt' }]);
    });

    QUnit.test('searchProducts() resolves to an empty array on a non-ok response', async function (assert) {
        const api = loadModule(MODULE_PATH);

        stubFetch({ ok: false, json: () => Promise.resolve({}) });

        assert.deepEqual(await api.searchProducts('shirt'), []);
    });

    QUnit.test('searchProducts() resolves to an empty array instead of rejecting on a network error', async function (assert) {
        const api = loadModule(MODULE_PATH);

        global.fetch = function () {
            return Promise.reject(new Error('network down'));
        };

        assert.deepEqual(await api.searchProducts('shirt'), []);
    });

    QUnit.test('searchProducts() URL-encodes the search term', async function (assert) {
        const api = loadModule(MODULE_PATH);

        const calls = stubFetch({ ok: true, json: () => Promise.resolve({ items: [] }) });

        await api.searchProducts('a b&c');

        assert.true(calls[0][0].includes('term=a%20b%26c') || calls[0][0].includes('term=a+b%26c'));
    });

    QUnit.test('renderDropdown() appends one row per item, labeled "sku — name"', function (assert) {
        const api = loadModule(MODULE_PATH, '<input id="sku-field">');
        const $input = global.$('#sku-field');

        api.renderDropdown($input, [
            { sku: '24-MB01', name: 'Blue Shirt' },
            { sku: '24-MB02', name: 'Red Shirt' }
        ]);

        const $rows = global.$('.ordo-sku-suggest-row');

        assert.strictEqual($rows.length, 2);
        assert.strictEqual(global.$($rows[0]).text(), '24-MB01 — Blue Shirt');
        assert.strictEqual(global.$($rows[1]).text(), '24-MB02 — Red Shirt');
    });

    QUnit.test('renderDropdown() with an empty list closes any existing dropdown and adds nothing', function (assert) {
        const api = loadModule(MODULE_PATH, '<input id="sku-field">');
        const $input = global.$('#sku-field');

        api.renderDropdown($input, [{ sku: '24-MB01', name: 'Blue Shirt' }]);
        assert.strictEqual(global.$('.ordo-sku-suggest').length, 1);

        api.renderDropdown($input, []);
        assert.strictEqual(global.$('.ordo-sku-suggest').length, 0);
    });

    QUnit.test('a dropdown row\'s mousedown fills the input with its SKU and closes the dropdown', function (assert) {
        const api = loadModule(MODULE_PATH, '<input id="sku-field">');
        const $input = global.$('#sku-field');

        api.renderDropdown($input, [{ sku: '24-MB01', name: 'Blue Shirt' }]);
        global.$('.ordo-sku-suggest-row').trigger('mousedown');

        assert.strictEqual($input.val(), '24-MB01');
        assert.strictEqual(global.$('.ordo-sku-suggest').length, 0);
    });

    QUnit.test('closeDropdown() removes an existing dropdown and is a no-op when there is none', function (assert) {
        const api = loadModule(MODULE_PATH, '<input id="sku-field">');
        const $input = global.$('#sku-field');

        api.renderDropdown($input, [{ sku: '24-MB01', name: 'Blue Shirt' }]);
        api.closeDropdown();
        assert.strictEqual(global.$('.ordo-sku-suggest').length, 0);

        api.closeDropdown();
        assert.strictEqual(global.$('.ordo-sku-suggest').length, 0);
    });
});
