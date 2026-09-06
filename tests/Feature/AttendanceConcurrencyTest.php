<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 14-schoolos-implementation-plan.md §3's Phase 3 test gate,
 * row 1 — "two simultaneous attendance submissions for the same
 * (student_id, date, session): one succeeds, one is correctly treated as
 * a correction (not a duplicate/race), verifying lockForUpdate() actually
 * serializes them."
 *
 * Every other test in this suite runs against phpunit.xml's in-memory
 * SQLite connection — one PDO handle per test process, which is fine for
 * every other repository here, none of which needed to prove anything
 * about two callers overlapping *in time*. That's exactly what this row
 * asks for, and a single in-memory connection structurally can't provide
 * it: one PHP process has exactly one AttendanceRepository::mark() call
 * running at once no matter how the test code is arranged, so calling it
 * twice in sequence only ever exercises the "existing row found" branch
 * AttendanceRepositoryTest's correction-row test already covers. It can
 * never produce two DB::transaction() calls genuinely open against the
 * same row at the same instant — the actual scenario the lock exists
 * for.
 *
 * This test builds that scenario for real: two separate PHP processes
 * (tests/Support/attendance_race_worker.php, not this test's own
 * process) opening two independent PDO connections against one on-disk
 * SQLite file, held at a rendezvous point until *both* have finished
 * booting Laravel and are ready to call mark() — a file-based handshake,
 * not a fixed sleep(), so the overlap isn't a timing guess (see
 * waitForFile()'s doc comment).
 *
 * A caveat worth being explicit about rather than letting the class name
 * imply more than SQLite can actually back: AttendanceRepository::mark()
 * says lockForUpdate() is "what makes the common case a clean serialized
 * update instead of a constraint-violation retry" — but Laravel's SQLite
 * grammar has no FOR UPDATE syntax at all (only MySQL/Postgres/SQL
 * Server compile one); ->lockForUpdate() is a silent no-op on this
 * connection. What actually serializes the two processes below is
 * SQLite's own whole-database write lock plus this test's busy_timeout
 * override (without it, the second writer gets an immediate "database is
 * locked" instead of waiting for the first to commit). That's enough to
 * prove mark() behaves correctly *once serialized* — one create, one
 * correction, no duplicate row, no uncaught exception — but it does not
 * exercise MySQL's row-level FOR UPDATE semantics, since SQLite has no
 * equivalent to not exercise correctly or otherwise. If this repository
 * ever runs against MySQL in production, the lock itself still needs a
 * MySQL-backed test to mean anything beyond "the code path is reachable."
 */
class AttendanceConcurrencyTest extends TestCase
{
    use CreatesSchoolOsFixtures;

    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        // A real file, not :memory: — a second PHP process needs a path
        // it can open its own connection against; an in-memory SQLite
        // database only ever exists inside the one PDO handle that
        // created it and is invisible to every other process.
        //
        // Built directly with uniqid() rather than tempnam(): tempnam()
        // can return false on failure (e.g. an unwritable or AV-locked
        // temp dir) without throwing, and nothing here checked for that —
        // false . '.sqlite' silently became the literal path ".sqlite",
        // and the unlink() that used to follow it failed with exactly
        // "No such file or directory". uniqid() can't fail that way, and
        // we don't need tempnam's atomic-create guarantee here since
        // nothing else can be racing to claim this exact filename.
        $this->dbPath = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
            . 'schoolos_attendance_race_' . uniqid('', true) . '.sqlite';
        touch($this->dbPath);

        config(['database.connections.sqlite.database' => $this->dbPath]);
        config(['database.connections.sqlite.busy_timeout' => 5000]);
        DB::purge('sqlite');

        Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        @unlink($this->dbPath);

        parent::tearDown();
    }

    public function test_two_concurrent_submissions_for_the_same_key_serialize_into_one_create_and_one_correction(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $tmpDir = sys_get_temp_dir();
        $token = uniqid('attendance_race_', true);
        $readyA = "{$tmpDir}/{$token}_ready_a";
        $readyB = "{$tmpDir}/{$token}_ready_b";
        $goFile = "{$tmpDir}/{$token}_go";
        $resultA = "{$tmpDir}/{$token}_result_a.json";
        $resultB = "{$tmpDir}/{$token}_result_b.json";

        $worker = base_path('tests/Support/attendance_race_worker.php');

        $shared = [
            $this->dbPath,
            (string) $school->id,
            (string) $academicYear->id,
            (string) $classSection->id,
            (string) $student->id,
            '2026-08-25',
            'morning',
        ];

        // Both submissions carry a reason. Which one reaches
        // AttendanceRecord::create() first vs. finds the row and
        // corrects it is decided by the OS scheduler, not by this test —
        // asserting on that ordering would make the test as racy as the
        // bug it's meant to catch. A reason on both sides means whichever
        // process loses the race and lands on the "existing row" branch
        // has what mark() requires to treat it as a correction instead of
        // throwing on a missing reason, so the assertions below check the
        // *outcome shape* (one create, one correction, same row), never
        // which process produced which.
        $processA = $this->startWorker($worker, [
            ...$shared, 'present', (string) $teacher->id, 'Resubmission A', $readyA, $goFile, $resultA,
        ]);
        $processB = $this->startWorker($worker, [
            ...$shared, 'absent', (string) $teacher->id, 'Resubmission B', $readyB, $goFile, $resultB,
        ]);

        $this->waitForFile($readyA, 'worker A never signaled ready');
        $this->waitForFile($readyB, 'worker B never signaled ready');

        // Both workers are now past their own Laravel boot and blocked on
        // go_file — this is the closest two independent OS processes can
        // get to a simultaneous start.
        touch($goFile);

        $this->waitForProcess($processA, 'A');
        $this->waitForProcess($processB, 'B');

        $this->assertFileExists($resultA, 'worker A produced no result file');
        $this->assertFileExists($resultB, 'worker B produced no result file');

        $outcomeA = json_decode(file_get_contents($resultA), true);
        $outcomeB = json_decode(file_get_contents($resultB), true);

        $this->assertSame('success', $outcomeA['outcome'] ?? null, 'worker A: ' . json_encode($outcomeA));
        $this->assertSame('success', $outcomeB['outcome'] ?? null, 'worker B: ' . json_encode($outcomeB));

        $this->assertSame(
            $outcomeA['record_id'],
            $outcomeB['record_id'],
            'both submissions should resolve to the same attendance_records row, not two rows'
        );

        $wasCreated = [$outcomeA['was_recently_created'], $outcomeB['was_recently_created']];
        sort($wasCreated);
        $this->assertSame(
            [false, true],
            $wasCreated,
            'expected exactly one create and one correction, not two creates (a race) or two corrections'
        );

        // This test's own connection was idle for the whole handshake
        // above (it never opened a transaction against $this->dbPath
        // itself), so reading through it now doesn't contend with
        // anything — it's just confirming the two workers' writes landed
        // where expected.
        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertDatabaseCount('attendance_corrections', 1);

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'date' => '2026-08-25',
            'session' => 'morning',
        ]);
    }

    /**
     * @param list<string> $args
     */
    private function startWorker(string $worker, array $args): Process
    {
        $process = new Process([PHP_BINARY, $worker, ...$args]);
        $process->setTimeout(10);
        $process->start();

        return $process;
    }

    private function waitForProcess(Process $process, string $label): void
    {
        $process->wait();

        if (!$process->isSuccessful()) {
            $this->fail("worker {$label} exited unsuccessfully: " . $process->getErrorOutput());
        }
    }

    /**
     * Polls for a file rather than sleep()ing a fixed duration. A fixed
     * sleep is either too short under load — flaky in exactly the way
     * this test exists to rule out — or wastefully long in the common
     * case. Polling for the file the other side actually writes is both
     * faster when things are healthy and correct under load.
     */
    private function waitForFile(string $path, string $timeoutMessage): void
    {
        $deadline = microtime(true) + 5.0;

        while (!file_exists($path)) {
            if (microtime(true) > $deadline) {
                $this->fail($timeoutMessage);
            }
            usleep(2000);
        }
    }
}