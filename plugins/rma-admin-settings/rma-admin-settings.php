<?php
/**
 * Plugin Name: RMA Admin Settings
 * Description: Configurações centralizadas para Equipe RMA (anuidade, API Maps, PIX e notificações).
 * Version: 0.2.0
 * Author: RMA
 */

if (! defined('ABSPATH')) {
    exit;
}

final class RMA_Admin_Settings {
    private const OPTION_GROUP = 'rma_admin_settings_group';

    private const OPTIONS = [
        'rma_annual_due_value',
        'rma_annual_dues_product_id',
        'rma_due_day_month',
        'rma_pix_key',
        'rma_google_maps_api_key',
        'rma_maps_only_adimplente',
        'rma_institutional_email',
        'rma_notifications_api_url',
        'rma_email_sender_mode',
        'rma_email_verification_header_image',
        'rma_email_verification_logo',
        'rma_email_verification_bg_color',
        'rma_email_verification_button_color',
        'rma_email_verification_body',
        'rma_email_verification_footer',
        'rma_email_verification_company',
        'rma_email_anexo2_confirmacao_subject',
        'rma_email_anexo2_confirmacao_body',
        'rma_email_anexo2_aviso_renovacao_subject',
        'rma_email_anexo2_aviso_renovacao_body',
        'rma_email_anexo2_lembrete_subject',
        'rma_email_anexo2_lembrete_body',
        'rma_email_anexo2_ultimo_aviso_subject',
        'rma_email_anexo2_ultimo_aviso_body',
        'rma_email_anexo2_cobranca_confirmada_subject',
        'rma_email_anexo2_cobranca_confirmada_body',
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_menu(): void {
        add_menu_page(
            'RMA Configurações',
            'RMA Configurações',
            'manage_options',
            'rma-admin-settings',
            [$this, 'render_page'],
            'dashicons-admin-generic',
            58
        );
    }

    public function register_settings(): void {
        foreach (self::OPTIONS as $option) {
            register_setting(self::OPTION_GROUP, $option, [
                'type' => 'string',
                'sanitize_callback' => function ($value) use ($option) {
                    return $this->sanitize_option($option, $value);
                },
                'show_in_rest' => false,
            ]);
        }

        add_settings_section(
            'rma_admin_main',
            'Parâmetros principais da operação RMA',
            static function () {
                echo '<p>Campos personalizáveis para operação do ciclo anual, mapa, financeiro e notificações.</p>';
                echo '<p><strong>Status do ciclo anual:</strong> ' . esc_html(rma_is_annual_cycle_open() ? 'Vigente' : 'Ainda não iniciado neste ano') . '</p>';
            },
            'rma-admin-settings'
        );

        $this->add_field('rma_annual_due_value', 'Valor da anuidade (R$)', 'number', 'Ex.: 1200.00');
        $this->add_field('rma_annual_dues_product_id', 'ID do produto Woo da anuidade', 'number', 'Ex.: 123');
        $this->add_field('rma_due_day_month', 'Data de início do ciclo anual (dd-mm)', 'text', 'Ex.: 01-02');
        $this->add_field('rma_pix_key', 'Chave PIX institucional', 'text', 'Ex.: financeiro@rma.org.br');
        $this->add_field('rma_google_maps_api_key', 'Google Maps API Key', 'text', 'Usada pelo tema para renderização do mapa');
        $this->add_field('rma_maps_only_adimplente', 'Diretório mostra apenas adimplentes por padrão', 'checkbox', '');
        $this->add_field('rma_institutional_email', 'E-mail institucional (notificações)', 'email', 'Ex.: secretaria@rma.org.br');
        $this->add_field('rma_notifications_api_url', 'URL da API de notificações (opcional)', 'url', 'Ex.: https://api.seudominio/notify');
        $this->add_field('rma_email_sender_mode', 'Motor de envio de e-mails', 'select', '');

        add_settings_section(
            'rma_admin_emails_verification',
            'Configurações > Emails > Verificação',
            static function () {
                echo '<p>Personalize o template do e-mail de verificação em 2 fatores. Variáveis suportadas: <code>{{nome}}</code>, <code>{{codigo}}</code>, <code>{{data}}</code>, <code>{{empresa}}</code>.</p>';
            },
            'rma-admin-settings'
        );

        $this->add_field('rma_email_verification_header_image', 'Imagem de header (URL)', 'url', 'https://.../header.jpg', 'rma_admin_emails_verification');
        $this->add_field('rma_email_verification_logo', 'Logo (URL)', 'url', 'https://.../logo.png', 'rma_admin_emails_verification');
        $this->add_field('rma_email_verification_bg_color', 'Cor de fundo do e-mail', 'text', '#f8fafb', 'rma_admin_emails_verification');
        $this->add_field('rma_email_verification_button_color', 'Cor do botão', 'text', '#7bad39', 'rma_admin_emails_verification');
        $this->add_field('rma_email_verification_body', 'Texto editável do corpo', 'textarea', 'Olá {{nome}}, seu código é {{codigo}}.', 'rma_admin_emails_verification');
        $this->add_field('rma_email_verification_footer', 'Footer editável', 'textarea', 'Equipe RMA • {{data}}', 'rma_admin_emails_verification');
        $this->add_field('rma_email_verification_company', 'Nome da empresa', 'text', 'RMA', 'rma_admin_emails_verification');

        add_settings_section(
            'rma_admin_emails_anexo2',
            'Configurações > Emails > ANEXO 2 (Ciclo Anual)',
            static function () {
                echo '<p>Templates padrão do ANEXO 2. Layout visual segue o padrão do e-mail de autenticação em 2 fatores.</p>';
                echo '<table class="widefat striped" style="max-width:780px"><thead><tr><th>Evento</th><th>Quando enviar</th></tr></thead><tbody>';
                foreach (rma_get_anexo2_events() as $event) {
                    echo '<tr><td>' . esc_html($event['label']) . '</td><td>' . esc_html($event['timing']) . '</td></tr>';
                }
                echo '</tbody></table>';
            },
            'rma-admin-settings'
        );

        foreach (rma_get_anexo2_events() as $key => $event) {
            $this->add_field('rma_email_anexo2_' . $key . '_subject', $event['label'] . ' — Assunto', 'text', $event['default_subject'], 'rma_admin_emails_anexo2');
            $this->add_field('rma_email_anexo2_' . $key . '_body', $event['label'] . ' — Corpo', 'textarea', $event['default_body'], 'rma_admin_emails_anexo2');
        }
    }

    private function add_field(string $name, string $label, string $type, string $placeholder, string $section = 'rma_admin_main'): void {
        add_settings_field(
            $name,
            $label,
            function () use ($name, $type, $placeholder) {
                $value = get_option($name, '');

                if ($type === 'checkbox') {
                    echo '<input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($value, '1', false) . ' />';
                    return;
                }

                if ($type === 'textarea') {
                    if ($value === '') {
                        $value = $placeholder;
                    }
                    echo '<textarea name="' . esc_attr($name) . '" rows="4" cols="60" placeholder="' . esc_attr($placeholder) . '">' . esc_textarea((string) $value) . '</textarea>';
                    return;
                }

                if ($type === 'select') {
                    $current = $value !== '' ? (string) $value : 'wp_mail';
                    echo '<select name="' . esc_attr($name) . '">';
                    echo '<option value="wp_mail" ' . selected($current, 'wp_mail', false) . '>WP Mail</option>';
                    echo '<option value="woo_mail" ' . selected($current, 'woo_mail', false) . '>WooCommerce Mailer</option>';
                    echo '</select>';
                    return;
                }

                $input_type = in_array($type, ['text', 'number', 'email', 'url'], true) ? $type : 'text';
                $attrs = $input_type === 'number' ? ' step="0.01" min="0"' : '';
                echo '<input type="' . esc_attr($input_type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr($placeholder) . '" class="regular-text"' . $attrs . ' />';
            },
            'rma-admin-settings',
            $section
        );
    }

    private function sanitize_option(string $option, $value) {
        if ($option === 'rma_maps_only_adimplente') {
            return $value === '1' ? '1' : '0';
        }

        if (in_array($option, ['rma_annual_due_value'], true)) {
            return (string) max(0, (float) $value);
        }

        if (in_array($option, ['rma_annual_dues_product_id'], true)) {
            return (string) max(0, (int) $value);
        }

        if ($option === 'rma_due_day_month') {
            $value = preg_replace('/[^0-9\-]/', '', (string) $value);
            return preg_match('/^\d{2}-\d{2}$/', $value) ? $value : '01-01';
        }

        if ($option === 'rma_institutional_email') {
            return sanitize_email((string) $value);
        }

        if ($option === 'rma_notifications_api_url') {
            return esc_url_raw((string) $value);
        }

        if (in_array($option, ['rma_email_verification_header_image', 'rma_email_verification_logo'], true)) {
            return esc_url_raw((string) $value);
        }

        if (in_array($option, ['rma_email_verification_bg_color', 'rma_email_verification_button_color'], true)) {
            $color = sanitize_hex_color((string) $value);
            return $color ?: '#7bad39';
        }

        if (str_contains($option, '_body') || str_contains($option, '_footer')) {
            return wp_kses_post((string) $value);
        }

        if ($option === 'rma_email_sender_mode') {
            $mode = sanitize_key((string) $value);
            return in_array($mode, ['wp_mail', 'woo_mail'], true) ? $mode : 'wp_mail';
        }

        return sanitize_text_field((string) $value);
    }

    public function render_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
          <h1>RMA Configurações (Equipe RMA)</h1>
          <form method="post" action="options.php">
            <?php
            settings_fields(self::OPTION_GROUP);
            do_settings_sections('rma-admin-settings');
            submit_button('Salvar configurações');
            ?>
          </form>
        </div>
        <?php
    }
}

new RMA_Admin_Settings();

function rma_get_anexo2_events(): array {
    return [
        'confirmacao' => [
            'label' => 'Confirmação',
            'timing' => 'imediato',
            'default_subject' => 'Confirmação de abertura do ciclo anual',
            'default_body' => 'Olá {{nome}}, o ciclo anual da anuidade de {{entidade}} foi iniciado. Vencimento: {{vencimento}}.',
        ],
        'aviso_renovacao' => [
            'label' => 'Aviso renovação',
            'timing' => '30 dias',
            'default_subject' => 'Renovação da anuidade em 30 dias',
            'default_body' => 'Sua anuidade vence em 30 dias ({{vencimento}}). Gere seu pagamento para evitar bloqueios.',
        ],
        'lembrete' => [
            'label' => 'Lembrete',
            'timing' => '7 dias',
            'default_subject' => 'Lembrete: anuidade vence em 7 dias',
            'default_body' => 'Este é um lembrete: faltam 7 dias para o vencimento da sua anuidade ({{vencimento}}).',
        ],
        'ultimo_aviso' => [
            'label' => 'Último aviso',
            'timing' => '1 dia',
            'default_subject' => 'Último aviso: anuidade vence amanhã',
            'default_body' => 'Último aviso: sua anuidade vence amanhã ({{vencimento}}). Regularize para manter seus acessos ativos.',
        ],
        'cobranca_confirmada' => [
            'label' => 'Cobrança confirmada',
            'timing' => 'imediato',
            'default_subject' => 'Pagamento confirmado com sucesso',
            'default_body' => 'Recebemos a confirmação do seu pagamento. Status atual: {{status}}. Obrigado por manter a entidade regular.',
        ],
    ];
}

function rma_is_annual_cycle_open(): bool {
    $day_month = (string) get_option('rma_due_day_month', '01-01');
    if (! preg_match('/^(\d{2})-(\d{2})$/', $day_month, $matches)) {
        return true;
    }

    $day = (int) $matches[1];
    $month = (int) $matches[2];
    if (! checkdate($month, $day, (int) gmdate('Y'))) {
        return true;
    }

    $current_md = gmdate('m-d');
    $target_md = sprintf('%02d-%02d', $month, $day);
    return $current_md >= $target_md;
}

function rma_render_verification_email_template(array $context = []): string {
    $context = wp_parse_args($context, [
        'nome' => 'Associado',
        'codigo' => '000000',
        'data' => wp_date('d/m/Y H:i'),
        'empresa' => (string) get_option('rma_email_verification_company', 'RMA'),
    ]);

    $body = (string) get_option('rma_email_verification_body', 'Utilize o código abaixo para confirmar seu acesso à plataforma RMA.');
    $body = strtr($body, [
        '{{nome}}' => (string) $context['nome'],
        '{{codigo}}' => (string) $context['codigo'],
        '{{data}}' => (string) $context['data'],
        '{{empresa}}' => (string) $context['empresa'],
    ]);

    return rma_render_email_shell('Verificação em 2 fatores', 'Proteja seu acesso', $body, '<div style="display:inline-block;margin:0 auto 16px;padding:16px 24px;border-radius:14px;background:rgba(255,255,255,.96);border:1px solid #dbe7f3;color:#0f172a;font-size:34px;letter-spacing:9px;font-weight:800;box-shadow:0 10px 24px rgba(15,23,42,.08);">' . esc_html((string) $context['codigo']) . '</div><p style="margin:0 0 16px;color:#64748b;font-size:13px;line-height:1.5;">Este código expira em poucos minutos. Nunca compartilhe com terceiros.</p>');
}

function rma_render_anexo2_email_template(string $event_key, array $context = []): string {
    $events = rma_get_anexo2_events();
    if (! isset($events[$event_key])) {
        $event_key = 'confirmacao';
    }

    $entity_name = (string) ($context['entidade'] ?? 'Entidade RMA');
    $defaults = [
        'nome' => 'Associado',
        'entidade' => $entity_name,
        'vencimento' => (string) ($context['vencimento'] ?? '—'),
        'link_pagamento' => (string) ($context['link_pagamento'] ?? home_url('/checkout/')),
        'valor' => (string) ($context['valor'] ?? ''),
        'status' => (string) ($context['status'] ?? 'adimplente'),
        'data' => wp_date('d/m/Y H:i'),
        'empresa' => (string) get_option('rma_email_verification_company', 'RMA'),
    ];
    $context = wp_parse_args($context, $defaults);

    $subject = (string) get_option('rma_email_anexo2_' . $event_key . '_subject', $events[$event_key]['default_subject']);
    $body = (string) get_option('rma_email_anexo2_' . $event_key . '_body', $events[$event_key]['default_body']);
    $body = strtr($body, [
        '{{nome}}' => (string) $context['nome'],
        '{{entidade}}' => (string) $context['entidade'],
        '{{vencimento}}' => (string) $context['vencimento'],
        '{{link_pagamento}}' => (string) $context['link_pagamento'],
        '{{valor}}' => (string) $context['valor'],
        '{{status}}' => (string) $context['status'],
        '{{data}}' => (string) $context['data'],
        '{{empresa}}' => (string) $context['empresa'],
    ]);

    $cta = '<div style="margin:8px 0 0;"><a href="' . esc_url((string) $context['link_pagamento']) . '" style="display:inline-block;text-decoration:none;background-image:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;padding:12px 24px;border-radius:999px;font-weight:700;font-size:14px;">Acessar pagamento</a></div>';

    return rma_render_email_shell('ANEXO 2 • ' . $events[$event_key]['label'], $subject, $body, $cta);
}

function rma_render_email_shell(string $eyebrow, string $title, string $body_html, string $extra_html = ''): string {
    $logo = trim((string) get_option('rma_email_verification_logo', ''));
    if ($logo === '') {
        $logo = 'https://www.agenciadigitalsaopaulo.com.br/rma/wp-content/uploads/2021/02/logo-.png';
    }

    $favicon = 'https://www.agenciadigitalsaopaulo.com.br/rma/wp-content/uploads/2021/02/favicon.png';

    ob_start();
    ?>
    <div style="background:#ffffff;padding:24px 12px;font-family:'Maven Pro','Segoe UI',Arial,sans-serif;">
      <div style="max-width:520px;margin:0 auto;background:rgba(255,255,255,.94);border-radius:20px;overflow:hidden;border:1px solid #e9eef3;box-shadow:0 16px 42px rgba(15,23,42,.10);">
        <div style="background-image:linear-gradient(135deg,#7bad39,#5ddabb);padding:24px 20px;text-align:center;color:#fff;">
          <?php if ($logo) : ?><img src="<?php echo esc_url($logo); ?>" alt="Logo RMA" style="display:block;max-width:180px;width:100%;height:auto;margin:0 auto 14px;" /><?php endif; ?>
          <p style="margin:0;font-size:12px;line-height:1.4;letter-spacing:.12em;text-transform:uppercase;font-weight:700;opacity:.95;color:#fff;text-align:center;"><?php echo esc_html($eyebrow); ?></p>
          <h2 style="margin:8px 0 0;font-size:30px;line-height:1.1;font-weight:800;color:#ffffff;text-align:center;"><?php echo esc_html($title); ?></h2>
        </div>
        <div style="padding:26px 24px 20px;text-align:center;background:rgba(255,255,255,.92);">
          <p style="margin:0 0 16px;color:#334155;line-height:1.7;font-size:16px;"><?php echo wp_kses_post($body_html); ?></p>
          <?php echo $extra_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <div style="background-image:linear-gradient(135deg,#7bad39,#5ddabb);padding:14px 24px;text-align:center;">
          <img src="<?php echo esc_url($favicon); ?>" alt="RMA" style="display:block;max-width:20px;width:20px;height:20px;margin:0 auto 6px;" />
          <p style="margin:0;color:#ffffff;font-size:16px;line-height:1.4;font-weight:700;letter-spacing:.02em;">rma.org.br</p>
        </div>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

function rma_send_anexo2_email(string $event_key, string $email, array $context = []): bool {
    if (! is_email($email)) {
        return false;
    }

    $events = rma_get_anexo2_events();
    if (! isset($events[$event_key])) {
        return false;
    }

    $subject = (string) get_option('rma_email_anexo2_' . $event_key . '_subject', $events[$event_key]['default_subject']);
    $message = rma_render_anexo2_email_template($event_key, $context);
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $sender_mode = (string) get_option('rma_email_sender_mode', 'wp_mail');
    if ($sender_mode === 'woo_mail' && function_exists('WC') && WC() && method_exists(WC(), 'mailer')) {
        $mailer = WC()->mailer();
        if ($mailer) {
            $wrapped = method_exists($mailer, 'wrap_message') ? $mailer->wrap_message($subject, $message) : $message;
            return (bool) $mailer->send($email, $subject, $wrapped, $headers, []);
        }
    }

    return (bool) wp_mail($email, $subject, $message, $headers);
}

final class RMA_Login_2FA_Gate {
    private const TRANSIENT_PREFIX = 'rma_2fa_login_';

    public function __construct() {
        add_filter('authenticate', [$this, 'intercept_login'], 99, 3);
        add_action('login_form_rma_2fa', [$this, 'render_2fa_form']);
    }

    public function intercept_login($user, string $username, string $password) {
        if (! empty($_POST['rma_2fa_token']) && ! empty($_POST['rma_2fa_code']) && ! empty($_POST['log'])) {
            $login = sanitize_user(wp_unslash($_POST['log']));
            $step_user = get_user_by('login', $login);
            if (! $step_user instanceof WP_User) {
                return new WP_Error('rma_2fa_user_invalid', 'Usuário inválido para validação do 2FA.');
            }

            return $this->validate_second_step($step_user, sanitize_text_field(wp_unslash($_POST['rma_2fa_token'])), sanitize_text_field(wp_unslash($_POST['rma_2fa_code'])));
        }

        if (! $user instanceof WP_User || is_wp_error($user)) {
            return $user;
        }

        if ($this->is_bypass_user($user)) {
            return $user;
        }

        $token = wp_generate_password(32, false, false);
        $code = (string) random_int(100000, 999999);
        set_transient(self::TRANSIENT_PREFIX . $token, [
            'user_id' => (int) $user->ID,
            'code_hash' => wp_hash_password($code),
        ], 10 * MINUTE_IN_SECONDS);

        $email = (string) $user->user_email;
        if (function_exists('rma_render_verification_email_template')) {
            $html = rma_render_verification_email_template([
                'nome' => $user->display_name ?: $user->user_login,
                'codigo' => $code,
            ]);
            wp_mail($email, 'Seu código de verificação', $html, ['Content-Type: text/html; charset=UTF-8']);
        }

        $url = add_query_arg([
            'action' => 'rma_2fa',
            'token' => rawurlencode($token),
            'user' => (int) $user->ID,
        ], wp_login_url());

        return new WP_Error('rma_2fa_required', sprintf('Código de verificação enviado por e-mail. <a href="%s">Clique aqui para validar o login</a>.', esc_url($url)));
    }

    private function validate_second_step(WP_User $user, string $token, string $code) {
        $payload = get_transient(self::TRANSIENT_PREFIX . $token);
        if (! is_array($payload) || (int) ($payload['user_id'] ?? 0) !== (int) $user->ID) {
            return new WP_Error('rma_2fa_expired', 'Código expirado. Faça login novamente.');
        }

        if (! wp_check_password($code, (string) ($payload['code_hash'] ?? ''), $user->ID)) {
            return new WP_Error('rma_2fa_invalid', 'Código inválido.');
        }

        delete_transient(self::TRANSIENT_PREFIX . $token);
        return $user;
    }

    public function render_2fa_form(): void {
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $user_id = isset($_GET['user']) ? (int) $_GET['user'] : 0;
        $user = $user_id > 0 ? get_user_by('id', $user_id) : false;

        if (! $user instanceof WP_User || $token === '') {
            wp_die('Sessão de 2 fatores inválida.');
        }

        login_header('Validação de 2 fatores', '', null);
        echo '<form method="post" action="' . esc_url(wp_login_url()) . '">';
        echo '<p>Digite o código de 6 dígitos enviado para <strong>' . esc_html($user->user_email) . '</strong>.</p>';
        echo '<p><label for="rma_2fa_code">Código</label><input type="text" name="rma_2fa_code" id="rma_2fa_code" class="input" maxlength="6" required /></p>';
        echo '<input type="hidden" name="log" value="' . esc_attr($user->user_login) . '" />';
        echo '<input type="hidden" name="pwd" value="__rma_2fa__" />';
        echo '<input type="hidden" name="rma_2fa_token" value="' . esc_attr($token) . '" />';
        echo '<p class="submit"><button type="submit" class="button button-primary button-large">Validar e entrar</button></p>';
        echo '</form>';
        login_footer();
        exit;
    }

    private function is_bypass_user(WP_User $user): bool {
        return user_can($user, 'manage_options');
    }
}

new RMA_Login_2FA_Gate();
