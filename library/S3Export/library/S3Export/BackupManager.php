<?php

class S3Export_BackupManager {

    /** @var \Aws\ImportExport\ImportExportClient */
    private $client;

    public function __construct() {
        $credentials = CM_Config::get()->awsCredentials;
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
     * @param S3Export_AwsBackupJob $job
     * @param S3Export_Device             $device
     */
    public function storeJobSignatureOnDevice(S3Export_AwsBackupJob $job, S3Export_Device $device) {
        $file = new CM_File('SIGNATURE', $device->getFilesystem());
        $file->write($job->getSignature());
    }
}
