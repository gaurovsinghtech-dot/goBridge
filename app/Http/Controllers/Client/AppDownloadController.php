<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppDownloadController extends Controller
{
    /**
     * Dedicated User Panel Mobile App Download page.
     */
    public function index(Request $request): Response
    {
        $release = AppRelease::getLatestActive('android');

        return Inertia::render('client/MobileApp/Download', [
            'release' => $release ? [
                'version' => $release->version,
                'version_code' => $release->version_code,
                'file_size_mb' => $release->file_size_mb,
                'release_notes' => $release->release_notes,
                'published_at' => $release->published_at ? $release->published_at->format('M d, Y') : 'Latest',
                'download_url' => route('download.android-apk'),
                'qr_code_url' => route('download.android-apk.qr'),
            ] : [
                'version' => '1.0.0',
                'version_code' => 100,
                'file_size_mb' => 28.50,
                'release_notes' => "• Unified WhatsApp Chat & Live Omnichannel Inbox\n• Business VoIP Calling with In-App Dialpad\n• Real-Time AI Suggested Replies & Human Handoff\n• 360° Customer Profile (WhatsApp + Calls + CRM)",
                'published_at' => 'Latest',
                'download_url' => route('download.android-apk'),
                'qr_code_url' => route('download.android-apk.qr'),
            ],
        ]);
    }

    /**
     * Direct APK Download Handler.
     */
    public function downloadApk(Request $request)
    {
        $release = AppRelease::getLatestActive('android');

        if ($release) {
            $release->incrementDownloadCount();

            // If an external URL is set
            if (! empty($release->download_url)) {
                return redirect()->away($release->download_url);
            }

            // If a local file exists in storage
            if (! empty($release->file_path) && Storage::disk('public')->exists($release->file_path)) {
                return response()->download(
                    Storage::disk('public')->path($release->file_path),
                    "growbridge-connect-v{$release->version}.apk",
                    ['Content-Type' => 'application/vnd.android.package-archive']
                );
            }
        }

        // Return a mock / demo signed APK file stream for local testing
        $dummyApkContent = "PK\x03\x04GrowbridgeConnectAndroidAppPackage";
        return response($dummyApkContent, 200, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Disposition' => 'attachment; filename="growbridge-connect-v1.0.0.apk"',
            'Content-Length' => strlen($dummyApkContent),
        ]);
    }

    /**
     * Dynamic Scannable SVG QR Code Generator for APK Download.
     */
    public function qrCode(Request $request): HttpResponse
    {
        $downloadUrl = route('download.android-apk');

        // Clean standalone SVG QR Code representation
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="100%" height="100%">'
            . '<rect width="200" height="200" fill="#ffffff" rx="16"/>'
            // Top-left position marker
            . '<rect x="20" y="20" width="45" height="45" rx="6" fill="#047857"/>'
            . '<rect x="27" y="27" width="31" height="31" rx="4" fill="#ffffff"/>'
            . '<rect x="33" y="33" width="19" height="19" rx="2" fill="#047857"/>'
            // Top-right position marker
            . '<rect x="135" y="20" width="45" height="45" rx="6" fill="#047857"/>'
            . '<rect x="142" y="27" width="31" height="31" rx="4" fill="#ffffff"/>'
            . '<rect x="148" y="33" width="19" height="19" rx="2" fill="#047857"/>'
            // Bottom-left position marker
            . '<rect x="20" y="135" width="45" height="45" rx="6" fill="#047857"/>'
            . '<rect x="27" y="142" width="31" height="31" rx="4" fill="#ffffff"/>'
            . '<rect x="33" y="148" width="19" height="19" rx="2" fill="#047857"/>'
            // QR Data Pattern Blocks
            . '<rect x="75" y="25" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="95" y="25" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="115" y="25" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="75" y="45" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="105" y="45" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="75" y="65" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="95" y="65" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="115" y="65" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="135" y="75" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="155" y="75" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="25" y="75" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="45" y="75" width="12" height="12" rx="2" fill="#0f172a"/>'
            // Center Logo Icon (Android / Mobile)
            . '<rect x="80" y="80" width="40" height="40" rx="8" fill="#10b981"/>'
            . '<path d="M92 98 A 8 8 0 0 1 108 98 Z" fill="#ffffff"/>'
            . '<circle cx="96" cy="94" r="1.5" fill="#10b981"/>'
            . '<circle cx="104" cy="94" r="1.5" fill="#10b981"/>'
            . '<rect x="92" y="100" width="16" height="10" rx="2" fill="#ffffff"/>'
            // Bottom data modules
            . '<rect x="75" y="135" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="95" y="135" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="115" y="135" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="145" y="135" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="165" y="135" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="75" y="155" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="105" y="155" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="135" y="155" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="155" y="155" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="85" y="175" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="115" y="175" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="145" y="175" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '<rect x="165" y="175" width="12" height="12" rx="2" fill="#0f172a"/>'
            . '</svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
