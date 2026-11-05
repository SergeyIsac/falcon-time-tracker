<?php
require_once __DIR__ . '/Store.php';

/**
 * Work time tracking domain logic.
 */
class TimeTracker
{
    private $store;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function listEmployees()
    {
        $data = $this->store->getData();
        $list = array_values($data['employees']);
        usort($list, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return $list;
    }

    public function addEmployee($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            throw new InvalidArgumentException('Employee name is required');
        }

        $data = $this->store->getData();
        foreach ($data['employees'] as $emp) {
            if (strcasecmp($emp['name'], $name) === 0) {
                throw new InvalidArgumentException('Employee already exists');
            }
        }

        $id = (int) $data['next_employee_id'];
        $employee = array(
            'id' => $id,
            'name' => $name,
            'created_at' => gmdate('c'),
        );
        $data['employees'][(string) $id] = $employee;
        $data['next_employee_id'] = $id + 1;
        $this->store->setData($data);

        return $employee;
    }

    public function getActiveEntry($employeeId)
    {
        $employeeId = (int) $employeeId;
        $data = $this->store->getData();
        foreach ($data['time_entries'] as $entry) {
            if ((int) $entry['employee_id'] === $employeeId && $entry['ended_at'] === null) {
                return $entry;
            }
        }
        return null;
    }

    public function startTimer($employeeId, $taskDescription)
    {
        $employeeId = (int) $employeeId;
        $taskDescription = trim((string) $taskDescription);
        if ($taskDescription === '') {
            throw new InvalidArgumentException('Task description is required');
        }

        $data = $this->store->getData();
        if (!isset($data['employees'][(string) $employeeId])) {
            throw new InvalidArgumentException('Employee not found');
        }

        if ($this->getActiveEntry($employeeId) !== null) {
            throw new RuntimeException('Timer already running for this employee. Stop it first.');
        }

        $id = (int) $data['next_entry_id'];
        $entry = array(
            'id' => $id,
            'employee_id' => $employeeId,
            'task' => $taskDescription,
            'started_at' => gmdate('c'),
            'ended_at' => null,
            'duration_seconds' => null,
        );
        $data['time_entries'][(string) $id] = $entry;
        $data['next_entry_id'] = $id + 1;
        $this->store->setData($data);

        return $entry;
    }

    public function stopTimer($employeeId)
    {
        $employeeId = (int) $employeeId;
        $data = $this->store->getData();
        if (!isset($data['employees'][(string) $employeeId])) {
            throw new InvalidArgumentException('Employee not found');
        }

        $active = null;
        $activeKey = null;
        foreach ($data['time_entries'] as $key => $entry) {
            if ((int) $entry['employee_id'] === $employeeId && $entry['ended_at'] === null) {
                $active = $entry;
                $activeKey = $key;
                break;
            }
        }

        if ($active === null) {
            throw new RuntimeException('No running timer for this employee');
        }

        $endedAt = gmdate('c');
        $duration = max(0, strtotime($endedAt) - strtotime($active['started_at']));
        $active['ended_at'] = $endedAt;
        $active['duration_seconds'] = $duration;
        $data['time_entries'][$activeKey] = $active;
        $this->store->setData($data);

        return $active;
    }

    /**
     * Report entries and totals for a period (inclusive dates, YYYY-MM-DD, local/UTC date of timestamps).
     */
    public function report($fromDate, $toDate, $employeeId = null)
    {
        if ($fromDate === null || $fromDate === '') {
            $fromDate = '1970-01-01';
        }
        if ($toDate === null || $toDate === '') {
            $toDate = '9999-12-31';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            throw new InvalidArgumentException('Dates must be YYYY-MM-DD');
        }
        if ($fromDate > $toDate) {
            throw new InvalidArgumentException('from must be <= to');
        }

        $fromTs = strtotime($fromDate . ' 00:00:00 UTC');
        $toTs = strtotime($toDate . ' 23:59:59 UTC');
        $filterEmployee = $employeeId !== null && $employeeId !== '' ? (int) $employeeId : null;

        $data = $this->store->getData();
        $entries = array();
        $totals = array();

        foreach ($data['employees'] as $emp) {
            $totals[(string) $emp['id']] = array(
                'employee_id' => (int) $emp['id'],
                'employee_name' => $emp['name'],
                'total_seconds' => 0,
                'entry_count' => 0,
            );
        }

        foreach ($data['time_entries'] as $entry) {
            if ($filterEmployee !== null && (int) $entry['employee_id'] !== $filterEmployee) {
                continue;
            }

            $startTs = strtotime($entry['started_at']);
            if ($startTs < $fromTs || $startTs > $toTs) {
                continue;
            }

            // Running timers: count elapsed until now for report display
            $duration = $entry['duration_seconds'];
            if ($duration === null) {
                $duration = max(0, time() - $startTs);
            }

            $enriched = $entry;
            $enriched['duration_seconds'] = $duration;
            $enriched['is_running'] = ($entry['ended_at'] === null);
            $empKey = (string) $entry['employee_id'];
            if (isset($data['employees'][$empKey])) {
                $enriched['employee_name'] = $data['employees'][$empKey]['name'];
            } else {
                $enriched['employee_name'] = 'Unknown';
            }

            $entries[] = $enriched;

            if (!isset($totals[$empKey])) {
                $totals[$empKey] = array(
                    'employee_id' => (int) $entry['employee_id'],
                    'employee_name' => $enriched['employee_name'],
                    'total_seconds' => 0,
                    'entry_count' => 0,
                );
            }
            $totals[$empKey]['total_seconds'] += $duration;
            $totals[$empKey]['entry_count'] += 1;
        }

        usort($entries, function ($a, $b) {
            return strcmp($b['started_at'], $a['started_at']);
        });

        $totalsList = array_values($totals);
        if ($filterEmployee !== null) {
            $totalsList = array_values(array_filter($totalsList, function ($t) use ($filterEmployee) {
                return (int) $t['employee_id'] === $filterEmployee;
            }));
        }

        usort($totalsList, function ($a, $b) {
            return strcasecmp($a['employee_name'], $b['employee_name']);
        });

        return array(
            'from' => $fromDate,
            'to' => $toDate,
            'entries' => $entries,
            'totals' => $totalsList,
            'grand_total_seconds' => array_sum(array_map(function ($t) {
                return (int) $t['total_seconds'];
            }, $totalsList)),
        );
    }

    public function getStatus()
    {
        $employees = $this->listEmployees();
        $withTimers = array();
        foreach ($employees as $emp) {
            $active = $this->getActiveEntry($emp['id']);
            $emp['active_entry'] = $active;
            $withTimers[] = $emp;
        }
        return $withTimers;
    }
}
