'use strict';

const QUnit = require('qunit');
const { loadModule } = require('./support/load-module');

const MODULE_PATH = 'view/adminhtml/web/js/free-gift-offer-form.js';

/**
 * Stubs global.fetch for one call and returns the args it was called with, so a test can assert
 * the request URL/params without a real network round trip - same pattern as
 * Test/js/segment-sku-autocomplete.test.js's own stubFetch().
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

QUnit.module('Ordo_Automation/js/free-gift-offer-form', function () {
    QUnit.test('isProductSkuField() matches a Products row\'s own sku field, nothing else', function (assert) {
        const api = loadModule(MODULE_PATH);

        assert.true(api.isProductSkuField(global.$('<input name="products[products][3][sku]">')));
        assert.false(api.isProductSkuField(global.$('<input name="products[products][3][qty]">')));
        assert.false(api.isProductSkuField(global.$('<input name="conditions[conditions][0][sku]">')));
    });

    QUnit.test('isTierField() matches a Tiers row\'s min_subtotal/gift_slots fields, nothing else', function (assert) {
        const api = loadModule(MODULE_PATH);

        assert.true(api.isTierField(global.$('<input name="tiers[tiers][1][min_subtotal]">')));
        assert.true(api.isTierField(global.$('<input name="tiers[tiers][1][gift_slots]">')));
        assert.false(api.isTierField(global.$('<input name="tiers[tiers][1][other]">')));
    });

    QUnit.test('formatMoney() fixes a numeric value to 2 decimals, leaves a non-numeric value untouched', function (assert) {
        const api = loadModule(MODULE_PATH);

        assert.strictEqual(api.formatMoney(5), '5.00');
        assert.strictEqual(api.formatMoney('19.5'), '19.50');
        assert.strictEqual(api.formatMoney('not-a-number'), 'not-a-number');
    });

    QUnit.test('sleep() resolves via setTimeout with the given delay, not before it fires', async function (assert) {
        const api = loadModule(MODULE_PATH);
        const originalSetTimeout = global.setTimeout;
        let capturedDelay = null;
        let fire = null;

        // Stub setTimeout so the test doesn't depend on real elapsed time (flaky on
        // loaded CI runners); instead assert the *contract*: sleep() schedules a
        // callback with the given delay, and only resolves once that callback runs.
        global.setTimeout = function (callback, delay) {
            capturedDelay = delay;
            fire = callback;
            return 0;
        };

        try {
            let resolved = false;
            const promise = api.sleep(10).then(function () {
                resolved = true;
            });

            assert.strictEqual(capturedDelay, 10);
            assert.false(resolved, 'must not resolve before the timer fires');

            fire();
            await promise;

            assert.true(resolved, 'resolves once the timer fires');
        } finally {
            global.setTimeout = originalSetTimeout;
        }
    });

    QUnit.test('searchProducts() resolves the response\'s items on a 200', async function (assert) {
        const api = loadModule(MODULE_PATH);

        stubFetch({ ok: true, json: () => Promise.resolve({ items: [{ sku: 'ABC', name: 'A Product' }] }) });

        const items = await api.searchProducts('abc');

        assert.deepEqual(items, [{ sku: 'ABC', name: 'A Product' }]);
    });

    QUnit.test('searchProducts() resolves to an empty array when the response has no items key', async function (assert) {
        const api = loadModule(MODULE_PATH);

        stubFetch({ ok: true, json: () => Promise.resolve({}) });

        assert.deepEqual(await api.searchProducts('abc'), []);
    });

    QUnit.test('renderChip() removes any existing chip and adds nothing when the item has no sku', function (assert) {
        const api = loadModule(
            MODULE_PATH,
            '<div class="admin__field-control"><input id="sku-input"><div class="ordo-picker-chip">stale</div></div>'
        );
        const $input = global.$('#sku-input');

        api.renderChip($input, null);

        assert.strictEqual($input.closest('.admin__field-control').find('.ordo-picker-chip').length, 0);
    });

    QUnit.test('renderChip() renders a chip with the item\'s name and sku', function (assert) {
        const api = loadModule(MODULE_PATH, '<div class="admin__field-control"><input id="sku-input"></div>');
        const $input = global.$('#sku-input');

        api.renderChip($input, { sku: 'XYZ', name: 'A Gift' });

        const $chip = $input.closest('.admin__field-control').find('.ordo-picker-chip');

        assert.strictEqual($chip.length, 1);
        assert.strictEqual($chip.find('.ordo-picker-chip-name').text(), 'A Gift');
        assert.strictEqual($chip.find('.ordo-picker-chip-sku').text(), 'XYZ');
    });
});
