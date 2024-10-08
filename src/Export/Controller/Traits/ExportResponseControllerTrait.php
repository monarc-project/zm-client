<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Controller\Traits;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use function strlen;

trait ExportResponseControllerTrait
{
    private function prepareJsonExportResponse(string $filename, string $output, bool $isEncrypted): ResponseInterface
    {
        $contentType = 'application/json; charset=utf-8';
        $extension = '.json';
        if ($isEncrypted) {
            $contentType = 'text/plain; charset=utf-8';
            $extension = '.bin';
        }
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $output);
        rewind($stream);

        return new Response($stream, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => strlen($output),
            'Content-Disposition' => 'attachment; filename="' . $filename . $extension . '"',
        ]);
    }

    private function prepareCsvExportResponse(string $output): ResponseInterface
    {
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $output);
        rewind($stream);

        return new Response($stream, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Length' => strlen($output),
        ]);
    }

    private function prepareWordExportResponse(string $filename, string $output): ResponseInterface
    {
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $output);
        rewind($stream);

        return new Response($stream, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Length' => strlen($output),
            'Content-Disposition' => 'attachment; filename="' . utf8_decode($filename) . '"',
        ]);
    }
}
