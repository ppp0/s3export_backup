<?php

class S3Export_TruecryptImage {

    /** @var CM_File */
    private $_image;

    /** @var string */
    private $_truecryptPassword;

    /** @var string */
    private $_mountpointPath;

    /**
     * @param CM_File $image
     * @param string  $truecryptPassword
     */
    public function __construct(CM_File $image, $truecryptPassword) {
        $this->_image = $image;
        $this->_truecryptPassword = (string) $truecryptPassword;
    }

    /**
     * @throws CM_Exception
     */
    public function mount() {
        $tmpDir = CM_File::createTmpDir();
        $this->_mountpointPath = (string) $tmpDir->getPathOnLocalFilesystem();

        CM_Util::exec('truecrypt', [
            "--password={$this->_truecryptPassword}",
            '--protect-hidden=no',
            '--keyfiles=',
            $this->_image->getPathOnLocalFilesystem(),
            $this->_mountpointPath,
        ]);
    }

    public function unmount() {
        CM_Util::exec('truecrypt', [
            "--dismount={$this->_image->getPathOnLocalFilesystem()}"
        ]);
    }

    /**
     * @return CM_File
     */
    public function getImage() {
        return $this->_image;
    }

    /**
     * @return CM_File_Filesystem
     */
    public function getFilesystem() {
        $adapter = new CM_File_Filesystem_Adapter_Local($this->_mountpointPath);
        $filesystem = new CM_File_Filesystem($adapter);
        return $filesystem;
    }
}
