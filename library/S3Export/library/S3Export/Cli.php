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

        if ($jobId = $this->_mountDisk($devicePath)) {
            $this->_decryptDisk($jobId, $truecryptPassword);
        }
        // randomly traverse decrypted partition and compare with S3
        $filesystemOriginal = $this->_getFilesystemOriginal();
        $pathList = $filesystemOriginal->listByPrefix('photo/390', true);
        foreach ($pathList as $path) {
            foreach ($path as $entry) {
                $fileOriginal = new CM_File($entry, $filesystemOriginal);
                print $entry . "\n";
                print $filesystemOriginal->getChecksum($entry) . " < -- etag \n";
            }
        }
    }

    public function initDisk($devicePath, $truecryptPassword) {
        // initialize a disk to be ready to be sent to AWS
    }

    public function mountDisk($devicePath) {
        var_dump($this->_mountDisk($devicePath));
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

    /**
     * @return CM_File_Filesystem
     * @throws CM_Exception_Invalid
     */
    private function _getFilesystemEncrypted() {
        return $this->getServiceManager()->get('s3export-filesystem-encrypted', 'CM_File_Filesystem');
    }

    /**
     *
     * @return string
     */
    private function _getPathPrefix(CM_File_Filesystem $filesystem) {
        return $filesystem->getAdapter()->getPathPrefix();
    }

    public static function getPackageName() {
        return 's3export';
    }
}
