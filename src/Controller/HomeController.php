<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $recentIssues = [
            [
                'title' => 'Blocked drain causing water buildup',
                'category' => 'Drainage',
                'area' => 'Dhanmondi',
                'status' => 'submitted',
                'priority' => 'high',
                'confirmations' => 18,
            ],
            [
                'title' => 'Broken streetlight near main road',
                'category' => 'Streetlight',
                'area' => 'Mirpur',
                'status' => 'verified',
                'priority' => 'normal',
                'confirmations' => 9,
            ],
            [
                'title' => 'Garbage pile left uncollected',
                'category' => 'Waste',
                'area' => 'Mohammadpur',
                'status' => 'in_progress',
                'priority' => 'high',
                'confirmations' => 26,
            ],
        ];

        return $this->render('home/index.html.twig', [
            'recentIssues' => $recentIssues,
        ]);
    }
}