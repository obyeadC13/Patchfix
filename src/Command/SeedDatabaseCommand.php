<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed-database',
    description: 'Seed Patch Fix database with dummy data for every table.'
)]
class SeedDatabaseCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->connection->beginTransaction();

        try {
            $this->seedUsers();
            $this->seedLocations();
            $this->seedIssueCategories();
            $this->seedOrganizationsAndTeams();
            $this->seedIssues();
            $this->seedIssuePhotos();
            $this->seedIssueStatusLogs();
            $this->seedIssueAssignments();
            $this->seedIssueRejections();
            $this->seedDuplicateIssues();
            $this->seedIssueComments();
            $this->seedIssueConfirmations();
            $this->seedIssueFollowers();
            $this->seedTeamMembers();
            $this->seedNotifications();
            $this->seedAuditLogs();

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            $output->writeln('<error>Seed failed:</error>');
            $output->writeln($exception->getMessage());

            return Command::FAILURE;
        }

        $output->writeln('<info>Patch Fix database seeded successfully.</info>');

        foreach ([
            'users',
            'divisions',
            'districts',
            'upazilas',
            'local_areas',
            'issue_categories',
            'issues',
            'issue_photos',
            'issue_status_logs',
            'issue_assignments',
            'issue_rejections',
            'duplicate_issues',
            'issue_comments',
            'issue_confirmations',
            'issue_followers',
            'organizations',
            'volunteer_teams',
            'team_members',
            'notifications',
            'audit_logs',
        ] as $table) {
            $count = $this->connection->fetchOne("SELECT COUNT(*) FROM {$table}");
            $output->writeln(sprintf('%s: %s', $table, $count));
        }

        return Command::SUCCESS;
    }

    private function seedUsers(): void
    {
        $password = password_hash('password', PASSWORD_BCRYPT);

        $users = [
            [
                'email' => 'admin@patchfix.test',
                'roles' => ['ROLE_ADMIN'],
                'full_name' => 'Patch Fix Admin',
                'phone' => '+8801700000001',
            ],
            [
                'email' => 'moderator@patchfix.test',
                'roles' => ['ROLE_MODERATOR'],
                'full_name' => 'Nadia Rahman',
                'phone' => '+8801700000002',
            ],
            [
                'email' => 'volunteer@patchfix.test',
                'roles' => ['ROLE_VOLUNTEER'],
                'full_name' => 'Tanvir Hasan',
                'phone' => '+8801700000003',
            ],
            [
                'email' => 'citizen1@patchfix.test',
                'roles' => ['ROLE_CITIZEN'],
                'full_name' => 'Ayesha Karim',
                'phone' => '+8801700000004',
            ],
            [
                'email' => 'citizen2@patchfix.test',
                'roles' => ['ROLE_CITIZEN'],
                'full_name' => 'Rafi Ahmed',
                'phone' => '+8801700000005',
            ],
            [
                'email' => 'citizen3@patchfix.test',
                'roles' => ['ROLE_CITIZEN'],
                'full_name' => 'Maliha Islam',
                'phone' => '+8801700000006',
            ],
        ];

        foreach ($users as $user) {
            $this->upsert('users', ['email' => $user['email']], [
                'email' => $user['email'],
                'roles' => $user['roles'],
                'password' => $password,
                'full_name' => $user['full_name'],
                'phone' => $user['phone'],
                'is_verified' => true,
                'last_login_at' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function seedLocations(): void
    {
        $data = [
            'Dhaka' => [
                'bn_name' => 'ঢাকা',
                'code' => '30',
                'districts' => [
                    'Dhaka' => [
                        'bn_name' => 'ঢাকা',
                        'upazilas' => [
                            'Dhanmondi' => ['type' => 'thana', 'locals' => ['Road 27', 'Road 32', 'Kalabagan', 'Satmasjid Road']],
                            'Mirpur' => ['type' => 'thana', 'locals' => ['Section 10', 'Section 11', 'Kazipara', 'Shewrapara']],
                            'Kafrul' => ['type' => 'thana', 'locals' => ['Kafrul', 'Ibrahimpur', 'Senpara Parbata']],
                            'Mohammadpur' => ['type' => 'thana', 'locals' => ['Town Hall', 'Shia Masjid', 'Adabor', 'Bashbari']],
                            'Uttara' => ['type' => 'thana', 'locals' => ['Sector 3', 'Sector 7', 'Sector 10', 'Azampur']],
                            'Gulshan' => ['type' => 'thana', 'locals' => ['Gulshan 1', 'Gulshan 2', 'Niketan']],
                            'Banani' => ['type' => 'thana', 'locals' => ['Banani DOHS', 'Kakoli', 'Chairman Bari']],
                        ],
                    ],
                    'Gazipur' => [
                        'bn_name' => 'গাজীপুর',
                        'upazilas' => [
                            'Gazipur Sadar' => ['type' => 'upazila', 'locals' => ['Joydebpur', 'Board Bazar']],
                            'Tongi' => ['type' => 'thana', 'locals' => ['Tongi Bazar', 'Station Road']],
                        ],
                    ],
                    'Narayanganj' => [
                        'bn_name' => 'নারায়ণগঞ্জ',
                        'upazilas' => [
                            'Narayanganj Sadar' => ['type' => 'upazila', 'locals' => ['Chashara', 'Dewbhog']],
                            'Fatullah' => ['type' => 'thana', 'locals' => ['Fatullah Bazar']],
                        ],
                    ],
                ],
            ],
            'Chattogram' => [
                'bn_name' => 'চট্টগ্রাম',
                'code' => '20',
                'districts' => [
                    'Chattogram' => [
                        'bn_name' => 'চট্টগ্রাম',
                        'upazilas' => [
                            'Kotwali' => ['type' => 'thana', 'locals' => ['New Market', 'Laldighi']],
                            'Pahartali' => ['type' => 'thana', 'locals' => ['Pahartali Bazar']],
                            'Panchlaish' => ['type' => 'thana', 'locals' => ['GEC Circle', 'Nasirabad']],
                        ],
                    ],
                    'Cumilla' => [
                        'bn_name' => 'কুমিল্লা',
                        'upazilas' => [
                            'Cumilla Sadar' => ['type' => 'upazila', 'locals' => ['Kandirpar']],
                            'Daudkandi' => ['type' => 'upazila', 'locals' => ['Daudkandi Bazar']],
                        ],
                    ],
                ],
            ],
            'Rajshahi' => [
                'bn_name' => 'রাজশাহী',
                'code' => '50',
                'districts' => [
                    'Rajshahi' => [
                        'bn_name' => 'রাজশাহী',
                        'upazilas' => [
                            'Boalia' => ['type' => 'thana', 'locals' => ['Shaheb Bazar']],
                            'Rajshahi Sadar' => ['type' => 'upazila', 'locals' => ['Court Area']],
                        ],
                    ],
                    'Bogura' => [
                        'bn_name' => 'বগুড়া',
                        'upazilas' => [
                            'Bogura Sadar' => ['type' => 'upazila', 'locals' => ['Satmatha']],
                        ],
                    ],
                ],
            ],
            'Khulna' => [
                'bn_name' => 'খুলনা',
                'code' => '40',
                'districts' => [
                    'Khulna' => [
                        'bn_name' => 'খুলনা',
                        'upazilas' => [
                            'Khulna Sadar' => ['type' => 'upazila', 'locals' => ['Dak Bangla', 'Sonadanga']],
                            'Khalishpur' => ['type' => 'thana', 'locals' => ['Khalishpur Bazar']],
                        ],
                    ],
                    'Jashore' => [
                        'bn_name' => 'যশোর',
                        'upazilas' => [
                            'Jashore Sadar' => ['type' => 'upazila', 'locals' => ['Monihar', 'New Market']],
                        ],
                    ],
                ],
            ],
            'Barishal' => [
                'bn_name' => 'বরিশাল',
                'code' => '10',
                'districts' => [
                    'Barishal' => [
                        'bn_name' => 'বরিশাল',
                        'upazilas' => [
                            'Barishal Sadar' => ['type' => 'upazila', 'locals' => ['Nathullabad', 'Rupatali']],
                        ],
                    ],
                ],
            ],
            'Sylhet' => [
                'bn_name' => 'সিলেট',
                'code' => '60',
                'districts' => [
                    'Sylhet' => [
                        'bn_name' => 'সিলেট',
                        'upazilas' => [
                            'Sylhet Sadar' => ['type' => 'upazila', 'locals' => ['Zindabazar', 'Ambarkhana']],
                        ],
                    ],
                ],
            ],
            'Rangpur' => [
                'bn_name' => 'রংপুর',
                'code' => '55',
                'districts' => [
                    'Rangpur' => [
                        'bn_name' => 'রংপুর',
                        'upazilas' => [
                            'Rangpur Sadar' => ['type' => 'upazila', 'locals' => ['Jahaj Company Mor']],
                        ],
                    ],
                ],
            ],
            'Mymensingh' => [
                'bn_name' => 'ময়মনসিংহ',
                'code' => '45',
                'districts' => [
                    'Mymensingh' => [
                        'bn_name' => 'ময়মনসিংহ',
                        'upazilas' => [
                            'Mymensingh Sadar' => ['type' => 'upazila', 'locals' => ['Ganginarpar']],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($data as $divisionName => $divisionData) {
            $divisionId = $this->upsert('divisions', ['name' => $divisionName], [
                'name' => $divisionName,
                'bn_name' => $divisionData['bn_name'],
                'code' => $divisionData['code'],
            ]);

            foreach ($divisionData['districts'] as $districtName => $districtData) {
                $districtId = $this->upsert('districts', [
                    'division_id' => $divisionId,
                    'name' => $districtName,
                ], [
                    'division_id' => $divisionId,
                    'name' => $districtName,
                    'bn_name' => $districtData['bn_name'],
                    'code' => null,
                ]);

                foreach ($districtData['upazilas'] as $upazilaName => $upazilaData) {
                    $upazilaId = $this->upsert('upazilas', [
                        'district_id' => $districtId,
                        'name' => $upazilaName,
                    ], [
                        'district_id' => $districtId,
                        'name' => $upazilaName,
                        'bn_name' => null,
                        'code' => null,
                        'type' => $upazilaData['type'],
                    ]);

                    foreach ($upazilaData['locals'] as $localName) {
                        $this->upsert('local_areas', [
                            'upazila_id' => $upazilaId,
                            'name' => $localName,
                        ], [
                            'upazila_id' => $upazilaId,
                            'name' => $localName,
                            'bn_name' => null,
                            'code' => null,
                            'type' => 'neighborhood',
                        ]);
                    }
                }
            }
        }
    }

    private function seedIssueCategories(): void
    {
        $categories = [
            ['Waste', 'waste', 'Garbage, dumping, and waste collection problems', 3],
            ['Drainage', 'drainage', 'Blocked drains and sewer overflow issues', 4],
            ['Road Damage', 'road-damage', 'Broken roads, potholes, and damaged surfaces', 4],
            ['Streetlight', 'streetlight', 'Broken or unsafe public lighting', 2],
            ['Waterlogging', 'waterlogging', 'Flooded or waterlogged streets', 5],
            ['Public Safety', 'public-safety', 'Safety hazards in public spaces', 5],
            ['Footpath', 'footpath', 'Damaged, blocked, or unsafe walking paths', 3],
            ['Illegal Dumping', 'illegal-dumping', 'Unauthorized dumping of waste or materials', 4],
            ['Noise', 'noise', 'Noise complaints and public disturbance issues', 2],
            ['Other', 'other', 'Other civic issues', 1],
        ];

        foreach ($categories as [$name, $slug, $description, $severityWeight]) {
            $this->upsert('issue_categories', ['slug' => $slug], [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'severity_weight' => $severityWeight,
                'is_active' => true,
            ]);
        }
    }

    private function seedOrganizationsAndTeams(): void
    {
        $orgId = $this->upsert('organizations', ['slug' => 'patch-fix-volunteers'], [
            'name' => 'Patch Fix Volunteers',
            'slug' => 'patch-fix-volunteers',
            'description' => 'A civic volunteer group helping track and resolve local issues.',
            'email' => 'volunteers@patchfix.test',
            'phone' => '+8801800000001',
            'website' => 'https://patchfix.test',
        ]);

        $dhakaId = $this->getId('divisions', ['name' => 'Dhaka']);
        $dhakaDistrictId = $this->getId('districts', ['name' => 'Dhaka']);
        $mirpurId = $this->getId('upazilas', ['name' => 'Mirpur']);

        $this->upsert('volunteer_teams', ['name' => 'Mirpur Response Team'], [
            'organization_id' => $orgId,
            'name' => 'Mirpur Response Team',
            'description' => 'Handles verified issues in Mirpur and nearby areas.',
            'division_id' => $dhakaId,
            'district_id' => $dhakaDistrictId,
            'upazila_id' => $mirpurId,
        ]);

        $dhanmondiId = $this->getId('upazilas', ['name' => 'Dhanmondi']);

        $this->upsert('volunteer_teams', ['name' => 'Dhanmondi Clean Streets'], [
            'organization_id' => $orgId,
            'name' => 'Dhanmondi Clean Streets',
            'description' => 'Focuses on waste, footpath, and drainage issues.',
            'division_id' => $dhakaId,
            'district_id' => $dhakaDistrictId,
            'upazila_id' => $dhanmondiId,
        ]);
    }

    private function seedIssues(): void
    {
        $issues = [
            ['PF-000001', 'Blocked drain causing water buildup', 'The drain has been blocked for several days and water is collecting across the street after rain.', 'submitted', 'high', 'Road 12, near the pharmacy', 'drainage', 'Dhaka', 'Dhaka', 'Dhanmondi', 'Road 27', 18, 2],
            ['PF-000002', 'Broken streetlight near main road', 'A streetlight has stopped working, making this stretch unsafe at night.', 'verified', 'normal', 'Section 10, main road', 'streetlight', 'Dhaka', 'Dhaka', 'Mirpur', 'Section 10', 9, 2],
            ['PF-000003', 'Garbage pile left uncollected', 'Waste has been piling up beside the lane and creating a bad smell for nearby residents.', 'in_progress', 'high', 'Town Hall market side lane', 'waste', 'Dhaka', 'Dhaka', 'Mohammadpur', 'Town Hall', 26, 1],
            ['PF-000004', 'Damaged footpath near school entrance', 'The footpath is broken and students are walking on the road during busy hours.', 'assigned', 'urgent', 'Near school gate, Kazipara', 'footpath', 'Dhaka', 'Dhaka', 'Mirpur', 'Kazipara', 31, 1],
            ['PF-000005', 'Streetlight not working near Section 10', 'This appears to be the same broken streetlight issue already reported nearby.', 'duplicate', 'normal', 'Section 10, near bus stand', 'streetlight', 'Dhaka', 'Dhaka', 'Mirpur', 'Section 10', 4, 0],
            ['PF-000006', 'Illegal dumping beside market entrance', 'Construction waste has been dumped beside the market entrance and blocks pedestrians.', 'rejected', 'normal', 'Near New Market entrance', 'illegal-dumping', 'Chattogram', 'Chattogram', 'Kotwali', 'New Market', 3, 0],
        ];

        foreach ($issues as [$ref, $title, $description, $status, $priority, $address, $categorySlug, $division, $district, $upazila, $local, $confirmations, $comments]) {
            $categoryId = $this->getId('issue_categories', ['slug' => $categorySlug]);
            $divisionId = $this->getId('divisions', ['name' => $division]);
            $districtId = $this->getId('districts', ['division_id' => $divisionId, 'name' => $district]);
            $upazilaId = $this->getId('upazilas', ['district_id' => $districtId, 'name' => $upazila]);
            $localAreaId = $this->getId('local_areas', ['upazila_id' => $upazilaId, 'name' => $local]);

            $submittedById = $this->getId('users', ['email' => 'citizen1@patchfix.test']);
            $assignedToId = in_array($status, ['assigned', 'in_progress'], true)
                ? $this->getId('users', ['email' => 'volunteer@patchfix.test'])
                : null;

            $this->upsert('issues', ['report_reference' => $ref], [
                'report_reference' => $ref,
                'title' => $title,
                'description' => $description,
                'status' => $status,
                'priority' => $priority,
                'address_text' => $address,
                'submitted_by_id' => $submittedById,
                'assigned_to_id' => $assignedToId,
                'category_id' => $categoryId,
                'division_id' => $divisionId,
                'district_id' => $districtId,
                'upazila_id' => $upazilaId,
                'local_area_id' => $localAreaId,
                'confirmation_count' => $confirmations,
                'comment_count' => $comments,
                'photo_count' => 1,
                'verified_at' => in_array($status, ['verified', 'assigned', 'in_progress', 'resolved'], true) ? (new \DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s') : null,
                'assigned_at' => in_array($status, ['assigned', 'in_progress'], true) ? (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s') : null,
                'started_at' => $status === 'in_progress' ? (new \DateTimeImmutable('-12 hours'))->format('Y-m-d H:i:s') : null,
                'rejected_at' => $status === 'rejected' ? (new \DateTimeImmutable('-5 hours'))->format('Y-m-d H:i:s') : null,
            ]);
        }
    }

    private function seedIssuePhotos(): void
    {
        foreach (['PF-000001', 'PF-000002', 'PF-000003', 'PF-000004'] as $reference) {
            $issueId = $this->getId('issues', ['report_reference' => $reference]);

            $this->upsert('issue_photos', ['issue_id' => $issueId, 'type' => 'report_photo'], [
                'issue_id' => $issueId,
                'uploaded_by_id' => $this->getId('users', ['email' => 'citizen1@patchfix.test']),
                'filename' => strtolower($reference) . '.jpg',
                'original_name' => strtolower($reference) . '-report.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 245000,
                'storage_path' => '/uploads/demo/' . strtolower($reference) . '.jpg',
                'caption' => 'Demo issue photo',
                'type' => 'report_photo',
            ]);
        }
    }

    private function seedIssueStatusLogs(): void
    {
        $moderatorId = $this->getId('users', ['email' => 'moderator@patchfix.test']);
        $volunteerId = $this->getId('users', ['email' => 'volunteer@patchfix.test']);

        $logs = [
            ['PF-000001', null, 'submitted', null, 'Issue submitted by community member.'],
            ['PF-000002', null, 'submitted', null, 'Issue submitted by community member.'],
            ['PF-000002', 'submitted', 'verified', $moderatorId, 'Moderator verified this report.'],
            ['PF-000003', null, 'submitted', null, 'Issue submitted by community member.'],
            ['PF-000003', 'submitted', 'verified', $moderatorId, 'Report verified after community confirmations.'],
            ['PF-000003', 'verified', 'assigned', $moderatorId, 'Assigned to volunteer team.'],
            ['PF-000003', 'assigned', 'in_progress', $volunteerId, 'Volunteer marked work as started.'],
            ['PF-000004', 'verified', 'assigned', $moderatorId, 'Assigned due to urgent safety risk.'],
            ['PF-000006', 'submitted', 'rejected', $moderatorId, 'Rejected due to insufficient location details.'],
        ];

        foreach ($logs as [$ref, $old, $new, $userId, $note]) {
            $issueId = $this->getId('issues', ['report_reference' => $ref]);

            $this->upsert('issue_status_logs', [
                'issue_id' => $issueId,
                'new_status' => $new,
                'note' => $note,
            ], [
                'issue_id' => $issueId,
                'old_status' => $old,
                'new_status' => $new,
                'changed_by_id' => $userId,
                'note' => $note,
            ]);
        }
    }

    private function seedIssueAssignments(): void
    {
        $moderatorId = $this->getId('users', ['email' => 'moderator@patchfix.test']);
        $volunteerId = $this->getId('users', ['email' => 'volunteer@patchfix.test']);

        foreach (['PF-000003', 'PF-000004'] as $ref) {
            $issueId = $this->getId('issues', ['report_reference' => $ref]);

            $this->upsert('issue_assignments', ['issue_id' => $issueId, 'assigned_to_id' => $volunteerId], [
                'issue_id' => $issueId,
                'assigned_to_id' => $volunteerId,
                'assigned_by_id' => $moderatorId,
                'note' => 'Assigned from demo moderation queue.',
            ]);
        }
    }

    private function seedIssueRejections(): void
    {
        $issueId = $this->getId('issues', ['report_reference' => 'PF-000006']);

        $this->upsert('issue_rejections', ['issue_id' => $issueId], [
            'issue_id' => $issueId,
            'rejected_by_id' => $this->getId('users', ['email' => 'moderator@patchfix.test']),
            'reason' => 'insufficient_information',
            'details' => 'The report needs a clearer location and photo before action can be taken.',
        ]);
    }

    private function seedDuplicateIssues(): void
    {
        $originalId = $this->getId('issues', ['report_reference' => 'PF-000002']);
        $duplicateId = $this->getId('issues', ['report_reference' => 'PF-000005']);

        $this->upsert('duplicate_issues', ['duplicate_issue_id' => $duplicateId], [
            'original_issue_id' => $originalId,
            'duplicate_issue_id' => $duplicateId,
            'marked_by_id' => $this->getId('users', ['email' => 'moderator@patchfix.test']),
            'note' => 'Same streetlight issue as PF-000002.',
        ]);
    }

    private function seedIssueComments(): void
    {
        $comments = [
            ['PF-000001', 'citizen2@patchfix.test', 'I passed this area today. The drain is still blocked.', 'public_comment'],
            ['PF-000001', 'moderator@patchfix.test', 'Waiting for verification and additional confirmation.', 'moderator_note'],
            ['PF-000003', 'volunteer@patchfix.test', 'Volunteer team visited the site and work has started.', 'volunteer_update'],
            ['PF-000004', 'citizen3@patchfix.test', 'This is risky during school hours. Needs urgent attention.', 'public_comment'],
        ];

        foreach ($comments as [$ref, $email, $message, $type]) {
            $issueId = $this->getId('issues', ['report_reference' => $ref]);
            $authorId = $this->getId('users', ['email' => $email]);

            $this->upsert('issue_comments', ['issue_id' => $issueId, 'author_id' => $authorId, 'message' => $message], [
                'issue_id' => $issueId,
                'author_id' => $authorId,
                'message' => $message,
                'type' => $type,
            ]);
        }
    }

    private function seedIssueConfirmations(): void
    {
        $userEmails = [
            'citizen1@patchfix.test',
            'citizen2@patchfix.test',
            'citizen3@patchfix.test',
            'volunteer@patchfix.test',
        ];

        foreach (['PF-000001', 'PF-000002', 'PF-000003', 'PF-000004'] as $ref) {
            $issueId = $this->getId('issues', ['report_reference' => $ref]);

            foreach ($userEmails as $email) {
                $userId = $this->getId('users', ['email' => $email]);

                $this->upsert('issue_confirmations', ['issue_id' => $issueId, 'user_id' => $userId], [
                    'issue_id' => $issueId,
                    'user_id' => $userId,
                ]);
            }
        }
    }

    private function seedIssueFollowers(): void
    {
        foreach (['PF-000001', 'PF-000003', 'PF-000004'] as $ref) {
            $issueId = $this->getId('issues', ['report_reference' => $ref]);

            foreach (['citizen2@patchfix.test', 'citizen3@patchfix.test'] as $email) {
                $userId = $this->getId('users', ['email' => $email]);

                $this->upsert('issue_followers', ['issue_id' => $issueId, 'user_id' => $userId], [
                    'issue_id' => $issueId,
                    'user_id' => $userId,
                ]);
            }
        }
    }

    private function seedTeamMembers(): void
    {
        $teamId = $this->getId('volunteer_teams', ['name' => 'Mirpur Response Team']);

        $this->upsert('team_members', ['team_id' => $teamId, 'user_id' => $this->getId('users', ['email' => 'volunteer@patchfix.test'])], [
            'team_id' => $teamId,
            'user_id' => $this->getId('users', ['email' => 'volunteer@patchfix.test']),
            'role' => 'lead',
        ]);

        $this->upsert('team_members', ['team_id' => $teamId, 'user_id' => $this->getId('users', ['email' => 'citizen3@patchfix.test'])], [
            'team_id' => $teamId,
            'user_id' => $this->getId('users', ['email' => 'citizen3@patchfix.test']),
            'role' => 'member',
        ]);
    }

    private function seedNotifications(): void
    {
        $notifications = [
            ['citizen1@patchfix.test', 'issue_verified', 'Your report was verified', 'A moderator verified one of your submitted reports.', 'PF-000002'],
            ['volunteer@patchfix.test', 'issue_assigned', 'New issue assigned', 'You were assigned a high-priority civic issue.', 'PF-000004'],
            ['citizen2@patchfix.test', 'issue_update', 'Issue update posted', 'A volunteer posted an update on an issue you follow.', 'PF-000003'],
        ];

        foreach ($notifications as [$email, $type, $title, $message, $ref]) {
            $userId = $this->getId('users', ['email' => $email]);
            $issueId = $this->getId('issues', ['report_reference' => $ref]);

            $this->upsert('notifications', ['user_id' => $userId, 'type' => $type, 'title' => $title], [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'related_issue_id' => $issueId,
            ]);
        }
    }

    private function seedAuditLogs(): void
    {
        $adminId = $this->getId('users', ['email' => 'admin@patchfix.test']);
        $moderatorId = $this->getId('users', ['email' => 'moderator@patchfix.test']);

        $this->upsert('audit_logs', ['action' => 'seed_database', 'entity_type' => 'system'], [
            'actor_id' => $adminId,
            'action' => 'seed_database',
            'entity_type' => 'system',
            'entity_id' => null,
            'old_values' => null,
            'new_values' => ['message' => 'Demo database seeded.'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PatchFix Seeder',
        ]);

        $this->upsert('audit_logs', ['action' => 'verify_issue', 'entity_type' => 'issue'], [
            'actor_id' => $moderatorId,
            'action' => 'verify_issue',
            'entity_type' => 'issue',
            'entity_id' => $this->getId('issues', ['report_reference' => 'PF-000002']),
            'old_values' => ['status' => 'submitted'],
            'new_values' => ['status' => 'verified'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PatchFix Seeder',
        ]);
    }

    private function upsert(string $table, array $criteria, array $data): int
    {
        $where = [];
        $params = [];

        foreach ($criteria as $column => $value) {
            $param = 'criteria_' . $column;
            $where[] = "{$column} = :{$param}";
            $params[$param] = $this->normalizeValue($value);
        }

        $existingId = $this->connection->fetchOne(
            sprintf('SELECT id FROM %s WHERE %s LIMIT 1', $table, implode(' AND ', $where)),
            $params
        );

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($existingId) {
            if ($this->hasColumn($table, 'updated_at')) {
                $data['updated_at'] = $now;
            }

            $this->connection->update($table, $this->normalizeData($data), ['id' => $existingId]);

            return (int) $existingId;
        }

        if ($this->hasColumn($table, 'created_at') && !isset($data['created_at'])) {
            $data['created_at'] = $now;
        }

        $this->connection->insert($table, $this->normalizeData($data));

        return (int) $this->connection->lastInsertId();
    }

    private function getId(string $table, array $criteria): int
    {
        $where = [];
        $params = [];

        foreach ($criteria as $column => $value) {
            $param = 'criteria_' . $column;
            $where[] = "{$column} = :{$param}";
            $params[$param] = $this->normalizeValue($value);
        }

        $id = $this->connection->fetchOne(
            sprintf('SELECT id FROM %s WHERE %s LIMIT 1', $table, implode(' AND ', $where)),
            $params
        );

        if (!$id) {
            throw new \RuntimeException(sprintf(
                'Could not find row in %s for criteria: %s',
                $table,
                json_encode($criteria)
            ));
        }

        return (int) $id;
    }

    private function normalizeData(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->normalizeValue($value);
        }

        return $data;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];

        $key = "{$table}.{$column}";

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $exists = (bool) $this->connection->fetchOne(
            "
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = :table
              AND column_name = :column
            ",
            [
                'table' => $table,
                'column' => $column,
            ]
        );

        $cache[$key] = $exists;

        return $exists;
    }
}