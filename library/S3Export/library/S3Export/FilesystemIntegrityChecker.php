<?php

class S3Export_FilesystemIntegrityChecker {

    /** @var CM_OutputStream_Interface */
    private $_output;

    /** @var CM_File_Filesystem */
    private $_backupFilesystem;

    /** @var CM_File_Filesystem */
    private $_sourceFilesystem;

    /** @var S3Export_Asserter */
    private $_asserter;

    /**
     * @param CM_OutputStream_Interface $output
     * @param CM_File_Filesystem        $sourceFilesystem
     * @param CM_File_Filesystem        $backupFilesystem
     */
    public function __construct(CM_OutputStream_Interface $output, CM_File_Filesystem $sourceFilesystem, CM_File_Filesystem $backupFilesystem) {
        $this->_output = $output;
        $this->_sourceFilesystem = $sourceFilesystem;
        $this->_backupFilesystem = $backupFilesystem;
        $this->_asserter = new S3Export_Asserter();
    }

    public function checkIntegrity() {
        $filePaths = $this->_sourceFilesystem->listByPrefix('*')['files'];
        foreach ($filePaths as $key => $path) {
            if ($key % 20 === 0) {
                continue;
            }
            $sourceFile = new CM_File($path, $this->_sourceFilesystem);
            $backupFile = new CM_File($path, $this->_backupFilesystem);
            $this->_asserter->assertThat($backupFile->exists(), null, function () use ($sourceFile) {
                $this->_output->writeln("Corresponding backup file does not exist for {$sourceFile->getPath()}");
            });
            $this->_asserter->assertThat($sourceFile->getHash() === $backupFile->getHash(), null, function () use ($sourceFile) {
                $this->_output->writeln("Different hashes for {$sourceFile->getPath()}");
            });
        }
        $this->_output->writeln(join(', ', [
            "Assertions run: {$this->_asserter->getAssertionCount()}",
            "successes: {$this->_asserter->getAssertionSuccessCount()}",
            "failures: {$this->_asserter->getAssertionFailCount()}"
        ]));
    }
}
