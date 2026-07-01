<?php
/**
 * Enregistrement des pages d'administration du plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-owners-page.php';
require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-properties-page.php';
require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-tenants-page.php';
require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-payments-page.php';
require_once LIMPEED_PLUGIN_DIR . 'admin/class-limpeed-settings-page.php';

class Limpeed_Admin {

	/**
	 * Initialise les hooks admin.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Déclare le menu principal et les sous-menus du plugin.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Limpeed Immobilier', 'limpeed-immobilier' ),
			__( 'Limpeed Immobilier', 'limpeed-immobilier' ),
			'manage_limpeed_properties',
			'limpeed-immobilier',
			array( $this, 'render_dashboard' ),
			'dashicons-building',
			26
		);

		add_submenu_page(
			'limpeed-immobilier',
			__( 'Tableau de bord', 'limpeed-immobilier' ),
			__( 'Tableau de bord', 'limpeed-immobilier' ),
			'manage_limpeed_properties',
			'limpeed-immobilier',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'limpeed-immobilier',
			__( 'Propriétaires', 'limpeed-immobilier' ),
			__( 'Propriétaires', 'limpeed-immobilier' ),
			'manage_limpeed_owners',
			'limpeed-owners',
			array( $this, 'render_owners' )
		);

		add_submenu_page(
			'limpeed-immobilier',
			__( 'Biens', 'limpeed-immobilier' ),
			__( 'Biens', 'limpeed-immobilier' ),
			'manage_limpeed_properties',
			'limpeed-properties',
			array( $this, 'render_properties' )
		);

		add_submenu_page(
			'limpeed-immobilier',
			__( 'Locataires', 'limpeed-immobilier' ),
			__( 'Locataires', 'limpeed-immobilier' ),
			'manage_limpeed_tenants',
			'limpeed-tenants',
			array( $this, 'render_tenants' )
		);

		add_submenu_page(
			'limpeed-immobilier',
			__( 'Paiements', 'limpeed-immobilier' ),
			__( 'Paiements', 'limpeed-immobilier' ),
			'manage_limpeed_payments',
			'limpeed-payments',
			array( $this, 'render_payments' )
		);

		add_submenu_page(
			'limpeed-immobilier',
			__( 'Réglages', 'limpeed-immobilier' ),
			__( 'Réglages', 'limpeed-immobilier' ),
			'manage_limpeed_agents',
			'limpeed-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Charge le CSS/JS admin uniquement sur les pages du plugin.
	 *
	 * @param string $hook
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'limpeed' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'limpeed-admin',
			LIMPEED_PLUGIN_URL . 'admin/assets/admin.css',
			array(),
			LIMPEED_VERSION
		);

		wp_enqueue_script(
			'limpeed-admin',
			LIMPEED_PLUGIN_URL . 'admin/assets/admin.js',
			array(),
			LIMPEED_VERSION,
			true
		);
	}

	/**
	 * Tableau de bord (indicateurs détaillés en Phase 2).
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_limpeed_properties' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'limpeed-immobilier' ) );
		}

		$owners_count     = Limpeed_Owners::count();
		$properties_count = Limpeed_Properties::count();
		$tenants_count    = Limpeed_Tenants::count( array( 'status' => 'actif' ) );
		$vacant_count     = Limpeed_Properties::count( array( 'status' => 'vacant' ) );

		$current_period  = Limpeed_Payments::get_current_period();
		$period_summary  = Limpeed_Payments::get_period_summary( $current_period );

		include LIMPEED_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Page Propriétaires.
	 */
	public function render_owners() {
		$page = new Limpeed_Owners_Page();
		$page->render();
	}

	/**
	 * Page Biens.
	 */
	public function render_properties() {
		$page = new Limpeed_Properties_Page();
		$page->render();
	}

	/**
	 * Page Locataires.
	 */
	public function render_tenants() {
		$page = new Limpeed_Tenants_Page();
		$page->render();
	}

	/**
	 * Page Paiements.
	 */
	public function render_payments() {
		$page = new Limpeed_Payments_Page();
		$page->render();
	}

	/**
	 * Page Réglages.
	 */
	public function render_settings() {
		$page = new Limpeed_Settings_Page();
		$page->render();
	}
}
