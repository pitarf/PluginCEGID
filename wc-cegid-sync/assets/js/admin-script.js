/**
 * Comportamento JavaScript para o painel de sincronização CEGID Sync.
 *
 * @package WC_Cegid_Sync
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		// --- SISTEMA DE TOASTS PERSONALIZADO ---
		function showToast(message, type = 'success') {
			// Cria o contêiner de toasts se não existir
			let $container = $('.cegid-toast-container');
			if ($container.length === 0) {
				$container = $('<div class="cegid-toast-container"></div>');
				$('body').append($container);
			}

			// Define ícones dashicons baseados no tipo
			const iconClass = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
			
			// Constrói o HTML do toast
			const $toast = $(`
				<div class="cegid-toast cegid-toast-${type}">
					<span class="dashicons ${iconClass} cegid-toast-icon"></span>
					<div class="cegid-toast-content">${message}</div>
				</div>
			`);

			$container.append($toast);

			// Executa a transição de entrada
			setTimeout(function() {
				$toast.addClass('show');
			}, 50);

			// Agenda a saída e destruição do toast
			setTimeout(function() {
				$toast.removeClass('show');
				setTimeout(function() {
					$toast.remove();
				}, 300);
			}, 4000);
		}

		// --- VERIFICAÇÃO DE FEEDBACK DE RECARREGAMENTO ---
		const urlParams = new URLSearchParams(window.location.search);
		if (urlParams.has('cegid_msg')) {
			const msgType = urlParams.get('cegid_msg');
			if (msgType === 'success') {
				showToast('Tabela de existências atualizada com o WooCommerce!', 'success');
			} else if (msgType === 'orders_success') {
				showToast('Lista de faturas sincronizada com os pedidos concluídos!', 'success');
			}
			// Limpa o parâmetro da URL de forma silenciosa para não poluir o histórico do navegador
			const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + window.location.search.replace(/&?cegid_msg=[^&]*/, '');
			window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
		}

		// --- EMISSÃO MANUAL DE FATURAS ---
		$('.cegid-sync-invoice-btn').on('click', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const $row = $btn.closest('tr');
			const $spinner = $row.find('.cegid-spinner');
			const orderId = $btn.data('order-id');

			// Desativa o botão e exibe o carregamento
			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: cegid_sync_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'cegid_generate_invoice',
					order_id: orderId,
					nonce: cegid_sync_params.nonce
				},
				success: function(response) {
					$spinner.removeClass('is-active');
					
					if (response.success) {
						showToast(response.data.message, 'success');
						// Fade out da linha e remoção elegante da tabela
						$row.css('background-color', '#ecfdf5');
						$row.fadeOut(800, function() {
							$row.remove();
							// Verifica se a tabela ficou vazia
							if ($('table tbody tr').length === 0) {
								location.reload(); // Recarrega para exibir o aviso de vazio
							}
						});
					} else {
						$btn.prop('disabled', false);
						showToast(response.data.message || cegid_sync_params.labels.error, 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					showToast(cegid_sync_params.labels.error, 'error');
				}
			});
		});

		// --- SINCRONIZAÇÃO MANUAL DE ESTOQUES ---
		$(document).on('click', '.cegid-sync-stock-btn', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const $row = $btn.closest('tr');
			const $spinner = $row.find('.cegid-spinner');
			const productId = $btn.data('product-id');
			const $syncCol = $row.find('.column-sync-time');

			// Desativa o botão e exibe carregamento
			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: cegid_sync_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'cegid_sync_stock_manual',
					product_id: productId,
					nonce: cegid_sync_params.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');

					if (response.success) {
						showToast(response.data.message, 'success');
						// Atualiza o estoque na tabela e a data de última sincronização
						if (response.data.is_service) {
							$row.find('.column-stock-wc').html('<span class="na-badge" style="color: #6366f1; font-size: 11px; font-weight: 500;">Serviço</span>');
							$row.find('.column-stock-cegid').html('<span class="na-badge" style="color: #6366f1; font-size: 11px; font-weight: 500;">Serviço</span>');
						} else {
							$row.find('.column-stock-wc').text(response.data.qty !== undefined && response.data.qty !== null ? response.data.qty : '—');
							if (response.data.cegid_stock === 'na' || (typeof response.data.cegid_stock === 'string' && response.data.cegid_stock.indexOf('Não exposto') !== -1)) {
								$row.find('.column-stock-cegid').html('<span class="na-badge" style="color: #64748b; font-style: italic; font-size: 12px;" title="A API pública de artigos do CEGID não disponibilizou a quantidade em inventário deste artigo.">— (Não exposto na API)</span>');
							} else if (response.data.cegid_stock !== undefined && response.data.cegid_stock !== null) {
								$row.find('.column-stock-cegid').text(response.data.cegid_stock);
							} else {
								$row.find('.column-stock-cegid').text(response.data.qty);
							}
						}
						$syncCol.html(`
							<span class="sync-time-text">${response.data.last_sync}</span>
						`);
						$row.css('background-color', '#ecfdf5');
						setTimeout(function() {
							$row.css('background-color', '');
						}, 1200);
					} else {
						showToast(response.data.message || cegid_sync_params.labels.error, 'error');
						// Exibe um ícone de warning com o erro
						$syncCol.find('.dashicons-warning').remove();
						$syncCol.append(`
							<span class="dashicons dashicons-warning text-danger" title="${response.data.message}"></span>
						`);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					showToast(cegid_sync_params.labels.error, 'error');
				}
			});
		});

		// --- SINCRONIZAÇÃO GLOBAL DE ESTOQUE (PULL) ---
		$('#cegid-sync-all-stocks-btn').on('click', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const $spinner = $('#cegid-global-spinner');

			// Desativa o botão e exibe carregamento
			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: cegid_sync_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'cegid_sync_all_stocks',
					nonce: cegid_sync_params.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');

					if (response.success) {
						showToast(response.data.message, 'success');
						// Recarrega a página após 1.5 segundos para atualizar todas as linhas de estoque na tabela
						setTimeout(function() {
							location.reload();
						}, 1500);
					} else {
						showToast(response.data.message || cegid_sync_params.labels.error, 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					showToast(cegid_sync_params.labels.error, 'error');
				}
			});
		});

		// --- ATIVAÇÃO DE LICENÇA ---
		$('#cegid-activate-license-btn').on('click', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const $spinner = $('#cegid-license-spinner');
			const licenseKey = $('#cegid-license-key-input').val();

			if (!licenseKey) {
				showToast('Por favor, insira uma chave de licença.', 'error');
				return;
			}

			// Desativa o botão e exibe carregamento
			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: cegid_sync_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'cegid_activate_license',
					license_key: licenseKey,
					nonce: cegid_sync_params.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');

					if (response.success) {
						showToast(response.data.message, 'success');
						// Recarrega a página após 1.2 segundos para atualizar a UI
						setTimeout(function() {
							location.reload();
						}, 1200);
					} else {
						showToast(response.data.message || 'Erro ao validar licença.', 'error');
						// Se for suspenso ou expirado, recarrega para exibir o erro local no formulário
						setTimeout(function() {
							location.reload();
						}, 1500);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					showToast('Falha na requisição. Verifique o servidor de licenças.', 'error');
				}
			});
		});

		// --- DESATIVAÇÃO DE LICENÇA ---
		$('#cegid-deactivate-license-btn').on('click', function(e) {
			e.preventDefault();

			if (!confirm('Deseja realmente desativar o plugin neste site? Você perderá o acesso ao faturamento e estoque.')) {
				return;
			}

			const $btn = $(this);
			const $spinner = $('#cegid-license-spinner');

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: cegid_sync_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'cegid_deactivate_license',
					nonce: cegid_sync_params.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');

					if (response.success) {
						showToast(response.data.message, 'success');
						setTimeout(function() {
							location.reload();
						}, 1200);
					} else {
						showToast(response.data.message || 'Erro ao desativar.', 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					showToast('Falha na requisição.', 'error');
				}
			});
		});

		// --- RECARREGAR PEDIDOS (SINCRONIZAR COM WOOCOMMERCE) ---
		$('#cegid-sync-orders-btn').on('click', function(e) {
			e.preventDefault();
			const $btn = $(this);
			$btn.prop('disabled', true).addClass('loading');
			showToast('A sincronizar pedidos com o WooCommerce...', 'success');
			setTimeout(function() {
				// Adiciona o parâmetro de sucesso e recarrega
				window.location.href = window.location.pathname + window.location.search + '&cegid_msg=orders_success';
			}, 600);
		});

		// --- SUPRIMIR PEDIDO (MOVER PARA LIXEIRA) ---
		$('.cegid-trash-order-btn').on('click', function(e) {
			e.preventDefault();

			if (!confirm('Deseja realmente mover este pedido para a lixeira do WooCommerce?')) {
				return;
			}

			const $btn = $(this);
			const $row = $btn.closest('tr');
			const $spinner = $row.find('.cegid-spinner');
			const orderId = $btn.data('order-id');

			// Desativa os botões da linha
			$row.find('.button, a.button').addClass('disabled').prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: cegid_sync_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'cegid_trash_order',
					order_id: orderId,
					nonce: cegid_sync_params.nonce
				},
				success: function(response) {
					$spinner.removeClass('is-active');
					
					if (response.success) {
						showToast(response.data.message, 'success');
						$row.css('background-color', '#fee2e2');
						$row.fadeOut(800, function() {
							$row.remove();
							// Se a tabela ficar vazia, recarrega para exibir o aviso
							if ($('table tbody tr').length === 0) {
								location.reload();
							}
						});
					} else {
						$row.find('.button, a.button').removeClass('disabled').prop('disabled', false);
						showToast(response.data.message || 'Erro ao suprimir pedido.', 'error');
					}
				},
				error: function() {
					$row.find('.button, a.button').removeClass('disabled').prop('disabled', false);
					$spinner.removeClass('is-active');
					showToast('Falha na requisição. Pedido não pôde ser suprimido.', 'error');
				}
			});
		});

		// --- RECARREGAR ESTOQUES (SINCRONIZAR TABELA COM WOOCOMMERCE) ---
		$('#cegid-reload-stock-btn').on('click', function(e) {
			e.preventDefault();
			const $btn = $(this);
			$btn.prop('disabled', true).addClass('loading');
			showToast('A atualizar existências com o WooCommerce...', 'success');
			setTimeout(function() {
				// Adiciona o parâmetro de sucesso e recarrega
				window.location.href = window.location.pathname + window.location.search + '&cegid_msg=success';
			}, 600);
		});

		// --- CADASTRAR ARTIGO NA CEGID (INDIVIDUAL) ---
		$(document).on('click', '.cegid-create-product-btn', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const productId = $btn.data('product-id');
			const $row = $('#cegid-product-row-' + productId);
			const $spinner = $row.find('.cegid-spinner');

			$row.find('.button').addClass('disabled').prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: cegid_sync_params.ajax_url,
				type: 'POST',
				data: {
					action: 'cegid_create_product',
					product_id: productId,
					nonce: cegid_sync_params.nonce
				},
				success: function(response) {
					$spinner.removeClass('is-active');
					if (response.success) {
						showToast(response.data.message || 'Artigo cadastrado!', 'success');
						// Atualiza o status visualmente para Cadastrado (verde)
						$row.find('.column-cegid-status').html(
							'<span class="cegid-badge-status status-active" style="background-color: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; border: 1px solid #a7f3d0;">Cadastrado</span>'
						);
						// Substitui o botão "Cadastrar Artigo" pelo botão "Sincronizar Estoque" + botão de desvincular
						const $actionsDiv = $row.find('.cegid-product-actions');
						$actionsDiv.html(
							'<button class="button action-btn cegid-sync-stock-btn" data-product-id="' + productId + '" style="height: 36px !important; line-height: 1 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;">' +
							'<span class="dashicons dashicons-update" style="margin: 0 !important; font-size: 16px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;"></span> Sincronizar Estoque' +
							'</button>' +
							'<button type="button" class="button action-btn cegid-unlink-product-btn" data-product-id="' + productId + '" title="Desvincular do CEGID (permite recadastrar)" style="height: 36px !important; line-height: 1 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; color: #dc2626; border-color: #fecaca; background-color: #fff5f5; padding: 0 8px;">' +
							'<span class="dashicons dashicons-editor-unlink" style="margin: 0 !important; font-size: 16px !important;"></span>' +
							'</button>' +
							'<span class="spinner cegid-spinner" style="float: none; margin: 0 0 0 5px;"></span>'
						);
					} else {
						$row.find('.button').removeClass('disabled').prop('disabled', false);
						showToast(response.data.message || 'Erro ao cadastrar artigo.', 'error');
					}
				},
				error: function() {
					$row.find('.button').removeClass('disabled').prop('disabled', false);
					$spinner.removeClass('is-active');
					showToast('Falha na requisição. Artigo não pôde ser cadastrado.', 'error');
				}
			});
		});

		// --- DESVINCULAR PRODUTO DO CEGID ---
		$(document).on('click', '.cegid-unlink-product-btn', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const productId = $btn.data('product-id');
			const sku = $btn.data('sku') || '';
			const $row = $('#cegid-product-row-' + productId);
			const $spinner = $row.find('.cegid-spinner');

			if (!confirm('Deseja realmente desvincular este item (' + (sku ? 'SKU: ' + sku : 'ID: ' + productId) + ') do CEGID no WooCommerce?\n\nIsso limpará a associação interna local e permitirá que você clique novamente em "Cadastrar Artigo" para reenviá-lo como Serviço ou Produto.')) {
				return;
			}

			$row.find('.button').addClass('disabled').prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: cegid_sync_params.ajax_url,
				type: 'POST',
				data: {
					action: 'cegid_unlink_product',
					product_id: productId,
					nonce: cegid_sync_params.nonce
				},
				success: function(response) {
					$spinner.removeClass('is-active');
					if (response.success) {
						showToast(response.data.message || 'Vínculo removido!', 'success');
						// Atualiza o status visualmente para Pendente
						$row.find('.column-cegid-status').html(
							'<span class="cegid-badge-status status-pending" style="background-color: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; border: 1px solid #fde68a;">Pendente</span>'
						);
						// Limpa o estoque CEGID exibido
						$row.find('.column-stock-cegid').html(
							'<span class="na-badge" style="color: #64748b; font-style: italic;">Não sincronizado</span>'
						);
						// Substitui os botões pelo botão "Cadastrar Artigo"
						const $actionsDiv = $row.find('.cegid-product-actions');
						$actionsDiv.html(
							'<button class="button button-primary action-btn cegid-create-product-btn" data-product-id="' + productId + '" style="background: linear-gradient(135deg, #3858e9 0%, #5d3df5 100%) !important; color: #ffffff !important; border: 0 !important; border-radius: 4px !important; height: 36px !important; line-height: 1 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;">' +
							'<span class="dashicons dashicons-plus" style="color: #ffffff !important; margin: 0 !important; font-size: 16px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;"></span> Cadastrar Artigo' +
							'</button>' +
							'<span class="spinner cegid-spinner" style="float: none; margin: 0 0 0 5px;"></span>'
						);
					} else {
						$row.find('.button').removeClass('disabled').prop('disabled', false);
						showToast(response.data.message || 'Erro ao desvincular produto.', 'error');
					}
				},
				error: function() {
					$row.find('.button').removeClass('disabled').prop('disabled', false);
					$spinner.removeClass('is-active');
					showToast('Falha na requisição ao desvincular o produto.', 'error');
				}
			});
		});

		// --- VERIFICAR VÍNCULOS DE PRODUTOS COM A CEGID (EM LOTE) ---
		$('#cegid-verify-links-btn').on('click', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const $spinner = $('#cegid-global-spinner');

			$btn.prop('disabled', true).addClass('loading');
			$spinner.addClass('is-active');
			showToast('A verificar vínculos de SKUs com a CEGID...', 'success');

			$.ajax({
				url: cegid_sync_params.ajax_url,
				type: 'POST',
				data: {
					action: 'cegid_verify_product_links',
					nonce: cegid_sync_params.nonce
				},
				success: function(response) {
					$spinner.removeClass('is-active');
					$btn.prop('disabled', false).removeClass('loading');
					if (response.success) {
						showToast(response.data.message, 'success');
						// Recarrega a página para atualizar todas as badges
						setTimeout(function() {
							window.location.reload();
						}, 1500);
					} else {
						showToast(response.data.message || 'Erro na verificação de vínculos.', 'error');
					}
				},
				error: function() {
					$spinner.removeClass('is-active');
					$btn.prop('disabled', false).removeClass('loading');
					showToast('Falha ao comunicar com o servidor para verificar vínculos.', 'error');
				}
			});
		});

		// --- AÇÕES EM MASSA: SELEÇÃO E GATILHOS DE PEDIDOS (INVOICES) ---
		$('#cegid-select-all-orders').on('change', function() {
			$('.cegid-order-checkbox').prop('checked', $(this).prop('checked'));
		});

		$('#cegid-bulk-apply-orders').on('click', function(e) {
			e.preventDefault();
			const action = $('#cegid-bulk-action-orders').val();
			if (!action) {
				showToast('Por favor, selecione uma ação em massa.', 'error');
				return;
			}

			const checkedIds = [];
			$('.cegid-order-checkbox:checked').each(function() {
				checkedIds.push($(this).val());
			});

			if (checkedIds.length === 0) {
				showToast('Selecione pelo menos um pedido.', 'error');
				return;
			}

			if (action === 'trash') {
				if (!confirm('Deseja realmente suprimir os ' + checkedIds.length + ' pedidos selecionados e movê-los para a lixeira?')) {
					return;
				}

				const $btn = $(this);
				$btn.prop('disabled', true).addClass('loading');
				showToast('A suprimir pedidos em lote...', 'success');

				$.ajax({
					url: cegid_sync_params.ajax_url,
					type: 'POST',
					data: {
						action: 'cegid_bulk_trash_orders',
						order_ids: checkedIds,
						nonce: cegid_sync_params.nonce
					},
					success: function(response) {
						$btn.prop('disabled', false).removeClass('loading');
						if (response.success) {
							showToast(response.data.message || 'Pedidos suprimidos!', 'success');
							// Remove as linhas da tabela suavemente
							checkedIds.forEach(function(id) {
								$('#cegid-order-row-' + id).fadeOut(400, function() {
									$(this).remove();
									if ($('table tbody tr').length === 0) {
										location.reload();
									}
								});
							});
							$('#cegid-select-all-orders').prop('checked', false);
						} else {
							showToast(response.data.message || 'Erro ao suprimir pedidos em lote.', 'error');
						}
					},
					error: function() {
						$btn.prop('disabled', false).removeClass('loading');
						showToast('Falha na requisição. Não foi possível processar a ação em lote.', 'error');
					}
				});
			}
		});

		// --- AÇÕES EM MASSA: SELEÇÃO E GATILHOS DE PRODUTOS (STOCKS) ---
		$('#cegid-select-all-products').on('change', function() {
			$('.cegid-product-checkbox').prop('checked', $(this).prop('checked'));
		});

		$('#cegid-bulk-apply-products').on('click', function(e) {
			e.preventDefault();
			const action = $('#cegid-bulk-action-products').val();
			if (!action) {
				showToast('Por favor, selecione uma ação em massa.', 'error');
				return;
			}

			const checkedIds = [];
			$('.cegid-product-checkbox:checked').each(function() {
				checkedIds.push($(this).val());
			});

			if (checkedIds.length === 0) {
				showToast('Selecione pelo menos um produto.', 'error');
				return;
			}

			const $btn = $(this);
			const $spinner = $('#cegid-global-spinner');

			if (action === 'create') {
				$btn.prop('disabled', true).addClass('loading');
				$spinner.addClass('is-active');
				showToast('A cadastrar produtos na CEGID em lote...', 'success');

				$.ajax({
					url: cegid_sync_params.ajax_url,
					type: 'POST',
					data: {
						action: 'cegid_bulk_create_products',
						product_ids: checkedIds,
						nonce: cegid_sync_params.nonce
					},
					success: function(response) {
						$btn.prop('disabled', false).removeClass('loading');
						$spinner.removeClass('is-active');
						if (response.success) {
							showToast(response.data.message || 'Produtos cadastrados!', 'success');
							// Recarrega para apresentar todas as badges corretas na tabela
							setTimeout(function() {
								window.location.reload();
							}, 1500);
						} else {
							showToast(response.data.message || 'Erro ao cadastrar produtos em lote.', 'error');
						}
					},
					error: function() {
						$btn.prop('disabled', false).removeClass('loading');
						$spinner.removeClass('is-active');
						showToast('Falha na requisição. Não foi possível cadastrar em lote.', 'error');
					}
				});
			} else if (action === 'sync') {
				$btn.prop('disabled', true).addClass('loading');
				$spinner.addClass('is-active');
				showToast('A sincronizar estoques com a CEGID em lote...', 'success');

				$.ajax({
					url: cegid_sync_params.ajax_url,
					type: 'POST',
					data: {
						action: 'cegid_bulk_sync_stocks',
						product_ids: checkedIds,
						nonce: cegid_sync_params.nonce
					},
					success: function(response) {
						$btn.prop('disabled', false).removeClass('loading');
						$spinner.removeClass('is-active');
						if (response.success) {
							showToast(response.data.message || 'Sincronização em lote concluída!', 'success');
							// Recarrega para mostrar os novos saldos na tabela
							setTimeout(function() {
								window.location.reload();
							}, 1500);
						} else {
							showToast(response.data.message || 'Erro na sincronização em lote.', 'error');
						}
					},
					error: function() {
						$btn.prop('disabled', false).removeClass('loading');
						$spinner.removeClass('is-active');
						showToast('Falha na requisição. Não foi possível sincronizar em lote.', 'error');
					}
				});
			}
		});

		// --- FILTRAR PRODUTOS POR STATUS DE CADASTRO (PENDENTE / CADASTRADO) ---
		$('#cegid-product-filter-status').on('change', function() {
			const filter = $(this).val();
			const $rows = $('#cegid-sync-all-stocks-btn').closest('.cegid-tab-panel').find('table tbody tr');

			if (!filter) {
				$rows.show();
			} else if (filter === 'pending') {
				$rows.each(function() {
					if ($(this).find('.cegid-badge-status.status-pending').length > 0) {
						$(this).show();
					} else {
						$(this).hide();
					}
				});
			} else if (filter === 'active') {
				$rows.each(function() {
					if ($(this).find('.cegid-badge-status.status-active').length > 0) {
						$(this).show();
					} else {
						$(this).hide();
					}
				});
			}
		});

		// --- TESTAR CONEXÃO MANUAL COM A API DA CEGID ---
		$('#cegid-test-connection-btn').on('click', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const $spinner = $('#cegid-logs-spinner');

			$btn.prop('disabled', true).addClass('loading');
			$spinner.addClass('is-active');
			showToast('A testar conexão com a API da CEGID...', 'info');

			$.ajax({
				url: cegid_sync_params.ajax_url,
				type: 'POST',
				data: {
					action: 'cegid_test_connection',
					nonce: cegid_sync_params.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false).removeClass('loading');
					$spinner.removeClass('is-active');
					if (response.success) {
						showToast(response.data.message || 'Conexão realizada com sucesso!', 'success');
					} else {
						showToast(response.data.message || 'Falha na conexão com a CEGID.', 'error');
					}
					setTimeout(function() {
						location.reload();
					}, 1500);
				},
				error: function() {
					$btn.prop('disabled', false).removeClass('loading');
					$spinner.removeClass('is-active');
					showToast('Falha na requisição ao testar conexão.', 'error');
				}
			});
		});

		// --- LIMPAR LOGS DE AUDITORIA ---
		$('#cegid-clear-logs-btn').on('click', function(e) {
			e.preventDefault();
			if (!confirm('Deseja realmente limpar todos os logs de auditoria gravados?')) {
				return;
			}

			const $btn = $(this);
			const $spinner = $('#cegid-logs-spinner');

			$btn.prop('disabled', true).addClass('loading');
			$spinner.addClass('is-active');

			$.ajax({
				url: cegid_sync_params.ajax_url,
				type: 'POST',
				data: {
					action: 'cegid_clear_logs',
					nonce: cegid_sync_params.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false).removeClass('loading');
					$spinner.removeClass('is-active');
					if (response.success) {
						showToast(response.data.message || 'Logs limpos com sucesso!', 'success');
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						showToast(response.data.message || 'Erro ao limpar logs.', 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false).removeClass('loading');
					$spinner.removeClass('is-active');
					showToast('Falha na requisição ao limpar logs.', 'error');
				}
			});
		});
		// --- COPIAR LOGS DE AUDITORIA ---
		$('#cegid-copy-logs-btn').on('click', function(e) {
			e.preventDefault();
			const $entries = $('#cegid-terminal-container .cegid-log-entry');
			if ($entries.length === 0) {
				showToast('Nenhum log disponível para cópia.', 'warning');
				return;
			}

			let fullLogText = '=== LOGS DE AUDITORIA WC CEGID SYNC ===\n';
			fullLogText += 'Exportado em: ' + new Date().toLocaleString() + '\n\n';

			$entries.each(function() {
				const time = $(this).find('span').eq(0).text().trim();
				const badge = $(this).find('span').eq(1).text().trim();
				const msg = $(this).find('span').eq(2).text().trim();
				const $pre = $(this).find('pre');

				fullLogText += time + ' ' + badge + ' ' + msg + '\n';
				if ($pre.length > 0) {
					fullLogText += 'DETALHES / PAYLOAD:\n' + $pre.text().trim() + '\n';
				}
				fullLogText += '--------------------------------------------------\n';
			});

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(fullLogText).then(function() {
					showToast('Logs completos copiados para a área de transferência!', 'success');
				}).catch(function() {
					copyFallback(fullLogText);
				});
			} else {
				copyFallback(fullLogText);
			}

			function copyFallback(text) {
				const $temp = $('<textarea>');
				$('body').append($temp);
				$temp.val(text).select();
				try {
					document.execCommand('copy');
					showToast('Logs completos copiados para a área de transferência!', 'success');
				} catch (err) {
					showToast('Não foi possível copiar os logs automaticamente.', 'error');
				}
				$temp.remove();
			}
		});
	});

})(jQuery);
