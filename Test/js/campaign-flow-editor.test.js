'use strict';

const QUnit = require('qunit');
const { loadModule } = require('./support/load-module');

const MODULE_PATH = 'view/adminhtml/web/js/campaign-flow-editor.js';

/**
 * unionNodeOutputConnections()/findDisconnectedNodeIds() are pure top-level helpers (no DOM,
 * jQuery, or Drawflow dependency of their own - see the module's own comment on why they were
 * pulled out of buildConnectivityGroups()/validateFlow() in the first place), exposed as
 * properties on the exported initCampaignFlowEditor function purely for this test to reach them.
 */
QUnit.module('Ordo_Automation/js/campaign-flow-editor', function () {
    QUnit.test('unionNodeOutputConnections() unions the source node with every connected target', function (assert) {
        const initCampaignFlowEditor = loadModule(MODULE_PATH);
        const calls = [];
        const union = function (a, b) {
            calls.push([a, b]);
        };
        const node = {
            outputs: {
                output_1: { connections: [{ node: '2' }, { node: '3' }] },
                output_2: { connections: [{ node: '4' }] }
            }
        };

        initCampaignFlowEditor.unionNodeOutputConnections('1', node, union);

        assert.deepEqual(calls, [['1', '2'], ['1', '3'], ['1', '4']]);
    });

    QUnit.test('unionNodeOutputConnections() is a no-op for a node with no outputs', function (assert) {
        const initCampaignFlowEditor = loadModule(MODULE_PATH);
        const calls = [];

        initCampaignFlowEditor.unionNodeOutputConnections('1', {}, function () {
            calls.push(true);
        });

        assert.deepEqual(calls, []);
    });

    QUnit.test('findDisconnectedNodeIds() returns every id whose group root differs from the primary root', function (assert) {
        const initCampaignFlowEditor = loadModule(MODULE_PATH);
        const roots = { 1: 'A', 2: 'A', 3: 'B' };
        const groups = { find: function (id) { return roots[id]; } };

        assert.deepEqual(
            initCampaignFlowEditor.findDisconnectedNodeIds({ 1: {}, 2: {}, 3: {} }, groups, 'A'),
            ['3']
        );
    });

    QUnit.test('findDisconnectedNodeIds() returns an empty array when every node shares the primary root', function (assert) {
        const initCampaignFlowEditor = loadModule(MODULE_PATH);
        const groups = { find: function () { return 'A'; } };

        assert.deepEqual(initCampaignFlowEditor.findDisconnectedNodeIds({ 1: {}, 2: {} }, groups, 'A'), []);
    });
});
