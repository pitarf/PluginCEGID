<?php
/**
 * Verificação de integridade e detecção de adulteração de código (Tamper Detection).
 *
 * @package WC_Cegid_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WC_CEGID_SYNC_PLUGIN_DIR' ) ) {
	define( 'WC_CEGID_SYNC_PLUGIN_DIR', defined( 'WC_CEGID_SYNC_PATH' ) ? WC_CEGID_SYNC_PATH : dirname( __DIR__ ) . '/' );
}

class Cegid_Integrity {

	/**
	 * Versão atual oficial do plugin.
	 */
	const VERSION = '1.4.3';

	/**
	 * Calcula o hash SHA-256 normalizado de um arquivo do plugin.
	 * Normaliza quebras de linha (\r\n para \n) para consistência entre sistemas operacionais (Windows/Linux/macOS).
	 *
	 * @param string $relative_path Caminho relativo a partir da raiz do plugin.
	 * @return string|null Hash hexadecimal SHA-256 ou null se o arquivo não existir.
	 */
	public static function get_file_hash( $relative_path ) {
		$base_dir  = defined( 'WC_CEGID_SYNC_PATH' ) ? WC_CEGID_SYNC_PATH : ( defined( 'WC_CEGID_SYNC_PLUGIN_DIR' ) ? WC_CEGID_SYNC_PLUGIN_DIR : dirname( __DIR__ ) . '/' );
		$full_path = rtrim( $base_dir, '/\\' ) . '/' . ltrim( $relative_path, '/\\' );
		if ( ! file_exists( $full_path ) || ! is_readable( $full_path ) ) {
			return null;
		}

		$content = file_get_contents( $full_path );
		if ( false === $content ) {
			return null;
		}

		// Normaliza quebras de linha para evitar divergências por Git autocrlf
		$normalized = str_replace( [ "\r\n", "\r" ], "\n", $content );
		return hash( 'sha256', $normalized );
	}

	/**
	 * Gera o manifesto criptográfico de integridade dos arquivos vitais do plugin.
	 *
	 * @return array Manifesto contendo versão e hashes dos módulos centrais.
	 */
	public static function get_manifest() {
		return [
			'version'  => self::VERSION,
			'settings' => self::get_file_hash( 'includes/class-cegid-settings.php' ),
			'ajax'     => self::get_file_hash( 'includes/class-cegid-ajax-handler.php' ),
			'client'   => self::get_file_hash( 'includes/class-cegid-api-client.php' ),
			'core'     => self::get_file_hash( 'wc-cegid-sync.php' ),
		];
	}

	/**
	 * Verifica se algum dos arquivos vitais teve seu conteúdo corrompido ou apagado.
	 *
	 * @return bool True se todos os arquivos vitais estiverem legíveis e com hashes gerados, false caso contrário.
	 */
	public static function is_local_intact() {
		$manifest = self::get_manifest();
		foreach ( [ 'settings', 'ajax', 'client', 'core' ] as $key ) {
			if ( empty( $manifest[ $key ] ) || strlen( $manifest[ $key ] ) !== 64 ) {
				return false;
			}
		}
		return true;
	}
}
