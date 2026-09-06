<?php

/**
 * Standalone worker for Tests\Feature\AttendanceConcurrencyTest.
 *
 * Deliberately outside app/Console/Commands: this is test-harness
 * plumbing to give AttendanceRepository::mark() a second, genuinely
 * concurrent OS process to run in — not application behavior, and not
 * something any route or command should be able to invoke. It boots
 * Laravel the same way artisan itself does (see artisan's own require
 * sequence) but stops short of dispatching a console command, since all
 * this needs is the container, config, and Eloquent — not a registered
 * command signature.
 *
 * Argv (positional, all strings): db_path school_id academic_year_id
 * class_section_id student_id date session status actor_id reason
 * ready_file go_file result_file
 *
 * Protocol: touch ready_file as soon as this process has repointed its
 * own DB connection at db_path and is ready to call mark(); then block
 * until go_file exists (the parent test creates it only once *both*
 * workers have signaled ready, so the two calls below start as close to
 * simultaneously as two independently-scheduled OS processes can); then
 * call mark() and write the outcome to result_file as JSON.
 */

require __DIR__ . '/../../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[
    $dbPath,
    $schoolId,
    $academicYearId,
    $classSectionId,
    $studentId,
    $date,
    $session,
    $status,
    $actorId,
    $reason,
    $readyFile,
    $goFile,
    $resultFile,
] = array_slice($argv, 1);

$reason = $reason === '' ? null : $reason;

// This process's own bootstrap already opened a connection against
// whatever config/database.php + env resolved to (the testing default,
// :memory:) — a database no other process can see. Repoint it at the
// shared file the parent test migrated and seeded before spawning this
// process, and force a fresh PDO handle so the repointing actually
// takes effect.
config(['database.connections.sqlite.database' => $dbPath]);
config(['database.connections.sqlite.busy_timeout' => 5000]);

// Swap in the IMMEDIATE-transaction override (see that class's doc
// comment) so mark()'s DB::transaction() call opens its lock up front
// instead of deferring it past the read — otherwise the loser of the
// two workers' race gets an unretryable SQLITE_BUSY that busy_timeout
// above never gets a chance to apply to. Registered before the purge
// below so the next connection built for 'sqlite' picks it up.
\Illuminate\Database\Connection::resolverFor(
    'sqlite',
    fn (...$args) => new \Tests\Support\ImmediateSqliteConnection(...$args)
);
\Illuminate\Support\Facades\DB::purge('sqlite');

touch($readyFile);

$deadline = microtime(true) + 5.0;
while (!file_exists($goFile)) {
    if (microtime(true) > $deadline) {
        file_put_contents($resultFile, json_encode(['outcome' => 'timeout_waiting_for_go']));
        exit(1);
    }
    usleep(2000);
}

$actor = \App\Models\User::find((int) $actorId);

try {
    $record = (new \App\Repositories\AttendanceRepository())->mark(
        (int) $schoolId,
        (int) $academicYearId,
        (int) $classSectionId,
        (int) $studentId,
        $date,
        $session,
        $status,
        $actor,
        null,
        $reason
    );

    file_put_contents($resultFile, json_encode([
        'outcome' => 'success',
        'record_id' => $record->id,
        'was_recently_created' => $record->wasRecentlyCreated,
    ]));
} catch (\Throwable $e) {
    file_put_contents($resultFile, json_encode([
        'outcome' => 'exception',
        'class' => get_class($e),
        'message' => $e->getMessage(),
    ]));
}