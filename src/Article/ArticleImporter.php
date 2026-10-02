<?php

namespace App\Article;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ArticleImporter
{
    private const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var array<string, Category> */
    private array $categories = [];
    /** @var array<string, Tag> */
    private array $tags = [];
    /** @var array<string, string> */
    private array $storedImages = [];
    /** @var string[] */
    private array $warnings = [];

    public function __construct(
        private EntityManagerInterface $em,
        private ArticleRepository $articleRepository,
        private CategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
        private UserRepository $userRepository,
        private SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads/articles')]
        private string $uploadDir,
    ) {
    }

    /**
     * @param UploadedFile[] $images
     *
     * @return array{created: int, updated: int, errors: string[], warnings: string[]}
     */
    public function import(string $csvPath, array $images, User $currentUser): array
    {
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle, escape: '');

        if (!$header || $header === [null]) {
            throw new \InvalidArgumentException('Le fichier CSV est vide.');
        }

        // Retire le BOM UTF-8 qu'ajoutent certains tableurs, et les espaces
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $header = array_map('trim', $header);

        foreach (['title', 'content'] as $column) {
            if (!in_array($column, $header, true)) {
                throw new \InvalidArgumentException(sprintf('La colonne obligatoire « %s » est absente du CSV.', $column));
            }
        }

        // Les images envoyées, indexées par leur nom de fichier
        $imagesByName = [];
        foreach ($images as $file) {
            if ($file instanceof UploadedFile && $file->isValid()
                && in_array($file->getMimeType(), self::IMAGE_MIME_TYPES, true)
            ) {
                $imagesByName[basename($file->getClientOriginalName())] = $file;
            }
        }

        $created = $updated = $index = 0;
        $errors = [];
        $seenSlugs = [];

        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if ($values === [null]) {
                continue; // ligne vide
            }

            $index++;

            if (count($values) !== count($header)) {
                $errors[] = sprintf('Article n°%d : nombre de colonnes incorrect.', $index);
                continue;
            }

            try {
                $isNew = $this->importRow(array_combine($header, $values), $imagesByName, $currentUser, $seenSlugs);
                $isNew ? $created++ : $updated++;
            } catch (\InvalidArgumentException $e) {
                $errors[] = sprintf('Article n°%d : %s', $index, $e->getMessage());
            }
        }

        fclose($handle);
        $this->em->flush();

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors, 'warnings' => $this->warnings];
    }

    /**
     * @param array<string, string>       $row
     * @param array<string, UploadedFile> $imagesByName
     * @param array<string, true>         $seenSlugs
     */
    private function importRow(array $row, array $imagesByName, User $currentUser, array &$seenSlugs): bool
    {
        $title = trim($row['title'] ?? '');
        $content = $row['content'] ?? '';

        if ($title === '' || trim($content) === '') {
            throw new \InvalidArgumentException('le titre et le contenu sont obligatoires.');
        }

        $slug = trim($row['slug'] ?? '') ?: $this->slugger->slug($title)->lower()->toString();

        if (isset($seenSlugs[$slug])) {
            throw new \InvalidArgumentException(sprintf('le slug « %s » apparaît deux fois dans le fichier.', $slug));
        }
        $seenSlugs[$slug] = true;

        // Un article qui existe déjà (même slug) est mis à jour au lieu d'être dupliqué
        $article = $this->articleRepository->findOneBy(['slug' => $slug]);
        $isNew = $article === null;

        if ($isNew) {
            $email = trim($row['author'] ?? '');
            $author = $email !== '' ? $this->userRepository->findOneBy(['email' => $email]) : null;

            $article = new Article();
            $article->setSlug($slug)->setAuthor($author ?? $currentUser);
            $this->em->persist($article);
        }

        $article->setTitle($title)
            ->setContent($content)
            ->setCategory($this->getCategory($row['category'] ?? ''))
            ->setStatus(ArticleStatus::tryFrom(trim($row['status'] ?? '')) ?? ArticleStatus::Draft);

        foreach ($article->getTags()->toArray() as $tag) {
            $article->removeTag($tag);
        }
        foreach (array_filter(array_map('trim', explode('|', $row['tags'] ?? ''))) as $tagName) {
            $article->addTag($this->getTag($tagName));
        }

        $publishedAt = trim($row['published_at'] ?? '');
        try {
            $article->setPublishedAt($publishedAt !== '' ? new \DateTimeImmutable($publishedAt) : null);
        } catch (\Exception) {
            throw new \InvalidArgumentException(sprintf('date de publication invalide « %s ».', $publishedAt));
        }

        $imageName = basename(trim($row['image'] ?? ''));
        if ($imageName !== '') {
            if (isset($imagesByName[$imageName])) {
                $article->setImage($this->storeImage($imagesByName[$imageName]));
            } elseif (is_file($this->uploadDir . '/' . $imageName)) {
                $article->setImage($imageName);
            } else {
                $this->warnings[] = sprintf('« %s » : image « %s » introuvable, article importé sans image.', $title, $imageName);
            }
        }

        return $isNew;
    }

    private function getCategory(string $name): Category
    {
        $name = trim($name) ?: 'Non classé';

        return $this->categories[mb_strtolower($name)] ??= $this->categoryRepository->findOneBy(['name' => $name])
            ?? $this->createCategory($name);
    }

    private function createCategory(string $name): Category
    {
        $category = new Category();
        $category->setName($name)->setSlug($this->slugger->slug($name)->lower()->toString());
        $this->em->persist($category);

        return $category;
    }

    private function getTag(string $name): Tag
    {
        return $this->tags[mb_strtolower($name)] ??= $this->tagRepository->findOneBy(['name' => $name])
            ?? $this->createTag($name);
    }

    private function createTag(string $name): Tag
    {
        $tag = new Tag();
        $tag->setName($name)->setSlug($this->slugger->slug($name)->lower()->toString());
        $this->em->persist($tag);

        return $tag;
    }

    /**
     * Enregistre l'image sous un nouveau nom sûr, comme le fait EasyAdmin.
     */
    private function storeImage(UploadedFile $file): string
    {
        $originalName = $file->getClientOriginalName();

        return $this->storedImages[$originalName] ??= (function () use ($file, $originalName): string {
            $base = $this->slugger->slug(pathinfo($originalName, PATHINFO_FILENAME))->lower();
            $hash = substr(hash_file('sha256', $file->getPathname()), 0, 12);
            $name = sprintf('%s-%s.%s', $base, $hash, $file->guessExtension() ?? 'jpg');

            if (!is_file($this->uploadDir . '/' . $name)) {
                $file->move($this->uploadDir, $name);
            }

            return $name;
        })();
    }
}
