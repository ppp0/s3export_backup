s3export_backup
===============

`s3export_backup` is a command line interface to [AWS Export services](http://aws.amazon.com/importexport/).

Installation and Configuration
------------------------------
It is recommended to install `s3export_backup` by using [librarian puppet](https://github.com/rodjek/librarian-puppet). Module can be found at https://github.com/cargomedia/puppet-packages/tree/master/modules/s3export_backup.


Sandbox
-------
Starting provided Vagrantfile will spin up a debian box with all the dependencies needed.
The application still needs adjustments in the configuration file `./resources/config/local.php`.
To use an external usb storage device please refer to https://www.virtualbox.org/manual/ch03.html#settings-usb

Usage
-----
When installed via puppet there should be a global binary `s3export` (otherwise look for `./bin/s3export` inside the project).
```
Usage:
 [options] <command> [arguments]

Options:
 --quiet
 --quiet-warnings
 --non-interactive
 --forks=<value>

Commands:
 s3export cancel-job <job-id>
 s3export create-job <manifest-path> <device-path> [--skip-format] [--dry-run]
 s3export get-status <job-id>
 s3export list-jobs
 s3export verify-backup <device-path> <truecrypt-password> [--target-directory=<value>]
```

### Creating a Backup Job

```
$ s3export create-job ./manifest /dev/sdb1 --dry-run
```

You need to provide a `manifest` file which has to be compiled according to [this reference](http://docs.aws.amazon.com/AWSImportExport/latest/DG/ManifestFileRef_Export.html).
Please consult the provided example manifest file and modify it according to your needs. Be aware that only `ext4` is supported right now.

#### Customers Outside the U.S.
When sending drives across customs, you need to add a `customs:` section to the manifest file. See our example file and [this reference](http://docs.aws.amazon.com/AWSImportExport/latest/DG/ManifestFileRef_international.html) on how to proceed.

### Backup Verification

```
$ s3export verify-backup /dev/sdb1 mysupersecurepassword --target-directory=/s3-export-bucket/
```
`--target-directory=` is mandatory if you have provided a `targetDirectory:` in the `manifest`. The verification is not able to find the backup's root on the external disk otherwise.

Backup verification requires that the physical drive be sent back by Amazon and properly a configured corresponding S3 filesystem (see Configuration section).

Tool scans backup drive for 100 random files. Each file is verified against remote S3 filesystem using two checks
- checks if corresponding file exists on remote filesystem
  - Not critical as a file might have been already deleted from S3 filesystem until arrival of the backup drive.
- compares local (backup) and remote (source) file hashes
  - Failures to be taken seriously: file content differ which is likely to be a backup error
   
Maintainer should manually look into verification command output and analyze it.
*As described above, 100 random files on the disk will be compared to their counterparts on S3. You obviously need to have internet access to accomplish this and to be aware that even though only the file's metadata is being transferred, you will be charged for the amount of data transferred.*
