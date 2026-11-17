<?php

/**
 * Command line installation handed from the bootstrap to the main installer
 *
 * @package   Duplicator\Installer
 * @copyright (c) 2026, Snap Creek LLC
 */

namespace Duplicator\Installer\Cli;

/**
 * What the command line asked: the parameters and the options the main installer needs. Inlined in installer.php, so
 * it depends on nothing.
 */
final class CliInstallation
{
    /** @var array<string,mixed> Parameters of the command line options and the --params file, by key */
    public array $params;
    /** @var bool Whether warnings that need to be accepted are accepted */
    public bool $acceptWarnings;
    /** @var bool Whether the installation proceeds without asking for confirmation */
    public bool $yes;
    /** @var string URL the request data are built from */
    public string $url;
    /** @var bool Whether the command line set the URL the site answers on */
    public bool $hasUrl;
    /** @var ?string Output format of --info, which describes the Backup instead of installing it; null to install */
    public ?string $info;

    /**
     * @param array<string,mixed> $params         Parameters of the command line options and the --params file, by key
     * @param bool                $acceptWarnings Whether warnings that need to be accepted are accepted
     * @param bool                $yes            Whether the installation proceeds without asking for confirmation
     * @param string              $url            URL the request data are built from
     * @param bool                $hasUrl         Whether the command line set the URL the site answers on
     * @param ?string             $info           Output format of --info; null to install
     */
    public function __construct(array $params, bool $acceptWarnings, bool $yes, string $url, bool $hasUrl, ?string $info)
    {
        $this->params         = $params;
        $this->acceptWarnings = $acceptWarnings;
        $this->yes            = $yes;
        $this->url            = $url;
        $this->hasUrl         = $hasUrl;
        $this->info           = $info;
    }
}
