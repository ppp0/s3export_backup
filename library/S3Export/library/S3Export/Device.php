<?php

class S3Export_Device {

    /** @var string */
    private $path;

    /** @var string */
    private $_mountpoint;

    /**
     * @param string $path
     */
    public function __construct($path) {
        $this->path = (string) $path;
    }

    /**
     * @param string $mountpoint
     * @throws CM_Exception
     */
    public function mount($mountpoint) {
        $this->_mountpoint = (string) $mountpoint;
        CM_Util::exec('sudo mount', [$this->path, $this->_mountpoint]);
    }

    public function unmount() {
        try {
            CM_Util::exec('sudo truecrypt', ['-d']);
            CM_Util::exec('sudo umount', [$this->_mountpoint]);
        } catch
        (Exception $ignored) {
        }
    }

    public function format() {
        if (!$this->_isPartitioned()) {
            $this->_partition();
        }
        CM_Util::exec('sudo mkfs', ['-t', 'ext4', '-m', '0', $this->path]);
    }

    /**
     * @return string
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * @return CM_File_Filesystem
     */
    public function getFilesystem() {
        $adapter = new CM_File_Filesystem_Adapter_Local($this->_mountpoint);
        $filesystem = new CM_File_Filesystem($adapter);
        return $filesystem;
    }

    /**
     * @return bool
     */
    protected function _isPartitioned() {
        return (bool) preg_match('/\d+$/', $this->path);
    }

    protected function _partition() {
        CM_Util::exec('sgdisk', ['-o', $this->path]);
        $startSector = CM_Util::exec('sgdisk', ['-F', $this->path]);
        $endSector = CM_Util::exec('sgdisk', ['-E', $this->path]);
        CM_Util::exec('sgdisk', ['-n', '1:' . $startSector . ':' . $endSector, $this->path]);
        $this->path .= '1';
    }
}
