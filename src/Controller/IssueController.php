<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IssueController extends AbstractController
{
    #[Route('/issues', name: 'app_issues')]
    public function index(Connection $connection): Response
    {
        $issues = $connection->fetchAllAssociative("
            SELECT
                i.id,
                i.report_reference,
                i.title,
                i.description,
                i.status,
                i.priority,
                i.address_text,
                i.confirmation_count,
                i.comment_count,
                i.photo_count,
                i.created_at,
                c.name AS category_name,
                d.name AS division_name,
                dis.name AS district_name,
                u.name AS upazila_name,
                la.name AS local_area_name
            FROM issues i
            INNER JOIN issue_categories c ON c.id = i.category_id
            INNER JOIN divisions d ON d.id = i.division_id
            INNER JOIN districts dis ON dis.id = i.district_id
            INNER JOIN upazilas u ON u.id = i.upazila_id
            LEFT JOIN local_areas la ON la.id = i.local_area_id
            ORDER BY i.created_at DESC
        ");

        $stats = [
            'total' => (int) $connection->fetchOne("SELECT COUNT(*) FROM issues"),
            'submitted' => (int) $connection->fetchOne("SELECT COUNT(*) FROM issues WHERE status = 'submitted'"),
            'verified' => (int) $connection->fetchOne("SELECT COUNT(*) FROM issues WHERE status IN ('verified', 'assigned', 'in_progress')"),
            'resolved' => (int) $connection->fetchOne("SELECT COUNT(*) FROM issues WHERE status = 'resolved'"),
        ];

        return $this->render('issue/index.html.twig', [
            'issues' => $issues,
            'stats' => $stats,
        ]);
    }

    #[Route('/issues/new', name: 'app_issue_new')]
    public function new(): Response
    {
        return $this->render('issue/new.html.twig');
    }

    #[Route('/issues/{id}', name: 'app_issue_show')]
    public function show(int $id, Connection $connection): Response
    {
        $issue = $connection->fetchAssociative("
            SELECT
                i.*,
                c.name AS category_name,
                d.name AS division_name,
                dis.name AS district_name,
                u.name AS upazila_name,
                la.name AS local_area_name,
                submitted.full_name AS submitted_by_name,
                assigned.full_name AS assigned_to_name
            FROM issues i
            INNER JOIN issue_categories c ON c.id = i.category_id
            INNER JOIN divisions d ON d.id = i.division_id
            INNER JOIN districts dis ON dis.id = i.district_id
            INNER JOIN upazilas u ON u.id = i.upazila_id
            LEFT JOIN local_areas la ON la.id = i.local_area_id
            LEFT JOIN users submitted ON submitted.id = i.submitted_by_id
            LEFT JOIN users assigned ON assigned.id = i.assigned_to_id
            WHERE i.id = :id
        ", [
            'id' => $id,
        ]);

        if (!$issue) {
            throw $this->createNotFoundException('Issue not found.');
        }

        return $this->render('issue/show.html.twig', [
            'issue' => $issue,
        ]);
    }
}