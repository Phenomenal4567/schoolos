<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_landing_page_renders_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Everything your school needs to run better.');
        $response->assertSee('SchoolOS brings student records, staff management, attendance, results, fees, payments, examinations, and everyday school operations into one connected platform.');
        $response->assertSee("Running a school shouldn't mean running around.", false);
        $response->assertSee('Everything your school needs. One connected system.');
        $response->assertSee('Everything your school needs to operate smoothly.');
        $response->assertSee('One platform. Every role.');
        $response->assertSee('Attendance without the morning paperwork.');
        $response->assertSee('Make school fees transparent and effortless to collect.');
        $response->assertSee('Your school, organized online.');
        $response->assertSee('From onboarding to full operation in minutes.');
        $response->assertSee('Engineered for integrity. Built on trust.');
        $response->assertSee('Answers to common questions.');
        $response->assertSee('Ready to bring your school operations together?');
        $response->assertSee(route('public.onboarding.create'));
        $response->assertSee(route('login'));

        // Assert placeholders are completely removed
        $response->assertDontSee('Placeholder Admin');
        $response->assertDontSee('Placeholder Teacher');
        $response->assertDontSee('Placeholder Parent');

        // Assert roles and trust pillars
        $response->assertSee('School Owner');
        $response->assertSee('School Admin');
        $response->assertSee('Teacher');
        $response->assertSee('Bursar');
        $response->assertSee('Parent');
        $response->assertSee('Tenant Data Isolation');
        $response->assertSee('Role-Based Access (RBAC)');
        $response->assertSee('Cryptographic Webhooks');
        $response->assertSee('Immutable Audit Trails');
    }
}
