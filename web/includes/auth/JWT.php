<?php

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\ValidationData;

class JWT
{
    public static function create(string $jti, string $secret, int $maxlife, int $aid)
    {
        return (new Builder())->setIssuer(Host::domain())
                              ->setAudience(Host::domain())
                              ->setId($jti, true)
                              ->setIssuedAt(time())
                              ->setNotBefore(time())
                              ->setExpiration(time() + $maxlife)
                              ->set('aid', $aid)
                              ->sign(new Sha256(), $secret)
                              ->getToken();
    }

    public static function parse(string $jwt)
    {
        return (new Parser())->parse($jwt);
    }

    public static function validate(Token $token)
    {
        $data = new ValidationData();
        $data->setIssuer(Host::domain());
        $data->setAudience(Host::domain());

        return (bool)($token->validate($data));
    }

    public static function verify(Token $token, string $secret)
    {
        return (bool)($token->verify(new Sha256(), $secret));
    }
}
