<?php
/**
 * Classe responsável por mapear dados do WooCommerce para o formato JSON da CEGID.
 *
 * @package WC_Cegid_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cegid_Data_Mapper {

	/**
	 * Mapeia um pedido do WooCommerce (WC_Order) para o payload de Faturamento (v1) da CEGID.
	 *
	 * @param WC_Order $order Objeto do pedido do WooCommerce.
	 * @return array Payload estruturado pronto para envio.
	 */
	public static function map_order_to_cegid_invoice( $order ) {
		$options = Cegid_Settings::get_options();

		// Resgata o NIF do cliente com fallback para consumidor final em Portugal (999999990)
		$nif = $order->get_meta( '_billing_nif', true );
		if ( empty( $nif ) ) {
			$nif = $order->get_meta( '_billing_vat', true );
		}
		if ( empty( $nif ) ) {
			$nif = $order->get_meta( 'vat_number', true );
		}
		$nif = preg_replace( '/[^0-9]/', '', $nif );
		if ( empty( $nif ) || strlen( $nif ) !== 9 ) {
			$nif = '999999990';
		}

		// Mapeia método de pagamento do WooCommerce para códigos suportados pelo TOConline
		$payment_method = $order->get_payment_method();
		$payment_mechanism = 'OU'; // Outro por padrão

		switch ( $payment_method ) {
			case 'bacs':
				$payment_mechanism = 'TR'; // Transferência Bancária
				break;
			case 'cod':
				$payment_mechanism = 'MO'; // Dinheiro / Numerário
				break;
			case 'stripe':
			case 'stripe_cc':
				$payment_mechanism = 'CC'; // Cartão de Crédito
				break;
			case 'multibanco':
			case 'ifthenpay_multibanco':
				$payment_mechanism = 'MB'; // Referência Multibanco
				break;
			default:
				$payment_mechanism = 'OU';
				break;
		}

		// Define se preços incluem IVA nas configurações da loja
		$vat_included_prices = wc_prices_include_tax();

		$payload = [
			'document_type'                    => $options['document_type'],
			'date'                             => $order->get_date_created()->date( 'Y-m-d' ),
			'document_series_prefix'           => $options['document_series_prefix'],
			'customer_tax_registration_number' => $nif,
			'customer_business_name'           => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ?: ( $order->get_billing_company() ?: __( 'Consumidor Final', 'wc-cegid-sync' ) ),
			'customer_address_detail'          => trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
			'customer_postcode'                => $order->get_billing_postcode(),
			'customer_city'                    => $order->get_billing_city(),
			'customer_country'                 => $order->get_billing_country() ?: 'PT',
			'payment_mechanism'                => $payment_mechanism,
			'vat_included_prices'              => $vat_included_prices,
			'external_reference'               => 'WC-' . $order->get_id(),
			'finalize'                         => ( $options['auto_finalize'] === 'yes' ),
			'return_pdf'                       => true,
			'lines'                            => [],
		];

		// Nota fiscal anexada opcionalmente
		$customer_note = $order->get_customer_note();
		if ( ! empty( $customer_note ) ) {
			$payload['notes'] = substr( sanitize_text_field( $customer_note ), 0, 200 );
		}

		// Adiciona linhas de itens do pedido
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			// Preço unitário dependendo se o WooCommerce está configurado para mostrar taxas inclusas
			if ( $vat_included_prices ) {
				$unit_price = (float) ( ( $item->get_subtotal() + $item->get_subtotal_tax() ) / max( 1, $item->get_quantity() ) );
			} else {
				$unit_price = (float) ( $item->get_subtotal() / max( 1, $item->get_quantity() ) );
			}

			// Mapeia impostos
			$tax_data = self::get_item_tax_data( $item );

			$item_type = self::is_service( $product ) ? 'Service' : 'Product';
			$sku       = $product->get_sku() ?: 'WC-PROD-' . $product->get_id();

			$payload['lines'][] = [
				'item_type'          => $item_type,
				'item_code'          => substr( $sku, 0, 30 ),
				'description'        => substr( $item->get_name(), 0, 100 ),
				'quantity'           => (float) $item->get_quantity(),
				'unit_price'         => round( $unit_price, 4 ),
				'tax_code'           => $tax_data['code'],
				'tax_percentage'     => $tax_data['percentage'],
				'tax_country_region' => 'PT',
			];
		}

		// Adiciona custos de portes/envio como linha de serviço se houver
		if ( (float) $order->get_shipping_total() > 0 ) {
			$shipping_tax = (float) $order->get_shipping_tax();
			$shipping_total = (float) $order->get_shipping_total();

			if ( $vat_included_prices ) {
				$unit_price_shipping = $shipping_total + $shipping_tax;
			} else {
				$unit_price_shipping = $shipping_total;
			}

			// Tenta mapear impostos dos portes de envio
			$shipping_tax_data = self::get_shipping_tax_data( $order );

			$payload['lines'][] = [
				'item_type'          => 'Service',
				'item_code'          => 'SHIPPING',
				'description'        => __( 'Custos de Envio / Portes', 'wc-cegid-sync' ),
				'quantity'           => 1.0,
				'unit_price'         => round( $unit_price_shipping, 4 ),
				'tax_code'           => $shipping_tax_data['code'],
				'tax_percentage'     => $shipping_tax_data['percentage'],
				'tax_country_region' => 'PT',
			];
		}

		return $payload;
	}

	/**
	 * Mapeia taxas de IVA do item para os códigos da CEGID (NOR, INT, RED, ISE).
	 *
	 * @param WC_Order_Item_Product $item Item do pedido.
	 * @return array Array contendo o código da taxa e o percentual.
	 */
	private static function get_item_tax_data( $item ) {
		$tax_data = [
			'code'       => 'ISE',
			'percentage' => 0.0,
		];

		$product = $item->get_product();
		if ( $product && $product->get_tax_status() === 'taxable' ) {
			$subtotal = (float) $item->get_subtotal();
			$tax      = (float) $item->get_subtotal_tax();

			if ( $subtotal > 0 && $tax > 0 ) {
				// Calcula taxa percentual efetiva
				$rate = round( ( $tax / $subtotal ) * 100 );
				$tax_data['percentage'] = (float) $rate;

				if ( $rate >= 20 ) {
					$tax_data['code'] = 'NOR'; // Normal (23%)
				} elseif ( $rate >= 10 ) {
					$tax_data['code'] = 'INT'; // Intermédia (13%)
				} elseif ( $rate > 0 ) {
					$tax_data['code'] = 'RED'; // Reduzida (6%)
				}
			}
		}

		return $tax_data;
	}

	/**
	 * Mapeia taxas de IVA de envio.
	 *
	 * @param WC_Order $order Objeto de pedido.
	 * @return array Array contendo o código da taxa e o percentual de envio.
	 */
	private static function get_shipping_tax_data( $order ) {
		$tax_data = [
			'code'       => 'ISE',
			'percentage' => 0.0,
		];

		$shipping_total = (float) $order->get_shipping_total();
		$shipping_tax   = (float) $order->get_shipping_tax();

		if ( $shipping_total > 0 && $shipping_tax > 0 ) {
			$rate = round( ( $shipping_tax / $shipping_total ) * 100 );
			$tax_data['percentage'] = (float) $rate;

			if ( $rate >= 20 ) {
				$tax_data['code'] = 'NOR';
			} elseif ( $rate >= 10 ) {
				$tax_data['code'] = 'INT';
			} elseif ( $rate > 0 ) {
				$tax_data['code'] = 'RED';
			}
		}

		return $tax_data;
	}

	/**
	 * Converte dados de produto do WooCommerce para o payload oficial de criação de artigo no CEGID (v0).
	 *
	 * @param WC_Product $product Objeto de produto do WooCommerce.
	 * @return array Payload estruturado no padrão JSON-API compatível com a CEGID.
	 */
	public static function map_product_to_cegid( $product ) {
		$options = Cegid_Settings::get_options();

		$sku = $product->get_sku() ?: 'WC-PROD-' . $product->get_id();
		
		// Calcula o preço de venda sem IVA
		$sales_price = (float) wc_get_price_excluding_tax( $product );
		if ( $sales_price <= 0 && (float) $product->get_regular_price() > 0 ) {
			$sales_price = (float) $product->get_regular_price();
		}

		// Determina o código fiscal da taxa de IVA (NOR, INT, RED, ISE)
		$tax_code = 'NOR'; // Fallback padrão (23%)

		// 1. Tenta mapear pela taxa configurada no WooCommerce
		$tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
		if ( ! empty( $tax_rates ) ) {
			$rate_info = reset( $tax_rates );
			$rate = (float) $rate_info['rate'];
			if ( $rate >= 20 ) {
				$tax_code = 'NOR';
			} elseif ( $rate >= 10 ) {
				$tax_code = 'INT';
			} elseif ( $rate > 0 ) {
				$tax_code = 'RED';
			} else {
				$tax_code = 'ISE';
			}
		} elseif ( ! empty( $options['product_tax_descriptor'] ) ) {
			// 2. Se o WooCommerce não tem taxas cadastradas, mapeia a partir da configuração do plugin
			$desc = strtoupper( trim( $options['product_tax_descriptor'] ) );
			if ( strpos( $desc, '23' ) !== false || strpos( $desc, 'NOR' ) !== false ) {
				$tax_code = 'NOR';
			} elseif ( strpos( $desc, '13' ) !== false || strpos( $desc, 'INT' ) !== false ) {
				$tax_code = 'INT';
			} elseif ( strpos( $desc, '06' ) !== false || strpos( $desc, '6' ) !== false || strpos( $desc, 'RED' ) !== false ) {
				$tax_code = 'RED';
			} elseif ( strpos( $desc, 'ISE' ) !== false || strpos( $desc, '0' ) !== false ) {
				$tax_code = 'ISE';
			}
		}

		$is_service = self::is_service( $product );

		$attributes = [
			'type'                     => $is_service ? 'Service' : 'Product',
			'item_code'                => substr( $sku, 0, 30 ),
			'item_description'         => substr( $product->get_name(), 0, 100 ),
			'sales_price'              => round( $sales_price, 4 ),
			'sales_price_includes_vat' => false,
			'tax_code'                 => $tax_code,
			'product_inventory_type'   => $is_service ? 'service' : 'physical',
			'is_active'                => true,
		];

		// Se for isenção de IVA, anexa motivo legal
		if ( $tax_code === 'ISE' && ! empty( $options['product_exemption_reason'] ) ) {
			$attributes['exemption_reason'] = $options['product_exemption_reason'];
		}

		return [
			'data' => [
				'type'       => $is_service ? 'services' : 'products',
				'attributes' => $attributes,
			],
		];
	}

	/**
	 * Determina se o produto do WooCommerce deve ser classificado como Serviço no CEGID (TOConline).
	 *
	 * Regra de Negócio:
	 * - Simple Subscriptions, Variable Subscriptions e Subscription Variations -> Serviço ('Service' / 'service').
	 * - Produtos Virtuais ou Downloadables -> Serviço ('Service' / 'service').
	 * - Produtos Simples Físicos ou Variações Físicas -> Produto ('Product' / 'physical').
	 *
	 * @param WC_Product $product Objeto de produto do WooCommerce.
	 * @return bool True se for serviço/assinatura, false se for produto físico.
	 */
	public static function is_service( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}

		$type = strtolower( (string) $product->get_type() );

		// Assinaturas (WooCommerce Subscriptions ou similares)
		$subscription_types = [ 'subscription', 'variable-subscription', 'subscription_variation' ];
		if ( in_array( $type, $subscription_types, true ) || strpos( $type, 'subscription' ) !== false ) {
			return true;
		}

		// Produtos virtuais ou para download
		if ( $product->is_virtual() || $product->is_downloadable() ) {
			return true;
		}

		// Tipos customizados de serviço
		if ( $type === 'service' || strpos( $type, 'service' ) !== false ) {
			return true;
		}

		return false;
	}
}
