<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="dashboard-header-wrapper">
	<div class="dashboard-header">
		<h3><?php echo esc_html__('Mapa de ONGs', 'exertio_theme'); ?></h3>
		<p><?php echo esc_html__('Painel de controle', 'exertio_theme'); ?></p>
	</div>
</div>
<div class="dashboard-content-area">
	<?php echo do_shortcode('[rma_map_directory]'); ?>
</div>
