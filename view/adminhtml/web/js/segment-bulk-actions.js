/**
 * "Bulk actions on current members" (bulkactions.phtml) - shows only the field(s) relevant to the
 * currently-selected Action, instead of every action's field sitting visible at once regardless of
 * selection. Reported directly against a real screenshot: "Add Points" selected, the Tag field
 * (used only by "Add Tag") still shown right above it - confusing on its own, and this form has no
 * ui_component/switcherConfig to lean on (see BulkActions.php's own docblock for why it's plain
 * HTML), so it would only get more confusing field-by-field as more bulk actions are added.
 *
 * Data-driven on purpose: every field is tagged `data-bulk-action-field="<action key>"` in the
 * phtml, and this script just shows whichever field(s) match the select's current value, hiding
 * the rest. Adding a future action (e.g. "Remove Tag") means adding one more <option> plus one
 * more data-bulk-action-field block with a matching key in the phtml - this script never needs to
 * change to support it.
 */
define([
    'jquery',
    'domReady!'
], function ($) {
    'use strict';

    /**
     * @param {jQuery} $select the Action <select>
     */
    function syncVisibility($select) {
        var $panel = $select.closest('.ordo-bulk-actions-panel'),
            selected = $select.val();

        $panel.find('[data-bulk-action-field]').each(function () {
            var $field = $(this);

            $field.toggle($field.data('bulkActionField') === selected);
        });
    }

    $('[data-bulk-action-select]').each(function () {
        var $select = $(this);

        syncVisibility($select);
        $select.on('change', function () {
            syncVisibility($select);
        });
    });

    // Exposed for Test/js/segment-bulk-actions.test.js - see segment-group-modal.js's own return
    // statement for why this is safe (side-effect-only module, nothing else requires() its return
    // value).
    return {
        syncVisibility: syncVisibility
    };
});
