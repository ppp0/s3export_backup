s3export_backup
===============

Command line tool to initiate and verify backups from AWS S3 Export on Debian.

## Installation and Configuration

### Puppet
Recommended way to install `s3export_backup` is using puppet.
As Cargomedia we provide and support our own module, please take a look at https://github.com/cargomedia/puppet-packages/tree/master/modules/s3export_backup

#### Configuration
There is a configuration file `./resources/config/local.php` where you need to provide your S3 credentials.

## Usage
When installed via puppet there should be global binary `s3export` (otherwise look for `./bin/s3export` inside the project). Binary provides various subcommands - listed below.
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
 s3export get-service-manager
 s3export get-status <job-id>
 s3export list-jobs
 s3export set-service-manager <service-manager>
 s3export verify-backup <device-path> <truecrypt-password> [--target-directory=<value>]
```

### Creating a Backup Job
Extensive documentation is found [here](http://awsdocs.s3.amazonaws.com/ImportExport/latest/IE-qrc.pdf) - be aware that only *Export* is of importance here.

```
#Example
$ s3export create-job ./manifest /dev/sdb1 --dry-run
```

You need to provide a `manifest` file which has to be compiled according to [this reference](http://docs.aws.amazon.com/AWSImportExport/latest/DG/ManifestFileRef_Export.html).
Please consult the provided example manifest file and modify it according to your needs. Be aware that only `ext4` is supported right now.

#### Customers Outside the U.S.
When sending drives across customs, you need to add a `customs:` section to the manifest file. See the our example file and [this reference](http://docs.aws.amazon.com/AWSImportExport/latest/DG/ManifestFileRef_international.html) on how to proceed.

### Backup Verification
Backup verification requires that the physical drive be sent back by Amazon and properly a configured corresponding S3 filesystem (see Configuration section).

Tool scans backup drive for 100 random files. Each file is verified against remote S3 filesystem using two checks
- checks if corresponding file exists on remote filesystem
- compares local (backup) and remote (source) file hashes

#### Usage

```sh
s3export verify-backup /dev/sdb1 mysupersecurepassword --target-directory=/s3-export-bucket/
```

* `--target-directory=` is mandatory is you have provided a `targetDirectory:` in the `manifest`. The verification is not able to find the backup's root on the external disk otherwise.
* As described above, 100 random files on the disk will be compared to their counterparts on S3. You obviously need to have internet access to accomplish this and to be aware that even though only the file's metadata is being transferred, you will be charged for the amount of data transferred.

#### Output Interpretation
Maintainer should manually look into verification command output and analyze it.

* File Does not Exist
   Not critical as a file might have been already deleted from S3 filesystem until arrival of the backup drive.

* Different Hashes
   To be taken seriously: file content differ which is likely to be a backup error
