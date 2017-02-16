node default {

  require 'gdisk'

  class { 'cm::application':
    development => true,
  }

}
