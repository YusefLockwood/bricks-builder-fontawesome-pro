<?php
/**
 * Plugin Name: Bricks Builder FontAwesome Pro
 * Description: Integrates Font Awesome icons into Bricks Builder, including support for Pro icons, custom weights, and families. Provides an easy-to-use interface for selecting and customizing icons.
 * Version: 1.0
 * Author: Yusef Lockwood
 * Author URI: https://iamyusef.dev/
 * Text Domain: bricks-builder-fontawesome-pro
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html 
 * License: GPL-2.0-or-later
 * Requires at Least: 5.0
 * Requires PHP: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

// Load the BFAP settings page code from /includes/bfap-settings.php
require_once plugin_dir_path( __FILE__ ) . 'includes/bfap-settings.php';

/**
 * Delete all transients related to the plugin when the plugin is deleted.
 */
function bfap_on_plugin_uninstall() {
  bfap_delete_all_transients();
}
register_uninstall_hook( __FILE__, 'bfap_on_plugin_uninstall' );

/**
 * Retrieve or create a Bearer token from https://api.fontawesome.com/token
 * using the user's API key from BFAP settings.
 */
function bfap_get_fontawesome_bearer_token($api_key = '') {
  $transient_key = 'bfap_fontawesome_bearer_token';
  $token         = get_transient( $transient_key );

  if ( $token !== false ) {
    return $token;
  }

  $options = get_option( 'bfap_fontawesome_options', [] );

  if (  empty( $api_key ) ) {
    $api_key = isset( $options['fa_api_key'] ) ? $options['fa_api_key'] : '';
  }

  if ( empty( $api_key ) ) {
    return '';
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
    return '';
  }

  $body_data = json_decode( wp_remote_retrieve_body( $response ), true );
  $bearer     = isset( $body_data['access_token'] ) ? $body_data['access_token'] : '';
  $expires_in = isset( $body_data['expires_in'] )   ? (int) $body_data['expires_in'] : 3600;

  if ( ! empty( $bearer ) ) {
    set_transient( $transient_key, $bearer, $expires_in );
    return $bearer;
  }

  return '';
}

/**
 * Fetch the latest Font Awesome version from GitHub.
 */
function bfap_get_latest_fontawesome_version() {
  $transient_key = 'bfap_latest_fontawesome_version';
  $version       = get_transient( $transient_key );

  if ( false === $version ) {
    $response = wp_remote_get( 'https://api.github.com/repos/FortAwesome/Font-Awesome/releases/latest' );

    if ( is_wp_error( $response ) ) {
      return '';
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( isset( $data['tag_name'] ) ) {
      $version = ltrim( $data['tag_name'], 'v' );
      set_transient( $transient_key, $version, 12 * HOUR_IN_SECONDS );
    }
  }

  return $version;
}

/**
 * Fetch the user's kits (token + name + release{version}).
 */
function bfap_get_fontawesome_kits_list() {
  $transient_key = 'bfap_fontawesome_kits_list';
  $kits          = get_transient( $transient_key );

  if ( false === $kits ) {
    $bearer_token = bfap_get_fontawesome_bearer_token();
    if ( empty( $bearer_token ) ) {
      return [];
    }

    $query = 'query AllKitsWithReleaseVersion {
  me {
    kits {
      name
      token
      release {
        version
      }
    }
  }
}';

    $response = wp_remote_post(
      'https://api.fontawesome.com/graphql',
      [
        'headers' => [
          'Authorization' => 'Bearer ' . $bearer_token,
          'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [ 'query' => $query ] ),
        'timeout' => 15,
      ]
    );

    if ( is_wp_error( $response ) ) {
      return [];
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if (
      ! is_array( $data )
      || ! isset( $data['data']['me']['kits'] )
      || ! is_array( $data['data']['me']['kits'] )
    ) {
      return [];
    }

    $kits_raw = $data['data']['me']['kits'];
    $kits     = [];

    foreach ( $kits_raw as $kit ) {
      $token   = isset( $kit['token'] ) ? $kit['token'] : '';
      $name    = isset( $kit['name'] )  ? $kit['name']  : '';
      $version = isset( $kit['release']['version'] ) ? $kit['release']['version'] : '';

      if ( $token ) {
        $kits[ $token ] = [
          'name'    => $name,
          'version' => $version,
        ];
      }
    }

    set_transient( $transient_key, $kits, 12 * HOUR_IN_SECONDS );
  }

  return $kits;
}

/**
 * Query icons for a specific release version, detecting brand icons
 * via familyStylesByLicense->free/pro arrays each having { style='brands' } if brand.
 */
function bfap_get_fa_icons_by_release_version( $version ) {
  if ( empty( $version ) ) {
    return [];
  }

  $transient_key = 'bfap_fa_icons_for_version_' . md5( $version );
  $icons         = get_transient( $transient_key );

  if ( false === $icons ) {
    $bearer_token = bfap_get_fontawesome_bearer_token();
    if ( empty( $bearer_token ) ) {
      return [];
    }

    $query = 'query ReleaseIcons($version: String!) {
  release(version: $version) {
    icons {
      id
      label
      familyStylesByLicense {
        free {
          style
        }
        pro {
          style
        }
      }
    }
  }
}';

    $response = wp_remote_post(
      'https://api.fontawesome.com/graphql',
      [
        'headers' => [
          'Authorization' => 'Bearer ' . $bearer_token,
          'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode([
          'query'     => $query,
          'variables' => [ 'version' => $version ],
        ]),
        'timeout' => 15,
      ]
    );

    pfg_log($response);

    if ( is_wp_error( $response ) ) {
      return [];
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if (
      ! is_array( $data )
      || ! isset( $data['data']['release']['icons'] )
      || ! is_array( $data['data']['release']['icons'] )
    ) {
      return [];
    }

    $icons_raw = $data['data']['release']['icons'];
    $icons     = [];

    foreach ( $icons_raw as $icon ) {
      $icon_id    = isset( $icon['id'] ) ? $icon['id'] : '';
      $icon_label = isset( $icon['label'] ) ? $icon['label'] : $icon_id;

      $is_brand = false;
      if ( isset( $icon['familyStylesByLicense'] ) ) {
        $fsl = $icon['familyStylesByLicense'];
        // check free
        if ( isset( $fsl['free'] ) && is_array( $fsl['free'] ) ) {
          foreach ( $fsl['free'] as $obj ) {
            if ( isset( $obj['style'] ) && $obj['style'] === 'brands' ) {
              $is_brand = true;
              break;
            }
          }
        }
        // check pro
        if ( ! $is_brand && isset( $fsl['pro'] ) && is_array( $fsl['pro'] ) ) {
          foreach ( $fsl['pro'] as $obj ) {
            if ( isset( $obj['style'] ) && $obj['style'] === 'brands' ) {
              $is_brand = true;
              break;
            }
          }
        }
      }

      if ( $icon_id ) {
        $icons[ $icon_id ] = [
          'id'    => $icon_id,
          'label' => $icon_label,
          'brand' => $is_brand,
        ];
      }
    }

    set_transient( $transient_key, $icons, 12 * HOUR_IN_SECONDS );
  }

  return $icons;
}

/**
 * Determine if we are using the free version or the pro version of Font Awesome.
 *
 * @return bool True if using the pro version, false if using the free version.
 */
function bfap_is_pro_version() {
  $options   = get_option( 'bfap_fontawesome_options', [] );
  $api_key   = isset( $options['fa_api_key'] ) ? $options['fa_api_key'] : '';
  $kit_token = isset( $options['fa_chosen_kit'] ) ? $options['fa_chosen_kit'] : '';

  return !empty( $api_key ) && !empty( $kit_token );
}

/**
 * Fetch icons from the latest Font Awesome version by parsing the icons.json file from GitHub.
 */
function bfap_get_fa_icons_from_cdn($version) {
  $transient_key = 'bfap_fa_icons_from_cdn';
  $icons         = get_transient( $transient_key );

  if ( false === $icons ) {
    // Use only the first whole number of the version
    $major_version = explode('.', $version)[0];
    $response = wp_remote_get( 'https://raw.githubusercontent.com/FortAwesome/Font-Awesome/refs/heads/' . $major_version . '.x/metadata/icons.json' );

    if ( is_wp_error( $response ) ) {
      return [];
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! is_array( $data ) ) {
      return [];
    }

    $icons = [];
    foreach ( $data as $icon_id => $icon_data ) {
      $icon_label = isset( $icon_data['label'] ) ? $icon_data['label'] : $icon_id;
      $is_brand   = isset( $icon_data['styles'] ) && in_array( 'brands', $icon_data['styles'], true );

      $icons[ $icon_id ] = [
        'id'    => $icon_id,
        'label' => $icon_label,
        'brand' => $is_brand,
      ];
    }

    set_transient( $transient_key, $icons, 12 * HOUR_IN_SECONDS );
  }

  return $icons;
}

/**
 * Returns an array for the Bricks select control: 
 *  - If brand => key = 'icon_id_brand'
 *  - The label includes an <i> tag for a small preview 
 */
function bfap_get_fontawesome_icons_list() {
  $options   = get_option( 'bfap_fontawesome_options', [] );
  $kit_token = isset( $options['fa_chosen_kit'] ) ? $options['fa_chosen_kit'] : '';

  if ( bfap_is_pro_version() ) {
    $kits = bfap_get_fontawesome_kits_list();
    $version = $kits[ $kit_token ]['version'] ?? '';
    $raw_icons = bfap_get_fa_icons_by_release_version( $version );
  } else {
    $version = bfap_get_latest_fontawesome_version();
    $raw_icons = bfap_get_fa_icons_from_cdn($version);
  }

  if ( empty( $raw_icons ) ) {
    return [];
  }

  $results = [];
  foreach ( $raw_icons as $icon_id => $meta ) {
    $brand    = $meta['brand'];
    $iconName = $meta['label'];

    // e.g. "twitter_brand" if brand => "twitter"
    $key = $brand ? ($icon_id . '_brand') : $icon_id;

    // For display in the label only. Actual final prefix is decided in the element class.
    $fa_prefix = $brand ? 'fa-brands' : 'fa-solid';

    $i_html   = sprintf(
      '<i class="%s fa-%s" style="margin-right:5px;"></i>',
      esc_attr( $fa_prefix ),
      esc_attr( $icon_id )
    );
    $label    = sprintf( '%s (%s)', $iconName, $icon_id );

    $results[ $key ] = $i_html . $label;
  }

  return $results;
}

/**
 * Enqueue the kit script for the chosen kit or the latest Font Awesome CDN.
 */
function bfap_enqueue_fontawesome_kit() {
  // Prevent Bricks Builder from enqueuing its own Font Awesome CSS files
  wp_dequeue_style( 'bricks-font-awesome-6-brands' );
  wp_dequeue_style( 'bricks-font-awesome-6' );

  if ( bfap_is_pro_version() ) {
    $options   = get_option( 'bfap_fontawesome_options', [] );
    $kit_token = isset( $options['fa_chosen_kit'] ) ? $options['fa_chosen_kit'] : '';
    $fa_url = 'https://kit.fontawesome.com/' . $kit_token . '.js';

    wp_enqueue_script(
      'bfap-fa-kit',
      $fa_url,
      [],
      $kit_token,
      false
    );
  } else {
    $latest_version = bfap_get_latest_fontawesome_version();
    $fa_url = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/' . $latest_version . '/css/all.min.css';

    if ( $latest_version ) {
      wp_enqueue_style(
        'bfap-fa-cdn',
        $fa_url,
        [],
        $latest_version
      );
    }
  }
}
add_action( 'wp_enqueue_scripts', 'bfap_enqueue_fontawesome_kit', 20 );
add_action( 'bricks_enqueue_scripts', 'bfap_enqueue_fontawesome_kit', 20 );

/**
 * Register the BFAP element class in /includes/class-bfap-fontawesome-element.php with Bricks.
 */
function bfap_register_bricks_element_class() {
  if ( class_exists( '\\Bricks\\Elements' ) ) {
    \Bricks\Elements::register_element(
      plugin_dir_path( __FILE__ ) . 'includes/class-bfap-fontawesome-element.php'
    );
  }
}
add_action( 'init', 'bfap_register_bricks_element_class', 20 );
