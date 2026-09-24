<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

/**
 * Validates the narrow FrontOffice bridge request contract before controller
 * data reaches the Scenario service. Values are deliberately not normalised.
 */
final class LegacyHandoffRequestValidator
{
    public static function issue(array $data): void
    {
        if (!isset($data['anrId'], $data['nonce'], $data['returnPath'])
            || array_diff(array_keys($data), ['anrId', 'nonce', 'returnPath']) !== []
            || !is_int($data['anrId']) || $data['anrId'] < 1
            || !is_string($data['nonce']) || strlen($data['nonce']) < 16 || strlen($data['nonce']) > 256
            || !is_string($data['returnPath'])
            || !preg_match('#^/(?!/)[^\\x00-\\x1f]{0,254}$#', $data['returnPath'])
            || $data['returnPath'] !== '/scenario/frontoffice'
        ) {
            throw new \InvalidArgumentException('Invalid Scenario handoff');
        }
    }

    public static function consume(array $data): void
    {
        if (!isset($data['code'], $data['nonce'], $data['audience'])
            || !is_string($data['code']) || strlen($data['code']) !== 64 || !ctype_xdigit($data['code'])
            || !is_string($data['nonce']) || strlen($data['nonce']) < 16 || strlen($data['nonce']) > 256
            || $data['audience'] !== 'scenario-ui') {
            throw new \InvalidArgumentException('Invalid Scenario handoff');
        }
    }

    public static function session(array $data): void
    {
        if (!isset($data['session'])
            || !is_string($data['session'])
            || strlen($data['session']) !== 64
            || !ctype_xdigit($data['session'])
        ) {
            throw new \InvalidArgumentException('Invalid Scenario handoff');
        }
    }
}
