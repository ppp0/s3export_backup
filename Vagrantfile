Vagrant.configure('2') do |config|
  config.ssh.forward_agent = true
  config.vm.box = 'cargomedia/debian-7-amd64-cm'

  config.vm.hostname = 'www.s3export-backup.dev'
  if Vagrant.has_plugin? 'landrush'
    config.landrush.enable
    config.landrush.tld = 'dev'
    config.landrush.host 's3export-backup.dev'
  end

  if Vagrant.has_plugin? 'vagrant-phpstorm-tunnel'
    config.phpstorm_tunnel.project_home = '/home/vagrant/s3export_backup'
  end

  synced_folder_type = ENV.fetch('SYNC_TYPE', 'nfs')
  synced_folder_type = nil if 'vboxsf' == synced_folder_type

  config.vm.network :private_network, ip: '10.10.10.11'
  config.vm.network :public_network, :bridge => 'en0: Wi-Fi (AirPort)'
  config.vm.synced_folder '.', '/home/vagrant/s3export_backup', :type => synced_folder_type, :rsync__args => %w('--verbose --archive --delete -z')

  config.librarian_puppet.puppetfile_dir = 'puppet'
  config.librarian_puppet.placeholder_filename = '.gitkeep'
  config.librarian_puppet.resolve_options = {:force => true}
  config.vm.provision :puppet do |puppet|
    puppet.module_path = 'puppet/modules'
    puppet.manifests_path = 'puppet/manifests'
  end

  config.vm.provision 'shell', run: 'always', inline: [
    'cd /home/vagrant/s3export_backup',
    'composer --no-interaction install --dev',
    'bin/cm app setup',
    'bin/cm db run-updates',
  ].join(' && ')
end
