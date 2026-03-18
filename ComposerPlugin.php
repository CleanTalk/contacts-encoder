<?php

namespace Cleantalk\Common\ContactsEncoder;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Composer Plugin to display a thank you message after installation.
 */
class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * @var bool Flag to prevent showing message multiple times
     */
    private static $messageShown = false;

    /**
     * Package name to check
     */
    const PACKAGE_NAME = 'cleantalk/contacts-encoder';

    /**
     * GitHub repository URL
     */
    const GITHUB_URL = 'https://github.com/CleanTalk/contacts-encoder';

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'onPackageInstall',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPackageUpdate',
            ScriptEvents::POST_INSTALL_CMD => 'showThankYouMessage',
            ScriptEvents::POST_UPDATE_CMD => 'showThankYouMessage',
        ];
    }

    /**
     * Handle package install event.
     *
     * @param PackageEvent $event
     * @return void
     */
    public function onPackageInstall(PackageEvent $event)
    {
        $package = $event->getOperation()->getPackage();
        if ($package->getName() === self::PACKAGE_NAME) {
            $this->displayMessage($event->getIO());
        }
    }

    /**
     * Handle package update event.
     *
     * @param PackageEvent $event
     * @return void
     */
    public function onPackageUpdate(PackageEvent $event)
    {
        $package = $event->getOperation()->getTargetPackage();
        if ($package->getName() === self::PACKAGE_NAME) {
            $this->displayMessage($event->getIO());
        }
    }

    /**
     * Display the thank you message on script events (for root package).
     *
     * @param Event $event
     * @return void
     */
    public function showThankYouMessage(Event $event)
    {
        $this->displayMessage($event->getIO());
    }

    /**
     * Display the thank you message.
     *
     * @param IOInterface $io
     * @return void
     */
    private function displayMessage(IOInterface $io)
    {
        if (self::$messageShown) {
            return;
        }

        self::$messageShown = true;

        $io->write('');
        $io->write('<info>Thank you for installing CleanTalk Contacts Encoder SDK!</info>');
        $io->write('<comment>★ Star us on GitHub:</comment> ' . self::GITHUB_URL);
        $io->write('');
    }
}
