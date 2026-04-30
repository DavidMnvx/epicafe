<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Captcha "question mathématique simple" (3 + 5 = ?).
 *
 * - Pas de dépendance externe (pas de Google reCAPTCHA, pas d'API)
 * - Accessible (lecteurs d'écran lisent la question)
 * - Anti-bot suffisant pour un site vitrine
 *
 * Stockage : en session, on garde le hash de la réponse attendue (jamais la réponse en clair).
 */
final class MathCaptcha
{
    private const SESSION_KEY = '_contact_captcha_hash';

    /** Liste des opérations possibles (texte, opération PHP) */
    private const OPS = [
        ['label' => 'plus',  'op' => '+'],
        ['label' => 'moins', 'op' => '-'],
    ];

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * Génère une nouvelle question et stocke le hash de la réponse en session.
     *
     * @return string La question affichée à l'utilisateur (ex : "Combien font 3 plus 5 ?")
     */
    public function generate(): string
    {
        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $op = self::OPS[array_rand(self::OPS)];

        if ($op['op'] === '-' && $b > $a) {
            // évite les nombres négatifs : on échange
            [$a, $b] = [$b, $a];
        }

        $answer = $op['op'] === '+' ? $a + $b : $a - $b;

        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY, $this->hash((string) $answer));

        return sprintf('Combien font %d %s %d ?', $a, $op['label'], $b);
    }

    /**
     * Vérifie la réponse fournie par l'utilisateur.
     * Une question utilisée est invalidée (one-shot).
     */
    public function verify(string $userAnswer): bool
    {
        $session = $this->requestStack->getSession();
        $expected = $session->get(self::SESSION_KEY);

        if (!is_string($expected) || $expected === '') {
            return false;
        }

        $userAnswer = trim($userAnswer);
        $valid = hash_equals($expected, $this->hash($userAnswer));

        // One-shot : on retire la question après vérification, succès ou non
        $session->remove(self::SESSION_KEY);

        return $valid;
    }

    private function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
