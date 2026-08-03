<?php

declare(strict_types=1);

/*
 * This file is part of the FOSOAuthServerBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\OAuthServerBundle\Tests\Storage;

use FOS\OAuthServerBundle\Model\AccessToken;
use FOS\OAuthServerBundle\Model\AccessTokenManagerInterface;
use FOS\OAuthServerBundle\Model\AuthCode;
use FOS\OAuthServerBundle\Model\AuthCodeManagerInterface;
use FOS\OAuthServerBundle\Model\Client;
use FOS\OAuthServerBundle\Model\ClientManagerInterface;
use FOS\OAuthServerBundle\Model\RefreshToken;
use FOS\OAuthServerBundle\Model\RefreshTokenManagerInterface;
use FOS\OAuthServerBundle\Storage\OAuthStorage;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

#[AllowMockObjectsWithoutExpectations]
class OAuthStorageTest extends TestCase
{
    protected ClientManagerInterface|MockObject $clientManager;
    protected AccessTokenManagerInterface|MockObject $accessTokenManager;
    protected RefreshTokenManagerInterface|MockObject $refreshTokenManager;
    protected AuthCodeManagerInterface|MockObject $authCodeManager;
    protected UserProviderInterface|MockObject $userProvider;
    protected PasswordHasherFactoryInterface|MockObject $passwordHasherFactory;
    protected OAuthStorage $storage;

    public function setUp(): void
    {
        $this->clientManager = $this->getMockBuilder(ClientManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->accessTokenManager = $this->getMockBuilder(AccessTokenManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->refreshTokenManager = $this->getMockBuilder(RefreshTokenManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->authCodeManager = $this->getMockBuilder(AuthCodeManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->userProvider = $this->getMockBuilder(UserProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->passwordHasherFactory = $this->getMockBuilder(PasswordHasherFactoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->storage = new OAuthStorage($this->clientManager, $this->accessTokenManager, $this->refreshTokenManager, $this->authCodeManager, $this->userProvider, $this->passwordHasherFactory);
    }

    public function testGetClientReturnsClientWithGivenId(): void
    {
        $client = new Client();

        $this->clientManager->expects($this->once())
            ->method('findClientByPublicId')
            ->with('123_abc')
            ->willReturn($client)
        ;

        $this->assertSame($client, $this->storage->getClient('123_abc'));
    }

    public function testGetClientReturnsNullIfNotExists(): void
    {
        $this->clientManager->expects($this->once())
            ->method('findClientByPublicId')
            ->with('123_abc')
            ->willReturn(null)
        ;

        $this->assertNull($this->storage->getClient('123_abc'));
    }

    public function testCheckClientCredentialsThrowsIfInvalidClientClass(): void
    {
        $client = $this->getMockBuilder('OAuth2\Model\IOAuth2Client')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->expectException('InvalidArgumentException');
        $this->storage->checkClientCredentials($client, 'dummy');
    }

    public function testCheckClientCredentialsReturnsTrueOnValidCredentials(): void
    {
        $client = new Client();
        $client->setSecret('dummy');

        $this->assertTrue($this->storage->checkClientCredentials($client, 'dummy'));
    }

    public function testCheckClientCredentialsReturnsFalseOnValidCredentials(): void
    {
        $client = new Client();
        $client->setSecret('dummy');

        $this->assertFalse($this->storage->checkClientCredentials($client, 'passe'));
    }

    public function testGetAccessTokenReturnsAccessTokenWithGivenId(): void
    {
        $token = new AccessToken();

        $this->accessTokenManager->expects($this->once())
            ->method('findTokenByToken')
            ->with('123_abc')
            ->willReturn($token)
        ;

        $this->assertSame($token, $this->storage->getAccessToken('123_abc'));
    }

    public function testGetAccessTokenReturnsNullIfNotExists(): void
    {
        $this->accessTokenManager->expects($this->once())
            ->method('findTokenByToken')
            ->with('123_abc')
            ->willReturn(null)
        ;

        $this->assertNull($this->storage->getAccessToken('123_abc'));
    }

    public function testCreateAccessTokenThrowsOnInvalidClientClass(): void
    {
        $client = $this->getMockBuilder('OAuth2\Model\IOAuth2Client')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->expectException('InvalidArgumentException');
        $this->storage->createAccessToken('foo', $client, new User('42'), 1, 'foo bar');
    }

    public function testCreateAccessToken(): void
    {
        $savedToken = null;

        $this->accessTokenManager->expects($this->once())
            ->method('createToken')
            ->with()
            ->willReturn(new AccessToken())
        ;
        $this->accessTokenManager->expects($this->once())
            ->method('updateToken')
            ->willReturnCallback(function ($token) use (&$savedToken) {
                $savedToken = $token;
            })
        ;

        $client = new Client();
        $user = new User('42');

        $token = $this->storage->createAccessToken('foo', $client, $user, 1, 'foo bar');

        $this->assertSame($savedToken, $token);

        $this->assertSame('foo', $token->getToken());
        $this->assertSame($client, $token->getClient());
        $this->assertSame($user, $token->getData());
        $this->assertSame($user, $token->getUser());
        $this->assertSame(1, $token->getExpiresAt());
        $this->assertSame('foo bar', $token->getScope());
    }

    public function testCreateAccessTokenWithoutUser(): void
    {
        $savedToken = null;

        $this->accessTokenManager->expects($this->once())
            ->method('createToken')
            ->with()
            ->willReturn(new AccessToken())
        ;
        $this->accessTokenManager->expects($this->once())
            ->method('updateToken')
            ->willReturnCallback(function ($token) use (&$savedToken) {
                $savedToken = $token;
            })
        ;

        $client = new Client();
        $user = null;

        $token = $this->storage->createAccessToken('foo', $client, $user, 1, 'foo bar');

        $this->assertSame($savedToken, $token);
    }

    public function testGetRefreshTokenReturnsRefreshTokenWithGivenId(): void
    {
        $token = new RefreshToken();

        $this->refreshTokenManager->expects($this->once())
            ->method('findTokenByToken')
            ->with('123_abc')
            ->willReturn($token)
        ;

        $this->assertSame($token, $this->storage->getRefreshToken('123_abc'));
    }

    public function testGetRefreshTokenReturnsNullIfNotExists(): void
    {
        $this->refreshTokenManager->expects($this->once())
            ->method('findTokenByToken')
            ->with('123_abc')
            ->willReturn(null)
        ;

        $this->assertNull($this->storage->getRefreshToken('123_abc'));
    }

    public function testCreateRefreshTokenThrowsOnInvalidClientClass(): void
    {
        $client = $this->getMockBuilder('OAuth2\Model\IOAuth2Client')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->expectException('InvalidArgumentException');
        $this->storage->createRefreshToken('foo', $client, 42, 1, 'foo bar');
    }

    public function testCreateRefreshToken(): void
    {
        $savedToken = null;

        $this->refreshTokenManager->expects($this->once())
            ->method('createToken')
            ->with()
            ->willReturn(new RefreshToken())
        ;
        $this->refreshTokenManager->expects($this->once())
            ->method('updateToken')
            ->willReturnCallback(function ($token) use (&$savedToken) {
                $savedToken = $token;
            })
        ;

        $client = new Client();
        $user = new User('42');

        $token = $this->storage->createRefreshToken('foo', $client, $user, 1, 'foo bar');

        $this->assertSame($savedToken, $token);

        $this->assertSame('foo', $token->getToken());
        $this->assertSame($client, $token->getClient());
        $this->assertSame($user, $token->getData());
        $this->assertSame($user, $token->getUser());
        $this->assertSame(1, $token->getExpiresAt());
        $this->assertSame('foo bar', $token->getScope());
    }

    public function testCreateRefreshTokenWithoutUser(): void
    {
        $savedToken = null;

        $this->refreshTokenManager->expects($this->once())
            ->method('createToken')
            ->with()
            ->willReturn(new RefreshToken())
        ;
        $this->refreshTokenManager->expects($this->once())
            ->method('updateToken')
            ->willReturnCallback(function ($token) use (&$savedToken) {
                $savedToken = $token;
            })
        ;

        $client = new Client();
        $user = null;

        $token = $this->storage->createRefreshToken('foo', $client, $user, 1, 'foo bar');

        $this->assertSame($savedToken, $token);
    }

    public function testCheckRestrictedGrantTypeThrowsOnInvalidClientClass(): void
    {
        $client = $this->getMockBuilder('OAuth2\Model\IOAuth2Client')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->expectException('InvalidArgumentException');

        $this->storage->checkRestrictedGrantType($client, 'foo');
    }

    public function testCheckRestrictedGrantType(): void
    {
        $client = new Client();
        $client->setAllowedGrantTypes(['foo', 'bar']);

        $this->assertTrue($this->storage->checkRestrictedGrantType($client, 'foo'));
        $this->assertTrue($this->storage->checkRestrictedGrantType($client, 'bar'));
        $this->assertFalse($this->storage->checkRestrictedGrantType($client, 'baz'));
    }

    public function testCheckUserCredentialsThrowsOnInvalidClientClass(): void
    {
        $client = $this->getMockBuilder('OAuth2\Model\IOAuth2Client')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->expectException('InvalidArgumentException');

        $this->storage->checkUserCredentials($client, 'Joe', 'baz');
    }

    public function testCheckUserCredentialsCatchesAuthenticationExceptions(): void
    {
        $client = new Client();

        $this->userProvider
            ->expects(self::once())
            ->method('loadUserByIdentifier')
            ->with('Joe')
            ->willThrowException(new AuthenticationException('No such user'))
        ;

        $result = $this->storage->checkUserCredentials($client, 'Joe', 'baz');

        $this->assertFalse($result);
    }

    public function testCheckUserCredentialsReturnsTrueOnValidCredentials(): void
    {
        $client = new Client();
        $user = new User('Joe');

        $hasher = $this->getMockBuilder(PasswordHasherInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $hasher->expects($this->once())
            ->method('verify')
            ->with('baz')
            ->willReturn(true)
        ;

        $this->userProvider->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with('Joe')
            ->willReturn($user)
        ;

        $this->passwordHasherFactory
            //->expects($this->any())
            ->method('getPasswordHasher')
            ->with($user)
            ->willReturn($hasher)
        ;

        $this->assertSame([
            'data' => $user,
        ], $this->storage->checkUserCredentials($client, 'Joe', 'baz'));
    }

    public function testCheckUserCredentialsReturnsFalseOnInvalidCredentials(): void
    {
        $client = new Client();
        $user = new User('Joe');
        ;

        $hasher = $this->getMockBuilder(PasswordHasherInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $hasher->expects($this->once())
            ->method('verify')
            ->with('baz')
            ->willReturn(false)
        ;

        $this->userProvider->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with('Joe')
            ->willReturn($user)
        ;

        $this->passwordHasherFactory->expects($this->once())
            ->method('getPasswordHasher')
            ->with($user)
            ->willReturn($hasher)
        ;

        $this->assertFalse($this->storage->checkUserCredentials($client, 'Joe', 'baz'));
    }

    public function testCheckUserCredentialsReturnsFalseIfUserNotExist(): void
    {
        $client = new Client();

        $this->userProvider->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with('Joe')
            ->willThrowException(new AuthenticationException('No such user'))
        ;

        $this->assertFalse($this->storage->checkUserCredentials($client, 'Joe', 'baz'));
    }

    public function testCreateAuthCodeThrowsOnInvalidClientClass(): void
    {
        $client = $this->getMockBuilder('OAuth2\Model\IOAuth2Client')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->expectException('InvalidArgumentException');
        $this->storage->createAuthCode('foo', $client, 42, 'http://www.example.com/', 1, 'foo bar');
    }

    public function testCreateAuthCode(): void
    {
        $savedCode = null;

        $this->authCodeManager->expects($this->once())
            ->method('createAuthCode')
            ->with()
            ->willReturn(new AuthCode())
        ;
        $this->authCodeManager->expects($this->once())
            ->method('updateAuthCode')
            ->willReturnCallback(function ($code) use (&$savedCode) {
                $savedCode = $code;
            })
        ;

        $client = new Client();
        $user = new User('42');

        $code = $this->storage->createAuthCode('foo', $client, $user, 'http://www.example.com/', 1, 'foo bar');

        $this->assertSame($savedCode, $code);

        $this->assertSame('foo', $code->getToken());
        $this->assertSame($client, $code->getClient());
        $this->assertSame($user, $code->getData());
        $this->assertSame($user, $code->getUser());
        $this->assertSame(1, $code->getExpiresAt());
        $this->assertSame('foo bar', $code->getScope());
        $this->assertNull($code->getNonce());
        $this->assertNull($code->getAuthTime());
    }

    public function testCreateAuthCodeBindsTheOidcNonce(): void
    {
        $this->authCodeManager->expects($this->once())
            ->method('createAuthCode')
            ->with()
            ->willReturn(new AuthCode())
        ;
        $this->authCodeManager->expects($this->once())
            ->method('updateAuthCode')
        ;

        $code = $this->storage->createAuthCode(
            'foo',
            new Client(),
            new User('42'),
            'http://www.example.com/',
            1,
            'openid profile',
            'n-0S6_WzA2Mj'
        );

        $this->assertSame('n-0S6_WzA2Mj', $code->getNonce());
    }

    public function testGetAuthCodeReturnsAuthCodeWithGivenId(): void
    {
        $code = new AuthCode();

        $this->authCodeManager->expects($this->once())
            ->method('findAuthCodeByToken')
            ->with('123_abc')
            ->willReturn($code)
        ;

        $this->assertSame($code, $this->storage->getAuthCode('123_abc'));
    }

    public function testGetAuthCodeReturnsNullIfNotExists(): void
    {
        $this->authCodeManager->expects($this->once())
            ->method('findAuthCodeByToken')
            ->with('123_abc')
            ->willReturn(null)
        ;

        $this->assertNull($this->storage->getAuthCode('123_abc'));
    }

    public function testValidGrantExtension(): void
    {
        $grantExtension = $this->getMockBuilder('FOS\OAuthServerBundle\Storage\GrantExtensionInterface')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $grantExtension
            ->expects($this->once())
            ->method('checkGrantExtension')
            ->willReturn(true)
        ;
        $this->storage->setGrantExtension('https://friendsofsymfony.com/grants/foo', $grantExtension);

        $client = $this->getMockBuilder('OAuth2\Model\IOAuth2Client')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->assertTrue($this->storage->checkGrantExtension($client, 'https://friendsofsymfony.com/grants/foo', [], []));
    }

    public function testValidGrantExtensionWithData(): void
    {
        $grantExtension = $this->getMockBuilder('FOS\OAuthServerBundle\Storage\GrantExtensionInterface')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $grantExtensionData = ['data' => 'Foo'];
        $grantExtension
            ->expects($this->once())
            ->method('checkGrantExtension')
            ->willReturn($grantExtensionData)
        ;
        $this->storage->setGrantExtension('https://friendsofsymfony.com/grants/foo', $grantExtension);

        $client = $this->getMockBuilder('OAuth2\Model\IOAuth2Client')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->assertEquals(
            $grantExtensionData,
            $this->storage->checkGrantExtension($client, 'https://friendsofsymfony.com/grants/foo', [], [])
        );
    }

    public function testInvalidGrantExtension(): void
    {
        $this->expectException(\OAuth2\OAuth2ServerException::class);

        $client = $this->getMockBuilder('OAuth2\Model\IOAuth2Client')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->storage->checkGrantExtension($client, 'https://friendsofsymfony.com/grants/bar', [], []);
    }

    public function testDoubleSetGrantExtension(): void
    {
        $grantExtension = $this->getMockBuilder('FOS\OAuthServerBundle\Storage\GrantExtensionInterface')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $grantExtension2 = $this->getMockBuilder('FOS\OAuthServerBundle\Storage\GrantExtensionInterface')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->storage->setGrantExtension($uri = 'https://friendsofsymfony.com/grants/foo', $grantExtension);
        $this->storage->setGrantExtension($uri, $grantExtension2);

        $storageClass = new \ReflectionClass(get_class($this->storage));
        $grantExtensionsProperty = $storageClass->getProperty('grantExtensions');
        $grantExtensions = $grantExtensionsProperty->getValue($this->storage);

        $this->assertSame($grantExtension2, $grantExtensions[$uri]);
    }

    public function testMarkAuthCodeAsUsedIfAuthCodeFound(): void
    {
        $authCode = $this->getMockBuilder('FOS\OAuthServerBundle\Model\AuthCodeInterface')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->authCodeManager->expects($this->atLeastOnce())
            ->method('findAuthCodeByToken')
            ->with('123_abc')
            ->willReturn($authCode)
        ;

        $this->authCodeManager->expects($this->atLeastOnce())
            ->method('deleteAuthCode')
            ->with($authCode)
            ->willReturn(null)
        ;

        $this->storage->markAuthCodeAsUsed('123_abc');
    }

    public function testMarkAuthCodeAsUsedIfAuthCodeNotFound(): void
    {
        $this->authCodeManager->expects($this->atLeastOnce())
            ->method('findAuthCodeByToken')
            ->with('123_abc')
            ->willReturn(null)
        ;

        $this->authCodeManager->expects($this->never())
            ->method('deleteAuthCode')
        ;

        $this->storage->markAuthCodeAsUsed('123_abc');
    }
}

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private string $username;

    public function __construct(string $username)
    {
        $this->username = $username;
    }

    public function getRoles(): array
    {
        return [];
    }

    public function getPassword(): string
    {
        return 'baz';
    }

    public function getSalt(): string
    {
        return '';
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function eraseCredentials(): void
    {
    }


}
