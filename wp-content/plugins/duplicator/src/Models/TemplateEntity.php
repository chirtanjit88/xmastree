<?php

namespace Duplicator\Models;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Core\CapMng;
use Duplicator\Core\Exceptions\DupliException;
use Duplicator\Core\Models\AbstractEntity;
use Duplicator\Core\Models\Api\AbstractApiEntity;
use Duplicator\Core\Models\Api\ApiField;
use Duplicator\Core\Models\Api\EntityApiException;
use Duplicator\Package\SettingsUtils;
use Duplicator\Core\Models\TraitEntitySerializationEncryption;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;
use Duplicator\Core\Models\TraitGenericModelList;
use Duplicator\Core\Models\UpdateFromInputInterface;
use Duplicator\Installer\Package\ArchiveDescriptor;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\NameFormat;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Libs\WpUtils\PathUtil;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Utils\Settings\ModelMigrateSettingsInterface;
use Exception;
use ReflectionClass;

class TemplateEntity extends AbstractApiEntity implements UpdateFromInputInterface, ModelMigrateSettingsInterface
{
    use TraitGenericModelList {
        getAll as traitGetAll;
    }
    use TraitEntitySerializationEncryption;

    /** @var int Longest template name */
    const NAME_MAX_LENGTH = 125;
    /** @var int Longest installer password */
    const PASSWORD_MAX_LENGTH = 150;
    /** @var string[] Installer text properties only the template form sets, by property name */
    const FORM_TEXT_PROPERTIES = [
        'installer_opts_db_host',
        'installer_opts_db_name',
        'installer_opts_db_user',
        'installer_opts_cpnl_host',
        'installer_opts_cpnl_user',
        'installer_opts_cpnl_db_action',
        'installer_opts_cpnl_db_host',
        'installer_opts_cpnl_db_name',
        'installer_opts_cpnl_db_user',
    ];
    /** @var array<string,string> Switches only the template form sets: property name by form checkbox */
    const FORM_SWITCHES = [
        'archive_filter_names'       => 'archive_filter_names',
        'databasePrefixFilter'       => 'db-prefix-filter',
        'databasePrefixSubFilter'    => 'db-prefix-sub-filter',
        'installer_opts_skip_scan'   => '_installer_opts_skip_scan',
        'installer_opts_cpnl_enable' => 'installer_opts_cpnl_enable',
    ];
    /** @var array<int,string> Entity API security values by ArchiveDescriptor secure mode */
    const API_SECURITY_MODES = [
        ArchiveDescriptor::SECURE_MODE_NONE        => 'none',
        ArchiveDescriptor::SECURE_MODE_INST_PWD    => 'password',
        ArchiveDescriptor::SECURE_MODE_ARC_ENCRYPT => 'encrypt',
    ];

    /**
     * Encrypted properties for template entities
     *
     * @var string[]
     */
    protected static array $encryptedProperties = ['installerPassowrd'];

    /** @var string */
    public $name = '';
    /** @var string  */
    public $package_name_format = NameFormat::DEFAULT_FORMAT;
    /** @var string */
    public $notes = '';
    //ARCHIVE:Files
    /** @var bool */
    public $archive_export_onlydb = false;
    /** @var bool */
    public $archive_filter_on = false;
    // An exclusion added below must also be cleared in the FullBackupTemplate constructor
    /** @var string */
    public $archive_filter_dirs = '';
    /** @var string */
    public $archive_filter_exts = '';
    /** @var string */
    public $archive_filter_files = '';
    /** @var bool */
    public $archive_filter_names = false;
    /** @var string[] */
    public $components = [];
    //ARCHIVE:Database
    /** @var bool */
    public $database_filter_on = false;  // Enable Table Filters
    /** @var bool */
    public $databasePrefixFilter = false;  // If true exclude tables without prefix
    /** @var bool */
    public $databasePrefixSubFilter = false;  // If true exclude unexisting subsite id tables
    /** @var string */
    public $database_filter_tables = ''; // List of filtered tables
    /** @var string */
    public $database_compatibility_modes = ''; // Older style sql compatibility
    //INSTALLER
    //Setup
    /** @var int */
    public $installer_opts_secure_on = 0;  // Enable Password Protection
    /** @var string */
    public $installerPassowrd = ''; // Password Protection password
    /** @var bool */
    public $installer_opts_skip_scan = false;  // Skip Scanner
    //Basic DB
    /** @var string */
    public $installer_opts_db_host = '';   // MySQL Server Host
    /** @var string */
    public $installer_opts_db_name = '';   // Database
    /** @var string */
    public $installer_opts_db_user = '';   // User
    //cPanel Login
    /** @var bool */
    public $installer_opts_cpnl_enable = false;
    /** @var string */
    public $installer_opts_cpnl_host = '';
    /** @var string */
    public $installer_opts_cpnl_user = '';
    /** @var string */
    public $installer_opts_cpnl_pass = '';
    //cPanel DB
    /** @var string */
    public $installer_opts_cpnl_db_action = 'create';
    /** @var string */
    public $installer_opts_cpnl_db_host = '';
    /** @var string */
    public $installer_opts_cpnl_db_name = '';
    /** @var string */
    public $installer_opts_cpnl_db_user = '';
    /** @var array<string, mixed> Addon-managed extra data */
    public $extraData = [];
    /** @var bool */
    public $is_default = false;
    /** @var bool */
    public $is_manual = false;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name       = __('New Template', 'duplicator');
        $this->components = BuildComponents::COMPONENTS_DEFAULT;
    }

    /**
     * Entity type
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'Package_Template_Entity';
    }

    /**
     * @return string
     */
    public static function apiSlug(): string
    {
        return 'template';
    }

    /**
     * @return array{singular:string,plural:string}
     */
    public static function apiLabels(): array
    {
        return [
            'singular' => __('Template', 'duplicator'),
            'plural'   => __('Templates', 'duplicator'),
        ];
    }

    /**
     * @return string
     */
    public static function apiCapability(): string
    {
        return CapMng::CAP_CREATE;
    }

    /**
     * Only standard templates are exposed: not the manual one, not subclasses
     *
     * @param AbstractEntity $entity Instance loaded by id or listed
     *
     * @return bool
     */
    public static function apiExposes(AbstractEntity $entity): bool
    {
        return $entity instanceof self && get_class($entity) === self::class && !$entity->is_manual;
    }

    /**
     * @return ApiField[]
     */
    public static function apiFields(): array
    {
        return [
            (new ApiField('name', ApiField::TYPE_STRING, 'Template name.'))
                ->requiredOnCreate(),
            new ApiField('notes', ApiField::TYPE_STRING, 'Template notes.'),
            new ApiField('name_format', ApiField::TYPE_STRING, 'Format of the Backup names; empty uses the default format.'),
            (new ApiField(
                'scope',
                ApiField::TYPE_STRING,
                'What the Backups include: all or database. Other values are component sets chosen on the template page, not settable here.'
            ))
                ->enum(array_column(BuildComponents::getPresetActions(), 'value'))
                ->writeEnum(BuildComponents::COMPONENTS_ACTIONS),
            new ApiField('exclude_paths', ApiField::TYPE_STRING_LIST, 'Full paths of the folders and files left out of the archive.'),
            new ApiField('exclude_exts', ApiField::TYPE_STRING_LIST, 'File extensions left out of the archive, without the dot.'),
            new ApiField('exclude_tables', ApiField::TYPE_STRING_LIST, 'Database tables left out of the Backup, with their prefix.'),
            (new ApiField(
                'security',
                ApiField::TYPE_STRING,
                'Installer security: password protects the installer, encrypt also encrypts the archive; both need a password.'
            ))
                ->enum(self::API_SECURITY_MODES),
            (new ApiField('password', ApiField::TYPE_STRING, 'Installer password used by the password and encrypt security modes; never returned.'))
                ->writeOnly(),
            (new ApiField('has_password', ApiField::TYPE_BOOLEAN, 'Whether an installer password is set.'))
                ->readOnly(),
            (new ApiField('is_default', ApiField::TYPE_BOOLEAN, 'Whether this is the default template, which can\'t be deleted.'))
                ->readOnly(),
        ];
    }

    /**
     * Refuse deleting the default template
     *
     * @return void
     */
    public function apiCheckDelete(): void
    {
        if ($this->is_default) {
            throw new EntityApiException(EntityApiException::CODE_CONFLICT, __('The default template can\'t be deleted.', 'duplicator'));
        }
    }

    /**
     * @return array<string,mixed>
     */
    protected function toArray(): array
    {
        return [
            'name'           => $this->name,
            'notes'          => $this->notes,
            'name_format'    => $this->package_name_format,
            'scope'          => BuildComponents::getActionFromComponents($this->components),
            'exclude_paths'  => self::splitList(';', $this->archive_filter_dirs . ';' . $this->archive_filter_files),
            'exclude_exts'   => self::splitList(';', $this->archive_filter_exts),
            'exclude_tables' => self::splitList(',', $this->database_filter_tables),
            'security'       => self::API_SECURITY_MODES[(int) $this->installer_opts_secure_on]
                ?? self::API_SECURITY_MODES[ArchiveDescriptor::SECURE_MODE_NONE],
            'has_password'   => strlen($this->installerPassowrd) > 0,
            'is_default'     => $this->is_default,
        ];
    }

    /**
     * Set the passed values with the template rules, shared by the template form and the entity API.
     *
     * Besides the API fields it takes what only the form sets: archive_filter_on and database_filter_on (without
     * them each filter turns on when its exclusions are set), a custom components set, and the FORM_TEXT_PROPERTIES
     * and FORM_SWITCHES properties by property name.
     *
     * @param array<string,mixed> $values Values by field name
     *
     * @return void
     */
    protected function setFromArray(array $values): void
    {
        foreach (self::FORM_TEXT_PROPERTIES as $property) {
            if (array_key_exists($property, $values)) {
                $this->{$property} = SnapUtil::sanitizeNSChars((string) $values[$property]);
            }
        }
        foreach (array_keys(self::FORM_SWITCHES) as $property) {
            if (array_key_exists($property, $values)) {
                $this->{$property} = (bool) $values[$property];
            }
        }
        if (array_key_exists('components', $values)) {
            $this->components = array_values(array_map('strval', (array) $values['components']));
        }
        if (array_key_exists('name', $values)) {
            $this->name = self::checkLength('name', SnapUtil::sanitizeNSCharsNewlineTrim((string) $values['name']), self::NAME_MAX_LENGTH);
        }
        if (array_key_exists('notes', $values)) {
            $this->notes = SnapUtil::sanitizeNSCharsNewlineTrim((string) $values['notes']);
        }
        if (array_key_exists('name_format', $values)) {
            $this->package_name_format = SnapUtil::sanitizeNSCharsNewlineTrim((string) $values['name_format']);
        }
        if (array_key_exists('scope', $values)) {
            $this->components = BuildComponents::getComponentsFromAction((string) $values['scope']);
        }
        if (array_key_exists('exclude_paths', $values)) {
            $joined                     = SnapUtil::sanitizeNSChars(implode(';', (array) $values['exclude_paths']));
            $this->archive_filter_dirs  = PackageArchive::parseDirectoryFilter($joined);
            $this->archive_filter_files = PackageArchive::parseFileFilter($joined);
        }
        if (array_key_exists('exclude_exts', $values)) {
            $this->archive_filter_exts = PackageArchive::parseExtensionFilter(
                SnapUtil::sanitizeNSCharsNewlineTrim(implode(';', (array) $values['exclude_exts']))
            );
        }
        if (array_key_exists('exclude_tables', $values)) {
            $tables                       = array_map([SnapUtil::class, 'sanitizeNSCharsNewlineTrim'], (array) $values['exclude_tables']);
            $this->database_filter_tables = implode(',', self::splitList(',', implode(',', $tables)));
        }
        if (array_key_exists('archive_filter_on', $values)) {
            $this->archive_filter_on = (bool) $values['archive_filter_on'];
        } elseif (array_key_exists('exclude_paths', $values) || array_key_exists('exclude_exts', $values)) {
            $this->archive_filter_on = ($this->archive_filter_dirs !== '' || $this->archive_filter_files !== '' || $this->archive_filter_exts !== '');
        }
        if (array_key_exists('database_filter_on', $values)) {
            $this->database_filter_on = (bool) $values['database_filter_on'];
        } elseif (array_key_exists('exclude_tables', $values)) {
            $this->database_filter_on = ($this->database_filter_tables !== '');
        }
        if (array_key_exists('security', $values)) {
            $this->setSecurity((string) $values['security']);
        }
        if (array_key_exists('password', $values)) {
            $this->installerPassowrd = self::checkLength(
                'password',
                SnapUtil::sanitizeNSCharsNewline((string) $values['password']),
                self::PASSWORD_MAX_LENGTH
            );
        }

        if ($this->name === '') {
            throw new EntityApiException(EntityApiException::CODE_INVALID_ARGUMENT, __('Template name can\'t be empty.', 'duplicator'));
        }
        if ((int) $this->installer_opts_secure_on !== ArchiveDescriptor::SECURE_MODE_NONE && $this->installerPassowrd === '') {
            throw new EntityApiException(
                EntityApiException::CODE_INVALID_ARGUMENT,
                __('The password and encrypt security modes need a password.', 'duplicator')
            );
        }
    }

    /**
     * Set the installer security mode, refusing to turn on archive encryption where it is not available
     *
     * @param string $security One of API_SECURITY_MODES
     *
     * @return void
     */
    private function setSecurity(string $security): void
    {
        $mode = array_search($security, self::API_SECURITY_MODES, true);
        if ($mode === false) {
            throw new EntityApiException(EntityApiException::CODE_INVALID_ARGUMENT, __('Select valid secure mode', 'duplicator'));
        }
        $message = '';
        if (
            $mode === ArchiveDescriptor::SECURE_MODE_ARC_ENCRYPT &&
            (int) $this->installer_opts_secure_on !== $mode &&
            !SettingsUtils::isArchiveEncryptionAvailable($message)
        ) {
            throw new EntityApiException(
                EntityApiException::CODE_UNAVAILABLE,
                SnapString::oneLine(wp_strip_all_tags($message))
            );
        }
        $this->installer_opts_secure_on = $mode;
    }

    /**
     * @param string $field Field name
     * @param string $value Sanitized value
     * @param int    $max   Maximum length
     *
     * @return string The value
     *
     * @throws EntityApiException When the value is too long
     */
    private static function checkLength(string $field, string $value, int $max): string
    {
        if (mb_strlen($value) > $max) {
            throw new EntityApiException(
                EntityApiException::CODE_INVALID_ARGUMENT,
                sprintf(__('%1$s must be at most %2$d characters.', 'duplicator'), $field, $max)
            );
        }
        return $value;
    }

    /**
     * @param string $separator List separator
     * @param string $value     Stored list
     *
     * @return string[] Non-empty trimmed items
     */
    private static function splitList(string $separator, string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode($separator, $value)), static fn(string $item): bool => $item !== '')));
    }

    /**
     * Get addon extra data value
     *
     * @param string $key     data key
     * @param mixed  $default default value if key not set
     *
     * @return mixed
     */
    public function getExtraData(string $key, $default = null)
    {
        return $this->extraData[$key] ?? $default;
    }

    /**
     * Set addon extra data value
     *
     * @param string $key   data key
     * @param mixed  $value value to set
     *
     * @return void
     */
    public function setExtraData(string $key, $value): void
    {
        $this->extraData[$key] = $value;
    }

    /**
     * Serialize the template entity
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $data = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);

        // Encrypt properties using trait
        $data = $this->encryptSerializedProperties($data);

        return $data;
    }

    /**
     * Unserialize the template entity
     *
     * @param array<string,mixed> $data Serialized data
     *
     * @return void
     */
    public function __unserialize(array $data): void
    {
        // Decrypt properties
        $data = $this->decryptSerializedProperties($data);

        // Type conversions for backward compatibility
        if (isset($data['database_compatibility_modes']) && is_array($data['database_compatibility_modes'])) {
            // for old version compatibility
            $data['database_compatibility_modes'] = implode(',', $data['database_compatibility_modes']);
        }

        if (isset($data['archive_filter_dirs'])) {
            $data['archive_filter_dirs'] = (string) $data['archive_filter_dirs'];
        }
        if (isset($data['archive_filter_files'])) {
            $data['archive_filter_files'] = (string) $data['archive_filter_files'];
        }
        if (isset($data['archive_filter_exts'])) {
            $data['archive_filter_exts'] = (string) $data['archive_filter_exts'];
        }
        if (isset($data['archive_filter_on'])) {
            $data['archive_filter_on'] = filter_var($data['archive_filter_on'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['database_filter_on'])) {
            $data['database_filter_on'] = filter_var($data['database_filter_on'], FILTER_VALIDATE_BOOLEAN);
        }
        // Assign properties
        foreach ($data as $pName => $val) {
            if (!property_exists($this, $pName)) {
                continue;
            }
            $this->$pName = $val;
        }
    }

    /**
     * Legacy decryption for installer password
     *
     * @param array<string,mixed> $data Serialized data
     *
     * @return array<string,mixed> Data with legacy format decrypted
     */
    protected function legacyDecryptProperties(array $data): array
    {
        // Decrypt installerPassowrd
        if (isset($data['installerPassowrd'])) {
            $data['installerPassowrd'] = CryptBlowfish::decryptIfAvaiable($data['installerPassowrd'], null, true);
        }

        return $data;
    }

    /**
     * To export data
     *
     * @return array<string,mixed>
     */
    public function settingsExport(): array
    {
        return JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
    }

    /**
     * Update object properties from import data
     *
     * @param array<string, mixed> $data        data to import
     * @param string               $dataVersion version of data
     * @param array<string, mixed> $extraData   extra data, useful form id mapping etc.
     *
     * @return bool True if success, otherwise false
     */
    public function settingsImport(array $data, string $dataVersion, array $extraData = []): bool
    {
        $skipProps = ['id'];

        $reflect = new ReflectionClass(self::class);
        $props   = $reflect->getProperties();

        foreach ($props as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            if (!isset($data[$prop->getName()])) {
                continue;
            }
            SnapUtil::setAccessible($prop);
            $prop->setValue($this, $data[$prop->getName()]);
        }

        if (!isset($data['components'])) {
            // Allow import of older templsates that did not have Backup components
            if ($this->archive_export_onlydb) {
                $this->components = [BuildComponents::COMP_DB];
            } else {
                $this->components = BuildComponents::COMPONENTS_DEFAULT;
            }
        }

        return true;
    }

    /**
     * Create default template
     *
     * @return void
     */
    public static function createDefault(): void
    {
        if (self::getAll() === false) {
            DupLog::trace('Unable to load templates, default template not created');
            return;
        }

        if (self::getDefaultTemplate() == null) {
            $template = new self();

            $template->name       = __('Default', 'duplicator');
            $template->notes      = __('The default template.', 'duplicator');
            $template->is_default = true;

            $template->save();
            DupLog::trace('Created default template');
        } else {
            // Update it
            DupLog::trace('Default template already exists so not creating');
        }
    }

    /**
     * Set data from query input
     *
     * @param int $type One of INPUT_GET, INPUT_POST, INPUT_COOKIE, INPUT_SERVER, or INPUT_ENV, SnapUtil::INPUT_REQUEST
     *
     * @return bool true on success or false on failure
     */
    public function setFromInput(int $type): bool
    {
        $input = SnapUtil::getInputFromType($type);
        $this->setFromArray($this->getFormValues($input));

        do_action('duplicator_template_after_set_from_post', $this, $input);

        return true;
    }

    /**
     * The values of the template form by setFromArray() key
     *
     * @param array<string,mixed> $input Form input
     *
     * @return array<string,mixed>
     */
    protected function getFormValues(array $input): array
    {
        $values = [
            'notes'              => stripslashes((string) ($input['package-notes'] ?? '')),
            'components'         => BuildComponents::getFromInput($input),
            'exclude_paths'      => explode(';', stripslashes((string) ($input['filter-paths'] ?? ''))),
            'exclude_exts'       => explode(';', stripslashes((string) ($input['filter-exts'] ?? ''))),
            'exclude_tables'     => explode(',', stripslashes((string) ($input['dbtables-list'] ?? ''))),
            'archive_filter_on'  => isset($input['filter-on']),
            'database_filter_on' => isset($input['dbfilter-on']),
            'security'           => self::API_SECURITY_MODES[(int) ($input['secure-on'] ?? -1)] ?? '',
            'password'           => stripslashes((string) ($input['secure-pass'] ?? '')),
        ];
        if (isset($input['name'])) {
            $values['name'] = stripslashes((string) $input['name']);
        }
        if (isset($input['package_name_format'])) {
            $values['name_format'] = stripslashes((string) $input['package_name_format']);
        }
        foreach (self::FORM_TEXT_PROPERTIES as $property) {
            if (isset($input[$property])) {
                $values[$property] = stripslashes((string) $input[$property]);
            }
        }
        // Checkboxes don't post a value when off
        foreach (self::FORM_SWITCHES as $property => $inputName) {
            $values[$property] = isset($input[$inputName]);
        }

        return $values;
    }

    /**
     * @return bool
     */
    public static function apiCanCopy(): bool
    {
        return true;
    }

    /**
     * A new template with the settings of this one, named as its copy
     *
     * @return AbstractApiEntity
     */
    public function apiCopy(): AbstractApiEntity
    {
        $copy = new self();
        // A template the entity API loaded is saved, so its id is positive
        $copy->copyFromSourceId(max(0, $this->getId()));
        return $copy;
    }

    /**
     * Copy template from id
     *
     * @param int<0, max> $templateId template id
     *
     * @return void
     */
    public function copyFromSourceId(int $templateId): void
    {
        if (($source = self::getById($templateId)) === false) {
            throw new Exception('Can\'t get tempalte id' . $templateId);
        }

        $currentName = $this->name;
        $isNew       = $this->getId() < 0;

        $this->copyFrom($source);

        if ($isNew) {
            $sourceName = $source->is_manual ? __("Active Build Settings", 'duplicator') : $source->name;
            $this->name = sprintf(__('%1$s - Copy', 'duplicator'), $sourceName);
        } else {
            $this->name = $currentName;
        }
    }

    /**
     * Copy every setting of another template, including its name, keeping this
     * template's id and role flags
     *
     * @param TemplateEntity $source template to copy from
     *
     * @return void
     */
    public function copyFrom(TemplateEntity $source): void
    {
        $skipProps = [
            'id',
            'is_manual',
            'is_default',
        ];

        $reflect = new ReflectionClass($this);
        $props   = $reflect->getProperties();

        foreach ($props as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            SnapUtil::setAccessible($prop);
            $prop->setValue($this, $prop->getValue($source));
        }
    }

    /**
     * Gets a list of core WordPress folders that have been filtered
     *
     * @return string[] Returns and array of folders paths
     */
    public function getWordPressCoreFilteredFoldersList(): array
    {
        return array_intersect(explode(';', $this->archive_filter_dirs), PathUtil::getWPCoreDirs());
    }

    /**
     * Is any of the WordPress core folders in the folder filter list
     *
     * @return bool Returns true if a WordPress core path is being filtered
     */
    public function isWordPressCoreFolderFiltered(): bool
    {
        return count($this->getWordPressCoreFilteredFoldersList()) > 0;
    }

    /**
     * Get all entities of current type
     *
     * @param int<0, max>                          $page           current page, if $pageSize is 0 o 1 $pase is the offset
     * @param int<0, max>                          $pageSize       page size, 0 return all entities
     * @param callable                             $sortCallback   sort function on items result
     * @param callable                             $filterCallback filter on items result
     * @param array{'col': string, 'mode': string} $orderby        query ordder by
     *
     * @return static[]|false return entities list of false on failure
     */
    public static function getAll(
        $page = 0,
        $pageSize = 0,
        $sortCallback = null,
        $filterCallback = null,
        $orderby = [
            'col'  => 'id',
            'mode' => 'ASC',
        ]
    ) {
        if (is_null($sortCallback)) {
            $sortCallback = function (self $a, self $b): int {
                if ($a->is_default) {
                    return -1;
                } elseif ($b->is_default) {
                    return 1;
                } else {
                    return strcasecmp($a->name, $b->name);
                }
            };
        }
        return self::traitGetAll($page, $pageSize, $sortCallback, $filterCallback, $orderby);
    }

    /**
     * Return list template json encoded data for javascript
     *
     * @param bool          $echo      echo data or return
     * @param static[]|null $templates pre-fetched templates to avoid duplicate query, null to fetch
     *
     * @return string
     */
    public static function getTemplatesFrontendListData(bool $echo = true, ?array $templates = null): string
    {
        if ($templates === null) {
            $templates = self::getAll();
            $templates = ($templates === false ? [] : $templates);
        }
        $result = JsonSerialize::serialize($templates, JsonSerialize::JSON_SKIP_MAGIC_METHODS | JsonSerialize::JSON_SKIP_CLASS_NAME);

        if ($echo) {
            echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return '';
        } else {
            return $result;
        }
    }

    /**
     * Get all entities of current type
     *
     * @param int<0, max> $page     current page, if $pageSize is 0 o 1 $pase is the offset
     * @param int<0, max> $pageSize page size, 0 return all entities
     *
     * @return static[]|false return entities list of false on failure
     */
    public static function getAllWithoutManualMode(
        $page = 0,
        $pageSize = 0
    ) {
        $filterManualCallback = (fn(self $obj): bool => $obj->is_manual === false);
        return self::getAll($page, $pageSize, null, $filterManualCallback);
    }

    /**
     * Get default template if exists
     *
     * @return ?self
     */
    public static function getDefaultTemplate()
    {
        if (($templates = self::getAll()) === false) {
            return null;
        }

        foreach ($templates as $template) {
            if ($template->is_default) {
                return $template;
            }
        }
        return null;
    }

    /**
     * Get the default template, which every install has
     *
     * @return self
     *
     * @throws DupliException When the default template is missing, a corrupted install
     */
    public static function getDefaultTemplateOrFail(): self
    {
        if (($default = self::getDefaultTemplate()) === null) {
            throw new DupliException(
                'Default backup template missing.',
                DupliException::CODE_ERROR,
                __('The default backup template is missing. Deactivate and reactivate Duplicator to restore it.', 'duplicator')
            );
        }
        return $default;
    }

    /**
     * Return manual template
     *
     * @return self
     */
    public static function getManualTemplate(): self
    {
        static $manualTemplate = null;

        if ($manualTemplate === null) {
            $templates = self::getAll();

            foreach (($templates === false ? [] : $templates) as $template) {
                if ($template->is_manual) {
                    $manualTemplate = $template;
                    break;
                }
            }

            if ($manualTemplate === null) {
                // Don't persist a new one when the existing templates can't be read
                $manualTemplate = self::createManual($templates !== false);
            }
        }

        return $manualTemplate;
    }

    /**
     * Create manual mode template
     *
     * @param bool $save Whether to persist the new template
     *
     * @return self
     */
    protected static function createManual(bool $save = true): self
    {
        $template = new self();

        $template->name      = __('[Manual Mode]', 'duplicator');
        $template->notes     = '';
        $template->is_manual = true;

        if ($save) {
            $template->save();
            DupLog::trace('Created manual mode template');
        }
        return $template;
    }
}
