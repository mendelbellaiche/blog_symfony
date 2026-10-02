<?php

namespace App\Moderation;

use App\Repository\ModerationWordRepository;

use function Symfony\Component\String\u;

final class ModerationMatcher
{
    /** @var array{watch: string[], forbidden: string[]}|null */
    private ?array $terms = null;

    public function __construct(private ModerationWordRepository $moderationWordRepository)
    {
    }

    /**
     * @return string[] les termes interdits trouvés dans le texte
     */
    public function findForbidden(string $text): array
    {
        return $this->match($text, $this->terms()['forbidden']);
    }

    /**
     * @return array{forbidden: string[], watch: string[]}
     */
    public function analyze(string $text): array
    {
        return [
            'forbidden' => $this->match($text, $this->terms()['forbidden']),
            'watch' => $this->match($text, $this->terms()['watch']),
        ];
    }

    /**
     * @return string[] tous les termes des deux listes
     */
    public function allTerms(): array
    {
        return [...$this->terms()['forbidden'], ...$this->terms()['watch']];
    }

    /**
     * @return array{watch: string[], forbidden: string[]}
     */
    private function terms(): array
    {
        // Les listes ne sont chargées qu'une fois par requête
        if ($this->terms === null) {
            $this->terms = ['watch' => [], 'forbidden' => []];

            foreach ($this->moderationWordRepository->findAll() as $word) {
                $this->terms[$word->getType()->value][] = $word->getTerm();
            }
        }

        return $this->terms;
    }

    /**
     * @param string[] $terms
     *
     * @return string[]
     */
    private function match(string $text, array $terms): array
    {
        $haystack = self::normalize($text);

        return array_values(array_filter(
            $terms,
            fn (string $term) => preg_match($this->pattern($term), $haystack) === 1,
        ));
    }

    /**
     * « Enculé  de… » devient « encule de… » : minuscules, sans accents, espaces simples.
     */
    private static function normalize(string $text): string
    {
        return u($text)->ascii()->lower()->collapseWhitespace()->toString();
    }

    private function pattern(string $term): string
    {
        $isPrefix = str_ends_with($term, '*');
        $words = explode(' ', self::normalize(rtrim($term, '*')));
        $body = implode('\s+', array_map(fn (string $word) => preg_quote($word, '/'), $words));

        // Le terme ne doit pas être collé à une autre lettre ou un chiffre,
        // sauf à la fin s'il se termine par *
        return '/(?<![a-z0-9])' . $body . ($isPrefix ? '' : '(?![a-z0-9])') . '/';
    }
}
