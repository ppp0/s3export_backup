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
        try {
            $filesystemBackupEncrypted = $this->_mountFilesystemEncrypted($devicePath);
            $truecryptImageFile = $this->_findTruecryptImageFile($filesystemBackupEncrypted);
            $filesystemBackup = $this->_decryptDisk($truecryptImageFile, $truecryptPassword);
            $filesystemOriginal = $this->_getFilesystemOriginal();

            $this->_compareFilesystems($filesystemOriginal, $filesystemBackup, 1000);
        } catch (Exception $e) {
            print('*** Excepshiawn !' . "\n");
            print_r($e->getMessage());
            $this->_cleanup();
        }
    }

    public function foo() {
        $filesystem = $this->_getFilesystemOriginal();
        print_r($filesystem->listByPrefix('tmp', true));
    }

    private function _cleanup() {
        CM_Util::exec('truecrypt', ['-d']);
        CM_Util::exec('umount', [$this->_getLocalFilesystemPath($this->_getFilesystemBackupEncrypted())]);
    }

    /**
     * @param CM_File_Filesystem $filesystemOriginal
     * @param CM_File_Filesystem $filesystemBackup
     * @param int $fileCountMax
     */
    private function _compareFilesystems(CM_File_Filesystem $filesystemOriginal, CM_File_Filesystem $filesystemBackup, $fileCountMax) {

        /**
         * @param CM_File_Filesystem $filesystem
         * @param string $path
         * @param int $fileCount
         * @param int $fileCountMax
         * @return string[]
         */
        function listFilesRecursively(CM_File_Filesystem $filesystem, $path, $fileCount, $fileCountMax) {
            print_r("path: " . $path);
            print "\n";
            $pathList = $filesystem->listByPrefix($path, true);
            $fileList = $pathList['files'];
            $dirList = $pathList['dirs'];
            shuffle($fileList);
            shuffle($dirList);

            while (!empty($fileList) || count($fileList) < $fileCountMax) {
                //check subdirs recursively
                print ("gluglu\n");
                print_r($fileList);
                print "\n";
                $dir = array_pop($dirList);
                if (null !== $dir) {
                    $fileListSubdir = listFilesRecursively($filesystem, $path . '/' . $dir, count($fileList), $fileCountMax);
                    $fileList = array_merge($fileList, $fileListSubdir);
                }
            }

            return $fileList;
        }

        $filePathList = listFilesRecursively($filesystemOriginal, '/', 0, $fileCountMax);
        foreach ($filePathList as $filePath) {
        }

        //        $pathList = $filesystemOriginal->listByPrefix('photo/390', true);
        //        foreach ($pathList as $path) {
        //            foreach ($path as $entry) {
        //                $fileOriginal = new CM_File($entry, $filesystemOriginal);
        //                print $entry . "\n";
        //                print $filesystemOriginal->getChecksum($entry) . " < -- etag \n";
        //            }
        //        }
    }

    /**
     * @todo check if idempotent
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
     * @todo check if idempotent
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
                print "arredy maunted\n";
                return $filesystemDecrypted;
            } else {
                $this->_cleanup();
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
