<?php

class S3export_BackupManagerTest extends PHPUnit_Framework_TestCase {

    use \Mocka\MockaTrait;

    public function testVerifyExport() {
        $filesystemSource = $this->_mockFilesystem();

        $outputString = '';
        $output = $this->mockObject('CM_OutputStream_Abstract');
        $output->mockMethod('write')->set(function ($output) use (&$outputString) {
            $outputString .= $output;
        });
        /** @var CM_OutputStream_Abstract $output */

        $backupManager = $this->mockClass('S3Export_BackupManager')->newInstanceWithoutConstructor();
        $backupManager->mockMethod('_getFilesystemOriginal')->set($filesystemSource);
        /** @var S3Export_BackupManager $backupManager */

        $backupManager->verifyExport($output, $filesystemSource);
        $this->assertContains('Assertions run: 20, succeeded: 20, failed: 0', $outputString);
    }

    /**
     * @return CM_File_Filesystem
     * @throws CM_Exception_Invalid
     */
    protected function _mockFilesystem() {
        $tmpdir = CM_File::createTmpDir();
        $adapter = new CM_File_Filesystem_Adapter_Local($tmpdir->getPathOnLocalFilesystem());
        $filesystem = new CM_File_Filesystem($adapter);

        $faker = Faker\Factory::create();

        for ($i = 0; $i < 100; $i++) {
            $path = $faker->lexify('????????' . $i);
            $content = $faker->paragraph(10);
            CM_File::create($path, $content, $filesystem);
        }
        return $filesystem;
    }
}
