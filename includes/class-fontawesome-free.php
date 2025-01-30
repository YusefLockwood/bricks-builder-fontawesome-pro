<?php

namespace BFAP;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

class FontAwesome_Free {
  public static function get_icons_from_cdn($version) {
    $transient_key = 'bfap_fa_icons_from_cdn';
    $icons         = get_transient( $transient_key );

    if ( false === $icons ) {
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
}
