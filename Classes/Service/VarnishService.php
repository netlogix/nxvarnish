<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Service;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\SingletonInterface;

readonly class VarnishService implements SingletonInterface
{
    public function __construct(
        protected RequestFactory $requestFactory,
        protected LoggerInterface $logger,
        #[
            Autowire(
                expression: 'service("extension-configuration").get("nxvarnish", "varnishHost")'
            )
        ]
        protected string $varnishHost
    ) {
    }

    /**
     * Ban all documents with the given tag from varnish
     */
    public function banTag(string $tag): void
    {
        try {
            $response = $this->requestFactory->request($this->varnishHost, 'BAN', [
                'headers' => ['X-Cache-Tags' => $tag],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('unexpected status after purging Varnish cache', [
                    'tag' => $tag,
                    'status' => $response->getStatusCode(),
                ]);
            }
        } catch (GuzzleException $guzzleException) {
            $this->logger->error('failed purging Varnish cache', ['exception' => $guzzleException, 'tag' => $tag]);
        }
    }
}
