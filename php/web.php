<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TikTokDownloadController;

// مسار جلب بيانات الفيديو
Route::post("fetch", [TikTokDownloadController::class, 'fetch'])
    ->middleware(['web', 'auth.session'])
    ->name("fetch");

// مسار تحميل الفيديو
Route::post("download", [TikTokDownloadController::class, 'download'])
    ->name("download");

// خريطة الموقع
Route::get("/sitemap.xml", function() {
    // إضافة كود sitemap هنا لاحقاً
    return response('', 404);
})->name('sitemap');

// المجموعة الرئيسية للموقع
Route::middleware(['web', 'theme'])->group(function () {
    Route::view('/tos', "theme::tos")->name('tos');
    Route::view('/privacy', "theme::privacy")->name('privacy');
});

// المجموعة المحلية للصفحات
Route::localization()->middleware(['web', 'theme'])->group(function () {
    Route::match(
        ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '/',
        fn() => view("theme::home")
    )->name('home');
    
    Route::view('/faq', "theme::faq")->name('faq');
    Route::view('/how-to-save', "theme::how-to-save")->name('how-to-save');
    
    // يمكن إضافة PopularVideosController لاحقاً
    Route::get('/popular-videos', function() {
        return response('', 404);
    })->name('popular-videos');
});