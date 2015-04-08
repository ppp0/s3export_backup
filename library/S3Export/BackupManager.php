<?php

class S3Export_BackupManager implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    /** @var array */
    private $_config;

    /** @var \Aws\ImportExport\ImportExportClient */
    private $_client;

    /**
     * @param array $config
     */
    public function __construct(array $config) {
        $this->_config = $config;

        $this->_client = \Aws\ImportExport\ImportExportClient::factory([
            'key'    => $config['key'],
            'secret' => $config['secret'],
        ]);
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
     * @param string[] $jobIds
     * @return \Guzzle\Service\Resource\Model
     */
    public function getShippingLabel(array $jobIds) {
        return $this->_client->getShippingLabel(['jobIds' => $jobIds]);
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
        $filePaths = $this->_getRandomFiles($backupFilesystem, 100, 100000);
        foreach ($filePaths as $path) {
            $backupFile = new CM_File($path, $backupFilesystem);
            $sourceFile = new CM_File($path, $sourceFilesystem);
            $asserter->assertThat($sourceFile->exists(),
                function () use ($output) {
                    $output->write(".");
                },
                function () use ($output, $backupFile) {
                    $output->writeln('E');
                    $output->writeln("Integrity mismatch: Corresponding backup file does not exist for {$backupFile->getPath()}");
                }
            );
            if ($sourceFile->exists()) {
                $asserter->assertThat($sourceFile->getHash() === $backupFile->getHash(),
                    function () use ($output) {
                        $output->write('.');
                    },
                    function () use ($output, $backupFile) {
                        $output->writeln('E');
                        $output->writeln("Integrity mismatch: Different hashes for {$backupFile->getPath()}");
                    }
                );
            }
        }
        $output->writeln('');
        $output->writeln(join(', ', [
            "Assertions run: {$asserter->getAssertionCount()}",
            "succeeded: {$asserter->getAssertionSuccessCount()}",
            "failed: {$asserter->getAssertionFailCount()}"
        ]));
    }

    /**
     * @throws CM_Exception_Invalid
     * @return string
     */
    public function getBucketName() {
        if (empty($this->_config['bucket'])) {
            throw new CM_Exception_Invalid('Cannot find `bucket` in config');
        }
        return $this->_config['bucket'];
    }

    /**
     * @param CM_File_Filesystem $filesystem
     * @param int                $limit
     * @param int                $poolLimit
     * @return string[]
     */
    protected function _getRandomFiles(CM_File_Filesystem $filesystem, $limit, $poolLimit) {
        $files = [];
        $directories = ['/'];
        do {
            $path = array_shift($directories);
            $entries = $filesystem->listByPrefix($path, true);
            $files = array_merge($files, $entries['files']);
            $directories = array_merge($directories, $entries['dirs']);
            shuffle($directories);
        } while (count($files) < $poolLimit && count($directories) > 0);
        shuffle($files);
        return array_slice($files, 0, $limit);
    }

    /**
     * @return CM_File_Filesystem
     * @throws CM_Exception_Invalid
     */
    protected function _getFilesystemOriginal() {
        return CM_Service_Manager::getInstance()->get('s3export-filesystem-original');
    }
}
