<?php

class S3Export_Cli extends CM_Cli_Runnable_Abstract implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    public function __construct(CM_InputStream_Interface $input = null, CM_OutputStream_Interface $output = null) {
        parent::__construct($input, $output);
        $this->setServiceManager(CM_Service_Manager::getInstance());
    }

    public function __destruct() {
        try {
            CM_Util::exec('sudo truecrypt', ['-d']);
            CM_Util::exec('sudo umount', [$this->_getLocalFilesystemPath($this->_getFilesystemBackupEncrypted())]);
        } catch (Exception $ignored) {
        }
    }

    /**
     * @param string $devicePath
     * @param string $truecryptPassword
     */
    public function verifyDisk($devicePath, $truecryptPassword) {
        try {
            $filesystemBackupEncrypted = $this->_mountFilesystemEncrypted($devicePath);
            $truecryptImageFile = $this->_findTruecryptImageFile($filesystemBackupEncrypted);
            $filesystemBackup = $this->_decryptDisk($truecryptImageFile, $truecryptPassword);
            $filesystemOriginal = $this->_getFilesystemOriginal();
        } catch (Exception $e) {
            print($e->getMessage());
        }
        $result = $this->_compareFilesystems($filesystemOriginal, $filesystemBackup, 10);
        print "\nCheck completed\n";
        print "Files checked: " . $result['files'] . "\n";
        foreach ($result['errors'] as $errorType => $occurences) {
            print $errorType . ": " . $occurences . "\n";
        }
        print ".\n";
    }

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

    public function getStatus($jobId) {
        $client = $this->_getAWSClient(CM_Config::get()->awsCredentials);
        print_r($client->getStatus(array(
            'JobId' => $jobId,
        )));
    }

    /**
     *
     * @param string $devicePath
     * @return CM_File_Filesystem
     */
    private function _mountFilesystemEncrypted($devicePath) {
        $filesystemEncrypted = $this->_getFilesystemBackupEncrypted();
        $mountPoint = rtrim($this->_getLocalFilesystemPath($filesystemEncrypted), '/');
        $deviceWithoutPartition = preg_replace('/\d+$/', '', $devicePath);
        $partitionsOnDevice = explode("\n", CM_Util::exec('lsblk', ['-nr', $deviceWithoutPartition]));
        array_pop($partitionsOnDevice);
        if (count($partitionsOnDevice) == 1) {
            $this->_getStreamOutput()->writeln('Fixing the partition table...');
            CM_Util::exec('sgdisk', ['--move-second-header', $deviceWithoutPartition]);
        }
        $mounted = CM_Util::exec('cat', ['/proc/mounts']);
        if (0 == preg_match('/' . preg_quote($devicePath, '/') . '.+' . preg_quote($mountPoint, '/') . '/', $mounted)) {
            CM_Util::exec('mount', [$devicePath, $mountPoint]);
        }
        return $filesystemEncrypted;
    }

    /**
     * @param CM_File_Filesystem $filesystem
     * @return CM_File
     * @throws CM_Exception
     */
    private function _findTruecryptImageFile(CM_File_Filesystem $filesystem) {
        $pathList = $filesystem->listByPrefix('/', true)['files'];
        $imagePath = Functional\first($pathList, function ($path) {
            return preg_match('/\.tc$/', $path);
        });
        if (null === $imagePath) {
            throw new CM_Exception('Cannot find .tc file on');
        }
        return new CM_File($imagePath, $filesystem);
    }

    /**
     * @param CM_File $truecryptImageFile
     * @param $truecryptPassword
     * @return CM_File_Filesystem
     */
    private function _decryptDisk(CM_File $truecryptImageFile, $truecryptPassword) {
        $filesystemDecrypted = $this->_getFilesystemBackupDecrypted();
        try {
            CM_Util::exec('truecrypt', [
                '-p', $truecryptPassword,
                '--protect-hidden=no',
                '--keyfiles=',
                $truecryptImageFile->getPathOnLocalFilesystem(),
                $this->_getLocalFilesystemPath($filesystemDecrypted),
            ]);
        } catch (CM_Exception $e) {
            if (preg_match('/The volume.+is already mounted/', $e->getMessage()) > 0) {
                return $filesystemDecrypted;
            } else {
                throw $e;
            }
        }
        return $filesystemDecrypted;
    }

    /**
     * @return CM_File_Filesystem
     * @throws CM_Exception_Invalid
     */
    private function _getFilesystemOriginal() {
        return $this->getServiceManager()->get('s3export-filesystem-original', 'CM_File_Filesystem');
    }

    /**
     * @return CM_File_Filesystem
     * @throws CM_Exception_Invalid
     */
    private function _getFilesystemBackupDecrypted() {
        return $this->getServiceManager()->get('s3export-filesystem-backup-decrypted', 'CM_File_Filesystem');
    }

    /**
     *
     * @param string $manifest
     * @param bool $dryRun
     * @return String awsJobId
     */
    private function _createAWSJob($manifest, $dryRun = true) {
        $client = $this->_getAWSClient(CM_Config::get()->awsCredentials);
        $apiResponse = $client->createJob(array(
            'JobType' => 'Export',
            'Manifest' => $manifest,
            'ValidateOnly' => $dryRun,
        ));
        return $apiResponse;
    }
    
    private function _getAWSClient($credentials) {
        return Aws\ImportExport\ImportExportClient::factory($credentials);
    }

    /**
     * @return CM_File_Filesystem
     * @throws CM_Exception_Invalid
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

    public static function getPackageName() {
        return 's3export';
    }
}
