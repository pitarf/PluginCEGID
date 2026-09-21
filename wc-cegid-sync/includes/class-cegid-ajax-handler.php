<?php
/**
 * Manipulador de requisições AJAX administrativas.
 *
 * @package WC_Cegid_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cegid_Ajax_Handler {

	/**
	 * Inicializa os hooks do manipulador AJAX.
	 */
	public static function init() {
		add_action( 'wp_ajax_cegid_generate_invoice', [ __CLASS__, 'ajax_generate_invoice' ] );
		add_action( 'wp_ajax_cegid_sync_stock_manual', [ __CLASS__, 'ajax_sync_stock_manual' ] );
		add_action( 'wp_ajax_cegid_sync_all_stocks', [ __CLASS__, 'ajax_sync_all_stocks' ] );
		add_action( 'wp_ajax_cegid_activate_license', [ __CLASS__, 'ajax_activate_license' ] );
		add_action( 'wp_ajax_cegid_deactivate_license', [ __CLASS__, 'ajax_deactivate_license' ] );
		add_action( 'wp_ajax_cegid_trash_order', [ __CLASS__, 'ajax_trash_order' ] );
		add_action( 'wp_ajax_cegid_create_product', [ __CLASS__, 'ajax_create_product' ] );
		add_action( 'wp_ajax_cegid_verify_product_links', [ __CLASS__, 'ajax_verify_product_links' ] );
		add_action( 'wp_ajax_cegid_bulk_trash_orders', [ __CLASS__, 'ajax_bulk_trash_orders' ] );
		add_action( 'wp_ajax_cegid_bulk_create_products', [ __CLASS__, 'ajax_bulk_create_products' ] );
		add_action( 'wp_ajax_cegid_bulk_sync_stocks', [ __CLASS__, 'ajax_bulk_sync_stocks' ] );
		add_action( 'wp_ajax_cegid_clear_logs', [ __CLASS__, 'ajax_clear_logs' ] );
		add_action( 'wp_ajax_cegid_test_connection', [ __CLASS__, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_cegid_unlink_product', [ __CLASS__, 'ajax_unlink_product' ] );
	}

	/**
	 * Processa o disparo AJAX para emitir fatura de um pedido WooCommerce.
	 */
	public static function ajax_generate_invoice() {
		// Valida segurança
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		// Valida se a licença está ativa
		if ( ! Cegid_Settings::is_license_active() ) {
			wp_send_json_error( [ 'message' => __( 'Acesso bloqueado: licença inválida ou inativa.', 'wc-cegid-sync' ) ] );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		if ( ! $order_id ) {
			wp_send_json_error( [ 'message' => __( 'ID de pedido inválido.', 'wc-cegid-sync' ) ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Pedido não encontrado no WooCommerce.', 'wc-cegid-sync' ) ] );
		}

		// Evita emissão duplicada
		$existing_id = $order->get_meta( '_cegid_invoice_id', true );
		if ( ! empty( $existing_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Este pedido já possui uma fatura gerada no CEGID.', 'wc-cegid-sync' ) ] );
		}

		// Mapeia e envia o payload
		$payload        = Cegid_Data_Mapper::map_order_to_cegid_invoice( $order );
		$doc_type_label = ! empty( $payload['document_type'] ) ? $payload['document_type'] : 'Documento';
		$order_total    = $order->get_total() . ' ' . $order->get_currency();
		$customer_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$customer_nif   = ! empty( $payload['customer_tax_id'] ) ? $payload['customer_tax_id'] : ( ! empty( $payload['data']['attributes']['customer_tax_id'] ) ? $payload['data']['attributes']['customer_tax_id'] : '999999990 (Consumidor Final)' );
		$series_code    = ! empty( $payload['series_code'] ) ? $payload['series_code'] : ( ! empty( $payload['data']['attributes']['series_code'] ) ? $payload['data']['attributes']['series_code'] : 'Padrão' );
		$items_count    = count( $order->get_items() );

		Cegid_API_Client::log(
			sprintf(
				/* translators: 1: Tipo de documento, 2: ID do Pedido, 3: Total, 4: NIF, 5: Série, 6: Quantidade de itens */
				__( 'Iniciando emissão de %1$s para o Pedido #%2$d. Valor: %3$s | NIF: %4$s | Série: %5$s | %6$d itens.', 'wc-cegid-sync' ),
				$doc_type_label,
				$order_id,
				$order_total,
				$customer_nif,
				$series_code,
				$items_count
			),
			'info',
			[
				'order_id'        => $order_id,
				'order_number'    => $order->get_order_number(),
				'document_type'   => $doc_type_label,
				'total'           => $order_total,
				'customer_name'   => $customer_name ?: 'Não informado',
				'customer_tax_id' => $customer_nif,
				'series'          => $series_code,
				'items_count'     => $items_count,
				'payload_sent'    => $payload,
			]
		);

		$response = Cegid_API_Client::create_invoice( $payload );

		$invoice_id = ! empty( $response['id'] ) ? $response['id'] : ( ! empty( $response['data']['id'] ) ? $response['data']['id'] : null );

		if ( ! $response || empty( $invoice_id ) ) {
			$last_error    = Cegid_API_Client::get_last_error();
			$error_message = ! empty( $last_error ) ? $last_error : __( 'Falha ao emitir documento de venda na API da CEGID. Inspecione os logs de auditoria.', 'wc-cegid-sync' );

			Cegid_API_Client::log(
				sprintf(
					/* translators: 1: Tipo de documento, 2: ID do pedido, 3: Detalhe do erro */
					__( 'Falha na emissão de %1$s para o Pedido #%2$d. Motivo: %3$s', 'wc-cegid-sync' ),
					$doc_type_label,
					$order_id,
					$error_message
				),
				'error',
				[
					'order_id'       => $order_id,
					'document_type'  => $doc_type_label,
					'error_details'  => $error_message,
					'payload_sent'   => $payload,
					'cegid_response' => $response,
				]
			);

			wp_send_json_error( [ 'message' => $error_message ] );
		}

		// Salva informações da fatura gerada no pedido
		$order->update_meta_data( '_cegid_invoice_id', $invoice_id );

		if ( ! empty( $response['url'] ) ) {
			$order->update_meta_data( '_cegid_invoice_url', esc_url_raw( $response['url'] ) );
		}

		// Adiciona nota informativa ao histórico do pedido
		$doc_type = isset( $response['document_type'] ) ? $response['document_type'] : $payload['document_type'];
		$doc_num  = isset( $response['document_number'] ) ? $response['document_number'] : ( ! empty( $response['attributes']['document_no'] ) ? $response['attributes']['document_no'] : '' );
		$is_final = ( $payload['finalize'] === true );

		$order->add_order_note(
			sprintf(
				/* translators: 1: Tipo do documento, 2: Número do documento, 3: ID interno, 4: Status */
				__( 'Fatura gerada no CEGID: %1$s %2$s (ID: %3$s - Status: %4$s).', 'wc-cegid-sync' ),
				$doc_type,
				$doc_num,
				$invoice_id,
				$is_final ? __( 'Finalizado', 'wc-cegid-sync' ) : __( 'Rascunho', 'wc-cegid-sync' )
			)
		);
		$order->save();

		Cegid_API_Client::log(
			sprintf(
				/* translators: 1: Tipo, 2: Pedido ID, 3: Número, 4: ID Documento, 5: Status */
				__( 'Documento %1$s emitido com SUCESSO na CEGID para o Pedido #%2$d! Nº: %3$s (ID CEGID: %4$s | Status: %5$s).', 'wc-cegid-sync' ),
				$doc_type,
				$order_id,
				$doc_num ?: '(aguardando numeração)',
				$invoice_id,
				$is_final ? __( 'Finalizado', 'wc-cegid-sync' ) : __( 'Rascunho', 'wc-cegid-sync' )
			),
			'success',
			[
				'order_id'        => $order_id,
				'invoice_id'      => $invoice_id,
				'document_number' => $doc_num,
				'document_type'   => $doc_type,
				'status'          => $is_final ? 'Finalizado' : 'Rascunho',
				'document_url'    => ! empty( $response['url'] ) ? $response['url'] : null,
				'cegid_response'  => $response,
			]
		);

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: 1: Tipo do documento, 2: Status */
				__( 'Documento %1$s gerado com sucesso na CEGID (%2$s)!', 'wc-cegid-sync' ),
				$doc_type,
				$is_final ? __( 'Finalizado', 'wc-cegid-sync' ) : __( 'Rascunho', 'wc-cegid-sync' )
			)
		] );
	}

	/**
	 * Processa o disparo AJAX para fazer o PULL de estoque de um produto individual.
	 */
	public static function ajax_sync_stock_manual() {
		// Valida segurança
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		// Valida se a licença está ativa
		if ( ! Cegid_Settings::is_license_active() ) {
			wp_send_json_error( [ 'message' => __( 'Acesso bloqueado: licença inválida ou inativa.', 'wc-cegid-sync' ) ] );
		}

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'ID de produto inválido.', 'wc-cegid-sync' ) ] );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Produto não encontrado no WooCommerce.', 'wc-cegid-sync' ) ] );
		}

		$sku = $product->get_sku();
		if ( empty( $sku ) ) {
			wp_send_json_error( [ 'message' => __( 'Este produto não possui SKU cadastrado.', 'wc-cegid-sync' ) ] );
		}

		$is_service = Cegid_Data_Mapper::is_service( $product );

		$cegid_product_id = $product->get_meta( '_cegid_product_id', true );
		$cegid_product    = null;

		// 1. Tenta buscar direto pelo ID do CEGID se o produto já estiver vinculado
		if ( ! empty( $cegid_product_id ) ) {
			$cegid_product = Cegid_API_Client::get_product( $cegid_product_id, $is_service );
		}

		// 2. Se não encontrou por ID, busca por SKU na CEGID
		if ( ! $cegid_product ) {
			$cegid_product = Cegid_API_Client::get_product_by_sku( $sku, $is_service );
			if ( $cegid_product && ! empty( $cegid_product['id'] ) ) {
				$cegid_product_id = $cegid_product['id'];
				$product->update_meta_data( '_cegid_product_id', $cegid_product_id );
			}
		}

		if ( ! $cegid_product ) {
			$err_msg = sprintf(
				/* translators: %s: SKU do produto */
				__( 'Produto SKU %s não foi localizado no cadastro da CEGID.', 'wc-cegid-sync' ),
				$sku
			);
			$product->update_meta_data( '_cegid_sync_error', $err_msg );
			$product->save();

			Cegid_API_Client::log(
				sprintf( __( 'Falha ao sincronizar estoque: %s', 'wc-cegid-sync' ), $err_msg ),
				'error',
				[ 'product_id' => $product_id, 'sku' => $sku, 'is_service' => $is_service ]
			);

			wp_send_json_error( [ 'message' => $err_msg ] );
		}

		$now_time       = time();
		$formatted_time = date_i18n( get_option( 'date_format' ) . ' H:i', $now_time );
		$old_qty        = $product->get_stock_quantity();

		// Cenário A: O item é um Serviço / Assinatura (não possui controle físico de estoque)
		if ( $is_service ) {
			$product->update_meta_data( '_cegid_stock_qty', 'service' );
			$product->update_meta_data( '_cegid_last_sync', $now_time );
			$product->delete_meta_data( '_cegid_sync_error' );
			$product->save();

			Cegid_API_Client::log(
				sprintf(
					/* translators: %s: SKU */
					__( 'Serviço/Assinatura SKU %s verificado e sincronizado com a CEGID (artigos de serviço não possuem controle de inventário físico).', 'wc-cegid-sync' ),
					$sku
				),
				'success',
				[
					'product_id' => $product_id,
					'sku'        => $sku,
					'type'       => 'service',
				]
			);

			wp_send_json_success( [
				'message'     => sprintf( __( 'Serviço/Assinatura SKU %s sincronizado com sucesso na CEGID.', 'wc-cegid-sync' ), $sku ),
				'qty'         => 'Serviço',
				'cegid_stock' => 'Serviço',
				'is_service'  => true,
				'last_sync'   => $formatted_time,
			] );
		}

		// Cenário B: Produto Físico - Extrai a quantidade de estoque de forma resiliente
		$cegid_qty = Cegid_API_Client::extract_stock_quantity( $cegid_product );

		// Se a CEGID não expõe saldo na API de artigos, NUNCA zerar o WooCommerce local!
		if ( $cegid_qty === null ) {
			$product->update_meta_data( '_cegid_stock_qty', 'na' );
			$product->update_meta_data( '_cegid_last_sync', $now_time );
			$product->delete_meta_data( '_cegid_sync_error' );
			$product->save();

			Cegid_API_Client::log(
				sprintf(
					/* translators: 1: SKU, 2: Qtd WooCommerce */
					__( 'Artigo físico SKU %1$s verificado na CEGID. Quantidade em armazém não disponibilizada pela API pública de artigos do CEGID; o estoque local do WooCommerce (%2$s unidades) foi preservado.', 'wc-cegid-sync' ),
					$sku,
					$old_qty !== null ? $old_qty : '0'
				),
				'info',
				[
					'product_id'    => $product_id,
					'sku'           => $sku,
					'current_stock' => $old_qty,
					'notice'        => 'Stock not exposed by CEGID products API; WC stock kept intact.',
				]
			);

			wp_send_json_success( [
				'message'     => sprintf(
					/* translators: 1: SKU, 2: Quantidade */
					__( 'Artigo SKU %1$s sincronizado com a CEGID! A quantidade local do WooCommerce (%2$s un.) foi mantida com segurança.', 'wc-cegid-sync' ),
					$sku,
					$old_qty !== null ? $old_qty : '0'
				),
				'qty'         => $old_qty !== null ? $old_qty : 0,
				'cegid_stock' => 'na',
				'is_service'  => false,
				'last_sync'   => $formatted_time,
			] );
		}

		// Cenário C: A CEGID retornou quantidade numérica válida de estoque
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $cegid_qty );
		$product->set_stock_status( $cegid_qty > 0 ? 'instock' : 'outofstock' );

		// Atualiza metadados após sincronização bem-sucedida
		$product->update_meta_data( '_cegid_stock_qty', $cegid_qty );
		$product->update_meta_data( '_cegid_last_sync', $now_time );
		$product->delete_meta_data( '_cegid_sync_error' );
		$product->save();

		// Sincroniza cache interno de estoque do WooCommerce
		wc_update_product_stock( $product, $cegid_qty, 'set' );

		Cegid_API_Client::log(
			sprintf(
				/* translators: 1: SKU, 2: Qtd Nova, 3: Qtd Antiga */
				__( 'Estoque do SKU %1$s sincronizado com sucesso da CEGID! Saldo atualizado para %2$s unidades (anterior: %3$s).', 'wc-cegid-sync' ),
				$sku,
				$cegid_qty,
				$old_qty !== null ? $old_qty : 'n/a'
			),
			'success',
			[
				'product_id' => $product_id,
				'sku'        => $sku,
				'old_stock'  => $old_qty,
				'new_stock'  => $cegid_qty,
			]
		);

		wp_send_json_success( [
			'message'     => sprintf( __( 'Estoque do SKU %s sincronizado com sucesso da CEGID: %s unidades.', 'wc-cegid-sync' ), $sku, $cegid_qty ),
			'qty'         => $cegid_qty,
			'cegid_stock' => $cegid_qty,
			'is_service'  => false,
			'last_sync'   => $formatted_time,
		] );
	}

	/**
	 * Processa o disparo AJAX para fazer o PULL de estoque global (todos os produtos).
	 */
	public static function ajax_sync_all_stocks() {
		// Valida segurança
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		// Valida se a licença está ativa
		if ( ! Cegid_Settings::is_license_active() ) {
			wp_send_json_error( [ 'message' => __( 'Acesso bloqueado: licença inválida ou inativa.', 'wc-cegid-sync' ) ] );
		}

		$summary = Cegid_Stock_Sync::execute_global_pull_sync();

		if ( $summary['success'] ) {
			wp_send_json_success( 
				[ 
					'message' => sprintf(
						__( 'Sincronização global concluída! Total de produtos analisados: %1$d. Atualizados: %2$d. Erros/Alertas: %3$d.', 'wc-cegid-sync' ),
						$summary['total'],
						$summary['updated'],
						$summary['errors']
					),
					'summary' => $summary,
				] 
			);
		} else {
			wp_send_json_error( [ 'message' => __( 'Falha ao executar a sincronização global de estoques.', 'wc-cegid-sync' ) ] );
		}
	}

	/**
	 * Processa a ativação remota de licença via chamada AJAX.
	 */
	public static function ajax_activate_license() {
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( trim( $_POST['license_key'] ) ) : '';
		if ( empty( $license_key ) ) {
			wp_send_json_error( [ 'message' => __( 'Por favor, insira uma chave de licença válida.', 'wc-cegid-sync' ) ] );
		}

		// Determina o endereço do servidor de licenças. Fallback local: https://license.rafaelpitaoficial.com.br
		$server_url = defined( 'WC_CEGID_LICENSE_SERVER_URL' ) ? WC_CEGID_LICENSE_SERVER_URL : 'https://license.rafaelpitaoficial.com.br';

		$domain = ! empty( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );

		$response = wp_remote_post(
			$server_url . '/api/license/activate',
			[
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( [
					'key'    => $license_key,
					'domain' => $domain,
				] ),
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => __( 'Erro ao conectar ao servidor de licenças. Verifique sua conexão e tente novamente.', 'wc-cegid-sync' ) ] );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code === 200 && isset( $body['success'] ) && $body['success'] ) {
			$options = Cegid_Settings::get_options();
			$options['license_key']     = $license_key;
			$options['license_status']  = 'active';
			$options['license_expires'] = isset( $body['expiresAt'] ) ? $body['expiresAt'] : '';
			update_option( 'wc_cegid_sync_settings', $options );

			wp_send_json_success( [ 'message' => __( 'Chave de licença validada e ativada com sucesso!', 'wc-cegid-sync' ) ] );
		} else {
			$options = Cegid_Settings::get_options();
			$options['license_key'] = $license_key;
			
			$status = 'inactive';
			if ( isset( $body['status'] ) ) {
				$status = $body['status'];
			}
			
			$msg = __( 'Chave de licença inválida ou inexistente.', 'wc-cegid-sync' );
			if ( isset( $body['message'] ) ) {
				$msg = $body['message'];
			}

			$options['license_status'] = $status;
			update_option( 'wc_cegid_sync_settings', $options );

			wp_send_json_error( [ 'message' => $msg ] );
		}
	}

	/**
	 * Processa a desativação da licença local e notifica o servidor central para liberar o domínio.
	 */
	public static function ajax_deactivate_license() {
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		$options = Cegid_Settings::get_options();
		$license_key = isset( $options['license_key'] ) ? $options['license_key'] : '';

		// Notifica o servidor central de licenças para liberar o vínculo do domínio
		if ( ! empty( $license_key ) ) {
			$server_url = defined( 'WC_CEGID_LICENSE_SERVER_URL' ) ? WC_CEGID_LICENSE_SERVER_URL : 'https://license.rafaelpitaoficial.com.br';
			$domain = ! empty( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );

			wp_remote_post(
				$server_url . '/api/license/deactivate',
				[
					'headers' => [ 'Content-Type' => 'application/json' ],
					'body'    => wp_json_encode( [
						'key'    => $license_key,
						'domain' => $domain,
					] ),
					'timeout' => 10,
				]
			);
		}

		$options['license_key']     = '';
		$options['license_status']  = 'inactive';
		$options['license_expires'] = '';
		update_option( 'wc_cegid_sync_settings', $options );

		wp_send_json_success( [ 'message' => __( 'Licença desativada com sucesso para este domínio.', 'wc-cegid-sync' ) ] );
	}

	/**
	 * Processa o disparo AJAX para mover um pedido para a lixeira do WooCommerce.
	 */
	public static function ajax_trash_order() {
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		if ( ! $order_id ) {
			wp_send_json_error( [ 'message' => __( 'ID de pedido inválido.', 'wc-cegid-sync' ) ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Pedido não encontrado no WooCommerce.', 'wc-cegid-sync' ) ] );
		}

		// Move o pedido para a lixeira (sem excluí-lo permanentemente do banco na hora)
		$result = $order->delete( false );

		if ( $result ) {
			wp_send_json_success( [ 'message' => __( 'Pedido suprimido e movido para a lixeira com sucesso!', 'wc-cegid-sync' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Falha ao mover o pedido para a lixeira.', 'wc-cegid-sync' ) ] );
		}
	}

	/**
	 * Cadastra um produto imediatamente na CEGID via API v0.
	 */
	public static function ajax_create_product() {
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		if ( ! Cegid_Settings::is_license_active() ) {
			wp_send_json_error( [ 'message' => __( 'Acesso bloqueado: licença inválida ou inativa.', 'wc-cegid-sync' ) ] );
		}

		$options = Cegid_Settings::get_options();

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'ID de produto inválido.', 'wc-cegid-sync' ) ] );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Produto não encontrado no WooCommerce.', 'wc-cegid-sync' ) ] );
		}

		$sku = $product->get_sku();
		if ( empty( $sku ) ) {
			wp_send_json_error( [ 'message' => __( 'Este produto não possui SKU cadastrado.', 'wc-cegid-sync' ) ] );
		}

		$name  = $product->get_name();
		$price = $product->get_regular_price() ?: $product->get_price();
		$is_service = Cegid_Data_Mapper::is_service( $product );

		// ETAPA 1: Verifica se o artigo já existe previamente na CEGID (cadastro manual prévio ou serviço)
		$existing_cegid = Cegid_API_Client::get_product_by_sku( $sku, $is_service );
		if ( $existing_cegid && ! empty( $existing_cegid['id'] ) ) {
			$cegid_id = $existing_cegid['id'];
			$product->update_meta_data( '_cegid_product_id', $cegid_id );

			if ( $is_service ) {
				$product->update_meta_data( '_cegid_stock_qty', 'service' );
			} else {
				$qty = Cegid_API_Client::extract_stock_quantity( $existing_cegid );
				$product->update_meta_data( '_cegid_stock_qty', $qty !== null ? $qty : 'na' );
			}
			$product->save();

			Cegid_API_Client::log(
				sprintf(
					/* translators: 1: SKU, 2: Nome, 3: ID CEGID */
					__( 'Artigo SKU: %1$s ("%2$s") já existia na CEGID. Vínculo associado com sucesso! (ID: %3$s).', 'wc-cegid-sync' ),
					$sku,
					$name,
					$cegid_id
				),
				'success',
				[
					'product_id'     => $product_id,
					'sku'            => $sku,
					'cegid_id'       => $cegid_id,
					'is_service'     => $is_service,
					'already_exists' => true,
				]
			);

			wp_send_json_success( [
				'message'   => __( 'Artigo já existia no CEGID! Vínculo associado com sucesso. Agora você pode sincronizar o estoque.', 'wc-cegid-sync' ),
				'cegid_id'  => $cegid_id,
			] );
		}

		// ETAPA 2: Se não existia, envia o payload de cadastro para a CEGID v0
		$payload = Cegid_Data_Mapper::map_product_to_cegid( $product );

		Cegid_API_Client::log(
			sprintf(
				/* translators: 1: Tipo, 2: SKU, 3: Nome, 4: Preço */
				__( 'Iniciando cadastro do novo %1$s SKU: %2$s ("%3$s") na CEGID. Preço: %4$s €.', 'wc-cegid-sync' ),
				$is_service ? 'Serviço' : 'Artigo',
				$sku,
				$name,
				$price ?: '0.00'
			),
			'info',
			[
				'product_id'   => $product_id,
				'sku'          => $sku,
				'product_name' => $name,
				'price'        => $price,
				'is_service'   => $is_service,
				'payload_sent' => $payload,
			]
		);

		$response = Cegid_API_Client::create_product( $payload, $is_service );

		if ( ! $response || ( empty( $response['id'] ) && empty( $response['data']['id'] ) ) ) {
			$last_error = Cegid_API_Client::get_last_error();

			// ETAPA 3: Fallback de detecção - se o erro foi por duplicidade ou se o produto passou a existir
			$fallback_existing = Cegid_API_Client::get_product_by_sku( $sku, $is_service );
			if ( $fallback_existing && ! empty( $fallback_existing['id'] ) ) {
				$cegid_id = $fallback_existing['id'];
				$product->update_meta_data( '_cegid_product_id', $cegid_id );

				if ( $is_service ) {
					$product->update_meta_data( '_cegid_stock_qty', 'service' );
				} else {
					$qty = Cegid_API_Client::extract_stock_quantity( $fallback_existing );
					$product->update_meta_data( '_cegid_stock_qty', $qty !== null ? $qty : 'na' );
				}
				$product->save();

				Cegid_API_Client::log(
					sprintf(
						/* translators: 1: SKU, 2: ID CEGID */
						__( 'Artigo SKU: %1$s detectado como existente na CEGID após tentativa de envio. Vínculo associado com sucesso (ID: %2$s).', 'wc-cegid-sync' ),
						$sku,
						$cegid_id
					),
					'success',
					[
						'product_id' => $product_id,
						'sku'        => $sku,
						'cegid_id'   => $cegid_id,
						'is_service' => $is_service,
					]
				);

				wp_send_json_success( [
					'message'   => __( 'Artigo já existia no CEGID! Vínculo associado com sucesso. Agora você pode sincronizar o estoque.', 'wc-cegid-sync' ),
					'cegid_id'  => $cegid_id,
				] );
			}

			$err_msg = ! empty( $last_error ) ? $last_error : __( 'Falha ao cadastrar artigo na CEGID. Verifique se o SKU já existe ou revise as credenciais.', 'wc-cegid-sync' );

			Cegid_API_Client::log(
				sprintf(
					/* translators: 1: SKU, 2: Nome, 3: Erro */
					__( 'Falha ao cadastrar artigo SKU: %1$s ("%2$s") na CEGID. Motivo: %3$s', 'wc-cegid-sync' ),
					$sku,
					$name,
					$err_msg
				),
				'error',
				[
					'product_id'     => $product_id,
					'sku'            => $sku,
					'error_details'  => $err_msg,
					'payload_sent'   => $payload,
					'cegid_response' => $response,
				]
			);

			wp_send_json_error( [ 'message' => $err_msg ] );
		}

		$cegid_id = ! empty( $response['id'] ) ? $response['id'] : $response['data']['id'];

		// Vincula o ID localmente no metadado
		$product->update_meta_data( '_cegid_product_id', $cegid_id );
		$product->save();

		Cegid_API_Client::log(
			sprintf(
				/* translators: 1: SKU, 2: Nome, 3: ID CEGID */
				__( 'Artigo SKU: %1$s ("%2$s") cadastrado com SUCESSO na CEGID! (ID Gerado: %3$s).', 'wc-cegid-sync' ),
				$sku,
				$name,
				$cegid_id
			),
			'success',
			[
				'product_id'     => $product_id,
				'sku'            => $sku,
				'product_name'   => $name,
				'cegid_id'       => $cegid_id,
				'cegid_response' => $response,
			]
		);

		wp_send_json_success( [
			'message'   => __( 'Artigo cadastrado com sucesso na CEGID!', 'wc-cegid-sync' ),
			'cegid_id'  => $cegid_id,
		] );
	}

	/**
	 * Varre produtos locais pendentes e verifica se já estão cadastrados na CEGID.
	 */
	public static function ajax_verify_product_links() {
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		if ( ! Cegid_Settings::is_license_active() ) {
			wp_send_json_error( [ 'message' => __( 'Acesso bloqueado: licença inválida ou inativa.', 'wc-cegid-sync' ) ] );
		}

		// Busca produtos publicados para checagem de SKUs pendentes
		$args = [
			'status' => 'publish',
			'limit'  => -1,
			'type'   => [ 'simple', 'variation', 'subscription', 'variable-subscription' ],
		];

		$all_products = wc_get_products( $args );
		$pending_products = [];

		foreach ( $all_products as $p ) {
			$existing_id = $p->get_meta( '_cegid_product_id', true );
			if ( empty( $existing_id ) && ! empty( $p->get_sku() ) ) {
				$pending_products[] = $p;
			}
		}

		if ( empty( $pending_products ) ) {
			wp_send_json_success( [ 'message' => __( 'Todos os produtos já possuem vínculo configurado com o CEGID.', 'wc-cegid-sync' ) ] );
		}

		$linked_count    = 0;
		$processed_count = count( $pending_products );

		Cegid_API_Client::log(
			sprintf(
				/* translators: %d: Total de produtos pendentes */
				__( 'Iniciando varredura inteligente de vínculos no CEGID para %d artigos pendentes...', 'wc-cegid-sync' ),
				$processed_count
			),
			'info'
		);

		foreach ( $pending_products as $product ) {
			$sku = $product->get_sku();
			if ( empty( $sku ) ) {
				continue;
			}

			$is_service = Cegid_Data_Mapper::is_service( $product );

			// Consulta a API da CEGID por SKU (com suporte a services e products)
			$cegid_item = Cegid_API_Client::get_product_by_sku( $sku, $is_service );

			if ( $cegid_item && ! empty( $cegid_item['id'] ) ) {
				$product->update_meta_data( '_cegid_product_id', $cegid_item['id'] );

				if ( $is_service ) {
					$product->update_meta_data( '_cegid_stock_qty', 'service' );
				} else {
					$qty = Cegid_API_Client::extract_stock_quantity( $cegid_item );
					$product->update_meta_data( '_cegid_stock_qty', $qty !== null ? $qty : 'na' );
				}

				$product->save();
				$linked_count++;

				Cegid_API_Client::log(
					sprintf(
						/* translators: 1: SKU, 2: ID CEGID */
						__( 'Vínculo localizado: Artigo SKU %1$s associado ao ID CEGID %2$s com sucesso.', 'wc-cegid-sync' ),
						$sku,
						$cegid_item['id']
					),
					'success'
				);
			}
		}

		$result_msg = sprintf(
			/* translators: 1: Quantidade vinculada, 2: Quantidade processada */
			__( 'Verificação concluída! %1$d produtos associados com sucesso ao CEGID (de %2$d verificados).', 'wc-cegid-sync' ),
			$linked_count,
			$processed_count
		);

		Cegid_API_Client::log(
			$result_msg,
			$linked_count > 0 ? 'success' : 'info',
			[
				'total_checked' => $processed_count,
				'total_linked'  => $linked_count,
			]
		);

		wp_send_json_success( [
			'message' => $result_msg,
		] );
	}

	/**
	 * Move vários pedidos para a lixeira do WooCommerce em lote.
	 */
	public static function ajax_bulk_trash_orders() {
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		$order_ids = isset( $_POST['order_ids'] ) ? array_map( 'intval', (array) $_POST['order_ids'] ) : [];
		if ( empty( $order_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Nenhum pedido selecionado.', 'wc-cegid-sync' ) ] );
		}

		$success_count = 0;
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$result = $order->delete( false );
				if ( $result ) {
					$success_count++;
				}
			}
		}

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: %d: quantidade de pedidos */
				__( 'Ação em lote concluída! %d pedidos movidos para a lixeira com sucesso.', 'wc-cegid-sync' ),
				$success_count
			)
		] );
	}

	/**
	 * Cadastra vários produtos em lote na CEGID via API v0.
	 */
	public static function ajax_bulk_create_products() {
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		if ( ! Cegid_Settings::is_license_active() ) {
			wp_send_json_error( [ 'message' => __( 'Acesso bloqueado: licença inválida ou inativa.', 'wc-cegid-sync' ) ] );
		}

		$options = Cegid_Settings::get_options();

		$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'intval', (array) $_POST['product_ids'] ) : [];
		if ( empty( $product_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Nenhum produto selecionado.', 'wc-cegid-sync' ) ] );
		}

		$total_requested = count( $product_ids );
		Cegid_API_Client::log(
			sprintf(
				/* translators: %d: quantidade de produtos */
				__( 'Iniciando operação de cadastro/vínculo em lote de %d artigos selecionados...', 'wc-cegid-sync' ),
				$total_requested
			),
			'info',
			[ 'selected_product_ids' => $product_ids ]
		);

		$success_count = 0;
		$failed_items  = [];

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$sku = $product->get_sku();
			if ( empty( $sku ) ) {
				$failed_items[] = [ 'id' => $product_id, 'sku' => '(vazio)', 'reason' => 'Produto sem SKU' ];
				continue;
			}

			// Pula os que já possuem ID cadastrado na CEGID
			$cegid_product_id = $product->get_meta( '_cegid_product_id', true );
			if ( ! empty( $cegid_product_id ) ) {
				continue;
			}

			$is_service = Cegid_Data_Mapper::is_service( $product );

			// ETAPA 1: Verifica se já existia previamente no CEGID (produto ou serviço)
			$existing_cegid = Cegid_API_Client::get_product_by_sku( $sku, $is_service );
			if ( $existing_cegid && ! empty( $existing_cegid['id'] ) ) {
				$cegid_id = $existing_cegid['id'];
				$product->update_meta_data( '_cegid_product_id', $cegid_id );
				if ( $is_service ) {
					$product->update_meta_data( '_cegid_stock_qty', 'service' );
				} else {
					$qty = Cegid_API_Client::extract_stock_quantity( $existing_cegid );
					$product->update_meta_data( '_cegid_stock_qty', $qty !== null ? $qty : 'na' );
				}
				$product->save();
				$success_count++;

				Cegid_API_Client::log(
					sprintf(
						/* translators: 1: SKU, 2: ID CEGID */
						__( '[Lote] Artigo SKU: %1$s já existia previamente no CEGID. Vínculo associado com sucesso (ID: %2$s).', 'wc-cegid-sync' ),
						$sku,
						$cegid_id
					),
					'success'
				);
				continue;
			}

			// ETAPA 2: Prepara o payload oficial para a CEGID v0 e envia
			$payload  = Cegid_Data_Mapper::map_product_to_cegid( $product );
			$response = Cegid_API_Client::create_product( $payload, $is_service );

			if ( $response && ( ! empty( $response['id'] ) || ! empty( $response['data']['id'] ) ) ) {
				$cegid_id = ! empty( $response['id'] ) ? $response['id'] : $response['data']['id'];
				$product->update_meta_data( '_cegid_product_id', $cegid_id );
				if ( $is_service ) {
					$product->update_meta_data( '_cegid_stock_qty', 'service' );
				}
				$product->save();
				$success_count++;

				Cegid_API_Client::log(
					sprintf(
						/* translators: 1: SKU, 2: ID CEGID */
						__( '[Lote] Artigo SKU: %1$s cadastrado com sucesso (ID: %2$s).', 'wc-cegid-sync' ),
						$sku,
						$cegid_id
					),
					'success'
				);
			} else {
				// ETAPA 3: Fallback de detecção
				$fallback = Cegid_API_Client::get_product_by_sku( $sku, $is_service );
				if ( $fallback && ! empty( $fallback['id'] ) ) {
					$cegid_id = $fallback['id'];
					$product->update_meta_data( '_cegid_product_id', $cegid_id );
					if ( $is_service ) {
						$product->update_meta_data( '_cegid_stock_qty', 'service' );
					} else {
						$qty = Cegid_API_Client::extract_stock_quantity( $fallback );
						$product->update_meta_data( '_cegid_stock_qty', $qty !== null ? $qty : 'na' );
					}
					$product->save();
					$success_count++;

					Cegid_API_Client::log(
						sprintf(
							/* translators: 1: SKU, 2: ID CEGID */
							__( '[Lote] Artigo SKU: %1$s detectado no CEGID após tentativa. Vínculo associado (ID: %2$s).', 'wc-cegid-sync' ),
							$sku,
							$cegid_id
						),
						'success'
					);
					continue;
				}

				$last_error     = Cegid_API_Client::get_last_error();
				$failed_items[] = [
					'id'     => $product_id,
					'sku'    => $sku,
					'reason' => $last_error ?: 'Rejeição na API da CEGID',
				];

				Cegid_API_Client::log(
					sprintf(
						/* translators: 1: SKU, 2: Erro */
						__( '[Lote] Falha ao cadastrar/vincular artigo SKU: %1$s: %2$s', 'wc-cegid-sync' ),
						$sku,
						$last_error ?: 'Erro desconhecido'
					),
					'warning',
					[
						'product_id' => $product_id,
						'sku'        => $sku,
						'payload'    => $payload,
					]
				);
			}
		}

		$summary_msg = sprintf(
			/* translators: 1: Cadastrados com sucesso, 2: Total */
			__( 'Operação em lote finalizada: %1$d de %2$d artigos processados/vinculados com sucesso.', 'wc-cegid-sync' ),
			$success_count,
			$total_requested
		);

		Cegid_API_Client::log(
			$summary_msg,
			$success_count > 0 ? 'info' : 'warning',
			[
				'success_count' => $success_count,
				'total'         => $total_requested,
				'failures'      => $failed_items,
			]
		);

		wp_send_json_success( [
			'message' => $summary_msg,
		] );
	}

	/**
	 * Puxa estoque da CEGID para vários produtos selecionados de uma vez só.
	 */
	public static function ajax_bulk_sync_stocks() {
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		if ( ! Cegid_Settings::is_license_active() ) {
			wp_send_json_error( [ 'message' => __( 'Acesso bloqueado: licença inválida ou inativa.', 'wc-cegid-sync' ) ] );
		}

		$options = Cegid_Settings::get_options();

		$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'intval', (array) $_POST['product_ids'] ) : [];
		if ( empty( $product_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Nenhum produto selecionado.', 'wc-cegid-sync' ) ] );
		}

		Cegid_API_Client::log(
			sprintf(
				/* translators: %d: quantidade de produtos */
				__( 'Iniciando atualização de estoque em lote para %d artigos...', 'wc-cegid-sync' ),
				count( $product_ids )
			),
			'info'
		);

		$success_count = 0;
		$updated_skus  = [];

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$sku = $product->get_sku();
			if ( empty( $sku ) ) {
				continue;
			}

			$is_service       = Cegid_Data_Mapper::is_service( $product );
			$cegid_product_id = $product->get_meta( '_cegid_product_id', true );
			$cegid_item       = null;

			// 1. Tenta obter produto diretamente pelo ID CEGID se já vinculado
			if ( ! empty( $cegid_product_id ) ) {
				$cegid_item = Cegid_API_Client::get_product( $cegid_product_id, $is_service );
			}

			// 2. Se não encontrou por ID, busca por SKU na CEGID
			if ( ! $cegid_item ) {
				$cegid_item = Cegid_API_Client::get_product_by_sku( $sku, $is_service );
				if ( $cegid_item && ! empty( $cegid_item['id'] ) ) {
					$cegid_product_id = $cegid_item['id'];
					$product->update_meta_data( '_cegid_product_id', $cegid_product_id );
				}
			}

			if ( $cegid_item ) {
				$now_time = time();
				$old_qty  = $product->get_stock_quantity();

				// Cenário A: Item de serviço/assinatura
				if ( $is_service ) {
					$product->update_meta_data( '_cegid_stock_qty', 'service' );
					$product->update_meta_data( '_cegid_last_sync', $now_time );
					$product->delete_meta_data( '_cegid_sync_error' );
					$product->save();

					$success_count++;
					$updated_skus[] = [
						'sku'     => $sku,
						'old_qty' => 'Serviço',
						'new_qty' => 'Serviço',
					];
					continue;
				}

				// Cenário B: Produto físico
				$new_qty = Cegid_API_Client::extract_stock_quantity( $cegid_item );

				if ( $new_qty === null ) {
					// Quantidade não exposta na API CEGID: preserva estoque local do WooCommerce
					$product->update_meta_data( '_cegid_stock_qty', 'na' );
					$product->update_meta_data( '_cegid_last_sync', $now_time );
					$product->delete_meta_data( '_cegid_sync_error' );
					$product->save();

					$success_count++;
					$updated_skus[] = [
						'sku'     => $sku,
						'old_qty' => $old_qty !== null ? $old_qty : 0,
						'new_qty' => 'Preservado (Não exposto na API)',
					];
					continue;
				}

				// Cenário C: Estoque retornado com sucesso pela CEGID
				$product->set_manage_stock( true );
				$product->set_stock_quantity( $new_qty );
				$product->set_stock_status( $new_qty > 0 ? 'instock' : 'outofstock' );

				$product->update_meta_data( '_cegid_stock_qty', $new_qty );
				$product->update_meta_data( '_cegid_last_sync', $now_time );
				$product->delete_meta_data( '_cegid_sync_error' );
				$product->save();

				wc_update_product_stock( $product, $new_qty, 'set' );

				$success_count++;
				$updated_skus[] = [
					'sku'     => $sku,
					'old_qty' => $old_qty,
					'new_qty' => $new_qty,
				];

				Cegid_API_Client::log(
					sprintf(
						/* translators: 1: SKU, 2: Quantidade anterior, 3: Nova quantidade */
						__( '[Estoque] SKU %1$s sincronizado: %2$s -> %3$s unidades.', 'wc-cegid-sync' ),
						$sku,
						$old_qty !== null ? $old_qty : 'n/a',
						$new_qty
					),
					'info'
				);
			}
		}

		$summary = sprintf(
			/* translators: 1: Quantidade atualizada, 2: Total */
			__( 'Sincronização em lote concluída! %1$d existências de estoque atualizadas (de %2$d solicitadas).', 'wc-cegid-sync' ),
			$success_count,
			count( $product_ids )
		);

		Cegid_API_Client::log(
			$summary,
			'success',
			[ 'updated' => $updated_skus ]
		);

		wp_send_json_success( [
			'message' => $summary,
		] );
	}

	/**
	 * Limpa todos os logs de auditoria do plugin.
	 */
	public static function ajax_clear_logs() {
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		Cegid_API_Client::clear_logs();
		Cegid_API_Client::log( __( 'Logs de auditoria foram limpos pelo administrador.', 'wc-cegid-sync' ), 'info' );

		wp_send_json_success( [ 'message' => __( 'Logs de auditoria limpos com sucesso!', 'wc-cegid-sync' ) ] );
	}

	/**
	 * Força o teste de conexão direta com a API da CEGID e retorna o status detalhado.
	 */
	public static function ajax_test_connection() {
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		// Limpa o transient em cache para forçar a comunicação real
		delete_transient( '_cegid_final_access_token' );

		$options   = Cegid_Settings::get_options();
		$env       = ! empty( $options['environment'] ) ? $options['environment'] : 'production';
		$client_id = ! empty( $options['client_id'] ) ? substr( $options['client_id'], 0, 6 ) . '...' : '(não configurado)';
		$username  = ! empty( $options['username'] ) ? $options['username'] : '(não configurado)';

		Cegid_API_Client::log(
			sprintf(
				/* translators: 1: Ambiente, 2: Client ID mascarado, 3: Usuário */
				__( 'Iniciando teste de comunicação OAuth2 com a API CEGID (Ambiente: %1$s | Client: %2$s | Usuário: %3$s)...', 'wc-cegid-sync' ),
				strtoupper( $env ),
				$client_id,
				$username
			),
			'info',
			[
				'environment' => $env,
				'client_id'   => $client_id,
				'username'    => $username,
				'timestamp'   => current_time( 'Y-m-d H:i:s' ),
			]
		);

		$token = Cegid_API_Client::get_access_token();

		if ( $token ) {
			$preview_token = substr( $token, 0, 10 ) . '...' . substr( $token, -6 );
			Cegid_API_Client::log(
				sprintf(
					/* translators: 1: Token preview */
					__( 'Teste de conexão concluído com SUCESSO! Token de acesso OAuth2 autenticado e validado (%s).', 'wc-cegid-sync' ),
					$preview_token
				),
				'success',
				[
					'token_preview' => $preview_token,
					'status'        => 'authenticated',
					'timestamp'     => current_time( 'Y-m-d H:i:s' ),
				]
			);

			wp_send_json_success( [
				'message' => __( 'Conexão estabelecida com sucesso! API da CEGID está 100% operacional e autenticada.', 'wc-cegid-sync' ),
				'status'  => 'connected',
			] );
		} else {
			$last_error = Cegid_API_Client::get_last_error();
			$err_msg    = ! empty( $last_error ) ? $last_error : __( 'Falha ao conectar com a API da CEGID. Verifique as credenciais no menu Configurações.', 'wc-cegid-sync' );

			Cegid_API_Client::log(
				sprintf(
					/* translators: 1: Detalhe do erro */
					__( 'Teste de conexão com a CEGID FALHOU: %s', 'wc-cegid-sync' ),
					$err_msg
				),
				'error',
				[
					'error_details' => $err_msg,
					'timestamp'     => current_time( 'Y-m-d H:i:s' ),
				]
			);

			wp_send_json_error( [
				'message' => $err_msg,
				'status'  => 'error',
			] );
		}
	}

	/**
	 * Remove o vínculo de um produto com a CEGID (limpa _cegid_product_id e _cegid_stock_qty).
	 * Permite que produtos cadastrados incorretamente sejam reconfigurados e recadastrados.
	 */
	public static function ajax_unlink_product() {
		check_ajax_referer( 'cegid_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão para esta ação.', 'wc-cegid-sync' ) ] );
		}

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'ID de produto inválido.', 'wc-cegid-sync' ) ] );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Produto não encontrado no WooCommerce.', 'wc-cegid-sync' ) ] );
		}

		$sku          = $product->get_sku();
		$old_cegid_id = $product->get_meta( '_cegid_product_id', true );

		$product->delete_meta_data( '_cegid_product_id' );
		$product->delete_meta_data( '_cegid_stock_qty' );
		$product->delete_meta_data( '_cegid_last_sync' );
		$product->delete_meta_data( '_cegid_sync_error' );
		$product->save();

		Cegid_API_Client::log(
			sprintf(
				/* translators: 1: SKU, 2: ID antigo */
				__( 'Vínculo do artigo SKU: %1$s com o CEGID (ID anterior: %2$s) foi removido no WooCommerce.', 'wc-cegid-sync' ),
				$sku,
				$old_cegid_id ?: 'N/A'
			),
			'info',
			[
				'product_id'   => $product_id,
				'sku'          => $sku,
				'old_cegid_id' => $old_cegid_id,
			]
		);

		wp_send_json_success( [
			'message' => __( 'Vínculo removido com sucesso! O produto agora pode ser recadastrado no CEGID.', 'wc-cegid-sync' ),
		] );
	}
}

