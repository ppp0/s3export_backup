node default {

  require 'truecrypt'

  package {['gdisk']:}

  class {'cm::application':
  development => true,
  }

  environment::variable {'PHP_IDE_CONFIG':
  value => 'serverName=www.cm.dev',
  }

  file {['/media/s3disk_crypted', '/media/s3disk_decrypted']:
      ensure => directory,
      owner => '0',
      group => '0',
      mode => '0755',
  }

}
