<?php

use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/robots.txt', fn () => response("User-agent: *\nDisallow: /api/\nDisallow: /en/admin\nDisallow: /ar/admin\nDisallow: /en/manage\nDisallow: /ar/manage\nDisallow: /en/confirmation\nDisallow: /ar/confirmation\nSitemap: ".rtrim(config('app.frontend_url'), '/')."/sitemap.xml\n")->header('Content-Type', 'text/plain'));
Route::get('/', fn () => redirect('/en'));
Route::get('/admin', fn () => redirect('/en/admin'));
Route::get('/admin/login', fn () => redirect('/en/admin/login'));
Route::get('/{locale}/{path?}', [SeoController::class, 'page'])->where('locale', 'en|ar')->where('path', '.*');
