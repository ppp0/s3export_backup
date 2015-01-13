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
     * @param string    $manifestPath
     * @param string    $device
     * @param bool|null $skipFormat
     * @param bool|null $dryRun
     * @throws CM_Exception
     * @throws CM_Exception_Invalid
     * @throws Exception
     * @internal param bool $confirm
     */
    public function initDisk($manifestPath, $device, $skipFormat = null, $dryRun = null) {
        $manifestPath = (string) $manifestPath;
        $device = (string) $device;
        $skipFormat = (bool) $skipFormat;
        $dryRun = (bool) $dryRun;

        $deviceData = $this->_gatherDeviceData();
        $manifest = $this->_createAWSManifest($deviceData);

        $apiResponse = $this->_createAWSJob($manifest, $dryRun);
        $this->_prepareDevice($device, $apiResponse->get('SignatureFileContents'), !$skipFormat);

        $signatureFile = new CM_File('SIGNATURE');
        $signatureFile->joinPath($mountpoint);
        $signatureFile->write();
        $this->_unmount();

        $this->_getStreamOutput()->writeln('Create Job completed:');
        $this->_getStreamOutput()->writeln('---------------------');
        $this->_getStreamOutput()->writeln('JobID: ' . $apiResponse->get('JobId'));
        $this->_getStreamOutput()->writeln('');
    }

    private function _formatDevice($device) {
        if (!preg_match('/\d+$/', $device)) {
            CM_Util::exec('sgdisk', ['-o', $device]);
            $startSector = CM_Util::exec('sgdisk', ['-F', $device]);
            $endSector = CM_Util::exec('sgdisk', ['-E', $device]);
            CM_Util::exec('sgdisk', ['-n', '1:' . $startSector . ':' . $endSector, $device]);
            $device = $device . '1';
        }
        CM_Util::exec('sudo mkfs', ['-t', 'ext4', '-m', '0', $device]);
    }

    /**
     * @param string $device
     * @param string $awsSignature
     * @throws CM_Exception
     */
    private function _prepareDevice($device, $awsSignature) {
        $mountpoint = $this->_getLocalFilesystemPath($this->_getFilesystemBackupEncrypted());
        CM_Util::exec('sudo mount', [$device, $mountpoint]);

    }

    /**
     * @return array
     */
    private function _gatherDeviceData() {
        $deviceId = $this->_getStreamInput()->read('Provide device ID');
        $cacheKey = 'DeviceData' . $deviceId;
        $cache = new CM_Cache_Storage_File();
        if (false === ($deviceData = $cache->get($cacheKey))) {
            $this->_getStreamOutput()->writeln('Device not found. Please provide required manifest data.');
            $deviceData = [
                'countryOfOrigin' => $this->_getStreamInput()->read('Country of origin?'),
            ];
            $cache->set($cacheKey, $deviceData);
        }
        return $deviceData;
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
     * @param bool   $dryRun
     * @return \Guzzle\Service\Resource\Model
     */
    private function _createAWSJob($manifest, $dryRun) {
        $client = $this->_getAWSClient(CM_Config::get()->awsCredentials);
        try {
            $apiResponse = $client->createJob(array(
                'JobType'      => 'Export',
                'Manifest'     => $manifest,
                'ValidateOnly' => $dryRun,
            ));
        } catch (Exception $e) {
            $this->_unmount();
            throw $e;
        }
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

    private function _unmount() {
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
