jQuery(document).ready(function ($) {
  $('#bfap-verify-api-key').on('click', function (e) {
    e.preventDefault();

    const $button = $(this);
    const $apiField = $('#fa_api_key');
    const $statusMessage = $('#bfap-api-status');
    const $kitsRow = $('#bfap-kits-row');
    const $kitsDropdown = $('#fa_chosen_kit');
    const $faWeight = $('#fa_weight');
    const $faFamilyRow = $('#fa_family_row');
    const apiKey = $apiField.val();
    const nonce = $button.data('nonce');

    if (!apiKey) {
      $statusMessage
        .text('Please enter an API key.')
        .css('color', 'red')
        .show();
      return;
    }

    $button.text('Verifying...').prop('disabled', true);
    $statusMessage.text('').hide(); // Hide previous messages

    $.post(
      ajaxurl,
      {
        action: 'bfap_verify_api_key',
        nonce: nonce,
        api_key: apiKey,
      },
      function (response) {
        if (response.success) {
          $statusMessage
            .text('API Key verified successfully.')
            .css('color', 'green')
            .show();

          // Populate kits dropdown
          $.post(
            ajaxurl,
            {
              action: 'bfap_get_kits',
              nonce: nonce,
              api_key: apiKey,
            },
            function (kitsResponse) {
              console.log(kitsResponse);
              if (kitsResponse.success) {
                $kitsDropdown.empty().append('<option value="">' + '-- Select a Kit --' + '</option>');
                $.each(kitsResponse.data.kits, function (token, kit) {
                  $kitsDropdown.append('<option value="' + token + '">' + kit.name + '</option>');
                });
                $kitsRow.fadeIn();
              } else {
                $kitsRow.hide();
              }
            }
          );

          // Show additional font weight options and font family row
          $faWeight.append('<option value="regular">Regular</option>');
          $faWeight.append('<option value="light">Light</option>');
          $faWeight.append('<option value="thin">Thin</option>');
          $faFamilyRow.show();
        } else {
          $statusMessage
            .text(response.data.message || 'Verification failed. Please try again.')
            .css('color', 'red')
            .show();
          $kitsRow.hide();
          $faFamilyRow.hide();
        }

        $button.text('Verify API Key').prop('disabled', false);
      }
    ).fail(function () {
      $statusMessage
        .text('An error occurred. Please try again.')
        .css('color', 'red')
        .show();
      $button.text('Verify API Key').prop('disabled', false);
    });
  });
});
