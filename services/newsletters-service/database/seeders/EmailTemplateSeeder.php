<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create email templates for different categories
        EmailTemplate::factory()
            ->count(5)
            ->active()
            ->ofCategory('newsletter')
            ->create();

        EmailTemplate::factory()
            ->count(3)
            ->active()
            ->ofCategory('promotional')
            ->create();

        EmailTemplate::factory()
            ->count(4)
            ->active()
            ->ofCategory('transactional')
            ->create();

        EmailTemplate::factory()
            ->count(2)
            ->active()
            ->ofCategory('notification')
            ->create();

        // Create some inactive templates
        EmailTemplate::factory()
            ->count(3)
            ->inactive()
            ->create();

        // Create specific templates for common use cases
        $this->createWelcomeTemplate();
        $this->createNewsletterTemplate();
        $this->createPromotionalTemplate();
        $this->createPasswordResetTemplate();
        $this->createOrderConfirmationTemplate();
    }

    /**
     * Create welcome email template.
     */
    private function createWelcomeTemplate(): void
    {
        $this->createOrUpdateTemplate([
            'name' => 'Welcome Email Template',
            'slug' => 'welcome-email',
            'subject' => 'Welcome to {{company_name}}, {{name}}!',
            'html_content' => $this->getWelcomeHtmlContent(),
            'plain_content' => $this->getWelcomePlainContent(),
            'variables' => [
                'name' => 'Full name of the new subscriber',
                'first_name' => 'First name of the new subscriber',
                'email' => 'Email address of the new subscriber',
                'company_name' => 'Name of the company',
                'unsubscribe_url' => 'URL to unsubscribe from emails'
            ],
            'category' => 'transactional',
            'is_active' => true,
            'description' => 'Welcome email sent to new newsletter subscribers',
        ]);
    }

    /**
     * Create newsletter template.
     */
    private function createNewsletterTemplate(): void
    {
        $this->createOrUpdateTemplate([
            'name' => 'Monthly Newsletter Template',
            'slug' => 'monthly-newsletter',
            'subject' => '{{company_name}} Newsletter - {{month}} {{year}}',
            'html_content' => $this->getNewsletterHtmlContent(),
            'plain_content' => $this->getNewsletterPlainContent(),
            'variables' => [
                'name' => 'Full name of the subscriber',
                'first_name' => 'First name of the subscriber',
                'company_name' => 'Name of the company',
                'month' => 'Current month name',
                'year' => 'Current year',
                'featured_article_title' => 'Title of the featured article',
                'featured_article_content' => 'Content of the featured article',
                'unsubscribe_url' => 'URL to unsubscribe from emails'
            ],
            'category' => 'newsletter',
            'is_active' => true,
            'description' => 'Monthly newsletter template with featured content',
        ]);
    }

    /**
     * Create promotional template.
     */
    private function createPromotionalTemplate(): void
    {
        $this->createOrUpdateTemplate([
            'name' => 'Promotional Offer Template',
            'slug' => 'promotional-offer',
            'subject' => '🔥 Exclusive offer for {{first_name}} - {{discount}}% OFF!',
            'html_content' => $this->getPromotionalHtmlContent(),
            'plain_content' => $this->getPromotionalPlainContent(),
            'variables' => [
                'name' => 'Full name of the subscriber',
                'first_name' => 'First name of the subscriber',
                'discount' => 'Discount percentage',
                'offer_title' => 'Title of the promotional offer',
                'offer_description' => 'Description of the promotional offer',
                'cta_text' => 'Call-to-action button text',
                'cta_url' => 'Call-to-action button URL',
                'expiry_date' => 'Offer expiration date',
                'company_name' => 'Name of the company',
                'unsubscribe_url' => 'URL to unsubscribe from emails'
            ],
            'category' => 'promotional',
            'is_active' => true,
            'description' => 'Template for promotional offers and discounts',
        ]);
    }

    /**
     * Create password reset template.
     */
    private function createPasswordResetTemplate(): void
    {
        $this->createOrUpdateTemplate([
            'name' => 'Password Reset Template',
            'slug' => 'password-reset',
            'subject' => 'Reset your {{company_name}} password',
            'html_content' => $this->getPasswordResetHtmlContent(),
            'plain_content' => $this->getPasswordResetPlainContent(),
            'variables' => [
                'name' => 'Full name of the user',
                'first_name' => 'First name of the user',
                'reset_url' => 'Password reset URL',
                'company_name' => 'Name of the company',
                'expiry_time' => 'Link expiration time (e.g., 24 hours)'
            ],
            'category' => 'transactional',
            'is_active' => true,
            'description' => 'Password reset email template',
        ]);
    }

    /**
     * Create order confirmation template.
     */
    private function createOrderConfirmationTemplate(): void
    {
        $this->createOrUpdateTemplate([
            'name' => 'Order Confirmation Template',
            'slug' => 'order-confirmation',
            'subject' => 'Order confirmed - {{order_number}}',
            'html_content' => $this->getOrderConfirmationHtmlContent(),
            'plain_content' => $this->getOrderConfirmationPlainContent(),
            'variables' => [
                'name' => 'Customer full name',
                'first_name' => 'Customer first name',
                'order_number' => 'Order number',
                'order_date' => 'Order date',
                'order_total' => 'Total order amount',
                'items' => 'List of ordered items',
                'shipping_address' => 'Shipping address',
                'tracking_url' => 'Order tracking URL',
                'company_name' => 'Name of the company'
            ],
            'category' => 'transactional',
            'is_active' => true,
            'description' => 'Order confirmation email template',
        ]);
    }

    /**
     * Create or update template handling soft deletes.
     */
    private function createOrUpdateTemplate(array $templateData): void
    {
        $existing = EmailTemplate::withTrashed()->where('slug', $templateData['slug'])->first();
        
        if ($existing) {
            if ($existing->trashed()) {
                // Restore and update the soft-deleted record
                $existing->restore();
            }
            $existing->update($templateData);
        } else {
            // Create new record
            EmailTemplate::create($templateData);
        }
    }

    // HTML Content Methods
    private function getWelcomeHtmlContent(): string
    {
        return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Welcome</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: #007bff; color: white; padding: 30px; text-align: center;">
        <h1 style="margin: 0;">Welcome to {{company_name}}!</h1>
    </div>
    <div style="padding: 30px;">
        <h2 style="color: #333;">Hi {{first_name}},</h2>
        <p>Thank you for subscribing to our newsletter! We\'re thrilled to have you join our community.</p>
        <div style="background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px;">
            <h3 style="margin-top: 0;">What you can expect:</h3>
            <ul>
                <li>Weekly industry insights and trends</li>
                <li>Exclusive offers and early access to products</li>
                <li>Helpful tips and resources</li>
            </ul>
        </div>
        <p>We promise to respect your inbox and provide valuable content.</p>
        <p>Welcome aboard!<br>The {{company_name}} Team</p>
    </div>
    <div style="background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;">
        <p><a href="{{unsubscribe_url}}" style="color: #ffffff;">Unsubscribe</a></p>
    </div>
</body>
</html>';
    }

    private function getNewsletterHtmlContent(): string
    {
        return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Newsletter</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: #f8f9fa; padding: 20px; text-align: center; border-bottom: 3px solid #007bff;">
        <h1 style="color: #333; margin: 0;">{{company_name}} Newsletter</h1>
        <p style="color: #666; margin: 10px 0 0 0;">{{month}} {{year}} Edition</p>
    </div>
    <div style="padding: 30px;">
        <p>Hello {{first_name}},</p>
        <p>Here\'s what\'s happening this month:</p>
        
        <div style="border-left: 4px solid #007bff; padding-left: 20px; margin: 25px 0;">
            <h3 style="color: #007bff; margin: 0 0 10px 0;">{{featured_article_title}}</h3>
            <p>{{featured_article_content}}</p>
        </div>
        
        <p>Thank you for being a valued subscriber!</p>
        <p>Best regards,<br>The {{company_name}} Team</p>
    </div>
    <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d;">
        <p><a href="{{unsubscribe_url}}" style="color: #6c757d;">Unsubscribe</a></p>
    </div>
</body>
</html>';
    }

    private function getPromotionalHtmlContent(): string
    {
        return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Special Offer</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; text-align: center;">
        <h1 style="margin: 0; font-size: 32px;">{{offer_title}}</h1>
        <p style="margin: 15px 0 0 0; font-size: 20px;">{{discount}}% OFF - Just for you, {{first_name}}!</p>
    </div>
    <div style="padding: 30px;">
        <p>Hi {{name}},</p>
        <p>{{offer_description}}</p>
        
        <div style="text-align: center; margin: 40px 0;">
            <a href="{{cta_url}}" style="background: #28a745; color: white; padding: 18px 35px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; font-size: 18px;">
                {{cta_text}}
            </a>
        </div>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 5px; text-align: center;">
            <p style="margin: 0; font-weight: bold;">⏰ Limited Time: Expires {{expiry_date}}</p>
        </div>
        
        <p>Happy shopping!</p>
        <p>The {{company_name}} Team</p>
    </div>
    <div style="background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6c757d;">
        <p><a href="{{unsubscribe_url}}" style="color: #6c757d;">Unsubscribe</a></p>
    </div>
</body>
</html>';
    }

    private function getPasswordResetHtmlContent(): string
    {
        return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Password Reset</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="padding: 30px;">
        <h1 style="color: #333;">Password Reset Request</h1>
        <p>Hi {{first_name}},</p>
        <p>You requested a password reset for your {{company_name}} account.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{reset_url}}" style="background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
                Reset Your Password
            </a>
        </div>
        
        <p><strong>This link will expire in {{expiry_time}}.</strong></p>
        <p>If you didn\'t request this reset, please ignore this email.</p>
        
        <p>Best regards,<br>The {{company_name}} Team</p>
    </div>
</body>
</html>';
    }

    private function getOrderConfirmationHtmlContent(): string
    {
        return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Order Confirmation</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: #28a745; color: white; padding: 20px; text-align: center;">
        <h1 style="margin: 0;">✅ Order Confirmed!</h1>
    </div>
    <div style="padding: 30px;">
        <p>Hi {{first_name}},</p>
        <p>Thank you for your order! Your order has been confirmed and is being processed.</p>
        
        <div style="background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px;">
            <h3 style="margin: 0 0 15px 0;">Order Details</h3>
            <p><strong>Order Number:</strong> {{order_number}}</p>
            <p><strong>Order Date:</strong> {{order_date}}</p>
            <p><strong>Total:</strong> {{order_total}}</p>
        </div>
        
        <h3>Items Ordered:</h3>
        <div>{{items}}</div>
        
        <h3>Shipping Address:</h3>
        <p>{{shipping_address}}</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{tracking_url}}" style="background: #007bff; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block;">
                Track Your Order
            </a>
        </div>
        
        <p>Thank you for your business!</p>
        <p>The {{company_name}} Team</p>
    </div>
</body>
</html>';
    }

    // Plain Content Methods
    private function getWelcomePlainContent(): string
    {
        return "Welcome to {{company_name}}!

Hi {{first_name}},

Thank you for subscribing to our newsletter! We're thrilled to have you join our community.

What you can expect:
- Weekly industry insights and trends
- Exclusive offers and early access to products
- Helpful tips and resources

We promise to respect your inbox and provide valuable content.

Welcome aboard!
The {{company_name}} Team

Unsubscribe: {{unsubscribe_url}}";
    }

    private function getNewsletterPlainContent(): string
    {
        return "{{company_name}} Newsletter - {{month}} {{year}}

Hello {{first_name}},

Here's what's happening this month:

FEATURED: {{featured_article_title}}
{{featured_article_content}}

Thank you for being a valued subscriber!

Best regards,
The {{company_name}} Team

Unsubscribe: {{unsubscribe_url}}";
    }

    private function getPromotionalPlainContent(): string
    {
        return "{{offer_title}} - {{discount}}% OFF

Hi {{name}},

{{offer_description}}

{{cta_text}}: {{cta_url}}

⏰ Limited Time: Expires {{expiry_date}}

Happy shopping!
The {{company_name}} Team

Unsubscribe: {{unsubscribe_url}}";
    }

    private function getPasswordResetPlainContent(): string
    {
        return "Password Reset Request

Hi {{first_name}},

You requested a password reset for your {{company_name}} account.

Reset your password: {{reset_url}}

This link will expire in {{expiry_time}}.

If you didn't request this reset, please ignore this email.

Best regards,
The {{company_name}} Team";
    }

    private function getOrderConfirmationPlainContent(): string
    {
        return "Order Confirmed!

Hi {{first_name}},

Thank you for your order! Your order has been confirmed and is being processed.

Order Details:
Order Number: {{order_number}}
Order Date: {{order_date}}
Total: {{order_total}}

Items Ordered:
{{items}}

Shipping Address:
{{shipping_address}}

Track your order: {{tracking_url}}

Thank you for your business!
The {{company_name}} Team";
    }
}