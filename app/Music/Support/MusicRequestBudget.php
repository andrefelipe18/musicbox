<?php

namespace App\Music\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class MusicRequestBudget
{
    private static ?int $deadline = null;

    private static bool $committed = false;

    public static function within(Closure $callback): mixed
    {
        $previous = self::$deadline;
        $previousCommitted = self::$committed;
        $previousBusyTimeout = self::sqliteBusyTimeout();
        $budget = max(1, (int) config('music.request_budget_seconds', 60)) * 1_000_000_000;
        $deadline = hrtime(true) + $budget;
        self::$deadline = $previous === null ? $deadline : min($previous, $deadline);
        self::$committed = false;

        try {
            self::assertAvailable();
            self::limitDatabaseWait();
            $result = $callback();
            if (! self::$committed) {
                self::assertAvailable();
            }

            return $result;
        } finally {
            $committed = self::$committed;
            self::$deadline = $previous;
            self::$committed = $previousCommitted;
            if ($previousBusyTimeout !== null) {
                try {
                    DB::statement('PRAGMA busy_timeout = '.max(0, $previousBusyTimeout));
                } catch (Throwable $exception) {
                    if (! $committed) {
                        throw $exception;
                    }

                    Log::warning('Could not restore SQLite busy timeout after committed music sync.', [
                        'exception_class' => $exception::class,
                    ]);
                }
            }
        }
    }

    public static function remainingSeconds(): ?float
    {
        return self::$deadline === null ? null : (self::$deadline - hrtime(true)) / 1_000_000_000;
    }

    public static function assertAvailable(): void
    {
        $remaining = self::remainingSeconds();
        if ($remaining !== null && $remaining <= 0) {
            throw new RuntimeException('Music catalog synchronization deadline exceeded.');
        }
    }

    public static function limitDatabaseWait(): void
    {
        self::assertAvailable();
        $remaining = self::remainingSeconds();
        if ($remaining !== null && DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA busy_timeout = '.max(1, (int) floor($remaining * 1000)));
        }
    }

    public static function markCommitted(): void
    {
        self::$committed = true;
    }

    private static function sqliteBusyTimeout(): ?int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return null;
        }

        return (int) DB::selectOne('PRAGMA busy_timeout')->timeout;
    }
}
