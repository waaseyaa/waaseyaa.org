<?php

namespace Deployer;

require 'recipe/common.php';

set('application', 'waaseyaa-org');
set('keep_releases', 5);
set('allow_anonymous_stats', false);

set('shared_dirs', ['storage']);
set('shared_files', ['.env']);
set('writable_dirs', ['storage', 'storage/framework']);

host('production')
    ->setHostname('waaseyaa.org')
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '/home/deployer/waaseyaa-org')
    ->set('labels', ['stage' => 'production']);

desc('Upload pre-built release artifact from CI');
task('deploy:upload', function (): void {
    upload('.build/', '{{release_path}}/', [
        'options' => ['--recursive', '--compress'],
    ]);
});

desc('Compile package manifest into shared storage (required before docs:fetch)');
task('waaseyaa:optimize-manifest', function (): void {
    run('cd {{release_path}} && WAASEYAA_DB={{deploy_path}}/shared/storage/waaseyaa.sqlite php vendor/bin/waaseyaa optimize:manifest');
});

desc('Reload PHP-FPM to pick up new release');
task('php-fpm:reload', function (): void {
    run('sudo systemctl reload php8.4-fpm');
});

desc('Fetch package READMEs and build docs navigation index');
task('docs:fetch', function (): void {
    $token = getenv('GITHUB_TOKEN') ?: '';
    run('cd {{release_path}} && WAASEYAA_DB={{deploy_path}}/shared/storage/waaseyaa.sqlite GITHUB_TOKEN=' . escapeshellarg($token) . ' php vendor/bin/waaseyaa docs:fetch');
});

desc('Deploy waaseyaa.org to production');
task('deploy', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'deploy:upload',
    'deploy:shared',
    'deploy:writable',
    'waaseyaa:optimize-manifest',
    'docs:fetch',
    'deploy:symlink',
    'deploy:unlock',
    'deploy:cleanup',
    'php-fpm:reload',
]);

after('deploy:failed', 'deploy:unlock');
