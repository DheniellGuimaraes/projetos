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

$state_tile_sizes = array(
	'am' => 20,
	'pa' => 20,
	'mt' => 19,
	'ba' => 18,
	'mg' => 17,
	'go' => 16,
	'ma' => 16,
	'pi' => 15,
	'to' => 15,
	'ms' => 15,
	'rs' => 14,
	'pr' => 14,
	'sc' => 13,
);

$svg_markup = '';
?>
<style>
	#map.rma-brazil-svg{
		width:100%;
		max-width:900px;
		height:auto;
		margin:0 auto;
		display:block;
		background:linear-gradient(180deg,#f7f9fb 0%,#eef3f7 100%);
		border-radius:16px;
		padding:8px;
	}
	#map .state path{
		fill:#e9eef2;
		stroke:#ffffff;
		stroke-width:1.2;
		transition:all .2s ease;
		filter:drop-shadow(0 4px 6px rgba(0,0,0,.15));
		transform-box:fill-box;
		transform-origin:center;
	}
	#map .state.has-ong path{fill:#c7ebd4;}
	#map .state:hover path,
	#map .state.is-active path{
		fill:#cfe8d8;
		transform:translateY(-2px);
	}
</style>
<div class="box-mapa" data-map-source="<?php echo $svg_markup !== '' ? esc_attr('real-svg') : esc_attr('fallback'); ?>">
	<?php if ($svg_markup !== '') : ?>
		<?php echo $svg_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php else : ?>
		<svg id="map" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 460 465" width="460" height="465" style="display:inline;" class="rma-brazil-svg brazil-svg" role="img" aria-label="Mapa do Brasil com estados">
			<defs>
				<linearGradient id="rmaMapStateGradient" x1="0%" y1="0%" x2="100%" y2="100%">
					<stop offset="0%" stop-color="#f4f8fb"></stop>
					<stop offset="100%" stop-color="#dfe8ee"></stop>
				</linearGradient>
			</defs>
			<g class="model-davi">
				<desc>Brasil</desc>
				<?php foreach ($state_points as $uf => $point) : ?>
					<?php
					$cx = (float) $point[0];
					$cy = (float) $point[1];
					$radius = isset($state_tile_sizes[$uf]) ? (float) $state_tile_sizes[$uf] : 12.0;
					$h = $radius;
					$w = $radius * 0.90;
					$path = sprintf(
						'M %.2f %.2f L %.2f %.2f L %.2f %.2f L %.2f %.2f L %.2f %.2f L %.2f %.2f Z',
						$cx,
						$cy - $h,
						$cx + $w,
						$cy - ($h * 0.48),
						$cx + $w,
						$cy + ($h * 0.48),
						$cx,
						$cy + $h,
						$cx - $w,
						$cy + ($h * 0.48),
						$cx - $w,
						$cy - ($h * 0.48)
					);
					?>
					<g id="state_<?php echo esc_attr($uf); ?>" class="state" data-state="<?php echo esc_attr($uf); ?>">
						<path d="<?php echo esc_attr($path); ?>" />
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
