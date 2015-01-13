<?php

class S3Export_BackupManager {

    /** @var \Aws\ImportExport\ImportExportClient */
    private $client;

    /**
     * @param array $credentials
     */
    public function __construct(array $credentials) {
        $this->client = Aws\ImportExport\ImportExportClient::factory($credentials);
    }

    /**
     * @param S3Export_AwsBackupManifest $manifest
     * @param bool|null                  $dryRun
     * @return S3Export_AwsBackupJob
     */
    public function createJob(S3Export_AwsBackupManifest $manifest, $dryRun = null) {
        $apiResponse = $this->client->createJob(array(
            'JobType'      => 'Export',
            'Manifest'     => $manifest->getContent(),
            'ValidateOnly' => (bool) $dryRun,
        ));
        $signature = new S3Export_AwsBackupJob($apiResponse->get('JobId'), $apiResponse->get('SignatureFileContents'));
        return $signature;
    }

    /**
     * @param string $id
     */
    public function cancelJob($id) {
        $this->_getClient()->cancelJob(['JobId' => (string) $id]);
    }

    /**
     * @param string $id
     * @return \Guzzle\Service\Resource\Model
     */
    public function getJobStatus($id) {
        return $this->_getClient()->getStatus(['JobId' => (string) $id]);
    }

    /**
     * @return \Guzzle\Service\Resource\Model
     */
    public function listJobs() {
        return $this->_getClient()->listJobs();
    }

    /**
     * @param S3Export_AwsBackupJob $job
     * @param S3Export_Device       $device
     */
    public function storeJobSignatureOnDevice(S3Export_AwsBackupJob $job, S3Export_Device $device) {
        $file = new CM_File('SIGNATURE', $device->getFilesystem());
        $file->write($job->getSignature());
    }

    /**
     * @return \Aws\ImportExport\ImportExportClient
     */
    protected function _getClient() {
        return $this->client;
    }
}
