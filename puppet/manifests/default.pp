node default {

  require 'truecrypt'
  require 'gdisk'

  class { 'cm::application':
    development => true,
  }

  env::variable { 'PHP_IDE_CONFIG':
    value => 'serverName=www.s3export_backup.dev',
  }
}
