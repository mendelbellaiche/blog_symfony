<?php

namespace App\Twig;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Twig\Attribute\AsTwigFilter;

final class MarkdownExtension
{
    private GithubFlavoredMarkdownConverter $converter;

    public function __construct()
    {
        $this->converter = new GithubFlavoredMarkdownConverter([
            // Le HTML écrit dans l'article est affiché comme du texte, jamais exécuté
            'html_input' => 'escape',
            // Refuse les liens dangereux comme javascript:...
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }

    #[AsTwigFilter('markdown', isSafe: ['html'])]
    public function markdown(string $content): string
    {
        return $this->converter->convert($content)->getContent();
    }

    #[AsTwigFilter('markdown_excerpt')]
    public function markdownExcerpt(string $content, int $length = 200): string
    {
        // Convertit en HTML, retire toutes les balises, puis coupe le texte
        $text = strip_tags($this->markdown($content));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return mb_strimwidth($text, 0, $length, '…');
    }
}
