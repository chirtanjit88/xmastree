<?php

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

/**
 * Variables
 *
 * @var string $indexError
 */
?>
<div class="sub-title">STATUS</div>
<p class="red">
    The Backup index file could not be read, so the folders to extract cannot be checked.
    This is not a permissions problem.
</p>

<div class="sub-title">DETAILS</div>
<pre><?php echo DUPX_U::esc_html($indexError); ?></pre>

<div class="sub-title">TROUBLESHOOT</div>
<ul>
    <li>
        The index file belongs to the Backup files and is missing or damaged, usually because the files were changed while they were uploaded.
        Upload the Backup files again and restart the installation.
    </li>
    <li>
        If the files were uploaded by FTP, use the binary transfer mode:
        the ASCII mode changes the line endings and damages binary files.
    </li>
</ul>
