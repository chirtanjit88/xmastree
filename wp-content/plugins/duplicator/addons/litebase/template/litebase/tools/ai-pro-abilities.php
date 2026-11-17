<?php

defined('ABSPATH') || exit;

/**
 * Variables
 *
 * @var \Duplicator\Core\Views\TplMng $tplMng
 */

/** @var string[] $abilities */
$abilities = $tplMng->getDataValueArrayRequired('abilities');
?>
<?php foreach ($abilities as $ability) : ?>
    <li class="dupli-litebase-ai-pro-ability">
        <i class="fa fa-caret-right"></i>
        <?php echo wp_kses_post($ability); ?>
        <span class="dupli-litebase-pro-label"><?php esc_html_e('Pro', 'duplicator'); ?></span>
    </li>
<?php endforeach; ?>
