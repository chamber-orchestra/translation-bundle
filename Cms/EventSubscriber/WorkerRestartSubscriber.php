<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

class WorkerRestartSubscriber implements EventSubscriberInterface
{
    private bool $scheduled = false;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly string $workerRestartCommand = '',
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'onTerminate'];
    }

    public function isConfigured(): bool
    {
        return '' !== $this->workerRestartCommand;
    }

    public function schedule(): void
    {
        $this->scheduled = true;
    }

    public function onTerminate(): void
    {
        if (!$this->scheduled || '' === $this->workerRestartCommand) {
            return;
        }

        $this->scheduled = false;

        $projectDir = $this->kernel->getProjectDir();
        $uid = \function_exists('posix_getuid') ? \posix_getuid() : 1000;
        $xdgRuntime = \getenv('XDG_RUNTIME_DIR') ?: "/run/user/{$uid}";
        $console = "{$projectDir}/bin/console";
        $logFile = "{$projectDir}/var/log/translation_restart.log";

        $cmd = "XDG_RUNTIME_DIR={$xdgRuntime} {$console} cache:warmup 2>&1 && XDG_RUNTIME_DIR={$xdgRuntime} {$this->workerRestartCommand} 2>&1";
        $output = \shell_exec($cmd);

        \file_put_contents($logFile, \date('Y-m-d H:i:s')." uid={$uid} xdg={$xdgRuntime}\ncmd: {$cmd}\noutput: {$output}\n---\n", \FILE_APPEND);
    }
}
