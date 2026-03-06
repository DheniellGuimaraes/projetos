<?php
/**
 * RMA Brazil map markup.
 *
 * Preferentially loads images/brasil.svg (real Brazil geometry) and annotates
 * states at runtime in JS. Falls back to the simplified map if the SVG file is
 * not available.
 */
if (!defined('ABSPATH')) {
    exit;
}

$state_points = array(
    'rr' => array(170, 48),
    'ap' => array(295, 72),
    'am' => array(165, 118),
    'pa' => array(265, 130),
    'ac' => array(78, 150),
    'ro' => array(125, 168),
    'to' => array(260, 184),
    'ma' => array(320, 146),
    'pi' => array(350, 172),
    'ce' => array(382, 162),
    'rn' => array(413, 172),
    'pb' => array(405, 195),
    'pe' => array(388, 210),
    'al' => array(395, 231),
    'se' => array(386, 245),
    'ba' => array(350, 256),
    'mt' => array(210, 236),
    'ms' => array(200, 300),
    'go' => array(258, 262),
    'df' => array(268, 258),
    'mg' => array(312, 292),
    'es' => array(351, 304),
    'rj' => array(334, 328),
    'sp' => array(286, 334),
    'pr' => array(275, 371),
    'sc' => array(287, 401),
    'rs' => array(272, 435),
);

$svg_path = trailingslashit(get_template_directory()) . 'images/brasil.svg';
$svg_markup = '';

if (file_exists($svg_path) && is_readable($svg_path)) {
    $raw_svg = (string) file_get_contents($svg_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    if (stripos($raw_svg, '<svg') !== false) {
        $raw_svg = preg_replace('/<\?xml[^>]*>\s*/i', '', $raw_svg);
        $raw_svg = preg_replace('/<!DOCTYPE[^>]*>\s*/i', '', $raw_svg);

        if (stripos($raw_svg, 'id="map"') === false && stripos($raw_svg, "id='map'") === false) {
            $raw_svg = preg_replace('/<svg\b/i', '<svg id="map"', $raw_svg, 1);
        }

        if (stripos($raw_svg, 'class=') === false) {
            $raw_svg = preg_replace('/<svg\b([^>]*)>/i', '<svg$1 class="rma-brazil-svg">', $raw_svg, 1);
        }

        $svg_markup = trim((string) $raw_svg);
    }
}
?>
<div class="box-mapa" data-map-source="<?php echo esc_attr($svg_markup !== '' ? 'file' : 'fallback'); ?>">
    <?php if ($svg_markup !== '') : ?>
        <?php echo $svg_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php else : ?>
        <svg id="map" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 460 465" width="460" height="465" style="display:inline;" class="rma-brazil-svg">
            <g class="model-davi">
                <desc>Brasil</desc>
                <path class="brazil-outline" d="M74 136L100 124L120 98L146 84L178 58L210 48L240 56L268 46L300 52L320 68L350 74L374 102L398 124L390 150L406 170L396 198L378 220L366 252L356 286L364 312L350 340L334 370L312 392L298 416L274 438L256 454L232 460L212 448L194 454L172 440L156 418L132 404L114 382L120 356L108 336L86 318L96 288L104 252L90 230L96 198L74 174L82 148Z" />
                <?php foreach ($state_points as $uf => $point) : ?>
                    <a href="#" id="state_<?php echo esc_attr($uf); ?>" class="state" data-state="<?php echo esc_attr($uf); ?>" xlink:href="">
                        <desc id="description_<?php echo esc_attr($uf); ?>"><?php echo esc_html(strtoupper($uf)); ?></desc>
                        <circle id="shape_<?php echo esc_attr($uf); ?>" class="shape" cx="<?php echo esc_attr((string) $point[0]); ?>" cy="<?php echo esc_attr((string) $point[1]); ?>" r="11"></circle>
                        <text id="label_icon_state_<?php echo esc_attr($uf); ?>" class="label_icon_state" x="<?php echo esc_attr((string) ($point[0] - 7)); ?>" y="<?php echo esc_attr((string) ($point[1] + 4)); ?>"><?php echo esc_html(strtoupper($uf)); ?></text>
                    </a>
                <?php endforeach; ?>
            </g>
        </svg>
    <?php endif; ?>
</div>
