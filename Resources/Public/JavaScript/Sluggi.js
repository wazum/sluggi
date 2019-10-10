define([
    'jquery'
  ], function ($) {
    if ($('input[name*="][tx_sluggi_sync]"]').val() > 0) {
      $(document).on('blur', '.slug-impact', function (e) {
        if ($('input[name*="][tx_sluggi_sync]"]').val() > 0) {
          $('.t3js-form-field-slug-recreate').trigger('click');
        }
      });
    }
    $(document).on('click', 'label.slug-sync', function (e) {
        if ($('input[name*="][tx_sluggi_sync]"]').val() < 1) {
            $('.t3js-form-field-slug-recreate').trigger('click');
        }
    });
  }
);
