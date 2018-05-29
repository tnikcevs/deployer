<?php
namespace Deployer;

require 'recipe/magento2.php';

// Project name
set('application', 'project-deployer');

// Project repository
set('repository', 'git@github.com:tnikcevs/deployer.git');

// Share deployer data
set('allow_anonymous_stats', false);

// How many releases to keep
set('keep_releases', 10);

// Release name by date and time
set('release_name', function () {
    return date('YmdHis');
});

// ?? what to search for in hosts and deploy?
set('default_stage', 'production');

// Shared files/dirs between deploys
// create this in shared directory, as this is server specific and won't be copied on deployment
add('shared_files', [
    'app/etc/env.php',
    'var/.maintenance.ip',
    'pub/robots.txt',
    'pub/.htaccess',
    '.htaccess',
    'robots.txt'
]);

set('writable_dirs', [
    'var',
    'pub/static',
    'pub/media',
    'generated',
    'pub/Assets',
]);

set('clear_paths', [
    'generated/*',
    'var/cache/*'
]);

add('shared_dirs', [
    'var/log',
    'var/backups',
    'pub/media',
    'pub/Assets',
    'audio',
    'pub/feeds',
    'flash',
    'music',
    'node_modules',
    'sitemaps',
    'video',
    'pub/wpd'
]);

// Hosts
inventory('hosts.yml');

// Tasks
desc('Symlink .htaccess files');
task('project:htaccess', function () {
    run("{{bin/symlink}} {{deploy_path}}/shared/.htaccess {{release_path}}/.htaccess");
    run("{{bin/symlink}} {{deploy_path}}/shared/pub/.htaccess {{release_path}}/pub/.htaccess");
});

desc('Production deployment confirmation');
task('project:confirm', function() {
    if(!askConfirmation('Proceed with deployment to production?')) {
        writeln('<comment>Deployment aborted</comment>');
        die;
    }
})->onStage('production');

task('build', function () {
    run('cd {{release_path}} && build');
});

//desc('Disable specific modules');
//task('magento:disable_modules', function () {
//    run("{{bin/php}} {{release_path}}/bin/magento module:disable MageWorx_OptionFeatures MageWorx_OptionInventory Ess_M2ePro");
//});

//desc('Restart PHP FPM');
//task('restart:php-fpm', function () {
//    run("sudo service php-fpm restart");
//});

desc('Magento2 deployment operations');
task('deploy:magento', [
    'magento:enable',
//    'magento:disable_modules',
    'magento:compile',
    'magento:deploy:assets',
    'magento:maintenance:enable',
    'magento:upgrade:db',
    'magento:cache:flush',
    'magento:maintenance:disable'
]);

desc('Deploy assets');
task('magento:deploy:assets', function () {
    run("{{bin/php}} {{release_path}}/bin/magento setup:static-content:deploy en_US en_GB", ['timeout' => null]);
});

task('deploy:vendor', function () {
    run("cd {{release_path}} && {{bin/php}} /usr/local/bin/composer install --no-dev", ['timeout' => null]);
});


// Events
before('deploy', 'project:confirm');
after('deploy:failed', 'deploy:unlock');
//after('magento:cache:flush', 'project:htaccess');
//after('success', 'restart:php-fpm');

task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:vendor',// Install/Update vendor
    'deploy:shared',
    'deploy:writable',
    'deploy:clear_paths',
    'deploy:magento',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);