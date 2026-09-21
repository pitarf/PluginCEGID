<?php
/**
 * Cliente HTTP para integração com a API da CEGID (TOConline).
 *
 * @package WC_Cegid_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cegid_API_Client {

	/**
	 * Armazena a última mensagem de erro ocorrida para exibição na UI.
	 *
	 * @var string
	 */
	private static $last_error = '';

	/**
	 * Retorna a última mensagem de erro capturada.
	 *
	 * @return string
	 */
	public static function get_last_error() {
		return self::$last_error;
	}

	/**
	 * Registra mensagens no log do PHP, no WooCommerce e na tabela de auditoria local.
	 *
	 * @param string      $message Mensagem a ser registrada.
	 * @param string      $level   Nível do log (error, info, success, warning).
	 * @param array|null  $context Dados contextuais adicionais (payload, status, respostas da API).
	 */
	public static function log( $message, $level = 'error', $context = null ) {
		self::$last_error = $message;

		$log_entry_text = 'WC CEGID Sync [' . strtoupper( $level ) . ']: ' . $message;
		if ( ! empty( $context ) ) {
			$log_entry_text .= ' | Context: ' . wp_json_encode( $context );
		}
		error_log( $log_entry_text );

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger     = wc_get_logger();
			$wc_context = [ 'source' => 'wc-cegid-sync' ];
			if ( ! empty( $context ) ) {
				$wc_context['context'] = $context;
			}
			if ( $level === 'error' ) {
				$logger->error( $message, $wc_context );
			} elseif ( $level === 'warning' ) {
				$logger->warning( $message, $wc_context );
			} elseif ( $level === 'success' ) {
				$logger->info( '[SUCESSO] ' . $message, $wc_context );
			} else {
				$logger->info( $message, $wc_context );
			}
		}

		// Grava no buffer de auditoria persistente do plugin (máximo 250 registros)
		$logs = get_option( '_cegid_audit_logs', [] );
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}

		array_unshift(
			$logs,
			[
				'time'    => current_time( 'Y-m-d H:i:s' ),
				'level'   => $level,
				'message' => $message,
				'context' => ! empty( $context ) ? $context : null,
			]
		);

		if ( count( $logs ) > 250 ) {
			$logs = array_slice( $logs, 0, 250 );
		}

		update_option( '_cegid_audit_logs', $logs, false );
	}

	/**
	 * Retorna a lista de logs de auditoria gravados.
	 *
	 * @return array
	 */
	public static function get_logs() {
		$logs = get_option( '_cegid_audit_logs', [] );
		return is_array( $logs ) ? $logs : [];
	}

	/**
	 * Limpa todos os logs de auditoria.
	 *
	 * @return bool
	 */
	public static function clear_logs() {
		return update_option( '_cegid_audit_logs', [], false );
	}

	/**
	 * Retorna a URL base da API com base no ambiente (sandbox ou produção) ou valor personalizado.
	 *
	 * @return string URL base da API.
	 */
	private static function get_api_host() {
		$options = Cegid_Settings::get_options();

		if ( ! empty( $options['api_url'] ) ) {
			return untrailingslashit( $options['api_url'] );
		}

		if ( isset( $options['sandbox'] ) && $options['sandbox'] === 'yes' ) {
			return 'https://sandbox-api-cwb.cldware.com';
		}
		return 'https://api3.business-pt.cegid.cloud';
	}

	/**
	 * Retorna a URL base de autenticação (OAuth) com base no ambiente ou valor personalizado.
	 *
	 * @return string URL de autenticação base.
	 */
	private static function get_auth_host() {
		$options = Cegid_Settings::get_options();

		if ( ! empty( $options['auth_url'] ) ) {
			return untrailingslashit( $options['auth_url'] );
		}

		if ( isset( $options['sandbox'] ) && $options['sandbox'] === 'yes' ) {
			return 'https://sandbox-api-cwb.cldware.com/oauth';
		}
		return 'https://app3.business-pt.cegid.cloud/oauth';
	}

	/**
	 * Obtém o token de acesso final, utilizando cache em Transients.
	 * Implementa o fluxo de autenticação em duas etapas da CEGID.
	 *
	 * @return string|false Token de acesso Bearer ou false em caso de erro.
	 */
	public static function get_access_token() {
		// Tenta obter o token final já em cache
		$cached_token = get_transient( '_cegid_final_access_token' );
		if ( $cached_token ) {
			return $cached_token;
		}

		$options = Cegid_Settings::get_options();
		$host    = self::get_api_host();

		// Credenciais obrigatórias
		if ( empty( $options['client_id'] ) || empty( $options['client_secret'] ) || empty( $options['username'] ) || empty( $options['password'] ) ) {
			self::log( __( 'Credenciais incompletas: preencha Client ID, Client Secret, Usuário e Senha nas Configurações.', 'wc-cegid-sync' ) );
			return false;
		}

		// --- ETAPA 1: Obter Token de Acesso Inicial via OAuth2 ---
		$auth_host  = self::get_auth_host();
		$auth_url   = $auth_host . '/token';
		$basic_auth = base64_encode( $options['client_id'] . ':' . $options['client_secret'] );

		$response = wp_remote_post(
			$auth_url,
			[
				'headers' => [
					'Authorization' => 'Basic ' . $basic_auth,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				],
				'body'    => [
					'grant_type' => 'client_credentials',
					'username'   => $options['username'],
					'password'   => $options['password'],
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			$err_msg = $response->get_error_message();
			self::log(
				sprintf(
					/* translators: 1: Mensagem de erro HTTP */
					__( 'Falha na conexão HTTP com o servidor de autenticação OAuth2 da CEGID (/token): %s', 'wc-cegid-sync' ),
					$err_msg
				),
				'error',
				[
					'auth_url'  => $auth_url,
					'wp_error'  => $err_msg,
					'timestamp' => current_time( 'Y-m-d H:i:s' ),
				]
			);
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$body        = json_decode( $raw_body, true );

		if ( empty( $body['access_token'] ) ) {
			$err_detail = ! empty( $body['error_description'] ) ? $body['error_description'] : ( ! empty( $body['error'] ) ? $body['error'] : $raw_body );
			self::log(
				sprintf(
					/* translators: 1: Status HTTP, 2: Detalhe do erro */
					__( 'Falha na autenticação OAuth2 da CEGID (HTTP %1$d): %2$s. Verifique Client ID, Client Secret, Usuário e Senha.', 'wc-cegid-sync' ),
					$status_code,
					$err_detail
				),
				'error',
				[
					'auth_url'     => $auth_url,
					'status_code'  => $status_code,
					'oauth_error'  => $err_detail,
					'response_raw' => substr( (string) $raw_body, 0, 500 ),
				]
			);
			return false;
		}

		$token = $body['access_token'];
		$ttl   = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : ( isset( $body['access_ttl'] ) ? (int) $body['access_ttl'] : 86400 );

		// Salva o token no WP Transient com 1 minuto de margem de segurança
		set_transient( '_cegid_final_access_token', $token, max( 60, $ttl - 60 ) );

		return $token;
	}

	/**
	 * Executa uma requisição genérica autenticada na API da CEGID.
	 *
	 * @param string $endpoint Endpoint do recurso (ex: '/v1/commercial_sales_documents').
	 * @param string $method Método HTTP (GET, POST, PATCH, PUT, DELETE).
	 * @param array  $body Payload a ser enviado no corpo da requisição.
	 * @param bool   $use_json_api Se true, usa o cabeçalho content-type de JSON-API (usado na v0).
	 * @return array|false Resposta decodificada em Array ou false em caso de erro.
	 */
	public static function request( $endpoint, $method = 'GET', $body = [], $use_json_api = false ) {
		$token = self::get_access_token();
		if ( ! $token ) {
			self::log(
				sprintf(
					/* translators: 1: Método, 2: Endpoint */
					__( 'Operação cancelada: Não foi possível obter token de acesso para a requisição %1$s %2$s.', 'wc-cegid-sync' ),
					$method,
					$endpoint
				),
				'error'
			);
			return false;
		}

		$host = self::get_api_host();
		$url  = $host . '/' . ltrim( $endpoint, '/' );

		$content_type = $use_json_api ? 'application/vnd.api+json' : 'application/json';
		$accept       = 'application/json, application/vnd.api+json';

		$args = [
			'method'  => $method,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => $content_type,
				'Accept'        => $accept,
			],
			'timeout' => 25,
		];

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			self::log(
				sprintf(
					/* translators: 1: Método HTTP, 2: Endpoint, 3: Mensagem de erro */
					__( 'Erro de transporte HTTP ao chamar %1$s %2$s: %3$s', 'wc-cegid-sync' ),
					$method,
					$endpoint,
					$error_message
				),
				'error',
				[
					'url'             => $url,
					'method'          => $method,
					'endpoint'        => $endpoint,
					'request_payload' => ! empty( $body ) ? $body : null,
					'wp_error'        => $error_message,
				]
			);
			return false;
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$err_json = json_decode( $response_body, true );
			$details  = [];
			$has_ja000 = false;

			if ( ! empty( $err_json['errors'] ) && is_array( $err_json['errors'] ) ) {
				foreach ( $err_json['errors'] as $e ) {
					$piece = '';
					if ( ! empty( $e['code'] ) ) {
						$piece .= '[' . $e['code'] . '] ';
						if ( stripos( $e['code'], 'JA000' ) !== false ) {
							$has_ja000 = true;
						}
					}
					if ( ! empty( $e['title'] ) ) {
						$piece .= $e['title'];
					}
					if ( ! empty( $e['detail'] ) && ( empty( $e['title'] ) || $e['detail'] !== $e['title'] ) ) {
						$piece .= ': ' . $e['detail'];
					}
					if ( ! empty( $e['source']['pointer'] ) ) {
						$piece .= ' (Campo: ' . $e['source']['pointer'] . ')';
					}
					$details[] = ! empty( $piece ) ? $piece : wp_json_encode( $e );
				}
				$err_msg = implode( ' | ', $details );
			} elseif ( ! empty( $err_json['message'] ) ) {
				$err_msg = $err_json['message'];
			} elseif ( ! empty( $err_json['error_description'] ) ) {
				$err_msg = $err_json['error_description'];
			} elseif ( ! empty( $err_json['error'] ) && is_string( $err_json['error'] ) ) {
				$err_msg = $err_json['error'];
			} else {
				$err_msg = is_string( $response_body ) ? substr( wp_strip_all_tags( $response_body ), 0, 300 ) : wp_json_encode( $response_body );
			}

			if ( $has_ja000 || stripos( $err_msg, 'JA000' ) !== false ) {
				$err_msg .= ' (Nota do Sistema: Erro interno de validação da CEGID/TOConline. Inspecione o payload enviado para verificar se todos os atributos obrigatórios e códigos fiscais estão corretos).';
			}

			$user_msg = sprintf(
				/* translators: 1: Código de status HTTP, 2: Método HTTP, 3: Endpoint, 4: Detalhes */
				__( 'CEGID API retornou HTTP %1$d em %2$s %3$s: %4$s', 'wc-cegid-sync' ),
				$status_code,
				$method,
				$endpoint,
				$err_msg
			);

			self::log(
				$user_msg,
				'error',
				[
					'url'             => $url,
					'method'          => $method,
					'endpoint'        => $endpoint,
					'status_code'     => $status_code,
					'request_payload' => ! empty( $body ) ? $body : null,
					'response_data'   => ! empty( $err_json ) ? $err_json : substr( (string) $response_body, 0, 1000 ),
				]
			);

			return false;
		}

		if ( empty( $response_body ) ) {
			return true;
		}

		return json_decode( $response_body, true );
	}

	/**
	 * Cria um rascunho ou emite uma fatura diretamente na CEGID (v1).
	 *
	 * @param array $payload Dados da fatura mapeados.
	 * @return array|false Retorna o array do documento criado ou false.
	 */
	public static function create_invoice( $payload ) {
		return self::request( 'v1/commercial_sales_documents', 'POST', $payload, true );
	}

	/**
	 * Finaliza um rascunho de fatura existente (v1).
	 *
	 * @param int $invoice_id ID do documento na CEGID.
	 * @return array|false Retorna os dados do documento finalizado ou false.
	 */
	public static function finalize_invoice( $invoice_id ) {
		return self::request( "v1/commercial_sales_documents/{$invoice_id}/finalize", 'POST' );
	}

	/**
	 * Retorna a lista de produtos cadastrados no CEGID (v0).
	 * Utiliza cache temporário estático para evitar requisições repetidas no mesmo ciclo de execução.
	 *
	 * @param int $page_size Quantidade de itens por requisição.
	 * @return array Lista de produtos no formato de dados JSON-API.
	 */
	public static function get_all_products( $page_size = 100 ) {
		static $cached_products = null;
		if ( $cached_products !== null ) {
			return $cached_products;
		}

		// Tentativa com paginação explícita
		$endpoint = 'products?page[size]=' . (int) $page_size;
		$response = self::request( $endpoint, 'GET', [], true );

		if ( ! empty( $response['data'] ) && is_array( $response['data'] ) ) {
			$cached_products = $response['data'];
			return $cached_products;
		}

		// Fallback para endpoint simples caso page[size] seja recusado
		$response_fallback = self::request( 'products', 'GET', [], true );
		if ( ! empty( $response_fallback['data'] ) && is_array( $response_fallback['data'] ) ) {
			$cached_products = $response_fallback['data'];
			return $cached_products;
		}

		$cached_products = [];
		return $cached_products;
	}

	/**
	 * Busca a lista geral de serviços da empresa na CEGID (v0).
	 * Utiliza cache temporário estático para evitar requisições repetidas no mesmo ciclo de execução.
	 *
	 * @param int $page_size Quantidade de itens por requisição.
	 * @return array Lista de serviços no formato de dados JSON-API.
	 */
	public static function get_all_services( $page_size = 100 ) {
		static $cached_services = null;
		if ( $cached_services !== null ) {
			return $cached_services;
		}

		$endpoint = 'services?page[size]=' . (int) $page_size;
		$response = self::request( $endpoint, 'GET', [], true );

		if ( ! empty( $response['data'] ) && is_array( $response['data'] ) ) {
			$cached_services = $response['data'];
			return $cached_services;
		}

		$response_fallback = self::request( 'services', 'GET', [], true );
		if ( ! empty( $response_fallback['data'] ) && is_array( $response_fallback['data'] ) ) {
			$cached_services = $response_fallback['data'];
			return $cached_services;
		}

		$cached_services = [];
		return $cached_services;
	}

	/**
	 * Busca produtos ou serviços cadastrados no CEGID filtrando por SKU (v0).
	 * Suporta pesquisa inteligente direcionada (services vs products) com fallback mútuo.
	 *
	 * @param string    $sku SKU do produto.
	 * @param bool|null $is_service Se true busca em services primeiro; se false busca em products primeiro; se null testa ambos.
	 * @return array|false Retorna dados do produto/serviço ou false se não encontrado.
	 */
	public static function get_product_by_sku( $sku, $is_service = null ) {
		$clean_sku = trim( (string) $sku );
		if ( empty( $clean_sku ) ) {
			return false;
		}

		$endpoints = ( $is_service === true ) ? [ 'services', 'products' ] : [ 'products', 'services' ];

		foreach ( $endpoints as $res_type ) {
			// Tentativa 1: Filtro direto padrão JSON-API por item_code
			$endpoint = $res_type . '?filter[item_code]=' . urlencode( $clean_sku );
			$response = self::request( $endpoint, 'GET', [], true );

			if ( ! empty( $response['data'] ) && is_array( $response['data'] ) ) {
				foreach ( $response['data'] as $item ) {
					if ( isset( $item['attributes']['item_code'] ) && strcasecmp( trim( $item['attributes']['item_code'] ), $clean_sku ) === 0 ) {
						return $item;
					}
				}
				if ( count( $response['data'] ) === 1 ) {
					return $response['data'][0];
				}
			}

			// Tentativa 2: Filtro com notação explícita de igualdade [eq]
			$endpoint_eq = $res_type . '?filter[item_code][eq]=' . urlencode( $clean_sku );
			$response_eq = self::request( $endpoint_eq, 'GET', [], true );

			if ( ! empty( $response_eq['data'] ) && is_array( $response_eq['data'] ) ) {
				foreach ( $response_eq['data'] as $item ) {
					if ( isset( $item['attributes']['item_code'] ) && strcasecmp( trim( $item['attributes']['item_code'] ), $clean_sku ) === 0 ) {
						return $item;
					}
				}
				if ( count( $response_eq['data'] ) === 1 ) {
					return $response_eq['data'][0];
				}
			}

			// Tentativa 3: Varredura da listagem geral
			$all_items = ( $res_type === 'services' ) ? self::get_all_services( 100 ) : self::get_all_products( 100 );
			if ( ! empty( $all_items ) ) {
				foreach ( $all_items as $item ) {
					if ( isset( $item['attributes']['item_code'] ) && strcasecmp( trim( $item['attributes']['item_code'] ), $clean_sku ) === 0 ) {
						return $item;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Busca dados de um produto ou serviço diretamente pelo seu ID na CEGID (v0).
	 *
	 * @param int|string $cegid_product_id ID no CEGID.
	 * @param bool       $is_service Se é do tipo serviço.
	 * @return array|false Retorna os dados ou false se não encontrado.
	 */
	public static function get_product( $cegid_product_id, $is_service = false ) {
		if ( empty( $cegid_product_id ) ) {
			return false;
		}

		$primary_res   = $is_service ? 'services' : 'products';
		$secondary_res = $is_service ? 'products' : 'services';

		$endpoint = $primary_res . '/' . rawurlencode( (string) $cegid_product_id );
		$response = self::request( $endpoint, 'GET', [], true );

		if ( ! empty( $response['data'] ) && is_array( $response['data'] ) ) {
			return $response['data'];
		}

		// Fallback para o outro endpoint caso o tipo tenha mudado
		$endpoint_fallback = $secondary_res . '/' . rawurlencode( (string) $cegid_product_id );
		$response_fallback = self::request( $endpoint_fallback, 'GET', [], true );

		if ( ! empty( $response_fallback['data'] ) && is_array( $response_fallback['data'] ) ) {
			return $response_fallback['data'];
		}

		return false;
	}

	/**
	 * Extrai a quantidade de estoque de um produto retornado pela CEGID de forma resiliente.
	 * Se a API da CEGID não possuir ou não expor nenhum campo de inventário, retorna null
	 * (evitando zerar indevidamente o estoque do WooCommerce).
	 *
	 * @param array $cegid_item Array do produto retornado pelo TOConline.
	 * @return float|null Quantidade de estoque identificada, ou null se não houver campo de estoque na resposta.
	 */
	public static function extract_stock_quantity( $cegid_item ) {
		$attrs = isset( $cegid_item['attributes'] ) && is_array( $cegid_item['attributes'] ) ? $cegid_item['attributes'] : [];

		// Mapeamento amplo de chaves de estoque utilizadas em APIs de ERP e TOConline
		$stock_keys = [
			'inventory_quantity',
			'stock_quantity',
			'current_stock',
			'stock',
			'quantity',
			'physical_quantity',
			'available_quantity',
			'quantity_on_hand',
			'stock_balance',
			'current_inventory',
			'balance',
			'existencias',
			'saldo',
		];

		foreach ( $stock_keys as $key ) {
			if ( array_key_exists( $key, $attrs ) && $attrs[ $key ] !== null && $attrs[ $key ] !== '' ) {
				return (float) $attrs[ $key ];
			}
		}

		return null;
	}

	/**
	 * Atualiza o estoque do produto no CEGID (v0).
	 *
	 * @param int   $cegid_product_id ID do produto no CEGID.
	 * @param string $sku SKU do produto.
	 * @param float  $quantity Nova quantidade em estoque.
	 * @return bool True em caso de sucesso, false caso contrário.
	 */
	public static function update_stock( $cegid_product_id, $sku, $quantity ) {
		$endpoint = "products/{$cegid_product_id}";
		$payload  = [
			'data' => [
				'type'       => 'products',
				'id'         => (string) $cegid_product_id,
				'attributes' => [
					'item_code'          => $sku,
					'inventory_quantity' => (float) $quantity,
				],
			],
		];

		$response = self::request( $endpoint, 'PATCH', $payload, true );
		return ! empty( $response );
	}

	/**
	 * Cria um novo produto ou serviço no CEGID (v0).
	 *
	 * @param array $payload Estrutura de dados do produto/serviço.
	 * @param bool  $is_service Indica se deve ser enviado para /services ou /products.
	 * @return array|false Dados do item criado ou false.
	 */
	public static function create_product( $payload, $is_service = false ) {
		$endpoint = ( $is_service || ( isset( $payload['data']['type'] ) && $payload['data']['type'] === 'services' ) ) ? 'services' : 'products';
		return self::request( $endpoint, 'POST', $payload, true );
	}

	/**
	 * Atualiza atributos de um produto existente no CEGID via API v0 (PATCH).
	 *
	 * @param int|string $cegid_product_id ID do produto na CEGID.
	 * @param array      $payload Estrutura de dados no padrão JSON-API.
	 * @return array|false Dados do produto atualizado ou false.
	 */
	public static function update_product( $cegid_product_id, $payload ) {
		if ( empty( $cegid_product_id ) ) {
			return false;
		}

		$endpoint = 'products/' . rawurlencode( (string) $cegid_product_id );
		return self::request( $endpoint, 'PATCH', $payload, true );
	}
}

