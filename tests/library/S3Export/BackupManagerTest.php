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
        $this->assertContains('Assertions run: 40, succeeded: 40, failed: 0', $outputString);
    }

    public function testListFiles() {
        $adapter = $this->_mockFilesystem()->getAdapter();
        $filesystem = $this->mockClass('CM_File_Filesystem')->newInstance([$adapter]);
        $listByPrefixMethod = $filesystem->mockMethod('listByPrefix')->set(function($path, $noRecursion) use ($filesystem) {
            return $filesystem->callOriginalMethod('listByPrefix', [$path, $noRecursion]);
        });
        /** @var CM_File_Filesystem $filesystem */

        $backupManager = $this->mockClass('S3Export_BackupManager')->newInstanceWithoutConstructor();
        /** @var S3Export_BackupManager $backupManager */

        $this->assertSame(0, $listByPrefixMethod->getCallCount());
        $this->assertCount(45, $backupManager->listFiles($filesystem, 45));
        // It needs to list root folder and then list 5 additional folders (10 files each) to get 45 files
        $this->assertSame(6, $listByPrefixMethod->getCallCount());

        $this->assertCount(200, $backupManager->listFiles($filesystem, 201));
        $this->assertSame(6 + 21, $listByPrefixMethod->getCallCount());
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

        for ($i = 0; $i < 20; $i++) {
            $directory = $faker->lexify('????????' . $i);
            for ($j = 0; $j < 10; $j++) {
                $path = $faker->lexify('????????' . $j);
                $content = $faker->paragraph(10);
                $file = new CM_File($directory . '/' . $path, $filesystem);
                $file->ensureParentDirectory();
                $file->write($content);
            }
        }
        return $filesystem;
    }
}
