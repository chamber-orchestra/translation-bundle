<?php

declare(strict_types=1);

namespace ChamberOrchestra\TranslationBundle\Provider;

use ChamberOrchestra\TranslationBundle\Contracts\Provider\LocaleProviderInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LocaleProvider implements LocaleProviderInterface
{
    private ?TranslatorInterface $translator;
    private ParameterBagInterface $parameterBag;
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack, ParameterBagInterface $parameterBag, ?TranslatorInterface $translator)
    {
        $this->requestStack = $requestStack;
        $this->translator = $translator;
        $this->parameterBag = $parameterBag;
    }

    public function provideCurrentLocale(): ?string
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if (null === $currentRequest) {
            return null;
        }

        $currentLocale = $currentRequest->getLocale();
        if ($currentLocale) {
            return $currentLocale;
        }

        if ($this->translator) {
            return $this->translator->getLocale();
        }

        return null;
    }

    public function provideFallbackLocale(): ?string
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if (null !== $currentRequest) {
            return $currentRequest->getDefaultLocale();
        }

        try {
            return $this->parameterBag->get('kernel.default_locale');
        } catch (ParameterNotFoundException $parameterNotFoundException) {
            return null;
        }
    }
}
