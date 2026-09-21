<?php
/**
 * Interface administrativa do plugin (Painel de Controle).
 *
 * @package WC_Cegid_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cegid_Admin_UI {

	/**
	 * Inicializa os hooks da interface administrativa.
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ], 9 ); // Prioridade 9 para carregar antes do settings
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_invoice_meta_box' ] );
	}

	/**
	 * Registra o menu principal do plugin no painel do WordPress.
	 */
	public static function add_admin_menu() {
		add_menu_page(
			__( 'Sincronizador WooCommerce CEGID', 'wc-cegid-sync' ),
			__( 'CEGID Sync', 'wc-cegid-sync' ),
			'manage_options',
			'wc-cegid-sync',
			[ __CLASS__, 'render_dashboard_page' ],
			'dashicons-randomize',
			57
		);

		add_submenu_page(
			'wc-cegid-sync',
			__( 'Painel de Sincronização', 'wc-cegid-sync' ),
			__( 'Sincronizador', 'wc-cegid-sync' ),
			'manage_options',
			'wc-cegid-sync',
			[ __CLASS__, 'render_dashboard_page' ]
		);
	}

	/**
	 * Enfilera os arquivos CSS e JS para a página do plugin no admin.
	 *
	 * @param string $hook_suffix O sufixo da página atual do admin.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'wc-cegid-sync' ) === false ) {
			return;
		}

		wp_enqueue_style( 'cegid-admin-style', WC_CEGID_SYNC_URL . 'assets/css/admin-style.css', [], WC_CEGID_SYNC_VERSION );
		wp_enqueue_script( 'cegid-admin-script', WC_CEGID_SYNC_URL . 'assets/js/admin-script.js', [ 'jquery' ], WC_CEGID_SYNC_VERSION, true );

		// Localiza dados do WordPress para uso no JavaScript (Ajax)
		wp_localize_script(
			'cegid-admin-script',
			'cegid_sync_params',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cegid_sync_nonce' ),
				'labels'   => [
					'success' => __( 'Sucesso!', 'wc-cegid-sync' ),
					'error'   => __( 'Ocorreu um erro.', 'wc-cegid-sync' ),
				],
			]
		);
	}

	/**
	 * Renderiza o painel principal de sincronização (Tabs de Faturas e Estoque).
	 */
	public static function render_dashboard_page() {
		// Verifica se o token de API está funcional para exibir status da conexão
		$connection_ok = false;
		$token = Cegid_API_Client::get_access_token();
		if ( $token ) {
			$connection_ok = true;
		}

		// Recupera as opções do plugin para verificar a licença
		$license_active = Cegid_Settings::is_license_active();

		// Recupera abas ativas (Se a licença for inativa, a aba padrão é a de licença)
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : ($license_active ? 'invoices' : 'license');
		?>
		<div class="wrap cegid-sync-wrap">
			<div class="cegid-header">
				<div class="cegid-branding">
					<span class="dashicons dashicons-randomize cegid-icon"></span>
					<h1><?php esc_html_e( 'Sincronizador WC CEGID Sync', 'wc-cegid-sync' ); ?></h1>
				</div>
				<div class="cegid-status" style="display: flex; gap: 10px;">
					<?php if ( $license_active ) : ?>
						<span class="status-indicator status-online" style="background-color: #f0fdf4; color: #15803d; border-color: #bbf7d0;">
							<span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Licença Ativa', 'wc-cegid-sync' ); ?>
						</span>
					<?php else : ?>
						<span class="status-indicator status-offline" style="background-color: #fef2f2; color: #991b1b; border-color: #fca5a5;">
							<span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Licença Inativa', 'wc-cegid-sync' ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $connection_ok ) : ?>
						<span class="status-indicator status-online">
							<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'API Conectada', 'wc-cegid-sync' ); ?>
						</span>
					<?php else : ?>
						<span class="status-indicator status-offline">
							<span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Erro de Conexão (Verifique as Credenciais)', 'wc-cegid-sync' ); ?>
						</span>
					<?php endif; ?>
				</div>
			</div>

			<h2 class="nav-tab-wrapper">
				<a href="?page=wc-cegid-sync&tab=invoices" class="nav-tab <?php echo $active_tab === 'invoices' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-media-text"></span> <?php esc_html_e( 'Validação de Faturas', 'wc-cegid-sync' ); ?>
				</a>
				<a href="?page=wc-cegid-sync&tab=stock" class="nav-tab <?php echo $active_tab === 'stock' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-store"></span> <?php esc_html_e( 'Sincronizar Estoques', 'wc-cegid-sync' ); ?>
				</a>
				<a href="?page=wc-cegid-sync&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Logs de Auditoria', 'wc-cegid-sync' ); ?>
				</a>
				<a href="?page=wc-cegid-sync&tab=license" class="nav-tab <?php echo $active_tab === 'license' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-admin-network"></span> <?php esc_html_e( 'Ativação da Licença', 'wc-cegid-sync' ); ?>
				</a>
			</h2>

			<div class="tab-content-container">
				<?php
				if ( ! $license_active && ! in_array( $active_tab, [ 'license', 'logs' ], true ) ) {
					// Trava de segurança: impede o acesso e força a aba de licença
					echo '<div class="notice notice-error inline" style="margin: 0 0 20px 0;"><p>' . esc_html__( 'Acesso Bloqueado. Por favor, ative uma chave de licença válida para liberar as funcionalidades do plugin.', 'wc-cegid-sync' ) . '</p></div>';
					self::render_license_tab();
				} else {
					if ( $active_tab === 'invoices' ) {
						self::render_invoices_tab();
					} elseif ( $active_tab === 'stock' ) {
						self::render_stock_tab();
					} elseif ( $active_tab === 'logs' ) {
						self::render_logs_tab();
					} else {
						self::render_license_tab();
					}
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderiza o conteúdo da aba de Faturas.
	 */
	private static function render_invoices_tab() {
		// Consulta pedidos WooCommerce completados que não têm fatura CEGID gerada
		$args = [
			'status'     => 'completed',
			'limit'      => 30,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'meta_query' => [
				[
					'key'     => '_cegid_invoice_id',
					'compare' => 'NOT EXISTS',
				],
			],
		];
		$query = new WC_Order_Query( $args );
		$orders = $query->get_orders();
		?>
		<div class="cegid-tab-panel">
			<!-- Caixa de Dicas e Guia Rápido da Aba de Faturas -->
			<details class="cegid-tab-help-box" open>
				<summary>
					<span class="cegid-tab-help-title">
						<span class="dashicons dashicons-lightbulb"></span>
						<strong><?php esc_html_e( 'Guia Rápido: Como Validar e Emitir Faturas no CEGID (TOConline)', 'wc-cegid-sync' ); ?></strong>
						<span class="cegid-tab-help-badge"><?php esc_html_e( 'Dicas de Uso', 'wc-cegid-sync' ); ?></span>
					</span>
					<span class="cegid-tab-help-toggle-text">
						<span class="dashicons dashicons-arrow-down-alt2"></span> <?php esc_html_e( 'Ocultar / Mostrar Dicas', 'wc-cegid-sync' ); ?>
					</span>
				</summary>
				<div class="cegid-tab-help-content">
					<div class="cegid-help-grid">
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">1</span>
								<span><?php esc_html_e( 'Revisão dos Pedidos', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Esta tela lista pedidos com status "Completado" no WooCommerce que ainda não possuem documento emitido na CEGID. Verifique o NIF e dados antes de emitir.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">2</span>
								<span><?php esc_html_e( 'Emissão de Fatura', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Clique no botão azul "Emitir Fatura". O plugin envia o pedido para a CEGID, certifica o documento fiscal e anexa o ID e link do PDF diretamente no pedido.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">3</span>
								<span><?php esc_html_e( 'Editar ou Suprimir', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Use "Editar" caso o cliente peça para ajustar o NIF ou produtos. Use "Suprimir" para mover para a lixeira pedidos cancelados que não devam ser faturados.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
					</div>
				</div>
			</details>

			<div class="cegid-action-bar">
				<h3><?php esc_html_e( 'Pedidos WooCommerce Prontos para Faturação', 'wc-cegid-sync' ); ?></h3>
				<button class="button button-secondary action-btn" id="cegid-sync-orders-btn" data-cegid-tooltip="<?php esc_attr_e( 'Recarrega a lista de pedidos concluídos do WooCommerce na tela.', 'wc-cegid-sync' ); ?>">
					<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Sincronizar Pedidos (Recarregar)', 'wc-cegid-sync' ); ?>
				</button>
				<div class="cegid-bulk-actions">
					<select id="cegid-bulk-action-orders" style="height: 36px; padding: 0 25px 0 10px; min-width: 170px; border-radius: 4px; border: 1px solid #ccd0d4; background-color: #ffffff;" data-cegid-tooltip="<?php esc_attr_e( 'Selecione uma ação para aplicar a todos os pedidos marcados.', 'wc-cegid-sync' ); ?>">
						<option value=""><?php esc_html_e( 'Ações em Massa', 'wc-cegid-sync' ); ?></option>
						<option value="trash"><?php esc_html_e( 'Suprimir (Lixeira)', 'wc-cegid-sync' ); ?></option>
					</select>
					<button class="button action-btn" id="cegid-bulk-apply-orders" style="height: 36px !important; line-height: 1 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;" data-cegid-tooltip="<?php esc_attr_e( 'Executa a ação selecionada nos pedidos marcados.', 'wc-cegid-sync' ); ?>">
						<?php esc_html_e( 'Aplicar', 'wc-cegid-sync' ); ?>
					</button>
				</div>
			</div>
			<p class="description" style="margin-bottom: 20px;"><?php esc_html_e( 'Lista de pedidos com status "Completado" que ainda não foram sincronizados com a CEGID.', 'wc-cegid-sync' ); ?></p>

			<?php if ( empty( $orders ) ) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'Nenhum pedido pendente de faturação encontrado no momento.', 'wc-cegid-sync' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped table-view-list posts">
					<thead>
						<tr>
							<th scope="col" class="manage-column column-cb check-column" style="width: 3%; padding: 8px 10px;"><input type="checkbox" id="cegid-select-all-orders" data-cegid-tooltip="<?php esc_attr_e( 'Marcar ou desmarcar todos os pedidos.', 'wc-cegid-sync' ); ?>" /></th>
							<th scope="col" class="column-order-id" style="width: 10%;">
								<?php esc_html_e( 'Pedido', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Número identificador único do pedido WooCommerce.', 'wc-cegid-sync' ); ?>"></span>
							</th>
							<th scope="col" style="width: 20%;">
								<?php esc_html_e( 'Cliente', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Nome do comprador cadastrado no pedido.', 'wc-cegid-sync' ); ?>"></span>
							</th>
							<th scope="col" style="width: 12%;">
								<?php esc_html_e( 'Data', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Data em que o pedido foi concluído no WooCommerce.', 'wc-cegid-sync' ); ?>"></span>
							</th>
							<th scope="col" style="width: 10%;">
								<?php esc_html_e( 'Total', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Valor total bruto do pedido com impostos.', 'wc-cegid-sync' ); ?>"></span>
							</th>
							<th scope="col" style="width: 13%;">
								<?php esc_html_e( 'Pagamento', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Método de pagamento selecionado na compra.', 'wc-cegid-sync' ); ?>"></span>
							</th>
							<th scope="col" class="column-action text-right" style="width: 32%;">
								<?php esc_html_e( 'Ações', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Ações disponíveis: Emitir fatura oficial, Editar pedido ou Suprimir para a lixeira.', 'wc-cegid-sync' ); ?>"></span>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $orders as $order ) : ?>
							<tr id="cegid-order-row-<?php echo esc_attr( $order->get_id() ); ?>">
								<td class="check-column" style="padding: 8px 10px; vertical-align: middle;">
									<input type="checkbox" class="cegid-order-checkbox" value="<?php echo esc_attr( $order->get_id() ); ?>" />
								</td>
								<td class="column-order-id">
									<strong>#<?php echo esc_html( $order->get_id() ); ?></strong>
								</td>
								<td><?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?></td>
								<td><?php echo esc_html( $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) ); ?></td>
								<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
								<td><?php echo esc_html( $order->get_payment_method_title() ); ?></td>
								<td class="column-action text-right">
									<div class="cegid-order-actions" style="display: flex; gap: 5px; justify-content: flex-end; align-items: center; height: 36px;">
										<button class="button button-primary action-btn cegid-sync-invoice-btn" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-cegid-tooltip="<?php esc_attr_e( 'Gera a fatura oficial certificada no TOConline (CEGID) e salva o PDF no pedido.', 'wc-cegid-sync' ); ?>" style="background: linear-gradient(135deg, #3858e9 0%, #5d3df5 100%) !important; color: #ffffff !important; border: 0 !important; border-radius: 4px !important; height: 36px !important; line-height: 1 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;">
											<span class="dashicons dashicons-media-text" style="color: #ffffff !important; margin: 0 !important; font-size: 16px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;"></span> <?php esc_html_e( 'Emitir Fatura', 'wc-cegid-sync' ); ?>
										</button>
										<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" class="button button-secondary action-btn" target="_blank" style="height: 36px !important; line-height: 1 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;" data-cegid-tooltip="<?php esc_attr_e( 'Abre os detalhes do pedido no WooCommerce para editar NIF, produtos ou valores.', 'wc-cegid-sync' ); ?>">
											<span class="dashicons dashicons-edit" style="color: #3858e9 !important; margin: 0 !important; font-size: 16px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;"></span> <?php esc_html_e( 'Editar', 'wc-cegid-sync' ); ?>
										</a>
										<button class="button button-secondary action-btn cegid-trash-order-btn" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" style="color: #dc2626 !important; border-color: #fca5a5 !important; background: #fef2f2 !important; height: 36px !important; line-height: 1 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;" data-cegid-tooltip="<?php esc_attr_e( 'Move este pedido para a lixeira do WooCommerce, retirando-o da fila de faturação.', 'wc-cegid-sync' ); ?>" data-cegid-tooltip-pos="left">
											<span class="dashicons dashicons-trash" style="color: #dc2626 !important; margin: 0 !important; font-size: 16px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;"></span> <?php esc_html_e( 'Suprimir', 'wc-cegid-sync' ); ?>
										</button>
										<span class="spinner cegid-spinner" style="margin: 0 0 0 5px; float: none;"></span>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_stock_tab() {
		// Consulta todos os produtos e variações publicados do WooCommerce
		$args = [
			'status' => 'publish',
			'limit'  => -1,
			'type'   => [ 'simple', 'variation', 'subscription', 'variable-subscription' ],
		];
		$all_products = wc_get_products( $args );

		// Filtra apenas produtos que possuem SKU cadastrado (obrigatório para CEGID)
		$products = [];
		foreach ( $all_products as $p ) {
			if ( ! empty( $p->get_sku() ) ) {
				$products[] = $p;
			}
		}
		?>
		<div class="cegid-tab-panel">
			<!-- Caixa de Dicas e Guia Rápido da Aba de Estoques -->
			<details class="cegid-tab-help-box" open>
				<summary>
					<span class="cegid-tab-help-title">
						<span class="dashicons dashicons-lightbulb"></span>
						<strong><?php esc_html_e( 'Guia Rápido: Como Gerenciar e Sincronizar Estoques com o CEGID', 'wc-cegid-sync' ); ?></strong>
						<span class="cegid-tab-help-badge"><?php esc_html_e( 'Dicas de Uso', 'wc-cegid-sync' ); ?></span>
					</span>
					<span class="cegid-tab-help-toggle-text">
						<span class="dashicons dashicons-arrow-down-alt2"></span> <?php esc_html_e( 'Ocultar / Mostrar Dicas', 'wc-cegid-sync' ); ?>
					</span>
				</summary>
				<div class="cegid-tab-help-content">
					<div class="cegid-help-grid">
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">1</span>
								<span><?php esc_html_e( 'Cadastrar ou Vincular', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Clique em "Cadastrar Artigo" no produto desejado. Se o item já existia no CEGID, o plugin localiza o SKU na hora e converte o botão em "Sincronizar Estoque".', 'wc-cegid-sync' ); ?>
							</p>
						</div>
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">2</span>
								<span><?php esc_html_e( 'Produtos Físicos vs Serviços', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Assinaturas (Subscriptions) e itens virtuais são cadastrados na área de "Serviços" do CEGID. Produtos simples vão para a área de "Produtos".', 'wc-cegid-sync' ); ?>
							</p>
						</div>
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">3</span>
								<span><?php esc_html_e( 'Proteção de Estoque', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'O botão "PULL da CEGID" atualiza o inventário da loja. Caso a CEGID não informe saldo de armazém, o seu estoque no WooCommerce nunca é zerado.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">4</span>
								<span><?php esc_html_e( 'Desvincular para Corrigir', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Use o botão de elo cortado ao lado de "Sincronizar Estoque" caso queira remover o ID associado e recadastrar o item do zero no CEGID.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
					</div>
				</div>
			</details>

			<div class="cegid-action-bar">
				<h3><?php esc_html_e( 'Validação de Estoque de Produtos', 'wc-cegid-sync' ); ?></h3>
				<button class="button button-primary action-btn" id="cegid-sync-all-stocks-btn" data-cegid-tooltip="<?php esc_attr_e( 'Consulta as quantidades reais na CEGID e atualiza o inventário do WooCommerce.', 'wc-cegid-sync' ); ?>">
					<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Sincronizar Estoques (PULL da CEGID)', 'wc-cegid-sync' ); ?>
				</button>
				<button class="button button-secondary action-btn" id="cegid-verify-links-btn" style="height: 36px !important; line-height: 1 !important;" data-cegid-tooltip="<?php esc_attr_e( 'Varre produtos pendentes e associa na hora aqueles que já foram cadastrados na CEGID.', 'wc-cegid-sync' ); ?>">
					<span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Verificar Vínculos na CEGID', 'wc-cegid-sync' ); ?>
				</button>
				<button class="button button-secondary action-btn" id="cegid-reload-stock-btn" style="height: 36px !important; line-height: 1 !important;" data-cegid-tooltip="<?php esc_attr_e( 'Recarrega a tela com as quantidades de estoque atualmente salvas no WooCommerce.', 'wc-cegid-sync' ); ?>">
					<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Sincronizar com WooCommerce (Recarregar)', 'wc-cegid-sync' ); ?>
				</button>
				<div class="cegid-bulk-actions">
					<select id="cegid-product-filter-status" style="height: 36px; padding: 0 25px 0 10px; min-width: 160px; border-radius: 4px; border: 1px solid #ccd0d4; background-color: #ffffff; margin-right: 5px;" data-cegid-tooltip="<?php esc_attr_e( 'Filtra os produtos entre Pendentes e já Cadastrados no CEGID.', 'wc-cegid-sync' ); ?>">
						<option value=""><?php esc_html_e( 'Todos os Status', 'wc-cegid-sync' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Apenas Pendentes', 'wc-cegid-sync' ); ?></option>
						<option value="active"><?php esc_html_e( 'Apenas Cadastrados', 'wc-cegid-sync' ); ?></option>
					</select>
					<select id="cegid-bulk-action-products" style="height: 36px; padding: 0 25px 0 10px; min-width: 170px; border-radius: 4px; border: 1px solid #ccd0d4; background-color: #ffffff;" data-cegid-tooltip="<?php esc_attr_e( 'Selecione a ação em massa para aplicar aos produtos selecionados.', 'wc-cegid-sync' ); ?>">
						<option value=""><?php esc_html_e( 'Ações em Massa', 'wc-cegid-sync' ); ?></option>
						<option value="sync"><?php esc_html_e( 'Sincronizar Estoques', 'wc-cegid-sync' ); ?></option>
						<option value="create"><?php esc_html_e( 'Cadastrar na CEGID', 'wc-cegid-sync' ); ?></option>
					</select>
					<button class="button action-btn" id="cegid-bulk-apply-products" style="height: 36px !important; line-height: 1 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;" data-cegid-tooltip="<?php esc_attr_e( 'Executa a operação em lote nos produtos marcados.', 'wc-cegid-sync' ); ?>">
						<?php esc_html_e( 'Aplicar', 'wc-cegid-sync' ); ?>
					</button>
				</div>
				<span class="spinner cegid-spinner" id="cegid-global-spinner" style="float: none; margin: 0;"></span>
			</div>
			<p class="description" style="margin-bottom: 20px;"><?php esc_html_e( 'Consulta as quantidades de estoque atuais na CEGID e atualiza o WooCommerce local.', 'wc-cegid-sync' ); ?></p>

			<?php if ( empty( $products ) ) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'Nenhum produto publicado com SKU encontrado.', 'wc-cegid-sync' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped table-view-list posts">
					<thead>
						<tr>
							<th scope="col" class="manage-column column-cb check-column" style="width: 3%; padding: 8px 10px;"><input type="checkbox" id="cegid-select-all-products" data-cegid-tooltip="<?php esc_attr_e( 'Marcar ou desmarcar todos os produtos da página.', 'wc-cegid-sync' ); ?>" /></th>
							<th scope="col" style="width: 15%;">
								<?php esc_html_e( 'SKU', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Código de referência único obrigatório para sincronização.', 'wc-cegid-sync' ); ?>"></span>
							</th>
							<th scope="col" style="width: 22%;">
								<?php esc_html_e( 'Produto', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Nome e classificação (Produto Físico ou Serviço/Assinatura).', 'wc-cegid-sync' ); ?>"></span>
							</th>
							<th scope="col" class="column-stock-wc" style="width: 15%;">
								<?php esc_html_e( 'Estoque WooCommerce', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Saldo de unidades atualmente disponível para venda no site.', 'wc-cegid-sync' ); ?>"></span>
							</th>
							<th scope="col" class="column-stock-cegid" style="width: 15%;">
								<?php esc_html_e( 'Estoque CEGID', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Quantidade física lida na CEGID (preserva o estoque do WooCommerce se não exposta na API).', 'wc-cegid-sync' ); ?>"></span>
							</th>
							<th scope="col" style="width: 12%;">
								<?php esc_html_e( 'Cadastro CEGID', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Cadastrado (vinculado na CEGID) ou Pendente (sem vínculo).', 'wc-cegid-sync' ); ?>"></span>
							</th>
							<th scope="col" style="width: 18%;">
								<?php esc_html_e( 'Última Sincronização', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Data e hora da última consulta de estoque realizada.', 'wc-cegid-sync' ); ?>"></span>
							</th>
							<th scope="col" class="column-action text-right" style="width: 15%;">
								<?php esc_html_e( 'Ações', 'wc-cegid-sync' ); ?>
								<span class="dashicons dashicons-editor-help cegid-help-tip" data-cegid-tooltip="<?php esc_attr_e( 'Cadastrar artigo, Sincronizar estoque individual ou Desvincular.', 'wc-cegid-sync' ); ?>"></span>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $products as $product ) : ?>
							<?php
							$sku = $product->get_sku();
							$last_sync = $product->get_meta( '_cegid_last_sync', true );
							$last_sync_formatted = ! empty( $last_sync ) ? date_i18n( get_option( 'date_format' ) . ' H:i', $last_sync ) : __( 'Nunca sincronizado', 'wc-cegid-sync' );
							$sync_error = $product->get_meta( '_cegid_sync_error', true );
							$cegid_product_id = $product->get_meta( '_cegid_product_id', true );
							$has_cegid_link = ! empty( $cegid_product_id );
							$is_service_item = Cegid_Data_Mapper::is_service( $product );
							?>
							<tr id="cegid-product-row-<?php echo esc_attr( $product->get_id() ); ?>">
								<td class="check-column" style="padding: 8px 10px; vertical-align: middle;">
									<input type="checkbox" class="cegid-product-checkbox" value="<?php echo esc_attr( $product->get_id() ); ?>" />
								</td>
								<td>
									<strong><?php echo esc_html( $sku ?: __( 'Sem SKU', 'wc-cegid-sync' ) ); ?></strong>
								</td>
								<td>
									<div><?php echo esc_html( $product->get_name() ); ?></div>
									<div style="margin-top: 4px;">
										<?php if ( $is_service_item ) : ?>
											<span class="cegid-badge-type" style="background-color: #ede9fe; color: #5b21b6; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; border: 1px solid #ddd6fe; display: inline-block;"><?php esc_html_e( 'Serviço / Assinatura', 'wc-cegid-sync' ); ?></span>
										<?php else : ?>
											<span class="cegid-badge-type" style="background-color: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; border: 1px solid #e2e8f0; display: inline-block;"><?php esc_html_e( 'Produto Físico', 'wc-cegid-sync' ); ?></span>
										<?php endif; ?>
									</div>
								</td>
								<td class="column-stock-wc">
									<?php
									if ( $is_service_item ) {
										echo '<span class="na-badge" style="color: #6366f1; font-size: 11px; font-weight: 500;">' . esc_html__( 'Serviço', 'wc-cegid-sync' ) . '</span>';
									} elseif ( $product->managing_stock() ) {
										echo esc_html( $product->get_stock_quantity() );
									} else {
										echo '<span class="na-badge">—</span>';
									}
									?>
								</td>
								<td class="column-stock-cegid">
									<?php
									$cegid_stock = $product->get_meta( '_cegid_stock_qty', true );
									if ( $is_service_item || $cegid_stock === 'service' ) {
										echo '<span class="na-badge" style="color: #6366f1; font-size: 11px; font-weight: 500;">' . esc_html__( 'Serviço', 'wc-cegid-sync' ) . '</span>';
									} elseif ( $cegid_stock === 'na' ) {
										echo '<span class="na-badge" style="color: #64748b; font-style: italic; font-size: 12px;" title="' . esc_attr__( 'A API pública de artigos do CEGID não disponibilizou a quantidade em inventário deste artigo.', 'wc-cegid-sync' ) . '">' . esc_html__( '— (Não exposto na API)', 'wc-cegid-sync' ) . '</span>';
									} elseif ( $cegid_stock !== '' && is_numeric( $cegid_stock ) ) {
										echo esc_html( $cegid_stock );
									} else {
										echo '<span class="na-badge" style="color: #64748b; font-style: italic;">' . esc_html__( 'Não sincronizado', 'wc-cegid-sync' ) . '</span>';
									}
									?>
								</td>
								<td class="column-cegid-status">
									<?php if ( $has_cegid_link ) : ?>
										<span class="cegid-badge-status status-active" style="background-color: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; border: 1px solid #a7f3d0;"><?php esc_html_e( 'Cadastrado', 'wc-cegid-sync' ); ?></span>
									<?php else : ?>
										<span class="cegid-badge-status status-pending" style="background-color: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; border: 1px solid #fde68a;"><?php esc_html_e( 'Pendente', 'wc-cegid-sync' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="column-sync-time">
									<span class="sync-time-text"><?php echo esc_html( $last_sync_formatted ); ?></span>
									<?php if ( ! empty( $sync_error ) ) : ?>
										<span class="dashicons dashicons-warning text-danger" title="<?php echo esc_attr( $sync_error ); ?>"></span>
									<?php endif; ?>
								</td>
								<td class="column-action text-right">
									<?php if ( ! empty( $sku ) ) : ?>
										<div class="cegid-product-actions" style="display: flex; gap: 5px; justify-content: flex-end; align-items: center; height: 36px;">
											<?php if ( $has_cegid_link ) : ?>
												<button class="button action-btn cegid-sync-stock-btn" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-cegid-tooltip="<?php esc_attr_e( 'Atualiza o estoque deste produto consultando o TOConline.', 'wc-cegid-sync' ); ?>" style="height: 36px !important; line-height: 1 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;">
													<span class="dashicons dashicons-update" style="margin: 0 !important; font-size: 16px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;"></span> <?php esc_html_e( 'Sincronizar Estoque', 'wc-cegid-sync' ); ?>
												</button>
												<button type="button" class="button action-btn cegid-unlink-product-btn" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-sku="<?php echo esc_attr( $sku ); ?>" data-cegid-tooltip="<?php esc_attr_e( 'Desvincula o produto do CEGID (permite recadastrá-lo).', 'wc-cegid-sync' ); ?>" data-cegid-tooltip-pos="left" style="height: 36px !important; line-height: 1 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; color: #dc2626; border-color: #fecaca; background-color: #fff5f5; padding: 0 8px;">
													<span class="dashicons dashicons-editor-unlink" style="margin: 0 !important; font-size: 16px !important;"></span>
												</button>
											<?php else : ?>
												<button class="button button-primary action-btn cegid-create-product-btn" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-cegid-tooltip="<?php esc_attr_e( 'Cadastra o artigo na CEGID (como Produto ou Serviço) e vincula o ID.', 'wc-cegid-sync' ); ?>" style="background: linear-gradient(135deg, #3858e9 0%, #5d3df5 100%) !important; color: #ffffff !important; border: 0 !important; border-radius: 4px !important; height: 36px !important; line-height: 1 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;">
													<span class="dashicons dashicons-plus" style="color: #ffffff !important; margin: 0 !important; font-size: 16px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;"></span> <?php esc_html_e( 'Cadastrar Artigo', 'wc-cegid-sync' ); ?>
												</button>
											<?php endif; ?>
											<span class="spinner cegid-spinner" style="float: none; margin: 0 0 0 5px;"></span>
										</div>
									<?php else : ?>
										<button class="button" disabled title="<?php esc_attr_e( 'Requer SKU para sincronização', 'wc-cegid-sync' ); ?>">
											<?php esc_html_e( 'Sem SKU', 'wc-cegid-sync' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Adiciona a Metabox da Fatura CEGID na página de edição de pedidos do WooCommerce.
	 */
	public static function add_invoice_meta_box() {
		$screens = [ 'shop_order', 'woocommerce_page_wc-orders' ];
		foreach ( $screens as $screen ) {
			add_meta_box(
				'cegid_invoice_meta_box',
				__( 'Fatura CEGID (TOConline)', 'wc-cegid-sync' ),
				[ __CLASS__, 'render_invoice_meta_box' ],
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Renderiza o conteúdo da Metabox da Fatura CEGID.
	 *
	 * @param WP_Post|WC_Order $post_or_order Objeto do post ou pedido do WooCommerce.
	 */
	public static function render_invoice_meta_box( $post_or_order ) {
		$order = ( $post_or_order instanceof WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		if ( ! $order ) {
			return;
		}

		$invoice_id  = $order->get_meta( '_cegid_invoice_id', true );
		$invoice_url = $order->get_meta( '_cegid_invoice_url', true );

		if ( empty( $invoice_id ) ) {
			echo '<p>' . esc_html__( 'Nenhuma fatura emitida para este pedido ainda.', 'wc-cegid-sync' ) . '</p>';
			return;
		}

		echo '<p style="margin-bottom: 12px;"><strong>' . esc_html__( 'ID da Fatura:', 'wc-cegid-sync' ) . '</strong> <code style="padding: 3px 6px; background: #f0f0f1; border-radius: 3px;">' . esc_html( $invoice_id ) . '</code></p>';
		
		if ( ! empty( $invoice_url ) ) {
			echo '<p><a href="' . esc_url( $invoice_url ) . '" class="button button-primary" target="_blank" style="display: inline-flex; align-items: center; gap: 5px;"><span class="dashicons dashicons-pdf" style="margin-top: 3px;"></span> ' . esc_html__( 'Ver Fatura PDF', 'wc-cegid-sync' ) . '</a></p>';
		} else {
			echo '<p class="description">' . esc_html__( 'URL do PDF indisponível.', 'wc-cegid-sync' ) . '</p>';
		}
	}

	/**
	 * Renderiza a aba de gerenciamento de Licença.
	 */
	private static function render_license_tab() {
		$options = Cegid_Settings::get_options();
		$license_key = isset( $options['license_key'] ) ? $options['license_key'] : '';
		$license_status = isset( $options['license_status'] ) ? $options['license_status'] : 'inactive';
		$license_expires = isset( $options['license_expires'] ) ? $options['license_expires'] : '';

		$bypass = defined( 'WC_CEGID_BYPASS_LICENSE' ) && WC_CEGID_BYPASS_LICENSE;
		?>
		<div class="cegid-tab-panel">
			<!-- Caixa de Dicas e Guia Rápido da Aba de Licença -->
			<details class="cegid-tab-help-box" open>
				<summary>
					<span class="cegid-tab-help-title">
						<span class="dashicons dashicons-lightbulb"></span>
						<strong><?php esc_html_e( 'Guia Rápido: Como Funciona a Ativação e Gestão de Licenças', 'wc-cegid-sync' ); ?></strong>
						<span class="cegid-tab-help-badge"><?php esc_html_e( 'Dicas de Uso', 'wc-cegid-sync' ); ?></span>
					</span>
					<span class="cegid-tab-help-toggle-text">
						<span class="dashicons dashicons-arrow-down-alt2"></span> <?php esc_html_e( 'Ocultar / Mostrar Dicas', 'wc-cegid-sync' ); ?>
					</span>
				</summary>
				<div class="cegid-tab-help-content">
					<div class="cegid-help-grid">
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">1</span>
								<span><?php esc_html_e( 'Formato da Chave', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'As chaves são emitidas no Painel Central no formato VP-XXXX-XXXX-XXXX. Cada chave garante acesso seguro às rotas da API.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">2</span>
								<span><?php esc_html_e( 'Vínculo com o Domínio', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Na primeira validação, a chave é associada ao domínio da sua loja. Em caso de migração de servidor, utilize a função "Reset Domínio" no painel central.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">3</span>
								<span><?php esc_html_e( 'Licença de Produção', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Na loja Vale do País, a licença vitalícia de desenvolvimento está ativa permanentemente, garantindo operações ininterruptas.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
					</div>
				</div>
			</details>

			<h3><?php esc_html_e( 'Ativação e Registro da Licença', 'wc-cegid-sync' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Registre a chave de licença contratada para liberar o faturamento automático e a sincronização de existências de estoque.', 'wc-cegid-sync' ); ?></p>

			<hr style="margin: 20px 0; border: 0; border-top: 1px solid #f1f5f9;" />

			<?php if ( $bypass ) : ?>
				<div class="cegid-license-card active-card" style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 20px; border-radius: 8px; max-width: 600px;">
					<h4 style="margin: 0 0 10px 0; color: #166534; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-shield" style="color: #15803d;"></span>
						<?php esc_html_e( 'Licença Vitalícia Ativa (Vale do País)', 'wc-cegid-sync' ); ?>
					</h4>
					<p style="margin: 0 0 12px 0; font-size: 13px; color: #374151; line-height: 1.5;">
						<?php esc_html_e( 'Este plugin está configurado com uma licença vitalícia e ilimitada de desenvolvimento. Todas as operações com a API da CEGID estão 100% ativas e liberadas para este domínio.', 'wc-cegid-sync' ); ?>
					</p>
					<p style="margin: 0; font-size: 11px; color: #6b7280; font-style: italic;">
						<?php esc_html_e( 'Nota técnica: O módulo de validação remota de licenças está implementado sob o capô, pronto para ser ativado para comercialização futura a novos clientes.', 'wc-cegid-sync' ); ?>
					</p>
				</div>
			<?php elseif ( $license_status === 'active' ) : ?>
				<div class="cegid-license-card active-card" style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 20px; border-radius: 8px; max-width: 600px;">
					<h4 style="margin: 0 0 10px 0; color: #166534; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-shield" style="color: #15803d;"></span>
						<?php esc_html_e( 'Licença Ativada com Sucesso!', 'wc-cegid-sync' ); ?>
					</h4>
					<p style="margin: 0 0 8px 0; font-size: 13px; color: #374151;">
						<strong><?php esc_html_e( 'Chave de Licença:', 'wc-cegid-sync' ); ?></strong> 
						<code style="padding: 2px 6px; background: #e2e8f0; border-radius: 4px; font-size: 12px; font-family: monospace; font-weight: bold;"><?php echo esc_html( $license_key ); ?></code>
					</p>
					<p style="margin: 0 0 15px 0; font-size: 13px; color: #374151;">
						<strong><?php esc_html_e( 'Válida para o Domínio:', 'wc-cegid-sync' ); ?></strong> 
						<code><?php echo esc_html( ! empty( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></code>
					</p>
					<?php if ( ! empty( $license_expires ) ) : ?>
						<p style="margin: 0 0 20px 0; font-size: 12px; color: #6b7280; font-style: italic;">
							<?php printf( esc_html__( 'Sua assinatura expira em: %s', 'wc-cegid-sync' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $license_expires ) ) ) ); ?>
						</p>
					<?php endif; ?>

					<button class="button button-secondary action-btn" id="cegid-deactivate-license-btn" data-cegid-tooltip="<?php esc_attr_e( 'Desativa a licença neste domínio para liberar o uso.', 'wc-cegid-sync' ); ?>" style="background: #fef2f2 !important; border-color: #fca5a5 !important; color: #dc2626 !important; font-weight: bold !important;">
						<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Desativar Licença neste Site', 'wc-cegid-sync' ); ?>
					</button>
					<span class="spinner cegid-spinner" id="cegid-license-spinner" style="float: none; margin: 0 0 0 10px;"></span>
				</div>
			<?php else : ?>
				<div class="cegid-license-card inactive-card" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px; max-width: 600px;">
					<h4 style="margin: 0 0 15px 0; color: #334155;">
						<?php esc_html_e( 'Insira sua Chave de Licença', 'wc-cegid-sync' ); ?>
					</h4>
					
					<?php if ( $license_status === 'suspended' ) : ?>
						<div class="notice notice-error inline" style="margin: 0 0 15px 0;"><p><?php esc_html_e( 'Erro: Esta licença foi suspensa pelo administrador.', 'wc-cegid-sync' ); ?></p></div>
					<?php elseif ( $license_status === 'expired' ) : ?>
						<div class="notice notice-warning inline" style="margin: 0 0 15px 0;"><p><?php esc_html_e( 'Erro: Esta licença expirou e precisa ser renovada.', 'wc-cegid-sync' ); ?></p></div>
					<?php endif; ?>

					<div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
						<input type="text" id="cegid-license-key-input" placeholder="VP-XXXX-XXXX-XXXX" value="<?php echo esc_attr( $license_key ); ?>" style="flex-grow: 1; height: 36px; padding: 0 10px; font-family: monospace; font-size: 13px; font-weight: bold; border: 1px solid #d1d5db; border-radius: 4px;" data-cegid-tooltip="<?php esc_attr_e( 'Cole aqui a chave gerada no painel de licenças.', 'wc-cegid-sync' ); ?>" />
						<button class="button button-primary action-btn" id="cegid-activate-license-btn" style="background: linear-gradient(135deg, #3858e9 0%, #5d3df5 100%) !important; color: white !important;" data-cegid-tooltip="<?php esc_attr_e( 'Valida a chave contra o servidor e associa o domínio.', 'wc-cegid-sync' ); ?>">
							<span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Validar e Ativar', 'wc-cegid-sync' ); ?>
						</button>
						<span class="spinner cegid-spinner" id="cegid-license-spinner" style="float: none; margin: 0;"></span>
					</div>
					<p class="description" style="margin: 0;">
						<?php esc_html_e( 'A chave será validada remotamente contra o servidor de licenciamento do Vale do País.', 'wc-cegid-sync' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderiza a aba de Logs de Auditoria do Plugin.
	 */
	private static function render_logs_tab() {
		$logs = Cegid_API_Client::get_logs();
		?>
		<div class="cegid-tab-panel">
			<!-- Caixa de Dicas e Guia Rápido da Aba de Logs -->
			<details class="cegid-tab-help-box" open>
				<summary>
					<span class="cegid-tab-help-title">
						<span class="dashicons dashicons-lightbulb"></span>
						<strong><?php esc_html_e( 'Guia Rápido: Diagnóstico, Testes e Logs de Auditoria', 'wc-cegid-sync' ); ?></strong>
						<span class="cegid-tab-help-badge"><?php esc_html_e( 'Dicas de Uso', 'wc-cegid-sync' ); ?></span>
					</span>
					<span class="cegid-tab-help-toggle-text">
						<span class="dashicons dashicons-arrow-down-alt2"></span> <?php esc_html_e( 'Ocultar / Mostrar Dicas', 'wc-cegid-sync' ); ?>
					</span>
				</summary>
				<div class="cegid-tab-help-content">
					<div class="cegid-help-grid">
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">1</span>
								<span><?php esc_html_e( 'Monitoramento em Tempo Real', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Cada tentativa de login OAuth2, emissão de fatura ou sincronização de estoque registra uma linha detalhada no console escuro.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">2</span>
								<span><?php esc_html_e( 'Inspeção de Payload JSON', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Clique em "🔍 Inspecionar Dados Técnicos & Payload" para ver o JSON exato enviado e o código fiscal retornado pela CEGID.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">3</span>
								<span><?php esc_html_e( 'Copiar Logs para Suporte', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Use o botão "Copiar Logs" para exportar o histórico com 1 clique e colar no WhatsApp da equipe técnica ou do suporte.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
					</div>
				</div>
			</details>

			<div class="cegid-action-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
				<div>
					<h3 style="margin: 0 0 4px 0;"><?php esc_html_e( 'Histórico de Logs e Auditoria da API', 'wc-cegid-sync' ); ?></h3>
					<p class="description" style="margin: 0;"><?php esc_html_e( 'Acompanhe em tempo real todas as requisições, diagnósticos de autenticação OAuth2 e respostas fiscais da CEGID.', 'wc-cegid-sync' ); ?></p>
				</div>
				<div style="display: flex; gap: 8px; align-items: center;">
					<button class="button action-btn" id="cegid-test-connection-btn" style="background: #e0e7ff !important; color: #3730a3 !important; border-color: #c7d2fe !important; font-weight: 600 !important; display: inline-flex; align-items: center; gap: 4px;" data-cegid-tooltip="<?php esc_attr_e( 'Valida imediatamente a autenticação OAuth2 com os servidores da CEGID.', 'wc-cegid-sync' ); ?>">
						<span class="dashicons dashicons-networking"></span> <?php esc_html_e( 'Testar Conexão Agora', 'wc-cegid-sync' ); ?>
					</button>
					<button class="button action-btn" id="cegid-copy-logs-btn" style="background: #f8fafc !important; color: #334155 !important; border-color: #cbd5e1 !important; font-weight: 600 !important; display: inline-flex; align-items: center; gap: 4px;" data-cegid-tooltip="<?php esc_attr_e( 'Copia todo o histórico de logs formatado para a área de transferência.', 'wc-cegid-sync' ); ?>">
						<span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copiar Logs', 'wc-cegid-sync' ); ?>
					</button>
					<button class="button action-btn" id="cegid-clear-logs-btn" style="background: #fef2f2 !important; color: #991b1b !important; border-color: #fecaca !important; font-weight: 600 !important; display: inline-flex; align-items: center; gap: 4px;" data-cegid-tooltip="<?php esc_attr_e( 'Limpa todos os logs registrados no banco de auditoria.', 'wc-cegid-sync' ); ?>">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Limpar Logs', 'wc-cegid-sync' ); ?>
					</button>
					<a href="?page=wc-cegid-sync&tab=logs" class="button action-btn" style="display: inline-flex; align-items: center; gap: 4px;" data-cegid-tooltip="<?php esc_attr_e( 'Recarrega a tela com os logs mais recentes.', 'wc-cegid-sync' ); ?>">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Recarregar', 'wc-cegid-sync' ); ?>
					</a>
					<span class="spinner cegid-spinner" id="cegid-logs-spinner" style="float: none; margin: 0;"></span>
				</div>
			</div>

			<div id="cegid-terminal-container" style="background: #0f172a; border-radius: 8px; border: 1px solid #1e293b; padding: 16px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13px; line-height: 1.6; min-height: 400px; max-height: 700px; overflow-y: auto; box-shadow: inset 0 2px 4px rgba(0,0,0,0.4);">
				<?php if ( empty( $logs ) ) : ?>
					<div style="color: #64748b; text-align: center; padding: 40px 20px;">
						<span class="dashicons dashicons-info" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 10px;"></span>
						<p style="margin: 0; font-size: 14px;"><?php esc_html_e( 'Nenhum registro de log capturado ainda.', 'wc-cegid-sync' ); ?></p>
						<p style="margin: 5px 0 0 0; font-size: 12px; color: #475569;"><?php esc_html_e( 'Clique em "Testar Conexão Agora" ou realize uma operação para gerar novos logs.', 'wc-cegid-sync' ); ?></p>
					</div>
				<?php else : ?>
					<?php foreach ( $logs as $log ) :
						$level       = isset( $log['level'] ) ? $log['level'] : 'info';
						$context     = isset( $log['context'] ) && is_array( $log['context'] ) ? $log['context'] : null;
						$badge_color = '#38bdf8';
						$badge_text  = '[INFO]';
						$msg_color   = '#f1f5f9';

						if ( $level === 'error' ) {
							$badge_color = '#ef4444';
							$badge_text  = '[ERRO]';
							$msg_color   = '#fca5a5';
						} elseif ( $level === 'success' ) {
							$badge_color = '#4ade80';
							$badge_text  = '[SUCESSO]';
							$msg_color   = '#bbf7d0';
						} elseif ( $level === 'warning' ) {
							$badge_color = '#fbbf24';
							$badge_text  = '[ALERTA]';
							$msg_color   = '#fde68a';
						}
						?>
						<div class="cegid-log-entry" style="margin-bottom: 10px; word-break: break-all; border-bottom: 1px solid #1e293b; padding-bottom: 8px;">
							<div>
								<span style="color: #64748b; font-weight: 500;">[<?php echo esc_html( $log['time'] ); ?>]</span>
								<span style="color: <?php echo esc_attr( $badge_color ); ?>; font-weight: 700; margin: 0 4px;"><?php echo esc_html( $badge_text ); ?></span>
								<span style="color: <?php echo esc_attr( $msg_color ); ?>;"><?php echo esc_html( $log['message'] ); ?></span>
							</div>

							<?php if ( ! empty( $context ) ) : ?>
								<details style="margin-top: 6px; background: rgba(15, 23, 42, 0.9); border: 1px solid #334155; border-radius: 6px; padding: 6px 10px;">
									<summary style="cursor: pointer; color: #94a3b8; font-size: 11px; font-weight: 600; outline: none; user-select: none;">
										🔍 <?php esc_html_e( 'Inspecionar Dados Técnicos & Payload', 'wc-cegid-sync' ); ?>
									</summary>
									<pre style="margin: 8px 0 2px 0; font-size: 11px; color: #cbd5e1; background: #020617; padding: 10px; border-radius: 4px; border: 1px solid #1e293b; overflow-x: auto; white-space: pre-wrap; word-break: break-all; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;"><?php echo esc_html( wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
								</details>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
