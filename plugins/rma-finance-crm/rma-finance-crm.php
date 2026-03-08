<?php
/**
 * Plugin Name: RMA Finance CRM
 * Description: CRM financeiro completo para superadmin e entidades, com menus lógicos, KPIs e histórico financeiro.
 * Version: 0.2.0
 * Author: RMA
 */

if (! defined('ABSPATH')) {
    exit;
}

final class RMA_Finance_CRM {
    private const CPT = 'rma_entidade';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_shortcode('rma_financeiro_entidade_crm', [$this, 'render_entity_shortcode']);
        add_shortcode('rma_financeiro_admin_crm', [$this, 'render_admin_shortcode']);
    }

    public function register_admin_menus(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        add_menu_page(
            __('CRM Financeiro RMA', 'rma-finance-crm'),
            __('CRM Financeiro', 'rma-finance-crm'),
            'manage_options',
            'rma-finance-crm',
            [$this, 'render_admin_dashboard_page'],
            'dashicons-chart-line',
            32
        );

        add_submenu_page('rma-finance-crm', __('Visão Geral', 'rma-finance-crm'), __('Visão Geral', 'rma-finance-crm'), 'manage_options', 'rma-finance-crm', [$this, 'render_admin_dashboard_page']);
        add_submenu_page('rma-finance-crm', __('Contas a Receber', 'rma-finance-crm'), __('Contas a Receber', 'rma-finance-crm'), 'manage_options', 'rma-finance-crm-receber', [$this, 'render_admin_receivables_page']);
        add_submenu_page('rma-finance-crm', __('Cobranças PIX', 'rma-finance-crm'), __('Cobranças PIX', 'rma-finance-crm'), 'manage_options', 'rma-finance-crm-pix', [$this, 'render_admin_pix_page']);
        add_submenu_page('rma-finance-crm', __('Conciliação', 'rma-finance-crm'), __('Conciliação', 'rma-finance-crm'), 'manage_options', 'rma-finance-crm-conciliacao', [$this, 'render_admin_reconciliation_page']);
        add_submenu_page('rma-finance-crm', __('Relatórios', 'rma-finance-crm'), __('Relatórios', 'rma-finance-crm'), 'manage_options', 'rma-finance-crm-reports', [$this, 'render_admin_reports_page']);
    }

    public function render_admin_dashboard_page(): void {
        $data = $this->get_admin_financial_data();
        $rows = [];
        foreach ($data['latest_entities'] as $e) {
            $rows[] = [$e['name'], $e['status_badge'], $e['estimated_value'], $e['due_year'], $e['last_order_badge']];
        }

        echo '<div class="wrap">';
        echo '<h1>CRM Financeiro RMA — Visão Geral</h1>';
        echo $this->build_shell_start('rma-finance-crm', 'Painel executivo consolidado para gestão financeira RMA.');
        echo $this->build_kpi_cards($data['kpis']);
        echo $this->build_progress_strip($data['adimplencia_rate']);
        echo $this->build_table('Últimas movimentações', $rows, ['Entidade', 'Status', 'Valor', 'Ano', 'Pedido']);
        echo $this->build_shell_end();
        echo '</div>';
    }

    public function render_admin_receivables_page(): void {
        $data = $this->get_admin_financial_data();
        $rows = [];
        foreach ($data['entities'] as $entity) {
            if (($entity['finance_status'] ?? '') !== 'adimplente') {
                $rows[] = [
                    $entity['name'],
                    $entity['status_badge'],
                    $entity['due_date'],
                    $entity['estimated_value'],
                    $entity['last_order_badge'],
                ];
            }
        }

        echo '<div class="wrap"><h1>CRM Financeiro RMA — Contas a Receber</h1>';
        echo $this->build_shell_start('rma-finance-crm-receber', 'Entidades com pendências, vencimentos e prioridade de cobrança.');
        echo $this->build_table('Entidades com pendência financeira', $rows, ['Entidade', 'Status financeiro', 'Vencimento', 'Valor estimado', 'Último pedido']);
        echo $this->build_shell_end();
        echo '</div>';
    }

    public function render_admin_pix_page(): void {
        $orders = $this->get_recent_due_orders();
        $rows = [];
        foreach ($orders as $order) {
            $rows[] = [
                '#' . (int) $order['id'],
                $order['entity_name'],
                $order['status_badge'],
                $order['total'],
                $order['due_year'],
            ];
        }

        echo '<div class="wrap"><h1>CRM Financeiro RMA — Cobranças PIX</h1>';
        echo $this->build_shell_start('rma-finance-crm-pix', 'Fila de pedidos PIX e acompanhamento da compensação.');
        echo $this->build_table('Pedidos PIX mais recentes', $rows, ['Pedido', 'Entidade', 'Status', 'Total', 'Ano']);
        echo $this->build_shell_end();
        echo '</div>';
    }

    public function render_admin_reconciliation_page(): void {
        $orders = $this->get_recent_due_orders();
        $pending = 0;
        $paid = 0;

        foreach ($orders as $order) {
            if (in_array($order['status'], ['processing', 'completed'], true)) {
                $paid++;
            } else {
                $pending++;
            }
        }

        $coverage = count($orders) > 0 ? (int) round(($paid / count($orders)) * 100) : 0;

        echo '<div class="wrap"><h1>CRM Financeiro RMA — Conciliação</h1>';
        echo $this->build_shell_start('rma-finance-crm-conciliacao', 'Conciliação automática de recebíveis com base em status WooCommerce.');
        echo $this->build_kpi_cards([
            ['label' => 'Pedidos conciliados', 'value' => (string) $paid, 'tone' => 'good'],
            ['label' => 'Pedidos pendentes', 'value' => (string) $pending, 'tone' => 'warn'],
            ['label' => 'Total analisado', 'value' => (string) count($orders), 'tone' => 'neutral'],
        ]);
        echo $this->build_progress_strip($coverage);
        echo $this->build_shell_end();
        echo '</div>';
    }

    public function render_admin_reports_page(): void {
        $report = $this->build_admin_reports_data();

        $state_rows = [];
        foreach ($report['state_summary'] as $state => $summary) {
            $state_rows[] = [
                $state,
                (string) $summary['total'],
                $this->format_status_badge('adimplente', (string) $summary['adimplentes']),
                $this->format_status_badge('inadimplente', (string) $summary['inadimplentes']),
            ];
        }

        $area_rows = [];
        foreach ($report['area_summary'] as $area => $summary) {
            $area_rows[] = [
                $area,
                (string) $summary['total'],
                $this->format_status_badge('active', (string) $summary['ativas']),
                $this->format_status_badge('inadimplente', (string) $summary['inativas']),
            ];
        }

        $annual_rows = [];
        foreach ($report['annual_revenue'] as $year => $value) {
            $annual_rows[] = [(string) $year, $this->format_money((float) $value)];
        }

        $history_rows = [];
        foreach ($report['history_rows'] as $item) {
            $history_rows[] = [
                $item['entity_name'],
                (string) $item['year'],
                $this->format_status_badge((string) $item['finance_status'], strtoupper((string) $item['finance_status'])),
                $this->format_money((float) $item['total']),
                '#' . (int) $item['order_id'],
                (string) $item['paid_at'],
            ];
        }

        echo '<div class="wrap"><h1>CRM Financeiro RMA — Relatórios</h1>';
        echo $this->build_shell_start('rma-finance-crm-reports', 'Conjunto completo de relatórios administrativos: estado, área, ativos/inativos, receita anual e histórico.');
        echo $this->build_kpi_cards([
            ['label' => 'Entidades ativas', 'value' => (string) $report['active_count'], 'tone' => 'good'],
            ['label' => 'Entidades inativas', 'value' => (string) $report['inactive_count'], 'tone' => 'warn'],
            ['label' => 'Receita acumulada', 'value' => $this->format_money((float) $report['revenue_total']), 'tone' => 'neutral'],
            ['label' => 'Eventos no histórico', 'value' => (string) $report['history_total'], 'tone' => 'neutral'],
        ]);
        echo $this->build_table('Relatório por estado', $state_rows, ['UF', 'Entidades', 'Adimplentes', 'Inadimplentes']);
        echo $this->build_table('Relatório por área de interesse', $area_rows, ['Área', 'Entidades', 'Ativas', 'Inativas']);
        echo $this->build_table('Receita anual', $annual_rows, ['Ano', 'Receita']);
        echo $this->build_table('Histórico geral de movimentações', $history_rows, ['Entidade', 'Ano', 'Status', 'Valor', 'Pedido', 'Data']);
        echo $this->build_shell_end();
        echo '</div>';
    }

    public function render_admin_shortcode(): string {
        if (! current_user_can('manage_options')) {
            return '<p>Acesso restrito.</p>';
        }

        ob_start();
        $this->render_admin_dashboard_page();
        return (string) ob_get_clean();
    }

    public function render_entity_shortcode(): string {
        if (! is_user_logged_in()) {
            return '<p>Faça login para acessar o CRM financeiro da entidade.</p>';
        }

        $entity_id = $this->get_entity_id_by_user(get_current_user_id());
        if ($entity_id <= 0) {
            return '<p>Nenhuma entidade vinculada à conta atual.</p>';
        }

        $entity = $this->build_entity_finance_row($entity_id);
        $history = $this->get_entity_history_rows($entity_id);
        $audit_rows = $this->get_entity_audit_rows($entity_id);

        ob_start();
        echo '<div class="rma-fin-shell">';
        echo $this->build_styles();
        echo '<div class="rma-fin-header"><h3>CRM Financeiro da Entidade</h3><p>Dados exclusivos da sua entidade: cobrança, PIX, histórico e visão financeira.</p></div>';
        echo '<div class="rma-fin-nav"><span class="is-active">Visão Geral</span><span>Minha Cobrança</span><span>Meu PIX</span><span>Histórico</span><span>Relatórios</span></div>';

        echo '<div class="rma-fin-entity-identity">';
        echo '<div><small>Entidade</small><strong>' . esc_html($entity['name']) . '</strong></div>';
        echo '<div><small>Status atual</small>' . $entity['status_badge'] . '</div>';
        echo '<div><small>Último pedido</small>' . $entity['last_order_badge'] . '</div>';
        echo '</div>';

        echo $this->build_kpi_cards([
            ['label' => 'Status Financeiro', 'value' => strtoupper($entity['finance_status']), 'tone' => $entity['finance_status'] === 'adimplente' ? 'good' : 'warn'],
            ['label' => 'Vencimento', 'value' => $entity['due_date'], 'tone' => 'neutral'],
            ['label' => 'Valor Anuidade', 'value' => $entity['estimated_value'], 'tone' => 'neutral'],
            ['label' => 'Ano de referência', 'value' => $entity['due_year'], 'tone' => 'neutral'],
        ]);

        echo $this->build_table('Histórico da sua entidade', $history, ['Status', 'Valor', 'Ano', 'Pedido', 'Data']);
        echo $this->build_table('Linha do tempo operacional', $audit_rows, ['Data', 'Origem', 'Evento', 'Severidade', 'Mensagem']);
        echo '</div>';

        return (string) ob_get_clean();
    }

    private function get_entity_audit_rows(int $entity_id): array {
        $events = get_post_meta($entity_id, 'rma_audit_timeline', true);
        $events = is_array($events) ? array_reverse($events) : [];
        $rows = [];

        foreach (array_slice($events, 0, 20) as $event) {
            $rows[] = [
                (string) ($event['datetime'] ?? '—'),
                strtoupper((string) ($event['source'] ?? 'sistema')),
                (string) ($event['event'] ?? 'evento'),
                $this->format_status_badge((string) ($event['severity'] ?? 'info'), strtoupper((string) ($event['severity'] ?? 'info'))),
                (string) ($event['message'] ?? '—'),
            ];
        }

        if (empty($rows)) {
            $rows[] = ['—', 'SISTEMA', 'sem_eventos', $this->format_status_badge('info', 'INFO'), 'Ainda não existem eventos operacionais consolidados.'];
        }

        return $rows;
    }

    private function get_admin_financial_data(): array {
        $ids = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft'],
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);

        $entities = [];
        $ad = 0;
        $inad = 0;
        $sum = 0.0;
        $state_summary = [];

        foreach ($ids as $entity_id) {
            $row = $this->build_entity_finance_row((int) $entity_id);
            $entities[] = $row;
            $sum += (float) $row['estimated_raw'];

            if ($row['finance_status'] === 'adimplente') {
                $ad++;
            } else {
                $inad++;
            }

            $uf = $row['state'] !== '' ? $row['state'] : 'N/I';
            if (! isset($state_summary[$uf])) {
                $state_summary[$uf] = ['total' => 0, 'adimplentes' => 0, 'inadimplentes' => 0];
            }
            $state_summary[$uf]['total']++;
            $state_summary[$uf][$row['finance_status'] === 'adimplente' ? 'adimplentes' : 'inadimplentes']++;
        }

        $total = count($entities);
        $adimplencia_rate = $total > 0 ? (int) round(($ad / $total) * 100) : 0;

        usort($entities, static function (array $a, array $b): int {
            return strcmp($b['due_date_sort'], $a['due_date_sort']);
        });

        return [
            'entities' => $entities,
            'latest_entities' => array_slice($entities, 0, 10),
            'kpis' => [
                ['label' => 'Entidades totais', 'value' => (string) $total, 'tone' => 'neutral'],
                ['label' => 'Adimplentes', 'value' => (string) $ad, 'tone' => 'good'],
                ['label' => 'Inadimplentes', 'value' => (string) $inad, 'tone' => 'warn'],
                ['label' => 'Receita estimada', 'value' => $this->format_money($sum), 'tone' => 'neutral'],
            ],
            'adimplencia_rate' => $adimplencia_rate,
            'state_summary' => $state_summary,
        ];
    }


    private function build_admin_reports_data(): array {
        $entities = $this->get_admin_financial_data()['entities'];

        $state_summary = [];
        $area_summary = [];
        $annual_revenue = [];
        $history_rows = [];
        $active_count = 0;
        $inactive_count = 0;
        $revenue_total = 0.0;

        foreach ($entities as $entity) {
            $entity_id = (int) ($entity['entity_id'] ?? 0);
            $state = (string) ($entity['state'] ?? 'N/I');
            if ($state === '') {
                $state = 'N/I';
            }
            if (! isset($state_summary[$state])) {
                $state_summary[$state] = ['total' => 0, 'adimplentes' => 0, 'inadimplentes' => 0];
            }
            $state_summary[$state]['total']++;
            if (($entity['finance_status'] ?? '') === 'adimplente') {
                $state_summary[$state]['adimplentes']++;
            } else {
                $state_summary[$state]['inadimplentes']++;
            }

            $area = $this->resolve_entity_area_label($entity_id);
            if (! isset($area_summary[$area])) {
                $area_summary[$area] = ['total' => 0, 'ativas' => 0, 'inativas' => 0];
            }
            $area_summary[$area]['total']++;

            $governance = (string) get_post_meta($entity_id, 'governance_status', true);
            $is_active = ($entity['finance_status'] ?? '') === 'adimplente' && $governance === 'aprovado';
            if ($is_active) {
                $active_count++;
                $area_summary[$area]['ativas']++;
            } else {
                $inactive_count++;
                $area_summary[$area]['inativas']++;
            }

            $history = get_post_meta($entity_id, 'finance_history', true);
            $history = is_array($history) ? $history : [];
            foreach ($history as $item) {
                $year = (string) ($item['year'] ?? gmdate('Y'));
                $total = (float) ($item['total'] ?? 0);
                if (! isset($annual_revenue[$year])) {
                    $annual_revenue[$year] = 0.0;
                }
                $annual_revenue[$year] += $total;
                $revenue_total += $total;

                $history_rows[] = [
                    'entity_name' => (string) ($entity['name'] ?? 'Entidade'),
                    'year' => $year,
                    'finance_status' => (string) ($item['finance_status'] ?? 'pendente'),
                    'total' => $total,
                    'order_id' => (int) ($item['order_id'] ?? 0),
                    'paid_at' => (string) ($item['paid_at'] ?? '—'),
                ];
            }
        }

        ksort($annual_revenue);
        usort($history_rows, static function (array $a, array $b): int {
            return strcmp((string) $b['paid_at'], (string) $a['paid_at']);
        });

        return [
            'state_summary' => $state_summary,
            'area_summary' => $area_summary,
            'annual_revenue' => $annual_revenue,
            'history_rows' => array_slice($history_rows, 0, 120),
            'history_total' => count($history_rows),
            'active_count' => $active_count,
            'inactive_count' => $inactive_count,
            'revenue_total' => $revenue_total,
        ];
    }

    private function resolve_entity_area_label(int $entity_id): string {
        if ($entity_id <= 0) {
            return 'Sem área';
        }

        $keys = ['area_interesse', 'area_de_interesse', 'areas_interesse', 'area_atuacao', 'segmento'];
        foreach ($keys as $key) {
            $value = get_post_meta($entity_id, $key, true);
            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('strval', $value)));
            }
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return 'Sem área';
    }

    private function build_entity_finance_row(int $entity_id): array {
        $finance_status = (string) get_post_meta($entity_id, 'finance_status', true);
        if ($finance_status === '') {
            $finance_status = 'inadimplente';
        }

        $due_date = (string) get_post_meta($entity_id, 'anuidade_vencimento', true);
        if ($due_date === '') {
            $due_date = (string) get_post_meta($entity_id, 'finance_due_at', true);
        }

        $due_date_display = $due_date !== '' ? wp_date('d/m/Y', strtotime($due_date)) : 'Não definido';
        $raw_value = (float) get_option('rma_annual_due_value', '0');
        $estimated_value = $this->format_money($raw_value);
        $state = (string) get_post_meta($entity_id, 'uf', true);
        if ($state === '') {
            $state = (string) get_post_meta($entity_id, 'estado', true);
        }

        $latest_order = $this->get_latest_order_for_entity($entity_id);

        return [
            'entity_id' => $entity_id,
            'name' => get_the_title($entity_id),
            'finance_status' => $finance_status,
            'due_date' => $due_date_display,
            'due_date_sort' => $due_date !== '' ? gmdate('Y-m-d', strtotime($due_date)) : '0000-00-00',
            'due_year' => $due_date !== '' ? wp_date('Y', strtotime($due_date)) : gmdate('Y'),
            'estimated_value' => $estimated_value,
            'estimated_raw' => $raw_value,
            'state' => strtoupper($state),
            'last_order_status' => $latest_order['status'] ?? 'sem pedido',
            'status_badge' => $this->format_status_badge($finance_status, strtoupper($finance_status)),
            'last_order_badge' => $this->format_status_badge($latest_order['status'] ?? 'sem pedido', strtoupper((string) ($latest_order['status'] ?? 'sem pedido'))),
        ];
    }

    private function get_entity_history_rows(int $entity_id): array {
        $history = get_post_meta($entity_id, 'finance_history', true);
        $history = is_array($history) ? array_reverse($history) : [];
        $rows = [];

        foreach (array_slice($history, 0, 20) as $item) {
            $status = (string) ($item['finance_status'] ?? '-');
            $rows[] = [
                $this->format_status_badge($status, strtoupper($status)),
                $this->format_money((float) ($item['total'] ?? 0)),
                (string) ($item['year'] ?? '-'),
                '#' . (int) ($item['order_id'] ?? 0),
                (string) ($item['paid_at'] ?? '-'),
            ];
        }

        if (empty($rows)) {
            $rows[] = ['-', '-', '-', '-', 'Sem histórico ainda'];
        }

        return $rows;
    }

    private function get_recent_due_orders(): array {
        if (! function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'limit' => 30,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['pending', 'on-hold', 'processing', 'completed', 'cancelled', 'failed', 'refunded'],
            'meta_key' => 'rma_is_annual_due',
            'meta_value' => '1',
        ]);

        $rows = [];
        foreach ($orders as $order) {
            if (! $order instanceof WC_Order) {
                continue;
            }
            $entity_id = (int) $order->get_meta('rma_entity_id');
            $status = (string) $order->get_status();
            $rows[] = [
                'id' => (int) $order->get_id(),
                'status' => $status,
                'status_badge' => $this->format_status_badge($status, strtoupper($status)),
                'total' => $this->format_money((float) $order->get_total()),
                'due_year' => (string) $order->get_meta('rma_due_year'),
                'entity_name' => $entity_id > 0 ? get_the_title($entity_id) : 'N/I',
            ];
        }

        return $rows;
    }

    private function get_latest_order_for_entity(int $entity_id): array {
        if (! function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['pending', 'on-hold', 'processing', 'completed', 'cancelled', 'failed', 'refunded'],
            'meta_key' => 'rma_entity_id',
            'meta_value' => $entity_id,
        ]);

        if (empty($orders) || ! $orders[0] instanceof WC_Order) {
            return [];
        }

        $order = $orders[0];
        return [
            'id' => (int) $order->get_id(),
            'status' => (string) $order->get_status(),
        ];
    }

    private function get_entity_id_by_user(int $user_id): int {
        if ($user_id <= 0) {
            return 0;
        }

        if (function_exists('rma_get_entity_id_by_author')) {
            return (int) rma_get_entity_id_by_author($user_id);
        }

        $ids = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft'],
            'fields' => 'ids',
            'author' => $user_id,
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return (int) ($ids[0] ?? 0);
    }

    private function build_shell_start(string $active_menu, string $subtitle): string {
        $items = [
            'rma-finance-crm' => 'Visão Geral',
            'rma-finance-crm-receber' => 'Contas a Receber',
            'rma-finance-crm-pix' => 'Cobranças PIX',
            'rma-finance-crm-conciliacao' => 'Conciliação',
            'rma-finance-crm-reports' => 'Relatórios',
        ];

        $nav = '<div class="rma-fin-nav">';
        foreach ($items as $key => $label) {
            $nav .= '<span class="' . ($key === $active_menu ? 'is-active' : '') . '">' . esc_html($label) . '</span>';
        }
        $nav .= '</div>';

        return '<div class="rma-fin-shell">' .
            $this->build_styles() .
            '<div class="rma-fin-header"><h3>CRM Financeiro Completo</h3><p>' . esc_html($subtitle) . '</p></div>' .
            $nav;
    }

    private function build_shell_end(): string {
        return '</div>';
    }

    private function build_kpi_cards(array $cards): string {
        $html = '<div class="rma-fin-cards">';
        foreach ($cards as $card) {
            $tone = sanitize_html_class((string) ($card['tone'] ?? 'neutral'));
            $html .= '<div class="rma-fin-card tone-' . esc_attr($tone) . '">';
            $html .= '<small>' . esc_html((string) ($card['label'] ?? '')) . '</small>';
            $html .= '<strong>' . esc_html((string) ($card['value'] ?? '-')) . '</strong>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    private function build_progress_strip(int $percent): string {
        $percent = max(0, min(100, $percent));
        return '<div class="rma-fin-progress">'
            . '<div class="rma-fin-progress-meta"><span>Taxa de adimplência</span><strong>' . esc_html((string) $percent) . '%</strong></div>'
            . '<div class="rma-fin-progress-track"><div class="rma-fin-progress-fill" style="width:' . (int) $percent . '%"></div></div>'
            . '</div>';
    }

    private function build_table(string $title, array $rows, array $headers): string {
        $html = '<div class="rma-fin-table-wrap"><h4>' . esc_html($title) . '</h4><table class="rma-fin-table"><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . esc_html($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        if (empty($rows)) {
            $html .= '<tr><td colspan="' . (int) count($headers) . '">Sem dados para exibir.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . wp_kses($cell, ['span' => ['class' => true]]) . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    private function format_status_badge(string $status, string $label): string {
        $status = sanitize_key($status);
        $class = 'is-neutral';
        if (in_array($status, ['adimplente', 'completed', 'processing'], true)) {
            $class = 'is-good';
        } elseif (in_array($status, ['inadimplente', 'failed', 'cancelled', 'refunded'], true)) {
            $class = 'is-bad';
        } elseif (in_array($status, ['pending', 'on-hold'], true)) {
            $class = 'is-warn';
        }

        return '<span class="rma-fin-badge ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    private function build_styles(): string {
        return '<style>
            .rma-fin-shell{font-family:"Maven Pro","Segoe UI",Arial,sans-serif;background:linear-gradient(180deg,#f8fbfd 0%,#ffffff 100%);padding:22px;border:1px solid #dce7ef;border-radius:18px;box-shadow:0 20px 44px rgba(15,23,42,.07);margin:10px 0}
            .rma-fin-header{background:linear-gradient(135deg,#7bad39,#5ddabb);padding:20px;border-radius:14px;color:#fff;margin-bottom:14px;position:relative;overflow:hidden}
            .rma-fin-header:after{content:"";position:absolute;inset:auto -30px -40px auto;width:120px;height:120px;background:rgba(255,255,255,.15);border-radius:50%}
            .rma-fin-header h3{margin:0 0 6px;font-size:24px;font-weight:800}
            .rma-fin-header p{margin:0;opacity:.95}
            .rma-fin-nav{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px}
            .rma-fin-nav span{background:#eef6ff;color:#0f172a;border:1px solid #dbe7f3;padding:7px 12px;border-radius:999px;font-size:12px;font-weight:700}
            .rma-fin-nav span.is-active{background:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;border-color:transparent;box-shadow:0 8px 18px rgba(93,218,187,.33)}
            .rma-fin-entity-identity{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;background:#fff;border:1px solid #e5eef5;border-radius:12px;padding:12px;margin-bottom:12px}
            .rma-fin-entity-identity small{display:block;color:#64748b}
            .rma-fin-entity-identity strong{display:block;color:#0f172a;font-size:18px;margin-top:2px}
            .rma-fin-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin-bottom:14px}
            .rma-fin-card{background:#fff;border:1px solid #e4edf5;border-radius:12px;padding:12px}
            .rma-fin-card small{display:block;color:#64748b;margin-bottom:6px;font-weight:600}
            .rma-fin-card strong{font-size:22px;color:#0f172a;line-height:1.2}
            .rma-fin-card.tone-good{border-color:#b7ebc7;background:#f2fcf5}
            .rma-fin-card.tone-warn{border-color:#ffe3b1;background:#fffaf1}
            .rma-fin-progress{background:#fff;border:1px solid #e4edf5;border-radius:12px;padding:12px;margin-bottom:14px}
            .rma-fin-progress-meta{display:flex;justify-content:space-between;margin-bottom:8px;color:#334155}
            .rma-fin-progress-track{height:10px;background:#eaf2f8;border-radius:999px;overflow:hidden}
            .rma-fin-progress-fill{height:100%;background:linear-gradient(90deg,#7bad39,#5ddabb);border-radius:999px}
            .rma-fin-table-wrap{background:#fff;border:1px solid #e4edf5;border-radius:12px;padding:12px;overflow:auto}
            .rma-fin-table-wrap h4{margin:0 0 10px;color:#0f172a}
            .rma-fin-table{width:100%;border-collapse:separate;border-spacing:0}
            .rma-fin-table th,.rma-fin-table td{padding:10px;border-bottom:1px solid #edf2f7;text-align:left;font-size:13px;white-space:nowrap}
            .rma-fin-table th{background:#f8fafc;color:#334155;font-weight:700;position:sticky;top:0}
            .rma-fin-badge{display:inline-block;border-radius:999px;padding:4px 10px;font-size:11px;font-weight:700;letter-spacing:.02em}
            .rma-fin-badge.is-good{background:#dbf7e5;color:#127a3f}
            .rma-fin-badge.is-bad{background:#ffe2e2;color:#b42318}
            .rma-fin-badge.is-warn{background:#fff2d6;color:#925f00}
            .rma-fin-badge.is-neutral{background:#e8eef5;color:#334155}
        </style>';
    }

    private function format_money(float $value): string {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}

new RMA_Finance_CRM();
