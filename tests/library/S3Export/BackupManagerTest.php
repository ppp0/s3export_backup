<?php

class S3export_BackupManagerTest extends PHPUnit_Framework_TestCase {

    use \Mocka\MockaTrait;

    public function testVerifyExport() {
        $filesystemSource = $this->_mockFilesystem();
        $filesystemSource->ensureDirectory('foo');
        $filesystemSource->write('foo/foo', 'foo-foo');
        $filesystemSource->write('foo/bar', 'foo-bar');
        $filesystemSource->ensureDirectory('bar');
        $filesystemSource->write('bar/foo', 'bar-foo');

        $filesystemBackup = $this->_mockFilesystem();
        $filesystemBackup->ensureDirectory('foo');
        $filesystemBackup->write('foo/foo', 'foo-foo');
        $filesystemBackup->write('foo/bar', 'foo-bar');
        $filesystemBackup->ensureDirectory('bar');
        $filesystemBackup->write('bar/foo', 'bar-foo-different-hash');
        $filesystemBackup->write('bar/bar', 'bar-bar');

        $outputString = '';
        $output = $this->mockObject('CM_OutputStream_Abstract');
        $output->mockMethod('write')->set(function ($output) use (&$outputString) {
            $outputString .= $output;
        });
        /** @var CM_OutputStream_Abstract $output */

        $backupManager = $this->mockClass('S3Export_BackupManager')->newInstanceWithoutConstructor();
        $backupManager->mockMethod('_getFilesystemOriginal')->set($filesystemSource);
        /** @var S3Export_BackupManager $backupManager */

        $backupManager->verifyExport($output, $filesystemBackup);
        $this->assertContains('Assertions run: 7, succeeded: 5, failed: 2', $outputString);
    }

    public function testListFiles() {
        $filesystem = $this->_mockFilesystem();
        $this->_fillFilesystemWithRandomFiles($filesystem);

        /** @var \Mocka\AbstractClassTrait $filesystem */
        $listByPrefixMethod = $filesystem->mockMethod('listByPrefix')->set(function ($path, $noRecursion) use ($filesystem) {
            return $filesystem->callOriginalMethod('listByPrefix', [$path, $noRecursion]);
        });
        /** @var CM_File_Filesystem $filesystem */

        $backupManager = $this->mockClass('S3Export_BackupManager')->newInstanceWithoutConstructor();
        /** @var S3Export_BackupManager $backupManager */

        $this->assertSame(0, $listByPrefixMethod->getCallCount());
        $this->assertCount(45, CMTest_TH::callProtectedMethod($backupManager, '_getRandomFiles', [$filesystem, 45]));
        // 6 times, because it needs to list root folder and then list 5 additional folders (10 files each) to get 45 files
        $this->assertSame(6, $listByPrefixMethod->getCallCount());

        $this->assertCount(200, CMTest_TH::callProtectedMethod($backupManager, '_getRandomFiles', [$filesystem, 201]));
        $this->assertSame(6 + 21, $listByPrefixMethod->getCallCount());
    }

    /**
     * @return \Mocka\AbstractClassTrait|CM_File_Filesystem
     * @throws CM_Exception_Invalid
     */
    protected function _mockFilesystem() {
        $tmpdir = CM_File::createTmpDir();
        $adapter = new CM_File_Filesystem_Adapter_Local($tmpdir->getPathOnLocalFilesystem());
        return $this->mockClass('CM_File_Filesystem')->newInstance([$adapter]);
    }

    /**
     * @param CM_File_Filesystem $filesystem
     */
    protected function _fillFilesystemWithRandomFiles(CM_File_Filesystem $filesystem) {
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
    }
}
