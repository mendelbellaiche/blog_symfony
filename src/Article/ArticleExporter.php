<?php

namespace App\Article;

use App\Entity\Tag;
use App\Repository\ArticleRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ArticleExporter
{
    public const COLUMNS = ['title', 'slug', 'category', 'tags', 'status', 'published_at', 'image', 'author', 'content'];

    public function __construct(
        private ArticleRepository $articleRepository,
        #[Autowire('%kernel.project_dir%/public/uploads/articles')]
        private string $uploadDir,
    ) {
    }

    /**
     * Crée l'archive ZIP dans un fichier temporaire et renvoie son chemin.
     */
    public function export(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'articles_');

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Impossible de créer l'archive ZIP.");
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, self::COLUMNS, escape: '');

        foreach ($this->articleRepository->findAllForExport() as $article) {
            fputcsv($csv, [
                $article->getTitle(),
                $article->getSlug(),
                $article->getCategory()->getName(),
                implode('|', $article->getTags()->map(fn (Tag $tag) => $tag->getName())->toArray()),
                $article->getStatus()->value,
                $article->getPublishedAt()?->format('Y-m-d H:i:s') ?? '',
                $article->getImage() ?? '',
                $article->getAuthor()->getEmail(),
                $article->getContent(),
            ], escape: '');

            $image = $article->getImage();
            if ($image && is_file($this->uploadDir . '/' . $image)) {
                $zip->addFile($this->uploadDir . '/' . $image, 'images/' . $image);
            }
        }

        rewind($csv);
        $zip->addFromString('articles.csv', stream_get_contents($csv));
        fclose($csv);
        $zip->close();

        return $path;
    }
}
