define([
    'jquery'
], function ($) {
    var triggerRecreate = function () {
        $('.t3js-form-field-slug-recreate').trigger('click');
    };
    var hideRecreate = function () {
        $('.t3js-form-field-slug-toggle').hide();
        $('.t3js-form-field-slug-recreate').hide();
    };
    var showRecreate = function () {
        $('.t3js-form-field-slug-toggle').show();
        $('.t3js-form-field-slug-recreate').show();
    };
    var syncLabelSelector = 'label[for*="tx_sluggi_sync"]';
    var syncCheckboxId = $(syncLabelSelector).attr('for');
    var syncCheckboxName = $('input[id="' + syncCheckboxId + '"]').data('formengine-input-name');
    var syncCheckboxSelector = 'input[name="' + syncCheckboxName + '"';
    var lockLabelSelector = 'label[for*="tx_sluggi_lock"]';
    var lockCheckboxId = $(lockLabelSelector).attr('for');
    var lockCheckboxName = $('input[id="' + lockCheckboxId + '"]').data('formengine-input-name');
    var lockCheckboxSelector = 'input[name="' + lockCheckboxName + '"';
    if ($(syncCheckboxSelector).val() === '1' || $(lockCheckboxSelector).val() === '1') {
        hideRecreate();
    }
    $(document).ready(function () {
        if (window && window.hasOwnProperty('tx_sluggi_lock') && window.tx_sluggi_lock === true) {
            $(syncLabelSelector).parents('.form-group').hide();
        }
    });
    $(document).on('blur', '.slug-impact', function (e) {
        if ($(syncCheckboxSelector).val() === '1') {
            triggerRecreate();
        }
    });
    $(document).on('click', syncLabelSelector, function (e) {
        // 0 = activated (the value changes afterwards)
        if ($(syncCheckboxSelector).val() === '0') {
            triggerRecreate();
            if ($(lockCheckboxSelector).val() === '1') {
                $(lockLabelSelector).trigger('click');
            }
            hideRecreate();
        } else if ($(lockCheckboxSelector).val() === '0' || $(lockCheckboxSelector).val() === undefined) {
            showRecreate();
        }
    });
    $(document).on('click', lockLabelSelector, function (e) {
        // 0 = activated (the value changes afterwards)
        if ($(lockCheckboxSelector).val() === '0') {
            if ($(syncCheckboxSelector).val() === '1') {
                $(syncLabelSelector).trigger('click');
            }
            hideRecreate();
        } else if ($(syncCheckboxSelector).val() === '0') {
            showRecreate();
        }
    });
});
