<?php

namespace BFAP;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

class FontAwesome {
  public static function get_bearer_token($api_key = '') {
    $transient_key = 'bfap_fontawesome_bearer_token';
    $token         = get_transient( $transient_key );

    if ( $token !== false ) {
      return $token;
    }

    $options = get_option( 'bfap_fontawesome_options', [] );

    if ( empty( $api_key ) ) {
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

  public static function get_latest_version() {
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

  public static function is_pro_version() {
    $options   = get_option( 'bfap_fontawesome_options', [] );
    $api_key   = isset( $options['fa_api_key'] ) ? $options['fa_api_key'] : '';
    $kit_token = isset( $options['fa_chosen_kit'] ) ? $options['fa_chosen_kit'] : '';

    return !empty( $api_key ) && !empty( $kit_token );
  }

  public static function get_fontawesome_icons_list() {
    $options   = get_option( 'bfap_fontawesome_options', [] );
    $kit_token = isset( $options['fa_chosen_kit'] ) ? $options['fa_chosen_kit'] : '';

    if ( self::is_pro_version() ) {
      $kits = FontAwesome_Pro::get_kits_list();
      $version = $kits[ $kit_token ]['version'] ?? '';
      $raw_icons = FontAwesome_Pro::get_icons_by_release_version( $version );
    } else {
      $version = self::get_latest_version();
      $raw_icons = FontAwesome_Free::get_icons_from_cdn($version);
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
}
