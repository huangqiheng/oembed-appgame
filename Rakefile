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
end_of_string

	File.open('oembed-appgame.yml', 'w') do |file|
		file.write yml_str
	end
	puts '请修改oembed-appgame.yml文件，指定插件的安装目录'
end

desc '更新本源代码，从github中。'
task :update do
	system 'git reset --hard HEAD'
	system 'git pull'
end

def install_plugin name
	my_path = File.dirname(__FILE__)

	config = YAML.load_file 'oembed-appgame.yml'
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

desc '默认就是alpha测试操作。'
task :default do
	Rake::Task['alpha'].invoke
end
