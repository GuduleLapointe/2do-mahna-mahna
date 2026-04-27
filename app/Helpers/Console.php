<?php
/**
 * Console output helper for CLI scripts.
 *
 * Provides leveled output with automatic caller identification and path normalization.
 *
 * Levels:
 *   notice()  — main milestones, always shown (unless quiet)
 *   detail()  — file operations, indented, paths shortened to relative
 *   verbose() — debug info, only shown with -v
 *   error()   — always logged via error_log, optional die
 */
class Console
{
    private static bool $quiet   = false;
    private static bool $verbose = false;
    private static ?string $outputDir = null;

    public static function init(bool $quiet, bool $verbose): void
    {
        self::$quiet   = $quiet;
        self::$verbose = $verbose && !$quiet;
    }

    /**
     * Register the output directory so detail() can shorten paths relative to it
     * (or relative to APP_DIR when output_dir is inside the project).
     */
    public static function setOutputDir(string $dir): void
    {
        self::$outputDir = rtrim(realpath($dir) ?: $dir, '/');
    }

    /** Main milestones — shown with [caller] prefix unless quiet. */
    public static function notice(string $message): void
    {
        if (self::$quiet) {
            return;
        }
        echo '[' . self::callerTag() . '] ' . $message . "\n";
    }

    /** File operations and secondary info — indented, paths shortened. */
    public static function detail(string $message): void
    {
        if (self::$quiet) {
            return;
        }
        echo '  ' . self::shortenPaths($message) . "\n";
    }

    /** Debug info — only shown with -v. */
    public static function verbose(string $message): void
    {
        if (!self::$verbose) {
            return;
        }
        echo '[' . self::callerTag() . '] ' . $message . "\n";
    }

    /**
     * Errors — always sent to error_log with file:line context.
     * Pass $die = true to halt after logging.
     */
    public static function error(string $message, int $code = 1, bool $die = false): void
    {
        $trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $frame  = $trace[1] ?? $trace[0];
        $location = basename($frame['file'] ?? '') . ':' . ($frame['line'] ?? 0);
        error_log('[' . self::callerTag(1) . '] ERROR ' . $code . ': ' . $message . ' (' . $location . ')');
        if ($die) {
            die($code);
        }
    }

    // -------------------------------------------------------------------------

    private static function callerTag(int $extraDepth = 0): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6 + $extraDepth);
        foreach ($trace as $frame) {
            if (($frame['class'] ?? '') === 'Console') {
                continue;
            }
            $class = $frame['class'] ?? '';
            if ($class) {
                return self::classToTag($class);
            }
            break;
        }
        global $argv;
        return basename($argv[0] ?? 'cli', '.php');
    }

    private static function classToTag(string $class): string
    {
        // HYPEvents_Exporter → hypevents, HTML_Exporter → html, iCal_Exporter → ical
        $tag = preg_replace('/_?Exporter$/i', '', $class);
        return strtolower(str_replace('_', '-', $tag));
    }

    private static function shortenPaths(string $message): string
    {
        // Prefer relative to APP_DIR (covers project output dirs like bundle/standalone/)
        if (defined('APP_DIR') && str_contains($message, APP_DIR)) {
            return str_replace(APP_DIR . '/', '', $message);
        }
        // Temp dir or other out-of-project path: strip output_dir prefix
        if (self::$outputDir && str_contains($message, self::$outputDir)) {
            return str_replace(self::$outputDir . '/', '', $message);
        }
        return $message;
    }
}
