<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Connection $connection): Response
    {
        $stats = [
            'reports_submitted' => (int) $connection->fetchOne('SELECT COUNT(*) FROM issues'),
            'verified_issues' => (int) $connection->fetchOne("SELECT COUNT(*) FROM issues WHERE status IN ('verified', 'assigned', 'in_progress', 'resolved')"),
            'resolved_fixes' => (int) $connection->fetchOne("SELECT COUNT(*) FROM issues WHERE status = 'resolved'"),
            'active_volunteers' => (int) $connection->fetchOne("SELECT COUNT(*) FROM users WHERE roles::text LIKE '%ROLE_VOLUNTEER%'"),
        ];

        $recentIssues = $connection->fetchAllAssociative("
            SELECT
                i.id,
                i.report_reference,
                i.title,
                i.description,
                i.status,
                i.priority,
                i.confirmation_count,
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
            LIMIT 3
        ");

        $priorityFocus = $connection->fetchAssociative("
            SELECT
                c.name AS category_name,
                COUNT(i.id) AS issue_count,
                COALESCE(SUM(i.confirmation_count), 0) AS total_confirmations
            FROM issues i
            INNER JOIN issue_categories c ON c.id = i.category_id
            WHERE i.status IN ('submitted', 'verified', 'assigned', 'in_progress')
            GROUP BY c.name
            ORDER BY total_confirmations DESC, issue_count DESC
            LIMIT 1
        ");

        $activeAreas = $connection->fetchAllAssociative("
            SELECT
                d.name AS division_name,
                dis.name AS district_name,
                u.name AS upazila_name,
                COUNT(i.id) AS issue_count
            FROM issues i
            INNER JOIN divisions d ON d.id = i.division_id
            INNER JOIN districts dis ON dis.id = i.district_id
            INNER JOIN upazilas u ON u.id = i.upazila_id
            WHERE i.status IN ('submitted', 'verified', 'assigned', 'in_progress')
            GROUP BY d.name, dis.name, u.name
            ORDER BY issue_count DESC
            LIMIT 3
        ");

        return $this->render('home/index.html.twig', [
            'stats' => $stats,
            'recentIssues' => $recentIssues,
            'priorityFocus' => $priorityFocus,
            'activeAreas' => $activeAreas,
        ]);
    }
}