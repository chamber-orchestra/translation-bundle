<?php

declare(strict_types=1);

namespace Tests\Unit\Provider;

use ChamberOrchestra\TranslationBundle\Provider\LocaleProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LocaleProviderTest extends TestCase
{
    private RequestStack $requestStack;
    private ParameterBagInterface&MockObject $parameterBag;
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    /**
     * @param TranslatorInterface|'default'|null $translator pass 'default' to use the test mock, null for no translator
     */
    private function makeProvider(mixed $translator = 'default'): LocaleProvider
    {
        return new LocaleProvider(
            $this->requestStack,
            $this->parameterBag,
            'default' === $translator ? $this->translator : $translator,
        );
    }

    // ─── provideCurrentLocale ────────────────────────────────────────────────

    #[Test]
    public function provideCurrentLocaleReturnsNullWithNoRequest(): void
    {
        self::assertNull($this->makeProvider()->provideCurrentLocale());
    }

    #[Test]
    public function provideCurrentLocaleReturnsRequestLocale(): void
    {
        $request = Request::create('/');
        $request->setLocale('fr');
        $this->requestStack->push($request);

        self::assertSame('fr', $this->makeProvider()->provideCurrentLocale());
    }

    #[Test]
    public function provideCurrentLocaleFallsBackToTranslatorLocale(): void
    {
        // Push a request whose getLocale() returns the default (which is empty string after stripping).
        // A freshly created Request has locale 'en' by default — simulate no explicit locale by
        // directly testing the translator path: no request at all, so provideCurrentLocale() returns null.
        // The translator path fires only when a request exists but its locale is falsy.
        // We simulate that by mocking getLocale() to return ''.
        $request = $this->createMock(Request::class);
        $request->method('getLocale')->willReturn('');
        $this->requestStack->push($request);

        $this->translator->method('getLocale')->willReturn('de');

        self::assertSame('de', $this->makeProvider()->provideCurrentLocale());
    }

    #[Test]
    public function provideCurrentLocaleReturnsNullWhenRequestHasNoLocaleAndNoTranslator(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getLocale')->willReturn('');
        $this->requestStack->push($request);

        self::assertNull($this->makeProvider(null)->provideCurrentLocale());
    }

    // ─── provideFallbackLocale ───────────────────────────────────────────────

    #[Test]
    public function provideFallbackLocaleReturnsRequestDefaultLocale(): void
    {
        $request = Request::create('/');
        // Request default locale is 'en' by default.
        $this->requestStack->push($request);

        self::assertSame('en', $this->makeProvider()->provideFallbackLocale());
    }

    #[Test]
    public function provideFallbackLocaleReturnsKernelDefaultLocaleWhenNoRequest(): void
    {
        $this->parameterBag->method('get')->with('kernel.default_locale')->willReturn('ru');

        self::assertSame('ru', $this->makeProvider()->provideFallbackLocale());
    }

    #[Test]
    public function provideFallbackLocaleReturnsNullWhenParameterMissing(): void
    {
        $this->parameterBag
            ->method('get')
            ->willThrowException(new ParameterNotFoundException('kernel.default_locale'));

        self::assertNull($this->makeProvider()->provideFallbackLocale());
    }
}
