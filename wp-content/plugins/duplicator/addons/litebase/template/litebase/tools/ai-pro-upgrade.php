<?php

defined('ABSPATH') || exit;

/**
 * Variables
 *
 * @var \Duplicator\Core\Views\TplMng $tplMng
 */

$upgradeUrl = $tplMng->getDataValueStringRequired('upgradeUrl');
?>
<p>
    <a
        class="button large margin-bottom-0 dupli-litebase-upgrade-btn"
        href="<?php echo esc_url($upgradeUrl); ?>"
        target="_blank"
        rel="noopener noreferrer"
    >
        <?php esc_html_e('Upgrade to Pro', 'duplicator'); ?>
    </a>
</p>
