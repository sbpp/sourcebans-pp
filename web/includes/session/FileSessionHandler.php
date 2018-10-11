<?php

class FileSessionHandler implements SessionHandlerInterface
{
    private $path = SB_CACHE.'/sessions';

    public function open($path, $name)
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0775);
        }

        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        return (string)@file_get_contents("{$this->path}/sess_{$id}");
    }

    public function write($id, $data)
    {
        return (bool)file_put_contents("{$this->path}/sess_{$id}", $data);
    }

    public function destroy($id)
    {
        $file = "{$this->path}/sess_$id";
        if (file_exists($file)) {
            unlink($file);
        }

        return true;
    }

    public function gc($maxlifetime)
    {
        foreach (glob("{$this->path}/sess_*") as $file) {
            if (file_exists($file) && filemtime($file) + $maxlifetime < time()) {
                unlink($file);
            }
        }

        return true;
    }
}
