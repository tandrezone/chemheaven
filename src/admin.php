<?php

declare(strict_types=1);

use Tandrezone\Ztemp\TemplateEngine;

class Admin
{
    public function __construct()
    {
        // Constructor code here
    }

    public function dashboard(): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $engine = new TemplateEngine(__DIR__ . '/../templates');

        echo $engine->render('admin.html', [
            'title' => 'Admin Dashboard',
            'brand' => 'ChemHeaven',
            'tagline' => 'Curated compounds for a sharper storefront.',
            'heading' => 'Admin Dashboard',
            'message' => 'The admin route now shares the same ztemp shell while keeping a dedicated control panel presentation.',
            'card_one_value' => '12',
            'card_one_label' => 'Pending variant updates',
            'card_two_value' => '04',
            'card_two_label' => 'Store cards in the launch set',
            'card_three_value' => 'Live',
            'card_three_label' => 'Shared layout status',
            'footer_text' => 'ChemHeaven admin mockup powered by the shared ztemp layout.',
        ]);
    }

    public function manageUsers()
    {
        // Code to manage users
    }

    public function settings()
    {
        // Code to manage settings
    }
}