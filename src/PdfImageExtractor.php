<?php

namespace AlexGrau\PdfImageExtractor;

use AlexGrau\PdfImageExtractor\Exceptions\CouldNotExtractImages;
use AlexGrau\PdfImageExtractor\Exceptions\PdfNotFound;
use Symfony\Component\Process\Process;

class PdfImageExtractor
{

    protected $pdf;

    protected $binPath;

    protected $options = [];


    public function __construct(string $binPath = null)
    {
        $this->binPath = $binPath ?? '/usr/bin/pdfimages';
    }


    public static function getImages(string $pdf, string $binPath = null, array $options = []): array
    {
        return (new static($binPath))->setOptions($options)
                                     ->setPdf($pdf)
                                     ->images();
    }


    public function images(): array
    {
        $path = '/tmp/img-' . rand(1000, 9999) . time();
        mkdir($path);
        $command = array_merge([$this->binPath], $this->options, [$this->pdf], ["$path/image"]);
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new CouldNotExtractImages($process);
        }

        // Read the images, delete them from the HD and return an array with the extension and content.
        $files = array_values(array_diff(scandir($path), ['.', '..']));

        foreach ($files as $idx => $file) {
            $filepath    = "$path/$file";
            $ext         = pathinfo($filepath, PATHINFO_EXTENSION);
            $files[$idx] = [
                'ext'     => $ext,
                'content' => file_get_contents($filepath)
            ];
            unlink($filepath);
        }
        rmdir($path);

        return $files;
    }


    /**
     * @param string $pdf
     *
     * @return PdfImageExtractor
     * @throws PdfNotFound
     */
    public function setPdf(string $pdf): self
    {
        if (!is_readable($pdf)) {
            throw new PdfNotFound(sprintf('could not find or read pdf `%s`', $pdf));
        }

        $this->pdf = $pdf;

        return $this;
    }


    public function setOptions(array $options): self
    {
        $mapper = function (string $content): array {
            $content = trim($content);
            if ('-' !== ($content[0] ?? '')) {
                $content = '-' . $content;
            }

            return explode(' ', $content, 2);
        };

        $reducer = function (array $carry, array $option): array {
            return array_merge($carry, $option);
        };

        $this->options = array_reduce(array_map($mapper, $options), $reducer, []);

        return $this;
    }
}
