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
     * @param string    $deviceName
     * @param bool|null $skipFormat
     * @param bool|null $dryRun
     * @throws CM_Exception
     * @throws CM_Exception_Invalid
     * @throws Exception
     * @internal param bool $confirm
     */
    public function initDisk($deviceName, $skipFormat = null, $dryRun = null) {
        $deviceName = (string) $deviceName;
        $skipFormat = (bool) $skipFormat;
        $dryRun = (bool) $dryRun;

        $device = new S3Export_Device($deviceName);
        if (!$skipFormat) {
            $device->format();
        }
        $device->mount('/media/s3disk_crypted');

        $manifest = new S3Export_AwsBackupManifest();
        $manifest->setDeviceData($this->_gatherDeviceData());

        $awsBackupManager = new S3Export_BackupManager();
        $job = $awsBackupManager->createJob($manifest, $dryRun);
        $awsBackupManager->storeJobSignatureOnDevice($job, $device);

        $device->unmount();

        $this->_getStreamOutput()->writeln('Create Job completed:');
        $this->_getStreamOutput()->writeln('---------------------');
        $this->_getStreamOutput()->writeln('JobID: ' . $job->getId());
        $this->_getStreamOutput()->writeln('');
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
     * @param array $credentials
     * @return \Aws\ImportExport\ImportExportClient
     */
    private function _getAWSClient($credentials) {
        return Aws\ImportExport\ImportExportClient::factory($credentials);
    }

    public static function getPackageName() {
        return 's3export';
    }
}
