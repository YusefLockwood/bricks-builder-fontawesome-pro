<?php

namespace BFAP;

use BFAP\FontAwesome;
use BFAP\FontAwesome_Pro;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

class BFAP_Fontawesome_Element extends \Bricks\Element {
  public $category = 'General';
  public $name     = 'bfap-fontawesome-element';
  public $icon     = 'fa-brands fa-font-awesome';

  public function get_label() {
    return 'FontAwesome Icon';
  }

  public function set_controls() {
    // Icon select (some icons might have "_brand" in their key if they're brand icons)
    $this->controls['fa_icon'] = [
      'type'       => 'select',
      'label'      => __( 'Select Icon', 'bricks-builder-fontawesome-pro' ),
      'name'       => 'fa_icon',
      'options'    => FontAwesome::get_fontawesome_icons_list(), // from the main plugin file
      'searchable' => true,
    ];

    // Weight select (user can override plugin default)
    $valid_weights = FontAwesome::is_pro_version() ? [
      'regular' => __( 'Regular', 'bricks-builder-fontawesome-pro' ),
      'solid'   => __( 'Solid', 'bricks-builder-fontawesome-pro' ),
      'light'   => __( 'Light', 'bricks-builder-fontawesome-pro' ),
      'thin'    => __( 'Thin', 'bricks-builder-fontawesome-pro' )
    ] : [
      'solid'   => __( 'Solid', 'bricks-builder-fontawesome-pro' )
    ];

    $this->controls['fa_weight'] = [
      'type'    => 'select',
      'label'   => __( 'Font Weight', 'bricks-builder-fontawesome-pro' ),
      'name'    => 'fa_weight',
      'options' => $valid_weights,
    ];

    if ( FontAwesome::is_pro_version() ) {
      // Family select (user can override plugin default)
      $this->controls['fa_family'] = [
        'type'    => 'select',
        'label'   => __( 'Font Family', 'bricks-builder-fontawesome-pro' ),
        'name'    => 'fa_family',
        'options' => [
          ''             => __( '-- Use Plugin Default --', 'bricks-builder-fontawesome-pro' ),
          'classic'      => __( 'Classic', 'bricks-builder-fontawesome-pro' ),
          'sharp'        => __( 'Sharp',   'bricks-builder-fontawesome-pro' ),
          'duotone'      => __( 'Duotone', 'bricks-builder-fontawesome-pro' ),
          'sharp-duotone'=> __( 'Sharp Duotone', 'bricks-builder-fontawesome-pro' ),
        ],
      ];
    }

    // Custom classes for extra styles (e.g. fa-spin, fa-fw, fa-rotate-90)
    $this->controls['fa_custom_classes'] = [
      'type'  => 'text',
      'label' => __( 'Custom Classes', 'bricks-builder-fontawesome-pro' ),
      'name'  => 'fa_custom_classes',
      'help'  => __(
        "Add extra classes like 'fa-fw', 'fa-spin', 'fa-rotate-90', etc.",
        'bricks-builder-fontawesome-pro'
      ),
    ];
  }

  public function render() {
    $settings   = $this->settings;

    // Icon key might be something like "camera_brand" or "twitter_brand" if brand, or just "coffee"
    $iconRaw = isset( $settings['fa_icon'] ) ? sanitize_title( $settings['fa_icon'] ) : '';
    if ( ! $iconRaw ) {
      return; // No icon selected
    }

    // Detect brand suffix "_brand"
    $brandSuffix = '_brand';
    $isBrand     = false;

    if ( substr( $iconRaw, -strlen($brandSuffix) ) === $brandSuffix ) {
      $isBrand = true;
      // Remove "_brand" from the icon name
      $iconRaw = substr( $iconRaw, 0, -strlen($brandSuffix) );
    }

    // Extra custom classes
    $custom_classes = isset( $settings['fa_custom_classes'] )
      ? sanitize_text_field( $settings['fa_custom_classes'] )
      : '';

    // Pull plugin defaults from bfap settings
    $plugin_options = get_option( 'bfap_fontawesome_options', [] );
    $plugin_weight  = isset( $plugin_options['fa_weight'] ) ? $plugin_options['fa_weight'] : 'regular';
    $plugin_family  = isset( $plugin_options['fa_family'] ) ? $plugin_options['fa_family'] : 'classic';

    // The user’s chosen weight/family in Bricks
    $user_weight = isset( $settings['fa_weight'] ) ? sanitize_text_field( $settings['fa_weight'] ) : '';
    $user_family = isset( $settings['fa_family'] ) ? sanitize_text_field( $settings['fa_family'] ) : '';

    // If brand => override everything with fa-brands
    if ( $isBrand ) {
      $prefixClasses = [ 'fa-brands' ];
    } else {
      // Fallback if user left empty => plugin defaults
      $final_weight = $user_weight ?: $plugin_weight;
      $final_family = FontAwesome::is_pro_version() ? ($user_family ?: $plugin_family) : '';

      // e.g. "fa-solid"
      $weight_class = 'fa-' . $final_weight;

      // Handle family
      switch ( $final_family ) {
        case 'sharp':
          $family_class = 'fa-sharp';
          break;
        case 'duotone':
          $family_class = 'fa-duotone';
          break;
        case 'sharp-duotone':
          $family_class = 'fa-sharp fa-duotone';
          break;
        default:
          $family_class = '';
          break;
      }

      $prefixClasses = array_filter([ $weight_class, $family_class ]);
    }

    // Prepend "fa-" to the icon's name for the final class
    // e.g. iconRaw = "camera" => "fa-camera"
    $iconClass = 'fa-' . $iconRaw;

    $classes = array_filter([
      implode( ' ', $prefixClasses ),
      $iconClass,
      $custom_classes,
    ]);

    // Output <i> tag
    echo '<i class="' . esc_attr( implode( ' ', $classes ) ) . '"></i>';
  }
}
