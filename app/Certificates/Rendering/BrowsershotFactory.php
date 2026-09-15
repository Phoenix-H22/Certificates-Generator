<?php

namespace App\Certificates\Rendering;

use Spatie\Browsershot\Browsershot;

/** Builds a Browsershot instance from config/certificates.php. */
final class BrowsershotFactory
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('certificates.browsershot', []));
    }

    public function make(string $html): Browsershot
    {
        $shot = Browsershot::html($html)
            ->newHeadless()
            ->timeout((int) ($this->config['timeout'] ?? 90))
            ->showBackground()
            ->setOption('args', [
                '--disable-dev-shm-usage',
                '--font-render-hinting=none',
                '--disable-gpu',
            ])
            ->setEnvironmentOptions(['LANG' => 'ar_EG.UTF-8']);

        if (! empty($this->config['node_binary'])) {
            $shot->setNodeBinary((string) $this->config['node_binary']);
        }

        if (! empty($this->config['npm_binary'])) {
            $shot->setNpmBinary((string) $this->config['npm_binary']);
        }

        if (! empty($this->config['chrome_path'])) {
            $shot->setChromePath((string) $this->config['chrome_path']);
        }

        $shot->setNodeModulePath((string) ($this->config['node_modules_path'] ?: base_path('node_modules')));

        if (! empty($this->config['no_sandbox'])) {
            $shot->noSandbox();
        }

        if (! empty($this->config['remote_ws'])) {
            $shot->setWSEndpoint((string) $this->config['remote_ws']);
        }

        return $shot;
    }
}
