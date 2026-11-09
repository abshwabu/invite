<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class DesignRendererService
{
    /**
     * Render a design using Node and return its SHA-256 hash and metadata.
     */
    public static function render(array $design, array $options = []): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'design_v2_');
        file_put_contents($tempFile, json_encode($design));

        $cmd = ['node', base_path('scripts/render_cli.js'), "--design={$tempFile}"];

        if (isset($options['palette'])) {
            $cmd[] = '--palette=' . json_encode($options['palette']);
        }
        if (isset($options['scale'])) {
            $cmd[] = '--scale=' . (float) $options['scale'];
        }
        if (isset($options['lang'])) {
            $cmd[] = '--lang=' . $options['lang'];
        }
        if (isset($options['out'])) {
            $cmd[] = '--out=' . $options['out'];
        }

        $process = new Process($cmd);
        $process->setTimeout(60);
        $process->run();

        @unlink($tempFile);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Failed to render design in Node: ' . $process->getErrorOutput());
        }

        $output = json_decode($process->getOutput(), true);

        return $output ?? ['hash' => trim($process->getOutput())];
    }

    /**
     * Get SHA256 hash of rendered design.
     */
    public static function getHash(array $design, array $options = []): string
    {
        $res = self::render($design, $options);

        return $res['hash'] ?? '';
    }
}
