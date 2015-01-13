<?php

class S3Export_AwsBackupManifest {

    /** @var array */
    private $_deviceData;

    /**
     * @param array $deviceData
     */
    public function setDeviceData(array $deviceData) {
        $this->_deviceData = $deviceData;
    }

    /**
     * @return string
     */
    public function getContent() {
        return '';
    }
}
