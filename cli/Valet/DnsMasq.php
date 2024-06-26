<?php

namespace Valet;

use Exception;
use Valet\Contracts\PackageManager;
use Valet\Contracts\ServiceManager;

class DnsMasq
{
    public $pm;
    public $sm;
    public $cli;
    public $files;
    public $rclocal;
    public $resolvconf;
    public $dnsmasqconf;
    public $dnsmasqOpts;
    public $resolvedConfigPath;
    public $configPath;
    public $nmConfigPath;

    /**
     * Create a new DnsMasq instance.
     *
     * @param PackageManager $pm    PackageManager object
     * @param ServiceManager $sm    ServiceManager object
     * @param Filesystem     $files Filesystem     object
     * @param CommandLine    $cli   CommandLine    object
     *
     * @return void
     */
    public function __construct(PackageManager $pm, ServiceManager $sm, Filesystem $files, CommandLine $cli)
    {
        $this->pm = $pm;
        $this->sm = $sm;
        $this->cli = $cli;
        $this->files = $files;
        $this->rclocal = '/etc/rc.local';
        $this->resolvconf = '/etc/resolv.conf';
        $this->dnsmasqconf = '/etc/dnsmasq.conf';
        $this->configPath = '/etc/dnsmasq.d/valet';
        $this->dnsmasqOpts = '/etc/dnsmasq.d/options';
        $this->nmConfigPath = '/etc/NetworkManager/conf.d/valet.conf';
        $this->resolvedConfigPath = '/etc/systemd/resolved.conf';
    }

    /**
     * Install and configure DnsMasq.
     *
     * @param bool $lock Lock or Unlock the file
     *
     * @return void
     */
    private function _lockResolvConf($lock = true)
    {
        $arg = $lock ? '+i' : '-i';

        if (!$this->files->isLink($this->resolvconf)) {
            $this->cli->run(
                "chattr {$arg} {$this->resolvconf}",
                function ($code, $msg) {
                    warning($msg);
                }
            );
        }
    }

    /**
     * Enable nameserver merging.
     *
     * @throws Exception
     *
     * @return void
     */
    private function _mergeDns()
    {
        $optDir = '/opt/valet-linux';
        $this->files->remove($optDir);
        $this->files->ensureDirExists($optDir);

        $this->sm->removeValetDns($this->files);

        if ($this->files->exists($this->rclocal)) {
            $this->files->restore($this->rclocal);
        }
    }

    /**
     * Install and configure DnsMasq.
     *
     * @param string $domain Domain TLD to use
     *
     * @throws Exception
     *
     * @return void
     */
    public function install($domain = 'test')
    {
        $this->dnsmasqSetup();
        $this->stopResolved();
        $this->createCustomConfigFile($domain);
        $this->sm->restart('dnsmasq');
    }

    /**
     * Stop the DnsMasq service.
     *
     * @return void
     */
    public function stop()
    {
        $this->sm->stop('dnsmasq');
    }

    /**
     * Restart the DnsMasq service.
     *
     * @return void
     */
    public function restart()
    {
        $this->sm->restart('dnsmasq');
    }

    /**
     * Append the custom DnsMasq configuration file to the main configuration file.
     *
     * @param string $domain Domain TLD to use
     *
     * @return void
     */
    public function createCustomConfigFile($domain)
    {
        $this->files->putAsUser(
            $this->configPath,
            'address=/.'.$domain.'/127.0.0.1'.PHP_EOL.'server=1.1.1.1'.PHP_EOL.'server=8.8.8.8'.PHP_EOL
        );
    }

    /**
     * Fix systemd-resolved configuration.
     *
     * @return void
     */
    public function stopResolved()
    {
        if (!$this->sm->disabled('systemd-resolved')) {
            $this->sm->disable('systemd-resolved');
        }
        $this->sm->stop('systemd-resolved');
    }

    /**
     * Setup dnsmasq with Network Manager.
     *
     * @throws Exception
     *
     * @return void
     */
    public function dnsmasqSetup()
    {
        $this->pm->ensureInstalled('dnsmasq');
        $this->sm->enable('dnsmasq');

        $this->files->ensureDirExists('/etc/NetworkManager/conf.d');
        $this->files->ensureDirExists('/etc/dnsmasq.d');

        $this->files->uncommentLine('IGNORE_RESOLVCONF', '/etc/default/dnsmasq');

        $this->_lockResolvConf(false);
        $this->_mergeDns();

        $this->files->unlink('/etc/dnsmasq.d/network-manager');
        $this->files->backup($this->dnsmasqconf);

        $this->files->putAsUser($this->dnsmasqconf, $this->files->get(__DIR__.'/../stubs/dnsmasq.conf'));
        $this->files->putAsUser($this->dnsmasqOpts, $this->files->get(__DIR__.'/../stubs/dnsmasq_options'));
        $this->files->putAsUser($this->nmConfigPath, $this->files->get(__DIR__.'/../stubs/networkmanager.conf'));
    }

    /**
     * Update the domain used by DnsMasq.
     *
     * @param string $newDomain New TLD
     *
     * @return void
     */
    public function updateDomain($newDomain)
    {
        $this->createCustomConfigFile($newDomain);
        $this->sm->restart('dnsmasq');
    }

    /**
     * Delete the DnsMasq config file.
     *
     * @return void
     */
    public function uninstall()
    {
        $this->sm->removeValetDns($this->files);

        $this->cli->passthru('rm -rf /opt/valet-linux');
        $this->files->unlink($this->configPath);
        $this->files->unlink($this->dnsmasqOpts);
        $this->files->unlink($this->nmConfigPath);
        $this->files->restore($this->resolvedConfigPath);

        $this->_lockResolvConf(false);
        $this->files->restore($this->rclocal);

        $this->cli->passthru('rm -f /etc/resolv.conf');
        $this->sm->stop('systemd-resolved');
        $this->sm->start('systemd-resolved');
        $this->files->symlink('/run/systemd/resolve/resolv.conf', $this->resolvconf);

        $this->files->restore($this->dnsmasqconf);
        $this->files->commentLine('IGNORE_RESOLVCONF', '/etc/default/dnsmasq');

        $this->pm->nmRestart($this->sm);
        $this->sm->restart('dnsmasq');

        info('Valet DNS changes have been rolled back');
    }
}
