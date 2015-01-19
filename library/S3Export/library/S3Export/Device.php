<?php

class S3Export_Device {

    /** @var string */
    private $_path;

    /** @var string */
    private $_mountpointPath;

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
        $mounts = CM_Util::exec('cat', ['/proc/mounts']);
        $mountPattern = '/' . preg_quote($this->_path, '/') . '.+' . preg_quote($this->_mountpointPath, '/') . '/';
        return (bool) preg_match($mountPattern, $mounts);
    }

    /**
     * @throws CM_Exception
     */
    public function mount() {
        if ($this->isMounted()) {
            return;
        }
        $tmpDir = CM_File::createTmpDir();
        $this->_mountpointPath = (string) $tmpDir->getPathOnLocalFilesystem();
        CM_Util::exec('sudo mount', [$this->_path, $this->_mountpointPath]);
    }

    public function unmount() {
        if (!$this->isMounted()) {
            return;
        }
        CM_Util::exec('sudo umount', [$this->_mountpointPath]);
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
     */
    public function getFilesystem() {
        $adapter = new CM_File_Filesystem_Adapter_Local($this->_mountpointPath);
        $filesystem = new CM_File_Filesystem($adapter);
        return $filesystem;
    }

    /**
     * @return bool
     * @throws CM_Exception
     */
    public function hasPartitions() {
        $blocks = explode("\n", CM_Util::exec('lsblk', ['-n', '-o', 'TYPE', $this->_getPathWithoutPartition()]));
        return \Functional\some($blocks, function($block) {
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
}
