<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EmailTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $templateNames = [
            'Welcome Email', 'Newsletter Template', 'Promotional Campaign',
            'Product Update', 'Order Confirmation', 'Password Reset',
            'Account Activation', 'Monthly Report', 'Event Invitation',
            'Seasonal Greeting', 'Survey Request', 'Thank You Message'
        ];

        $name = $this->faker->randomElement($templateNames);

        return [
            'name' => $name,
            'slug' => Str::slug($name . '-' . $this->faker->randomNumber(4)),
            'subject' => $this->faker->randomElement([
                'Welcome to {{company_name}}, {{name}}!',
                '{{company_name}} Newsletter - {{month}} Edition',
                'Exclusive offer for {{first_name}}',
                'Your {{product_name}} update is here',
                'Don\'t miss out, {{name}}!',
                'Thank you {{first_name}} for your support'
            ]),
            'html_content' => $this->generateHtmlTemplate(),
            'plain_content' => $this->generatePlainTemplate(),
            'variables' => [
                'name' => 'Full name of the recipient',
                'first_name' => 'First name of the recipient',
                'email' => 'Email address of the recipient',
                'company_name' => 'Name of the company',
                'product_name' => 'Name of the product',
                'unsubscribe_url' => 'URL to unsubscribe from emails',
                'month' => 'Current month',
                'year' => 'Current year'
            ],
            'category' => $this->faker->randomElement(['newsletter', 'transactional', 'promotional', 'notification']),
            'is_active' => $this->faker->boolean(85), // 85% chance of being active
            'created_by' => $this->faker->optional()->numberBetween(1, 10),
            'description' => $this->faker->optional()->sentence(),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => now(),
        ];
    }

    /**
     * Generate realistic HTML template content.
     */
    private function generateHtmlTemplate(): string
    {
        $templates = [
            '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>{{subject}}</title>
            </head>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="background: #f8f9fa; padding: 20px; text-align: center;">
                    <h1 style="color: #333; margin: 0;">{{company_name}}</h1>
                </div>
                <div style="padding: 30px;">
                    <h2 style="color: #007bff;">Hello {{name}}!</h2>
                    <p>We hope this message finds you well. Here\'s what we wanted to share with you:</p>
                    
                    <div style="background: #e9ecef; padding: 20px; margin: 20px 0; border-radius: 5px;">
                        <h3 style="margin-top: 0; color: #495057;">Featured Content</h3>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                    </div>
                    
                    <p>Thank you for being part of our community, {{first_name}}!</p>
                    <p>Best regards,<br>The {{company_name}} Team</p>
                </div>
                <div style="background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;">
                    <p>© {{year}} {{company_name}}. All rights reserved.</p>
                    <p><a href="{{unsubscribe_url}}" style="color: #ffffff;">Unsubscribe</a> | Contact Support</p>
                </div>
            </body>
            </html>',

            '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>{{subject}}</title>
            </head>
            <body style="font-family: Georgia, serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
                <div style="border-top: 4px solid #007bff;">
                    <div style="padding: 40px;">
                        <h1 style="color: #333; font-size: 28px; margin-bottom: 30px;">Hi {{first_name}},</h1>
                        
                        <p style="font-size: 16px; line-height: 1.6; color: #555;">
                            We\'re excited to share some updates with you from {{company_name}}.
                        </p>
                        
                        <div style="margin: 30px 0; padding: 25px; border-left: 4px solid #28a745; background: #f8fff9;">
                            <h3 style="margin: 0 0 15px 0; color: #28a745;">Latest Updates</h3>
                            <p style="margin: 0; color: #666;">Here are the most important updates we wanted to share with you this {{month}}.</p>
                        </div>
                        
                        <p style="font-size: 16px; line-height: 1.6; color: #555;">
                            We appreciate your continued support and engagement.
                        </p>
                        
                        <p style="margin-top: 40px;">
                            Warm regards,<br>
                            <strong>{{company_name}} Team</strong>
                        </p>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-top: 1px solid #dee2e6; text-align: center;">
                        <p style="margin: 0; font-size: 12px; color: #6c757d;">
                            <a href="{{unsubscribe_url}}" style="color: #6c757d;">Unsubscribe</a> | 
                            {{company_name}} © {{year}}
                        </p>
                    </div>
                </div>
            </body>
            </html>'
        ];

        return $this->faker->randomElement($templates);
    }

    /**
     * Generate realistic plain text template content.
     */
    private function generatePlainTemplate(): string
    {
        return "Hello {{name}},

We hope this message finds you well. Here's what we wanted to share with you from {{company_name}}:

FEATURED CONTENT
----------------
Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.

Thank you for being part of our community, {{first_name}}!

Best regards,
The {{company_name}} Team

© {{year}} {{company_name}}. All rights reserved.
Unsubscribe: {{unsubscribe_url}}";
    }

    /**
     * Indicate that the template is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the template is of a specific category.
     */
    public function ofCategory(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    /**
     * Indicate that the template has specific variables.
     */
    public function withVariables(array $variables): static
    {
        return $this->state(fn (array $attributes) => [
            'variables' => $variables,
        ]);
    }
}