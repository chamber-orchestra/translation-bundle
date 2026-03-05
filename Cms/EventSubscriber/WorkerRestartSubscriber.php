<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Cms\EventSubscriber;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
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

        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $app->run(new ArrayInput(['command' => 'cache:warmup']), new NullOutput());

        \shell_exec($this->workerRestartCommand.' 2>/dev/null &');
    }
}
