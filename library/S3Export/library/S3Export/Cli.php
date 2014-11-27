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
add
    /**
     *
     * @param string $devicePath
     * @return null | string crypted file (JobID.tc)
     *
     *
     */

    private function _mountDisk($devicePath) {
        $filesystemEncrypted = $this->_getFilesystemEncrypted();
        $device_without_partition = preg_replace('/\d+$/', '', $devicePath);
        $partitions_on_device = explode("\n", CM_Util::exec('lsblk', ['-nr', $device_without_partition]));
        array_pop($partitions_on_device);
        if (count($partitions_on_device) == 1) {
            $this->_getStreamOutput()->writeln('Fixing the partition table...');
            CM_Util::exec('sgdisk', ['--move-second-header', $device_without_partition]);
        }
        CM_Util::exec('mount', [$devicePath, $this->_getPathPrefix($this->_getFilesystemEncrypted())]);
        $crypt_array = $filesystemEncrypted->listByPrefix('/', true);
        if ($crypt_files = preg_grep('/\.tc/', $crypt_array['files'])) {
            return basename(reset($crypt_files));
        }
    }

    private function _decryptDisk($jobId, $truecryptPassword) {

        CM_Util::exec('truecrypt', ['-p', $truecryptPassword, '--protect-hidden=no', '--keyfiles=""',
                $this->_getPathPrefix($this->_getFilesystemEncrypted()) . $jobId,
                $this->_getPathPrefix($this->_getFilesystemBackup())]);

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
