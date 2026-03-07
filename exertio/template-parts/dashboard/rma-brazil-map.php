<?php
/**
 * RMA Brazil map markup.
 */
if (!defined('ABSPATH')) {
	exit;
}

$state_points = array(
	'ac' => array(78, 150),
	'al' => array(395, 231),
	'ap' => array(295, 72),
	'am' => array(165, 118),
	'ba' => array(350, 256),
	'ce' => array(382, 162),
	'df' => array(268, 258),
	'es' => array(351, 304),
	'go' => array(258, 262),
	'ma' => array(320, 146),
	'mt' => array(210, 236),
	'ms' => array(200, 300),
	'mg' => array(312, 292),
	'pa' => array(265, 130),
	'pb' => array(405, 195),
	'pr' => array(275, 371),
	'pe' => array(388, 210),
	'pi' => array(350, 172),
	'rj' => array(334, 328),
	'rn' => array(413, 172),
	'rs' => array(272, 435),
	'ro' => array(125, 168),
	'rr' => array(170, 48),
	'sc' => array(287, 401),
	'sp' => array(286, 334),
	'se' => array(386, 245),
	'to' => array(260, 184),
);

$svg_markup = '';
?>
<div class="box-mapa" data-map-source="<?php echo $svg_markup !== '' ? esc_attr('real-svg') : esc_attr('fallback'); ?>">
	<?php if ($svg_markup !== '') : ?>
		<?php echo $svg_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php else : ?>
		<svg id="map" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 460 465" width="460" height="465" style="display:inline;" class="rma-brazil-svg brazil-svg">
			<g class="model-davi">
				<desc>Brasil</desc>
				<?php foreach ($state_points as $uf => $point) : ?>
					<g id="state_<?php echo esc_attr($uf); ?>" class="state" data-state="<?php echo esc_attr($uf); ?>">
						<circle class="shape" cx="<?php echo esc_attr((string) $point[0]); ?>" cy="<?php echo esc_attr((string) $point[1]); ?>" r="11" />
					</g>
				<?php endforeach; ?>
				<g id="rma-map-pins"></g>
			</g>
		</svg>
	<?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
	if (window.rmaMapInitialized) {
		return;
	}
	window.rmaMapInitialized = true;
	if (typeof window.initRmaMap === 'function') {
		window.initRmaMap();
	}
});
</script>
