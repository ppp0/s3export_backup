s3export_backup
===============

Command line tool to initiate and verify backups from AWS S3 Export on Debian.

## Installation

### Puppet
Recommended way to install s3export_backup is using puppet.
As Cargomedia we provide and support our own module, please take a look at https://github.com/cargomedia/puppet-packages/tree/master/modules/s3export_backup

### Manual installation
It is not recommended to install tool manually as it depends on several other libraries and is most likely hard to maintain.
It's possible to install s3export_backup via composer (https://packagist.org/packages/cargomedia/s3export_backup)
A binary `./bin/s3export` should be executable once installation process is complete.

Requirements:
- cm framework dependencies (php5, apcu, memcache, curl)
- gdisk
- truecrypt
For more hints look at https://github.com/cargomedia/puppet-packages/blob/master/modules/s3export_backup/manifests/init.pp


## Configuration
Most of functionalities require access to source S3 filesystem.
To provide access for the tool a configuration file needs to be adjusted. Open `resources/config/local.php` and adjust dummy variables with correct values.

## Usage
When installed via puppet there should be global binary `s3export` (otherwise look for `./bin/s3export` inside the project). Binary provides various subcommands listed below.
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
Backup verification requires physical drive sent back by Amazon and properly configured s3 filesystem (see Configuration section).
Tool scans backup drive and finds 100 random files. Each file is verified against S3 filesystem.
Each verification consists of two checks:
- existence of the corresponding file on remote filesystem
- comparison between local (backup) and remote (source) file hashes

#### Output interpretation
Maintainer should manually look into verification command output and interpret it on his own.
Failing existence check can be assumed as normal, as a file might have been already deleted from S3 filesystem until arrival of the backup.
The second check (hash) is far more important and any mismatches should be treated seriously as potential backup errors.
