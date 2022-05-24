<?php
declare(strict_types=1);

namespace Netlogix\Nxvarnish\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CurlVarnishService implements SingletonInterface
{

    protected $varnishHost = '';

    /**
     * Create an instance of the CURLHTTP cachemanager. IT takes one parameter, the HTTP address
     * (including http://) that the Varnish server is running on. If this parameter is specified
     * This one is used, otherwise, the host of the URL that needs to cleared is used.
     */
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
        $options = [];
        $options[CURLOPT_HTTPHEADER][] = 'X-Cache-Tags: ' . $tag;

        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $this->varnishHost);
        curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'BAN');
        curl_setopt($curlHandle, CURLOPT_HEADER, 0);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt_array($curlHandle, $options);
        curl_exec($curlHandle);
        curl_close($curlHandle);
    }

}
