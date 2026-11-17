<?php

/**
 * Finalization of a command line installation
 *
 * @package   Duplicator\Installer
 * @copyright (c) 2026, Snap Creek LLC
 */

namespace Duplicator\Installer\Cli;

use Duplicator\Installer\Core\Params\PrmMng;
use Duplicator\Installer\Utils\Log\Log;
use Exception;

/**
 * Runs the actions of the first administrator login, which remove the installer files, so that no login is needed:
 * finalize-site.php does it in a PHP process of its own, with a timeout
 */
final class CliFinalizer
{
    /** @var int Seconds the finalization may take */
    const TIMEOUT = 120;
    /** @var string Prefix of the result line the finalization process prints */
    const RESULT_PREFIX = 'DUPX_CLI_FINALIZE_RESULT:';

    /**
     * Finalize the installed site; a failure is a warning, the site stays installed
     *
     * @param string $logPath Path of the installer log
     *
     * @return string Path of the installer log, moved when the installer files are removed
     */
    public static function run(string $logPath): string
    {
        CliEnvironment::out(CliEnvironment::style('Finalizing...', 'bold'));
        $params = PrmMng::getInstance();
        try {
            $result = self::runProcess([
                'server'       => CliEnvironment::getServerVars($params->getValue(PrmMng::PARAM_URL_NEW), 'wp-admin/index.php'),
                'wpLoad'       => $params->getValue(PrmMng::PARAM_PATH_WP_CORE_NEW) . '/wp-load.php',
                'resultPrefix' => self::RESULT_PREFIX,
            ]);
        } catch (Exception $e) {
            Log::info('CLI FINALIZE FAILED: ' . $e->getMessage());
            CliEnvironment::out('  ' . CliEnvironment::style('[warning]', 'yellow') . ' ' . $e->getMessage() . ', log in as administrator to finish');
            return $logPath;
        }

        foreach (array_keys(array_filter($result['files'], fn($removed): bool => !$removed)) as $path) {
            CliEnvironment::out('  ' . CliEnvironment::style('[warning]', 'yellow') . ' can\'t remove ' . $path);
        }
        if (count($result['files']) === 0) {
            CliEnvironment::out('  installer files kept');
            return $logPath;
        }
        foreach ($result['stored'] as $stored) {
            if (basename($stored) === basename($logPath)) {
                return $stored;
            }
        }
        return $logPath;
    }

    /**
     * Run the finalization process and read its result
     *
     * @param array{server:array<string,string>,wpLoad:string,resultPrefix:string} $data Data of the process
     *
     * @return array{files:array<string,bool>,stored:string[]}
     */
    private static function runProcess(array $data): array
    {
        $env     = array_merge(getenv(), ['DUPX_CLI_FINALIZE' => (string) json_encode($data)]);
        $command = [
            PHP_BINARY,
            __DIR__ . '/finalize-site.php',
        ];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, DUPX_ROOT, $env);
        if (!is_resource($process)) {
            throw new Exception('finalize failed: can\'t start PHP');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = '';
        $start  = time();
        do {
            $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            $status  = proc_get_status($process);
            if ($status['running'] && time() - $start > self::TIMEOUT) {
                proc_terminate($process, 9);
                throw new Exception('finalize timed out');
            }
            if ($status['running']) {
                usleep(100000);
            }
        } while ($status['running']);
        $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        proc_close($process);

        $pos    = strrpos($output, self::RESULT_PREFIX);
        $result = ($pos === false ? null : json_decode(trim(substr($output, $pos + strlen(self::RESULT_PREFIX))), true));
        Log::info('CLI FINALIZE OUTPUT:' . "\n" . ($pos === false ? $output : substr($output, 0, $pos)));
        if (!is_array($result)) {
            throw new Exception('finalize failed, see the log');
        }
        if (empty($result['success'])) {
            throw new Exception('finalize failed: ' . ($result['error'] ?? ''));
        }
        return [
            'files'  => (array) ($result['files'] ?? []),
            'stored' => (array) ($result['stored'] ?? []),
        ];
    }
}
