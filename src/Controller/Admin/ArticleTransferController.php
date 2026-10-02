<?php

namespace App\Controller\Admin;

use App\Article\ArticleExporter;
use App\Article\ArticleImporter;
use App\Entity\User;
use App\Form\ArticleImportType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/articles')]
#[IsGranted('ROLE_ADMIN')]
final class ArticleTransferController extends AbstractController
{
    #[Route('/export', name: 'admin_articles_export', methods: ['GET'])]
    public function export(ArticleExporter $exporter): BinaryFileResponse
    {
        $response = new BinaryFileResponse($exporter->export());
        $response->headers->set('Content-Type', 'application/zip');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('articles-%s.zip', date('Y-m-d')),
        );
        $response->deleteFileAfterSend();

        return $response;
    }

    #[Route('/import', name: 'admin_articles_import', methods: ['GET', 'POST'])]
    public function import(
        Request $request,
        ArticleImporter $importer,
        LoggerInterface $auditLogger,
        #[CurrentUser] User $user,
    ): Response {
        $form = $this->createForm(ArticleImportType::class);
        $form->handleRequest($request);
        $report = null;

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $report = $importer->import(
                    $form->get('csv')->getData()->getPathname(),
                    $form->get('images')->getData() ?? [],
                    $user,
                );

                $auditLogger->info("Import d'articles", [
                    'admin' => $user->getEmail(),
                    'created' => $report['created'],
                    'updated' => $report['updated'],
                    'errors' => count($report['errors']),
                ]);
            } catch (\InvalidArgumentException $e) {
                $form->get('csv')->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('admin/article_import.html.twig', [
            'form' => $form,
            'report' => $report,
        ]);
    }
}
