<?php

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\ValidationData;

class JWT
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

    private static function getConfig(): Configuration
    {
        return Configuration::forSymmetricSigner(new Sha256(), InMemory::base64Encoded(SB_SECRET_KEY));
    }
}
