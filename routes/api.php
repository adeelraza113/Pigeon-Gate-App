<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\APIController;
use App\Events\NewMessage;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Ably\AblyRest;

// routes/web.php
Route::post('/register', [APIController::class, 'register']);
Route::post('/login', [APIController::class, 'login']);
Route::post('/generate-otp', [APIController::class, 'generateOtp']);
Route::post('/verify-otp', [APIController::class, 'verifyOtp']);
Route::post('/reset-password-with-otp', [APIController::class, 'resetPasswordWithOtp']);




Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/users', [APIController::class, 'getUserNames']);
   Route::get('/fetch-messages/{channelName}', [APIController::class, 'fetchMessages']);
   Route::delete('/user', [APIController::class, 'deleteUser']);
   Route::put('/update', [APIController::class, 'updateProfileImage']);
   

   
    Route::get('/user-profile', [APIController::class, 'getWalkingUserProfileView']);
    Route::delete('/pigeon', [APIController::class, 'deletePigeon']);

    Route::group(['as' => "pigeon-", 'prefix' => 'pigeon'], function () {
        Route::get('/all', [APIController::class, 'getAllPigeons'])->name('all');
        Route::get('/get_by_id/{id}', [APIController::class, 'getPigeonByID'])->name('get_by_id');
        Route::post('/add', [APIController::class, 'addPigeon'])->name('add');
        Route::post('/boost', [APIController::class, 'boostPigeon']);
        Route::get('/boosts', [APIController::class, 'getBoosts']);
        Route::post('approveBoost', [APIController::class, 'approveBoost']);
        Route::delete('/ignore-boost', [APIController::class, 'ignoreBoost']);
        Route::post('/report', [APIController::class, 'reportPost']);
        Route::get('/reports', [APIController::class, 'getReports']);
        Route::delete('/ignore-report', [APIController::class, 'ignoreReport']);

    });

    Route::group(['as' => "shop-", 'prefix' => 'shop'], function () {
        Route::get('/all', [APIController::class, 'getAllShops'])->name('all');
        Route::post('/create', [APIController::class, 'createShop'])->name('create');
        Route::get('/get_by_id/{id}', [APIController::class, 'getShopByID'])->name('get_by_id');
        Route::get('/get_by_user/{id}', [APIController::class, 'getShopByUser'])->name('get_by_user');
        Route::get('/search/{keyword}', [APIController::class, 'searchShops'])->name('search');
    });
    
    Route::group(['as' => "product-", 'prefix' => 'product'], function () {
        Route::get('/all', [APIController::class, 'getAllProductsByShop'])->name('all');
        Route::post('/add', [APIController::class, 'addProduct'])->name('add');
        Route::get('/search/{keyword}', [APIController::class, 'searchShops'])->name('search');
        Route::get('/get_by_id/{id}', [APIController::class, 'getShopByID'])->name('get_by_id');
        Route::get('/get_by_user/{id}', [APIController::class, 'getShopByUser'])->name('get_by_user');
        Route::post('/review', [APIController::class, 'addReview']);
    });

    Route::group(['as' => "article-", 'prefix' => 'article'], function () {
        Route::get('/all', [APIController::class, 'getAllArticles'])->name('all');
        Route::post('/add', [APIController::class, 'addArticle'])->name('add');
        Route::post('/views', [APIController::class, 'viewsOnArticle']);
        Route::post('/likes', [APIController::class, 'likesOnArticle']);
        Route::post('/comments', [APIController::class, 'commentsOnArticle']);
    });

    Route::group(['as' => "event-", 'prefix' => 'event'], function () {
        Route::get('/all', [APIController::class, 'getAllEvents'])->name('all');
        Route::post('/add', [APIController::class, 'addEvent'])->name('add');
    });

    Route::post('/follow', [APIController::class, 'followUser'])->name('follow');
    Route::post('/like', [APIController::class, 'likePigeonPost'])->name('like');
    Route::post('/view', [APIController::class, 'viewPigeonPost'])->name('view');
    Route::get('/user-details', [APIController::class, 'userDetails'])->name('user-details');
    Route::get('/users/messages', [APIController::class, 'getUserMessages']);
    Route::post('/send-message', [APIController::class, 'sendMessage']);
     Route::get('/messages', [APIController::class, 'getMessages']);
    Route::post('/notifications', [APIController::class, 'createNotification']);
    Route::put('/mark-read', [APIController::class, 'markAsRead']);
    Route::get('/notifications', [APIController::class, 'getUnreadNotifications']); 
    Route::get('/pigeonnames', [APIController::class, 'getPigeonNames']);
    Route::post('/clubscore', [APIController::class, 'addClubScore']);

    
    Route::group(['as' => "club-", 'prefix' => 'club'], function () {
        Route::get('/all', [APIController::class, 'getAllClubs'])->name('all');
        Route::post('/create', [APIController::class, 'createClub'])->name('create');
        Route::get('/get-club-members/{id}', [APIController::class, 'getAllMembersOfClub'])->name('get-club-members');
        Route::get('/get-club-follow-requests/{id}', [APIController::class, 'getAllFollowRequestsOfClub'])->name('get-club-follow-requests');
        Route::post('/approve-club-join-request', [APIController::class, 'approveClubJoinRequest'])->name('approve-club-join-request');
        Route::post('/request-to-join-club', [APIController::class, 'requetToJoinClub'])->name('request-to-join-club');
        Route::get('/details', [APIController::class, 'getClubDetails'])->name('details');
        Route::post('/assign-role', [APIController::class, 'assignClubRole'])->name('assign-role');
        Route::post('/add-club-posts', [APIController::class, 'createClubPost'])->name('add-club-posts');
        Route::get('/terms-joining-fee', [APIController::class, 'getClubTermsAndJoiningFee'])->name('terms-joining-fee');
        Route::get('/pending-requests', [APIController::class, 'getPendingRequests'])->name('pending-requests');
    });
});