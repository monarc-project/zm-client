<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Middleware;

use Doctrine\ORM\EntityNotFoundException;
use Fig\Http\Message\StatusCodeInterface;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Router\RouteMatch;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AnrValidationMiddleware implements MiddlewareInterface
{
    private AnrTable $anrTable;

    private UserAnrTable $userAnrTable;

    private UserSuperClass $connectedUser;

    private ResponseFactory $responseFactory;

    public function __construct(
        AnrTable $anrTable,
        UserAnrTable $userAnrTable,
        ConnectedUserService $connectedUserService,
        ResponseFactory $responseFactory
    ) {
        $this->anrTable = $anrTable;
        $this->userAnrTable = $userAnrTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var RouteMatch $routeMatch */
        $routeMatch = $request->getAttribute(RouteMatch::class);
        $anrId = (int)$routeMatch->getParam('anrid');
        if ($anrId === 0) {
            $anrId = (int)($request->getParsedBody()['anr'] ?? 0);
        }

        try {
            /** @var Anr $anr */
            $anr = $this->anrTable->findById($anrId);
        } catch (EntityNotFoundException $e) {
            return $this->responseFactory->createResponse(
                StatusCodeInterface::STATUS_NOT_FOUND,
                sprintf('Analysis with ID %s not found.', $anrId)
            );
        }

        /* Ensure the record in the anr is presented in the table, means at least read permissions are allowed.
         * It's necessary e.g. for the "monarc_api_duplicate_client_anr" route. */
        $userAnr = $this->userAnrTable->findByAnrAndUser($anr, $this->connectedUser);
        if ($userAnr === null) {
            return $this->responseFactory->createResponse(
                StatusCodeInterface::STATUS_FORBIDDEN,
                sprintf('Analysis with ID %s is not accessible for view.', $anrId)
            );
        }
        /* The validation is already performed in Module.php
         * A batch of routes (aliases) have to be excluded in the condition to bypass forbidden response.
        if (!$userAnr->hasWriteAccess() && \in_array($request->getMethod(), ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return $this->responseFactory->createResponse(
                StatusCodeInterface::STATUS_FORBIDDEN,
                sprintf('Analysis with ID %s is not accessible for any modifications.', $anrId)
            );
        }
        */

        $request = $request->withAttribute('anr', $anr);

        return $handler->handle($request);
    }
}
