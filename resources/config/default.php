<?php

return function (CM_Config_Node $config) {
    $config->debug = true;

    $config->services['s3export-filesystem-original'] = array(
        'class'  => 'CM_File_Filesystem_Factory',
        'method' => array(
            'name'      => 'createFilesystem',
            'arguments' => array(
                'CM_File_Filesystem_Adapter_AwsS3',
                array(
                    'bucket' => '<bucket>',
                    'region' => '<region>',
                    'key'    => '<access-key>',
                    'secret' => '<secret-access-key>',
                ),
            ),
        ));

    $config->services['s3export-filesystem-backup-encrypted'] = array(
        'class'  => 'CM_File_Filesystem_Factory',
        'method' => array(
            'name'      => 'createFilesystem',
            'arguments' => array(
                'CM_File_Filesystem_Adapter_Local',
                array(
                    'pathPrefix' => '/media/s3disk_crypted',
                )
            ),
        ));

    $config->services['s3export-backup-manager'] = [
        'class'     => 'S3Export_BackupManager',
        'arguments' => [
            'key'    => '<access-key>',
            'secret' => '<secret-access-key>',
        ]
    ];
};
