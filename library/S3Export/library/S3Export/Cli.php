<?php

class S3Export_Cli extends CM_Cli_Runnable_Abstract implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    public function __construct(CM_InputStream_Interface $input = null, CM_OutputStream_Interface $output = null) {
        parent::__construct($input, $output);
        $this->setServiceManager(CM_Service_Manager::getInstance());
    }

    public function __destruct() {
        $this->_cleanup();
    }

    /**
     * @param string $devicePath
     * @param string $truecryptPassword
     */
    public function verifyDisk($devicePath, $truecryptPassword) {
    }

    /**
     *
     */
    public function initDisk($device) {
        if (!preg_match('/\d+$/', $device)) {
            CM_Util::exec('sgdisk', ['-o', $device]);
            $startSector = CM_Util::exec('sgdisk', ['-F', $device]);
            $endSector = CM_Util::exec('sgdisk', ['-E', $device]);
            CM_Util::exec('sgdisk', ['-n', '1:' . $startSector . ':' . $endSector, $device]);
            $device = $device . '1';
        }
//        CM_Util::exec('sudo mkfs', ['-t', 'ext4', '-m', '0', $device]);
//        $mountpoint = $this->_getLocalFilesystemPath($this->_getFilesystemBackupEncrypted());
//        CM_Util::exec('sudo mount', [$device, $mountpoint]);
$mountpoint = '/tmp/test';

        $file = new CM_File(getcwd() . '/manifest');
        $manifest = $file->read();
        if (!preg_match('/fileSystem:(.*)/', $manifest, $matches)) {
            throw new CM_Exception_Invalid('Manifest file has not fileSystem field');
        }
        if (!$matches[1] == 'EXT4') {
            throw new CM_Exception_Invalid('Only file system EXT4 supported (manifest)');
        }

        $apiResponse = $this->_createAWSJob($manifest, true);

        $signatureFile = new CM_File($mountpoint . '/SIGNATURE');
        $signatureFile->write($apiResponse->get('SignatureFileContents'));

        print("\nCreate Job completed:\n");
        print("---------------------\n");
        print('JobID: ' . $apiResponse->get('JobId') . "\n");
        print("\n\n");

    }


    public function cancelJob($jobId) {
        $client = $this->_getAWSClient(CM_Config::get()->awsCredentials);
        print_r($client->cancelJob(array(
            'JobId' => $jobId,
        )));
    }

    public function listJobs() {
        $client = $this->_getAWSClient(CM_Config::get()->awsCredentials);
        print_r($client->listJobs());
    }

    public function getStatus($jobId) {
        $client = $this->_getAWSClient(CM_Config::get()->awsCredentials);
        print_r($client->getStatus(array(
            'JobId' => $jobId,
        )));
    }

    /**
     *
     * @param string $manifest
     * @param bool $dryRun
     * @return String awsJobId
     */
    private function _createAWSJob($manifest, $dryRun = true) {
        $client = $this->_getAWSClient(CM_Config::get()->awsCredentials);
        $apiResponse = $client->createJob(array(
            'JobType' => 'Export',
            'Manifest' => $manifest,
            'ValidateOnly' => $dryRun,
        ));
        return $apiResponse;
    }

    private function _getAWSClient($credentials) {
        return Aws\ImportExport\ImportExportClient::factory($credentials);
    }

    private function _cleanup() {
        try {
            CM_Util::exec('sudo truecrypt', ['-d']);
            CM_Util::exec('sudo umount', [$this->_getLocalFilesystemPath($this->_getFilesystemBackupEncrypted())]);
        } catch (Exception $ignored) {
            print $ignored->getMessage();
        }
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
