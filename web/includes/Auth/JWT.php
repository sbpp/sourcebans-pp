<?php

namespace Sbpp\Auth;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\InvalidKeyProvided;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;

final class JWT
{
    public static function create(string $jti, int $maxLife, int $aid): Token\Plain
    {
        $config = static::getConfig();
        $date = new DateTimeImmutable();

        return $config->builder()
            ->identifiedBy($jti)
            ->issuedBy(Host::domain())
            ->permittedFor(Host::domain())
            ->canOnlyBeUsedAfter($date)
            ->issuedAt($date)
            ->expiresAt($date->setTimestamp($date->getTimestamp() + $maxLife))
            ->withClaim('aid', $aid)
            ->getToken($config->signer(), $config->signingKey());
    }

    public static function parse(string $jwt): Token
    {
        $config = self::getConfig();

        return $config->parser()->parse($jwt);
    }

    public static function validate(Token $token): bool
    {
        $config = self::getConfig();

        $constrains = [
            new SignedWith($config->signer(), $config->signingKey()),
            new IssuedBy(Host::domain()),
            new PermittedFor(Host::domain()),
            new IdentifiedBy($token->claims()->get('jti'))
        ];

        return $config->validator()->validate($token, ...$constrains);
    }

    /**
     * Decode an operator-supplied SB_SECRET_KEY into a signing key.
     *
     * Public so tests can pin the invalid-key operator message without
     * redefining the SB_SECRET_KEY constant mid-process.
     */
    public static function signingKeyFromSecret(string $secret): InMemory
    {
        try {
            return InMemory::base64Encoded($secret);
        } catch (CannotDecodeContent|InvalidKeyProvided $e) {
            self::failInvalidSecretKey($e);
        }
    }

    private static function getConfig(): Configuration
    {
        return Configuration::forSymmetricSigner(new Sha256(), self::signingKeyFromSecret(SB_SECRET_KEY));
    }

    /**
     * Operator-facing abort when SB_SECRET_KEY is not valid base64.
     *
     * The JWT library's CannotDecodeContent message ("invalid base64
     * characters detected") is accurate but useless for a self-hoster
     * who pasted a UUID / random password into the env var. Surface
     * the fix instead of the library stack.
     */
    private static function failInvalidSecretKey(\Throwable $cause): never
    {
        $message = "SB_SECRET_KEY is not valid base64.\n"
            . "The panel's session cookies are signed with that value, so login cannot continue.\n"
            . "\n"
            . "Generate a valid key:\n"
            . "  openssl rand -base64 47\n"
            . "\n"
            . "Then set SB_SECRET_KEY to that output (env var or config.php) and restart.\n"
            . "Rotating the key logs every admin out.";

        error_log('[Sbpp\Auth\JWT] invalid SB_SECRET_KEY: ' . $cause->getMessage());

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $message . "\n");
            exit(1);
        }

        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        die($message);
    }
}

// Issue #1290 phase B: legacy global-name shim. Procedural code keeps
// using `\JWT` until the call-site sweep PR.
class_alias(\Sbpp\Auth\JWT::class, 'JWT');
