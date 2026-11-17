<?php

/**
 * Description of the Backup for installer.php --info
 *
 * @package   Duplicator\Installer
 * @copyright (c) 2026, Snap Creek LLC
 */

namespace Duplicator\Installer\Cli;

use Duplicator\Installer\Core\Params\Items\ParamForm;
use Duplicator\Installer\Core\Params\Items\ParamItem;
use Duplicator\Installer\Core\Params\PrmMng;
use DUPX_ArchiveConfig;

/**
 * Prints the Backup and the parameters meant for the command line, with their values and the values they accept for
 * this Backup, as text or JSON
 */
final class CliInfo
{
    /** @var array<int,string> Site types by multisite mode of the archive */
    const SITE_TYPES = [
        0 => 'single site',
        1 => 'multisite with subdomains',
        2 => 'multisite with subfolders',
    ];

    /**
     * Print the description
     *
     * @param string $format Output format, text or json
     * @param bool   $hasUrl Whether the command line set the URL the site answers on
     *
     * @return void
     */
    public static function print(string $format, bool $hasUrl): void
    {
        $params = self::paramsInfo();
        if (!$hasUrl) {
            // The request data are built from a placeholder URL
            $params[PrmMng::PARAM_URL_NEW]['value'] = null;
        }
        $backup = self::backupInfo();

        if ($format === 'json') {
            $info = [
                'backup' => $backup,
                'params' => $params,
            ];
            CliEnvironment::out((string) json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }

        CliEnvironment::out(CliEnvironment::style('Backup', 'bold'));
        foreach ($backup as $name => $value) {
            if ($name !== 'subsites') {
                CliEnvironment::out('  ' . str_pad($name . ':', 20) . self::valueToText($value));
            }
        }
        foreach ($backup['subsites'] ?? [] as $subsite) {
            CliEnvironment::out('  subsite ' . $subsite['id'] . ': ' . $subsite['url'] . ' (' . $subsite['title'] . ')');
        }

        CliEnvironment::out('');
        CliEnvironment::out(CliEnvironment::style('Parameters of the --params file, as {"<key>": {"value": <value>}}', 'bold'));
        foreach ($params as $key => $param) {
            CliEnvironment::out('');
            CliEnvironment::out('  ' . CliEnvironment::style($key, 'bold') . ' (' . $param['type'] . '): ' . $param['description']);
            CliEnvironment::out('    value: ' . self::valueToText($param['value']));
            foreach ($param['values'] ?? [] as $value) {
                CliEnvironment::out('    - ' . self::valueToText($value['value']) . (strlen($value['label']) > 0 ? ': ' . $value['label'] : ''));
            }
        }
    }

    /**
     * Description of the Backup
     *
     * @return array<string,mixed>
     */
    private static function backupInfo(): array
    {
        $archive = DUPX_ArchiveConfig::getInstance();
        $params  = PrmMng::getInstance();
        $info    = [
            'name'          => $archive->package_name,
            'created'       => $archive->created,
            'site'          => self::SITE_TYPES[$archive->mu_mode] ?? 'unknown',
            'url'           => $params->getValue(PrmMng::PARAM_URL_OLD),
            'path'          => $params->getValue(PrmMng::PARAM_PATH_OLD),
            'table prefix'  => $archive->wp_tableprefix,
            'database only' => $archive->isDBOnly(),
            'no database'   => $archive->isDBExcluded(),
            'duplicator'    => $archive->version_dup,
            'wordpress'     => $archive->version_wp,
            'php'           => $archive->version_php,
            'database'      => $archive->version_db,
        ];
        if ($archive->isNetwork()) {
            $info['subsites'] = array_map(fn($subsite): array => [
                'id'    => $subsite->id,
                'url'   => $subsite->fullHomeUrl,
                'title' => $subsite->blogname,
            ], $archive->subsites);
        }
        return $info;
    }

    /**
     * Parameters the --params file can set, with their current value and the values they accept for this Backup
     *
     * @return array<string,array{type:string,description:string,value:mixed,values?:array<array{value:mixed,label:string}>}>
     */
    private static function paramsInfo(): array
    {
        $result    = [];
        $params    = PrmMng::getInstance();
        $passwords = $params->getPasswordParams();
        foreach ($params->getCliParams() as $key => $param) {
            $values = self::acceptedValues($param);
            if ($values === []) {
                // No value is valid for this Backup
                continue;
            }

            $value = $param->getValue();
            $item  = [
                'type'        => self::valueType($value),
                'description' => $param->getDescription(),
                // Passwords are not shown
                'value'       => (isset($passwords[$key]) && strlen((string) $value) > 0 ? '(hidden)' : $value),
            ];
            if ($values !== null) {
                $item['values'] = $values;
            }
            $result[$key] = $item;
        }
        return $result;
    }

    /**
     * Values a parameter accepts for this Backup: its options the installer page shows, or its accepted values
     *
     * @param ParamItem $param Parameter
     *
     * @return ?array<array{value:mixed,label:string}> null for a parameter without a list of values
     */
    private static function acceptedValues(ParamItem $param): ?array
    {
        $accept  = $param->getAcceptValues();
        $options = ($param instanceof ParamForm ? $param->getOptions() : []);
        if (count($options) === 0) {
            return (count($accept) === 0 ? null : array_map(fn($value): array => [
                'value' => $value,
                'label' => '',
            ], array_values($accept)));
        }

        $values = [];
        foreach ($options as $option) {
            // The value types of options and accepted values differ, as in the installer page
            if (!$option->isEnable() || (count($accept) > 0 && !in_array($option->value, $accept))) {
                continue;
            }
            $values[] = [
                'value' => $option->value,
                'label' => CliEnvironment::toText((string) $option->label),
            ];
        }
        return $values;
    }

    /**
     * Name of the type of a parameter value
     *
     * @param mixed $value Value
     *
     * @return string
     */
    private static function valueType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_array($value)) {
            return 'array';
        }
        return 'string';
    }

    /**
     * Text of a value
     *
     * @param mixed $value Value
     *
     * @return string
     */
    private static function valueToText($value): string
    {
        if ($value === null) {
            return '(not set)';
        }
        return (is_string($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
