'use strict';

const QUnit = require('qunit');
const { loadModule } = require('./support/load-module');

const MODULE_PATH = 'view/adminhtml/web/js/segment-bulk-actions.js';

const PANEL_HTML = '<div class="ordo-bulk-actions-panel">'
    + '<select data-bulk-action-select="1">'
    + '<option value="add_tag" selected>Add Tag</option>'
    + '<option value="add_points">Add Points</option>'
    + '</select>'
    + '<div data-bulk-action-field="add_tag">tag field</div>'
    + '<div data-bulk-action-field="add_points">points field</div>'
    + '</div>';

QUnit.module('Ordo_Automation/js/segment-bulk-actions', function () {
    QUnit.test('on load, shows only the field matching the select\'s initial value', function (assert) {
        loadModule(MODULE_PATH, PANEL_HTML);

        assert.true(global.$('[data-bulk-action-field="add_tag"]').css('display') !== 'none');
        assert.false(global.$('[data-bulk-action-field="add_points"]').css('display') !== 'none');
    });

    QUnit.test('switching the select toggles which field is shown', function (assert) {
        loadModule(MODULE_PATH, PANEL_HTML);

        global.$('[data-bulk-action-select]').val('add_points').trigger('change');

        assert.false(global.$('[data-bulk-action-field="add_tag"]').css('display') !== 'none');
        assert.true(global.$('[data-bulk-action-field="add_points"]').css('display') !== 'none');
    });

    QUnit.test('syncVisibility() is scoped to the select\'s own .ordo-bulk-actions-panel', function (assert) {
        // Two independent panels on the same page (not a real scenario today, but the selector
        // scoping in segment-bulk-actions.js's syncVisibility() is written to support it) -
        // changing one panel's Action must not touch the other panel's fields.
        const api = loadModule(
            MODULE_PATH,
            '<div class="ordo-bulk-actions-panel" id="panel-a">'
            + '<select data-bulk-action-select="1">'
            + '<option value="add_tag" selected>Add Tag</option>'
            + '<option value="add_points">Add Points</option>'
            + '</select>'
            + '<div data-bulk-action-field="add_tag">a-tag</div>'
            + '<div data-bulk-action-field="add_points">a-points</div>'
            + '</div>'
            + '<div class="ordo-bulk-actions-panel" id="panel-b">'
            + '<select data-bulk-action-select="1">'
            + '<option value="add_tag" selected>Add Tag</option>'
            + '<option value="add_points">Add Points</option>'
            + '</select>'
            + '<div data-bulk-action-field="add_tag">b-tag</div>'
            + '<div data-bulk-action-field="add_points">b-points</div>'
            + '</div>'
        );

        api.syncVisibility(global.$('#panel-b [data-bulk-action-select]').val('add_points'));

        assert.true(global.$('#panel-a [data-bulk-action-field="add_tag"]').css('display') !== 'none', 'panel A is untouched');
        assert.false(global.$('#panel-b [data-bulk-action-field="add_tag"]').css('display') !== 'none');
        assert.true(global.$('#panel-b [data-bulk-action-field="add_points"]').css('display') !== 'none');
    });
});
