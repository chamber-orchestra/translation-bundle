<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\DependencyInjection;

use ChamberOrchestra\CmsBundle\Generator\CsvGeneratorInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ChamberOrchestraTranslationExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        if (\class_exists(CsvGeneratorInterface::class)) {
            $loader->load('services_cms.yaml');
        }
    }
}
