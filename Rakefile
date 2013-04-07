#encoding: utf-8

require 'fileutils'
require 'yaml'

desc '初始化yml文件。'
task :init do
	yml_str = <<end_of_string
install:
  - '/srv/http/public_html/site1/wp-content/plugins'
  - '/srv/http/public_html/site2/wp-content/plugins'
  - '/srv/http/public_html/site3/wp-content/plugins'

alpha:
  - '/srv/http/public_html/site1/wp-content/plugins'

chown:
  user: www-data
  group: www-data

discuz:
  login_url: 'http://bbs.appgame.com/logging.php?action=login'
  username: admin
  password: 123456

error_log:
  tail: '/var/log/fpm-php.bbs.log'

cache_path: '/srv/http/public_html/site/app'
end_of_string

	File.open('config.yml', 'w') do |file|
		file.write yml_str
	end
	puts '请修改config.yml文件，指定插件的安装目录'
end

def config
	YAML.load_file 'config.yml'
end

namespace :cache do
	desc '查看全部cache的内容'
	task :all do
		system "ls -lt '#{config['cache_path']}'| grep cache"
	end
	desc '查看所cache的mobile内容'
	task :mobile do
		system "ls -lt '#{config['cache_path']}'| grep mobile"
	end
	desc '查看所cache的未命中内容内容'
	task :error do
		system "ls -lt '#{config['cache_path']}'| grep ERROR"
	end
	desc '查看全部内容计数'
	task :count do
		system "ls '#{config['cache_path']}'| grep cache | wc -l"
	end

	namespace :rm do
		desc '删除全部cache的内容'
		task :all do
			system "find '#{config['cache_path']}' -type f | grep cache | xargs rm"
		end
		desc '删除所cache的mobile内容'
		task :mobile do
			system "find '#{config['cache_path']}' -type f | grep mobile | xargs rm"
		end
		desc '删除所cache的未命中内容'
		task :error do
			system "find '#{config['cache_path']}' -type f | grep ERROR | xargs rm"
		end
		desc '删除所cache的bbs的内容'
		task :bbs do
			system "find '#{config['cache_path']}' -type f | grep bbs.appgame | xargs rm"
		end
	end

	desc '删除cache的指定grep匹配的'
	task :rm, :key  do |t, args|
		system "find '#{config['cache_path']}' -type f | grep 'cache.*#{args[:key]}' | xargs rm"
	end
end

desc '查看cache，指定关键字'
task :cache, :key do |t, args|
	system "ls -lt '#{config['cache_path']}'| grep 'cache.*#{args[:key]}'"
end

desc '查看error_log日志，使用tail -f命令'
task :log do
	system "tail -f '#{config['error_log']['tail']}'"
end

desc '从github中，更新本源代码。'
task :pull do
	system 'git reset --hard HEAD'
	system 'git pull'
end

def install_plugin name
	my_path = File.dirname(__FILE__)

	config = YAML.load_file 'config.yml'
	alpha = config[name]
	chown = config['chown']
	install_baton = true 

	alpha.each do |dist_path|
		if File.directory? dist_path
			puts("当前程序目录是：#{my_path}") if install_baton
			puts "安装程序到：#{dist_path}"
			FileUtils.cp_r(my_path, dist_path)
			FileUtils.chown_R chown['user'], chown['group'], dist_path
			install_baton = false
		end
	end
end

desc '根据提供的yml文件，将php程序安装到指定地点。'
task :install do
	install_plugin 'install'
end

desc '根据提供的yml文件，将php程序安装到“测试目录”中。'
task :alpha do
	install_plugin 'alpha'
end

desc '默认就是rake log测试操作。'
task :default => ['log']

desc '从github中，更新本源代码，并且执行install安装过程。'
task :update => [:pull, :install]

