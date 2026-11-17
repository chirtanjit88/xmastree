<?php

/**
 * Validation tests and final report notices on the command line
 *
 * @package   Duplicator\Installer
 * @copyright (c) 2026, Snap Creek LLC
 */

namespace Duplicator\Installer\Cli;

use Duplicator\Installer\Core\Params\PrmMng;
use DUPX_Ctrl_S4;
use DUPX_NOTICE_ITEM;
use DUPX_NOTICE_MANAGER;
use DUPX_Validation_abstract_item;
use DUPX_Validation_manager;

/**
 * Prints what the installer pages show as results: the validation tests that are not good and the final report
 * notices, one line each, without the passwords of the parameters
 */
final class CliReport
{
    /** @var string[] Validation categories, in the order of the installer page */
    const TEST_CATEGORIES = [
        DUPX_Validation_manager::CAT_GENERAL,
        DUPX_Validation_manager::CAT_FILESYSTEM,
        DUPX_Validation_manager::CAT_PHP,
        DUPX_Validation_manager::CAT_DATABASE,
    ];

    /** @var array<int,array{string,string}> Label and style of the final report notices by level */
    const NOTICE_LEVELS = [
        DUPX_NOTICE_ITEM::NOTICE       => [
            'notice',
            'bold',
        ],
        DUPX_NOTICE_ITEM::SOFT_WARNING => [
            'soft warning',
            'yellow',
        ],
        DUPX_NOTICE_ITEM::HARD_WARNING => [
            'hard warning',
            'yellow',
        ],
        DUPX_NOTICE_ITEM::CRITICAL     => [
            'critical',
            'red',
        ],
        DUPX_NOTICE_ITEM::FATAL        => [
            'fatal',
            'red',
        ],
    ];

    /**
     * Print the validation tests that are not good; under the ones that need attention, their status
     *
     * @return void
     */
    public static function printTests(): void
    {
        $manager = DUPX_Validation_manager::getInstance();
        foreach (self::TEST_CATEGORIES as $category) {
            foreach ($manager->getTestsCategory($category) as $test) {
                $testLevel = $test->test();
                if (!$test->display() || $testLevel > DUPX_Validation_abstract_item::LV_SOFT_WARNING) {
                    continue;
                }
                $levelName  = DUPX_Validation_abstract_item::resultLevelToString($testLevel);
                $levelStyle = ($testLevel <= DUPX_Validation_abstract_item::LV_FAIL ? 'red' : 'yellow');
                CliEnvironment::out('  ' . CliEnvironment::style('[' . $levelName . ']', $levelStyle) . ' ' . CliEnvironment::toText($test->getTitle()));
                if ($testLevel <= DUPX_Validation_abstract_item::LV_HARD_WARNING) {
                    CliEnvironment::out('      ' . self::hidePasswords(self::testStatus($test->getContent())));
                }
            }
        }
    }

    /**
     * Print the notices the final page of the installer lists: their details are in the log
     *
     * @return void
     */
    public static function printNotices(): void
    {
        $nManager = DUPX_NOTICE_MANAGER::getInstance();
        $nManager->sortFinalReport();
        foreach ($nManager->getFinalReportNotices() as $notice) {
            if (!isset(self::NOTICE_LEVELS[$notice->level]) || !self::isFinalPageNotice($notice)) {
                continue;
            }
            [
                $label,
                $style,
            ] = self::NOTICE_LEVELS[$notice->level];
            CliEnvironment::out('  ' . CliEnvironment::style('[' . $label . ']', $style) . ' ' . CliEnvironment::toText($notice->shortMsg));
        }
    }

    /**
     * Print a text indented under the current step, without the passwords of the parameters
     *
     * @param string $text Text
     *
     * @return void
     */
    public static function printBlock(string $text): void
    {
        CliEnvironment::out('  ' . str_replace("\n", "\n  ", self::hidePasswords($text)));
    }

    /**
     * Whether the final page of the installer lists the notice: one of its sections shows its level
     *
     * @param DUPX_NOTICE_ITEM $notice Notice
     *
     * @return bool
     */
    private static function isFinalPageNotice(DUPX_NOTICE_ITEM $notice): bool
    {
        foreach ($notice->sections as $section) {
            if (isset(DUPX_Ctrl_S4::NOTICES_MIN_LEVELS[$section]) && $notice->level >= DUPX_Ctrl_S4::NOTICES_MIN_LEVELS[$section]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Status of a test, without the details and the troubleshooting the installer page shows when the test is opened:
     * its STATUS section, or the text before the first section
     *
     * @param string $html Test content
     *
     * @return string One line of text
     */
    private static function testStatus(string $html): string
    {
        $sections = preg_split('#<div class="sub-title">\s*#i', $html);
        $status   = $sections[0];
        if (count($sections) > 1 && stripos($sections[1], 'STATUS</div>') === 0) {
            $status = substr($sections[1], strlen('STATUS</div>'));
        } elseif (strlen(trim(strip_tags($status))) === 0 && count($sections) > 1) {
            // A test without a status starts with its details
            $status = (string) preg_replace('#^.*?</div>#s', '', $sections[1], 1);
        }
        return str_replace("\n", ' ', CliEnvironment::toText($status));
    }

    /**
     * Replace the passwords of the parameters in a text shown on the command line
     *
     * @param string $text Text
     *
     * @return string
     */
    private static function hidePasswords(string $text): string
    {
        $passwords = array_map(fn($param) => $param->getValue(), PrmMng::getInstance()->getPasswordParams());
        return CliEnvironment::hideSecrets($text, array_values($passwords));
    }
}
