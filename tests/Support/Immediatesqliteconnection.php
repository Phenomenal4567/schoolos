<?php

namespace Tests\Support;

use Illuminate\Database\SQLiteConnection;
use Throwable;

/**
 * Test-harness-only override for AttendanceConcurrencyTest's worker
 * processes (tests/Support/attendance_race_worker.php) — never
 * registered in the application itself.
 *
 * Why this exists: PDO's beginTransaction() always issues a plain
 * `BEGIN` against SQLite, which SQLite treats as DEFERRED — no lock is
 * taken until the transaction's first statement runs, and a *read*
 * first (exactly what AttendanceRepository::mark()'s lockForUpdate()
 * query is, since lockForUpdate() itself compiles to nothing on
 * SQLite — see that method's doc comment) only acquires a SHARED lock.
 * When the transaction later tries to write and upgrade that SHARED
 * lock to a write lock, and another connection already holds the
 * write lock, SQLite returns SQLITE_BUSY *immediately* — this
 * particular failure mode does not consult PRAGMA busy_timeout at
 * all, because busy_timeout only governs waiting to acquire a lock a
 * transaction doesn't already hold a weaker version of. This is
 * documented SQLite behavior (see sqlite.org/lang_transaction.html on
 * DEFERRED vs IMMEDIATE), not a Laravel or SQLite bug, and it's the
 * literal cause of the "database is locked" failures this override
 * fixes: both workers read first, so both take a SHARED lock, and
 * whichever loses the race to upgrade gets an unretryable SQLITE_BUSY
 * a few milliseconds in — nowhere near the 5-second busy_timeout this
 * suite configures.
 *
 * The fix is to open the transaction as IMMEDIATE instead, which
 * takes the write lock (RESERVED) up front, before either worker has
 * read anything — at that point a genuine lock conflict *does* go
 * through the normal busy-handler wait/retry path that busy_timeout
 * governs, which is what this whole test exists to exercise. PHP has
 * no PDO attribute for this (a core-PHP feature request, still open
 * as of PHP 8.4/8.5 at time of writing), and Laravel's connection
 * classes don't expose a config option for it either — overriding
 * createTransaction() to swap the underlying statement is the
 * established workaround; see e.g.
 * https://laravel-news.com/using-sqlite-in-production-with-laravel
 * ("Transactions" section) for the same technique applied in
 * production.
 *
 * Not applied to AttendanceRepository or any other application code:
 * production targets MySQL, where lockForUpdate() takes a real
 * row-level lock and this deferred/shared-lock scenario doesn't
 * arise. This class exists solely so the SQLite-backed race test can
 * observe mark()'s outcome under genuine contention instead of a
 * SQLite transaction-mode artifact unrelated to the code under test.
 */
class ImmediateSqliteConnection extends SQLiteConnection
{
    /**
     * @return void
     */
    protected function createTransaction()
    {
        if ($this->transactions === 0) {
            $this->reconnectIfMissingConnection();

            try {
                $this->getPdo()->exec('begin immediate transaction');
            } catch (Throwable $e) {
                $this->handleBeginTransactionException($e);
            }
        } elseif ($this->transactions >= 1 && $this->queryGrammar->supportsSavepoints()) {
            $this->createSavepoint();
        }
    }
}