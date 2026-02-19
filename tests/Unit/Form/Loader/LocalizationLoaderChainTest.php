<?php

declare(strict_types=1);

namespace Tests\Unit\Form\Loader;

use ChamberOrchestra\TranslationBundle\Form\Loader\LocalizationLoaderChain;
use ChamberOrchestra\TranslationBundle\Form\Loader\LocalizationLoaderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LocalizationLoaderChainTest extends TestCase
{
    #[Test]
    public function returnsFirstNonNullValue(): void
    {
        $loaderA = $this->createMock(LocalizationLoaderInterface::class);
        $loaderA->method('load')->willReturn(null);

        $loaderB = $this->createMock(LocalizationLoaderInterface::class);
        $loaderB->method('load')->willReturn('translated value');

        $loaderC = $this->createMock(LocalizationLoaderInterface::class);
        $loaderC->expects(self::never())->method('load');

        $chain = new LocalizationLoaderChain([$loaderA, $loaderB, $loaderC]);

        self::assertSame('translated value', $chain->load('some.key'));
    }

    #[Test]
    public function fallsBackToKeyWhenAllLoadersReturnNull(): void
    {
        $loaderA = $this->createMock(LocalizationLoaderInterface::class);
        $loaderA->method('load')->willReturn(null);

        $loaderB = $this->createMock(LocalizationLoaderInterface::class);
        $loaderB->method('load')->willReturn(null);

        $chain = new LocalizationLoaderChain([$loaderA, $loaderB]);

        self::assertSame('some.key', $chain->load('some.key'));
    }

    #[Test]
    public function fallsBackToKeyWithNoLoaders(): void
    {
        $chain = new LocalizationLoaderChain([]);

        self::assertSame('fallback.key', $chain->load('fallback.key'));
    }

    #[Test]
    public function implementsLoaderInterface(): void
    {
        $chain = new LocalizationLoaderChain([]);

        self::assertInstanceOf(LocalizationLoaderInterface::class, $chain);
    }

    #[Test]
    public function firstLoaderTakesPriorityOverSecond(): void
    {
        $loaderA = $this->createMock(LocalizationLoaderInterface::class);
        $loaderA->method('load')->willReturn('from A');

        $loaderB = $this->createMock(LocalizationLoaderInterface::class);
        $loaderB->expects(self::never())->method('load');

        $chain = new LocalizationLoaderChain([$loaderA, $loaderB]);

        self::assertSame('from A', $chain->load('key'));
    }
}
