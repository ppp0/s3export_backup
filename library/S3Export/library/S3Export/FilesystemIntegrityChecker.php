<?php

class S3Export_FilesystemIntegrityChecker {

    /** @var CM_OutputStream_Interface */
    private $_output;

    /** @var CM_File_Filesystem */
    private $_backupFilesystem;

    /** @var CM_File_Filesystem */
    private $_sourceFilesystem;

    /** @var int */
    private $_assertionSuccessCount;

    /** @var int */
    private $_assertionFailCount;

    /**
     * @param CM_OutputStream_Interface $output
     * @param CM_File_Filesystem        $sourceFilesystem
     * @param CM_File_Filesystem        $backupFilesystem
     */
    public function __construct(CM_OutputStream_Interface $output, CM_File_Filesystem $sourceFilesystem, CM_File_Filesystem $backupFilesystem) {
        $this->_output = $output;
        $this->_sourceFilesystem = $sourceFilesystem;
        $this->_backupFilesystem = $backupFilesystem;
        $this->_assertionSuccessCount = 0;
        $this->_assertionFailCount = 0;
    }

    /**
     * @param mixed $assertion
     */
    public function assertThat($assertion) {
        if ($assertion) {
            $this->_assertionSuccessCount++;
        } else {
            $this->_assertionFailCount++;
        }
    }
    /**
     * @return int
     */
    public function getAssertionSuccessCount() {
        return $this->_assertionSuccessCount;
    }

    /**
     * @return int
     */
    public function getAssertionFailCount() {
        return $this->_assertionFailCount;
    }

    /**
     * @return int
     */
    public function getAssertionCount() {
        return $this->getAssertionSuccessCount() + $this->getAssertionFailCount();
    }

    public function checkIntegrity() {
        $filePaths = $this->_sourceFilesystem->listByPrefix('*')['files'];
        foreach ($filePaths as $key => $path) {
            if ($key % 20 !== 0) {
                continue;
            }
            $sourceFile = new CM_File($path, $this->_sourceFilesystem);
            $backupFile = new CM_File($path, $this->_backupFilesystem);
            $this->assertThat($sourceFile->getHash() === $backupFile->getHash());
        }
    }

}
