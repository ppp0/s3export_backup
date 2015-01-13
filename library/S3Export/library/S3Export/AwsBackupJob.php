<?php

class S3Export_AwsBackupJob {

    /** @var string */
    private $signature;

    /**
     * @param string $id
     * @param string $signature
     */
    public function __construct($id, $signature) {
        $this->_id = (string) $id;
        $this->signature = (string) $signature;
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
        return $this->signature;
    }
}
