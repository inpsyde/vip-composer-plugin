<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Utils;

use Composer\Composer;
use Composer\Util\HttpDownloader;
use Inpsyde\VipComposer\Io;

class HttpClient
{
    private HttpDownloader $client;

    /**
     * @param Io $io
     * @param Composer $composer
     */
    public function __construct(private Io $io, Composer $composer)
    {
        $this->client = \Composer\Factory::createHttpDownloader(
            $io->composerIo(),
            $composer->getConfig()
        );
    }

    /**
     * @param non-empty-string $url
     * @param array $options
     * @param string|null $authorization
     * @return string
     */
    public function get(string $url, array $options = [], ?string $authorization = null): string
    {
        try {
            if (($authorization !== null) && ($authorization !== '')) {
                isset($options['http']) or $options['http'] = [];
                /** @psalm-suppress MixedArrayAssignment */
                isset($options['http']['header']) or $options['http']['header'] = [];
                /** @psalm-suppress MixedArrayAssignment */
                $options['http']['header'][] = "Authorization: {$authorization}";
            }

            $result = null;
            $response = $this->client->get($url, $options);
            $statusCode = $response->getStatusCode();
            if (($statusCode > 199) && ($statusCode < 300)) {
                $result = $response->getBody();
            }

            if (($result === null) || ($result === '')) {
                throw new \Exception("Could not obtain a response from '{$url}'.");
            }

            return $result;
        } catch (\Throwable $throwable) {
            $this->io->verboseError('  ' . $throwable->getMessage());

            return '';
        }
    }
}
