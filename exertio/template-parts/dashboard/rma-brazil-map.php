<?php
/**
 * RMA Brazil map markup.
 *
 * Renders the real inline SVG map from /images/brasil.svg when available.
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

$state_codes = array_keys($state_points);
$state_codes_pattern = implode('|', array_map('preg_quote', $state_codes));
$svg_file_path = trailingslashit(get_template_directory()) . 'images/brasil.svg';
$svg_markup = '';

if (file_exists($svg_file_path) && is_readable($svg_file_path)) {
	$svg_markup = (string) file_get_contents($svg_file_path);
	$svg_markup = trim($svg_markup);

	if ($svg_markup !== '' && stripos($svg_markup, '<svg') !== false) {
		if (stripos($svg_markup, 'id="map"') === false) {
			$svg_markup = preg_replace('/<svg\b/i', '<svg id="map"', $svg_markup, 1);
		}
		if (stripos($svg_markup, 'class="rma-brazil-svg"') === false) {
			$svg_markup = preg_replace('/<svg\b([^>]*)class="([^"]*)"/i', '<svg$1class="$2 rma-brazil-svg"', $svg_markup, 1, $class_replaced);
			if (empty($class_replaced)) {
				$svg_markup = preg_replace('/<svg\b/i', '<svg class="rma-brazil-svg"', $svg_markup, 1);
			}
		}

		$tag_pattern = '/<(path|g|a)\b([^>]*)\bid="(' . $state_codes_pattern . ')"([^>]*)>(.*?)<\/\1>/is';
		$svg_markup = preg_replace_callback(
			$tag_pattern,
			function ($matches) {
				$tag = strtolower((string) $matches[1]);
				$attrs = (string) $matches[2] . (string) $matches[4];
				$uf = strtolower((string) $matches[3]);
				$content = (string) $matches[5];
				$open = '<' . $tag . $attrs . ' id="state_shape_' . $uf . '">';
				$close = '</' . $tag . '>';
				return '<g id="state_' . $uf . '" class="state" data-state="' . $uf . '">' . $open . $content . $close . '</g>';
			},
			$svg_markup
		);

		foreach ($state_codes as $uf) {
			if (stripos($svg_markup, 'id="state_' . $uf . '"') !== false) {
				if (!preg_match('/id="state_' . preg_quote($uf, '/') . '"[^>]*\bclass="[^"]*\bstate\b/i', $svg_markup)) {
					$svg_markup = preg_replace('/id="state_' . preg_quote($uf, '/') . '"([^>]*)/i', 'id="state_' . $uf . '"$1 class="state" data-state="' . $uf . '"', $svg_markup, 1);
				}
				continue;
			}

			$point = isset($state_points[$uf]) ? $state_points[$uf] : array(0, 0);
			$svg_markup = preg_replace(
				'/<\/svg>\s*$/i',
				'<g id="state_' . $uf . '" class="state" data-state="' . $uf . '"><circle class="shape" cx="' . (float) $point[0] . '" cy="' . (float) $point[1] . '" r="14" fill="transparent" stroke="transparent" /></g></svg>',
				$svg_markup,
				1
			);
		}

		if (stripos($svg_markup, 'id="rma-map-pins"') === false) {
			$svg_markup = preg_replace('/<\/svg>\s*$/i', '<g id="rma-map-pins"></g></svg>', $svg_markup, 1, $pins_inserted);
			if (empty($pins_inserted)) {
				$svg_markup .= '<g id="rma-map-pins"></g>';
			}
		}
	} else {
		$svg_markup = '';
	}
}
?>
<div class="box-mapa" data-map-source="<?php echo $svg_markup !== '' ? esc_attr('real-svg') : esc_attr('fallback'); ?>">
	<?php if ($svg_markup !== '') : ?>
		<?php echo $svg_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php else : ?>
		<svg id="map" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 460 465" width="460" height="465" style="display:inline;" class="rma-brazil-svg">
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
