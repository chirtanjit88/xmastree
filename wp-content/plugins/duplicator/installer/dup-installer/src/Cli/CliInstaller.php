<?php

/**
 * Command line installation
 *
 * @package   Duplicator\Installer
 * @copyright (c) 2026, Snap Creek LLC
 */

namespace Duplicator\Installer\Cli;

use Duplicator\Installer\Core\Bootstrap;
use Duplicator\Installer\Core\Hooks\HooksMng;
use Duplicator\Installer\Core\InstState;
use Duplicator\Installer\Core\Params\Items\ParamItem;
use Duplicator\Installer\Core\Params\PrmMng;
use Duplicator\Installer\Core\Security;
use Duplicator\Installer\Utils\Log\Log;
use Duplicator\Installer\Utils\Tests\WP\TestsExecuter;
use DUPX_ArchiveConfig;
use DUPX_Ctrl_ajax;
use DUPX_CTRL;
use DUPX_Ctrl_S4;
use DUPX_Validation_abstract_item;
use DUPX_Validation_database_service;
use Error;
use Exception;
use VendorDuplicator\WpOrg\Requests\Requests;

/**
 * Installs the site from the command line in one run: the same actions the browser calls one request at a time, in
 * the order of the one-step deploy, with every answer taken from the parameters. On a terminal it asks the values the
 * command line did not set and a confirmation; otherwise it never waits for input, and a decision the parameters do
 * not hold ends the run with an error.
 */
final class CliInstaller
{
    /** @var string Constant that marks a command line installation */
    const MODE_CONSTANT = 'DUPX_CLI_INSTALL';
    /** @var int Seconds a final test request may take */
    const TEST_TIMEOUT = 30;
    /** @var array<string,string> Database parameters asked on a terminal, with their question */
    const DB_QUESTIONS = [
        PrmMng::PARAM_DB_HOST => 'Database host',
        PrmMng::PARAM_DB_NAME => 'Database name',
        PrmMng::PARAM_DB_USER => 'Database user',
        PrmMng::PARAM_DB_PASS => 'Database password',
    ];
    /** @var string[] Database parameters required without a terminal */
    const DB_REQUIRED = [
        PrmMng::PARAM_DB_NAME,
        PrmMng::PARAM_DB_USER,
    ];

    private CliInstallation $installation;

    /**
     * @param CliInstallation $installation Installation the bootstrap started
     */
    private function __construct(CliInstallation $installation)
    {
        $this->installation = $installation;
    }

    /**
     * Whether the installer runs from the command line
     *
     * @return bool
     */
    public static function isActive(): bool
    {
        return defined(self::MODE_CONSTANT) && constant(self::MODE_CONSTANT) === true;
    }

    /**
     * Install the site: main.installer.php calls it in the process where installer.php started the installation
     *
     * @return int Exit status
     */
    public static function run(): int
    {
        $installation = CliEnvironment::getInstallation();
        if ($installation === null) {
            CliEnvironment::error('run installer.php to install from the command line.');
            return CliEnvironment::EXIT_USAGE;
        }

        // The first request of an installation: the parameters are loaded from scratch, then the ones of the command
        $_GET[PrmMng::PARAM_CTRL_ACTION]     = 'ctrl-step1';
        $_REQUEST[PrmMng::PARAM_CTRL_ACTION] = 'ctrl-step1';
        HooksMng::getInstance()->addAction('after_params_overwrite', static function ($items) use ($installation): void {
            self::applyParams((array) $items, $installation->params);
        });

        try {
            define(self::MODE_CONSTANT, true);
            CliEnvironment::setServerVars($installation->url, basename(DUPX_INIT) . '/main.installer.php');
            Bootstrap::init();
            Log::setThrowExceptionOnError(true);
            DUPX_CTRL::initRequest();
            if ($installation->info !== null) {
                CliInfo::print($installation->info, $installation->hasUrl);
            } else {
                (new self($installation))->install();
            }
        } catch (CliCancelledException $e) {
            CliEnvironment::error($e->getMessage());
            return CliEnvironment::EXIT_ERROR;
        } catch (Exception | Error $e) {
            CliEnvironment::error(CliEnvironment::toText($e->getMessage()));
            self::logFailure($e);
            return CliEnvironment::EXIT_ERROR;
        }
        return CliEnvironment::EXIT_OK;
    }

    /**
     * Log a failure and print where the log is
     *
     * @param Exception|Error $e Failure
     *
     * @return void
     */
    private static function logFailure($e): void
    {
        if (!class_exists(Security::class, false)) {
            // The installer failed before starting its log
            return;
        }
        try {
            Log::logException($e, Log::LV_DEFAULT, 'CLI INSTALL FAILED: ');
            CliEnvironment::err('Details are in ' . Log::getLogFilePath());
        } catch (Exception | Error $logError) {
            // The log can be the problem itself, such as a folder another user owns
            if ($logError->getMessage() !== $e->getMessage()) {
                CliEnvironment::err('The installer log can\'t be written: ' . $logError->getMessage());
            }
        }
    }

    /**
     * Set the command line parameters over the overwrite parameters, as an overwrite parameters file does; a key the
     * installer does not know is ignored, as there, with a warning
     *
     * @param ParamItem[]         $items  Installer parameters by key
     * @param array<string,mixed> $params Command line parameters by key
     *
     * @return void
     */
    private static function applyParams(array $items, array $params): void
    {
        foreach ($params as $key => $data) {
            if (!isset($items[$key])) {
                CliEnvironment::err(CliEnvironment::style('[warning]', 'yellow', STDERR) . ' unknown parameter ' . $key . ', ignored');
                continue;
            }
            if ($items[$key]->fromArrayData((array) $data) === false) {
                throw new Exception('invalid value for ' . $key . '.');
            }
            $items[$key]->setOveriteStatus();
        }
    }

    /**
     * Run every step of the installation
     *
     * @return void
     */
    private function install(): void
    {
        $this->setDatabaseParams();

        CliEnvironment::out(CliEnvironment::style('Validating...', 'bold'));
        $this->validate();
        $this->confirm();

        CliEnvironment::out(CliEnvironment::style('Setting the parameters...', 'bold'));
        $result = self::action(DUPX_Ctrl_ajax::ACTION_SET_PARAMS_S1);
        if (!$result['isValid']) {
            throw new Exception('Invalid parameters: ' . $result['nextStepMessagesHtml']);
        }

        self::runChunks('Decompressing the database', DUPX_Ctrl_ajax::ACTION_DB_GUNZIP, static function (array $data): bool {
            return self::passToDone($data['pass'] ?? null);
        });
        self::runChunks('Extracting the files', DUPX_Ctrl_ajax::ACTION_EXTRACTION, static function (array $data): bool {
            return self::passToDone($data['pass'] ?? null);
        });
        self::runChunks('Installing the database', DUPX_Ctrl_ajax::ACTION_DBINSTALL, static function (array $data): bool {
            if (!empty($data['is_error'])) {
                throw new Exception('Database install error: ' . ($data['error_msg'] ?? ''));
            }
            if (!empty($data['continue_chunking'])) {
                return false;
            }
            if (!empty($data['pass'])) {
                return true;
            }
            throw new Exception('Database install did not pass.');
        });
        $lastChunkPos = null;
        self::runChunks('Updating the site', DUPX_Ctrl_ajax::ACTION_WEBSITE_UPDATE, static function (array $data) use (&$lastChunkPos): bool {
            $step = (array) ($data['step3'] ?? []);
            if ((int) ($step['chunk'] ?? 0) === 1) {
                if ($step['chunkPos'] === $lastChunkPos) {
                    throw new Exception('The site update is stuck at the same position.');
                }
                $lastChunkPos = $step['chunkPos'];
                return false;
            }
            if ((int) ($step['pass'] ?? 0) === 1) {
                return true;
            }
            throw new Exception('The site update failed: ' . ($step['error_msg'] ?? 'no result'));
        });

        CliEnvironment::out(CliEnvironment::style('Running the final tests...', 'bold'));
        self::finalTests();

        DUPX_Ctrl_S4::updateFinalReport();
        self::action(DUPX_Ctrl_ajax::ACTION_SET_AUTO_CLEAN_FILES);
        CliReport::printNotices();

        // Last: the finalization can remove the installer files, this code and its log included
        $logPath = CliFinalizer::run(Log::getLogFilePath());
        CliEnvironment::out(CliEnvironment::style('Installation completed:', 'green') . ' ' . PrmMng::getInstance()->getValue(PrmMng::PARAM_URL_NEW));
        CliEnvironment::out('Log: ' . $logPath);
    }

    /**
     * Database parameters the command line did not set: asked on a terminal, required otherwise
     *
     * @return void
     */
    private function setDatabaseParams(): void
    {
        if (InstState::dbDoNothing() || DUPX_ArchiveConfig::getInstance()->isDBExcluded()) {
            return;
        }

        $params = PrmMng::getInstance();
        foreach (self::DB_QUESTIONS as $key => $question) {
            if (isset($this->installation->params[$key])) {
                continue;
            }
            $value = (string) $params->getValue($key);
            if (CliEnvironment::isInteractive()) {
                $value = CliEnvironment::ask($question, $value, $key === PrmMng::PARAM_DB_PASS);
                if (!$params->setValue($key, $value)) {
                    throw new Exception('invalid ' . strtolower($question) . '.');
                }
            } elseif (strlen($value) === 0 && in_array($key, self::DB_REQUIRED, true)) {
                throw new Exception('--' . array_search($key, CliEnvironment::PARAM_OPTIONS, true) . ' is required.');
            }
        }
        $params->save();
    }

    /**
     * Run the validation: a failure stops the installation, and so do warnings that need to be accepted unless
     * --accept-warnings is passed
     *
     * @return void
     */
    private function validate(): void
    {
        $level = (int) self::action(DUPX_Ctrl_ajax::ACTION_VALIDATE)['mainLevel'];
        CliReport::printTests();
        self::checkValidationLevel($level, $this->installation->acceptWarnings);

        // Running the command accepts the terms and notices the installer page asks to accept
        $params = PrmMng::getInstance();
        $params->setValue(PrmMng::PARAM_ACCEPT_TERM_COND, true);
        $params->save();
    }

    /**
     * Stop the installation on the validation result: a failure always, warnings that need to be accepted unless they
     * are accepted
     *
     * @param int  $level          Main validation level
     * @param bool $acceptWarnings Whether --accept-warnings is passed
     *
     * @return void
     */
    public static function checkValidationLevel(int $level, bool $acceptWarnings): void
    {
        if ($level <= DUPX_Validation_abstract_item::LV_FAIL) {
            throw new Exception('validation failed.');
        }
        if ($level <= DUPX_Validation_abstract_item::LV_HARD_WARNING && !$acceptWarnings) {
            throw new Exception('validation warnings, check them and pass --accept-warnings.');
        }
    }

    /**
     * Show what the installation will do, as the confirmation of the installer page, and ask to proceed: on a terminal
     * always, unless --yes; otherwise an installation that overwrites an existing site or database needs --yes
     *
     * @return void
     */
    private function confirm(): void
    {
        CliReport::printBlock(CliEnvironment::toText((string) self::action(DUPX_Ctrl_ajax::ACTION_PROCEED_CONFIRM_DIALOG)));
        $proceed = self::proceedsWithoutAsking($this->installation->yes, CliEnvironment::isInteractive(), static function (): bool {
            $tables = (InstState::dbDoNothing() ? 0 : DUPX_Validation_database_service::getInstance()->getDBActionAffectedTablesCount());
            return $tables > 0 || InstState::isOverwrite();
        });
        if ($proceed) {
            return;
        }
        if (!CliEnvironment::confirm(CliEnvironment::style('This process cannot be undone.', 'yellow') . ' Proceed?')) {
            throw new CliCancelledException();
        }
    }

    /**
     * Whether the installation proceeds without asking for confirmation: with --yes, or without a terminal when it does
     * not overwrite an existing site or database, which there needs --yes
     *
     * @param bool            $yes         Whether --yes is passed
     * @param bool            $interactive Whether a terminal can answer
     * @param callable():bool $overwrites  Whether the installation overwrites an existing site or database
     *
     * @return bool true to proceed, false to ask on the terminal
     */
    public static function proceedsWithoutAsking(bool $yes, bool $interactive, callable $overwrites): bool
    {
        if ($yes) {
            return true;
        }
        if ($interactive) {
            return false;
        }
        if ($overwrites()) {
            throw new Exception('the installation overwrites an existing site or database, pass --yes.');
        }
        return true;
    }

    /**
     * Call a chunked action until it is done, printing its progress
     *
     * @param string                             $title  Step title
     * @param string                             $action Action name
     * @param callable(array<string,mixed>):bool $isDone Whether the result ends the step, throwing on a failure
     *
     * @return void
     */
    private static function runChunks(string $title, string $action, callable $isDone): void
    {
        CliEnvironment::out(CliEnvironment::style($title . '...', 'bold'));
        $lastPerc = -1;
        do {
            $data = (array) self::action($action);
            if (isset($data['perc']) && ($perc = (int) floor((float) rtrim((string) $data['perc'], '%') / 10) * 10) > $lastPerc) {
                CliEnvironment::out('  ' . $perc . '%');
                $lastPerc = $perc;
            }
        } while (!$isDone($data));
    }

    /**
     * Run an installer action inside an output buffer, as the AJAX controller does: output an action prints is
     * logged, not shown
     *
     * @param string $action Action name
     *
     * @return mixed Action result
     */
    private static function action(string $action)
    {
        ob_start();
        try {
            return DUPX_Ctrl_ajax::actions($action);
        } finally {
            $output = trim((string) ob_get_clean());
            if (strlen($output) > 0) {
                Log::info('CLI ACTION [' . $action . '] UNEXPECTED OUTPUT:' . "\n" . $output);
            }
        }
    }

    /**
     * Result of the pass value of the gunzip and extraction actions
     *
     * @param mixed $pass Pass value, 1 done and -1 to call again
     *
     * @return bool
     */
    private static function passToDone($pass): bool
    {
        if ((int) $pass === 1) {
            return true;
        }
        if ((int) $pass === -1) {
            return false;
        }
        throw new Exception('Unknown pass value ' . Log::v2str($pass));
    }

    /**
     * Load the front-end and the login page of the new site, as the installer page does; a failure is a warning
     *
     * @return void
     */
    private static function finalTests(): void
    {
        self::action(DUPX_Ctrl_ajax::ACTION_FINAL_TESTS_PREPARE);
        if (!InstState::dbDoNothing()) {
            foreach (['front-end' => TestsExecuter::getFrontendUrl(), 'login page' => TestsExecuter::getBackendUrl()] as $name => $url) {
                try {
                    $response = Requests::get($url, [], [
                        'timeout'    => self::TEST_TIMEOUT,
                        'verify'     => false,
                        'verifyname' => false,
                    ]);
                    $ok       = $response->success && json_decode($response->body, true) === true;
                } catch (Exception $e) {
                    $ok = false;
                }
                Log::info('CLI FINAL TEST ' . $name . ': ' . ($ok ? 'OK' : 'FAIL'));
                if (!$ok) {
                    CliEnvironment::out('  ' . CliEnvironment::style('[warning]', 'yellow') . ' ' . $name . ' test failed');
                }
            }
        }
        self::action(DUPX_Ctrl_ajax::ACTION_FINAL_TESTS_AFTER);
    }
}
