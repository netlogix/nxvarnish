<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Service;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class VarnishService implements LoggerAwareInterface, SingletonInterface
{
    use LoggerAwareTrait;

    protected $varnishHost = '';

    public function __construct()
    {
        $this->varnishHost = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get(
            'nxvarnish',
            'varnishHost'
        );
    }

    /**
     * Ban all documents with the given tag from varnish
     */
    public function banTag(string $tag): void
    {
        try {
            $response = GeneralUtility::makeInstance(RequestFactory::class)
                ->request($this->varnishHost, 'BAN', ['headers' => ['X-Cache-Tags' => $tag]]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error(
                    'unexpected status after purging Varnish cache',
                    ['tag' => $tag, 'status' => $response->getStatusCode()]
                );
            }
        } catch (GuzzleException $e) {
            $this->logger->error('failed purging Varnish cache', ['exception' => $e, 'tag' => $tag]);
        }
    }

}
