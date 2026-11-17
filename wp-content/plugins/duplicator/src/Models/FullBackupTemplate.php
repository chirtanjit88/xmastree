<?php

/**
 * Template for plugin-requested Backups.
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Models;

use Duplicator\Core\Exceptions\DupliException;
use Duplicator\Package\Create\BuildComponents;
use Exception;

/**
 * Runtime copy of a template, the default one unless another is given, with the full component set and the
 * template's own exclusions removed. The prefix filter keeps the template's value, so a Backup covers the tables
 * carrying the site prefix.
 */
final class FullBackupTemplate extends TemplateEntity
{
    /**
     * Derive the template from the source template, without its exclusions.
     *
     * @param ?TemplateEntity $source Template to derive from, null for the default template
     *
     * @throws DupliException When the default template is missing
     */
    public function __construct(?TemplateEntity $source = null)
    {
        parent::__construct();

        $this->copyFrom($source ?? TemplateEntity::getDefaultTemplateOrFail());

        $this->components              = BuildComponents::COMPONENTS_DEFAULT;
        $this->database_filter_on      = false;
        $this->database_filter_tables  = '';
        $this->databasePrefixSubFilter = false;
        $this->archive_filter_on       = false;
        $this->archive_filter_dirs     = '';
        $this->archive_filter_files    = '';
        $this->archive_filter_exts     = '';
        $this->archive_filter_names    = false;
        $this->setExtraData('filter_sites', []);
    }

    /**
     * @return string
     */
    public static function getType(): string
    {
        return 'Full_Backup_Template';
    }

    /**
     * The template exists only for the duration of a request.
     *
     * @return bool
     * @throws Exception Always
     */
    public function save(): bool
    {
        throw new Exception('The full-backup template is built at runtime and cannot be saved.');
    }
}
