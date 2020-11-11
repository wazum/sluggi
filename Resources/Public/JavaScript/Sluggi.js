define([
    'jquery'
], function ($) {
    var recreateTitle = $('button.t3js-form-field-slug-recreate').attr('title');
    var triggerRecreate = function () {
        $('.t3js-form-field-slug-recreate').trigger('click');
    };
    var hideEdit = function (title) {
        $('button.t3js-form-field-slug-toggle').hide();
        $('button.t3js-form-field-slug-recreate')
            .prop('disabled', true)
            .attr('title', title);
    };
    var showEdit = function () {
        $('.t3js-form-field-slug-toggle').show();
        $('button.t3js-form-field-slug-recreate')
            .prop('disabled', false)
            .attr('title', recreateTitle);
    };
    var syncLabelSelector = 'label[for*="tx_sluggi_sync"]';
    var syncCheckboxId = $(syncLabelSelector).attr('for');
    var syncCheckboxName = $('input[id="' + syncCheckboxId + '"]').data('formengine-input-name');
    var syncCheckboxSelector = 'input[name="' + syncCheckboxName + '"';
    var lockLabelSelector = 'label[for*="tx_sluggi_lock"]';
    var lockCheckboxId = $(lockLabelSelector).attr('for');
    var lockCheckboxName = $('input[id="' + lockCheckboxId + '"]').data('formengine-input-name');
    var lockCheckboxSelector = 'input[name="' + lockCheckboxName + '"';
    if ($(lockCheckboxSelector).val() === '1') {
        hideEdit('Lock active');
    } else if ($(syncCheckboxSelector).val() === '1') {
        hideEdit('Sync active');
    }
    $(document).ready(function () {
        if ('tx_sluggi_lock' in window && window.tx_sluggi_lock === true) {
            $('button.t3js-form-field-slug-toggle').hide();
            $('button.t3js-form-field-slug-recreate')
                .prop('disabled', true)
                .attr('title', 'Lock active');
        } else if ($(syncCheckboxSelector).length === 0 && 'tx_sluggi_sync' in window && window.tx_sluggi_sync === true) {
            $('button.t3js-form-field-slug-toggle').hide();
            $('button.t3js-form-field-slug-recreate')
                .prop('disabled', true)
                .attr('title', 'Sync active');
        }
    });
    $(document).on('blur', '.slug-impact', function (e) {
        var syncGloballyActive = $(syncCheckboxSelector).length === 0 && 'tx_sluggi_sync' in window && window.tx_sluggi_sync === true;
        if ($(syncCheckboxSelector).val() === '1' || syncGloballyActive) {
            if (syncGloballyActive) {
                $('button.t3js-form-field-slug-recreate').prop('disabled', false);
            }
            triggerRecreate();
            if (syncGloballyActive) {
                $('button.t3js-form-field-slug-recreate').prop('disabled', true);
            }
        }
    });
    $(document).on('click', syncLabelSelector, function (e) {
        // 0 = activated (the value changes afterwards)
        if ($(syncCheckboxSelector).val() === '0') {
            triggerRecreate();
            if ($(lockCheckboxSelector).val() === '1') {
                $(lockLabelSelector).trigger('click');
            }
            hideEdit('Sync active');
        } else if ($(lockCheckboxSelector).length === 0 || $(lockCheckboxSelector).val() === '0') {
            showEdit();
        }
    });
    $(document).on('click', lockLabelSelector, function (e) {
        // 0 = activated (the value changes afterwards)
        if ($(lockCheckboxSelector).val() === '0') {
            if ($(syncCheckboxSelector).val() === '1' || ('tx_sluggi_sync' in window && window.tx_sluggi_sync === true)) {
                $(syncLabelSelector).trigger('click');
            }
            hideEdit('Lock active');
        } else if ($(syncCheckboxSelector).length === 0 || $(syncCheckboxSelector).val() === '0') {
            if (('tx_sluggi_sync' in window && window.tx_sluggi_sync === true)) {
                triggerRecreate();
            } else {
                showEdit();
            }
        }
    });
});
