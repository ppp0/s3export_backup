s3export_backup
===============

Command line tool to initiate and verify backups from AWS S3 Export on Debian.

## Installation and configuration

### Puppet
Recommended way to install `s3export_backup` is using puppet.
As Cargomedia we provide and support our own module, please take a look at https://github.com/cargomedia/puppet-packages/tree/master/modules/s3export_backup

### Manual
It is not recommended to install `s3export_backup` manually as it depends on several other libraries and is most likely hard to maintain.

Still it's possible to install it via composer (https://packagist.org/packages/cargomedia/s3export_backup).
A binary `./bin/s3export` should be executable once installation process is complete.

Additionally all listed requirements need to be installed manually:
- cm framework dependencies (php5, apcu, memcache, curl)
- gdisk
- truecrypt

For more hints look into https://github.com/cargomedia/puppet-packages/blob/master/modules/s3export_backup/manifests/init.pp

#### Configuration
There is single configuration file `./resources/config/local.php` which needs to be adjusted .
Replace dummy variables with correct values as most features require access to remote (backup source) S3 filesystem.


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

### Backup verification
Backup verification requires physical drive sent back by Amazon and properly configured S3 filesystem (see Configuration section).

Tool scans backup drive for 100 random files. Each file is verified against remote S3 filesystem using two checkes
- checks if corresponding file exists on remote filesystem
- compares local (backup) and remote (source) file hashes

#### Output interpretation
Maintainer should manually look into verification command output and analyze it.

Failing existence check can be assumed as normal, as a file might have been already deleted from S3 filesystem until arrival of the backup drive.
The second check (hash) is far more important and any mismatches should be treated as potential backup errors.
