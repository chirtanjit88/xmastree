<?php

/**
 * Entity API error
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Exception;
use Throwable;
use WP_Error;

/**
 * Entity API failure with a stable error code and the matching HTTP status.
 */
final class EntityApiException extends Exception
{
    const CODE_NOT_FOUND        = 'not_found';
    const CODE_INVALID_ARGUMENT = 'invalid_argument';
    const CODE_UNAVAILABLE      = 'unavailable';
    const CODE_CONFLICT         = 'conflict';
    const CODE_FAILED           = 'failed';

    private const STATUSES = [
        self::CODE_NOT_FOUND        => 404,
        self::CODE_INVALID_ARGUMENT => 400,
        self::CODE_UNAVAILABLE      => 403,
        self::CODE_CONFLICT         => 409,
        self::CODE_FAILED           => 500,
    ];

    private string $errorCode;

    /**
     * @param string     $errorCode One of the CODE_* constants
     * @param string     $message   Translated message for the caller
     * @param ?Throwable $previous  Underlying failure
     */
    public function __construct(string $errorCode, string $message, ?Throwable $previous = null)
    {
        if (!isset(self::STATUSES[$errorCode])) {
            throw new Exception('Unknown entity API error code: ' . $errorCode);
        }
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
    }

    /**
     * @return string One of the CODE_* constants
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return int HTTP status matching the error code
     */
    public function getStatus(): int
    {
        return self::STATUSES[$this->errorCode];
    }

    /**
     * @return WP_Error The error as duplicator_<code> with the status in its data
     */
    public function toWpError(): WP_Error
    {
        return new WP_Error('duplicator_' . $this->errorCode, $this->getMessage(), ['status' => $this->getStatus()]);
    }
}
