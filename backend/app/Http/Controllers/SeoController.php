<?php

namespace App\Http\Controllers;

use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\Service;
use App\Services\Settings;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public function page(Request $r, Settings $settings)
    {
        $file = base_path('../frontend/dist/index.html');
        if (! is_file($file)) {
            return response('Build the frontend: cd frontend && npm ci && npm run build', 503);
        }
        $locale = $r->segment(1) === 'ar' ? 'ar' : 'en';
        $path = $r->path();
        $private = preg_match('#/(admin|manage|confirmation)(/|$)#', '/'.$path);
        $ar = $locale === 'ar';
        if ($r->segment(2) && ! in_array($r->segment(2), ['services', 'about', 'gallery', 'courses', 'contact', 'book', 'privacy', 'policy', 'admin', 'manage', 'confirmation'], true)) {
            abort(404);
        }
        $titles = ['services' => $ar ? 'خدماتنا' : 'Our services', 'about' => $ar ? 'حكايتنا' : 'Our story', 'gallery' => $ar ? 'معرض الصور' : 'The gallery', 'courses' => $ar ? 'الأكاديمية' : 'The academy', 'contact' => $ar ? 'تواصلي معنا' : 'Visit us', 'book' => $ar ? 'احجزي موعدك' : 'Book your moment', 'privacy' => $ar ? 'الخصوصية' : 'Privacy', 'policy' => $ar ? 'سياسة الحجز' : 'Booking policy'];
        $title = $titles[$r->segment(2)] ?? ($ar ? 'لحظة تخصّكِ' : 'A moment of your own');
        if ($r->segment(3) && in_array($r->segment(2), ['services', 'courses'], true)) {
            $class = $r->segment(2) === 'services' ? Service::class : Course::class;
            $record = $class::where('published', true)->find($r->segment(3));
            if (! $record) {
                abort(404);
            } $value = $record->name ?? $record->title;
            $title = $value[$locale];
        }
        $seo = ContentBlock::whereIn('key', ['seo_title', 'seo_description'])->where('published', true)->get()->keyBy('key');
        if (! $r->segment(2) && isset($seo['seo_title'])) {
            $title = $seo['seo_title']->value[$locale];
        }
        $title .= ' · '.$settings->get('salon_name', 'REMI Nail Spa');
        $description = $ar ? 'اكتشفي خدمات REMI Nail Spa ودورات الأظافر واحجزي لحظتكِ.' : 'Discover REMI Nail Spa beauty services, nail training, and your next moment of care.';
        if (isset($seo['seo_description'])) {
            $description = $seo['seo_description']->value[$locale];
        }
        $base = rtrim(config('app.frontend_url'), '/');
        $canonical = $base.'/'.$path;
        $escape = fn ($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $html = file_get_contents($file);
        $html = preg_replace('#<title>.*?</title>#s', '<title>'.$escape($title).'</title>', $html);
        $meta = '<meta name="description" content="'.$escape($description).'"><link rel="canonical" href="'.$escape($canonical).'">';
        $meta .= '<meta name="robots" content="'.($private ? 'noindex,nofollow' : 'index,follow').'"><meta property="og:title" content="'.$escape($title).'"><meta property="og:description" content="'.$escape($description).'"><meta property="og:url" content="'.$escape($canonical).'"><meta property="og:type" content="website"><meta name="twitter:card" content="summary_large_image">';
        foreach (['en', 'ar'] as $lang) {
            $meta .= '<link rel="alternate" hreflang="'.$lang.'" href="'.$escape($base.preg_replace('#^/(en|ar)#', '/'.$lang, '/'.$path)).'">';
        }
        $html = str_replace('</head>', $meta.'</head>', $html);
        $html = preg_replace('/<html[^>]*>/', '<html lang="'.$locale.'" dir="'.($ar ? 'rtl' : 'ltr').'">', $html);

        return response($html)->header('Content-Type', 'text/html; charset=utf-8')->header('Cache-Control', 'no-cache');
    }

    public function sitemap()
    {
        $paths = ['', '/services', '/about', '/gallery', '/courses', '/contact', '/privacy', '/policy'];
        foreach (Service::where('published', true)->pluck('id') as $id) {
            $paths[] = '/services/'.$id;
        }
        foreach (Course::where('published', true)->pluck('id') as $id) {
            $paths[] = '/courses/'.$id;
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (['en', 'ar'] as $locale) {
            foreach ($paths as $path) {
                $xml .= '<url><loc>'.htmlspecialchars(rtrim(config('app.frontend_url'), '/').'/'.$locale.$path, ENT_XML1).'</loc></url>';
            }
        }

        return response($xml.'</urlset>')->header('Content-Type', 'application/xml');
    }
}
