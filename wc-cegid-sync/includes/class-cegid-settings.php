<?php
/**
 * Classe responsável pelas configurações do plugin no painel administrativo.
 *
 * @package WC_Cegid_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cegid_Settings {

	/**
	 * Inicializa os hooks da página de configurações.
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_settings_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
	}

	/**
	 * Adiciona o submenu de configurações sob o menu principal do CEGID Sync.
	 */
	public static function add_settings_menu() {
		add_submenu_page(
			'wc-cegid-sync',
			__( 'Configurações da API', 'wc-cegid-sync' ),
			__( 'Configurações', 'wc-cegid-sync' ),
			'manage_options',
			'wc-cegid-sync-settings',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	/**
	 * Registra as opções do plugin e seus respectivos campos na Settings API do WordPress.
	 */
	public static function register_settings() {
		register_setting( 'wc_cegid_sync_group', 'wc_cegid_sync_settings', [ __CLASS__, 'sanitize_settings' ] );

		add_settings_section(
			'cegid_api_section',
			__( 'Configurações de Integração com a API CEGID (TOConline)', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_section_info' ],
			'wc-cegid-sync-settings'
		);

		add_settings_field(
			'sandbox',
			__( 'Ambiente Sandbox / Testes', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_sandbox_field' ],
			'wc-cegid-sync-settings',
			'cegid_api_section'
		);

		add_settings_field(
			'client_id',
			__( 'Client ID', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_text_field' ],
			'wc-cegid-sync-settings',
			'cegid_api_section',
			[ 'field' => 'client_id' ]
		);

		add_settings_field(
			'client_secret',
			__( 'Client Secret', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_password_field' ],
			'wc-cegid-sync-settings',
			'cegid_api_section',
			[ 'field' => 'client_secret' ]
		);

		add_settings_field(
			'username',
			__( 'Utilizador / E-mail', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_text_field' ],
			'wc-cegid-sync-settings',
			'cegid_api_section',
			[ 'field' => 'username' ]
		);

		add_settings_field(
			'password',
			__( 'Senha', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_password_field' ],
			'wc-cegid-sync-settings',
			'cegid_api_section',
			[ 'field' => 'password' ]
		);

		add_settings_field(
			'nif_empresa',
			__( 'NIF da Empresa', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_text_field' ],
			'wc-cegid-sync-settings',
			'cegid_api_section',
			[ 'field' => 'nif_empresa' ]
		);

		add_settings_field(
			'api_url',
			__( 'URL Base da API', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_text_field' ],
			'wc-cegid-sync-settings',
			'cegid_api_section',
			[ 'field' => 'api_url', 'description' => 'Deixe em branco para usar o padrão. Produção: https://api3.business-pt.cegid.cloud. Sandbox: https://sandbox-api-cwb.cldware.com' ]
		);

		add_settings_field(
			'auth_url',
			__( 'URL de Autenticação (OAuth)', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_text_field' ],
			'wc-cegid-sync-settings',
			'cegid_api_section',
			[ 'field' => 'auth_url', 'description' => 'Deixe em branco para usar o padrão. Produção: https://app3.business-pt.cegid.cloud/oauth. Sandbox: https://sandbox-api-cwb.cldware.com/oauth' ]
		);

		add_settings_section(
			'cegid_documents_section',
			__( 'Configurações de Faturação', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_documents_section_info' ],
			'wc-cegid-sync-settings'
		);

		add_settings_field(
			'document_type',
			__( 'Tipo de Documento Padrão', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_document_type_field' ],
			'wc-cegid-sync-settings',
			'cegid_documents_section'
		);

		add_settings_field(
			'document_series_prefix',
			__( 'Prefixo da Série de Documentos', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_text_field' ],
			'wc-cegid-sync-settings',
			'cegid_documents_section',
			[ 'field' => 'document_series_prefix', 'description' => 'Série padrão do TOConline. Ex: OLIAK (obrigatório para a olivicultura do Vale do País; a série CONAK não deve ser usada aqui)' ]
		);

		add_settings_field(
			'auto_finalize',
			__( 'Emitir e Finalizar Automaticamente', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_auto_finalize_field' ],
			'wc-cegid-sync-settings',
			'cegid_documents_section'
		);

		add_settings_section(
			'cegid_products_section',
			__( 'Configurações de Artigos e Produtos', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_products_section_info' ],
			'wc-cegid-sync-settings'
		);

		add_settings_field(
			'product_tax_descriptor',
			__( 'Descritor de IVA Padrão', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_text_field' ],
			'wc-cegid-sync-settings',
			'cegid_products_section',
			[ 'field' => 'product_tax_descriptor', 'description' => 'Código de IVA padrão para os produtos criados. Ex: IVA-M23 (Taxa Normal 23% Portugal), IVA-M06 (Taxa Reduzida 6%).' ]
		);

		add_settings_field(
			'product_unit_of_measure',
			__( 'Unidade de Medida Padrão', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_text_field' ],
			'wc-cegid-sync-settings',
			'cegid_products_section',
			[ 'field' => 'product_unit_of_measure', 'description' => 'Unidade de medida a enviar. Ex: unidade, litro, kg.' ]
		);

		add_settings_field(
			'product_exemption_reason',
			__( 'Motivo de Isenção de IVA Padrão', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_text_field' ],
			'wc-cegid-sync-settings',
			'cegid_products_section',
			[ 'field' => 'product_exemption_reason', 'description' => 'Preencha apenas se a taxa de IVA padrão for isenta (ex: menção legal de isenção de IVA).' ]
		);

		add_settings_field(
			'product_auto_sync',
			__( 'Enviar Automaticamente ao Publicar', 'wc-cegid-sync' ),
			[ __CLASS__, 'render_product_auto_sync_field' ],
			'wc-cegid-sync-settings',
			'cegid_products_section'
		);
	}

	/**
	 * Renderiza a descrição da seção de API.
	 */
	public static function render_section_info() {
		echo '<p>' . esc_html__( 'Insira os dados da API obtidos em sua conta TOConline (Empresa > Configurações > Dados API).', 'wc-cegid-sync' ) . '</p>';
	}

	/**
	 * Renderiza a descrição da seção de documentos.
	 */
	public static function render_documents_section_info() {
		echo '<p>' . esc_html__( 'Configure como as faturas serão criadas na CEGID a partir das vendas do WooCommerce.', 'wc-cegid-sync' ) . '</p>';
	}

	/**
	 * Renderiza a descrição da seção de produtos.
	 */
	public static function render_products_section_info() {
		echo '<p>' . esc_html__( 'Configure as definições fiscais padrão e o comportamento de sincronização de artigos e produtos com o TOConline.', 'wc-cegid-sync' ) . '</p>';
	}

	/**
	 * Obtém todas as configurações salvas ou valores padrão.
	 */
	public static function get_options() {
		$defaults = [
			'sandbox'                  => 'yes',
			'client_id'                => '',
			'client_secret'            => '',
			'username'                 => '',
			'password'                 => '',
			'nif_empresa'              => '',
			'api_url'                  => '',
			'auth_url'                 => '',
			'license_key'              => '',
			'license_status'           => 'inactive',
			'license_expires'          => '',
			'document_type'            => 'FT',
			'document_series_prefix'   => 'OLIAK',
			'auto_finalize'            => 'no',
			'product_tax_descriptor'   => 'IVA-M23',
			'product_unit_of_measure'  => 'unidade',
			'product_exemption_reason' => '',
			'product_auto_sync'        => 'no',
		];
		$saved = get_option( 'wc_cegid_sync_settings', [] );
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Renderiza campo de texto geral.
	 */
	public static function render_text_field( $args ) {
		$options = self::get_options();
		$field = $args['field'];
		$desc = isset( $args['description'] ) ? $args['description'] : '';
		$value = isset( $options[ $field ] ) ? $options[ $field ] : '';
		?>
		<input type="text" name="wc_cegid_sync_settings[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<?php if ( $desc ) : ?>
			<p class="description"><?php echo esc_html( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renderiza campo de senha.
	 */
	public static function render_password_field( $args ) {
		$options = self::get_options();
		$field = $args['field'];
		$value = isset( $options[ $field ] ) ? $options[ $field ] : '';
		?>
		<input type="password" name="wc_cegid_sync_settings[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<?php
	}

	/**
	 * Renderiza o checkbox do ambiente Sandbox.
	 */
	public static function render_sandbox_field() {
		$options = self::get_options();
		$value = isset( $options['sandbox'] ) ? $options['sandbox'] : 'no';
		?>
		<input type="checkbox" name="wc_cegid_sync_settings[sandbox]" value="yes" <?php checked( $value, 'yes' ); ?> />
		<span class="description"><?php esc_html_e( 'Ativar para usar a API de testes (sandbox-api-cwb.cldware.com). Desative para produção.', 'wc-cegid-sync' ); ?></span>
		<?php
	}

	/**
	 * Renderiza a opção de auto-finalizar.
	 */
	public static function render_auto_finalize_field() {
		$options = self::get_options();
		$value = isset( $options['auto_finalize'] ) ? $options['auto_finalize'] : 'no';
		?>
		<input type="checkbox" name="wc_cegid_sync_settings[auto_finalize]" value="yes" <?php checked( $value, 'yes' ); ?> />
		<span class="description"><?php esc_html_e( 'Se ativado, a fatura será emitida diretamente como documento oficial finalizado na CEGID. Se desativado, será criada como rascunho.', 'wc-cegid-sync' ); ?></span>
		<?php
	}

	/**
	 * Renderiza a opção de auto-sincronizar produtos.
	 */
	public static function render_product_auto_sync_field() {
		$options = self::get_options();
		$value = isset( $options['product_auto_sync'] ) ? $options['product_auto_sync'] : 'no';
		?>
		<input type="checkbox" name="wc_cegid_sync_settings[product_auto_sync]" value="yes" <?php checked( $value, 'yes' ); ?> />
		<span class="description"><?php esc_html_e( 'Se ativado, sempre que criar ou atualizar um produto no WooCommerce, ele será cadastrado automaticamente na CEGID.', 'wc-cegid-sync' ); ?></span>
		<?php
	}

	/**
	 * Renderiza o seletor do tipo de documento.
	 */
	public static function render_document_type_field() {
		$options = self::get_options();
		$value = isset( $options['document_type'] ) ? $options['document_type'] : 'FT';
		?>
		<select name="wc_cegid_sync_settings[document_type]">
			<option value="FT" <?php selected( $value, 'FT' ); ?>><?php esc_html_e( 'Fatura (FT)', 'wc-cegid-sync' ); ?></option>
			<option value="FS" <?php selected( $value, 'FS' ); ?>><?php esc_html_e( 'Fatura Simplificada (FS)', 'wc-cegid-sync' ); ?></option>
			<option value="FR" <?php selected( $value, 'FR' ); ?>><?php esc_html_e( 'Fatura-Recibo (FR)', 'wc-cegid-sync' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Sanitiza as configurações antes de salvar no banco de dados.
	 */
	public static function sanitize_settings( $input ) {
		$sanitized = [];
		$existing  = self::get_options();

		$sanitized['sandbox']                = ( isset( $input['sandbox'] ) && $input['sandbox'] === 'yes' ) ? 'yes' : 'no';
		$sanitized['client_id']              = sanitize_text_field( isset( $input['client_id'] ) ? $input['client_id'] : '' );
		
		// Preserva client_secret caso o campo seja submetido em branco
		if ( empty( $input['client_secret'] ) && ! empty( $existing['client_secret'] ) ) {
			$sanitized['client_secret'] = $existing['client_secret'];
		} else {
			$sanitized['client_secret'] = isset( $input['client_secret'] ) ? sanitize_text_field( trim( $input['client_secret'] ) ) : '';
		}

		$sanitized['username']               = sanitize_email( isset( $input['username'] ) ? $input['username'] : '' );

		// Preserva password caso o campo seja submetido em branco
		if ( empty( $input['password'] ) && ! empty( $existing['password'] ) ) {
			$sanitized['password'] = $existing['password'];
		} else {
			$sanitized['password'] = isset( $input['password'] ) ? trim( (string) $input['password'] ) : '';
		}

		$sanitized['nif_empresa']            = sanitize_text_field( isset( $input['nif_empresa'] ) ? $input['nif_empresa'] : '' );
		$sanitized['api_url']                = esc_url_raw( trim( isset( $input['api_url'] ) ? $input['api_url'] : '' ) );
		$sanitized['auth_url']               = esc_url_raw( trim( isset( $input['auth_url'] ) ? $input['auth_url'] : '' ) );
		$sanitized['license_key']            = sanitize_text_field( isset( $input['license_key'] ) ? $input['license_key'] : '' );
		$sanitized['license_status']         = sanitize_text_field( isset( $input['license_status'] ) ? $input['license_status'] : '' );
		$sanitized['license_expires']        = sanitize_text_field( isset( $input['license_expires'] ) ? $input['license_expires'] : '' );
		$sanitized['document_type']          = ( isset( $input['document_type'] ) && in_array( $input['document_type'], [ 'FT', 'FS', 'FR' ], true ) ) ? $input['document_type'] : 'FT';
		$sanitized['document_series_prefix'] = sanitize_text_field( isset( $input['document_series_prefix'] ) ? $input['document_series_prefix'] : '' );
		$sanitized['auto_finalize']          = ( isset( $input['auto_finalize'] ) && $input['auto_finalize'] === 'yes' ) ? 'yes' : 'no';
		$sanitized['product_tax_descriptor']   = sanitize_text_field( isset( $input['product_tax_descriptor'] ) ? $input['product_tax_descriptor'] : '' );
		$sanitized['product_unit_of_measure']  = sanitize_text_field( isset( $input['product_unit_of_measure'] ) ? $input['product_unit_of_measure'] : '' );
		$sanitized['product_exemption_reason'] = sanitize_text_field( isset( $input['product_exemption_reason'] ) ? $input['product_exemption_reason'] : '' );
		$sanitized['product_auto_sync']        = ( isset( $input['product_auto_sync'] ) && $input['product_auto_sync'] === 'yes' ) ? 'yes' : 'no';

		return $sanitized;
	}

	/**
	 * Verifica se a licença do plugin está ativa ou se o bypass comercial está ativado.
	 *
	 * @return bool True se a licença estiver ativa ou em modo de bypass comercial, false caso contrário.
	 */
	public static function is_license_active() {
		if ( defined( 'WC_CEGID_BYPASS_LICENSE' ) && WC_CEGID_BYPASS_LICENSE ) {
			return true;
		}
		$options = self::get_options();
		return isset( $options['license_status'] ) && $options['license_status'] === 'active';
	}

	/**
	 * Renderiza a página administrativa de opções.
	 */
	public static function render_settings_page() {
		?>
		<div class="wrap cegid-sync-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<!-- Caixa de Dicas e Guia Rápido da Página de Configurações -->
			<details class="cegid-tab-help-box" open style="margin-top: 15px;">
				<summary>
					<span class="cegid-tab-help-title">
						<span class="dashicons dashicons-lightbulb"></span>
						<strong><?php esc_html_e( 'Guia Rápido: Como Preencher as Configurações da API CEGID (TOConline)', 'wc-cegid-sync' ); ?></strong>
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
								<span><?php esc_html_e( 'Credenciais OAuth2', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Obtenha o Client ID e Client Secret no portal do TOConline em Empresa > Configurações > API. Insira o Utilizador (e-mail) e Senha autorizados.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">2</span>
								<span><?php esc_html_e( 'Parâmetros de Faturamento', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Defina a Série de Faturação (ex: OLIAK) e o Tipo de Documento fiscal (FT para Fatura, FR para Fatura-Recibo ou FS para Fatura Simplificada).', 'wc-cegid-sync' ); ?>
							</p>
						</div>
						<div class="cegid-help-step-card">
							<div class="cegid-help-step-header">
								<span class="cegid-help-step-num">3</span>
								<span><?php esc_html_e( 'Ambiente de Produção', 'wc-cegid-sync' ); ?></span>
							</div>
							<p class="cegid-help-step-desc">
								<?php esc_html_e( 'Mantenha o campo Sandbox desmarcado para emitir faturas com valor fiscal real. O cluster oficial de produção é https://api3.business-pt.cegid.cloud.', 'wc-cegid-sync' ); ?>
							</p>
						</div>
					</div>
				</div>
			</details>

			<form action="options.php" method="POST">
				<?php
				settings_fields( 'wc_cegid_sync_group' );
				do_settings_sections( 'wc-cegid-sync-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
