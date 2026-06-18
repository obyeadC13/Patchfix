<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    public function new(Request $request, Connection $connection): Response
    {
        $categories = $connection->fetchAllAssociative("
            SELECT id, name
            FROM issue_categories
            WHERE is_active = true
            ORDER BY name ASC
        ");

        $divisions = $connection->fetchAllAssociative("
            SELECT id, name
            FROM divisions
            ORDER BY name ASC
        ");

        $districts = $connection->fetchAllAssociative("
            SELECT id, division_id, name
            FROM districts
            ORDER BY name ASC
        ");

        $upazilas = $connection->fetchAllAssociative("
            SELECT id, district_id, name, type
            FROM upazilas
            ORDER BY name ASC
        ");

        $localAreas = $connection->fetchAllAssociative("
            SELECT id, upazila_id, name, type
            FROM local_areas
            ORDER BY name ASC
        ");

        if ($request->isMethod('POST')) {
            $title = trim((string) $request->request->get('title'));
            $description = trim((string) $request->request->get('description'));
            $addressText = trim((string) $request->request->get('address_text'));
            $priority = (string) $request->request->get('priority', 'normal');

            $categoryId = (int) $request->request->get('category_id');
            $divisionId = (int) $request->request->get('division_id');
            $districtId = (int) $request->request->get('district_id');
            $upazilaId = (int) $request->request->get('upazila_id');

            $localAreaId = $request->request->get('local_area_id')
                ? (int) $request->request->get('local_area_id')
                : null;

            if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
                $priority = 'normal';
            }

            if (
                $title === '' ||
                $description === '' ||
                $addressText === '' ||
                !$categoryId ||
                !$divisionId ||
                !$districtId ||
                !$upazilaId
            ) {
                $this->addFlash('error', 'Please fill in all required fields.');

                return $this->render('issue/new.html.twig', [
                    'categories' => $categories,
                    'divisions' => $divisions,
                    'districts' => $districts,
                    'upazilas' => $upazilas,
                    'localAreas' => $localAreas,
                ]);
            }

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $reference = $this->generateIssueReference($connection);

            $connection->insert('issues', [
                'report_reference' => $reference,
                'title' => $title,
                'description' => $description,
                'status' => 'submitted',
                'priority' => $priority,
                'address_text' => $addressText,
                'submitted_by_id' => null,
                'assigned_to_id' => null,
                'category_id' => $categoryId,
                'division_id' => $divisionId,
                'district_id' => $districtId,
                'upazila_id' => $upazilaId,
                'local_area_id' => $localAreaId,
                'confirmation_count' => 0,
                'comment_count' => 0,
                'photo_count' => 0,
                'created_at' => $now,
                'updated_at' => null,
            ]);

            $issueId = (int) $connection->lastInsertId();

            $connection->insert('issue_status_logs', [
                'issue_id' => $issueId,
                'old_status' => null,
                'new_status' => 'submitted',
                'changed_by_id' => null,
                'note' => 'Issue submitted through the public report form.',
                'created_at' => $now,
            ]);

            $this->addFlash('success', 'Your issue report has been submitted.');

            return $this->redirectToRoute('app_issue_show', [
                'id' => $issueId,
            ]);
        }

        return $this->render('issue/new.html.twig', [
            'categories' => $categories,
            'divisions' => $divisions,
            'districts' => $districts,
            'upazilas' => $upazilas,
            'localAreas' => $localAreas,
        ]);
    }

    #[Route('/issues/{id}', name: 'app_issue_show', requirements: ['id' => '\d+'])]
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

        $statusLogs = [];

        if ($this->tableExists($connection, 'issue_status_logs')) {
            $statusLogs = $connection->fetchAllAssociative("
                SELECT
                    l.old_status,
                    l.new_status,
                    l.note,
                    l.created_at,
                    u.full_name AS changed_by_name
                FROM issue_status_logs l
                LEFT JOIN users u ON u.id = l.changed_by_id
                WHERE l.issue_id = :id
                ORDER BY l.created_at ASC
            ", [
                'id' => $id,
            ]);
        }

        $photos = [];

        if ($this->tableExists($connection, 'issue_photos')) {
            $imageColumn = $this->firstExistingColumn($connection, 'issue_photos', [
                'image_url',
                'photo_url',
                'file_url',
                'file_path',
                'path',
                'url',
            ]);

            $captionColumn = $this->firstExistingColumn($connection, 'issue_photos', [
                'caption',
                'description',
                'note',
                'title',
            ]);

            if ($imageColumn) {
                $captionSelect = $captionColumn
                    ? "p.{$captionColumn} AS caption"
                    : "NULL AS caption";

                $photos = $connection->fetchAllAssociative("
                    SELECT
                        p.{$imageColumn} AS image_url,
                        {$captionSelect},
                        p.created_at
                    FROM issue_photos p
                    WHERE p.issue_id = :id
                    ORDER BY p.created_at DESC
                ", [
                    'id' => $id,
                ]);
            }
        }

        $comments = [];

        if ($this->tableExists($connection, 'issue_comments')) {
            $commentColumn = $this->firstExistingColumn($connection, 'issue_comments', [
                'body',
                'comment',
                'comment_text',
                'content',
                'message',
                'note',
            ]);

            $authorColumn = $this->firstExistingColumn($connection, 'issue_comments', [
                'user_id',
                'author_id',
                'created_by_id',
                'submitted_by_id',
            ]);

            if ($commentColumn) {
                if ($authorColumn) {
                    $comments = $connection->fetchAllAssociative("
                        SELECT
                            c.{$commentColumn} AS body,
                            c.created_at,
                            u.full_name AS author_name
                        FROM issue_comments c
                        LEFT JOIN users u ON u.id = c.{$authorColumn}
                        WHERE c.issue_id = :id
                        ORDER BY c.created_at DESC
                    ", [
                        'id' => $id,
                    ]);
                } else {
                    $comments = $connection->fetchAllAssociative("
                        SELECT
                            c.{$commentColumn} AS body,
                            c.created_at,
                            'Community member' AS author_name
                        FROM issue_comments c
                        WHERE c.issue_id = :id
                        ORDER BY c.created_at DESC
                    ", [
                        'id' => $id,
                    ]);
                }
            }
        }

        return $this->render('issue/show.html.twig', [
            'issue' => $issue,
            'statusLogs' => $statusLogs,
            'comments' => $comments,
            'photos' => $photos,
        ]);
    }

    private function generateIssueReference(Connection $connection): string
    {
        $nextId = (int) $connection->fetchOne('SELECT COALESCE(MAX(id), 0) + 1 FROM issues');

        return sprintf('PF-%06d', $nextId);
    }

    private function tableExists(Connection $connection, string $table): bool
    {
        return (bool) $connection->fetchOne("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name = :table
            LIMIT 1
        ", [
            'table' => $table,
        ]);
    }

    private function hasColumn(Connection $connection, string $table, string $column): bool
    {
        return (bool) $connection->fetchOne("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = :table
              AND column_name = :column
            LIMIT 1
        ", [
            'table' => $table,
            'column' => $column,
        ]);
    }

    private function firstExistingColumn(Connection $connection, string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if ($this->hasColumn($connection, $table, $column)) {
                return $column;
            }
        }

        return null;
    }
}
