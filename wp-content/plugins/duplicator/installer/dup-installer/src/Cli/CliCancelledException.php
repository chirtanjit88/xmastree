<?php

/**
 * Command line installation the user did not confirm
 *
 * @package   Duplicator\Installer
 * @copyright (c) 2026, Snap Creek LLC
 */

namespace Duplicator\Installer\Cli;

use Exception;

/**
 * Thrown when the user answers no to the confirmation: the installation stops before changing anything, and the
 * error is not an installer failure to log
 */
final class CliCancelledException extends Exception
{
    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct('installation cancelled.');
    }
}
