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
    }

    /**
     * @param string $device
     * @param bool $confirm
     * @throws CM_Exception_Invalid
     */
    public function initDisk($manifestPath, $device, $confirm = false) {
        if (!preg_match('/\d+$/', $device)) {
            CM_Util::exec('sgdisk', ['-o', $device]);
            $startSector = CM_Util::exec('sgdisk', ['-F', $device]);
            $endSector = CM_Util::exec('sgdisk', ['-E', $device]);
            CM_Util::exec('sgdisk', ['-n', '1:' . $startSector . ':' . $endSector, $device]);
            $device = $device . '1';
        }
        CM_Util::exec('sudo mkfs', ['-t', 'ext4', '-m', '0', $device]);
        $mountpoint = $this->_getLocalFilesystemPath($this->_getFilesystemBackupEncrypted());
        CM_Util::exec('sudo mount', [$device, $mountpoint]);

        $file = new CM_File($manifestPath);
        $manifest = $file->read();
        if (!preg_match('/fileSystem:(.*)/', $manifest, $matches)) {
            throw new CM_Exception_Invalid('Manifest file has not fileSystem field');
            $this->_cleanup();
        }
        if (!$matches[1] == 'EXT4') {
            throw new CM_Exception_Invalid('Only file system EXT4 supported (manifest)');
            $this->_cleanup();
        }
        $apiResponse = $this->_createAWSJob($manifest, !$confirm);

        $signatureFile = new CM_File($mountpoint . '/SIGNATURE');
        $signatureFile->write($apiResponse->get('SignatureFileContents'));
        $this->_cleanup();

        print("\nCreate Job completed:\n");
        print("---------------------\n");
        print('JobID: ' . $apiResponse->get('JobId') . "\n");
        print("\n\n");
    }

    /**
     * @param string $jobId
     */
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

    /**
     * @param string $jobId
     */
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

    /**
     * @return CM_File_Filesystem
     * @throws CM_Exception_Invalid
     */
    private function _getFilesystemBackupEncrypted() {
        return $this->getServiceManager()->get('s3export-filesystem-backup-encrypted', 'CM_File_Filesystem');
    }

    /**
     * @param CM_File_Filesystem $filesystem
     * @return string
     */
    private function _getLocalFilesystemPath(CM_File_Filesystem $filesystem) {
        $directory = new CM_File('/', $filesystem);
        return $directory->getPathOnLocalFilesystem();
    }

    private function _cleanup() {
        try {
            CM_Util::exec('sudo truecrypt', ['-d']);
            CM_Util::exec('sudo umount', [$this->_getLocalFilesystemPath($this->_getFilesystemBackupEncrypted())]);
        } catch
        (Exception $ignored) {
        }
    }

    public static function getPackageName() {
        return 's3export';
    }
}
