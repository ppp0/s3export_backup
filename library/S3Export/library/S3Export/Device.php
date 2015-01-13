<?php

class S3Export_Device {

    /** @var string */
    private $_deviceName;

    /** @var string */
    private $_mountpoint;

    /**
     * @param string $deviceName
     */
    public function __construct($deviceName) {
        $this->_deviceName = (string) $deviceName;
    }

    /**
     * @param string $mountpoint
     * @throws CM_Exception
     */
    public function mount($mountpoint) {
        $this->_mountpoint = (string) $mountpoint;
        CM_Util::exec('sudo mount', [$this->_deviceName, $this->_mountpoint]);
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
        CM_Util::exec('sudo mkfs', ['-t', 'ext4', '-m', '0', $this->_deviceName]);
    }

    /**
     * @return string
     */
    public function getDeviceName() {
        return $this->_deviceName;
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
        return (bool) preg_match('/\d+$/', $this->_deviceName);
    }

    protected function _partition() {
        CM_Util::exec('sgdisk', ['-o', $this->_deviceName]);
        $startSector = CM_Util::exec('sgdisk', ['-F', $this->_deviceName]);
        $endSector = CM_Util::exec('sgdisk', ['-E', $this->_deviceName]);
        CM_Util::exec('sgdisk', ['-n', '1:' . $startSector . ':' . $endSector, $this->_deviceName]);
        $this->_deviceName .= '1';
    }
}
