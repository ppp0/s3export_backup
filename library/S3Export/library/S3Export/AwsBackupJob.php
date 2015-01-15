<?php

class S3Export_AwsBackupJob {

    /** @var string */
    private $_signature;

    /** @var string */
    private $_id;

    /**
     * @param string $id
     * @param string $signature
     */
    public function __construct($id, $signature) {
        $this->_id = (string) $id;
        $this->_signature = (string) $signature;
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->_id;
    }

    /**
     * @return string
     */
    public function getSignature() {
        return $this->_signature;
    }
}
