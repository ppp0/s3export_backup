<?php

class S3Export_Cli extends CM_Cli_Runnable_Abstract implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    public function __construct(CM_InputStream_Interface $input = null, CM_OutputStream_Interface $output = null) {
        parent::__construct($input, $output);
        $this->setServiceManager(CM_Service_Manager::getInstance());
    }

    /**
     * @param string $devicePath
     * @param string $truecryptPassword
     */
    public function verifyDisk($devicePath, $truecryptPassword) {
        $filesystemOriginal = $this->_getFilesystemOriginal();
        $filesystemBackup = $this->_getFilesystemBackup();
    }

    /**
     * @return CM_File_Filesystem
     * @throws CM_Exception_Invalid
     */
    private function _getFilesystemOriginal() {
        return $this->getServiceManager()->get('s3export-filesystem-original', 'CM_File_Filesystem');
    }

    /**
     * @return CM_File_Filesystem
     * @throws CM_Exception_Invalid
     */
    private function _getFilesystemBackup() {
        return $this->getServiceManager()->get('s3export-filesystem-backup', 'CM_File_Filesystem');
    }

    public static function getPackageName() {
        return 's3export';
    }
}
