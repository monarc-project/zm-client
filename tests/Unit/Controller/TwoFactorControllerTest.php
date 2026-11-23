<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit\Controller;

use Laminas\View\Model\JsonModel;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Table\UserTable;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

/** @coversNothing */
class TwoFactorControllerTest extends TestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function testOfficeProfileSetupReturnsPngWithoutSavingUser(): void
    {
        $user = $this->officeUser();
        $user->method('getId')->willReturn(42);
        $table = $this->createMock(UserTable::class);
        $table->expects(self::never())->method('save');
        $result = $this->responseData($this->officeController($user, $table)->get(42));

        self::assertSame(42, $result['id']);
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $result['secret']);
        $this->assertPng($result['qrcode']);
    }

    /** @dataProvider validAndInvalidCodes */
    public function testOfficeActivationOnlyPersistsVerifiedSecret(bool $valid): void
    {
        $user = $this->officeUser();
        $user->expects($valid ? self::once() : self::never())->method('setSecretKey')->with(self::SECRET);
        $user->expects($valid ? self::once() : self::never())->method('setTwoFactorAuthEnabled')->with(true);
        $table = $this->createMock(UserTable::class);
        $table->expects($valid ? self::once() : self::never())->method('save')->with($user);
        $controller = $this->officeController($user, $table);
        $code = $valid ? (new TwoFactorAuth(new EndroidQrCodeProvider(), 'MONARC'))->getCode(self::SECRET) : '00000';

        $result = $this->responseData($controller->create(['secretKey' => self::SECRET, 'verificationCode' => $code]));

        self::assertSame($valid, $result['status']);
    }

    public static function validAndInvalidCodes(): array
    {
        return [[true], [false]];
    }

    private function officeUser(): UserSuperClass
    {
        return $this->createMock(\Monarc\FrontOffice\Entity\User::class);
    }

    private function officeController(UserSuperClass $user, UserTable $table): object
    {
        $identity = $this->createMock(ConnectedUserService::class);
        $identity->method('getConnectedUser')->willReturn($user);
        $config = $this->createMock(ConfigService::class);

        return new \Monarc\FrontOffice\Controller\ApiUserTwoFAController($config, $table, $identity);
    }

    private function responseData(ResponseInterface|JsonModel $response): array
    {
        return $response instanceof JsonModel ? $response->getVariables()
            : json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function assertPng(string $uri): void
    {
        self::assertStringStartsWith('data:image/png;base64,', $uri);
        $image = base64_decode(substr($uri, strlen('data:image/png;base64,')), true);
        self::assertSame("\x89PNG\r\n\x1a\n", substr($image, 0, 8));
        $size = getimagesizefromstring($image);
        self::assertSame([200, 200], [$size[0], $size[1]]);
    }
}
