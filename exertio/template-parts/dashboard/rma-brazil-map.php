<?php
/**
 * RMA Brazil map markup.
 *
 * Renders the real inline SVG map from /images/brasil.svg when available.
 */
if (!defined('ABSPATH')) {
	exit;
}

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

		$svg_markup = preg_replace_callback(
			'/<([a-z0-9:_-]+)\b([^>]*)\bid="state_([a-z]{2})"([^>]*)>/i',
			function ($matches) {
				$tag_name = $matches[1];
				$attrs = $matches[2] . $matches[4];
				$uf = strtolower($matches[3]);

				if (!preg_match('/\bclass="([^"]*)"/i', $attrs)) {
					$attrs .= ' class="state"';
				} elseif (!preg_match('/\bclass="[^"]*\bstate\b/i', $attrs)) {
					$attrs = preg_replace('/\bclass="([^"]*)"/i', 'class="$1 state"', $attrs, 1);
				}

				if (!preg_match('/\bdata-state="[a-z]{2}"/i', $attrs)) {
					$attrs .= ' data-state="' . $uf . '"';
				} else {
					$attrs = preg_replace('/\bdata-state="[^"]*"/i', 'data-state="' . $uf . '"', $attrs, 1);
				}

				return '<' . $tag_name . $attrs . ' id="state_' . $uf . '">';
			},
			$svg_markup
		);

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
				<g id="rma-map-pins"></g>
			</g>
		</svg>
	<?php endif; ?>
</div>
