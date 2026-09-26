<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use App\Support\Brand;
use Inertia\Inertia;
use Inertia\Response;

class CmsPageController extends Controller
{
    public function show(string $slug): Response
    {
        $lookupSlug = match ($slug) {
            'privacy-policy' => 'privacy',
            'terms-of-service' => 'terms',
            default => $slug,
        };

        $page = CmsPage::where('slug', $lookupSlug)
            ->where('published', true)
            ->first();

        if (! $page) {
            if ($lookupSlug === 'privacy') {
                return $this->fallbackPrivacy();
            }
            if ($lookupSlug === 'terms') {
                return $this->fallbackTerms();
            }

            abort(404);
        }

        return Inertia::render('marketing/CmsPage', [
            'page' => [
                'title'            => Brand::apply($page->title),
                'content'          => Brand::apply($page->content),
                'meta_title'       => Brand::apply($page->meta_title),
                'meta_description' => Brand::apply($page->meta_description),
                'layout'           => $page->layout,
            ],
        ]);
    }

    public function privacyAlias(): Response
    {
        return $this->show('privacy');
    }

    public function termsAlias(): Response
    {
        return $this->show('terms');
    }

    private function fallbackPrivacy(): Response
    {
        $brandName = Brand::current();

        return Inertia::render('marketing/CmsPage', [
            'page' => [
                'title'            => 'Privacy Policy',
                'content'          => "<p>Last updated: ".now()->format('F j, Y')."</p>"
                    ."<p>This Privacy Policy explains how {$brandName} (\"we\", \"us\") collects, uses, discloses, and safeguards your information when you use our platform and services.</p>"
                    .'<h2>1. Information We Collect</h2><p>We collect information you provide directly (such as your name, email address, phone number, and billing details), information collected automatically (such as usage data, device information, and cookies), and information from third parties (such as WhatsApp/Meta APIs you connect).</p>'
                    .'<h2>2. How We Use Your Information</h2><p>We use your information to provide and improve our services, process transactions, communicate with you, ensure platform security, and comply with legal obligations.</p>'
                    .'<h2>3. Sharing of Information</h2><p>We do not sell your personal data. We share information only with authorized service providers, when required by law, or with your explicit consent.</p>'
                    .'<h2>4. Security</h2><p>We implement industry-standard technical and organizational security measures, including encryption in transit and at rest, to safeguard your data.</p>'
                    ."<h2>5. Contact Us</h2><p>If you have any questions about this Privacy Policy, please contact us via <a href=\"/contact\">our contact page</a>.</p>",
                'meta_title'       => "Privacy Policy — {$brandName}",
                'meta_description' => "How {$brandName} collects, uses, and protects your personal data.",
                'layout'           => 'legal',
            ],
        ]);
    }

    private function fallbackTerms(): Response
    {
        $brandName = Brand::current();

        return Inertia::render('marketing/CmsPage', [
            'page' => [
                'title'            => 'Terms of Service',
                'content'          => "<p>Last updated: ".now()->format('F j, Y')."</p>"
                    ."<p>These Terms of Service govern your access to and use of {$brandName}. By using our services, you agree to these terms.</p>"
                    .'<h2>1. Accounts</h2><p>You are responsible for maintaining the confidentiality of your account credentials and for all activity under your account.</p>'
                    .'<h2>2. Acceptable Use</h2><p>You agree not to misuse the services, send unsolicited messages (spam), violate messaging platform policies, or infringe the rights of others.</p>'
                    .'<h2>3. Contact</h2><p>For questions about these terms, reach us via <a href=\"/contact\">our contact page</a>.</p>',
                'meta_title'       => "Terms of Service — {$brandName}",
                'meta_description' => "The terms and conditions for using {$brandName}.",
                'layout'           => 'legal',
            ],
        ]);
    }
}
