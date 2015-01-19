node default {

  require 'truecrypt'

  class { 'cm::application':
    development => true,
  }

  environment::variable { 'PHP_IDE_CONFIG':
    value => 'serverName=www.s3export_backup.dev',
  }
}
