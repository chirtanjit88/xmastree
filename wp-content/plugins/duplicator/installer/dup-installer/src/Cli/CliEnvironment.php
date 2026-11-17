<?php

/**
 * Command line environment shared by the installer bootstrap and the main installer
 *
 * @package   Duplicator\Installer
 * @copyright (c) 2026, Snap Creek LLC
 */

namespace Duplicator\Installer\Cli;

use Exception;

/**
 * Reads the command line options and gives the installer the request data it reads from a web server, built from the
 * URL the site answers on. Inlined in installer.php, so it depends on nothing.
 */
class CliEnvironment
{
    const EXIT_OK    = 0;
    const EXIT_ERROR = 1;
    const EXIT_USAGE = 2;

    /** @var array<string,string> Options that set an installer parameter, with the parameter they set */
    const PARAM_OPTIONS = [
        'url'      => 'url_new',
        'dbaction' => 'dbaction',
        'dbhost'   => 'dbhost',
        'dbname'   => 'dbname',
        'dbuser'   => 'dbuser',
        'dbpass'   => 'dbpass',
    ];
    /** @var string[] Other options that take a value */
    const VALUE_OPTIONS = [
        'params',
        'archive-path',
        'archive-password',
        'format',
    ];
    /** @var string[] Options without a value */
    const FLAG_OPTIONS = [
        'accept-warnings',
        'yes',
        'ignore-owner',
        'info',
        'help',
    ];
    /** @var array<string,string> ANSI codes of the text styles */
    const STYLES = [
        'bold'   => '1',
        'red'    => '1;31',
        'green'  => '1;32',
        'yellow' => '1;33',
    ];
    /** @var int Shortest secret replaced in a text: a shorter one would replace parts of words */
    const MIN_HIDDEN_SECRET_LEN = 4;
    /** @var string[] Output formats of --info */
    const INFO_FORMATS = [
        'text',
        'json',
    ];

    /** @var ?CliInstallation Installation the bootstrap hands to the main installer */
    private static ?CliInstallation $installation = null;
    /** @var ?string Folder of the archive or the archive itself, from --archive-path */
    private static ?string $archiveLocation = null;

    /**
     * Parse the command line options, written --name=value or --flag
     *
     * @param string[] $argv Command line arguments, the script name first
     *
     * @return array<string,mixed> Values by option name; null for an option not passed, false for a flag not passed
     */
    public static function parseOptions(array $argv): array
    {
        $options = array_fill_keys(array_merge(array_keys(self::PARAM_OPTIONS), self::VALUE_OPTIONS), null);
        $options = array_merge($options, array_fill_keys(self::FLAG_OPTIONS, false));

        foreach (array_slice($argv, 1) as $arg) {
            if (strpos($arg, '--') !== 0) {
                throw new Exception('Unexpected argument ' . $arg);
            }
            $parts = explode('=', substr($arg, 2), 2);
            $name  = $parts[0];
            $value = $parts[1] ?? null;

            if (in_array($name, self::FLAG_OPTIONS, true)) {
                if ($value !== null) {
                    throw new Exception('--' . $name . ' takes no value');
                }
                $options[$name] = true;
            } elseif (isset(self::PARAM_OPTIONS[$name]) || in_array($name, self::VALUE_OPTIONS, true)) {
                // An empty database password is valid
                if ($value === null || (strlen($value) === 0 && $name !== 'dbpass')) {
                    throw new Exception('--' . $name . ' needs a value');
                }
                $options[$name] = $value;
            } else {
                throw new Exception('Unknown option --' . $name);
            }
        }

        return $options;
    }

    /**
     * Installer parameters of the options: the --params file, read only, with the parameter options over it
     *
     * @param array<string,mixed> $options Parsed options
     *
     * @return array<string,mixed> Parameters by key, in the format of the overwrite parameters
     */
    public static function getInstallerParams(array $options): array
    {
        $params = [];
        if ($options['params'] !== null) {
            if (!is_readable($options['params'])) {
                throw new Exception('can\'t read ' . $options['params'] . '.');
            }
            $params = json_decode((string) file_get_contents($options['params']), true);
            if (!is_array($params)) {
                throw new Exception($options['params'] . ' is not a JSON object.');
            }
        }

        foreach (self::PARAM_OPTIONS as $option => $key) {
            if ($options[$option] !== null) {
                $params[$key]          = (isset($params[$key]) && is_array($params[$key]) ? $params[$key] : []);
                $params[$key]['value'] = $options[$option];
            }
        }
        return $params;
    }

    /**
     * Set where the bootstrap looks for the archive, as the archive query parameter does on the web
     *
     * @param ?string $location Folder of the archive or the archive itself, null for the installer folder
     *
     * @return void
     */
    public static function setArchiveLocation(?string $location): void
    {
        self::$archiveLocation = $location;
    }

    /**
     * Where the bootstrap looks for the archive, null when the command line did not set it
     *
     * @return ?string
     */
    public static function getArchiveLocation(): ?string
    {
        return self::$archiveLocation;
    }

    /**
     * Hand the installation to the main installer, which runs next in the same process
     *
     * @param CliInstallation $installation Installation
     *
     * @return void
     */
    public static function startInstallation(CliInstallation $installation): void
    {
        self::$installation = $installation;
    }

    /**
     * The installation the bootstrap started, null when the main installer did not start from installer.php
     *
     * @return ?CliInstallation
     */
    public static function getInstallation(): ?CliInstallation
    {
        return self::$installation;
    }

    /**
     * Set the request data a web server would give a script served at the URL: host, scheme and path
     *
     * @param string $siteUrl    URL of the folder that holds installer.php
     * @param string $scriptPath Path of the running script relative to that folder
     *
     * @return void
     */
    public static function setServerVars(string $siteUrl, string $scriptPath): void
    {
        $vars = self::getServerVars($siteUrl, $scriptPath);
        if (!isset($vars['HTTPS'])) {
            unset($_SERVER['HTTPS']);
        }
        $_SERVER = array_merge($_SERVER, $vars);
    }

    /**
     * Request data a web server would give a script served at the URL: host, scheme and path
     *
     * @param string $siteUrl    URL of the folder that holds the script
     * @param string $scriptPath Path of the script relative to that folder
     *
     * @return array<string,string>
     */
    public static function getServerVars(string $siteUrl, string $scriptPath): array
    {
        $parts = parse_url($siteUrl);
        if (!is_array($parts) || !isset($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            throw new Exception('The URL ' . $siteUrl . ' is not a valid http or https URL');
        }

        $isHttps = ($parts['scheme'] === 'https');
        $port    = (int) ($parts['port'] ?? ($isHttps ? 443 : 80));
        $script  = rtrim($parts['path'] ?? '', '/') . '/' . ltrim($scriptPath, '/');
        $vars    = [
            'HTTP_HOST'       => $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''),
            'SERVER_NAME'     => $parts['host'],
            'SERVER_PORT'     => (string) $port,
            'REQUEST_METHOD'  => 'GET',
            'REQUEST_URI'     => $script,
            'SCRIPT_NAME'     => $script,
            'PHP_SELF'        => $script,
            'QUERY_STRING'    => '',
            'REMOTE_ADDR'     => '127.0.0.1',
            // The CLI SAPI does not define it, and the bootstrap and the log header read it
            'SERVER_SOFTWARE' => 'cli',
            'HTTP_USER_AGENT' => 'Duplicator installer CLI',
        ];
        if ($isHttps) {
            $vars['HTTPS'] = 'on';
        }
        return $vars;
    }

    /**
     * Check that the command runs as the owner of the site folder: the files it writes get the user who runs it, and
     * the web server, which runs as the folder owner, could not write them otherwise
     *
     * @param string $folder Site folder
     *
     * @return ?string Error message, null when the users match or can't be read, as on Windows
     */
    public static function getOwnerError(string $folder): ?string
    {
        if (!function_exists('posix_geteuid') || ($owner = @fileowner($folder)) === false) {
            return null;
        }
        $user = posix_geteuid();
        if ($user === $owner) {
            return null;
        }

        $userName  = self::userName($user);
        $ownerName = self::userName($owner);
        return 'run as ' . $ownerName . ', the owner of ' . $folder . ' (now ' . $userName . '), or pass --ignore-owner.';
    }

    /**
     * Name of a system user
     *
     * @param int $uid User ID
     *
     * @return string Name, or the ID when the name can't be read
     */
    private static function userName(int $uid): string
    {
        $info = (function_exists('posix_getpwuid') ? posix_getpwuid($uid) : false);
        return (is_array($info) ? $info['name'] : 'user ' . $uid);
    }

    /**
     * Whether a person can answer: the standard input is a terminal. Cron, CI, pipes and agents get no question,
     * so the command never waits for an answer nobody can give.
     *
     * @return bool
     */
    public static function isInteractive(): bool
    {
        return function_exists('stream_isatty') && @stream_isatty(STDIN);
    }

    /**
     * Ask a value on the terminal
     *
     * @param string $question Question
     * @param string $default  Value of an empty answer
     * @param bool   $hidden   Whether the answer is not shown, as for a password
     *
     * @return string
     */
    public static function ask(string $question, string $default = '', bool $hidden = false): string
    {
        $shownDefault = ($hidden || strlen($default) === 0 ? '' : ' [' . $default . ']');
        fwrite(STDOUT, $question . $shownDefault . ': ');
        $echoOff = ($hidden && function_exists('shell_exec'));
        if ($echoOff) {
            shell_exec('stty -echo 2>/dev/null');
        }
        $line = fgets(STDIN);
        if ($echoOff) {
            shell_exec('stty echo 2>/dev/null');
            fwrite(STDOUT, "\n");
        }
        if ($line === false) {
            throw new Exception('no answer.');
        }
        $line = rtrim($line, "\r\n");
        return (strlen($line) > 0 ? $line : $default);
    }

    /**
     * Ask a yes or no question on the terminal, no by default
     *
     * @param string $question Question
     *
     * @return bool
     */
    public static function confirm(string $question): bool
    {
        return in_array(strtolower(trim(self::ask($question . ' [y/N]'))), ['y', 'yes'], true);
    }

    /**
     * Print a line on standard output
     *
     * @param string $message Message
     *
     * @return void
     */
    public static function out(string $message): void
    {
        fwrite(STDOUT, $message . "\n");
        // Keep the order with the error lines when both streams go to the same pipe
        fflush(STDOUT);
    }

    /**
     * Print a line on standard error
     *
     * @param string $message Message
     *
     * @return void
     */
    public static function err(string $message): void
    {
        fwrite(STDERR, $message . "\n");
    }

    /**
     * Print an error on standard error
     *
     * @param string $message Message, without the Error prefix
     *
     * @return void
     */
    public static function error(string $message): void
    {
        self::err(self::style('Error:', 'red', STDERR) . ' ' . $message);
    }

    /**
     * Text in a style, on a terminal only: redirected output and NO_COLOR get the plain text
     *
     * @param string   $text   Text
     * @param string   $style  One of STYLES
     * @param resource $stream Stream the text is printed on
     *
     * @return string
     */
    public static function style(string $text, string $style, $stream = STDOUT): string
    {
        if (!isset(self::STYLES[$style]) || getenv('NO_COLOR') !== false || !function_exists('stream_isatty') || !@stream_isatty($stream)) {
            return $text;
        }
        return "\033[" . self::STYLES[$style] . 'm' . $text . "\033[0m";
    }

    /**
     * Plain text of an HTML message
     *
     * @param string $html Message
     *
     * @return string
     */
    public static function toText(string $html): string
    {
        // Style and script blocks are not text
        $html = (string) preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', '', $html);
        // Line breaks of the source are spaces, as in a browser; blocks end a line, list items start one,
        // table cells are separated by a space
        $html = (string) preg_replace('/\s+/', ' ', $html);
        $html = (string) preg_replace('#<br\s*/?>|</(?:p|div|tr|ul|table)>|(?=<(?:ul|table)\b)#i', "\n", $html);
        $html = (string) preg_replace('#<li\b[^>]*>#i', "\n- ", $html);
        $html = (string) preg_replace('#</t[dh]>#i', ' ', $html);
        // A non-breaking space is a space
        $text = str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
        // No spaces around the lines, no empty lines
        $text = (string) preg_replace('/^ +| +$/m', '', (string) preg_replace('/ +/', ' ', $text));
        return trim((string) preg_replace("/\n+/", "\n", $text));
    }

    /**
     * Replace secrets, such as passwords, in a text shown on the command line
     *
     * @param string                                    $text    Text
     * @param array<int,string|array<array-key,string>> $secrets Secrets to hide; a list holds several, such as the
     *                                                           user password resets
     *
     * @return string
     */
    public static function hideSecrets(string $text, array $secrets): string
    {
        foreach ($secrets as $secret) {
            if (is_array($secret)) {
                $text = self::hideSecrets($text, array_values($secret));
                continue;
            }
            if (strlen((string) $secret) >= self::MIN_HIDDEN_SECRET_LEN) {
                $text = str_replace($secret, '*****', $text);
            }
        }
        return $text;
    }
}
