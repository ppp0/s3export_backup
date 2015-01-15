<?php

class S3Export_BackupManager {

    /** @var \Aws\ImportExport\ImportExportClient */
    private $_client;

    /**
     * @param array $credentials
     */
    public function __construct(array $credentials) {
        $this->_client = Aws\ImportExport\ImportExportClient::factory($credentials);
    }

    /**
     * @param string    $manifest
     * @param bool|null $dryRun
     * @return S3Export_AwsBackupJob
     */
    public function createJob($manifest, $dryRun = null) {
        $apiResponse = $this->_client->createJob(array(
            'JobType'      => 'Export',
            'Manifest'     => (string) $manifest,
            'ValidateOnly' => (bool) $dryRun,
        ));
        return new S3Export_AwsBackupJob($apiResponse->get('JobId'), $apiResponse->get('SignatureFileContents'));
    }

    /**
     * @param string $id
     */
    public function cancelJob($id) {
        $this->_client->cancelJob(['JobId' => (string) $id]);
    }

    /**
     * @param string $id
     * @return \Guzzle\Service\Resource\Model
     */
    public function getJobStatus($id) {
        return $this->_client->getStatus(['JobId' => (string) $id]);
    }

    /**
     * @return \Guzzle\Service\Resource\Model
     */
    public function listJobs() {
        return $this->_client->listJobs();
    }

    /**
     * @param S3Export_AwsBackupJob $job
     * @param S3Export_Device       $device
     */
    public function storeJobSignatureOnDevice(S3Export_AwsBackupJob $job, S3Export_Device $device) {
        $file = new CM_File('SIGNATURE', $device->getFilesystem());
        $file->write($job->getSignature());
    }
}
