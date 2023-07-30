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
    var lockLabelSelector = 'label[for*="slug_locked"]';
    var lockCheckboxId = $(lockLabelSelector).attr('for');
    var lockCheckboxName = $('input[id="' + lockCheckboxId + '"]').data('formengine-input-name');
    var lockCheckboxSelector = 'input[name="' + lockCheckboxName + '"';
    var slugInputSelector = 'input.t3js-form-field-slug-input';
    if ($(lockCheckboxSelector).val() === '1') {
        hideEdit('Lock active');
    } else if ($(syncCheckboxSelector).val() === '1') {
        hideEdit('Sync active');
    }
    $(document).ready(function () {
        if (parseInt($(slugInputSelector)[0].dataset.txSluggiLock)) {
            $('button.t3js-form-field-slug-toggle').hide();
            $('button.t3js-form-field-slug-recreate')
                .prop('disabled', true)
                .attr('title', 'Lock active');
        } else if ($(syncCheckboxSelector).length === 0 && parseInt($(slugInputSelector)[0].dataset.txSluggiSync)) {
            $('button.t3js-form-field-slug-toggle').hide();
            $('button.t3js-form-field-slug-recreate')
                .prop('disabled', true)
                .attr('title', 'Sync active');
        }
    });
    $(document).on('blur', '.slug-impact', function (e) {
        var syncActive = $(syncCheckboxSelector).length === 0 && parseInt($(slugInputSelector)[0].dataset.txSluggiSync);
        if ($(syncCheckboxSelector).val() === '1' || syncActive) {
            $('button.t3js-form-field-slug-recreate').prop('disabled', false);
            triggerRecreate();
            $('button.t3js-form-field-slug-recreate').prop('disabled', true);
        }
    });
    $(document).on('click', syncLabelSelector + ',#' + syncCheckboxId, function (e) {
        // 0 = activated (the value changes afterwards)
        if ($(syncCheckboxSelector).val() === '0') {
            triggerRecreate();
            if ($(lockCheckboxSelector).val() === '1') {
                $(lockLabelSelector).trigger('click');
                $(lockCheckboxSelector).val(0);
            }
            window.setTimeout(function () {
                hideEdit('Sync active');
            }, 100);
            $(slugInputSelector)[0].dataset.txSluggiSync = '1';
        } else if ($(lockCheckboxSelector).length === 0 || $(lockCheckboxSelector).val() === '0') {
            showEdit();
            $(slugInputSelector)[0].dataset.txSluggiSync = '0';
        }
    });
    $(document).on('click', lockLabelSelector + ',#' + lockCheckboxId, function (e) {
        // 0 = activated (the value changes afterwards)
        if ($(lockCheckboxSelector).val() === '0') {
            if ($(syncCheckboxSelector).val() === '1') {
                $(syncLabelSelector).trigger('click');
                $(syncCheckboxSelector).val(0);
            }
            window.setTimeout(function () {
                hideEdit('Lock active');
            }, 100);
            $(slugInputSelector)[0].dataset.txSluggiLock = '1';
        } else if ($(syncCheckboxSelector).length === 0 || $(syncCheckboxSelector).val() === '0') {
            if (parseInt($(slugInputSelector)[0].dataset.txSluggiSync)) {
                triggerRecreate();
            } else {
                showEdit();
            }
            $(slugInputSelector)[0].dataset.txSluggiLock = '0';
        }
    });
});
