<?php

class S3Export_Cli extends CM_Cli_Runnable_Abstract {

    /**
     * @param string $devicePath
     * @param string $truecryptPassword
     */
    public function verifyDisk($devicePath, $truecryptPassword) {
    }

    public static function getPackageName() {
        return 's3export';
    }
}
