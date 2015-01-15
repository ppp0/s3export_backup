<?php

class S3Export_Cli extends CM_Cli_Runnable_Abstract implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    public function __construct(CM_InputStream_Interface $input = null, CM_OutputStream_Interface $output = null) {
        parent::__construct($input, $output);
        $this->setServiceManager(CM_Service_Manager::getInstance());
    }

    public function listJobs() {
        $this->_getStreamOutput()->writeln(print_r($this->_getBackupManager()->listJobs(), true));
    }

    /**
     * @param string $jobId
     */
    public function getStatus($jobId) {
        $this->_getStreamOutput()->writeln(print_r($this->_getBackupManager()->getJobStatus($jobId), true));
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

    /**
     * @param           $manifestPath
     * @param string    $devicePath
     * @param bool|null $skipFormat
     * @param bool|null $dryRun
     */
    public function createJob($manifestPath, $devicePath, $skipFormat = null, $dryRun = null) {
        $manifestPath = (string) $manifestPath;
        if (!preg_match('/^\//', $manifestPath)) {
            $manifestPath = getcwd() . '/' . $manifestPath;
        }
        $devicePath = (string) $devicePath;
        $skipFormat = (bool) $skipFormat;
        $dryRun = (bool) $dryRun;
        $awsBackupManager = $this->_getBackupManager();

        $this->_getStreamOutput()->writeln('Preparing backup device');
        $device = new S3Export_Device($devicePath);
        if (!$skipFormat) {
            $device->format();
        }
        $device->mount();

        $this->_getStreamOutput()->writeln('Creating AWS backup job');
        $manifestFile = new CM_File($manifestPath);
        $job = $awsBackupManager->createJob($manifestFile->read(), $dryRun);
        $this->_getStreamOutput()->writeln("Job created, id: `{$job->getId()}`");
        $this->_getStreamOutput()->writeln('Storing AWS Signature on backup device');
        $awsBackupManager->storeJobSignatureOnDevice($job, $device);

        $device->unmount();
    }

    /**
     * @param string $jobId
     */
    public function cancelJob($jobId) {
        $this->_getBackupManager()->cancelJob($jobId);
        $this->_getStreamOutput()->writeln('Job successfully cancelled');
    }

    /**
     * @return S3Export_BackupManager
     * @throws CM_Exception_Invalid
     */
    protected function _getBackupManager() {
        return CM_Service_Manager::getInstance()->get('s3export-backup-manager');
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

    public static function getPackageName() {
        return 's3export';
    }
}
