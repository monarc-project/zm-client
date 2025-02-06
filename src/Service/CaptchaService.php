<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2025 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Laminas\Captcha\Image as CaptchaImage;
use Laminas\Session\Container;
use Monarc\Core\Entity\ActionHistorySuperClass;
use Monarc\Core\Service\ConfigService;

final class CaptchaService
{
    private const DEFAULT_FAILED_LOGIN_ATTEMPTS = 3;

    private array $params;

    public function __construct(private ActionHistoryService $actionHistoryService, ConfigService $config)
    {
        $this->params = $config->getCaptchaConfig();
    }

    public function isActivated(): bool
    {
        $isEnabled = (bool)($this->params['enabled'] ?? false);
        if (!$isEnabled) {
            return false;
        }

        $failedLoginAttemptsLimit = (int)($this->params['failedLoginAttempts'] ?? self::DEFAULT_FAILED_LOGIN_ATTEMPTS);
        if ($failedLoginAttemptsLimit > 0) {
            /* Login attempt number validation from the logs. */
            $lastLoginsHistory = $this->actionHistoryService->getActionsHistoryByAction(
                ActionHistorySuperClass::ACTION_LOGIN_ATTEMPT,
                $failedLoginAttemptsLimit
            );
            if (\count($lastLoginsHistory) < $failedLoginAttemptsLimit) {
                return false;
            }
            foreach ($lastLoginsHistory as $lastLoginHistory) {
                if ($lastLoginHistory->getStatus() === ActionHistorySuperClass::STATUS_SUCCESS) {
                    return false;
                }
            }
        }

        return true;
    }

    public function generate(): array
    {
        $captcha = new CaptchaImage($this->params['params']);

        $captchaId = $captcha->generate();

        /* Store the captcha ID for validation. */
        $session = new Container('captcha');
        $session->offsetSet('captchaId', $captchaId);

        return [
            'captchaId' => $captchaId,
            'captchaUrl' => $captcha->getImgUrl() . $captcha->getId() . $captcha->getSuffix(),
        ];
    }

    public function isValid(string $captchaId, string $inputText): bool
    {
        $captcha = new CaptchaImage($this->params['params']);

        $session = new Container('captcha');
        $storedCaptchaId = $session->offsetGet('captchaId');
        if ($captchaId !== $storedCaptchaId) {
            return false;
        }

        return $captcha->isValid(['input' => $inputText, 'id' => $storedCaptchaId]);
    }
}
