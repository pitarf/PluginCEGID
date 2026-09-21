<?php
/**
 * Plugin Name: WC CEGID Sync
 * Plugin URI: https://github.com/valedopais/wc-cegid-sync
 * Description: Sincroniza pedidos concluídos do WooCommerce e estoques com a API da CEGID (TOConline).
 * Version: 1.4.2
 * Author: Rafael Pita
 * Author URI: https://wa.me/5521966149077
 * License: GPLv2 or later
 * Text Domain: wc-cegid-sync
 * Domain Path: /languages
 *
 * @package WC_Cegid_Sync
 */

// Evita o acesso direto ao arquivo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constantes do plugin para uso global
define( 'WC_CEGID_SYNC_VERSION', '1.4.2' );
define( 'WC_CEGID_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_CEGID_SYNC_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_CEGID_SYNC_BASENAME', plugin_basename( __FILE__ ) );
 
// Controle de ativação estrita de licença (false exige ativação via chave no servidor de licenças)
define( 'WC_CEGID_BYPASS_LICENSE', false );

/**
 * Função de inicialização principal do plugin.
 * Garante que o WooCommerce esteja ativo antes de iniciar a execução.
 */
function wc_cegid_sync_init() {
	// Verifica se o WooCommerce está instalado e ativo
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_cegid_sync_missing_woocommerce_notice' );
		return;
	}

	// Carrega as dependências internas do plugin
	require_once WC_CEGID_SYNC_PATH . 'includes/class-cegid-integrity.php';
	require_once WC_CEGID_SYNC_PATH . 'includes/class-cegid-settings.php';
	require_once WC_CEGID_SYNC_PATH . 'includes/class-cegid-api-client.php';
	require_once WC_CEGID_SYNC_PATH . 'includes/class-cegid-data-mapper.php';
	require_once WC_CEGID_SYNC_PATH . 'includes/class-cegid-admin-ui.php';
	require_once WC_CEGID_SYNC_PATH . 'includes/class-cegid-ajax-handler.php';
	require_once WC_CEGID_SYNC_PATH . 'includes/class-cegid-stock-sync.php';

	// Inicializa as classes gerenciadoras
	Cegid_Settings::init();
	Cegid_Admin_UI::init();
	Cegid_Ajax_Handler::init();
	Cegid_Stock_Sync::init();

	// Hooks de sincronização automática de produtos
	add_action( 'woocommerce_update_product', 'wc_cegid_sync_auto_product_sync', 10, 1 );
	add_action( 'woocommerce_save_product_variation', 'wc_cegid_sync_auto_product_sync', 10, 1 );
}
add_action( 'plugins_loaded', 'wc_cegid_sync_init' );

/**
 * Hook de ativação do plugin.
 * Inicializa opções padrões no banco de dados para evitar avisos ou erros.
 */
function wc_cegid_sync_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cegid-settings.php';
	
	$options = get_option( 'wc_cegid_sync_settings' );
	if ( false === $options ) {
		add_option( 'wc_cegid_sync_settings', Cegid_Settings::get_options() );
	}
}
register_activation_hook( __FILE__, 'wc_cegid_sync_activate' );

/**
 * Exibe um alerta no painel administrativo se o WooCommerce não estiver ativo.
 */
function wc_cegid_sync_missing_woocommerce_notice() {
	?>
	<div class="error notice">
		<p><?php esc_html_e( 'O plugin WC CEGID Sync requer que o WooCommerce esteja instalado e ativo para funcionar.', 'wc-cegid-sync' ); ?></p>
	</div>
	<?php
}

/**
 * Sincroniza um produto com a CEGID silenciosamente no momento de sua criação ou alteração.
 *
 * @param int $product_id ID do produto do WooCommerce.
 */
function wc_cegid_sync_auto_product_sync( $product_id ) {
	static $being_synced = [];

	if ( isset( $being_synced[ $product_id ] ) ) {
		return;
	}
	$being_synced[ $product_id ] = true;

	$options = Cegid_Settings::get_options();
	if ( empty( $options['product_auto_sync'] ) || $options['product_auto_sync'] !== 'yes' ) {
		return;
	}

	if ( ! Cegid_Settings::is_license_active() ) {
		return;
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return;
	}

	$sku = $product->get_sku();
	if ( empty( $sku ) ) {
		return;
	}

	// Só cadastra se ele não possuir ID de produto CEGID vinculado ainda
	$cegid_product_id = $product->get_meta( '_cegid_product_id', true );
	if ( ! empty( $cegid_product_id ) ) {
		return;
	}

	// Prepara o payload oficial para a CEGID v0
	$payload = Cegid_Data_Mapper::map_product_to_cegid( $product );

	$response = Cegid_API_Client::create_product( $payload );

	if ( $response && ( ! empty( $response['id'] ) || ! empty( $response['data']['id'] ) ) ) {
		$cegid_id = ! empty( $response['id'] ) ? $response['id'] : $response['data']['id'];
		$product->update_meta_data( '_cegid_product_id', $cegid_id );
		$product->save();
	}
}
