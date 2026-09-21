<?php
/**
 * Gerenciador de sincronização de estoque (Fluxo PULL: CEGID -> WooCommerce).
 *
 * @package WC_Cegid_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cegid_Stock_Sync {

	/**
	 * Inicializa os hooks de estoque periódicos.
	 */
	public static function init() {
		// Hook assíncrono para execução do WP-Cron periódico
		add_action( 'cegid_pull_stock_cron_action', [ __CLASS__, 'execute_global_pull_sync' ] );

		// Registra o evento de Cron recorrente se não estiver agendado
		if ( ! wp_next_scheduled( 'cegid_pull_stock_cron_action' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'cegid_pull_stock_cron_action' );
		}
	}

	/**
	 * Executa a sincronização global de estoques da CEGID para o WooCommerce (PULL).
	 * Pode ser disparada pelo WP-Cron ou manualmente via AJAX.
	 *
	 * @return array Resumo do resultado da sincronização.
	 */
	public static function execute_global_pull_sync() {
		// Valida se a licença está ativa antes de rodar a sincronização
		if ( ! Cegid_Settings::is_license_active() ) {
			return [
				'success' => false,
				'total'   => 0,
				'updated' => 0,
				'errors'  => 1,
				'message' => __( 'Acesso bloqueado: licença inativa.', 'wc-cegid-sync' )
			];
		}

		$summary = [
			'success'   => true,
			'total'     => 0,
			'updated'   => 0,
			'skipped'   => 0,
			'errors'    => 0,
			'details'   => [],
		];

		// Busca produtos (simples) e variações que gerenciam estoque no WooCommerce
		$args = [
			'limit'        => -1,
			'status'       => 'publish',
			'manage_stock' => true,
			'return'       => 'ids',
		];

		$product_ids = wc_get_products( $args );

		if ( empty( $product_ids ) ) {
			$summary['details'][] = __( 'Nenhum produto com gerenciamento de estoque ativo foi encontrado no WooCommerce.', 'wc-cegid-sync' );
			return $summary;
		}

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$summary['total']++;
			$sku = $product->get_sku();

			if ( empty( $sku ) ) {
				$summary['skipped']++;
				$summary['details'][] = sprintf(
					/* translators: %d: ID do produto */
					__( 'Produto ID %d ignorado: SKU ausente.', 'wc-cegid-sync' ),
					$product_id
				);
				continue;
			}

			$is_service = Cegid_Data_Mapper::is_service( $product );

			// 1. Tenta obter produto diretamente pelo ID CEGID se vinculado
			$cegid_product_id = $product->get_meta( '_cegid_product_id', true );
			$cegid_product    = null;

			if ( ! empty( $cegid_product_id ) ) {
				$cegid_product = Cegid_API_Client::get_product( $cegid_product_id, $is_service );
			}

			// 2. Se não encontrou por ID, busca por SKU
			if ( ! $cegid_product ) {
				$cegid_product = Cegid_API_Client::get_product_by_sku( $sku, $is_service );
				if ( $cegid_product && ! empty( $cegid_product['id'] ) ) {
					$product->update_meta_data( '_cegid_product_id', $cegid_product['id'] );
				}
			}

			if ( ! $cegid_product ) {
				$summary['errors']++;
				$err_msg = sprintf(
					/* translators: %s: SKU do produto */
					__( 'Produto SKU %s não encontrado na CEGID.', 'wc-cegid-sync' ),
					$sku
				);
				$product->update_meta_data( '_cegid_sync_error', $err_msg );
				$product->save();
				$summary['details'][] = $err_msg;
				continue;
			}

			$now_time = time();

			// Cenário A: Item de serviço/assinatura
			if ( $is_service ) {
				$product->update_meta_data( '_cegid_stock_qty', 'service' );
				$product->update_meta_data( '_cegid_last_sync', $now_time );
				$product->delete_meta_data( '_cegid_sync_error' );
				$product->save();
				$summary['skipped']++;
				$summary['details'][] = sprintf(
					/* translators: %s: SKU */
					__( 'SKU %s é serviço/assinatura (sem controle de estoque físico).', 'wc-cegid-sync' ),
					$sku
				);
				continue;
			}

			// Cenário B: Produto físico - lê a quantidade atualizada na CEGID
			$cegid_qty = Cegid_API_Client::extract_stock_quantity( $cegid_product );
			$local_qty = (float) $product->get_stock_quantity();

			if ( $cegid_qty === null ) {
				// Saldo não disponibilizado pela API do CEGID: preserva estoque local do WooCommerce
				$product->update_meta_data( '_cegid_stock_qty', 'na' );
				$product->update_meta_data( '_cegid_last_sync', $now_time );
				$product->delete_meta_data( '_cegid_sync_error' );
				$product->save();
				$summary['skipped']++;
				$summary['details'][] = sprintf(
					/* translators: 1: SKU, 2: Quantidade */
					__( 'SKU %1$s: quantidade não exposta pela API do CEGID (estoque local de %2$s un. preservado).', 'wc-cegid-sync' ),
					$sku,
					$local_qty
				);
				continue;
			}

			// Cenário C: Atualização real de saldo vindo da CEGID
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $cegid_qty );
			$product->set_stock_status( $cegid_qty > 0 ? 'instock' : 'outofstock' );

			if ( $local_qty !== $cegid_qty ) {
				wc_update_product_stock( $product, $cegid_qty, 'set' );
				$summary['updated']++;
				$summary['details'][] = sprintf(
					/* translators: 1: SKU, 2: Estoque antigo, 3: Estoque novo */
					__( 'SKU %1$s atualizado com sucesso de %2$s para %3$s.', 'wc-cegid-sync' ),
					$sku,
					$local_qty,
					$cegid_qty
				);
			} else {
				$summary['skipped']++;
			}

			// Atualiza metadados após leitura com sucesso
			$product->update_meta_data( '_cegid_stock_qty', $cegid_qty );
			$product->update_meta_data( '_cegid_last_sync', $now_time );
			$product->delete_meta_data( '_cegid_sync_error' );
			$product->save();
		}

		return $summary;
	}
}
