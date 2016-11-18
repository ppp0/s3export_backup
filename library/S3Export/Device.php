<?php

class S3Export_Device {

    /** @var string */
    private $_path;

    /**
     * @param string $path
     */
    public function __construct($path) {
        $this->_path = (string) $path;
    }

    /**
     * @return bool
     * @throws CM_Exception
     */
    public function isMounted() {
        return null !== $this->_findMountpoint();
    }

    /**
     * @throws CM_Exception
     */
    public function mount() {
        if ($this->isMounted()) {
            return;
        }
        $tmpDir = CM_File::createTmpDir();
        $mountpointPath = (string) $tmpDir->getPathOnLocalFilesystem();
        CM_Util::exec('sudo mount', [$this->_path, $mountpointPath]);

        $this->_waitForMountStatus(true);
    }

    public function unmount() {
        $mountpoint = $this->_findMountpoint();
        if (null === $mountpoint) {
            return;
        }
        CM_Util::exec('sudo umount', [$mountpoint]);

        $this->_waitForMountStatus(false);
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
     * @return CM_File
     */
    public function getMountpoint() {
        return new CM_File('/', $this->getFilesystem());
    }

    /**
     * @return CM_File_Filesystem
     * @throws CM_Exception
     */
    public function getFilesystem() {
        $mountpoint = $this->_findMountpoint();
        if (null == $mountpoint) {
            throw new CM_Exception('Device is not mounted', ['device' => $this->getPath()]);
        }
        $adapter = new CM_File_Filesystem_Adapter_Local($mountpoint);
        $filesystem = new CM_File_Filesystem($adapter);
        return $filesystem;
    }

    /**
     * @return bool
     * @throws CM_Exception
     */
    public function hasPartitions() {
        $blocks = explode("\n", CM_Util::exec('lsblk', ['-n', '-o', 'TYPE', $this->_getPathWithoutPartition()]));
        return \Functional\some($blocks, function ($block) {
            return $block === 'part';
        });
    }

    public function fixPartitioning() {
        CM_Util::exec('sgdisk', ['--move-second-header', $this->_getPathWithoutPartition()]);
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

    /**
     * @return string
     */
    protected function _getPathWithoutPartition() {
        return preg_replace('/\d+$/', '', $this->_path);
    }

    /**
     * @return string|null
     */
    protected function _findMountpoint() {
        $mounts = (new CM_File('/proc/mounts'))->read();
        $mountPattern = '/^' . preg_quote($this->_path, '/') . '\s+([^\s]+)/m';
        if (!preg_match($mountPattern, $mounts, $matches)) {
            return null;
        }
        return $matches[1];
    }

    /**
     * @param boolean $stateExpected
     * @throws CM_Exception
     */
    protected function _waitForMountStatus($stateExpected) {
        $timeMax = microtime(true) + 5;
        while ($stateExpected !== $this->isMounted()) {
            usleep(1000000 * 0.1);
            if (microtime(true) > $timeMax) {
                throw new CM_Exception('Timeout waiting for mount state', ['device' => $this->getPath(), 'expected-state' => $stateExpected]);
            }
        }
    }
}
