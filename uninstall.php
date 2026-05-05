<?php
/**
 * Uninstall routine — called by WordPress when the plugin is deleted.
 *
 * Drops all plugin tables and removes all plugin options.
 *
 * NOTE: This file is executed only when the plugin is explicitly deleted
 * from the WordPress admin (Plugins → Delete). It does NOT run on deactivation.
 *
 * @package VeriFactu_InFoAL
 */

// Security: WordPress must define this constant before this file runs
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Load the installer class so we can call ::uninstall()
if ( ! class_exists( 'Verifactu_Installer' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-verifactu-installer.php';
}

// Run the uninstall: drop tables + remove options
Verifactu_Installer::uninstall();
