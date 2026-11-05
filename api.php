<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/lib/TimeTracker.php';

function json_response($payload, $code = 200)
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return array();
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(array('error' => 'Invalid JSON body'), 400);
    }
    return $data;
}

try {
    $storePath = __DIR__ . '/data/db.json';
    $tracker = new TimeTracker(new Store($storePath));

    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($method === 'GET' && $action === 'employees') {
        json_response(array('employees' => $tracker->getStatus()));
    }

    if ($method === 'POST' && $action === 'employees') {
        $body = read_json_body();
        $name = isset($body['name']) ? $body['name'] : '';
        $employee = $tracker->addEmployee($name);
        json_response(array('employee' => $employee), 201);
    }

    if ($method === 'POST' && $action === 'timer/start') {
        $body = read_json_body();
        $employeeId = isset($body['employee_id']) ? $body['employee_id'] : 0;
        $task = isset($body['task']) ? $body['task'] : '';
        $entry = $tracker->startTimer($employeeId, $task);
        json_response(array('entry' => $entry), 201);
    }

    if ($method === 'POST' && $action === 'timer/stop') {
        $body = read_json_body();
        $employeeId = isset($body['employee_id']) ? $body['employee_id'] : 0;
        $entry = $tracker->stopTimer($employeeId);
        json_response(array('entry' => $entry));
    }

    if ($method === 'GET' && $action === 'report') {
        $from = isset($_GET['from']) ? $_GET['from'] : '';
        $to = isset($_GET['to']) ? $_GET['to'] : '';
        $employeeId = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;
        $report = $tracker->report($from, $to, $employeeId);
        json_response($report);
    }

    json_response(array('error' => 'Unknown action'), 404);
} catch (InvalidArgumentException $e) {
    json_response(array('error' => $e->getMessage()), 400);
} catch (RuntimeException $e) {
    json_response(array('error' => $e->getMessage()), 409);
} catch (Exception $e) {
    json_response(array('error' => 'Server error: ' . $e->getMessage()), 500);
}
