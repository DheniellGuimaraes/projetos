<?php
/**
 * Plugin Name: RMA Governance
 * Description: Workflow de 3 aceites para entidades RMA com logs de auditoria.
 * Version: 0.4.3
 * Author: RMA
 */

if (! defined('ABSPATH')) {
    exit;
}

final class RMA_Governance {
    private const CPT = 'rma_entidade';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('init', [$this, 'handle_entity_document_upload']);
        add_action('wp_footer', [$this, 'inject_entity_dashboard_governance_menu'], 99);
        add_action('wp_footer', [$this, 'inject_entity_dashboard_governance_content'], 100);
        add_action('wp_footer', [$this, 'inject_entity_notifications_dropdown'], 101);

        add_action('wp_ajax_rma_mark_entity_notifications_read', [$this, 'ajax_mark_entity_notifications_read']);

        add_action('rma/entity_approved', [$this, 'on_entity_approved'], 10, 2);
        add_action('rma/entity_rejected', [$this, 'on_entity_rejected'], 10, 2);
        add_action('rma/entity_resubmitted', [$this, 'on_entity_resubmitted'], 10, 1);
        add_action('rma/entity_finance_updated', [$this, 'on_entity_finance_updated'], 10, 3);

        add_shortcode('rma_governanca_entidade_documentos', [$this, 'render_entity_governance_documents']);
        add_shortcode('rma_governanca_entidade_pendencias', [$this, 'render_entity_governance_pendencias']);
        add_shortcode('rma_governanca_entidade_status', [$this, 'render_entity_governance_status']);
        add_shortcode('rma_governanca_entidade_upload', [$this, 'render_entity_governance_upload']);
    }

    public function register_admin_page(): void {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            'Governança RMA',
            'Governança',
            'edit_others_posts',
            'rma-governance-audit',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void {
        if (! current_user_can('edit_others_posts')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        $this->handle_admin_actions();

        $status_filter = isset($_GET['status_filter']) ? sanitize_key((string) wp_unslash($_GET['status_filter'])) : '';
        $allowed_status_filters = ['', 'pendente', 'em_analise', 'recusado', 'aprovado'];
        if (! in_array($status_filter, $allowed_status_filters, true)) {
            $status_filter = '';
        }

        $search_filter = isset($_GET['search']) ? sanitize_text_field((string) wp_unslash($_GET['search'])) : '';

        $all_rows = $this->load_governance_rows();
        $rows = $this->load_governance_rows([
            'status_filter' => $status_filter,
            'search_filter' => $search_filter,
        ]);
        $summary = $this->build_summary($all_rows);

        $selected_entity_id = isset($_GET['entity_id']) ? (int) $_GET['entity_id'] : 0;
        $selected = $selected_entity_id > 0 ? $this->load_entity_details($selected_entity_id) : null;

        $notice = isset($_GET['rma_notice']) ? sanitize_text_field(rawurldecode((string) wp_unslash($_GET['rma_notice']))) : '';
        $notice_type = isset($_GET['rma_notice_type']) ? sanitize_key((string) wp_unslash($_GET['rma_notice_type'])) : '';
        if ($selected_entity_id > 0 && $selected === null && $notice === '') {
            $notice = 'A entidade selecionada não foi encontrada para gerenciamento.';
            $notice_type = 'error';
        }
        ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Maven+Pro:wght@600&display=swap" rel="stylesheet">
        <style>
            .rma-gov-wrap,
            .rma-gov-wrap * {
                font-family: 'Maven Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
                font-weight: 600 !important;
                box-sizing: border-box;
            }

            .rma-gov-wrap {
                --rma-bg: linear-gradient(150deg, #fcfdff 0%, #f2f7ff 100%);
                --rma-card: rgba(255, 255, 255, 0.86);
                --rma-border: rgba(255, 255, 255, 0.78);
                --rma-shadow: 0 24px 60px rgba(15, 23, 42, 0.11);
                --rma-text: #162538;
                --rma-muted: #5b6a7c;
                --rma-approve: #0f9f6f;
                --rma-reject: #ce3f4a;
                --rma-pending: #d78d11;
                margin-top: 14px;
                border-radius: 24px;
                padding: 28px;
                border: 1px solid rgba(255,255,255,0.9);
                background: var(--rma-bg);
                box-shadow: var(--rma-shadow);
                color: var(--rma-text);
            }

            .rma-gov-head { margin-bottom: 16px; }
            .rma-filter-bar { display:flex; flex-wrap:wrap; gap:8px; margin: 12px 0 18px; }
            .rma-filter-bar input, .rma-filter-bar select { border:1px solid rgba(15,23,42,.2); border-radius:10px; padding:8px 10px; background:#fff; min-width:170px; }
            .rma-filter-bar button, .rma-filter-bar a { border-radius:10px; padding:8px 11px; text-decoration:none; border:none; cursor:pointer; }
            .rma-filter-bar button { background:#162538; color:#fff; }
            .rma-filter-bar a { background:rgba(15,23,42,.08); color:#162538; }
            #rma-governance-detail { scroll-margin-top: 80px; }
            .rma-gov-head h1 { margin: 0 0 8px 0 !important; font-size: 30px !important; }
            .rma-gov-head p { margin: 0 !important; color: var(--rma-muted); }

            .rma-gov-notice {
                border-radius: 12px;
                padding: 12px 14px;
                margin: 12px 0 18px;
                border: 1px solid transparent;
            }
            .rma-gov-notice.success { background: rgba(15,159,111,.11); border-color: rgba(15,159,111,.3); color: #0d7d58; }
            .rma-gov-notice.error { background: rgba(206,63,74,.1); border-color: rgba(206,63,74,.24); color: #b2303c; }

            .rma-gov-cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
                gap: 12px;
                margin-bottom: 22px;
            }
            .rma-gov-card {
                border-radius: 16px;
                padding: 14px;
                background: var(--rma-card);
                border: 1px solid var(--rma-border);
                box-shadow: 0 10px 30px rgba(15,23,42,0.07);
                backdrop-filter: blur(14px);
                -webkit-backdrop-filter: blur(14px);
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .rma-gov-card .icon {
                width: 30px;
                height: 30px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #fff;
                border: 1px solid rgba(15,23,42,.08);
            }
            .rma-gov-card strong { display: block; font-size: 22px; line-height: 1.1; }
            .rma-muted { color: var(--rma-muted); font-size: 12px; }

            .rma-gov-table-wrap,
            .rma-gov-detail {
                background: var(--rma-card);
                border: 1px solid var(--rma-border);
                border-radius: 18px;
                box-shadow: 0 10px 30px rgba(15,23,42,.06);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
            }
            .rma-gov-table-wrap { overflow: auto; margin-bottom: 18px; }

            table.rma-gov-table,
            table.rma-gov-nested {
                width: 100%;
                border-collapse: collapse;
                background: transparent !important;
            }
            .rma-gov-table th,
            .rma-gov-table td,
            .rma-gov-nested th,
            .rma-gov-nested td {
                border-bottom: 1px solid rgba(15,23,42,.08) !important;
                padding: 12px;
                text-align: left;
                vertical-align: top;
            }
            .rma-gov-table th,
            .rma-gov-nested th {
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: .05em;
                color: var(--rma-muted);
            }

            .rma-badge {
                border-radius: 999px;
                padding: 6px 11px;
                font-size: 12px;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                border: 1px solid transparent;
            }
            .rma-badge.aprovado { color: var(--rma-approve); background: rgba(15,159,111,.12); border-color: rgba(15,159,111,.25); }
            .rma-badge.recusado { color: var(--rma-reject); background: rgba(206,63,74,.12); border-color: rgba(206,63,74,.25); }
            .rma-badge.em_analise,
            .rma-badge.pendente { color: var(--rma-pending); background: rgba(215,141,17,.12); border-color: rgba(215,141,17,.25); }

            .rma-link {
                color: #1559d6 !important;
                text-decoration: none !important;
            }
            .rma-link:hover { text-decoration: underline !important; }

            .rma-gov-detail {
                padding: 18px;
                display: grid;
                gap: 14px;
            }
            .rma-gov-detail-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 12px;
            }
            .rma-detail-card {
                background: rgba(255,255,255,.9);
                border: 1px solid rgba(15,23,42,.08);
                border-radius: 14px;
                padding: 12px;
            }
            .rma-detail-card h3 { margin: 0 0 8px; font-size: 15px; }
            .rma-actions form { display: grid; gap: 8px; margin-bottom: 10px; }
            .rma-actions textarea,
            .rma-actions input[type="text"] {
                width: 100%;
                border: 1px solid rgba(15,23,42,.2);
                border-radius: 10px;
                padding: 8px 10px;
                background: #fff;
                font-size: 13px;
            }
            .rma-actions button {
                border: none;
                border-radius: 10px;
                padding: 9px 12px;
                color: #fff;
                cursor: pointer;
                width: fit-content;
            }
            .rma-btn-approve { background: #00bfa5; }
            .rma-btn-reject { background: #e8063c; }
            .rma-btn-resubmit { background: linear-gradient(135deg, #d78d11 0%, #bd7a0f 100%); }

            .rma-timeline { margin: 0; padding-left: 18px; }
            .rma-timeline li { margin: 0 0 8px 0; color: var(--rma-text); }
        </style>

        <div class="wrap rma-gov-wrap">
            <div class="rma-gov-head">
                <h1>Governança RMA</h1>
                <p>Status operacionais e gestão prática de documentos e auditoria.</p>
            </div>

            <?php if ($notice !== '') : ?>
                <div class="rma-gov-notice <?php echo esc_attr($notice_type === 'success' ? 'success' : 'error'); ?>">
                    <?php echo esc_html($notice); ?>
                </div>
            <?php endif; ?>

            <form method="get" class="rma-filter-bar">
                <input type="hidden" name="post_type" value="<?php echo esc_attr(self::CPT); ?>">
                <input type="hidden" name="page" value="rma-governance-audit">
                <select name="status_filter">
                    <option value="" <?php selected($status_filter, ''); ?>>Todos os status</option>
                    <option value="pendente" <?php selected($status_filter, 'pendente'); ?>>Aguardando</option>
                    <option value="em_analise" <?php selected($status_filter, 'em_analise'); ?>>Em análise</option>
                    <option value="recusado" <?php selected($status_filter, 'recusado'); ?>>Recusado</option>
                    <option value="aprovado" <?php selected($status_filter, 'aprovado'); ?>>Aprovado</option>
                </select>
                <input type="text" name="search" placeholder="Buscar por entidade ou ID" value="<?php echo esc_attr($search_filter); ?>">
                <button type="submit">Filtrar</button>
                <a href="<?php echo esc_url($this->build_admin_url()); ?>">Limpar</a>
            </form>

            <div class="rma-gov-cards">
                <?php echo $this->summary_card('approve', 'Aprovadas', $summary['approved']); ?>
                <?php echo $this->summary_card('reject', 'Recusadas', $summary['rejected']); ?>
                <?php echo $this->summary_card('pending', 'Aguardando análise', $summary['pending']); ?>
                <?php echo $this->summary_card('document', 'Arquivos privados', $summary['documents']); ?>
            </div>

            <div id="rma-governance-detail">
                <?php if ($selected !== null) : ?>
                    <div class="rma-gov-detail">
                        <div>
                            <h2 style="margin:0 0 6px;">Entidade selecionada: <?php echo esc_html($selected['title']); ?></h2>
                            <span class="rma-badge <?php echo esc_attr($selected['status']); ?>">
                                <?php echo $this->status_icon($selected['status']); ?>
                                <?php echo esc_html($this->status_label($selected['status'])); ?>
                            </span>
                            <?php if ($selected['rejection_reason'] !== '') : ?>
                                <p class="rma-muted" style="margin-top:8px;">Motivo da recusa: <?php echo esc_html($selected['rejection_reason']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="rma-gov-detail-grid">
                            <div class="rma-detail-card rma-actions">
                                <h3>Ações de governança</h3>
                                <?php if (in_array($selected['status'], ['pendente', 'em_analise'], true)) : ?>
                                    <form method="post">
                                        <input type="hidden" name="rma_governance_action" value="approve">
                                        <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                                        <?php wp_nonce_field('rma_gov_action_' . $selected['id'] . '_approve'); ?>
                                        <textarea name="comment" rows="2" placeholder="Comentário opcional do aceite"></textarea>
                                        <button class="rma-btn-approve" type="submit">Registrar Aprovado</button>
                                    </form>

                                    <form method="post">
                                        <input type="hidden" name="rma_governance_action" value="reject">
                                        <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                                        <?php wp_nonce_field('rma_gov_action_' . $selected['id'] . '_reject'); ?>
                                        <input type="text" name="reason" required placeholder="Motivo obrigatório da recusa">
                                        <button class="rma-btn-reject" type="submit">Recusar Entidade</button>
                                    </form>

                                    <form method="post">
                                        <input type="hidden" name="rma_governance_action" value="force_approve">
                                        <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                                        <?php wp_nonce_field('rma_gov_action_' . $selected['id'] . '_force_approve'); ?>
                                        <button class="rma-btn-approve" type="submit">Liberar Acesso Agora</button>
                                    </form>
                                <?php elseif ($selected['status'] === 'recusado') : ?>
                                    <form method="post">
                                        <input type="hidden" name="rma_governance_action" value="resubmit">
                                        <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                                        <?php wp_nonce_field('rma_gov_action_' . $selected['id'] . '_resubmit'); ?>
                                        <button class="rma-btn-resubmit" type="submit">Reenviar para análise</button>
                                    </form>
                                <?php else : ?>
                                    <p class="rma-muted">Entidade liberada. Você pode manter, suspender ou remover permanentemente.</p>
                                <?php endif; ?>

                                <?php if (in_array($selected['status'], ['aprovado', 'suspenso'], true)) : ?>
                                    <form method="post">
                                        <input type="hidden" name="rma_governance_action" value="suspend">
                                        <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                                        <?php wp_nonce_field('rma_gov_action_' . $selected['id'] . '_suspend'); ?>
                                        <button class="rma-btn-reject" type="submit">Suspender Entidade</button>
                                    </form>

                                    <form method="post" onsubmit="return confirm('Confirma a exclusão permanente da entidade e dos documentos privados?');">
                                        <input type="hidden" name="rma_governance_action" value="delete">
                                        <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                                        <?php wp_nonce_field('rma_gov_action_' . $selected['id'] . '_delete'); ?>
                                        <button class="rma-btn-reject" style="background:#a10024;" type="submit">Deletar Entidade</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="rma-detail-card">
                                <h3>Aceites registrados</h3>
                                <?php if (empty($selected['approvals'])) : ?>
                                    <p class="rma-muted">Ainda não há aceites para esta entidade.</p>
                                <?php else : ?>
                                    <ul class="rma-timeline">
                                        <?php foreach ($selected['approvals'] as $approval) : ?>
                                            <li>
                                                <?php echo esc_html($approval['user_name']); ?> · <?php echo esc_html($approval['datetime']); ?> · <span class="rma-muted">Etapa <?php echo esc_html((string) ($approval['stage'] ?? 0)); ?></span>
                                                <?php if ($approval['comment'] !== '') : ?>
                                                    <br><span class="rma-muted"><?php echo esc_html($approval['comment']); ?></span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rma-detail-card">
                            <h3>Documentos privados (<?php echo esc_html((string) count($selected['documents'])); ?>)</h3>
                            <?php if (empty($selected['documents'])) : ?>
                                <p class="rma-muted">Não há documentos enviados nesta entidade.</p>
                            <?php else : ?>
                                <table class="rma-gov-nested">
                                    <thead>
                                    <tr>
                                        <th>Arquivo</th>
                                        <th>Tipo</th>
                                        <th>Enviado em</th>
                                        <th>Ação</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($selected['documents'] as $doc) : ?>
                                        <tr>
                                            <td><?php echo esc_html($doc['name']); ?></td>
                                            <td><?php echo esc_html($doc['document_type'] !== '' ? $doc['document_type'] : 'geral'); ?></td>
                                            <td><?php echo esc_html($doc['uploaded_at'] !== '' ? $doc['uploaded_at'] : '—'); ?></td>
                                            <td><a class="rma-link" href="<?php echo esc_url($doc['download_url']); ?>" target="_blank" rel="noopener">Baixar</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <div class="rma-detail-card">
                            <h3>Trilha de auditoria</h3>
                            <?php if (empty($selected['audit_logs'])) : ?>
                                <p class="rma-muted">Sem eventos de auditoria.</p>
                            <?php else : ?>
                                <ul class="rma-timeline">
                                    <?php foreach ($selected['audit_logs'] as $log) : ?>
                                        <li><?php echo esc_html($log['action']); ?> · <?php echo esc_html($log['datetime']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="rma-gov-detail">
                        <div class="rma-detail-card">
                            <h3>Painel de gerenciamento</h3>
                            <p class="rma-muted">Clique em <strong>Gerenciar</strong> em qualquer entidade para abrir a análise completa (ações, documentos e auditoria) aqui no topo.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($selected === null) : ?>
                <div class="rma-gov-table-wrap">
                    <table class="rma-gov-table">
                        <thead>
                        <tr>
                            <th>Entidade</th>
                            <th>Status</th>
                            <th>Aceites</th>
                            <th>Documentos</th>
                            <th>Último evento</th>
                            <th>Ações</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)) : ?>
                            <tr><td colspan="6">Nenhuma entidade encontrada para governança.</td></tr>
                        <?php else : ?>
                            <?php foreach ($rows as $row) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($row['title']); ?></strong><br>
                                        <span class="rma-muted">ID #<?php echo esc_html((string) $row['id']); ?></span>
                                    </td>
                                    <td>
                                        <span class="rma-badge <?php echo esc_attr($row['status']); ?>">
                                            <?php echo $this->status_icon($row['status']); ?>
                                            <?php echo esc_html($this->status_label($row['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html((string) $row['approvals_count']); ?>/3</td>
                                    <td><?php echo esc_html((string) $row['documents_count']); ?></td>
                                    <td><span class="rma-muted"><?php echo esc_html($row['last_audit'] !== '' ? $row['last_audit'] : '—'); ?></span></td>
                                    <td>
                                        <a class="rma-link" href="<?php echo esc_url($this->build_admin_url(['entity_id' => $row['id'], 'status_filter' => $status_filter, 'search' => $search_filter])); ?>#rma-governance-detail">Gerenciar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <p style="margin-top:14px;"><a class="rma-link" href="<?php echo esc_url($this->build_admin_url(['status_filter' => $status_filter, 'search' => $search_filter])); ?>">← Voltar para lista de entidades</a></p>
            <?php endif; ?>

        </div>
        <?php
    }

    private function handle_admin_actions(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        if ($page !== 'rma-governance-audit') {
            return;
        }

        $action = isset($_POST['rma_governance_action']) ? sanitize_key((string) wp_unslash($_POST['rma_governance_action'])) : '';
        $entity_id = isset($_POST['entity_id']) ? (int) $_POST['entity_id'] : 0;

        if ($entity_id <= 0 || ! in_array($action, ['approve', 'reject', 'resubmit', 'force_approve', 'suspend', 'delete'], true)) {
            $this->redirect_with_notice($entity_id, 'Ação inválida.', 'error');
        }

        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';
        if (! wp_verify_nonce($nonce, 'rma_gov_action_' . $entity_id . '_' . $action)) {
            $this->redirect_with_notice($entity_id, 'Falha de segurança ao validar ação.', 'error');
        }

        $request = new WP_REST_Request('POST');
        $request->set_param('id', $entity_id);

        if ($action === 'approve') {
            $request->set_param('comment', sanitize_textarea_field((string) ($_POST['comment'] ?? '')));
            $response = $this->approve_entity($request);
        } elseif ($action === 'reject') {
            $request->set_param('reason', sanitize_text_field((string) ($_POST['reason'] ?? '')));
            $response = $this->reject_entity($request);
        } elseif ($action === 'resubmit') {
            $response = $this->resubmit_entity($request);
        } elseif ($action === 'force_approve') {
            $response = $this->force_approve_entity($request);
        } elseif ($action === 'suspend') {
            $response = $this->suspend_entity($request);
        } else {
            $response = $this->delete_entity($request);
        }

        $payload = $response instanceof WP_REST_Response ? $response->get_data() : [];
        $status = $response instanceof WP_REST_Response ? (int) $response->get_status() : 500;

        if ($status >= 200 && $status < 300) {
            $ok_message = 'Ação executada com sucesso.';
            if ($action === 'approve') {
                $ok_message = 'Aceite registrado com sucesso.';
            } elseif ($action === 'reject') {
                $ok_message = 'Entidade recusada com sucesso.';
            } elseif ($action === 'resubmit') {
                $ok_message = 'Entidade reenviada para análise.';
            } elseif ($action === 'force_approve') {
                $ok_message = 'Acesso liberado com sucesso para a entidade.';
            } elseif ($action === 'suspend') {
                $ok_message = 'Entidade suspensa com sucesso.';
            } elseif ($action === 'delete') {
                $ok_message = 'Entidade removida permanentemente.';
            }

            if ($action === 'delete') {
                $this->redirect_with_notice(0, $ok_message, 'success');
            }

            $this->redirect_with_notice($entity_id, $ok_message, 'success');
        }

        $error_message = is_array($payload) ? (string) ($payload['message'] ?? 'Não foi possível executar a ação.') : 'Não foi possível executar a ação.';
        $this->redirect_with_notice($entity_id, $error_message, 'error');
    }

    private function redirect_with_notice(int $entity_id, string $message, string $type): void {
        $args = [
            'entity_id' => $entity_id,
            'rma_notice' => rawurlencode($message),
            'rma_notice_type' => $type === 'success' ? 'success' : 'error',
        ];

        wp_safe_redirect($this->build_admin_url($args));
        exit;
    }

    private function build_admin_url(array $args = []): string {
        $base = [
            'post_type' => self::CPT,
            'page' => 'rma-governance-audit',
        ];

        return add_query_arg(array_merge($base, $args), admin_url('edit.php'));
    }

    private function load_entity_details(int $entity_id): ?array {
        if ($entity_id <= 0 || get_post_type($entity_id) !== self::CPT) {
            return null;
        }

        $approvals = get_post_meta($entity_id, 'governance_approvals', true);
        $approvals = is_array($approvals) ? $approvals : [];

        $documents = get_post_meta($entity_id, 'entity_documents', true);
        $documents = is_array($documents) ? $documents : [];

        $audit_logs = get_post_meta($entity_id, 'governance_audit_logs', true);
        $audit_logs = is_array($audit_logs) ? $audit_logs : [];
        $audit_logs = array_reverse($audit_logs);

        $normalized_approvals = [];
        foreach ($approvals as $approval) {
            $user_id = (int) ($approval['user_id'] ?? 0);
            $user = $user_id > 0 ? get_userdata($user_id) : null;
            $normalized_approvals[] = [
                'user_name' => $user ? (string) $user->display_name : 'Usuário #' . $user_id,
                'datetime' => (string) ($approval['datetime'] ?? ''),
                'comment' => (string) ($approval['comment'] ?? ''),
                'stage' => (int) ($approval['stage'] ?? 0),
            ];
        }

        $normalized_documents = [];
        foreach ($documents as $doc) {
            $doc_id = sanitize_text_field((string) ($doc['id'] ?? ''));
            if ($doc_id === '') {
                continue;
            }

            $normalized_documents[] = [
                'name' => sanitize_file_name((string) ($doc['name'] ?? 'documento')),
                'document_type' => sanitize_key((string) ($doc['document_type'] ?? '')),
                'uploaded_at' => sanitize_text_field((string) ($doc['uploaded_at'] ?? '')),
                'download_url' => rest_url(sprintf('rma/v1/entities/%d/documents/%s', $entity_id, rawurlencode($doc_id))),
            ];
        }

        $normalized_logs = [];
        foreach ($audit_logs as $log) {
            $normalized_logs[] = [
                'action' => sanitize_key((string) ($log['action'] ?? 'evento')),
                'datetime' => sanitize_text_field((string) ($log['datetime'] ?? '')),
            ];
        }

        return [
            'id' => $entity_id,
            'title' => (string) get_the_title($entity_id),
            'status' => $this->normalized_governance_status($entity_id),
            'rejection_reason' => (string) get_post_meta($entity_id, 'governance_rejection_reason', true),
            'approvals' => $normalized_approvals,
            'documents' => $normalized_documents,
            'audit_logs' => array_slice($normalized_logs, 0, 20),
        ];
    }

    private function load_governance_rows(array $filters = []): array {
        $posts = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['draft', 'publish', 'pending', 'private'],
            'posts_per_page' => 200,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        if (empty($posts)) {
            return [];
        }

        $rows = [];
        $status_filter = sanitize_key((string) ($filters['status_filter'] ?? ''));
        $search_filter = sanitize_text_field((string) ($filters['search_filter'] ?? ''));

        foreach ($posts as $post) {
            $entity_id = (int) $post->ID;
            $approvals = get_post_meta($entity_id, 'governance_approvals', true);
            $approvals = is_array($approvals) ? $approvals : [];

            $documents = get_post_meta($entity_id, 'entity_documents', true);
            $documents = is_array($documents) ? $documents : [];

            $audit_logs = get_post_meta($entity_id, 'governance_audit_logs', true);
            $audit_logs = is_array($audit_logs) ? $audit_logs : [];
            $last_audit = '';

            if (! empty($audit_logs)) {
                $last_log = end($audit_logs);
                $action = (string) ($last_log['action'] ?? 'evento');
                $datetime = (string) ($last_log['datetime'] ?? '');
                $last_audit = trim($action . ' · ' . $datetime, " ·");
            }

            $title = (string) get_the_title($entity_id);
            $status = $this->normalized_governance_status($entity_id);

            if ($status_filter !== '' && $status !== $status_filter) {
                continue;
            }

            if ($search_filter !== '' && stripos($title, $search_filter) === false && stripos((string) $entity_id, $search_filter) === false) {
                continue;
            }

            $rows[] = [
                'id' => $entity_id,
                'title' => $title,
                'status' => $status,
                'approvals_count' => count($approvals),
                'documents_count' => count($documents),
                'last_audit' => $last_audit,
            ];
        }

        return $rows;
    }

    private function build_summary(array $rows): array {
        $summary = [
            'approved' => 0,
            'rejected' => 0,
            'pending' => 0,
            'documents' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'pendente');
            if ($status === 'aprovado') {
                $summary['approved']++;
            } elseif ($status === 'recusado') {
                $summary['rejected']++;
            } else {
                $summary['pending']++;
            }

            $summary['documents'] += (int) ($row['documents_count'] ?? 0);
        }

        return $summary;
    }

    private function summary_card(string $icon, string $label, int $value): string {
        return sprintf(
            '<div class="rma-gov-card"><span class="icon">%s</span><div><strong>%s</strong><span class="rma-muted">%s</span></div></div>',
            $this->svg_icon($icon),
            esc_html((string) $value),
            esc_html($label)
        );
    }

    private function status_label(string $status): string {
        $labels = [
            'aprovado' => 'Aprovado',
            'recusado' => 'Recusado',
            'suspenso' => 'Suspenso',
            'em_analise' => 'Em análise',
            'pendente' => 'Aguardando',
        ];

        return $labels[$status] ?? 'Aguardando';
    }

    private function status_icon(string $status): string {
        if ($status === 'aprovado') {
            return $this->svg_icon('approve');
        }

        if ($status === 'recusado') {
            return $this->svg_icon('reject');
        }

        if ($status === 'em_analise') {
            return $this->svg_icon('analysis');
        }

        if ($status === 'suspenso') {
            return $this->svg_icon('suspended');
        }

        return $this->svg_icon('pending');
    }

    private function svg_icon(string $name): string {
        $icons = [
            'approve' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 7L10 17l-5-5" stroke="#0f9f6f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'reject' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" stroke="#ce3f4a" stroke-width="2" stroke-linecap="round"/></svg>',
            'pending' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8" stroke="#d78d11" stroke-width="2"/><path d="M12 8v5l3 2" stroke="#d78d11" stroke-width="2" stroke-linecap="round"/></svg>',
            'analysis' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 5h9M10 12h9M10 19h9" stroke="#d78d11" stroke-width="2" stroke-linecap="round"/><circle cx="6" cy="5" r="1.5" fill="#d78d11"/><circle cx="6" cy="12" r="1.5" fill="#d78d11"/><circle cx="6" cy="19" r="1.5" fill="#d78d11"/></svg>',
            'document' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="#2351a3" stroke-width="2"/><path d="M14 3v5h5" stroke="#2351a3" stroke-width="2"/></svg>',
            'suspended' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8" stroke="#e8063c" stroke-width="2"/><path d="M8 12h8" stroke="#e8063c" stroke-width="2" stroke-linecap="round"/></svg>',
        ];

        return $icons[$name] ?? $icons['pending'];
    }

    public function register_routes(): void {
        register_rest_route('rma/v1', '/entities/(?P<id>\d+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_entity'],
            'permission_callback' => [$this, 'can_approve'],
        ]);

        register_rest_route('rma/v1', '/entities/(?P<id>\d+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject_entity'],
            'permission_callback' => [$this, 'can_approve'],
        ]);

        register_rest_route('rma/v1', '/entities/(?P<id>\d+)/resubmit', [
            'methods' => 'POST',
            'callback' => [$this, 'resubmit_entity'],
            'permission_callback' => [$this, 'can_resubmit'],
        ]);
    }

    public function can_approve(): bool {
        return current_user_can('edit_others_posts');
    }


    public function can_resubmit(WP_REST_Request $request): bool {
        if (! is_user_logged_in()) {
            return false;
        }

        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return false;
        }

        if (current_user_can('edit_others_posts')) {
            return true;
        }

        return (int) get_post_field('post_author', $entity_id) === get_current_user_id();
    }


    private function governance_stage_label(int $stage): string {
        $labels = [
            1 => 'Documentação',
            2 => 'Compliance',
            3 => 'Diretoria',
        ];

        return $labels[$stage] ?? 'N/I';
    }

    private function resolve_reviewer_stage(int $user_id): int {
        if ($user_id <= 0) {
            return 0;
        }

        $user = get_userdata($user_id);
        if (! $user instanceof WP_User) {
            return 0;
        }

        if (in_array('administrator', (array) $user->roles, true)) {
            return 3;
        }

        if (in_array('editor', (array) $user->roles, true)) {
            return 2;
        }

        if (user_can($user, 'edit_others_posts')) {
            return 1;
        }

        return 0;
    }

    private function current_required_stage(array $approvals): int {
        $stages = [];
        foreach ($approvals as $entry) {
            $stage = (int) ($entry['stage'] ?? 0);
            if ($stage > 0) {
                $stages[$stage] = true;
            }
        }

        if (! empty($stages[1]) && ! empty($stages[2]) && ! empty($stages[3])) {
            return 0;
        }

        if (empty($stages[1])) {
            return 1;
        }

        if (empty($stages[2])) {
            return 2;
        }

        return 3;
    }

    public function approve_entity(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $status = $this->normalized_governance_status($entity_id);
        if ($status === 'aprovado') {
            return new WP_REST_Response(['message' => 'Entidade já aprovada.'], 409);
        }

        if ($status === 'recusado') {
            return new WP_REST_Response([
                'message' => 'Entidade recusada deve ser reenviada antes de novos aceites.',
                'current_status' => $status,
            ], 409);
        }

        if (! in_array($status, ['pendente', 'em_analise'], true)) {
            return new WP_REST_Response([
                'message' => 'Status de governança inválido para aceite.',
                'current_status' => $status,
            ], 409);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_REST_Response(['message' => 'Usuário não autenticado.'], 401);
        }

        $reviewer_stage = $this->resolve_reviewer_stage($user_id);
        if ($reviewer_stage <= 0) {
            return new WP_REST_Response(['message' => 'Usuário sem papel habilitado na matriz de aprovação.'], 403);
        }

        $author_id = (int) get_post_field('post_author', $entity_id);
        if ($author_id > 0 && $author_id === $user_id) {
            return new WP_REST_Response([
                'message' => 'Auto-aceite não é permitido para a própria entidade.',
                'current_status' => $status,
            ], 409);
        }

        $approvals = get_post_meta($entity_id, 'governance_approvals', true);
        $approvals = is_array($approvals) ? $approvals : [];

        foreach ($approvals as $entry) {
            if ((int) ($entry['user_id'] ?? 0) === $user_id) {
                return new WP_REST_Response(['message' => 'Usuário já registrou aceite nesta entidade.'], 409);
            }
        }

        if (count($approvals) >= 3) {
            return new WP_REST_Response(['message' => 'Limite de 3 aceites já atingido.'], 409);
        }

        $required_stage = $this->current_required_stage($approvals);
        if ($required_stage <= 0) {
            return new WP_REST_Response(['message' => 'Aprovação já concluída.'], 409);
        }

        if ($reviewer_stage !== $required_stage) {
            return new WP_REST_Response([
                'message' => 'Esta aprovação exige a etapa ' . $required_stage . ' (' . $this->governance_stage_label($required_stage) . ').',
                'required_stage' => $required_stage,
                'reviewer_stage' => $reviewer_stage,
            ], 409);
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $comment = $this->limit_text(sanitize_textarea_field((string) $request->get_param('comment')), 1000);

        $approvals[] = [
            'user_id' => $user_id,
            'datetime' => current_time('mysql', true),
            'ip' => $ip,
            'comment' => $comment,
            'stage' => $reviewer_stage,
        ];

        $new_status = count($approvals) >= 3 ? 'aprovado' : 'em_analise';
        update_post_meta($entity_id, 'governance_approvals', $approvals);
        update_post_meta($entity_id, 'governance_status', $new_status);
        delete_post_meta($entity_id, 'governance_rejection_reason');

        $this->append_audit_log($entity_id, 'approve', [
            'user_id' => $user_id,
            'ip' => $ip,
            'comment' => $comment,
            'approvals_count' => count($approvals),
            'stage' => $reviewer_stage,
            'required_stage' => $required_stage,
        ]);

        if ($new_status === 'aprovado') {
            wp_update_post([
                'ID' => $entity_id,
                'post_status' => 'publish',
            ]);
            do_action('rma/entity_approved', $entity_id, $approvals);
        } else {
            wp_update_post([
                'ID' => $entity_id,
                'post_status' => 'draft',
            ]);
        }

        return new WP_REST_Response([
            'entity_id' => $entity_id,
            'approvals_count' => count($approvals),
            'governance_status' => $new_status,
        ]);
    }

    public function reject_entity(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $status = $this->normalized_governance_status($entity_id);
        if ($status === 'aprovado') {
            return new WP_REST_Response(['message' => 'Entidade aprovada não pode ser recusada diretamente.'], 409);
        }

        if ($status === 'recusado') {
            return new WP_REST_Response([
                'message' => 'Entidade já está recusada. Use o reenvio para reiniciar o ciclo.',
                'current_status' => $status,
            ], 409);
        }

        if (! in_array($status, ['pendente', 'em_analise'], true)) {
            return new WP_REST_Response([
                'message' => 'Status de governança inválido para recusa.',
                'current_status' => $status,
            ], 409);
        }

        $reason = $this->limit_text(sanitize_textarea_field((string) $request->get_param('reason')), 1000);
        if ($reason === '') {
            return new WP_REST_Response(['message' => 'Motivo da recusa é obrigatório.'], 422);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_REST_Response(['message' => 'Usuário não autenticado.'], 401);
        }
        update_post_meta($entity_id, 'governance_status', 'recusado');
        update_post_meta($entity_id, 'governance_rejection_reason', $reason);
        update_post_meta($entity_id, 'governance_approvals', []);

        wp_update_post([
            'ID' => $entity_id,
            'post_status' => 'draft',
        ]);

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        $this->append_audit_log($entity_id, 'reject', [
            'user_id' => $user_id,
            'ip' => $ip,
            'reason' => $reason,
        ]);

        do_action('rma/entity_rejected', $entity_id, $reason);

        return new WP_REST_Response([
            'entity_id' => $entity_id,
            'governance_status' => 'recusado',
            'reason' => $reason,
        ]);
    }


    public function resubmit_entity(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $current_status = $this->normalized_governance_status($entity_id);
        if ($current_status !== 'recusado') {
            return new WP_REST_Response([
                'message' => 'Somente entidades recusadas podem ser reenviadas.',
                'current_status' => $current_status,
            ], 409);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_REST_Response(['message' => 'Usuário não autenticado.'], 401);
        }
        update_post_meta($entity_id, 'governance_status', 'pendente');
        update_post_meta($entity_id, 'governance_approvals', []);
        delete_post_meta($entity_id, 'governance_rejection_reason');

        wp_update_post([
            'ID' => $entity_id,
            'post_status' => 'draft',
        ]);

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        $this->append_audit_log($entity_id, 'resubmit', [
            'user_id' => $user_id,
            'ip' => $ip,
        ]);

        do_action('rma/entity_resubmitted', $entity_id);

        return new WP_REST_Response([
            'entity_id' => $entity_id,
            'governance_status' => 'pendente',
            'message' => 'Entidade reenviada para análise.',
        ]);
    }



    private function suspend_entity(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0 || ! current_user_can('edit_others_posts')) {
            return new WP_REST_Response(['message' => 'Usuário sem permissão para suspender entidade.'], 403);
        }

        update_post_meta($entity_id, 'governance_status', 'suspenso');

        wp_update_post([
            'ID' => $entity_id,
            'post_status' => 'draft',
        ]);

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $this->append_audit_log($entity_id, 'suspend', [
            'user_id' => $user_id,
            'ip' => $ip,
        ]);

        return new WP_REST_Response([
            'entity_id' => $entity_id,
            'governance_status' => 'suspenso',
        ]);
    }

    private function delete_entity(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0 || ! current_user_can('delete_others_posts')) {
            return new WP_REST_Response(['message' => 'Usuário sem permissão para deletar entidade.'], 403);
        }

        $this->purge_private_documents($entity_id);
        $deleted = wp_delete_post($entity_id, true);

        if (! $deleted) {
            return new WP_REST_Response(['message' => 'Falha ao deletar entidade.'], 500);
        }

        return new WP_REST_Response([
            'entity_id' => $entity_id,
            'deleted' => true,
        ]);
    }

    private function purge_private_documents(int $entity_id): void {
        $docs = get_post_meta($entity_id, 'entity_documents', true);
        $docs = is_array($docs) ? $docs : [];

        foreach ($docs as $doc) {
            $path = (string) ($doc['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $real = realpath($path);
            if ($real === false || ! is_file($real)) {
                continue;
            }

            @unlink($real);
        }
    }



    private function force_approve_entity(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0 || ! current_user_can('edit_others_posts')) {
            return new WP_REST_Response(['message' => 'Usuário sem permissão para liberar acesso.'], 403);
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $approvals = [
            [
                'user_id' => $user_id,
                'datetime' => current_time('mysql', true),
                'ip' => $ip,
                'comment' => 'Aprovação administrativa direta (etapa 1).',
                'stage' => 1,
            ],
            [
                'user_id' => $user_id,
                'datetime' => current_time('mysql', true),
                'ip' => $ip,
                'comment' => 'Aprovação administrativa direta (etapa 2).',
                'stage' => 2,
            ],
            [
                'user_id' => $user_id,
                'datetime' => current_time('mysql', true),
                'ip' => $ip,
                'comment' => 'Aprovação administrativa direta (etapa 3).',
                'stage' => 3,
            ],
        ];

        update_post_meta($entity_id, 'governance_approvals', $approvals);
        update_post_meta($entity_id, 'governance_status', 'aprovado');
        delete_post_meta($entity_id, 'governance_rejection_reason');

        wp_update_post([
            'ID' => $entity_id,
            'post_status' => 'publish',
        ]);

        $this->append_audit_log($entity_id, 'force_approve', [
            'user_id' => $user_id,
            'ip' => $ip,
        ]);

        do_action('rma/entity_approved', $entity_id, $approvals);

        return new WP_REST_Response([
            'entity_id' => $entity_id,
            'governance_status' => 'aprovado',
            'approvals_count' => count($approvals),
        ]);
    }



    public function render_entity_governance_documents(): string {
        if (! is_user_logged_in()) {
            return '<p>Faça login para visualizar os documentos da governança.</p>';
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            return '<p>Nenhuma entidade vinculada ao usuário atual.</p>';
        }

        $status = $this->normalized_governance_status($entity_id);
        $rejection_reason = (string) get_post_meta($entity_id, 'governance_rejection_reason', true);
        $documents = get_post_meta($entity_id, 'entity_documents', true);
        $documents = is_array($documents) ? $documents : [];

        ob_start();
        echo $this->render_entity_governance_styles();
        ?>
        <div class="rma-gov-entity-wrap">
            <?php echo $this->render_entity_governance_nav('rma-governanca-documentos'); ?>
            <div class="rma-gov-entity-head">
                <h3>Governança da Entidade</h3>
                <p>Documentos enviados no cadastro para análise da Equipe RMA.</p>
            </div>

            <div class="rma-gov-entity-meta">
                <div class="rma-gov-entity-card"><small>Entidade</small><strong><?php echo esc_html(get_the_title($entity_id)); ?></strong></div>
                <div class="rma-gov-entity-card"><small>Status da governança</small><span class="rma-badge <?php echo esc_attr($status); ?>"><?php echo esc_html(strtoupper(str_replace('_', ' ', $status))); ?></span></div>
                <div class="rma-gov-entity-card"><small>Documentos enviados</small><strong><?php echo esc_html((string) count($documents)); ?></strong></div>
            </div>

            <?php if ($rejection_reason !== '') : ?>
                <div class="rma-gov-entity-alert"><strong>Motivo da reprovação:</strong> <?php echo esc_html($rejection_reason); ?></div>
            <?php endif; ?>

            <div class="rma-gov-entity-table-wrap">
                <table class="rma-gov-entity-table">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Tipo</th>
                            <th>Enviado em</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($documents)) : ?>
                        <tr><td colspan="4">Nenhum documento enviado no cadastro.</td></tr>
                    <?php else : ?>
                        <?php foreach ($documents as $doc) :
                            $doc_id = sanitize_text_field((string) ($doc['id'] ?? ''));
                            $name = sanitize_text_field((string) ($doc['name'] ?? 'Documento sem nome'));
                            $type = sanitize_key((string) ($doc['document_type'] ?? 'geral'));
                            $uploaded_at = sanitize_text_field((string) ($doc['uploaded_at'] ?? ''));
                            $download = $doc_id !== '' ? rest_url(sprintf('rma/v1/entities/%d/documents/%s', $entity_id, rawurlencode($doc_id))) : '';
                        ?>
                        <tr>
                            <td><?php echo esc_html($name); ?></td>
                            <td><?php echo esc_html($type !== '' ? $type : 'geral'); ?></td>
                            <td><?php echo esc_html($uploaded_at !== '' ? $uploaded_at : '—'); ?></td>
                            <td>
                                <?php if ($download !== '') : ?>
                                    <a class="rma-gov-entity-link" href="<?php echo esc_url($download); ?>" target="_blank" rel="noopener">Baixar</a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function get_entity_id_by_author(int $user_id): int {
        if ($user_id <= 0) {
            return 0;
        }

        $ids = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft', 'pending'],
            'fields' => 'ids',
            'author' => $user_id,
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return (int) ($ids[0] ?? 0);
    }

    public function render_entity_governance_pendencias(): string {
        if (! is_user_logged_in()) {
            return '<p>Faça login para visualizar pendências de governança.</p>';
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            return '<p>Nenhuma entidade vinculada ao usuário atual.</p>';
        }

        $status = $this->normalized_governance_status($entity_id);
        $reason = (string) get_post_meta($entity_id, 'governance_rejection_reason', true);
        $docs = get_post_meta($entity_id, 'entity_documents', true);
        $docs = is_array($docs) ? $docs : [];

        $pending = [];
        if ($status !== 'aprovado') {
            $pending[] = 'Governança em análise pela Equipe RMA.';
        }
        if (count($docs) === 0) {
            $pending[] = 'Nenhum documento enviado. Envie seus documentos para continuar a avaliação.';
        }
        if ($reason !== '') {
            $pending[] = 'Existem ajustes solicitados pela RMA: ' . $reason;
        }

        ob_start();
        echo $this->render_entity_governance_styles();
        echo '<div class="rma-gov-entity-wrap">';
        echo $this->render_entity_governance_nav('rma-governanca-pendencias');
        echo '<div class="rma-gov-entity-head"><h3>Pendências da Governança</h3><p>Acompanhe os pontos pendentes antes da liberação final.</p></div>';
        echo '<div class="rma-gov-entity-table-wrap"><table class="rma-gov-entity-table"><thead><tr><th>Item</th><th>Status</th></tr></thead><tbody>';
        if (empty($pending)) {
            echo '<tr><td>Nenhuma pendência encontrada.</td><td><span class="rma-badge aprovado">OK</span></td></tr>';
        } else {
            foreach ($pending as $item) {
                echo '<tr><td>' . esc_html($item) . '</td><td><span class="rma-badge pendente">PENDENTE</span></td></tr>';
            }
        }
        echo '</tbody></table></div></div>';
        return (string) ob_get_clean();
    }


    public function render_entity_governance_status(): string {
        if (! is_user_logged_in()) {
            return '<p>Faça login para visualizar o status da filiação.</p>';
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            return '<p>Nenhuma entidade vinculada ao usuário atual.</p>';
        }

        $governance_status = $this->normalized_governance_status($entity_id);
        $finance_status = (string) get_post_meta($entity_id, 'finance_status', true);
        $docs_status = (string) get_post_meta($entity_id, 'documentos_status', true);
        $docs = get_post_meta($entity_id, 'entity_documents', true);
        $docs = is_array($docs) ? $docs : [];
        $due_at = (string) get_post_meta($entity_id, 'anuidade_vencimento', true);
        if ($due_at === '') {
            $due_at = (string) get_post_meta($entity_id, 'finance_due_at', true);
        }

        ob_start();
        echo $this->render_entity_governance_styles();
        echo '<div class="rma-gov-entity-wrap">';
        echo $this->render_entity_governance_nav('rma-governanca-status');
        echo '<div class="rma-gov-entity-head"><h3>Status da Filiação</h3><p>Acompanhe o status atual da sua entidade junto à RMA.</p></div>';
        echo '<div class="rma-gov-entity-meta">';
        echo '<div class="rma-gov-entity-card"><small>Governança</small>' . $this->format_entity_badge($governance_status) . '</div>';
        echo '<div class="rma-gov-entity-card"><small>Financeiro</small>' . $this->format_entity_badge($finance_status !== '' ? $finance_status : 'pendente') . '</div>';
        echo '<div class="rma-gov-entity-card"><small>Documentação</small><strong>' . esc_html($docs_status !== '' ? strtoupper($docs_status) : 'PENDENTE') . '</strong></div>';
        echo '<div class="rma-gov-entity-card"><small>Documentos enviados</small><strong>' . esc_html((string) count($docs)) . '</strong></div>';
        echo '</div>';
        echo '<div class="rma-gov-entity-table-wrap"><table class="rma-gov-entity-table"><tbody>';
        echo '<tr><th>Entidade</th><td>' . esc_html(get_the_title($entity_id)) . '</td></tr>';
        echo '<tr><th>Vencimento da anuidade</th><td>' . esc_html($due_at !== '' ? $due_at : 'Não definido') . '</td></tr>';
        echo '<tr><th>Situação de filiação</th><td>' . ($governance_status === 'aprovado' && $finance_status === 'adimplente' ? '<span class="rma-badge aprovado">ATIVA</span>' : '<span class="rma-badge pendente">EM REGULARIZAÇÃO</span>') . '</td></tr>';
        echo '</tbody></table></div>';
        echo '</div>';

        return (string) ob_get_clean();
    }

    public function render_entity_governance_upload(): string {
        if (! is_user_logged_in()) {
            return '<p>Faça login para enviar documentos para a governança.</p>';
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            return '<p>Nenhuma entidade vinculada ao usuário atual.</p>';
        }

        $notice = isset($_GET['rma_doc_notice']) ? sanitize_text_field((string) wp_unslash($_GET['rma_doc_notice'])) : '';
        $type = isset($_GET['rma_doc_notice_type']) ? sanitize_key((string) wp_unslash($_GET['rma_doc_notice_type'])) : '';

        ob_start();
        echo $this->render_entity_governance_styles();
        echo '<div class="rma-gov-entity-wrap">';
        echo $this->render_entity_governance_nav('rma-governanca-upload');
        echo '<div class="rma-gov-entity-head"><h3>Enviar Documentos</h3><p>Envie novos documentos para análise do superadmin (Equipe RMA).</p></div>';
        if ($notice !== '') {
            echo '<div class="rma-gov-entity-alert" style="background:' . ($type === 'success' ? 'rgba(15,159,111,.09);border-color:rgba(15,159,111,.25);color:#0d7d58' : 'rgba(206,63,74,.09);border-color:rgba(206,63,74,.25);color:#b2303c') . '">' . esc_html($notice) . '</div>';
        }
        echo '<form method="post" enctype="multipart/form-data" class="rma-gov-entity-table-wrap" style="padding:14px">';
        wp_nonce_field('rma_entity_upload_document', 'rma_entity_upload_document_nonce');
        echo '<input type="hidden" name="rma_entity_upload_action" value="1" />';
        echo '<p><label>Tipo do documento</label><br/><input type="text" name="document_type" placeholder="ex.: estatuto, ata, comprovante" style="width:100%;max-width:480px;border:1px solid rgba(15,23,42,.2);border-radius:10px;padding:8px 10px"></p>';
        echo '<p><label>Arquivo</label><br/><input type="file" name="entity_document_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required></p>';
        echo '<p><label style="display:inline-flex;align-items:center;gap:8px;"><input type="checkbox" name="document_public" value="1" /> Permitir exibição pública deste documento no perfil da entidade</label></p>';
        echo '<p><button type="submit" style="border:none;border-radius:10px;padding:10px 14px;background:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;cursor:pointer">Enviar documento</button></p>';
        echo '</form></div>';
        return (string) ob_get_clean();
    }

    public function handle_entity_document_upload(): void {
        if (! is_user_logged_in() || ! isset($_POST['rma_entity_upload_action'])) {
            return;
        }

        $nonce = isset($_POST['rma_entity_upload_document_nonce']) ? sanitize_text_field((string) wp_unslash($_POST['rma_entity_upload_document_nonce'])) : '';
        if (! wp_verify_nonce($nonce, 'rma_entity_upload_document')) {
            $this->redirect_entity_dashboard_notice('Falha de segurança ao enviar documento.', 'error');
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            $this->redirect_entity_dashboard_notice('Entidade não encontrada para este usuário.', 'error');
        }

        if (empty($_FILES['entity_document_file']['name'])) {
            $this->redirect_entity_dashboard_notice('Selecione um arquivo para envio.', 'error');
        }

        $file = $_FILES['entity_document_file'];
        $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        $name = sanitize_file_name((string) ($file['name'] ?? 'documento'));
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (! in_array($ext, $allowed_ext, true)) {
            $this->redirect_entity_dashboard_notice('Formato inválido. Use PDF, JPG, PNG, DOC ou DOCX.', 'error');
        }

        $upload_dir = wp_upload_dir();
        $base = trailingslashit($upload_dir['basedir']) . 'rma-private/' . $entity_id;
        if (! wp_mkdir_p($base)) {
            $this->redirect_entity_dashboard_notice('Não foi possível preparar a pasta de upload.', 'error');
        }

        $unique_name = wp_unique_filename($base, $name);
        $target = trailingslashit($base) . $unique_name;
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || ! is_uploaded_file($tmp) || ! move_uploaded_file($tmp, $target)) {
            $this->redirect_entity_dashboard_notice('Falha ao salvar o arquivo enviado.', 'error');
        }

        $docs = get_post_meta($entity_id, 'entity_documents', true);
        $docs = is_array($docs) ? $docs : [];

        $docs[] = [
            'id' => wp_generate_uuid4(),
            'name' => $unique_name,
            'path' => $target,
            'document_type' => sanitize_key((string) ($_POST['document_type'] ?? 'geral')),
            'uploaded_at' => current_time('mysql', true),
            'uploaded_by' => get_current_user_id(),
            'mime_type' => sanitize_text_field((string) ($file['type'] ?? '')),
            'size' => (int) ($file['size'] ?? 0),
            'is_public' => isset($_POST['document_public']) ? '1' : '0',
        ];

        update_post_meta($entity_id, 'entity_documents', $docs);
        update_post_meta($entity_id, 'documentos_status', 'enviado');
        update_post_meta($entity_id, 'governance_status', 'em_analise');

        $this->append_audit_log($entity_id, 'entity_document_upload', [
            'user_id' => get_current_user_id(),
            'count' => count($docs),
        ]);

        $this->push_entity_notification($entity_id, 'governanca', 'Documento enviado', 'Recebemos seu novo documento. A Equipe RMA irá analisar em breve.', add_query_arg('ext', 'rma-governanca-documentos', home_url('/dashboard/')));

        $this->redirect_entity_dashboard_notice('Documento enviado com sucesso para análise da RMA.', 'success');
    }

    public function inject_entity_dashboard_governance_menu(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }

        ?>
        <script>
        (function(){
            var titles = Array.prototype.slice.call(document.querySelectorAll('.menu-title'));
            if (!titles.length) { return; }

            var findToggle = function(expected){
                expected = String(expected || '').toLowerCase();
                return titles.find(function(node){
                    var txt = (node.textContent || '').trim().toLowerCase();
                    return txt === expected;
                }) || null;
            };

            var base = window.location.origin + window.location.pathname;
            var url = new URL(window.location.href);
            var activeExt = (url.searchParams.get('ext') || '').toLowerCase();
            var linkClass = function(ext){ return 'nav-link' + (activeExt === ext ? ' active' : ''); };

            var mountSubmenu = function(toggleNode, exts, html){
                if (!toggleNode) { return; }
                var navLink = toggleNode.closest('a.nav-link');
                if (!navLink) { return; }
                var collapseId = navLink.getAttribute('href');
                if (!collapseId || collapseId.charAt(0) !== '#') { return; }
                var collapse = document.querySelector(collapseId);
                if (!collapse) { return; }
                collapse.innerHTML = html;
                if (exts.indexOf(activeExt) !== -1) {
                    collapse.classList.add('show');
                    navLink.classList.add('active');
                    navLink.setAttribute('aria-expanded', 'true');
                }
            };

            mountSubmenu(
                findToggle('documentos'),
                ['rma-governanca-documentos','rma-governanca-pendencias','rma-governanca-status','rma-governanca-upload'],
                [
                    '<ul class="nav flex-column sub-menu">',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-documentos')+'" href="'+base+'?ext=rma-governanca-documentos">Documentos Enviados</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-pendencias')+'" href="'+base+'?ext=rma-governanca-pendencias">Pendências</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-status')+'" href="'+base+'?ext=rma-governanca-status">Status</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-upload')+'" href="'+base+'?ext=rma-governanca-upload">Enviar Documentos</a></li>',
                    '</ul>'
                ].join('')
            );

            mountSubmenu(
                findToggle('governança') || findToggle('governanca'),
                ['rma-governanca-documentos','rma-governanca-pendencias','rma-governanca-status','rma-governanca-upload'],
                [
                    '<ul class="nav flex-column sub-menu">',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-documentos')+'" href="'+base+'?ext=rma-governanca-documentos">Documentos Enviados</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-pendencias')+'" href="'+base+'?ext=rma-governanca-pendencias">Pendências</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-status')+'" href="'+base+'?ext=rma-governanca-status">Status</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-upload')+'" href="'+base+'?ext=rma-governanca-upload">Enviar Documentos</a></li>',
                    '</ul>'
                ].join('')
            );

            mountSubmenu(
                findToggle('financeiro'),
                ['rma-financeiro-visao-geral','rma-financeiro-cobranca','rma-financeiro-pix','rma-financeiro-historico','rma-financeiro-relatorios'],
                [
                    '<ul class="nav flex-column sub-menu">',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-visao-geral')+'" href="'+base+'?ext=rma-financeiro-visao-geral">Visão Geral</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-cobranca')+'" href="'+base+'?ext=rma-financeiro-cobranca">Minha Cobrança</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-pix')+'" href="'+base+'?ext=rma-financeiro-pix">Meu PIX</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-historico')+'" href="'+base+'?ext=rma-financeiro-historico">Histórico</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-relatorios')+'" href="'+base+'?ext=rma-financeiro-relatorios">Relatórios</a></li>',
                    '</ul>'
                ].join('')
            );

            var supportToggle = findToggle('suporte');
            if (supportToggle) {
                var supportLink = supportToggle.closest('a.nav-link');
                if (supportLink) {
                    supportLink.setAttribute('href', base + '?ext=saved-services');
                    if (['saved-services','rma-suporte','rma-suporte-novo','rma-suporte-tickets'].indexOf(activeExt) !== -1) {
                        supportLink.classList.add('active');
                    }
                }
            }
        })();
        </script>
        <?php
    }

    public function inject_entity_dashboard_governance_content(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }

        $ext = isset($_GET['ext']) ? sanitize_key((string) wp_unslash($_GET['ext'])) : '';
        $map = [
            'rma-governanca-documentos' => '[rma_governanca_entidade_documentos]',
            'rma-governanca-pendencias' => '[rma_governanca_entidade_pendencias]',
            'rma-governanca-status' => '[rma_governanca_entidade_status]',
            'rma-governanca-upload' => '[rma_governanca_entidade_upload]',
        ];

        if (! isset($map[$ext])) {
            return;
        }

        $content = do_shortcode($map[$ext]);
        ?>
        <script>
        (function(){
            var html = <?php echo wp_json_encode($content); ?>;
            var selectors = ['.main-panel .content-wrapper','.main-content .content-wrapper','.main-content','.content-wrapper','.dashboard-content-area'];
            var target = null;
            for (var i=0;i<selectors.length;i++) {
                target = document.querySelector(selectors[i]);
                if (target) { break; }
            }
            if (!target) { return; }
            target.innerHTML = html;
        })();
        </script>
        <?php
    }

    private function redirect_entity_dashboard_notice(string $message, string $type): void {
        $base = home_url('/dashboard/');
        $url = add_query_arg([
            'ext' => 'rma-governanca-upload',
            'rma_doc_notice' => rawurlencode($message),
            'rma_doc_notice_type' => $type === 'success' ? 'success' : 'error',
        ], $base);
        wp_safe_redirect($url);
        exit;
    }


    private function render_entity_governance_nav(string $active_ext): string {
        $base = home_url('/dashboard/');
        $items = [
            'rma-governanca-documentos' => 'Documentos Enviados',
            'rma-governanca-pendencias' => 'Pendências',
            'rma-governanca-status' => 'Status',
            'rma-governanca-upload' => 'Enviar Documentos',
        ];

        $html = '<div class="rma-gov-entity-tabs">';
        foreach ($items as $ext => $label) {
            $class = $ext === $active_ext ? ' is-active' : '';
            $url = add_query_arg('ext', $ext, $base);
            $html .= '<a class="rma-gov-entity-tab' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        $html .= '</div>';

        return $html;
    }

    private function render_entity_governance_styles(): string {
        return '<style>
            .rma-gov-entity-wrap,.rma-gov-entity-wrap *{font-family:"Maven Pro",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;box-sizing:border-box}
            .rma-gov-entity-wrap{background:linear-gradient(150deg,#fcfdff 0%,#f2f7ff 100%);border:1px solid rgba(255,255,255,.9);box-shadow:0 24px 60px rgba(15,23,42,.11);border-radius:24px;padding:22px;color:#162538}
            .rma-gov-entity-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px}
            .rma-gov-entity-tab{text-decoration:none;color:#17355c;background:#e9f1fb;border:1px solid #d4e2f2;padding:6px 11px;border-radius:999px;font-size:12px;font-weight:700}
            .rma-gov-entity-tab.is-active{background:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;border-color:transparent;box-shadow:0 8px 18px rgba(93,218,187,.33)}
            .rma-gov-entity-head{background:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;border-radius:16px;padding:16px 18px;margin-bottom:14px}
            .rma-gov-entity-head h3{margin:0 0 4px;font-size:22px}
            .rma-gov-entity-head p{margin:0;opacity:.95}
            .rma-gov-entity-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:14px}
            .rma-gov-entity-card{background:rgba(255,255,255,.92);border:1px solid rgba(15,23,42,.08);border-radius:12px;padding:11px}
            .rma-gov-entity-card small{display:block;color:#5b6a7c;font-size:12px;margin-bottom:5px}
            .rma-gov-entity-card strong{font-size:16px}
            .rma-badge{border-radius:999px;padding:4px 10px;font-size:12px;display:inline-flex;border:1px solid transparent}
            .rma-badge.aprovado{color:#0f9f6f;background:rgba(15,159,111,.12);border-color:rgba(15,159,111,.25)}
            .rma-badge.recusado{color:#ce3f4a;background:rgba(206,63,74,.12);border-color:rgba(206,63,74,.25)}
            .rma-badge.em_analise,.rma-badge.pendente{color:#d78d11;background:rgba(215,141,17,.12);border-color:rgba(215,141,17,.25)}
            .rma-gov-entity-table-wrap{overflow:auto;background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:12px}
            .rma-gov-entity-table{width:100%;border-collapse:collapse}
            .rma-gov-entity-table th,.rma-gov-entity-table td{padding:11px;border-bottom:1px solid rgba(15,23,42,.08);text-align:left;font-size:13px}
            .rma-gov-entity-table th{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#5b6a7c}
            .rma-gov-entity-link{color:#1559d6;text-decoration:none}
            .rma-gov-entity-link:hover{text-decoration:underline}
            .rma-gov-entity-alert{background:rgba(206,63,74,.09);border:1px solid rgba(206,63,74,.25);color:#b2303c;border-radius:10px;padding:10px;margin:0 0 12px}
        </style>';
    }


    private function format_entity_badge(string $status): string {
        $status = sanitize_key($status !== '' ? $status : 'pendente');
        $label = strtoupper(str_replace('_', ' ', $status));
        if (in_array($status, ['aprovado', 'adimplente', 'active', 'ativo'], true)) {
            return '<span class="rma-badge aprovado">' . esc_html($label) . '</span>';
        }
        if (in_array($status, ['recusado', 'inadimplente', 'failed', 'cancelled', 'refunded'], true)) {
            return '<span class="rma-badge recusado">' . esc_html($label) . '</span>';
        }
        return '<span class="rma-badge pendente">' . esc_html($label) . '</span>';
    }

    public function on_entity_approved(int $entity_id): void {
        $this->push_entity_notification($entity_id, 'governanca', 'Filiação aprovada', 'Sua entidade foi aprovada na governança da RMA.', add_query_arg('ext', 'rma-governanca-status', home_url('/dashboard/')));
    }

    public function on_entity_rejected(int $entity_id, string $reason = ''): void {
        $message = $reason !== '' ? 'Sua filiação foi recusada: ' . $reason : 'Sua filiação foi recusada. Verifique as pendências.';
        $this->push_entity_notification($entity_id, 'governanca', 'Filiação recusada', $message, add_query_arg('ext', 'rma-governanca-pendencias', home_url('/dashboard/')));
    }

    public function on_entity_resubmitted(int $entity_id): void {
        $this->push_entity_notification($entity_id, 'governanca', 'Filiação reenviada', 'Sua entidade foi reenviada para nova análise da governança.', add_query_arg('ext', 'rma-governanca-status', home_url('/dashboard/')));
    }

    public function on_entity_finance_updated(int $entity_id, int $order_id, array $history = []): void {
        $last = ! empty($history) ? end($history) : [];
        $finance_status = (string) ($last['finance_status'] ?? get_post_meta($entity_id, 'finance_status', true));
        $title = $finance_status === 'adimplente' ? 'Pagamento confirmado' : 'Atualização financeira';
        $message = $finance_status === 'adimplente'
            ? 'Seu pagamento foi confirmado e seu status financeiro está adimplente.'
            : 'Houve atualização no financeiro da sua entidade. Consulte os detalhes no painel.';
        $this->push_entity_notification($entity_id, 'financeiro', $title, $message, add_query_arg('ext', 'rma-governanca-status', home_url('/dashboard/')));
    }

    private function push_entity_notification(int $entity_id, string $category, string $title, string $message, string $url = ''): void {
        if ($entity_id <= 0 || get_post_type($entity_id) !== self::CPT) {
            return;
        }

        $items = get_post_meta($entity_id, 'entity_notifications', true);
        $items = is_array($items) ? $items : [];

        $items[] = [
            'id' => wp_generate_uuid4(),
            'category' => sanitize_key($category),
            'title' => sanitize_text_field($title),
            'message' => sanitize_text_field($message),
            'url' => esc_url_raw($url),
            'read' => false,
            'datetime' => current_time('mysql', true),
        ];

        if (count($items) > 80) {
            $items = array_slice($items, -80);
        }

        update_post_meta($entity_id, 'entity_notifications', $items);
    }

    private function get_entity_notifications(int $entity_id): array {
        $items = get_post_meta($entity_id, 'entity_notifications', true);
        return is_array($items) ? array_reverse($items) : [];
    }

    public function ajax_mark_entity_notifications_read(): void {
        if (! is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuário não autenticado.'], 401);
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            wp_send_json_error(['message' => 'Entidade não encontrada.'], 404);
        }

        $items = get_post_meta($entity_id, 'entity_notifications', true);
        $items = is_array($items) ? $items : [];
        foreach ($items as &$item) {
            $item['read'] = true;
        }
        unset($item);

        update_post_meta($entity_id, 'entity_notifications', $items);
        wp_send_json_success(['message' => 'Notificações marcadas como lidas.']);
    }

    public function inject_entity_notifications_dropdown(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            return;
        }

        $items = array_slice($this->get_entity_notifications($entity_id), 0, 12);
        $unread = 0;
        $list_html = '';

        if (empty($items)) {
            $list_html = '<li class="dropdown-item-text" style="white-space:normal">Sem notificações no momento.</li>';
        } else {
            foreach ($items as $item) {
                $is_unread = empty($item['read']);
                if ($is_unread) {
                    $unread++;
                }

                $title = esc_html((string) ($item['title'] ?? 'Notificação'));
                $message = esc_html((string) ($item['message'] ?? ''));
                $datetime = esc_html((string) ($item['datetime'] ?? ''));
                $url = esc_url((string) ($item['url'] ?? ''));
                $style = $is_unread ? 'background:rgba(93,218,187,.08);' : '';

                $inner = '<div style="font-size:13px;line-height:1.25"><strong>' . $title . '</strong><br><span style="opacity:.85">' . $message . '</span><br><small style="opacity:.65">' . $datetime . '</small></div>';
                if ($url !== '') {
                    $list_html .= '<a class="dropdown-item" style="white-space:normal;' . $style . '" href="' . $url . '">' . $inner . '</a>';
                } else {
                    $list_html .= '<li class="dropdown-item-text" style="white-space:normal;' . $style . '">' . $inner . '</li>';
                }
            }
        }

        ?>
        <script>
        (function(){
            var root = document.querySelector('li.nav-item.dropdown.notification-click');
            if (!root) { return; }
            var dropdown = root.querySelector('.dropdown-menu.navbar-dropdown');
            if (!dropdown) { return; }
            dropdown.innerHTML = '<h6 class="p-3 mb-0">Notificações da Entidade</h6><div class="dropdown-divider"></div><?php echo wp_kses_post($list_html); ?>';

            var badgeContainer = root.querySelector('.badge-container');
            if (badgeContainer) {
                badgeContainer.innerHTML = <?php echo wp_json_encode($unread > 0 ? '<span class="badge badge-danger">' . $unread . '</span>' : ''); ?>;
            }

            var trigger = root.querySelector('a.notification-click, a.dropdown-toggle');
            if (trigger) {
                trigger.addEventListener('click', function(){
                    var data = new FormData();
                    data.append('action', 'rma_mark_entity_notifications_read');
                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {method:'POST', credentials:'same-origin', body:data}).then(function(){
                        if (badgeContainer) { badgeContainer.innerHTML = ''; }
                    });
                }, {once:true});
            }
        })();
        </script>
        <?php
    }

    private function normalized_governance_status(int $entity_id): string {
        $status = (string) get_post_meta($entity_id, 'governance_status', true);
        $status = trim($status);

        if ($status === '') {
            return 'pendente';
        }

        return $status;
    }


    private function limit_text(string $value, int $max): string {
        if ($max <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }

    private function append_audit_log(int $entity_id, string $action, array $data): void {
        $logs = get_post_meta($entity_id, 'governance_audit_logs', true);
        $logs = is_array($logs) ? $logs : [];

        $logs[] = [
            'action' => $action,
            'datetime' => current_time('mysql', true),
            'data' => $data,
        ];

        $max_logs = 200;
        if (count($logs) > $max_logs) {
            $logs = array_slice($logs, -1 * $max_logs);
        }

        update_post_meta($entity_id, 'governance_audit_logs', $logs);

        if (function_exists('rma_append_entity_audit_event')) {
            rma_append_entity_audit_event($entity_id, 'governance', $action, 'info', 'Evento de governança registrado.', $data);
        }
    }
}

new RMA_Governance();
