<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Oidc;

use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;
use Psr\Clock\ClockInterface;

/**
 * Prüft das ID-Token: Signatur (RS256, Schlüssel aus dem JWKS), Aussteller, Empfänger, Gültigkeit, nonce.
 */
class IdTokenPruefer
{
    public function __construct(private readonly ?ClockInterface $uhr = null)
    {
    }

    /** @param array{keys?: list<array<string, mixed>>} $jwks */
    public function pruefe(string $idToken, array $jwks, string $aussteller, string $clientId, string $nonce): Identitaet
    {
        try {
            $token = (new Parser(new JoseEncoder()))->parse($idToken);
        } catch (\Throwable $e) {
            throw new OidcFehler('ID-Token nicht lesbar: ' . $e->getMessage(), 0, $e);
        }
        if (!$token instanceof UnencryptedToken) {
            throw new OidcFehler('ID-Token nicht lesbar.');
        }
        if ($token->headers()->get('alg') !== 'RS256') {
            throw new OidcFehler('Unerwartetes Signaturverfahren.');
        }

        $kid = $token->headers()->get('kid');
        $schluessel = null;
        foreach ($jwks['keys'] ?? [] as $jwk) {
            if ($kid === null || ($jwk['kid'] ?? null) === $kid) {
                $schluessel = $jwk;
                break;
            }
        }
        if ($schluessel === null) {
            throw new OidcFehler('Kein passender Schlüssel im JWKS.');
        }

        try {
            (new Validator())->assert(
                $token,
                new SignedWith(new Sha256(), InMemory::plainText(Jwk::rsaPem($schluessel))),
                new IssuedBy($aussteller),
                new PermittedFor($clientId),
                // ZITADEL setzt kein nbf; exp ist unten Pflicht, iat und exp prüft LooseValidAt
                new LooseValidAt($this->uhr ?? SystemClock::fromUTC(), new \DateInterval('PT60S')),
            );
        } catch (RequiredConstraintsViolated $e) {
            throw new OidcFehler('ID-Token ungültig: ' . implode(' / ', array_map(static fn ($v) => $v->getMessage(), $e->violations())), 0, $e);
        }

        $claims = $token->claims();
        if (!$claims->has('exp')) {
            throw new OidcFehler('ID-Token ohne Ablaufdatum.');
        }
        if (!\is_string($claims->get('nonce')) || !hash_equals($nonce, $claims->get('nonce'))) {
            throw new OidcFehler('nonce stimmt nicht.');
        }
        $sub = $claims->get('sub');
        $email = $claims->get('email');
        if (!\is_string($sub) || $sub === '' || !\is_string($email) || $email === '') {
            throw new OidcFehler('sub oder email fehlt im ID-Token (Anwendung braucht „Benutzerinfo im ID-Token“).');
        }

        return new Identitaet(
            $sub,
            mb_strtolower($email),
            $claims->get('email_verified') === true,
            (string) ($claims->get('given_name') ?? ''),
            (string) ($claims->get('family_name') ?? ''),
            $idToken,
        );
    }
}
