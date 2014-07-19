<?php

/**
 * This file is part of Bldr.io
 *
 * (c) Aaron Scherer <aequasi@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE
 */

namespace Bldr\Composer\BldrPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\PackageEvent;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 */
class BldrPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var  $composer
     */
    private $composer;

    /**
     * @var  $io
     */
    private $io;

    /**
     * @var  $blockLoader
     */
    private $blockLoader;

    /**
     * @var Filesystem $filesystem
     */
    private $filesystem;

    /**
     * Initializes the filesystem
     */
    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io       = $io;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::PRE_PACKAGE_INSTALL    => [
                'onPrePackageInstall',
            ],
            ScriptEvents::PRE_PACKAGE_UPDATE     => [
                'onPrePackageUpdate',
            ],
            ScriptEvents::POST_PACKAGE_INSTALL   => [
                'onPostPackageInstall',
            ],
            ScriptEvents::POST_PACKAGE_UPDATE    => [
                'onPostPackageUpdate',
            ],
            ScriptEvents::POST_PACKAGE_UNINSTALL => [
                'onPostPackageUninstall',
            ],
        );
    }

    /**
     * @param PackageEvent $packageEvent
     */
    public function onPrePackageInstall(PackageEvent $packageEvent)
    {
        $package = $packageEvent->getOperation()->getPackage();
        $this->removeBlockFromLoader($package);
    }

    /**
     * @param PackageEvent $packageEvent
     */
    public function onPrePackageUpdate(PackageEvent $packageEvent)
    {
        $package = $packageEvent->getOperation()->getInitialPackage();
        $this->removeBlockFromLoader($package);
    }

    /**
     * @param PackageEvent $packageEvent
     */
    public function onPostPackageInstall(PackageEvent $packageEvent)
    {
        $package = $packageEvent->getOperation()->getPackage();
        $this->addBlockToLoader($package);
    }

    /**
     * @param PackageEvent $packageEvent
     */
    public function onPostPackageUpdate(PackageEvent $packageEvent)
    {
        $package = $packageEvent->getOperation()->getTargetPackage();
        $this->addBlockToLoader($package);
    }

    /**
     * @param PackageEvent $packageEvent
     */
    public function onPostPackageUninstall(PackageEvent $packageEvent)
    {
        $package = $packageEvent->getOperation()->getPackage();
        $this->removeBlockFromLoader($package);
    }

    /**
     * @return string
     */
    private function getBlockLoader()
    {
        if (null !== $this->blockLoader) {
            return $this->blockLoader;
        }

        $config = $this->composer->getConfig();

        $this->blockLoader = $config->has('block-loader') ? $config->get('block-loader') : '.bldr/blocks.yml';

        if (!file_exists($this->blockLoader)) {
            touch($this->blockLoader);
        }

        return $this->blockLoader;
    }

    /**
     * @param PackageInterface $package
     *
     * @return string|null
     */
    private function getBlockClass(PackageInterface $package)
    {
        $extra = $package->getExtra();

        return isset($extra['block-class']) ? $extra['block-class'] : null;
    }

    private function getLoaderContents()
    {
        $loader = Yaml::parse(file_get_contents($this->getBlockLoader()));
        if (null === $loader) {
            $loader = [];
        }

        return $loader;
    }

    /**
     * @param PackageInterface $package
     *
     * @return bool|int
     */
    private function addBlockToLoader(PackageInterface $package)
    {
        $class = $this->getBlockClass($package);
        if (null === $class) {
            return false;
        }

        $loader = $this->getLoaderContents();

        if (in_array($class, $loader)) {
            return false;
        }

        $loader[] = $class;

        return file_put_contents($this->getBlockLoader(), Yaml::dump($loader, 4));
    }

    /**
     * @param PackageInterface $package
     *
     * @return bool|int
     */
    private function removeBlockFromLoader(PackageInterface $package)
    {
        $class = $this->getBlockClass($package);
        if (null === $class) {
            return false;
        }

        $loader = $this->getLoaderContents();

        if (!in_array($class, $loader)) {
            return false;
        }
        unset($loader[array_search($class, $loader)]);

        return file_put_contents($this->getBlockLoader(), Yaml::dump($loader, 4));
    }
}
