<?php

class S3Export_BackupManager implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    /** @var \Aws\ImportExport\ImportExportClient */
    private $_client;

    /**
     * @param array $clientConfig
     */
    public function __construct(array $clientConfig) {
        $this->_client = \Aws\ImportExport\ImportExportClient::factory($clientConfig);
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

    /**
     * @param CM_OutputStream_Interface $output
     * @param CM_File_Filesystem        $backupFilesystem
     */
    public function verifyExport(CM_OutputStream_Interface $output, CM_File_Filesystem $backupFilesystem) {
        $asserter = new S3Export_Asserter();

        $sourceFilesystem = $this->_getFilesystemOriginal();
        // TODO: Performance/memory bottle-neck
        $filePaths = $sourceFilesystem->listByPrefix('*')['files'];
        for ($i = 0; $i < 1000; $i++) {
            $index = array_rand($filePaths);
            $path = $filePaths[$index];

            $sourceFile = new CM_File($path, $sourceFilesystem);
            $backupFile = new CM_File($path, $backupFilesystem);
            $asserter->assertThat($backupFile->exists(), null, function () use ($output, $sourceFile) {
                $output->writeln("Integrity mismatch: Corresponding backup file does not exist for {$sourceFile->getPath()}");
            });
            $asserter->assertThat($sourceFile->getHash() === $backupFile->getHash(), null, function () use ($output, $sourceFile) {
                $output->writeln("Integrity mismatch: Different hashes for {$sourceFile->getPath()}");
            });
        }
        $output->writeln(join(', ', [
            "Assertions run: {$asserter->getAssertionCount()}",
            "succeeded: {$asserter->getAssertionSuccessCount()}",
            "failed: {$asserter->getAssertionFailCount()}"
        ]));

    }

    /**
     * @return CM_File_Filesystem
     * @throws CM_Exception_Invalid
     */
    protected function _getFilesystemOriginal() {
        return CM_Service_Manager::getInstance()->get('s3export-filesystem-original');
    }
}
