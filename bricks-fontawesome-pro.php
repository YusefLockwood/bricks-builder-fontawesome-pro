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

use BFAP\FontAwesome;
use BFAP\Settings_Page;

class BFAP {
  public function __construct() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-settings-page.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-fontawesome.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-fontawesome-free.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-fontawesome-pro.php';
    
    new Settings_Page();
    
    add_action( 'init', [ $this, 'register_bricks_element_class' ], 20 );
    add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_fontawesome_kit' ], 20 );
    add_action( 'bricks_enqueue_scripts', [ $this, 'enqueue_fontawesome_kit' ], 20 );
    register_uninstall_hook( __FILE__, [ 'BFAP', 'on_plugin_uninstall' ] );
  }

  public function register_bricks_element_class() {
    if ( class_exists( '\\Bricks\\Elements' ) ) {
      \Bricks\Elements::register_element(
        plugin_dir_path( __FILE__ ) . 'includes/class-bfap-fontawesome-element.php'
      );
    }
  }

  public function enqueue_fontawesome_kit() {
    wp_dequeue_style( 'bricks-font-awesome-6-brands' );
    wp_dequeue_style( 'bricks-font-awesome-6' );

    if ( FontAwesome::is_pro_version() ) {
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
      $latest_version = FontAwesome::get_latest_version();
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

  public static function delete_all_transients() {
    global $wpdb;

    delete_transient( 'bfap_fontawesome_bearer_token' );
    delete_transient( 'bfap_fontawesome_kits_list' );
    delete_transient( 'bfap_fa_icons_from_cdn' );

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

  public static function on_plugin_uninstall() {
    $this->delete_all_transients();
  }
}

new BFAP();
