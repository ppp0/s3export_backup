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
     */
    public function initDisk($manifestPath, $device, $noFormat = null, $dryRun = null) {
        if (null === $dryRun) { $dryRun = false; }
        if (null === $noFormat) { $noFormat = false; }
        if ($noFormat != true) {
            if (!preg_match('/\d+$/', $device)) {
                CM_Util::exec('sgdisk', ['-o', $device]);
                $startSector = CM_Util::exec('sgdisk', ['-F', $device]);
                $endSector = CM_Util::exec('sgdisk', ['-E', $device]);
                CM_Util::exec('sgdisk', ['-n', '1:' . $startSector . ':' . $endSector, $device]);
                $device = $device . '1';
            }
            CM_Util::exec('sudo mkfs', ['-t', 'ext4', '-m', '0', $device]);
        }
        $mountpoint = $this->_getLocalFilesystemPath($this->_getFilesystemBackupEncrypted());
        CM_Util::exec('sudo mount', [$device, $mountpoint]);

        $file = new CM_File($manifestPath);
        if ($file->isDirectory()) {
            $this->_cleanup();
            throw new CM_Exception_Invalid('Manifest file expected, path to directory given');
        }
        $manifest = $file->read();
        if (!preg_match('/fileSystem:(.*)/', $manifest, $matches)) {
            $this->_cleanup();
            throw new CM_Exception_Invalid('Manifest file has not fileSystem field');
        }
        if (!$matches[1] == 'EXT4') {
            $this->_cleanup();
            throw new CM_Exception_Invalid('Only file system EXT4 supported (manifest)');
        }
        $apiResponse = $this->_createAWSJob($manifest, $dryRun);

        $signatureFile = new CM_File('SIGNATURE');
        $signatureFile->joinPath($mountpoint);
        $signatureFile->write($apiResponse->get('SignatureFileContents'));
        $this->_cleanup();

        $this->_getStreamOutput()->writeln('Create Job completed:');
        $this->_getStreamOutput()->writeln('---------------------');
        $this->_getStreamOutput()->writeln('JobID: ' . $apiResponse->get('JobId'));
        $this->_getStreamOutput()->writeln('');
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
     * @return \Guzzle\Service\Resource\Model
     */
    private function _createAWSJob($manifest, $dryRun = null) {
        if (null === $dryRun) { $dryRun = true; }
        $client = $this->_getAWSClient(CM_Config::get()->awsCredentials);
        $apiResponse = $client->createJob(array(
            'JobType' => 'Export',
            'Manifest' => $manifest,
            'ValidateOnly' => $dryRun,
        ));
        return $apiResponse;
    }

    /**
     * @param array $credentials
     * @return \Aws\ImportExport\ImportExportClient
     */
    private function _getAWSClient($credentials) {
        return Aws\ImportExport\ImportExportClient::factory($credentials);
    }

    /**
     * @return CM_File_Filesystem
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
