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
     * @return \Aws\ImportExport\ImportExportClient
     */
    public function getClient() {
        return $this->client;
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
     * @param S3Export_AwsBackupJob $job
     * @param S3Export_Device       $device
     */
    public function storeJobSignatureOnDevice(S3Export_AwsBackupJob $job, S3Export_Device $device) {
        $file = new CM_File('SIGNATURE', $device->getFilesystem());
        $file->write($job->getSignature());
    }
}
