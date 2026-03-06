<?php
/**
 * RMA Brazil map SVG markup (all 27 states clickable).
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
?>
<div class="box-mapa">
    <svg id="map" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 460 465" width="460" height="465" style="display:inline;">
        <g class="model-davi">
            <desc>Brasil</desc>
            <path class="brazil-outline" d="M150 34l34-16 30 8 25-10 30 6 20 15 30 8 25 30 27 24-8 27 16 22-8 24-20 20-13 31-4 25 9 18-12 26-9 25-22 20-18 18 1 21-10 17-18 24-8 18-22 8-16 28-20 10-20-8-17 8-20-13-14-23-18-8-16-18 4-24-9-19-18-14 10-26 9-31-12-20 5-27-18-20 8-30 20-17 10-21 4-28 20-14z" />
            <?php foreach ($state_points as $uf => $point) : ?>
                <a href="#" id="state_<?php echo esc_attr($uf); ?>" class="state" data-state="<?php echo esc_attr($uf); ?>" xlink:href="">
                    <desc id="description_<?php echo esc_attr($uf); ?>"><?php echo esc_html(strtoupper($uf)); ?></desc>
                    <circle id="shape_<?php echo esc_attr($uf); ?>" class="shape" cx="<?php echo esc_attr((string) $point[0]); ?>" cy="<?php echo esc_attr((string) $point[1]); ?>" r="11"></circle>
                    <text id="label_icon_state_<?php echo esc_attr($uf); ?>" class="label_icon_state" x="<?php echo esc_attr((string) ($point[0] - 7)); ?>" y="<?php echo esc_attr((string) ($point[1] + 4)); ?>"><?php echo esc_html(strtoupper($uf)); ?></text>
                </a>
            <?php endforeach; ?>
        </g>
    </svg>
</div>
