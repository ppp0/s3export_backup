<?php

class S3Export_Device {

    /** @var string */
    private $_path;

    /** @var string */
    private $_mountpoint;

    /**
     * @param string $path
     */
    public function __construct($path) {
        $this->_path = (string) $path;
    }

    /**
     * @throws CM_Exception
     */
    public function mount() {
        $tmpDir = CM_File::createTmpDir();
        $this->_mountpoint = (string) $tmpDir->getPathOnLocalFilesystem();
        CM_Util::exec('sudo mount', [$this->_path, $this->_mountpoint]);
    }

    public function unmount() {
        CM_Util::exec('sudo umount', [$this->_mountpoint]);
    }

    public function format() {
        if (!$this->_isPartitioned()) {
            $this->_partition();
        }
        CM_Util::exec('sudo mkfs', ['-t', 'ext4', '-m', '0', $this->_path]);
    }

    /**
     * @return string
     */
    public function getPath() {
        return $this->_path;
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
        return (bool) preg_match('/\d+$/', $this->_path);
    }

    protected function _partition() {
        CM_Util::exec('sgdisk', ['-o', $this->_path]);
        $startSector = CM_Util::exec('sgdisk', ['-F', $this->_path]);
        $endSector = CM_Util::exec('sgdisk', ['-E', $this->_path]);
        CM_Util::exec('sgdisk', ['-n', '1:' . $startSector . ':' . $endSector, $this->_path]);
        $this->_path .= '1';
    }
}
