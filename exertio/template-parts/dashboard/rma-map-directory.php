<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<section class="rma-map-premium-shell">
	<div class="dashboard-header-wrapper">
		<div class="dashboard-header">
			<h3><?php echo esc_html__('Mapa de ONGs — Modo Turbo', 'exertio_theme'); ?></h3>
			<p><?php echo esc_html__('Experiência premium com dados focados exclusivamente no diretório geográfico das ONGs.', 'exertio_theme'); ?></p>
		</div>
	</div>
	<div class="dashboard-content-area rma-map-premium-content">
		<?php echo do_shortcode('[rma_map_directory per_page="24"]'); ?>
	</div>
</section>
