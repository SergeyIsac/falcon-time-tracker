<?php
/**
 * Lightweight test runner (no PHPUnit required).
 * Run: php tests/run.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/lib/TimeTracker.php';

$passed = 0;
$failed = 0;
$testDir = dirname(__DIR__) . '/data';
$storeFile = $testDir . '/test-db.json';

function assert_true($cond, $message)
{
    global $passed, $failed;
    if ($cond) {
        echo "  OK  $message\n";
        $passed++;
    } else {
        echo " FAIL $message\n";
        $failed++;
    }
}

function assert_eq($actual, $expected, $message)
{
    assert_true($actual === $expected, $message . ' (got ' . var_export($actual, true) . ', expected ' . var_export($expected, true) . ')');
}

function fresh_tracker()
{
    global $storeFile;
    if (file_exists($storeFile)) {
        unlink($storeFile);
    }
    $store = new Store($storeFile);
    $store->reset();
    return new TimeTracker($store);
}

echo "Falcon Time Tracker — tests\n\n";

// --- Add employees ---
echo "Scenario: add employees\n";
$t = fresh_tracker();
$a = $t->addEmployee('Alex Rivera');
$b = $t->addEmployee('Blake Chen');
assert_eq($a['name'], 'Alex Rivera', 'first employee name');
assert_eq(count($t->listEmployees()), 2, 'two employees listed');

$threw = false;
try {
    $t->addEmployee('  ');
} catch (InvalidArgumentException $e) {
    $threw = true;
}
assert_true($threw, 'empty name rejected');

$threw = false;
try {
    $t->addEmployee('alex rivera');
} catch (InvalidArgumentException $e) {
    $threw = true;
}
assert_true($threw, 'duplicate name rejected (case-insensitive)');

// --- Start / stop timer ---
echo "\nScenario: start and stop timer\n";
$t = fresh_tracker();
$emp = $t->addEmployee('Casey Ng');
$entry = $t->startTimer($emp['id'], 'Build report UI');
assert_eq($entry['task'], 'Build report UI', 'task stored');
assert_true($entry['ended_at'] === null, 'timer is running');
assert_true($t->getActiveEntry($emp['id']) !== null, 'active entry visible');

$stopped = $t->stopTimer($emp['id']);
assert_true($stopped['ended_at'] !== null, 'timer stopped');
assert_true($stopped['duration_seconds'] !== null, 'duration recorded');
assert_true($t->getActiveEntry($emp['id']) === null, 'no active entry after stop');

// --- Restarting the timer ---
echo "\nScenario: restarting the timer\n";
$t = fresh_tracker();
$emp = $t->addEmployee('Dana Ortiz');
$t->startTimer($emp['id'], 'First session');
$t->stopTimer($emp['id']);
$second = $t->startTimer($emp['id'], 'Second session after restart');
assert_eq($second['task'], 'Second session after restart', 'can start again after stop');
assert_true($t->getActiveEntry($emp['id']) !== null, 'second timer is active');

$threw = false;
try {
    $t->startTimer($emp['id'], 'Overlap attempt');
} catch (RuntimeException $e) {
    $threw = true;
}
assert_true($threw, 'cannot start a second concurrent timer');

$t->stopTimer($emp['id']);
$report = $t->report(gmdate('Y-m-d'), gmdate('Y-m-d'), $emp['id']);
assert_eq(count($report['entries']), 2, 'two completed sessions after restart cycle');

// --- Empty report ---
echo "\nScenario: empty report\n";
$t = fresh_tracker();
$t->addEmployee('Empty Report Person');
$report = $t->report('2020-01-01', '2020-01-31', null);
assert_eq(count($report['entries']), 0, 'no entries in empty period');
assert_eq($report['grand_total_seconds'], 0, 'grand total is zero');
assert_true(is_array($report['totals']), 'totals array present');

// --- Persistence after "restart" ---
echo "\nScenario: persistence after application restart\n";
$t = fresh_tracker();
$emp = $t->addEmployee('Evan Park');
$t->startTimer($emp['id'], 'Ship feature');
$t->stopTimer($emp['id']);

// Simulate app restart: new Store + TimeTracker on same file
$t2 = new TimeTracker(new Store($storeFile));
$list = $t2->listEmployees();
assert_eq(count($list), 1, 'employee survives restart');
$report = $t2->report(gmdate('Y-m-d'), gmdate('Y-m-d'), null);
assert_eq(count($report['entries']), 1, 'time entry survives restart');
assert_true($report['grand_total_seconds'] >= 0, 'total readable after restart');

// --- Report totals for selected period ---
echo "\nScenario: report totals by period\n";
$t = fresh_tracker();
$e1 = $t->addEmployee('Alex');
$e2 = $t->addEmployee('Blake');
$t->startTimer($e1['id'], 'A1');
sleep(1);
$t->stopTimer($e1['id']);
$t->startTimer($e2['id'], 'B1');
$t->stopTimer($e2['id']);

$today = gmdate('Y-m-d');
$all = $t->report($today, $today, null);
assert_eq(count($all['entries']), 2, 'both employees in report');
assert_true($all['grand_total_seconds'] >= 1, 'grand total includes durations');

$onlyAlex = $t->report($today, $today, $e1['id']);
assert_eq(count($onlyAlex['entries']), 1, 'filtered by employee');
assert_eq($onlyAlex['entries'][0]['employee_id'], $e1['id'], 'correct employee kept');

$old = $t->report('2010-01-01', '2010-01-02', null);
assert_eq(count($old['entries']), 0, 'out-of-range period is empty');

// cleanup
if (file_exists($storeFile)) {
    unlink($storeFile);
}

echo "\n-----------------------------\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
exit($failed > 0 ? 1 : 0);
