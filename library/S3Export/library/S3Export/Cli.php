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
        $awsBackupManager = $this->_getBackupManager();

        $this->_getStreamOutput()->writeln('Preparing backup device');
        $device = new S3Export_Device($deviceName);
        if (!$skipFormat) {
            $device->format();
        }
        $device->mount('/media/s3disk_crypted');

        $manifest = new S3Export_AwsBackupManifest();
        $manifest->setDeviceData($this->_gatherDeviceData());
        $this->_getStreamOutput()->writeln('Creating AWS backup job');
        $job = $awsBackupManager->createJob($manifest, $dryRun);
        $this->_getStreamOutput()->writeln("Job created. ID: `{$job->getId()}`");

        $this->_getStreamOutput()->writeln('Storing AWS Signature on backup device');
        $awsBackupManager->storeJobSignatureOnDevice($job, $device);

        $device->unmount();
    }

    /**
     * @param string $jobId
     */
    public function cancelJob($jobId) {
        print_r($this->_getBackupManager()->getClient()->cancelJob(array(
            'JobId' => $jobId,
        )));
    }

    public function listJobs() {
        print_r($this->_getBackupManager()->getClient()->listJobs());
    }

    /**
     * @param string $jobId
     */
    public function getStatus($jobId) {
        print_r($this->_getBackupManager()->getClient()->getStatus(array(
            'JobId' => $jobId,
        )));
    }

    /**
     * @return array
     */
    protected function _gatherDeviceData() {
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
     * @return S3Export_BackupManager
     * @throws CM_Exception_Invalid
     */
    protected function _getBackupManager() {
        return CM_Service_Manager::getInstance()->get('s3export-backup-manager');
    }

    public static function getPackageName() {
        return 's3export';
    }
}
