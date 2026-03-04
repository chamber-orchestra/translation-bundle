<?php

declare(strict_types=1);

namespace Tests\Integrational;

use ChamberOrchestra\CmsBundle\Generator\CsvGeneratorInterface;
use ChamberOrchestra\DoctrineClockBundle\ChamberOrchestraDoctrineClockBundle;
use ChamberOrchestra\MetadataBundle\ChamberOrchestraMetadataBundle;
use ChamberOrchestra\PaginationBundle\ChamberOrchestraPaginationBundle;
use ChamberOrchestra\TranslationBundle\ChamberOrchestraTranslationBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new ChamberOrchestraMetadataBundle(),
            new ChamberOrchestraDoctrineClockBundle(),
            new ChamberOrchestraPaginationBundle(),
            new ChamberOrchestraTranslationBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test_secret',
            'test' => true,
            'default_locale' => 'en',
            'translator' => [
                'default_path' => '%kernel.project_dir%/tests/translations',
                'fallbacks' => ['en'],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'url' => '%env(DATABASE_URL)%',
            ],
            'orm' => [
                'entity_managers' => [
                    'default' => [
                        'mappings' => [
                            'Tests' => [
                                'type' => 'attribute',
                                'dir' => '%kernel.project_dir%/tests/Integrational/Entity',
                                'prefix' => 'Tests\\Integrational\\Entity',
                                'alias' => 'Tests',
                            ],
                            'TranslationBundle' => [
                                'type' => 'attribute',
                                'dir' => '%kernel.project_dir%/Entity',
                                'prefix' => 'ChamberOrchestra\\TranslationBundle\\Entity',
                                'alias' => 'TranslationBundle',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Expose EntityManagerInterface for direct use in tests.
        $container->services()
            ->alias(EntityManagerInterface::class, 'doctrine.orm.entity_manager')
            ->public();

        // Expose the console command (registered by the bundle with its arguments).
        $container->services()
            ->alias('test.translation.export_command', 'ChamberOrchestra\TranslationBundle\Command\ExportTranslationCommand')
            ->public();

        // Stub CsvGeneratorInterface so the CMS TranslationController can be compiled
        // without pulling in the full cms-bundle service stack.
        $container->services()
            ->set(CsvGeneratorInterface::class)
            ->synthetic();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }
}
