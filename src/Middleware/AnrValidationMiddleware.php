<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use Laminas\Router\RouteMatch;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\UserAnrTable;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AnrValidationMiddleware implements MiddlewareInterface
{
    private AnrTable $anrTable;
    private UserAnrTable $userAnrTable;
    private ConnectedUserService $connectedUserService;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(
        AnrTable $anrTable,
        UserAnrTable $userAnrTable,
        ConnectedUserService $connectedUserService,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->anrTable = $anrTable;
        $this->userAnrTable = $userAnrTable;
        $this->connectedUserService = $connectedUserService;
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var RouteMatch $routeMatch */
        $routeMatch = $request->getAttribute(RouteMatch::class);
        $anrId = $routeMatch->getParam('anrid');
        $anr = $this->anrTable->findById($anrId, false);
        if ($anr === null) {
            return $this->responseFactory->createResponse(
                StatusCodeInterface::STATUS_NOT_FOUND,
                sprintf('Analysis with ID %s not found.', $anrId)
            );
        }

        $userAnr = $this->userAnrTable->findByAnrAndUser($anr, $this->connectedUserService->getConnectedUser());
        if ($userAnr === null) {
            return $this->responseFactory->createResponse(
                StatusCodeInterface::STATUS_FORBIDDEN,
                sprintf('Analysis with ID %s is not accessible for view.', $anrId)
            );
        }
        if (!$userAnr->hasWriteAccess() && \in_array($request->getMethod(), ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return $this->responseFactory->createResponse(
                StatusCodeInterface::STATUS_FORBIDDEN,
                sprintf('Analysis with ID %s is not accessible for any modifications.', $anrId)
            );
        }

        $request = $request->withAttribute('anr', $anr);

        return $handler->handle($request);
    }
}
