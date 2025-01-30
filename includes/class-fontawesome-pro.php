<?php

namespace BFAP;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

class FontAwesome_Pro {
  public static function get_kits_list() {
    $transient_key = 'bfap_fontawesome_kits_list';
    $kits          = get_transient( $transient_key );

    if ( false === $kits ) {
      $bearer_token = FontAwesome::get_bearer_token();
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

  public static function get_icons_by_release_version( $version ) {
    if ( empty( $version ) ) {
      return [];
    }

    $transient_key = 'bfap_fa_icons_for_version_' . md5( $version );
    $icons         = get_transient( $transient_key );

    if ( false === $icons ) {
      $bearer_token = FontAwesome::get_bearer_token();
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
}
