<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
 * Registers the plugin settings and adds the admin subpage under "Settings".
 */
function bfap_register_settings() {
  register_setting( 'bfap_fontawesome_options_group', 'bfap_fontawesome_options', 'bfap_sanitize_options' );

  add_options_page(
    __( 'Bricks FontAwesome', 'bricks-builder-fontawesome-pro' ),
    __( 'Bricks FontAwesome', 'bricks-builder-fontawesome-pro' ),
    'manage_options',
    'bfap-fontawesome',
    'bfap_fontawesome_options_page'
  );
}
add_action( 'admin_menu', 'bfap_register_settings' );

/**
 * Delete all transients related to the plugin.
 */
function bfap_delete_all_transients() {
  global $wpdb;

  // Delete specific transients
  delete_transient( 'bfap_fontawesome_bearer_token' );
  delete_transient( 'bfap_fontawesome_kits_list' );
  delete_transient( 'bfap_fa_icons_from_cdn' );

  // Delete version-specific transients
  $transient_name = '_transient_bfap_fa_icons_for_version_%';
  $sql = $wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    $transient_name
  );
  $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

  $transient_name = '_transient_timeout_bfap_fa_icons_for_version_%';
  $sql = $wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    $transient_name
  );
  $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Sanitizes the options and ensures API key, kits, font weight, and font family fields are properly handled.
 * Deletes transients when settings are updated to ensure fresh data.
 */
function bfap_sanitize_options( $input ) {
  $sanitized = [];

  // Clear all related transients
  bfap_delete_all_transients();

  if ( isset( $input['fa_api_key'] ) ) {
    $sanitized['fa_api_key'] = sanitize_text_field( $input['fa_api_key'] );
  }

  $valid_weights = bfap_is_pro_version() ? [ 'regular', 'solid', 'light', 'thin' ] : [ 'solid' ];
  if ( isset( $input['fa_weight'] ) && in_array( $input['fa_weight'], $valid_weights, true ) ) {
    $sanitized['fa_weight'] = $input['fa_weight'];
  } else {
    $sanitized['fa_weight'] = 'solid';
  }

  if ( bfap_is_pro_version() ) {
    $valid_families = [ 'classic', 'sharp', 'duotone', 'sharp-duotone' ];
    if ( isset( $input['fa_family'] ) && in_array( $input['fa_family'], $valid_families, true ) ) {
      $sanitized['fa_family'] = $input['fa_family'];
    } else {
      $sanitized['fa_family'] = 'classic';
    }
  } else {
    $sanitized['fa_family'] = 'classic';
  }

  if ( isset( $input['fa_chosen_kit'] ) ) {
    $sanitized['fa_chosen_kit'] = sanitize_text_field( $input['fa_chosen_kit'] );
  } else {
    $sanitized['fa_chosen_kit'] = '';
  }

  return $sanitized;
}

/**
 * Outputs the settings page.
 */
function bfap_fontawesome_options_page() {
  if ( ! current_user_can( 'manage_options' ) ) {
    return;
  }

  $options = get_option( 'bfap_fontawesome_options', [] );
  $api_key = isset( $options['fa_api_key'] ) ? $options['fa_api_key'] : '';
  $chosen_kit = isset( $options['fa_chosen_kit'] ) ? $options['fa_chosen_kit'] : '';
  $fa_weight = isset( $options['fa_weight'] ) ? $options['fa_weight'] : 'solid';
  $fa_family = isset( $options['fa_family'] ) ? $options['fa_family'] : 'classic';

  // Fetch kits if API key exists
  $kits_list = $api_key ? bfap_get_fontawesome_kits_list() : [];
  ?>
  <div class="wrap">
    <h1><?php esc_html_e( 'Bricks FontAwesome Settings', 'bricks-builder-fontawesome-pro' ); ?></h1>
    <form method="post" action="options.php">
      <?php settings_fields( 'bfap_fontawesome_options_group' ); ?>
      <table class="form-table">
        <tr>
          <th scope="row">
            <label for="fa_api_key"><?php esc_html_e( 'Font Awesome API Key', 'bricks-builder-fontawesome-pro' ); ?></label>
          </th>
          <td>
            <input
              type="text"
              id="fa_api_key"
              name="bfap_fontawesome_options[fa_api_key]"
              value="<?php echo esc_attr( $api_key ); ?>"
              style="width: 300px;"
            />
            <button
              type="button"
              class="button"
              id="bfap-verify-api-key"
              data-nonce="<?php echo esc_attr( wp_create_nonce( 'bfap_verify_api_key' ) ); ?>"
            >
              <?php esc_html_e( 'Verify API Key', 'bricks-builder-fontawesome-pro' ); ?>
            </button>
            <p id="bfap-api-status" style="display: none; font-weight: bold;"></p>
            <p class="description">
              <?php esc_html_e( 'Enter your Font Awesome API key and click "Verify API Key" to validate it.', 'bricks-builder-fontawesome-pro' ); ?>
            </p>
          </td>
        </tr>

        <tr id="bfap-kits-row" style="<?php echo !bfap_is_pro_version() ? 'display:none;' : ''; ?>">
          <th scope="row">
            <label for="fa_chosen_kit"><?php esc_html_e( 'Choose Your Kit', 'bricks-builder-fontawesome-pro' ); ?></label>
          </th>
          <td>
            <select id="fa_chosen_kit" name="bfap_fontawesome_options[fa_chosen_kit]">
              <option value=""><?php esc_html_e( '-- Select a Kit --', 'bricks-builder-fontawesome-pro' ); ?></option>
              <?php
              foreach ( $kits_list as $kit_token => $kit_data ) {
                $kit_name = $kit_data['name'] ?? $kit_token;
                printf(
                  '<option value="%s" %s>%s</option>',
                  esc_attr( $kit_token ),
                  selected( $chosen_kit, $kit_token, false ),
                  esc_html( $kit_name )
                );
              }
              ?>
            </select>
          </td>
        </tr>

        <tr>
          <th scope="row">
            <label for="fa_weight"><?php esc_html_e( 'Default Font Weight', 'bricks-builder-fontawesome-pro' ); ?></label>
          </th>
          <td>
            <select id="fa_weight" name="bfap_fontawesome_options[fa_weight]">
              <option value="solid" <?php selected( $fa_weight, 'solid' ); ?>>Solid</option>
              <?php if ( bfap_is_pro_version() ) : ?>
                <option value="regular" <?php selected( $fa_weight, 'regular' ); ?>>Regular</option>
                <option value="light" <?php selected( $fa_weight, 'light' ); ?>>Light</option>
                <option value="thin" <?php selected( $fa_weight, 'thin' ); ?>>Thin</option>
              <?php endif; ?>
            </select>
          </td>
        </tr>

        <tr id="fa_family_row" style="<?php echo !bfap_is_pro_version() ? 'display:none;' : ''; ?>">
          <th scope="row">
            <label for="fa_family"><?php esc_html_e( 'Default Icon Family', 'bricks-builder-fontawesome-pro' ); ?></label>
          </th>
          <td>
            <select id="fa_family" name="bfap_fontawesome_options[fa_family]">
              <option value="classic" <?php selected( $fa_family, 'classic' ); ?>>Classic</option>
              <option value="sharp" <?php selected( $fa_family, 'sharp' ); ?>>Sharp</option>
              <option value="duotone" <?php selected( $fa_family, 'duotone' ); ?>>Duotone</option>
              <option value="sharp-duotone" <?php selected( $fa_family, 'sharp-duotone' ); ?>>Sharp Duotone</option>
            </select>
          </td>
        </tr>

      </table>
      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}

/**
 * Handles the AJAX request to verify the API key.
 */
function bfap_verify_api_key() {
  check_ajax_referer( 'bfap_verify_api_key', 'nonce' );

  $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash($_POST['api_key']) ) : '';

  if ( empty( $api_key ) ) {
    wp_send_json_error( [ 'message' => __( 'API Key is required.', 'bricks-builder-fontawesome-pro' ) ] );
  }

  $response = wp_remote_post(
    'https://api.fontawesome.com/token',
    [
      'headers' => [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
      ],
      'body'    => '',
      'timeout' => 15,
    ]
  );

  if ( is_wp_error( $response ) ) {
    wp_send_json_error( [ 'message' => __( 'Error connecting to Font Awesome API.', 'bricks-builder-fontawesome-pro' ) ] );
  }

  $data = json_decode( wp_remote_retrieve_body( $response ), true );

  if ( isset( $data['access_token'] ) ) {
    wp_send_json_success();
  }

  wp_send_json_error( [ 'message' => __( 'Invalid API Key.', 'bricks-builder-fontawesome-pro' ) ] );
}
add_action( 'wp_ajax_bfap_verify_api_key', 'bfap_verify_api_key' );

/**
 * Handles the AJAX request to fetch kits using the API key.
 */
function bfap_get_kits() {
  check_ajax_referer( 'bfap_verify_api_key', 'nonce' );

  $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash($_POST['api_key']) ) : '';

  if ( empty( $api_key ) ) {
    wp_send_json_error( [ 'message' => __( 'API Key is required.', 'bricks-builder-fontawesome-pro' ) ] );
  }

  $bearer_token = bfap_get_fontawesome_bearer_token($api_key);
  if ( empty( $bearer_token ) ) {
    wp_send_json_error( [ 'message' => __( 'Invalid API Key.', 'bricks-builder-fontawesome-pro' ) ] );
  }

  $kits = bfap_get_fontawesome_kits_list();
  if ( empty( $kits ) ) {
    wp_send_json_error( [ 'message' => __( 'No kits found.', 'bricks-builder-fontawesome-pro' ) ] );
  }

  wp_send_json_success( [ 'kits' => $kits ] );
}
add_action( 'wp_ajax_bfap_get_kits', 'bfap_get_kits' );

/**
 * Enqueues the necessary scripts for the settings page.
 */
function bfap_enqueue_admin_scripts( $hook ) {
  if ( $hook !== 'settings_page_bfap-fontawesome' ) {
    return;
  }

  wp_enqueue_script(
    'bfap-settings-script',
    plugin_dir_url( __FILE__ ) . '../assets/bfap-settings.js',
    [ 'jquery' ],
    '1.0',
    true
  );
}
add_action( 'admin_enqueue_scripts', 'bfap_enqueue_admin_scripts' );