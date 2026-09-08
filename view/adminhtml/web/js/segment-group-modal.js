/**
 * "Group (nested AND/OR)" condition editor for the Segment form's conditions list.
 *
 * A nested dynamicRows-inside-dynamicRows was tried first (a group's own conditions as a second
 * dynamicRows field nested inside the outer conditions row) and does not work: no core Magento
 * module anywhere nests one dynamicRows inside another's own record, and confirmed directly here
 * too - its "Add Condition to Group" button fired with no console error but never added a row
 * (checked past the click handler itself via a plain dispatchEvent, ruling out a click-target
 * problem). Rather than keep guessing at Magento_Ui internals, a group's own conditions are
 * edited in a small modal instead - the exact "+ Choose from Catalog" pattern already proven on
 * the Free Gift Offer form, just editing condition rows instead of picking products. The result
 * is written into a single hidden group_conditions_json field (a plain JSON array of
 * {type, params}) that Model\Segment\SegmentSaveProcessor::normalizeGroupRow() reads directly -
 * no dynamicRows involved on the group's own list at all.
 *
 * Each modal row is Type + one generic "value" input (labeled per-type, e.g. "Minimum score" for
 * score_at_least) - the same one-value-per-type shape every dedicated field on the outer form
 * already has, just not switched via a declarative switcherConfig here since there is no
 * declarative row to switch fields on. The 3 condition types with no single dedicated value
 * anywhere on this form (in_segment, loyalty_tier_at_least, nps_score_at_least) get a raw
 * "Advanced (JSON)" textarea instead, identical in spirit to the outer form's own params_json
 * fallback for the same 3 types.
 */
define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'domReady!'
], function ($) {
    'use strict';

    // type -> {key, label} for the one dedicated value every other condition type has - mirrors
    // Model\Segment\SegmentSaveProcessor::DEDICATED_PARAM_FIELDS / ordo_segment_form.xml's own
    // switcherConfig exactly, just expressed as a JS lookup instead of 6 separate declarative
    // fields (a group's own list isn't a declarative dynamicRows at all, see file docblock).
    var VALUE_FIELD_BY_TYPE = {
        tag: {key: 'tag', label: 'Tag'},
        order_total_gte: {key: 'amount', label: 'Amount'},
        visitor_tag: {key: 'tag', label: 'Tag'},
        score_at_least: {key: 'threshold', label: 'Minimum score'},
        recency_days_at_most: {key: 'days', label: 'Recency (days since last order, at most)'},
        order_frequency_at_least: {key: 'count', label: 'Order count, at least'},
        monetary_total_at_least: {key: 'amount', label: 'Minimum order total'},
        recency_percentile_at_least: {key: 'percentile', label: 'Percentile, at least (0-100)'},
        order_frequency_percentile_at_least: {key: 'percentile', label: 'Percentile, at least (0-100)'},
        monetary_percentile_at_least: {key: 'percentile', label: 'Percentile, at least (0-100)'}
    };

    /**
     * The outer conditions list's own Type <select> already has every ConditionTypeWithGroup
     * option, correctly translated - cloned here (minus "group" itself, since a group's own
     * conditions can't themselves be groups - one level of nesting only) instead of hard-coding
     * type/label pairs a second time in JS where they could drift out of sync.
     *
     * @return {Array} [{value, label}]
     */
    function readTypeOptions() {
        var $anySelect = $('[data-index="conditions"] select').first(),
            options = [];

        $anySelect.find('option').each(function () {
            var value = $(this).val();

            if (value && value !== 'group') {
                options.push({value: value, label: $(this).text()});
            }
        });

        return options;
    }

    /**
     * @param {Array} conditions [{type, params}]
     * @return {jQuery}
     */
    function buildModalMarkup(conditions, typeOptions) {
        var $modal = $('<div class="ordo-group-modal"></div>'),
            $list = $('<div class="ordo-group-modal-rows"></div>').appendTo($modal);

        $('<button type="button" class="ordo-group-modal-add">+ Add Condition</button>')
            .on('click', function () {
                appendRow($list, typeOptions, {type: typeOptions[0].value, params: {}});
            })
            .appendTo($modal);

        conditions.forEach(function (condition) {
            appendRow($list, typeOptions, condition);
        });

        if (conditions.length === 0) {
            appendRow($list, typeOptions, {type: typeOptions[0].value, params: {}});
        }

        return $modal;
    }

    /**
     * @param {jQuery} $list
     * @param {Array} typeOptions
     * @param {Object} condition {type, params}
     */
    function appendRow($list, typeOptions, condition) {
        var $row = $('<div class="ordo-group-modal-row"></div>'),
            $typeSelect = $('<select class="admin__control-select"></select>'),
            $valueWrap = $('<div class="ordo-group-modal-value"></div>'),
            $delete = $('<button type="button" class="ordo-group-modal-delete" title="Remove">✕</button>');

        typeOptions.forEach(function (opt) {
            $('<option></option>').attr('value', opt.value).text(opt.label).appendTo($typeSelect);
        });
        $typeSelect.val(condition.type);

        $delete.on('click', function () {
            $row.remove();
        });

        $typeSelect.on('change', function () {
            renderValueField($valueWrap, $typeSelect.val(), {});
        });

        $row.append($typeSelect).append($valueWrap).append($delete);
        $list.append($row);

        renderValueField($valueWrap, condition.type, condition.params || {});
    }

    /**
     * @param {jQuery} $valueWrap
     * @param {String} type
     * @param {Object} existingParams
     */
    function renderValueField($valueWrap, type, existingParams) {
        var field = VALUE_FIELD_BY_TYPE[type];

        $valueWrap.empty();

        if (field) {
            $('<label></label>').text(field.label).appendTo($valueWrap);
            $('<input type="text" class="admin__control-text ordo-group-modal-value-input">')
                .val(existingParams[field.key] || '')
                .appendTo($valueWrap);
            $valueWrap.data('valueKey', field.key);
        } else {
            $('<label></label>').text('Advanced (JSON)').appendTo($valueWrap);
            $('<textarea class="admin__control-textarea ordo-group-modal-json"></textarea>')
                .val(Object.keys(existingParams).length ? JSON.stringify(existingParams) : '')
                .appendTo($valueWrap);
            $valueWrap.data('valueKey', null);
        }
    }

    /**
     * @param {jQuery} $list
     * @return {Array} [{type, params}]
     */
    function readRows($list) {
        var conditions = [];

        $list.find('.ordo-group-modal-row').each(function () {
            var $row = $(this),
                type = $row.find('select').val(),
                $valueWrap = $row.find('.ordo-group-modal-value'),
                valueKey = $valueWrap.data('valueKey'),
                params = {};

            if (valueKey) {
                var value = $.trim($valueWrap.find('input').val());

                if (value !== '') {
                    params[valueKey] = value;
                }
            } else {
                var raw = $.trim($valueWrap.find('textarea').val());

                if (raw !== '') {
                    try {
                        params = JSON.parse(raw);
                    } catch (e) {
                        params = {};
                    }
                }
            }

            conditions.push({type: type, params: params});
        });

        return conditions;
    }

    /**
     * @param {jQuery} $row the condition row (tr.data-row) currently set to type "group"
     * @param {jQuery} $previewCell the (visible) cell the manage-button/preview live in - NOT
     *  necessarily the same cell as group_conditions_json's own (invisible) one, see
     *  refreshGroupRows()'s own comment on why
     */
    function openGroupModal($row, $previewCell) {
        var $jsonField = $row.find('textarea[name*="[group_conditions_json]"]'),
            existing = [],
            typeOptions = readTypeOptions(),
            $modalContent;

        try {
            existing = JSON.parse($jsonField.val() || '[]');
        } catch (e) {
            existing = [];
        }

        if (!Array.isArray(existing)) {
            existing = [];
        }

        $modalContent = buildModalMarkup(existing, typeOptions);

        $modalContent.modal({
            title: 'Group conditions',
            modalClass: 'ordo-group-modal-wrapper',
            buttons: [
                {
                    text: 'Done',
                    class: 'action-primary',
                    click: function () {
                        var conditions = readRows($modalContent.find('.ordo-group-modal-rows'));

                        $jsonField.val(JSON.stringify(conditions)).trigger('change').trigger('input');
                        $modalContent.modal('closeModal');
                        updatePreview($previewCell, conditions);
                    }
                },
                {
                    text: 'Cancel',
                    click: function () {
                        $modalContent.modal('closeModal');
                    }
                }
            ]
        }).modal('openModal');
    }

    /**
     * @param {jQuery} $groupCell
     * @param {Array} conditions
     */
    function updatePreview($groupCell, conditions) {
        var $preview = $groupCell.find('.ordo-group-preview'),
            count = conditions.length;

        $preview.text(count === 1 ? '1 condition configured' : count + ' conditions configured');
    }

    /**
     * Finds every condition row currently set to type "group" and, if it doesn't already have
     * the manage-button/preview injected, adds them next to the hidden group_conditions_json
     * field. Re-run on every change to the outer Type select and after every dynamicRows
     * add/delete, since rows (and their type) can change at any time.
     */
    function refreshGroupRows() {
        $('[data-index="conditions"] tr.data-row').each(function () {
            var $row = $(this),
                type = $row.find('[data-index="type"] select').val(),
                $jsonField = $row.find('textarea[name*="[group_conditions_json]"]'),
                // Magento's own knockout binding sets `visible: elem.visible()` on the <td>
                // itself, not just the field div inside it - since group_conditions_json's own
                // visible is permanently false (see ordo_segment_form.xml), its <td> is always
                // display:none too, which would hide anything appended inside it, manage button
                // included. group_logic's own <td> stays visible for a 'group' row, so the
                // button/preview are injected there instead - a sibling field's cell, not this
                // field's own (invisible) one.
                $groupCell = $row.find('[data-index="group_logic"]').closest('td');

            if (!$groupCell.length) {
                return;
            }

            if (type !== 'group') {
                $groupCell.find('.ordo-group-manage-button, .ordo-group-preview').remove();
                return;
            }

            if ($groupCell.find('.ordo-group-manage-button').length) {
                return;
            }

            var existing = [];

            try {
                existing = JSON.parse($jsonField.val() || '[]');
            } catch (e) {
                existing = [];
            }
            if (!Array.isArray(existing)) {
                existing = [];
            }

            $('<button type="button" class="ordo-group-manage-button">Manage group conditions</button>')
                .on('click', function () {
                    openGroupModal($row, $groupCell);
                })
                .appendTo($groupCell);
            $('<div class="ordo-group-preview"></div>').appendTo($groupCell);
            updatePreview($groupCell, existing);
        });
    }

    $(document).on('change', '[data-index="conditions"] select', function () {
        // A change on any Type select (including one that just switched a row to/from "group")
        // - re-scan on a tick delay so knockout's own visible-binding toggle for
        // group_conditions_json (see ordo_segment_form.xml's switcherConfig) has already applied
        // before this looks for it.
        setTimeout(refreshGroupRows, 50);
    });

    $(document).on('click', '[data-index="conditions"] button[data-action="add_new_row"], [data-index="conditions"] button.action-delete', function () {
        setTimeout(refreshGroupRows, 150);
    });

    refreshGroupRows();
});
