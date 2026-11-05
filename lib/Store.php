<?php
/**
 * Simple JSON file persistence.
 */
class Store
{
    private $path;
    private $data;

    public function __construct($path)
    {
        $this->path = $path;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!file_exists($path)) {
            $this->data = array(
                'employees' => array(),
                'time_entries' => array(),
                'next_employee_id' => 1,
                'next_entry_id' => 1,
            );
            $this->flush();
        } else {
            $raw = file_get_contents($path);
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Corrupt data store: ' . $path);
            }
            $this->data = $decoded;
        }
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData(array $data)
    {
        $this->data = $data;
        $this->flush();
    }

    public function flush()
    {
        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode store data');
        }
        $tmp = $this->path . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write store');
        }
        if (!rename($tmp, $this->path)) {
            throw new RuntimeException('Failed to commit store');
        }
    }

    /** Reset store — used by tests. */
    public function reset()
    {
        $this->data = array(
            'employees' => array(),
            'time_entries' => array(),
            'next_employee_id' => 1,
            'next_entry_id' => 1,
        );
        $this->flush();
    }
}
